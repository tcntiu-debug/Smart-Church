<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PendingTasksController extends Controller
{
    public function __construct()
    {
        // The Blade board stays behind the session guard; the two /api/*
        // methods must not. App\Http\Middleware\Authenticate implements
        // AuthenticatesRequests, and that interface sits in the framework's
        // $middlewarePriority, so the pipeline hoists 'auth' ABOVE the
        // verify.token guard declared in routes/api.php. Left on the API
        // methods, 'auth' evaluated Auth::check() before VerifyApiToken had
        // called Auth::loginUsingId(), so /api/pending-tasks and
        // /api/pending-tasks/touchpoints answered {"message":"Unauthenticated."}
        // for every caller - including one holding a valid token.
        $this->middleware('auth')->except(['getApiPendingTasks', 'getApiTouchpoints']);
    }

    /**
     * Display pending tasks board
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        $full_name_session = $user->first_name . ' ' . $user->last_name;
        $campus_id = $user->campus_id ?? 0;
        
        // Time greeting
        date_default_timezone_set('Africa/Lagos');
        $hour = date('H');
        if ($hour < 12) {
            $time_greeting = "Good morning";
        } elseif ($hour < 17) {
            $time_greeting = "Good afternoon";
        } else {
            $time_greeting = "Good evening";
        }
        
        // Filter logic
        $filter_tp = $request->get('touchpoint', '');
        
        // Fetch unique touchpoints for dropdown
        $tp_list = $this->touchpointList($campus_id);
        
        $pending_actions = $this->pendingActions($campus_id, $filter_tp);
        
        return view('pending-tasks.index', compact(
            'pending_actions', 'filter_tp', 'tp_list', 'campus_id', 
            'full_name_session', 'time_greeting'
        ));
    }
    
    /**
     * GET /api/pending-tasks - the mobile Action Board.
     *
     * Rows are the very objects the Blade board renders, so the app depends on
     * these aliases: ft_first_name, ft_last_name, ft_phone, attendant_type,
     * week_label, next_task, latest_pending_log.{outcome,comment}, guide_*.
     */
    public function getApiPendingTasks(Request $request)
    {
        $user = Auth::user();
        $campus_id = $user->campus_id ?? 0;

        $pending_actions = $this->pendingActions($campus_id, $request->get('touchpoint', ''));

        return response()->json([
            'success' => true,
            'data' => $pending_actions,
            'total' => count($pending_actions),
            'campus_id' => $campus_id,
        ]);
    }

    /**
     * GET /api/pending-tasks/touchpoints - filter options for the Action Board.
     *
     * The picker draws one item per row from tp.subgroup, so the row objects go
     * over the wire intact instead of being flattened to bare strings.
     */
    public function getApiTouchpoints()
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data' => $this->touchpointList($user->campus_id ?? 0),
        ]);
    }

    /**
     * Guide touchpoints present on this campus's follow-up rows.
     */
    private function touchpointList($campus_id)
    {
        // Campus filter: null in single campus mode, so the condition and its
        // binding only exist while multi-campus is on.
        $campusId = Campus::filterId($campus_id);

        return DB::select("
            SELECT DISTINCT tm.subgroup 
            FROM tiu_member tm
            JOIN member_tracking_followup mtf ON tm.tiu_member_id = mtf.tiu_member_id
            JOIN first_timer ft ON mtf.first_timer_id = ft.first_timer_id
            WHERE tm.subgroup IS NOT NULL 
            AND tm.subgroup != '' 
            " . ($campusId ? ' AND ft.campus_id = ?' : '') . "
            ORDER BY tm.subgroup ASC
        ", $campusId ? [$campusId] : []);
    }

    /**
     * Board rows for one campus: rank 1 follow-up rows that still have a
     * visible action in status Pending, each carrying the next task text, its
     * week label and the newest log entry.
     *
     * Shared by the Blade board and GET /api/pending-tasks so the two can never
     * drift apart; the aliases returned here are the mobile app's contract.
     */
    private function pendingActions($campus_id, $filter_tp)
    {
        // Build where clauses
        $where_clauses = ["mtf.followup_rank = 1"];
        $bindings = [];

        // Campus filter (inert while the church runs a single campus)
        $campusId = Campus::filterId($campus_id);

        if ($campusId) {
            $where_clauses[] = "ft.campus_id = ?";
            $bindings[] = $campusId;
        }

        if ($filter_tp !== '') {
            if ($filter_tp === 'None') {
                $where_clauses[] = "(tm.subgroup = '' OR tm.subgroup IS NULL)";
            } else {
                // Bound, never interpolated: this value now also arrives
                // straight off the API query string, where the caller owns it.
                $where_clauses[] = "tm.subgroup = ?";
                $bindings[] = $filter_tp;
            }
        }

        $where_sql = implode(" AND ", $where_clauses);

        // Main query to fetch data
        $sql = "
            SELECT 
                mtf.tracking_id,
                mtf.tiu_member_id,
                mtf.followup_response_new,
                ft.first_name AS ft_first_name,
                ft.last_name AS ft_last_name,
                ft.phone_number AS ft_phone,
                ft.attendant_type,
                ft.campus_id AS ft_campus_id,
                tm.first_name AS guide_first_name,
                tm.last_name AS guide_last_name,
                tm.phone_number AS guide_phone,
                tm.subgroup AS guide_touchpoint
            FROM member_tracking_followup mtf
            JOIN first_timer ft ON mtf.first_timer_id = ft.first_timer_id
            JOIN tiu_member tm ON mtf.tiu_member_id = tm.tiu_member_id
            WHERE {$where_sql}
            ORDER BY tm.first_name ASC
        ";

        $results = DB::select($sql, $bindings);
        $pending_actions = [];

        foreach ($results as $row) {
            $json_data = json_decode($row->followup_response_new, true);
            if (!is_array($json_data)) continue;

            $found_task = false;
            $next_task_text = "";
            $current_week_label = "";
            $latest_pending_log = null;

            foreach ($json_data as $week_key => $tasks) {
                if (!is_array($tasks)) continue;
                foreach ($tasks as $task_key => $details) {
                    if (isset($details['display']) && $details['display'] == true &&
                        isset($details['status']) && $details['status'] == 'Pending' &&
                        !$found_task) {
                        $next_task_text = $details['text'];
                        $current_week_label = $week_key;
                        $found_task = true;

                        if (isset($details['logs']) && is_array($details['logs'])) {
                            foreach ($details['logs'] as $log) {
                                if (isset($log['timestamp'])) {
                                    if ($latest_pending_log === null || strtotime($log['timestamp']) > strtotime($latest_pending_log['timestamp'])) {
                                        $latest_pending_log = $log;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($found_task) {
                $row->next_task = $next_task_text;
                $row->week_label = $current_week_label;
                $row->latest_pending_log = $latest_pending_log;
                $pending_actions[] = $row;
            }
        }

        return $pending_actions;
    }

    /**
     * Format phone number for WhatsApp
     */
    private function formatForWhatsApp($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strpos($phone, '234') === 0) return $phone;
        return (strpos($phone, '0') === 0) ? '234' . substr($phone, 1) : '234' . $phone;
    }
}