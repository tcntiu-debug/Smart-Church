<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class MyTaskController extends Controller
{
    public function index(Request $request)
    {
        // Check if we're viewing a specific guide's tasks
    $guide_id = $request->get('guide_id');
    $viewing_guide_name = $request->get('guide_name');
    
    // Debug: Log the values to see what's coming through
    \Log::info('Guide ID: ' . $guide_id);
    \Log::info('Guide Name: ' . $viewing_guide_name);
    
    if ($guide_id) {
        // Admin viewing another guide's tasks
        $tiu_member_id = $guide_id;
        $is_viewing_other = true;
    } else {
        // User viewing their own tasks
        $user = Auth::user();
        $tiu_member_id = $user->tiu_member_id ?? $user->id;
        $viewing_guide_name = $user->first_name . ' ' . $user->last_name;
        $is_viewing_other = false;
    }

        // --- 1. Get TRACKING & INTEGRATION assigned first timers (department_id=23 or null) ---
        $firstTimers = DB::table('member_tracking_followup as mtf')
            ->join('first_timer as ft', 'mtf.first_timer_id', '=', 'ft.first_timer_id')
            ->where('mtf.tiu_member_id', $tiu_member_id)
            ->where(function ($q) {
                $q->where('mtf.department_id', 23)
                  ->orWhereNull('mtf.department_id');
            })
            ->select('ft.*', 'mtf.tracking_id')
            ->get();

        // --- 2. Get FOF SUPPORT assigned records (department_id=15) ---
        $fofFirstTimers = DB::table('member_tracking_followup as mtf')
            ->join('fof_register_table as fof', 'mtf.fof_register_id', '=', 'fof.id')
            ->where('mtf.tiu_member_id', $tiu_member_id)
            ->where('mtf.department_id', 15)
            ->select('fof.*', 'mtf.tracking_id')
            ->get();

        // Get updates data (FOF status) - only for tracking first timers
        $updatesData = [];
        $updates = DB::table('first_timers_updates')->get();
        foreach ($updates as $u) {
            $updatesData[$u->first_timer_id] = (array)$u;
        }

        // Get tracking data with task counts - only for tracking first timers
        $trackingData = [];
        $tracking = DB::table('member_tracking_followup')
            ->where('tiu_member_id', $tiu_member_id)
            ->where(function ($q) {
                $q->where('department_id', 23)
                  ->orWhereNull('department_id');
            })
            ->get();

        foreach ($tracking as $t) {
            $pendingCount = 0;
            if ($t->followup_response_new) {
                $followup = json_decode($t->followup_response_new, true);
                if (is_array($followup)) {
                    foreach ($followup as $week => $weekTasks) {
                        if (is_array($weekTasks)) {
                            foreach ($weekTasks as $task) {
                                if (isset($task['display']) && $task['display'] && (!isset($task['status']) || $task['status'] == 'Pending')) {
                                    $pendingCount++;
                                }
                            }
                        }
                    }
                }
            }
            $trackingData[$t->first_timer_id] = [
                'tracking_id' => $t->tracking_id,
                'pending_count' => $pendingCount
            ];
        }

        return view('my-task.index', compact(
            'firstTimers', 'fofFirstTimers', 'updatesData', 'trackingData',
            'is_viewing_other', 'viewing_guide_name', 'guide_id'
        ));
    }

    public function update(Request $request)
    {
        $request->validate([
            'first_timer_id' => 'required',
            'update_type' => 'required',
            'value' => 'required'
        ]);

        switch ($request->update_type) {
            case 'fof':
                $existing = DB::table('first_timers_updates')
                    ->where('first_timer_id', $request->first_timer_id)
                    ->first();

                if ($existing) {
                    DB::table('first_timers_updates')
                        ->where('first_timer_id', $request->first_timer_id)
                        ->update(['foundation_of_faith' => $request->value]);
                } else {
                    DB::table('first_timers_updates')->insert([
                        'first_timer_id' => $request->first_timer_id,
                        'department' => "",
                        'cluster' => "",
                        'house_fellowship' => "",
                        'foundation_of_faith' => $request->value,
                        'water_baptism' => "",
                        'holy_ghost_baptism' => "",
                        'birthday' => "",
                        'community' => "",
                        'bus_route' => "",
                        'created_date' => now(),
                    ]);
                }
                break;

            case 'address':
                DB::table('first_timer')
                    ->where('first_timer_id', $request->first_timer_id)
                    ->update(['address' => $request->value]);
                break;
        }

        // Preserve guide_id if it exists in the request
        $redirect = redirect()->back()->with('success', 'Updated successfully!');
        if ($request->has('guide_id')) {
            $redirect = redirect()->back()->with('success', 'Updated successfully!');
        }
        return $redirect;
    }

    public function showTasks(Request $request, $id)
    {
        // Check if we're viewing a specific guide's tasks
        $guide_id = $request->get('guide_id');
        $viewing_guide_name = $request->get('guide_name');
        
        if ($guide_id) {
            // Admin viewing another guide's tasks
            $tiu_member_id = $guide_id;
            $is_viewing_other = true;
        } else {
            // User viewing their own tasks
            $user = Auth::user();
            $tiu_member_id = $user->tiu_member_id ?? $user->id;
            $viewing_guide_name = $user->first_name . ' ' . $user->last_name;
            $is_viewing_other = false;
        }

        // First, check if this ID is a FOF register ID (department_id=15)
        $fofTracking = DB::table('member_tracking_followup')
            ->where('tiu_member_id', $tiu_member_id)
            ->where('fof_register_id', $id)
            ->where('department_id', 15)
            ->first();

        if ($fofTracking) {
            // This is a FOF record - get data from fof_register_table
            $fofRecord = DB::table('fof_register_table')
                ->where('id', $id)
                ->first();

            if (!$fofRecord) {
                return redirect()->route('my-tasks.index')->with('error', 'FOF record not found');
            }

            // Map FOF record to firstTimer-like object for the view
            $firstTimer = (object)[
                'first_name' => $fofRecord->first_name,
                'last_name' => $fofRecord->last_name,
                'phone_number' => $fofRecord->phone_number,
                'gender' => $fofRecord->gender,
                'attendant_type' => 'FOF Student',
                'address' => $fofRecord->smart_request ?? '',
                'occupation' => '',
                'created_at' => $fofRecord->registration_date,
                'first_timer_id' => $id,
            ];

            $tracking = $fofTracking;
        } else {
            // Regular first timer tracking
            $firstTimer = DB::table('first_timer')
                ->where('first_timer_id', $id)
                ->first();

            if (!$firstTimer) {
                return redirect()->route('my-tasks.index')->with('error', 'First timer not found');
            }

            // Get tracking data
            $tracking = DB::table('member_tracking_followup')
                ->where('tiu_member_id', $tiu_member_id)
                ->where('first_timer_id', $id)
                ->first();
        }

        $tasks = [];
        if ($tracking && $tracking->followup_response_new) {
            $tasks = json_decode($tracking->followup_response_new, true);
            if (!is_array($tasks)) {
                $tasks = [];
            }
        }

        return view('my-task.tasks', compact('firstTimer', 'tasks', 'tracking', 'is_viewing_other', 'viewing_guide_name', 'guide_id'));
    }

    public function updateTask(Request $request)
    {
        $request->validate([
            'tracking_id' => 'required',
            'week' => 'required',
            'task_key' => 'required',
            'outcome' => 'required'
        ]);

        $tracking_id = $request->tracking_id;
        $week = $request->week;
        $task_key = $request->task_key;
        $outcome = $request->outcome;
        $comment = $request->comment ?? '';
        
        // Determine which guide is performing this action
        $guide_id = $request->get('performing_guide_id', Auth::user()->tiu_member_id ?? Auth::user()->id);
        $member_role = Auth::user()->member_role ?? '';

        // Check if it's a skip action
        $action = $request->action ?? 'log_outcome';

        DB::beginTransaction();

        try {
            // Fetch current JSON
            $tracking = DB::table('member_tracking_followup')
                ->where('tracking_id', $tracking_id)
                ->first();

            if (!$tracking) {
                throw new \Exception('Tracking record not found.');
            }

            $current_response = json_decode($tracking->followup_response_new, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($current_response[$week][$task_key])) {
                throw new \Exception('Invalid data structure or task key not found.');
            }

            // Initialize logs array if missing
            if (!isset($current_response[$week][$task_key]['logs']) || !is_array($current_response[$week][$task_key]['logs'])) {
                $current_response[$week][$task_key]['logs'] = [];
            }

            // ==========================================
            // LOGIC A: SKIP TASK
            // ==========================================
            if ($action === 'skip_task') {
                // Permission Check
                if ($member_role !== 'Admin' && $member_role !== 'Super User') {
                    throw new \Exception('Unauthorized: Only Admins can skip tasks.');
                }

                // Add System Log
                $new_log = [
                    'timestamp' => now()->toDateTimeString(),
                    'guide_id' => $guide_id,
                    'outcome' => 'Skipped',
                    'comment' => 'Task skipped by Admin via Dashboard.'
                ];
                $current_response[$week][$task_key]['logs'][] = $new_log;

                // Set Status
                $current_response[$week][$task_key]['status'] = 'Skipped';

                // Activate Next Task (within same week, or load next week)
                $task_keys = array_keys($current_response[$week]);
                $current_task_index = array_search($task_key, $task_keys);
                if ($current_task_index !== false && isset($task_keys[$current_task_index + 1])) {
                    $next_task_key = $task_keys[$current_task_index + 1];
                    $current_response[$week][$next_task_key]['display'] = true;
                } else {
                    // All tasks in this week skipped → load next week from task table (same campus)
                    $weekNumber = (int) filter_var($week, FILTER_SANITIZE_NUMBER_INT);
                    $nextWeekNumber = $weekNumber + 1;
                    $nextWeekKey = 'week' . $nextWeekNumber;

                    if (!isset($current_response[$nextWeekKey])) {
                        $userCampusId = Auth::user()->campus_id ?? 0;
                        $taskRow = DB::table('task')
                            ->where('wid', $nextWeekNumber)
                            ->where('campus_id', $userCampusId)
                            ->value('questions');

                        if ($taskRow) {
                            $rawJson = html_entity_decode($taskRow, ENT_QUOTES | ENT_HTML5);
                            $decoded = json_decode($rawJson, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                foreach ($decoded as &$td) {
                                    if (is_array($td)) {
                                        $td['logs'] = $td['logs'] ?? [];
                                    }
                                }
                                unset($td);
                                $firstTaskKey = array_key_first($decoded);
                                if ($firstTaskKey && isset($decoded[$firstTaskKey])) {
                                    $decoded[$firstTaskKey]['display'] = true;
                                }
                                $current_response[$nextWeekKey] = $decoded;
                            }
                        }
                    }
                }

                $msg = 'Task skipped successfully.';
            }

            // ==========================================
            // LOGIC B: LOG REGULAR OUTCOME
            // ==========================================
            else {
                if (empty($outcome)) {
                    throw new \Exception('Outcome is required.');
                }

                // ======================================================================
                // 24-HOUR RESTRICTION LOGIC (For ALL Positive Outcomes)
                // Checks the last approved task across ALL weeks (same week & previous weeks)
                // ======================================================================
                if ($outcome === 'Successful - Positive') {
                    $lastApprovedTimestamp = 0;

                    // Iterate through ALL weeks, ALL tasks to find the most recent approval
                    foreach ($current_response as $wk => $wkTasks) {
                        if (!is_array($wkTasks)) continue;
                        foreach ($wkTasks as $tKey => $tDetails) {
                            // Only check tasks that are already approved (NOT the current task being processed)
                            if (($wk !== $week || $tKey !== $task_key) && isset($tDetails['status']) && $tDetails['status'] === 'Approved') {
                                if (isset($tDetails['logs']) && is_array($tDetails['logs'])) {
                                    $lastLog = end($tDetails['logs']);
                                    if ($lastLog && isset($lastLog['timestamp'])) {
                                        $logTime = strtotime($lastLog['timestamp']);
                                        if ($logTime > $lastApprovedTimestamp) {
                                            $lastApprovedTimestamp = $logTime;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // Perform check
                    if ($lastApprovedTimestamp > 0) {
                        $currentTime = time();
                        $hoursDiff = ($currentTime - $lastApprovedTimestamp) / 3600;

                        if ($hoursDiff < 24) {
                            $hoursRemaining = round(24 - $hoursDiff);
                            throw new \Exception("Please wait {$hoursRemaining} more hours before approving the next task (24-hour cooldown applies across all weeks).");
                        }
                    }
                }
                // ======================================================================

                $new_log = [
                    'timestamp' => now()->toDateTimeString(),
                    'guide_id' => $guide_id,
                    'outcome' => $outcome,
                    'comment' => $comment
                ];
                $current_response[$week][$task_key]['logs'][] = $new_log;

                // Automation Logic for Positive Outcome
                if ($outcome === 'Successful - Positive') {
                    $current_response[$week][$task_key]['status'] = 'Approved';

                    // Get all task keys in this week
                    $task_keys = array_keys($current_response[$week]);
                    $current_task_index = array_search($task_key, $task_keys);

                    if ($current_task_index !== false && isset($task_keys[$current_task_index + 1])) {
                        // Next task exists within same week - activate it
                        $next_task_key = $task_keys[$current_task_index + 1];
                        $current_response[$week][$next_task_key]['display'] = true;
                    } else {
                        // All tasks in this week are complete → load next week from task table (same campus)
                        $weekNumber = (int) filter_var($week, FILTER_SANITIZE_NUMBER_INT);
                        $nextWeekNumber = $weekNumber + 1;
                        $nextWeekKey = 'week' . $nextWeekNumber;

                        // Only load next week if it doesn't already exist in the response
                        if (!isset($current_response[$nextWeekKey])) {
                            $userCampusId = Auth::user()->campus_id ?? 0;
                            $taskRow = DB::table('task')
                                ->where('wid', $nextWeekNumber)
                                ->where('campus_id', $userCampusId)
                                ->value('questions');

                            if ($taskRow) {
                                $rawJson = html_entity_decode($taskRow, ENT_QUOTES | ENT_HTML5);
                                $decoded = json_decode($rawJson, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                    // Add logs array to each task
                                    foreach ($decoded as &$td) {
                                        if (is_array($td)) {
                                            $td['logs'] = $td['logs'] ?? [];
                                        }
                                    }
                                    unset($td);
                                    // Set first task of next week as display=true
                                    $firstTaskKey = array_key_first($decoded);
                                    if ($firstTaskKey && isset($decoded[$firstTaskKey])) {
                                        $decoded[$firstTaskKey]['display'] = true;
                                    }
                                    $current_response[$nextWeekKey] = $decoded;
                                }
                            }
                        }
                    }
                } else {
                    // For non-positive outcomes, just mark as Pending (so they can try again)
                    $current_response[$week][$task_key]['status'] = 'Pending';
                }

                $msg = 'Outcome logged successfully.';
            }

            // --- SAVE TO DATABASE ---
            $new_response_json = json_encode($current_response);
            DB::table('member_tracking_followup')
                ->where('tracking_id', $tracking_id)
                ->update(['followup_response_new' => $new_response_json]);

            DB::commit();

            // Preserve guide_id in redirect if it exists
            $redirect = redirect()->back()->with('success', $msg);
            if ($request->has('guide_id')) {
                $redirect = redirect()->back()->with('success', $msg);
            }
            return $redirect;
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // ==========================================
    // API METHODS FOR MOBILE APP (unchanged)
    // ==========================================

    public function getApiIndex(Request $request)
    {
        try {
            $userId = $request->user_id;

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User ID not found'
                ], 401);
            }

            $firstTimers = DB::table('member_tracking_followup as mtf')
                ->join('first_timer as ft', 'mtf.first_timer_id', '=', 'ft.first_timer_id')
                ->where('mtf.tiu_member_id', $userId)
                ->select('ft.*', 'mtf.tracking_id')
                ->get();

            // Process each first timer
            foreach ($firstTimers as $firstTimer) {
                $tracking = DB::table('member_tracking_followup')
                    ->where('tracking_id', $firstTimer->tracking_id)
                    ->first();

                $pendingCount = 0;
                $totalTasks = 0;

                if ($tracking && $tracking->followup_response_new) {
                    $followup = json_decode($tracking->followup_response_new, true);
                    if (is_array($followup)) {
                        foreach ($followup as $week => $weekTasks) {
                            if (is_array($weekTasks)) {
                                foreach ($weekTasks as $task) {
                                    if (isset($task['display']) && $task['display']) {
                                        $totalTasks++;
                                        if (!isset($task['status']) || $task['status'] == 'Pending') {
                                            $pendingCount++;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                $firstTimer->pending_tasks = $pendingCount;
                $firstTimer->total_tasks = $totalTasks;
                $firstTimer->completion_percentage = $totalTasks > 0 ? round(($totalTasks - $pendingCount) / $totalTasks * 100) : 0;
            }

            return response()->json([
                'success' => true,
                'data' => $firstTimers,
                'count' => $firstTimers->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getApiFirstTimerDetails($id, Request $request)
    {
        try {
            $firstTimer = DB::table('first_timer')
                ->where('first_timer_id', $id)
                ->first();

            if (!$firstTimer) {
                return response()->json([
                    'success' => false,
                    'message' => 'First timer not found'
                ], 404);
            }

            $updates = DB::table('first_timers_updates')
                ->where('first_timer_id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'first_timer' => $firstTimer,
                    'updates' => $updates
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getApiTasks($id, Request $request)
    {
        try {
            $userId = $request->user_id;

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User ID not found'
                ], 401);
            }

            // Get tracking data
            $tracking = DB::table('member_tracking_followup')
                ->where('tiu_member_id', $userId)
                ->where('first_timer_id', $id)
                ->first();

            if (!$tracking) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'tracking_id' => null,
                        'tasks' => []
                    ],
                    'message' => 'No tasks found for this first timer'
                ]);
            }

            $tasksData = $tracking->followup_response_new ? json_decode($tracking->followup_response_new, true) : [];

            // Format tasks for mobile
            $formattedTasks = [];
            foreach ($tasksData as $weekName => $weekTasks) {
                $weekData = [];
                foreach ($weekTasks as $taskKey => $task) {
                    if (isset($task['display']) && $task['display']) {
                        $weekData[] = [
                            'key' => $taskKey,
                            'text' => $task['text'],
                            'status' => $task['status'] ?? 'Not Started',
                            'outcome' => $task['outcome'] ?? null,
                            'comment' => $task['comment'] ?? null,
                            'completed_at' => $task['completed_at'] ?? null,
                            'logs' => $task['logs'] ?? []
                        ];
                    }
                }
                if (count($weekData) > 0) {
                    $formattedTasks[$weekName] = $weekData;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'tracking_id' => $tracking->tracking_id,
                    'tasks' => $formattedTasks
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateTaskApi(Request $request)
    {
        $request->validate([
            'tracking_id' => 'required',
            'week' => 'required',
            'task_key' => 'required',
            'outcome' => 'required'
        ]);

        $tracking_id = $request->tracking_id;
        $week = $request->week;
        $task_key = $request->task_key;
        $outcome = $request->outcome;
        $comment = $request->comment ?? '';
        $guide_id = Auth::user()->tiu_member_id ?? Auth::user()->id;

        DB::beginTransaction();

        try {
            $tracking = DB::table('member_tracking_followup')
                ->where('tracking_id', $tracking_id)
                ->first();

            if (!$tracking) {
                throw new \Exception('Tracking record not found.');
            }

            $current_response = json_decode($tracking->followup_response_new, true);
            if (!isset($current_response[$week][$task_key])) {
                throw new \Exception('Task not found.');
            }

            if (!isset($current_response[$week][$task_key]['logs'])) {
                $current_response[$week][$task_key]['logs'] = [];
            }

            // 24-hour restriction across ALL weeks for positive outcomes
            if ($outcome === 'Successful - Positive') {
                $lastApprovedTimestamp = 0;

                // Iterate through ALL weeks, ALL tasks to find the most recent approval
                foreach ($current_response as $wk => $wkTasks) {
                    if (!is_array($wkTasks)) continue;
                    foreach ($wkTasks as $tKey => $tDetails) {
                        if (($wk !== $week || $tKey !== $task_key) && isset($tDetails['status']) && $tDetails['status'] === 'Approved') {
                            if (isset($tDetails['logs']) && is_array($tDetails['logs'])) {
                                $lastLog = end($tDetails['logs']);
                                if ($lastLog && isset($lastLog['timestamp'])) {
                                    $logTime = strtotime($lastLog['timestamp']);
                                    if ($logTime > $lastApprovedTimestamp) {
                                        $lastApprovedTimestamp = $logTime;
                                    }
                                }
                            }
                        }
                    }
                }

                if ($lastApprovedTimestamp > 0) {
                    $hoursDiff = (time() - $lastApprovedTimestamp) / 3600;
                    if ($hoursDiff < 24) {
                        $hoursRemaining = round(24 - $hoursDiff);
                        throw new \Exception("Please wait {$hoursRemaining} more hours before approving the next task (24-hour cooldown applies across all weeks).");
                    }
                }
            }

            // Add log
            $current_response[$week][$task_key]['logs'][] = [
                'timestamp' => now()->toDateTimeString(),
                'guide_id' => $guide_id,
                'outcome' => $outcome,
                'comment' => $comment
            ];

            // Update status
            if ($outcome === 'Successful - Positive') {
                $current_response[$week][$task_key]['status'] = 'Approved';
                $current_response[$week][$task_key]['outcome'] = $outcome;
                $current_response[$week][$task_key]['comment'] = $comment;
                $current_response[$week][$task_key]['completed_at'] = now()->toDateTimeString();

                // Activate next task (within same week, or load next week)
                $task_keys = array_keys($current_response[$week]);
                $current_index = array_search($task_key, $task_keys);
                if ($current_index !== false && isset($task_keys[$current_index + 1])) {
                    $next_key = $task_keys[$current_index + 1];
                    $current_response[$week][$next_key]['display'] = true;
                } else {
                    // All tasks complete → load next week from task table (same campus)
                    $weekNumber = (int) filter_var($week, FILTER_SANITIZE_NUMBER_INT);
                    $nextWeekNumber = $weekNumber + 1;
                    $nextWeekKey = 'week' . $nextWeekNumber;

                    if (!isset($current_response[$nextWeekKey])) {
                        $userCampusId = Auth::user()->campus_id ?? 0;
                        $taskRow = DB::table('task')
                            ->where('wid', $nextWeekNumber)
                            ->where('campus_id', $userCampusId)
                            ->value('questions');

                        if ($taskRow) {
                            $rawJson = html_entity_decode($taskRow, ENT_QUOTES | ENT_HTML5);
                            $decoded = json_decode($rawJson, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                foreach ($decoded as &$td) {
                                    if (is_array($td)) {
                                        $td['logs'] = $td['logs'] ?? [];
                                    }
                                }
                                unset($td);
                                $firstTaskKey = array_key_first($decoded);
                                if ($firstTaskKey && isset($decoded[$firstTaskKey])) {
                                    $decoded[$firstTaskKey]['display'] = true;
                                }
                                $current_response[$nextWeekKey] = $decoded;
                            }
                        }
                    }
                }
            } else {
                $current_response[$week][$task_key]['status'] = 'Pending';
            }

            DB::table('member_tracking_followup')
                ->where('tracking_id', $tracking_id)
                ->update(['followup_response_new' => json_encode($current_response)]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully',
                'data' => $current_response[$week][$task_key]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function updateFieldApi(Request $request)
    {
        $request->validate([
            'first_timer_id' => 'required',
            'field' => 'required|in:fof,address,department,cluster,water_baptism,holy_ghost_baptism,birthday,community',
            'value' => 'required'
        ]);

        if ($request->field === 'address') {
            DB::table('first_timer')
                ->where('first_timer_id', $request->first_timer_id)
                ->update(['address' => $request->value]);
        } else {
            $existing = DB::table('first_timers_updates')
                ->where('first_timer_id', $request->first_timer_id)
                ->first();

            if ($existing) {
                DB::table('first_timers_updates')
                    ->where('first_timer_id', $request->first_timer_id)
                    ->update([$request->field => $request->value]);
            } else {
                DB::table('first_timers_updates')->insert([
                    'first_timer_id' => $request->first_timer_id,
                    $request->field => $request->value,
                    'created_date' => now()
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Updated successfully'
        ]);
    }

    public function getApiProgress($id, Request $request)
    {
        $userId = $request->user_id;

        $tracking = DB::table('member_tracking_followup')
            ->where('tiu_member_id', $userId)
            ->where('first_timer_id', $id)
            ->first();

        $stats = ['approved' => 0, 'pending' => 0, 'total' => 0];

        if ($tracking && $tracking->followup_response_new) {
            $followup = json_decode($tracking->followup_response_new, true);
            foreach ($followup as $week => $weekTasks) {
                foreach ($weekTasks as $task) {
                    if (isset($task['display']) && $task['display']) {
                        $stats['total']++;
                        if (isset($task['status']) && $task['status'] === 'Approved') {
                            $stats['approved']++;
                        } else {
                            $stats['pending']++;
                        }
                    }
                }
            }
        }

        $stats['percentage'] = $stats['total'] > 0 ? round($stats['approved'] / $stats['total'] * 100) : 0;

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}