<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminAssignmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        $userCampusId = $user->campus_id ?? 0;

        // Authorize
        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect('/aoverview')->with('error', 'Access Denied');
        }

        $search = $request->get('search', '');
        $status = $request->get('status', '');

        // Get unassigned first-timers
        $unassignedQuery = DB::table('first_timer')
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhere('status', 'Not Assigned');
            })
            ->where('campus_id', $userCampusId)
            ->orderBy('first_name', 'asc');

        if (!empty($search)) {
            $unassignedQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $unassigned = $unassignedQuery->get();

        // Get all active TIU members (guides)
        $guides = DB::table('tiu_member')
            ->where('status', 1)
            ->where('campus_id', $userCampusId)
            ->whereNotIn('member_role', ['Super User', 'Admin'])
            ->orderBy('first_name', 'asc')
            ->get();

        // Get assignments with guide + first timer info
        $assignmentsQuery = DB::table('member_tracking_followup as mtf')
            ->join('first_timer as ft', 'mtf.first_timer_id', '=', 'ft.first_timer_id')
            ->join('tiu_member as tm', 'mtf.tiu_member_id', '=', 'tm.tiu_member_id')
            ->where('ft.campus_id', $userCampusId)
            ->select(
                'mtf.*',
                'ft.first_name as ft_first_name',
                'ft.last_name as ft_last_name',
                'ft.phone_number as ft_phone',
                'ft.status as ft_status',
                'ft.first_timer_id',
                'tm.first_name as guide_first_name',
                'tm.last_name as guide_last_name'
            )
            ->orderBy('tm.first_name', 'asc')
            ->orderBy('ft.first_name', 'asc');

        if (!empty($search)) {
            $assignmentsQuery->where(function ($q) use ($search) {
                $q->where('ft.first_name', 'like', "%{$search}%")
                  ->orWhere('ft.last_name', 'like', "%{$search}%")
                  ->orWhere('tm.first_name', 'like', "%{$search}%")
                  ->orWhere('tm.last_name', 'like', "%{$search}%");
            });
        }

        if (!empty($status) && $status === 'completed') {
            $assignmentsQuery->where('mtf.followup_rank', '>', 1);
        } elseif (!empty($status) && $status === 'active') {
            $assignmentsQuery->where('mtf.followup_rank', 1);
        }

        $assignments = $assignmentsQuery->paginate(100)->appends(request()->query());

        return view('admin.assignments.index', compact('unassigned', 'guides', 'assignments', 'search', 'status'));
    }

    /**
     * AJAX: Drag-drop assignment (mirrors assign_timer.php)
     */
    public function ajaxAssign(Request $request)
    {
        $request->validate([
            'Guild_name'       => 'required|integer',
            'first_timer_id'   => 'required|integer',
            'full_name'        => 'required|string',
            'status'           => 'required|string',
        ]);

        try {
            $guideId      = $request->Guild_name;
            $firstTimerId = $request->first_timer_id;
            $ftFullName   = $request->full_name;
            $currentStatus = $request->status;

            // Check if already assigned
            $existing = DB::table('member_tracking_followup')
                ->where('first_timer_id', $firstTimerId)
                ->exists();

            $newStatus = $existing ? 'Reassigned' : 'Assigned';

            if (!$existing) {
                // NEW ASSIGNMENT — fetch Week 1 tasks from task table (filtered by campus)
                $followupResponseJson = '{}';
                $userCampusId = Auth::user()->campus_id ?? 0;
                $taskRow = DB::table('task')
                    ->where('wid', 1)
                    ->where('campus_id', $userCampusId)
                    ->value('questions');
                if ($taskRow) {
                    $rawJson = html_entity_decode($taskRow, ENT_QUOTES | ENT_HTML5);
                    $decoded = json_decode($rawJson, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        foreach ($decoded as &$td) {
                            if (is_array($td)) $td['logs'] = [];
                        }
                        unset($td);
                        $followupResponseJson = json_encode(['week1' => $decoded]);
                    }
                }

                DB::table('member_tracking_followup')->insert([
                    'first_timer_id'        => $firstTimerId,
                    'tiu_member_id'         => $guideId,
                    'followup_rank'         => 1,
                    'timeStamp_registered'  => now(),
                    'followup_response_new' => $followupResponseJson,
                ]);
            } else {
                // RE-ASSIGNMENT
                $currentJson = DB::table('member_tracking_followup')
                    ->where('first_timer_id', $firstTimerId)
                    ->value('followup_response_new');

                if (strlen($currentJson ?? '') < 10) {
                    // Broken JSON → rebuild (filtered by campus)
                    $userCampusId = Auth::user()->campus_id ?? 0;
                    $taskRow = DB::table('task')
                        ->where('wid', 1)
                        ->where('campus_id', $userCampusId)
                        ->value('questions');
                    if ($taskRow) {
                        $rawJson = html_entity_decode($taskRow, ENT_QUOTES | ENT_HTML5);
                        $decoded = json_decode($rawJson, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            foreach ($decoded as &$td) {
                                if (is_array($td)) $td['logs'] = [];
                            }
                            unset($td);
                            $followupResponseJson = json_encode(['week1' => $decoded]);
                            DB::table('member_tracking_followup')
                                ->where('first_timer_id', $firstTimerId)
                                ->update([
                                    'tiu_member_id'         => $guideId,
                                    'followup_rank'         => 1,
                                    'timeStamp_registered'  => now(),
                                    'followup_response_new' => $followupResponseJson,
                                ]);
                        }
                    }
                } else {
                    DB::table('member_tracking_followup')
                        ->where('first_timer_id', $firstTimerId)
                        ->update([
                            'tiu_member_id'        => $guideId,
                            'followup_rank'        => 1,
                            'timeStamp_registered' => now(),
                        ]);
                }
            }

            // Update first_timer status
            DB::table('first_timer')
                ->where('first_timer_id', $firstTimerId)
                ->update(['status' => $newStatus, 'status_change_date' => now()]);

            return response()->json([
                'status'  => 'success',
                'message' => "$ftFullName has been successfully $newStatus.",
            ]);
        } catch (\Exception $e) {
            Log::error('ajaxAssign error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Drag-drop unassignment (mirrors unassign_timer.php)
     */
    public function ajaxUnassign(Request $request)
    {
        $request->validate(['first_timer_id' => 'required|integer']);

        try {
            $firstTimerId = $request->first_timer_id;

            DB::table('member_tracking_followup')
                ->where('first_timer_id', $firstTimerId)
                ->update(['tiu_member_id' => 0]);

            DB::table('first_timer')
                ->where('first_timer_id', $firstTimerId)
                ->update(['status' => 'Unassigned', 'status_change_date' => now()]);

            $timerData = DB::table('first_timer')
                ->where('first_timer_id', $firstTimerId)
                ->first();

            return response()->json([
                'status'     => 'success',
                'message'    => 'First-timer has been unassigned.',
                'timer_data' => $timerData,
            ]);
        } catch (\Exception $e) {
            Log::error('ajaxUnassign error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Suggest assignments (mirrors suggest_assignments.php)
     */
    public function ajaxSuggest(Request $request)
    {
        try {
            $guideGender     = $request->get('guide_gender', '');
            $guideChurchType = $request->get('guide_church_type', '');
            $guideOccupation = $request->get('guide_occupation', '');

            $ftGender     = $request->get('ft_gender', '');
            $ftChurchType = $request->get('ft_church_type', '');

            $user = Auth::user();
            $userCampusId = $user->campus_id ?? session('campus_id', 0);

            // Guides pool
            $guideQuery = DB::table('tiu_member as tm')
                ->leftJoin('member_tracking_followup as mtf', 'tm.tiu_member_id', '=', 'mtf.tiu_member_id')
                ->where('tm.department_name', 'LIKE', '%"23"%')
                ->where('tm.campus_id', $userCampusId)
                ->select(
                    'tm.tiu_member_id',
                    'tm.first_name',
                    'tm.last_name',
                    'tm.gender',
                    'tm.church_type_id',
                    DB::raw('COUNT(mtf.tracking_id) as active_assignments'),
                    DB::raw('SUM(CASE WHEN mtf.timeStamp_registered < DATE_SUB(NOW(), INTERVAL 6 WEEK) THEN 1 ELSE 0 END) as old_assignments')
                );

            if (!empty($guideGender))     $guideQuery->where('tm.gender', $guideGender);
            if (!empty($guideChurchType)) $guideQuery->where('tm.church_type_id', $guideChurchType);
            if (!empty($guideOccupation)) $guideQuery->where('tm.occupation', $guideOccupation);

            $guidesPool = $guideQuery->groupBy('tm.tiu_member_id', 'tm.first_name', 'tm.last_name', 'tm.gender', 'tm.church_type_id')
                ->orderBy('tm.first_name')
                ->get();

            if ($guidesPool->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No available guides match the current filters.']);
            }

            // Unassigned first timers
            $ftQuery = DB::table('first_timer')
                ->whereIn('status', ['Unassigned', 'Not Assigned'])
                ->where('campus_id', $userCampusId)
                ->whereNotIn('first_timer_id', function ($q) {
                    $q->select('first_timer_id')->from('member_tracking_followup')->where('tiu_member_id', '>', 0);
                });

            if (!empty($ftGender))     $ftQuery->where('gender', $ftGender);
            if (!empty($ftChurchType)) $ftQuery->where('church_type_id', $ftChurchType);

            $unassignedTimers = $ftQuery->get(['first_timer_id', 'first_name', 'last_name', 'gender', 'church_type_id', 'status']);

            if ($unassignedTimers->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No unassigned first-timers match the current filters.']);
            }

            // Matching algorithm
            $suggestions = [];
            $guideWorkload = $guidesPool->pluck('active_assignments', 'tiu_member_id')->toArray();

            foreach ($unassignedTimers as $ft) {
                $bestScore = -1;
                $bestGuideId = null;

                foreach ($guidesPool as $guide) {
                    $score = 0;
                    $workload = $guideWorkload[$guide->tiu_member_id] ?? 0;

                    if ($workload == 0) $score += 100;
                    elseif ($workload == 1) $score += 50;
                    elseif ($workload == 2) $score += 25;
                    else $score += max(0, 10 - $workload);

                    if (!empty($ft->gender) && $ft->gender === $guide->gender) $score += 30;
                    if (!empty($ft->church_type_id) && $ft->church_type_id == $guide->church_type_id) $score += 20;
                    if (($guide->old_assignments ?? 0) > 0) $score -= 15;

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestGuideId = $guide->tiu_member_id;
                    }
                }

                if ($bestGuideId !== null) {
                    $bestGuide = $guidesPool->firstWhere('tiu_member_id', $bestGuideId);
                    $suggestions[] = [
                        'first_timer_id'      => $ft->first_timer_id,
                        'first_timer_name'    => $ft->first_name . ' ' . $ft->last_name,
                        'first_timer_status'  => $ft->status,
                        'suggested_guide_id'  => $bestGuideId,
                        'suggested_guide_name'=> $bestGuide->first_name . ' ' . $bestGuide->last_name,
                        'score'               => $bestScore,
                    ];
                    $guideWorkload[$bestGuideId]++;
                }
            }

            return response()->json(['success' => true, 'suggestions' => $suggestions]);
        } catch (\Exception $e) {
            Log::error('ajaxSuggest error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function assign(Request $request)
    {
        $request->validate([
            'first_timer_id' => 'required|integer',
            'guide_id' => 'required|integer',
        ]);

        try {
            $ftId = $request->first_timer_id;
            $guideId = $request->guide_id;
            $userCampusId = Auth::user()->campus_id ?? 0;

            // Check if already assigned
            $existing = DB::table('member_tracking_followup')
                ->where('first_timer_id', $ftId)
                ->exists();

            if ($existing) {
                return redirect()->back()->with('error', 'First timer already assigned!');
            }

            // Generate the followup response JSON from task table (filtered by campus)
            $followupResponse = $this->generateDefaultFollowupResponse();
            $taskRow = DB::table('task')
                ->where('wid', 1)
                ->where('campus_id', $userCampusId)
                ->value('questions');
            if ($taskRow) {
                $rawJson = html_entity_decode($taskRow, ENT_QUOTES | ENT_HTML5);
                $decoded = json_decode($rawJson, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    foreach ($decoded as &$td) {
                        if (is_array($td)) $td['logs'] = [];
                    }
                    unset($td);
                    $followupResponse = ['week1' => $decoded];
                }
            }

            DB::table('member_tracking_followup')->insert([
                'tiu_member_id' => $guideId,
                'first_timer_id' => $ftId,
                'followup_rank' => 1,
                'followup_response_new' => json_encode($followupResponse),
                'timeStamp_registered' => now(),
            ]);

            // Update first timer status
            DB::table('first_timer')
                ->where('first_timer_id', $ftId)
                ->update(['status' => 'Assigned']);

            return redirect()->back()->with('success', 'First timer assigned successfully!');
        } catch (\Exception $e) {
            Log::error('Assign error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function unassign(Request $request)
    {
        $request->validate([
            'tracking_id' => 'required|integer',
        ]);

        try {
            $tracking = DB::table('member_tracking_followup')
                ->where('tracking_id', $request->tracking_id)
                ->first();

            if ($tracking) {
                DB::table('first_timer')
                    ->where('first_timer_id', $tracking->first_timer_id)
                    ->update(['status' => 'Not Assigned']);

                DB::table('member_tracking_followup')
                    ->where('tracking_id', $request->tracking_id)
                    ->delete();
            }

            return redirect()->back()->with('success', 'Assignment removed!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * ====================================================================
     * Drag & Drop Assignment View (New! Ported from admin-assign-new.php)
     * ====================================================================
     */
    public function dragDropIndex(Request $request)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        $userCampusId = $user->campus_id ?? session('campus_id', 0);

        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect('/home')->with('error', 'Access Denied');
        }

        // --- SELECTED DEPARTMENT ---
        $selectedDeptId = $request->get('dept_id', '23');

        // Available departments for dropdown
        $deptOptions = DB::table('department')
            ->whereIn('dept_id', [15, 23])
            ->orderBy('dept_name')
            ->get(['dept_id', 'dept_name']);

        // --- GET FILTERS ---
        $guideGender     = $request->get('guide_gender', '');
        $guideChurchType = $request->get('guide_church_type', '');
        $guideOccupation = $request->get('guide_occupation', '');

        $ftStartDate  = $request->get('ft_start_date', '');
        $ftEndDate    = $request->get('ft_end_date', '');
        $ftGender     = $request->get('ft_gender', '');
        $ftChurchType = $request->get('ft_church_type', '');
        $ftGuestType  = $request->get('ft_guest_type', '');

        // --- CHURCH TYPES & OCCUPATIONS FOR FILTER DROPDOWNS ---
        $churchTypes = DB::table('church_type')
            ->orderBy('church_type_name')
            ->get(['id', 'church_type_name']);

        $occupations = DB::table('tiu_member')
            ->whereNotNull('occupation')
            ->where('occupation', '!=', '')
            ->select('occupation')
            ->distinct()
            ->orderBy('occupation')
            ->get()
            ->pluck('occupation');

        $guestTypeOptions = [
            "Visiting",
            "New To TCN (Will Join TCN)",
            "New To TCN (May Join TCN)",
            "TCN Member (Relocating to Ikd)",
            "TCN Member (In Transit)",
            "TCN Member (Other Center)"
        ];

        // --- 1. FETCH GUIDES (filtered by selected department) ---
        $guideQuery = DB::table('tiu_member')
            ->where('department_name', 'LIKE', '%"' . $selectedDeptId . '"%')
            ->where('campus_id', $userCampusId);

        if (!empty($guideGender)) {
            $guideQuery->where('gender', $guideGender);
        }
        if (!empty($guideChurchType)) {
            $guideQuery->where('church_type_id', $guideChurchType);
        }
        if (!empty($guideOccupation)) {
            $guideQuery->where('occupation', $guideOccupation);
        }

        $guides = $guideQuery->orderBy('first_name')->orderBy('last_name')->get();

        // --- 2. FETCH ASSIGNED MEMBERS PER GUIDE (via member_tracking_followup with department_id) ---
        $guidesArray = [];
        if ($guides->isNotEmpty()) {
            $guideIds = $guides->pluck('tiu_member_id')->toArray();

            if ($selectedDeptId == 23) {
                // TRACKING & INTEGRATION: Show assigned first_timers via member_tracking_followup
                $assignedMembers = DB::table('first_timer as ft')
                    ->join('member_tracking_followup as mtf', 'ft.first_timer_id', '=', 'mtf.first_timer_id')
                    ->whereIn('mtf.tiu_member_id', $guideIds)
                    ->where('mtf.department_id', 23)
                    ->where('ft.status', '!=', 'Integrated')
                    ->select('ft.*', 'mtf.tiu_member_id')
                    ->get()
                    ->groupBy('tiu_member_id');
            } else {
                // FOUNDATION OF FAITH SUPPORT: Show assigned fof_register records via member_tracking_followup
                $assignedMembers = DB::table('fof_register_table as fof')
                    ->join('member_tracking_followup as mtf', 'fof.id', '=', 'mtf.fof_register_id')
                    ->whereIn('mtf.tiu_member_id', $guideIds)
                    ->where('mtf.department_id', 15)
                    ->select('fof.*', 'mtf.tiu_member_id')
                    ->get()
                    ->groupBy('tiu_member_id');
            }

            foreach ($guides as $g) {
                $guidesArray[$g->tiu_member_id] = [
                    'details' => $g,
                    'first_timers' => $assignedMembers->get($g->tiu_member_id, collect())->toArray(),
                ];
            }
        }

        // --- 3. FETCH UNASSIGNED MEMBERS ---
        if ($selectedDeptId == 23) {
            // TRACKING & INTEGRATION: Unassigned first_timer records
            $unassignedQuery = DB::table('first_timer')
                ->whereIn('status', ['Unassigned', 'Not Assigned'])
                ->where('campus_id', $userCampusId)
                ->whereNotIn('first_timer_id', function ($q) {
                    $q->select('first_timer_id')
                      ->from('member_tracking_followup')
                      ->where('tiu_member_id', '>', 0);
                });

            if (!empty($ftStartDate) && !empty($ftEndDate)) {
                $unassignedQuery->whereBetween(DB::raw('DATE(register_date)'), [$ftStartDate, $ftEndDate]);
            }
            if (!empty($ftGender)) {
                $unassignedQuery->where('gender', $ftGender);
            }
            if (!empty($ftChurchType)) {
                $unassignedQuery->where('church_type_id', $ftChurchType);
            }
            if (!empty($ftGuestType)) {
                $unassignedQuery->where('attendant_type', $ftGuestType);
            }

            $unassignedMembers = $unassignedQuery->orderBy('first_name')->orderBy('last_name')->get();
        } else {
            // FOUNDATION OF FAITH SUPPORT: Show ALL fof_register records (all cohorts)
            // Exclude those already assigned via member_tracking_followup (department_id=15)
            $unassignedQuery = DB::table('fof_register_table')
                ->where('campus_id', $userCampusId)
                ->whereNotIn('id', function ($q) {
                    $q->select('fof_register_id')
                      ->from('member_tracking_followup')
                      ->where('department_id', 15)
                      ->where('tiu_member_id', '>', 0);
                });

            if (!empty($ftStartDate) && !empty($ftEndDate)) {
                $unassignedQuery->whereBetween(DB::raw('DATE(registration_date)'), [$ftStartDate, $ftEndDate]);
            }
            if (!empty($ftGender)) {
                $unassignedQuery->where('gender', $ftGender);
            }
            if (!empty($ftGuestType)) {
                $unassignedQuery->where('how_heard', $ftGuestType);
            }

            $unassignedMembers = $unassignedQuery->orderBy('first_name')->orderBy('last_name')->get();
        }

        return view('admin.assignments.drag-drop', compact(
            'guidesArray',
            'unassignedMembers',
            'selectedDeptId',
            'deptOptions',
            'churchTypes',
            'occupations',
            'guestTypeOptions',
            'guideGender',
            'guideChurchType',
            'guideOccupation',
            'ftStartDate',
            'ftEndDate',
            'ftGender',
            'ftChurchType',
            'ftGuestType'
        ));
    }

    /**
     * AJAX: FOF Drag-drop assignment (assign fof_register records to FOF guides)
     * Stores assignment in member_tracking_followup with department_id=15
     */
    public function ajaxFofAssign(Request $request)
    {
        $request->validate([
            'Guild_name'       => 'required|integer',
            'first_timer_id'   => 'required|string',
            'full_name'        => 'required|string',
        ]);

        try {
            $guideId      = $request->Guild_name;
            $fofRecordId  = $request->first_timer_id;
            $ftFullName   = $request->full_name;

            // Parse fof_ prefix and id
            $fofId = str_replace('fof_', '', $fofRecordId);

            // Check if already assigned in member_tracking_followup (dept 15)
            $existing = DB::table('member_tracking_followup')
                ->where('fof_register_id', $fofId)
                ->where('department_id', 15)
                ->exists();

            if (!$existing) {
                // NEW ASSIGNMENT
                DB::table('member_tracking_followup')->insert([
                    'tiu_member_id'      => $guideId,
                    'fof_register_id'    => $fofId,
                    'followup_rank'      => 1,
                    'department_id'      => 15,
                    'timeStamp_registered' => now(),
                ]);
            } else {
                // RE-ASSIGNMENT
                DB::table('member_tracking_followup')
                    ->where('fof_register_id', $fofId)
                    ->where('department_id', 15)
                    ->update(['tiu_member_id' => $guideId, 'timeStamp_registered' => now()]);
            }

            return response()->json([
                'status'  => 'success',
                'message' => "$ftFullName has been successfully assigned.",
            ]);
        } catch (\Exception $e) {
            Log::error('ajaxFofAssign error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: FOF Drag-drop unassignment
     * Removes assignment from member_tracking_followup
     */
    public function ajaxFofUnassign(Request $request)
    {
        $request->validate(['first_timer_id' => 'required|string']);

        try {
            $fofRecordId = $request->first_timer_id;
            $fofId = str_replace('fof_', '', $fofRecordId);

            // Set tiu_member_id to 0 instead of deleting (to preserve tracking data)
            DB::table('member_tracking_followup')
                ->where('fof_register_id', $fofId)
                ->where('department_id', 15)
                ->update(['tiu_member_id' => 0]);

            $timerData = DB::table('fof_register_table')
                ->where('id', $fofId)
                ->first();

            return response()->json([
                'status'     => 'success',
                'message'    => 'FOF registrant has been unassigned.',
                'timer_data' => $timerData,
            ]);
        } catch (\Exception $e) {
            Log::error('ajaxFofUnassign error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function suggest()
    {
        $user = Auth::user();
        $userCampusId = $user->campus_id ?? 0;

        // Get unassigned first timers
        $unassigned = DB::table('first_timer')
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhere('status', 'Not Assigned');
            })
            ->where('campus_id', $userCampusId)
            ->orderBy('first_name', 'asc')
            ->get();

        // Get guides with assignment counts
        $guides = DB::table('tiu_member')
            ->leftJoin('member_tracking_followup', 'tiu_member.tiu_member_id', '=', 'member_tracking_followup.tiu_member_id')
            ->where('tiu_member.status', 1)
            ->where('tiu_member.campus_id', $userCampusId)
            ->select(
                'tiu_member.tiu_member_id',
                'tiu_member.first_name',
                'tiu_member.last_name',
                'tiu_member.subgroup',
                DB::raw('COUNT(member_tracking_followup.tracking_id) as assignment_count')
            )
            ->groupBy('tiu_member.tiu_member_id', 'tiu_member.first_name', 'tiu_member.last_name', 'tiu_member.subgroup')
            ->orderBy('assignment_count', 'asc')
            ->get();

        return view('admin.assignments.suggest', compact('unassigned', 'guides'));
    }

    public function generatePairings(Request $request)
    {
        $request->validate([
            'guide_id' => 'required|integer',
            'first_timer_ids' => 'required|array',
            'first_timer_ids.*' => 'integer',
        ]);

        try {
            $guideId = $request->guide_id;
            $ftIds = $request->first_timer_ids;
            $userCampusId = Auth::user()->campus_id ?? 0;

            foreach ($ftIds as $ftId) {
                $existing = DB::table('member_tracking_followup')
                    ->where('first_timer_id', $ftId)
                    ->exists();

                if (!$existing) {
                    // Use campus-filtered tasks
                    $followupResponse = $this->generateDefaultFollowupResponse();
                    $taskRow = DB::table('task')
                        ->where('wid', 1)
                        ->where('campus_id', $userCampusId)
                        ->value('questions');
                    if ($taskRow) {
                        $rawJson = html_entity_decode($taskRow, ENT_QUOTES | ENT_HTML5);
                        $decoded = json_decode($rawJson, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            foreach ($decoded as &$td) {
                                if (is_array($td)) $td['logs'] = [];
                            }
                            unset($td);
                            $followupResponse = ['week1' => $decoded];
                        }
                    }

                    DB::table('member_tracking_followup')->insert([
                        'tiu_member_id' => $guideId,
                        'first_timer_id' => $ftId,
                        'followup_rank' => 1,
                        'followup_response_new' => json_encode($followupResponse),
                        'timeStamp_registered' => now(),
                    ]);

                    DB::table('first_timer')
                        ->where('first_timer_id', $ftId)
                        ->update(['status' => 'Assigned']);
                }
            }

            return redirect()->back()->with('success', 'Pairings generated successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    private function generateDefaultFollowupResponse()
    {
        $weeks = [
            'Week 1' => [
                'task_1' => ['text' => 'Pray for the first-timer', 'display' => true, 'status' => 'Pending', 'logs' => []],
                'task_2' => ['text' => 'Call the first-timer within 24 hours', 'display' => false, 'status' => 'Pending', 'logs' => []],
                'task_3' => ['text' => 'Send a welcome SMS', 'display' => false, 'status' => 'Pending', 'logs' => []],
                'task_4' => ['text' => 'Invite to next service', 'display' => false, 'status' => 'Pending', 'logs' => []],
            ],
            'Week 2' => [
                'task_1' => ['text' => 'Follow up call - check on well-being', 'display' => true, 'status' => 'Pending', 'logs' => []],
                'task_2' => ['text' => 'Share a Bible verse', 'display' => false, 'status' => 'Pending', 'logs' => []],
                'task_3' => ['text' => 'Invite to house fellowship', 'display' => false, 'status' => 'Pending', 'logs' => []],
            ],
            'Week 3' => [
                'task_1' => ['text' => 'Visit or call the first-timer', 'display' => true, 'status' => 'Pending', 'logs' => []],
                'task_2' => ['text' => 'Discuss spiritual growth', 'display' => false, 'status' => 'Pending', 'logs' => []],
            ],
            'Week 4' => [
                'task_1' => ['text' => 'Final follow-up for the month', 'display' => true, 'status' => 'Pending', 'logs' => []],
                'task_2' => ['text' => 'Encourage FOF registration', 'display' => false, 'status' => 'Pending', 'logs' => []],
            ],
        ];

        return $weeks;
    }
}
