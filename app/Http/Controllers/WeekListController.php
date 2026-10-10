<?php

namespace App\Http\Controllers;

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
        $start_date_filter = $request->get('start_date', '');
        $end_date_filter = $request->get('end_date', '');
        $guest_type_filter = $request->get('guest_type', '');
        $church_type_id_filter = $request->get('church_type_id', '');

        // Set default date range (current week: Sunday to Saturday)
        if (!$start_date_filter || !$end_date_filter) {
            $today = Carbon::today();
            $dayOfWeek = $today->dayOfWeek; // 0 = Sunday, 6 = Saturday
            $sunday = $today->copy()->subDays($dayOfWeek);
            $saturday = $sunday->copy()->addDays(6);
            
            $start_date_filter = $sunday->format('Y-m-d');
            $end_date_filter = $saturday->format('Y-m-d');
            $page_title = "Weekly Prayer List (" . $sunday->format('M d') . " - " . $saturday->format('M d, Y') . ")";
        } else {
            $page_title = "First Timers from " . date('M d, Y', strtotime($start_date_filter)) . " to " . date('M d, Y', strtotime($end_date_filter));
        }

        // Build the query
        $query = DB::table('first_timer as ft')
            ->leftJoin('church_type as ct', 'ft.church_type_id', '=', 'ct.id');

        // Campus filter - only show first timers from user's campus
        if ($member_role !== 'Super User' && !empty($userCampusId)) {
            $query->where('ft.campus_id', $userCampusId);
        }

        // Date range condition
        if ($start_date_filter && $end_date_filter) {
            $query->whereBetween('ft.register_date', [$start_date_filter . ' 00:00:00', $end_date_filter . ' 23:59:59']);
        }

        // Guest type filter
        if ($guest_type_filter) {
            $query->where('ft.attendant_type', $guest_type_filter);
        }

        // Church type filter
        if ($church_type_id_filter && $church_type_id_filter !== 'All') {
            $query->where('ft.church_type_id', $church_type_id_filter);
        }

        // Get the results
        $firstTimers = $query->select('ft.*', 'ct.church_type_name')
            ->orderBy('ft.register_date', 'desc')
            ->get();

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
            ['callerSubgroup' => self::CALLER_SUBGROUP]
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
}