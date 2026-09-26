<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PendingTasksController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
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
        
        // Build where clauses
        $where_clauses = ["mtf.followup_rank = 1"];
        
        // Campus filter
        if (!empty($campus_id)) {
            $where_clauses[] = "ft.campus_id = " . intval($campus_id);
        }
        
        if ($filter_tp !== '') {
            if ($filter_tp === 'None') {
                $where_clauses[] = "(tm.subgroup = '' OR tm.subgroup IS NULL)";
            } else {
                $where_clauses[] = "tm.subgroup = '" . addslashes($filter_tp) . "'";
            }
        }
        
        $where_sql = implode(" AND ", $where_clauses);
        
        // Fetch unique touchpoints for dropdown
        $tp_list = DB::select("
            SELECT DISTINCT tm.subgroup 
            FROM tiu_member tm
            JOIN member_tracking_followup mtf ON tm.tiu_member_id = mtf.tiu_member_id
            JOIN first_timer ft ON mtf.first_timer_id = ft.first_timer_id
            WHERE tm.subgroup IS NOT NULL 
            AND tm.subgroup != '' 
            AND ft.campus_id = ?
            ORDER BY tm.subgroup ASC
        ", [$campus_id]);
        
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
        
        $results = DB::select($sql);
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
        
        return view('pending-tasks.index', compact(
            'pending_actions', 'filter_tp', 'tp_list', 'campus_id', 
            'full_name_session', 'time_greeting'
        ));
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