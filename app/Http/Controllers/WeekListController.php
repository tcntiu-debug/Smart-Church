<?php

namespace App\Http\Controllers;

use App\Services\GuideArchiveService;
use App\Services\NotificationService;
use App\Services\SubgroupAssignmentService;
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
     * Team offered on the first visit and after Reset.
     *
     * The team staffing the call list is no longer fixed: /weeklist has a
     * multi-select touch point filter (resolveTouchpoints()) because the team
     * in charge of the weekly calls rotates from time to time.
     */
    private const DEFAULT_CALLER_SUBGROUP = 'Touch Point 7';

    /**
     * Writes the "you have been given first timers to call" alerts (item 5(b)).
     *
     * @var \App\Services\NotificationService
     */
    private $notifications;

    public function __construct(NotificationService $notifications)
    {
        $this->notifications = $notifications;

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
        // One or more guest types can be combined (`guest_type[]`).
        $guest_type_filter = $this->resolveGuestTypes($request);
        $church_type_id_filter = $request->get('church_type_id', '');

        // Which touch point(s) staff the call list. The visitor picks them by
        // hand, because the team on call rotates from time to time.
        $touchpointOptions = $this->touchpointOptions();
        $selectedTouchpoints = $this->resolveTouchpoints($request, $touchpointOptions);

        $page_title = $defaultWindow
            ? "Weekly Prayer List (" . date('M d', strtotime($start_date_filter)) . " - " . date('M d, Y', strtotime($end_date_filter)) . ")"
            : "First Timers from " . date('M d, Y', strtotime($start_date_filter)) . " to " . date('M d, Y', strtotime($end_date_filter));

        // Get the results. The admin bulk actions (the modal's "Assign all
        // callers" and the toolbar's "Unassign all guides") rebuild exactly
        // this window through the same helper.
        $firstTimers = $this->windowRows($request, $start_date_filter, $end_date_filter, $member_role, $userCampusId);

        // Get guest types for filter dropdown (also filtered by campus)
        $guestTypesQuery = DB::table('first_timer')
            ->whereNotNull('attendant_type')
            ->where('attendant_type', '!=', '');
        if ($member_role !== 'Super User' && !empty($userCampusId)) {
            $guestTypesQuery->forCurrentCampus('campus_id', $userCampusId);
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
        // Weekly Call List (caller assignment for the selected touch points)
        // ------------------------------------------------------------------
        [$callers, $callerNames, $assignments, $groupedForPrint] = $this->buildCallerData($rows, $selectedTouchpoints);

        return view('weeklist.index', array_merge(
            compact('rows', 'page_title', 'guestTypes', 'churchTypes',
                'start_date_filter', 'end_date_filter', 'guest_type_filter', 'church_type_id_filter', 'member_role',
                'callers', 'callerNames', 'assignments', 'groupedForPrint',
                'touchpointOptions', 'selectedTouchpoints'),
            ['isAdmin' => $this->canManageAssignments($member_role)]
        ));
    }

    /**
     * Touch points that may staff the weekly call list.
     *
     * Read from the real sub_group table (department 23), so a new touch point
     * shows up in the filter without a code change. When that lookup fails on a
     * half-migrated host, the names actually stored on members are used.
     *
     * @return array<int, string>
     */
    private function touchpointOptions(): array
    {
        $names = [];

        try {
            $names = SubgroupAssignmentService::groupNamesForDepartment(
                SubgroupAssignmentService::TOUCHPOINT_DEPARTMENT_ID
            );
        } catch (\Throwable $e) {
            Log::error('Could not load the week list touch points: ' . $e->getMessage());
        }

        if ($names === []) {
            try {
                $names = DB::table('tiu_member')
                    ->whereNotNull('subgroup')
                    ->where('subgroup', '!=', '')
                    ->pluck('subgroup')
                    ->flatMap(function ($raw) {
                        return SubgroupAssignmentService::namesForDepartment(
                            $raw,
                            SubgroupAssignmentService::TOUCHPOINT_DEPARTMENT_ID
                        );
                    })
                    ->all();
            } catch (\Throwable $e) {
                Log::error('Could not derive the week list touch points: ' . $e->getMessage());
            }
        }

        $options = [];
        foreach ($names as $name) {
            $name = trim((string) $name);

            if ($name !== '' && !in_array($name, $options, true)) {
                $options[] = $name;
            }
        }

        sort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    /**
     * The touch point filter the visitor submitted, limited to real groups.
     *
     * The form posts `touchpoints[]`; `touchpoint` is accepted as well so old
     * links keep working. An empty or unknown selection falls back to the
     * team the page used before the filter existed (Touch Point 7, or the first
     * group on file when that one is gone).
     *
     * @param  array<int, string>  $options
     * @return array<int, string>
     */
    private function resolveTouchpoints(Request $request, array $options): array
    {
        $requested = $request->input('touchpoints', $request->input('touchpoint', []));

        if (!is_array($requested)) {
            $requested = [$requested];
        }

        $lookup = [];
        foreach ($options as $option) {
            $lookup[strtolower($option)] = $option;
        }

        $selected = [];
        foreach ($requested as $value) {
            if (!is_string($value)) {
                continue;
            }

            $key = strtolower(trim($value));

            if (isset($lookup[$key]) && !in_array($lookup[$key], $selected, true)) {
                $selected[] = $lookup[$key];
            }
        }

        if ($selected !== []) {
            return $selected;
        }

        $default = strtolower(self::DEFAULT_CALLER_SUBGROUP);
        if (isset($lookup[$default])) {
            return [$lookup[$default]];
        }

        return $options === [] ? [] : [$options[0]];
    }

    /**
     * The guest type filter the visitor submitted.
     *
     * The form posts `guest_type[]`, so several guest types can be combined; a
     * plain `guest_type` string (old links and the mobile API) is still
     * understood. Blank values are dropped, and an empty result means "every
     * guest type" - the filter is simply not applied.
     *
     * @return array<int, string>
     */
    private function resolveGuestTypes(Request $request): array
    {
        $requested = $request->input('guest_type', []);

        if (!is_array($requested)) {
            $requested = [$requested];
        }

        $types = [];
        foreach ($requested as $value) {
            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);

            if ($value !== '' && !in_array($value, $types, true)) {
                $types[] = $value;
            }
        }

        return $types;
    }

    /**
     * Build the caller list and the per first-timer assignments used by the
     * Weekly Call List modal.
     *
     * Callers are TIU members assigned to at least one of the touch points the
     * filter on the page is set to. The subgroup column is stored either as
     * plain text ("Touch Point 7") or as a JSON list of
     * "<department_id>::<name>" entries (e.g. '["23::Touch Point 7"]'), so
     * SubgroupAssignmentService::likePatterns() covers both shapes.
     *
     * Every first timer in the selected window is then handed to a caller by a
     * round-robin that keeps genders together: male first timers rotate through
     * the male callers, female first timers through the female callers.
     *
     * @param  array  $rows
     * @param  array<int, string>  $touchpointNames  Touch points chosen in the filter
     * @return array{0: \Illuminate\Support\Collection, 1: array, 2: array, 3: array}
     */
    private function buildCallerData(array $rows, array $touchpointNames = []): array
    {
        $query = DB::table('tiu_member');

        if ($touchpointNames === []) {
            // Nothing selected and nothing on file: offer no caller, so every
            // row falls back to "Unassigned" instead of a stale team.
            $query->whereRaw('1 = 0');
        } else {
            $query->where(function ($nested) use ($touchpointNames) {
                foreach ($touchpointNames as $touchpointName) {
                    foreach (SubgroupAssignmentService::likePatterns(
                        SubgroupAssignmentService::TOUCHPOINT_DEPARTMENT_ID,
                        $touchpointName
                    ) as $pattern) {
                        $nested->orWhere('subgroup', 'LIKE', $pattern);
                    }
                }
            });
        }

        $callers = $query
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['tiu_member_id', 'first_name', 'last_name', 'gender']);

        $callerNames = [];
        $callerIds = [];
        $maleCallers = [];
        $femaleCallers = [];
        $allCallers = [];

        foreach ($callers as $caller) {
            $caller->full_name = trim($caller->first_name . ' ' . $caller->last_name);
            $callerNames[$caller->tiu_member_id] = $caller->full_name;
            $callerIds[(int) $caller->tiu_member_id] = true;
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
        // A pick that belongs to a team outside the current filter is ignored,
        // so switching touch points really does switch the call list.
        $manual = [];
        foreach ($this->getCallerAssignments() as $assignment) {
            if (!isset($callerIds[(int) $assignment->caller_member_id])) {
                continue;
            }

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

            // The dropdown only lists the selected teams; reject a crafted
            // request that picks somebody outside them.
            $touchpoints = $this->resolveTouchpoints($request, $this->touchpointOptions());

            if (!SubgroupAssignmentService::isAssignedToAny(
                $caller->subgroup ?? null,
                SubgroupAssignmentService::TOUCHPOINT_DEPARTMENT_ID,
                $touchpoints
            )) {
                return response()->json([
                    'success' => false,
                    'message' => 'That member is not in the selected touch point(s): ' . implode(', ', $touchpoints),
                ], 422);
            }

            $callerName = trim($caller->first_name . ' ' . $caller->last_name);

            // Who holds the row right now, so re-picking the same caller is silent.
            $previousCallerId = (int) DB::table('caller_assignments')
                ->where('first_timer_id', $firstTimerId)
                ->value('caller_member_id');

            DB::table('caller_assignments')->updateOrInsert(
                ['first_timer_id' => $firstTimerId],
                [
                    'caller_member_id' => $callerId,
                    'caller_name'      => $callerName,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]
            );

            // The caller learns about the first timer they have just been handed.
            $notified = 0;

            if ($previousCallerId !== $callerId) {
                $notified = $this->notifyCallers(
                    [$callerId => [$this->firstTimerName(DB::table('first_timer')->where('first_timer_id', $firstTimerId)->first(), $firstTimerId)]],
                    [$callerId => $callerName],
                    $user->campus_id ?? null
                );
            }

            return response()->json([
                'success'     => true,
                'caller_name' => $callerName,
                'notified'    => $notified,
                'message'     => $notified > 0 ? 'Saved, caller notified' : 'Saved',
            ]);
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
     * Tell each caller which first timers are now theirs to call (item 5(b)).
     *
     * One notification per caller and per change: the bell shows what was added,
     * and the browser alert (see resources/views/layouts/app.blade.php) points at
     * the week list so the caller can open it straight away.
     *
     * @param  array<int, array<int, string>>  $newForCaller  caller id => first timer names
     * @param  array<int, string>  $callerNames  caller id => name
     * @param  mixed  $campusId
     * @return int  How many callers were notified
     */
    private function notifyCallers(array $newForCaller, array $callerNames, $campusId = null): int
    {
        if ($newForCaller === []) {
            return 0;
        }

        $messages = [];

        foreach ($newForCaller as $callerId => $names) {
            $count = count($names);

            $messages[$callerId] = [
                'title' => $count === 1
                    ? 'You have 1 new first timer to call'
                    : "You have {$count} new first timers to call",
                'body'  => $this->notifications->names($names, 3),
                'data'  => [
                    'url'          => '/weeklist',
                    'caller_name'  => $callerNames[$callerId] ?? null,
                    'first_timers' => $names,
                ],
            ];
        }

        return $this->notifications->notifyEach(
            $messages,
            NotificationService::TYPE_CALLER_ASSIGNMENT,
            $campusId ? (int) $campusId : null
        );
    }

    /**
     * Tell each guide which first timers are now theirs to follow up (item 11).
     *
     * The mirror image of notifyCallers(): one alert per guide, the
     * `guide_assignment` type, and a click that lands on /my-tasks - where the
     * follow-up tasks this assignment just created are waiting.
     *
     * @param  array<int, array<int, string>>  $newForGuide  guide id => first timer names
     * @param  array<int, string>  $guideNames  guide id => name
     * @param  array<int, int>  $firstIdForGuide  guide id => the first timer the bell opens
     * @param  mixed  $campusId
     * @return int  How many guides were notified
     */
    private function notifyGuides(array $newForGuide, array $guideNames, array $firstIdForGuide = [], $campusId = null): int
    {
        if ($newForGuide === []) {
            return 0;
        }

        $messages = [];

        foreach ($newForGuide as $guideId => $names) {
            $count = count($names);

            $messages[$guideId] = [
                'title' => $count === 1
                    ? 'You have 1 new first timer to follow up'
                    : "You have {$count} new first timers to follow up",
                'body'  => $this->notifications->names($names, 3),
                'data'  => [
                    'url'            => '/my-tasks',
                    'guide_name'     => $guideNames[$guideId] ?? null,
                    'first_timer_id' => $firstIdForGuide[$guideId] ?? null,
                    'first_timers'   => $names,
                ],
            ];
        }

        return $this->notifications->notifyEach(
            $messages,
            NotificationService::TYPE_GUIDE_ASSIGNMENT,
            $campusId ? (int) $campusId : null
        );
    }

    /**
     * The Week 1 follow-up payload a fresh assignment starts with: the `task`
     * row the drag & drop board reads (wid = 1, campus filtered), decoded with an
     * empty log per task.
     *
     * '{}' when the row is missing or unusable - what
     * AdminAssignmentController::ajaxAssign() falls back to as well.
     */
    private function weekOneFollowUpJson(): string
    {
        $questions = DB::table('task')
            ->where('wid', 1)
            ->forCurrentCampus('campus_id', Auth::user()->campus_id ?? 0)
            ->value('questions');

        if (!$questions) {
            return '{}';
        }

        $decoded = json_decode(html_entity_decode($questions, ENT_QUOTES | ENT_HTML5), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return '{}';
        }

        foreach ($decoded as &$task) {
            if (is_array($task)) {
                $task['logs'] = [];
            }
        }
        unset($task);

        return json_encode(['week1' => $decoded]);
    }

    /**
     * Move an existing follow-up row to another guide with the writes
     * AdminAssignmentController::ajaxAssign() makes for a dropped card: the new
     * guide, rank 1 and a fresh timestamp, and the Week 1 payload rebuilt only
     * when the stored one is unusable (the guide's own log has to survive).
     *
     * With nothing to rebuild from the guide columns are still written - the
     * single-card path would leave such a row untouched, which in a bulk run
     * would silently drop an assignment.
     *
     * @param  string  $storedJson  The payload currently on the row
     * @param  string  $weekOneJson  The rebuilt Week 1 payload ('{}' if unknown)
     */
    private function moveFollowUpToGuide(int $firstTimerId, int $guideId, string $storedJson, string $weekOneJson): void
    {
        $update = [
            'tiu_member_id'        => $guideId,
            'followup_rank'        => 1,
            'timeStamp_registered' => now(),
        ];

        if (strlen($storedJson) < 10 && $weekOneJson !== '{}') {
            // The same "broken payload" guard ajaxAssign() uses.
            $update['followup_response_new'] = $weekOneJson;
        }

        DB::table('member_tracking_followup')
            ->where('first_timer_id', $firstTimerId)
            ->update($update);
    }

    /**
     * The display name of a week list row (the query selects `ft.*`, so the row
     * carries first_name / last_name) or of a bare first_timer lookup.
     *
     * @param  mixed  $row
     * @param  mixed  $firstTimerId  Used when the row is missing or unnamed
     */
    private function firstTimerName($row, $firstTimerId = null): string
    {
        $row = is_object($row) ? $row : (object) [];
        $name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        $id = $row->first_timer_id ?? $firstTimerId;

        return $id ? 'First timer #' . (int) $id : 'A first timer';
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
     * applies (campus scope, date window, guest type(s), church type).
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
            $query->forCurrentCampus('ft.campus_id', $userCampusId);
        }

        $query->whereBetween('ft.register_date', [$start . ' 00:00:00', $end . ' 23:59:59']);

        $guestTypes = $this->resolveGuestTypes($request);
        if ($guestTypes !== []) {
            $query->whereIn('ft.attendant_type', $guestTypes);
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

        // Exactly the team the modal is showing, so one click cannot hand the
        // list to a touch point the admin did not select.
        $selectedTouchpoints = $this->resolveTouchpoints($request, $this->touchpointOptions());

        if ($rows->isEmpty()) {
            return response()->json(['success' => true, 'assigned' => 0, 'skipped' => 0, 'message' => 'No first timers in this window.']);
        }

        try {
            $this->ensureCallerAssignmentsTable();

            // The same round-robin the modal renders, manual overrides included.
            [, $callerNames, $assignments] = $this->buildCallerData($rows->values()->all(), $selectedTouchpoints);

            $assigned = 0;
            $skipped = 0;

            // caller id => the first timers that are new to that caller, so the
            // alert only mentions what this click actually changed.
            $newForCaller = [];

            // Who holds each row right now: clicking the button twice must not
            // alert the same caller about the same first timer again.
            $currentCallers = DB::table('caller_assignments')
                ->whereIn('first_timer_id', $rows->pluck('first_timer_id')->all())
                ->pluck('caller_member_id', 'first_timer_id');

            DB::transaction(function () use ($rows, $callerNames, $assignments, $currentCallers, &$assigned, &$skipped, &$newForCaller) {
                foreach ($rows as $row) {
                    $callerId = $assignments[$row->first_timer_id] ?? null;

                    if (!$callerId) {
                        // Nobody in this first timer's gender lane (or no callers).
                        $skipped++;

                        continue;
                    }

                    if ((int) ($currentCallers[$row->first_timer_id] ?? 0) !== (int) $callerId) {
                        $newForCaller[(int) $callerId][] = $this->firstTimerName($row);
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

            // Every caller hears about the list they have just been given.
            $notified = $this->notifyCallers($newForCaller, $callerNames, $user->campus_id ?? null);

            $message = "Saved {$assigned} caller assignment(s).";

            if ($skipped > 0) {
                $message .= " {$skipped} first timer(s) had no caller available.";
            }

            if ($notified > 0) {
                $message .= " {$notified} caller(s) notified.";
            }

            return response()->json([
                'success'  => true,
                'assigned' => $assigned,
                'skipped'  => $skipped,
                'notified' => $notified,
                'message'  => $message,
            ]);
        } catch (\Throwable $e) {
            Log::error('Bulk caller assignment failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not save the caller list'], 500);
        }
    }

    /**
     * Admin-only: hand every first timer the modal is showing to the caller in
     * its own row, the way a card is handed to a guide on the drag & drop board.
     *
     * This is the "Assign all callers" button inside the Weekly Call List modal.
     * The modal already shows who will call each first timer (a manual pick
     * first, the round-robin otherwise - WeekListController::buildCallerData()),
     * so one click commits that whole plan as the real follow-up assignment:
     *
     *   * the caller in the row becomes `member_tracking_followup.tiu_member_id`,
     *     with rank 1, a fresh timestamp and the Week 1 tasks a drag & drop would
     *     have started them with (a broken payload is rebuilt),
     *   * `first_timer.status` becomes "Assigned" - or "Reassigned" when the row
     *     already had a guide,
     *   * every guide who actually gained a first timer gets one
     *     `guide_assignment` alert pointing at /my-tasks.
     *
     * That is AdminAssignmentController::ajaxAssign() applied to the whole window
     * at once: the same role check, the same window filters, and a guide whose
     * first timer did not change is not alerted a second time.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignGuides(Request $request)
    {
        $user = Auth::user();
        $member_role = $user->member_role ?? 'Guest';

        if (!$this->canManageAssignments($member_role)) {
            return response()->json(['success' => false, 'message' => 'Access Denied'], 403);
        }

        [$start, $end] = $this->resolveWindow($request);
        $rows = $this->windowRows($request, $start, $end, $member_role, $user->campus_id ?? 0);

        // The same touch point filter the modal was built with.
        $selectedTouchpoints = $this->resolveTouchpoints($request, $this->touchpointOptions());

        if ($rows->isEmpty()) {
            return response()->json(['success' => true, 'assigned' => 0, 'reassigned' => 0, 'skipped' => 0, 'notified' => 0, 'message' => 'No first timers in this window.']);
        }

        try {
            // Exactly what the modal is showing: manual pick first, round-robin
            // for everything else, gender lanes kept.
            [, $callerNames, $assignments] = $this->buildCallerData($rows->values()->all(), $selectedTouchpoints);

            $firstTimerIds = $rows->pluck('first_timer_id')->map('intval')->filter()->unique()->values()->all();

            // Who holds each row right now, so clicking the button twice neither
            // re-alerts a guide nor throws away the task log they already filled.
            $current = DB::table('member_tracking_followup')
                ->whereIn('first_timer_id', $firstTimerIds)
                ->get(['first_timer_id', 'tiu_member_id', 'followup_response_new'])
                ->keyBy('first_timer_id');

            // The Week 1 payload a brand new assignment starts with (one read of
            // the task row for the whole window, not one per first timer).
            $weekOneJson = $this->weekOneFollowUpJson();

            $assigned = 0;
            $reassigned = 0;
            $skipped = 0;

            // guide id => first timers that are new to that guide, so the alert
            // only mentions what this click actually changed.
            $newForGuide = [];
            $firstIdForGuide = [];

            DB::transaction(function () use ($rows, $assignments, $current, $weekOneJson, &$assigned, &$reassigned, &$skipped, &$newForGuide, &$firstIdForGuide) {
                foreach ($rows as $row) {
                    $firstTimerId = (int) $row->first_timer_id;
                    $guideId = (int) ($assignments[$row->first_timer_id] ?? 0);

                    if ($guideId <= 0) {
                        // Nobody in this first timer's gender lane (or no callers).
                        $skipped++;

                        continue;
                    }

                    $existing = $current->get($row->first_timer_id);
                    $previousGuideId = $existing ? (int) $existing->tiu_member_id : 0;

                    if ($existing) {
                        $this->moveFollowUpToGuide($firstTimerId, $guideId, (string) ($existing->followup_response_new ?? ''), $weekOneJson);
                        $reassigned++;
                    } else {
                        DB::table('member_tracking_followup')->insert([
                            'first_timer_id'        => $firstTimerId,
                            'tiu_member_id'         => $guideId,
                            'followup_rank'         => 1,
                            'timeStamp_registered'  => now(),
                            'followup_response_new' => $weekOneJson,
                        ]);
                        $assigned++;
                    }

                    DB::table('first_timer')
                        ->where('first_timer_id', $firstTimerId)
                        ->update([
                            'status'             => $existing ? 'Reassigned' : 'Assigned',
                            'status_change_date' => now(),
                        ]);

                    if ($previousGuideId !== $guideId) {
                        $newForGuide[$guideId][] = $this->firstTimerName($row, $firstTimerId);
                        $firstIdForGuide[$guideId] = $firstIdForGuide[$guideId] ?? $firstTimerId;
                    }
                }
            });

            // Every guide hears about the list they were just handed (item 11).
            $notified = $this->notifyGuides($newForGuide, $callerNames, $firstIdForGuide, $user->campus_id ?? null);

            $message = "Assigned {$assigned} first timer(s) to their caller.";

            if ($reassigned > 0) {
                $message .= " {$reassigned} already had a guide and were reassigned.";
            }

            if ($skipped > 0) {
                $message .= " {$skipped} first timer(s) had no caller available.";
            }

            if ($notified > 0) {
                $message .= " {$notified} guide(s) notified.";
            }

            return response()->json([
                'success'    => true,
                'assigned'   => $assigned,
                'reassigned' => $reassigned,
                'skipped'    => $skipped,
                'notified'   => $notified,
                'message'    => $message,
            ]);
        } catch (\Throwable $e) {
            Log::error('Bulk guide assignment from the call list failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not assign the callers'], 500);
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