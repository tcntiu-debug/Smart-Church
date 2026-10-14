<?php

namespace App\Http\Controllers;

use App\Services\GuideArchiveService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class WeekListController extends Controller
{
    /**
     * Members of this sub-group make up the weekly call list callers.
     */
    private const CALLER_SUBGROUP = 'Touch Point 7';

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display the week list of first timers
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $member_role = $user->member_role ?? 'Guest';
        $userCampusId = $user->campus_id ?? 0;
        
        // Only Workers, Admins, Super Users can access
        if (!in_array($member_role, ['Worker', 'Admin', 'Admins', 'Workers', 'Super User'])) {
            return redirect('/mytask')->with('error', 'Access Denied');
        }

        // Get filter parameters
        // The default window is the current week (Sunday to Saturday).
        [$start_date_filter, $end_date_filter, $defaultWindow] = $this->resolveWindow($request);
        $guest_type_filter = $request->get('guest_type', '');
        $church_type_id_filter = $request->get('church_type_id', '');

        $page_title = $defaultWindow
            ? "Weekly Prayer List (" . date('M d', strtotime($start_date_filter)) . " - " . date('M d, Y', strtotime($end_date_filter)) . ")"
            : "First Timers from " . date('M d, Y', strtotime($start_date_filter)) . " to " . date('M d, Y', strtotime($end_date_filter));

        // Get the results. The bulk buttons (assign all callers / unassign all
        // guides) rebuild exactly this window through the same helper.
        $firstTimers = $this->windowRows($request, $start_date_filter, $end_date_filter, $member_role, $userCampusId);

        // Get guest types for filter dropdown (also filtered by campus)
        $guestTypesQuery = DB::table('first_timer')
            ->whereNotNull('attendant_type')
            ->where('attendant_type', '!=', '');
        if ($member_role !== 'Super User' && !empty($userCampusId)) {
            $guestTypesQuery->where('campus_id', $userCampusId);
        }
        $guestTypes = $guestTypesQuery->distinct()->pluck('attendant_type');

        // Get church types for filter dropdown
        $churchTypes = DB::table('church_type')
            ->orderBy('church_type_name', 'asc')
            ->get();


        // Process duplicate detection
        $rows = [];
        $duplicateMap = [
            'phone' => [],
            'email' => [],
            'name' => []
        ];

        foreach ($firstTimers as $row) {
            $rows[] = $row;
            
            $phone = trim($row->phone_number);
            $email = strtolower(trim($row->email ?? ''));
            $name = strtolower(trim($row->first_name . $row->last_name));
            
            if ($phone) {
                $duplicateMap['phone'][$phone] = ($duplicateMap['phone'][$phone] ?? 0) + 1;
            }
            if ($email) {
                $duplicateMap['email'][$email] = ($duplicateMap['email'][$email] ?? 0) + 1;
            }
            if ($name) {
                $duplicateMap['name'][$name] = ($duplicateMap['name'][$name] ?? 0) + 1;
            }
        }

        // Process guide assignments
        foreach ($rows as $row) {
            $trimmedPhone = trim($row->phone_number);
            $email = strtolower(trim($row->email ?? ''));
            
            $row->isDuplicate = (
                ($duplicateMap['phone'][$trimmedPhone] ?? 0) > 1 || 
                ($email && ($duplicateMap['email'][$email] ?? 0) > 1) ||
                (($duplicateMap['name'][strtolower(trim($row->first_name . $row->last_name))] ?? 0) > 1)
            );
            
            // Guide assignment
            $tracking = DB::table('member_tracking_followup')
                ->where('first_timer_id', $row->first_timer_id)
                ->first();
            
            $row->isGuideAssigned = false;
            $row->guide_name = '';
            $row->guide_phone = '';
            $row->guide_gender = '';
            
            if ($tracking && $tracking->tiu_member_id) {
                $guide = DB::table('tiu_member')
                    ->where('tiu_member_id', $tracking->tiu_member_id)
                    ->first();
                
                if ($guide) {
                    $row->isGuideAssigned = true;
                    $row->guide_name = $guide->first_name . ' ' . $guide->last_name;
                    $row->guide_phone = '234' . ltrim($guide->phone_number, '0');
                    $row->guide_gender = $guide->gender ?? '';
                }
            }
            
            // Format phone number for WhatsApp
            $row->whatsapp_phone = '234' . ltrim($row->phone_number, '0');
            
            // Generate WhatsApp message based on guest type
            $row->whatsapp_message = $this->generateWhatsAppMessage(
                $row->first_name . ' ' . $row->last_name,
                $row->attendant_type,
                $row->isGuideAssigned,
                $row->guide_name
            );
        }

        // ------------------------------------------------------------------
        // Weekly Call List (caller assignment for the Touch Point 7 team)
        // ------------------------------------------------------------------
        [$callers, $callerNames, $assignments, $groupedForPrint] = $this->buildCallerData($rows);

        return view('weeklist.index', array_merge(
            compact('rows', 'page_title', 'guestTypes', 'churchTypes',
                'start_date_filter', 'end_date_filter', 'guest_type_filter', 'church_type_id_filter', 'member_role',
                'callers', 'callerNames', 'assignments', 'groupedForPrint'),
            ['callerSubgroup' => self::CALLER_SUBGROUP, 'isAdmin' => $this->canManageAssignments($member_role)]
        ));
    }

    /**
     * Build the caller list and the per first-timer assignments used by the
     * Weekly Call List modal.
     *
     * Callers are TIU members whose subgroup matches self::CALLER_SUBGROUP
     * ("Touch Point 7"). The subgroup column is stored either as plain text
     * ("Touch Point 7") or as a JSON list of "<department_id>::<name>" entries
     * (e.g. '["23::Touch Point 7"]'), so a LIKE match covers both formats.
     *
     * Every first timer in the selected window is then handed to a caller by a
     * round-robin that keeps genders together: male first timers rotate through
     * the male callers, female first timers through the female callers.
     *
     * @param  array  $rows
     * @return array{0: \Illuminate\Support\Collection, 1: array, 2: array, 3: array}
     */
    private function buildCallerData(array $rows): array
    {
        $callers = DB::table('tiu_member')
            ->where('subgroup', 'like', '%' . self::CALLER_SUBGROUP . '%')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['tiu_member_id', 'first_name', 'last_name', 'gender']);

        $callerNames = [];
        $maleCallers = [];
        $femaleCallers = [];
        $allCallers = [];

        foreach ($callers as $caller) {
            $caller->full_name = trim($caller->first_name . ' ' . $caller->last_name);
            $callerNames[$caller->tiu_member_id] = $caller->full_name;
            $allCallers[] = $caller->tiu_member_id;

            // Each gender gets its own round-robin lane.
            $gender = $this->normalizeGender($caller->gender);

            if ($gender === 'male') {
                $maleCallers[] = $caller->tiu_member_id;
            } elseif ($gender === 'female') {
                $femaleCallers[] = $caller->tiu_member_id;
            }
        }

        // Manual overrides saved from the modal, keyed by first_timer_id.
        $manual = [];
        foreach ($this->getCallerAssignments() as $assignment) {
            $manual[$assignment->first_timer_id] = [
                'id'   => $assignment->caller_member_id,
                'name' => $assignment->caller_name,
            ];
        }

        $assignments = [];      // first_timer_id => caller tiu_member_id (int|null)
        $groupedForPrint = [];  // caller name => list of people to call
        $maleIdx = 0;
        $femaleIdx = 0;
        $otherIdx = 0;
        $maleCount = count($maleCallers);
        $femaleCount = count($femaleCallers);
        $allCount = count($allCallers);

        // The round-robin walks the whole selected window (all $rows), so the
        // first timers of one gender are spread evenly across that gender's
        // callers instead of piling up on the first one.
        foreach ($rows as $row) {
            $ftId = $row->first_timer_id;
            $callerId = null;
            $callerName = 'Unassigned';
            $gender = $this->normalizeGender($row->gender);

            if (!empty($manual[$ftId]['id'])) {
                // Manual override takes precedence over round-robin.
                $callerId = (int) $manual[$ftId]['id'];
                $callerName = $manual[$ftId]['name'] ?: ($callerNames[$callerId] ?? 'Unassigned');
            } elseif ($gender === 'male' && $maleCount > 0) {
                $callerId = $maleCallers[$maleIdx % $maleCount];
                $maleIdx++;
                $callerName = $callerNames[$callerId] ?? 'Unassigned';
            } elseif ($gender === 'female' && $femaleCount > 0) {
                $callerId = $femaleCallers[$femaleIdx % $femaleCount];
                $femaleIdx++;
                $callerName = $callerNames[$callerId] ?? 'Unassigned';
            } elseif ($gender === '' && $allCount > 0) {
                // Blank / placeholder gender ("select"): no gender lane applies,
                // so rotate the whole team to make sure the person is still called.
                $callerId = $allCallers[$otherIdx % $allCount];
                $otherIdx++;
                $callerName = $callerNames[$callerId] ?? 'Unassigned';
            }

            $assignments[$ftId] = $callerId;

            $groupedForPrint[$callerName][] = [
                'name'       => trim($row->first_name . ' ' . $row->last_name),
                'phone'      => $row->phone_number,
                'gender'     => $row->gender,
                'guest_type' => $row->attendant_type,
                'age'        => ($row->age !== null && $row->age !== '') ? $row->age : 'Not specified',
            ];
        }

        return [$callers, $callerNames, $assignments, $groupedForPrint];
    }

    /**
     * Normalise a stored gender to "male", "female" or "".
     *
     * The public first-timer form stores the placeholder option text
     * ("select") on some records and the tiu_member table can hold an empty
     * string, so anything unrecognised collapses to "" and is treated as
     * "no gender".
     *
     * @param  mixed  $gender
     */
    private function normalizeGender($gender): string
    {
        $gender = strtolower(trim((string) $gender));

        if (in_array($gender, ['male', 'm'], true)) {
            return 'male';
        }

        if (in_array($gender, ['female', 'f'], true)) {
            return 'female';
        }

        return '';
    }

    /**
     * Generate WhatsApp message based on guest type
     */
    private function generateWhatsAppMessage($name, $attendantType, $isGuideAssigned, $guideName)
    {
        $guideLine = $isGuideAssigned ? "\n\nA team member ($guideName) will reach out to you soon." : "";
        
        $messages = [
            "New To TCN (May Join TCN)" => "Dear $name,\n\nIt was a blessing to have you with us at TCN Ikorodu today! We're grateful for the time you spent in worship with us and pray that the Lord continues to lead and guide you.\n\nNo matter where your journey takes you, know that you're always welcome here.$guideLine\n\nTCN Ikorodu",
            
            "New To TCN (Will Join TCN)" => "Dear $name,\n\nWe are excited to welcome you to TCN Ikorodu! We thank God for leading you here and look forward to growing in faith together.$guideLine\n\nTCN Ikorodu",
            
            "TCN Member (Relocating to Ikd)" => "Dear $name,\n\nIt was a joy to worship with you at TCN Ikorodu today! We love connecting with our TCN family.\n\nMay God bless and strengthen you.\n\nWelcome home!$guideLine\n\nTCN Ikorodu",
            
            "TCN Member (In Transit)" => "Dear $name,\n\nIt was a joy to worship with you at TCN Ikorodu today! We love connecting with our TCN family.\n\nMay God bless and strengthen you.\n\nWelcome home!$guideLine\n\nTCN Ikorodu"
        ];
        
        $defaultMessage = "Dear $name,\n\nThank you for visiting TCN Ikorodu! We pray God blesses you abundantly.\n\nTCN Ikorodu";
        
        return $messages[$attendantType] ?? $defaultMessage;
    }

    /**
     * Fetch all saved manual caller assignments (tolerant of a missing table).
     *
     * @return \Illuminate\Support\Collection
     */
    private function getCallerAssignments()
    {
        try {
            $this->ensureCallerAssignmentsTable();

            return DB::table('caller_assignments')->get();
        } catch (\Throwable $e) {
            Log::error('Could not load caller assignments: ' . $e->getMessage());

            return collect();
        }
    }

    /**
     * Create the caller_assignments table on first use when migrations cannot be
     * run remotely (SFTP-only hosting has no shell for "php artisan migrate").
     * Safe to call repeatedly.
     */
    private function ensureCallerAssignmentsTable(): void
    {
        if (Schema::hasTable('caller_assignments')) {
            return;
        }

        Schema::create('caller_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('first_timer_id')->unique();
            $table->unsignedBigInteger('caller_member_id')->nullable();
            $table->string('caller_name', 150)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Save (or clear) the manual caller assignment for a first timer (AJAX).
     */
    public function saveCallerAssignment(Request $request)
    {
        $user = Auth::user();
        $member_role = $user->member_role ?? 'Guest';

        if (!in_array($member_role, ['Worker', 'Admin', 'Admins', 'Workers', 'Super User'])) {
            return response()->json(['success' => false, 'message' => 'Access Denied'], 403);
        }

        $data = $request->validate([
            'first_timer_id'   => 'required|integer',
            'caller_member_id' => 'nullable|integer',
        ]);

        $firstTimerId = (int) $data['first_timer_id'];
        $callerId = !empty($data['caller_member_id']) ? (int) $data['caller_member_id'] : null;

        try {
            $this->ensureCallerAssignmentsTable();

            // An empty selection reverts the row back to automatic round-robin.
            if (!$callerId) {
                DB::table('caller_assignments')->where('first_timer_id', $firstTimerId)->delete();

                return response()->json(['success' => true, 'caller_name' => null, 'message' => 'Reverted to auto assignment']);
            }

            $caller = DB::table('tiu_member')->where('tiu_member_id', $callerId)->first();
            if (!$caller) {
                return response()->json(['success' => false, 'message' => 'Selected caller not found'], 422);
            }

            $callerName = trim($caller->first_name . ' ' . $caller->last_name);

            DB::table('caller_assignments')->updateOrInsert(
                ['first_timer_id' => $firstTimerId],
                [
                    'caller_member_id' => $callerId,
                    'caller_name'      => $callerName,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]
            );

            return response()->json(['success' => true, 'caller_name' => $callerName, 'message' => 'Saved']);
        } catch (\Throwable $e) {
            Log::error('Caller assignment save failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not save assignment'], 500);
        }
    }

    /**
     * Who may use the bulk buttons on the week list: Admins and Super Users.
     *
     * Workers still see the page and can still change one caller at a time, they
     * just cannot release or reassign the whole window in one click.
     *
     * @param  mixed  $memberRole
     */
    private function canManageAssignments($memberRole): bool
    {
        return in_array($memberRole, ['Admin', 'Admins', 'Super User'], true);
    }

    /**
     * The date window the week list is showing: the requested filter, or the
     * current Sunday-to-Saturday week.
     *
     * @return array{0: string, 1: string, 2: bool}  [start, end, wasDefaulted]
     */
    private function resolveWindow(Request $request): array
    {
        $start = (string) $request->get('start_date', '');
        $end = (string) $request->get('end_date', '');

        if ($start !== '' && $end !== '') {
            return [$start, $end, false];
        }

        $today = Carbon::today();
        $sunday = $today->copy()->subDays($today->dayOfWeek); // 0 = Sunday
        $saturday = $sunday->copy()->addDays(6);

        return [$sunday->format('Y-m-d'), $saturday->format('Y-m-d'), true];
    }

    /**
     * The first timers the week list is showing, with the filters the page
     * applies (campus scope, date window, guest type, church type).
     *
     * Shared by the page and the admin bulk actions so a button always acts on
     * exactly what the admin can see.
     *
     * @param  string  $memberRole
     * @param  mixed   $userCampusId
     * @return \Illuminate\Support\Collection
     */
    private function windowRows(Request $request, string $start, string $end, $memberRole, $userCampusId)
    {
        $query = DB::table('first_timer as ft')
            ->leftJoin('church_type as ct', 'ft.church_type_id', '=', 'ct.id');

        // Campus filter - only show first timers from the user's campus.
        if ($memberRole !== 'Super User' && !empty($userCampusId)) {
            $query->where('ft.campus_id', $userCampusId);
        }

        $query->whereBetween('ft.register_date', [$start . ' 00:00:00', $end . ' 23:59:59']);

        $guestType = (string) $request->get('guest_type', '');
        if ($guestType !== '') {
            $query->where('ft.attendant_type', $guestType);
        }

        $churchTypeId = (string) $request->get('church_type_id', '');
        if ($churchTypeId !== '' && $churchTypeId !== 'All') {
            $query->where('ft.church_type_id', $churchTypeId);
        }

        return $query->select('ft.*', 'ct.church_type_name')
            ->orderBy('ft.register_date', 'desc')
            ->get();
    }

    /**
     * Admin-only: save the automatic round-robin for every first timer in the
     * current window.
     *
     * Instead of setting a caller row by row in the "View Call List" modal, one
     * click locks in the spread the modal is showing: existing manual picks are
     * kept, everything else gets its round-robin caller.
     */
    public function assignAllCallers(Request $request)
    {
        $user = Auth::user();
        $member_role = $user->member_role ?? 'Guest';

        if (!$this->canManageAssignments($member_role)) {
            return response()->json(['success' => false, 'message' => 'Access Denied'], 403);
        }

        [$start, $end] = $this->resolveWindow($request);
        $rows = $this->windowRows($request, $start, $end, $member_role, $user->campus_id ?? 0);

        if ($rows->isEmpty()) {
            return response()->json(['success' => true, 'assigned' => 0, 'skipped' => 0, 'message' => 'No first timers in this window.']);
        }

        try {
            $this->ensureCallerAssignmentsTable();

            // The same round-robin the modal renders, manual overrides included.
            [, $callerNames, $assignments] = $this->buildCallerData($rows->values()->all());

            $assigned = 0;
            $skipped = 0;

            DB::transaction(function () use ($rows, $callerNames, $assignments, &$assigned, &$skipped) {
                foreach ($rows as $row) {
                    $callerId = $assignments[$row->first_timer_id] ?? null;

                    if (!$callerId) {
                        // Nobody in this first timer's gender lane (or no callers).
                        $skipped++;

                        continue;
                    }

                    DB::table('caller_assignments')->updateOrInsert(
                        ['first_timer_id' => $row->first_timer_id],
                        [
                            'caller_member_id' => $callerId,
                            'caller_name'      => $callerNames[$callerId] ?? null,
                            'created_at'       => now(),
                            'updated_at'       => now(),
                        ]
                    );

                    $assigned++;
                }
            });

            $message = "Saved {$assigned} caller assignment(s).";

            if ($skipped > 0) {
                $message .= " {$skipped} first timer(s) had no caller available.";
            }

            return response()->json([
                'success'  => true,
                'assigned' => $assigned,
                'skipped'  => $skipped,
                'message'  => $message,
            ]);
        } catch (\Throwable $e) {
            Log::error('Bulk caller assignment failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not save the caller list'], 500);
        }
    }

    /**
     * Admin-only: release every guide in the current window in one click.
     *
     * Same move the drag & drop page makes for a single first timer
     * (AdminAssignmentController::ajaxUnassign): the follow-up row keeps its
     * content and only loses the guide (tiu_member_id = 0), so the tasks, logs
     * and comments the guide recorded stay on record.
     */
    public function unassignAllGuides(Request $request)
    {
        $user = Auth::user();
        $member_role = $user->member_role ?? 'Guest';

        if (!$this->canManageAssignments($member_role)) {
            return response()->json(['success' => false, 'message' => 'Access Denied'], 403);
        }

        [$start, $end] = $this->resolveWindow($request);
        $rows = $this->windowRows($request, $start, $end, $member_role, $user->campus_id ?? 0);

        $firstTimerIds = $rows->pluck('first_timer_id')->map('intval')->filter()->unique()->values()->all();

        if (empty($firstTimerIds)) {
            return response()->json(['success' => true, 'unassigned' => 0, 'message' => 'No first timers in this window.']);
        }

        try {
            $trackings = DB::table('member_tracking_followup')
                ->whereIn('first_timer_id', $firstTimerIds)
                ->where('tiu_member_id', '>', 0)
                ->get(['tracking_id', 'first_timer_id']);

            if ($trackings->isEmpty()) {
                return response()->json(['success' => true, 'unassigned' => 0, 'message' => 'No guide is assigned in this window.']);
            }

            $trackingIds = $trackings->pluck('tracking_id')->all();
            $releasedIds = $trackings->pluck('first_timer_id')->map('intval')->unique()->values()->all();

            DB::transaction(function () use ($trackingIds, $releasedIds) {
                // tiu_member_id = 0 and not a delete: the guide's feedback stays.
                DB::table('member_tracking_followup')
                    ->whereIn('tracking_id', $trackingIds)
                    ->update(['tiu_member_id' => 0]);

                DB::table('first_timer')
                    ->whereIn('first_timer_id', $releasedIds)
                    ->update(['status' => 'Unassigned', 'status_change_date' => now()]);
            });

            return response()->json([
                'success'    => true,
                'unassigned' => count($trackingIds),
                'message'    => 'Released ' . count($trackingIds) . ' first timer(s). Their follow-up notes were kept.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Bulk guide unassign failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not unassign the guides'], 500);
        }
    }

    /**
     * Admin-only: who guided a first timer after they were released, with the
     * last comment each guide logged.
     *
     * This is the answer to "the cron deletes the follow-up at 8 weeks but I
     * still want to see the last guide and the last comment": the follow-up row
     * is copied into member_tracking_followup_archive before it is deleted, and
     * this page reads that copy.
     */
    public function guideHistory(Request $request)
    {
        $user = Auth::user();
        $member_role = $user->member_role ?? 'Guest';

        if (!$this->canManageAssignments($member_role)) {
            return redirect()->route('weeklist.index')->with('error', 'Access Denied');
        }

        // Covers hosts that cannot run migrations after a deploy.
        app(GuideArchiveService::class)->ensureArchiveTable();

        $search = trim((string) $request->get('search', ''));
        $reason = trim((string) $request->get('reason', ''));

        $query = DB::table('member_tracking_followup_archive as a')
            ->leftJoin('first_timer as ft', 'a.first_timer_id', '=', 'ft.first_timer_id')
            ->leftJoin('tiu_member as tm', 'a.tiu_member_id', '=', 'tm.tiu_member_id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('ft.first_name', 'like', $like)
                    ->orWhere('ft.last_name', 'like', $like)
                    ->orWhere('tm.first_name', 'like', $like)
                    ->orWhere('tm.last_name', 'like', $like);
            });
        }

        if ($reason !== '') {
            $query->where('a.archive_reason', $reason);
        }

        $records = $query->select(
            'a.archive_id',
            'a.first_timer_id',
            'a.tiu_member_id',
            'a.archive_reason',
            'a.archived_at',
            'a.timeStamp_registered',
            'a.followup_response',
            'a.followup_response_new',
            'a.tiu_member_comment',
            'ft.first_name as ft_first_name',
            'ft.last_name as ft_last_name',
            'ft.phone_number as ft_phone',
            'tm.first_name as guide_first_name',
            'tm.last_name as guide_last_name'
        )
            ->orderByDesc('a.archived_at')
            ->orderByDesc('a.archive_id')
            ->paginate(50)
            ->withQueryString();

        // "The last comment" straight out of the archived task log.
        $records->getCollection()->transform(function ($row) {
            $last = GuideArchiveService::lastActivity($row);

            $row->ft_name = trim(($row->ft_first_name ?? '') . ' ' . ($row->ft_last_name ?? ''));
            $row->guide_name = trim(($row->guide_first_name ?? '') . ' ' . ($row->guide_last_name ?? ''));
            $row->last_comment = $last['comment'];
            $row->last_outcome = $last['outcome'];
            $row->last_task = $last['task'];
            $row->last_logged_at = $last['logged_at'];

            return $row;
        });

        $reasons = DB::table('member_tracking_followup_archive')
            ->whereNotNull('archive_reason')
            ->distinct()
            ->orderBy('archive_reason')
            ->pluck('archive_reason');

        return view('weeklist.guide-history', compact('records', 'reasons', 'search', 'reason'));
    }
}