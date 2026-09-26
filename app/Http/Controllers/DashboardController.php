<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

public function adminOverview(Request $request)
{
    $user = Auth::user();
    $member_role = $user->member_role;
    $tiu_member_id_session = $user->tiu_member_id;
    
    $filter_role = $request->get('member_role', 'tiu');
    $filter_gender = $request->get('gender', '');
    
    // Pre-fetch all department names
    $all_departments = DB::table('department')
        ->pluck('dept_name', 'dept_id')
        ->toArray();
    
    $query = DB::table('tiu_member as tm')
        ->leftJoin('member_tracking_followup as mtf', 'tm.tiu_member_id', '=', 'mtf.tiu_member_id')
        ->leftJoin('first_timer as ft', 'mtf.first_timer_id', '=', 'ft.first_timer_id')
        ->whereNotIn('tm.status', [2, 3]);
    
    // Filter by same campus (supports multi-campus)
    if (in_array($member_role, ['Super User', 'Admin'])) {
        $query->where('tm.campus_id', $user->campus_id);
    }
    
    // Filter by role
    if ($filter_role === 'tiu') {
        $query->where('tm.department_name', 'like', '%"23"%');
    } elseif ($filter_role === 'guest') {
        $query->where('tm.member_role', 'TIU Guest');
    }
    
    // Filter by gender
    if (!empty($filter_gender)) {
        $query->where('tm.gender', $filter_gender);
    }
    
    // Non-admin users can only see themselves
    if (!in_array($member_role, ['Super User', 'Admin'])) {
        $query->where('tm.tiu_member_id', $tiu_member_id_session);
    }
    
    $guides = $query->select(
            'tm.tiu_member_id',
            'tm.first_name',
            'tm.last_name',
            'tm.phone_number',
            'tm.member_role',
            'tm.department_name',
            DB::raw('COUNT(mtf.first_timer_id) as first_timer_count'),
            DB::raw("GROUP_CONCAT(CONCAT_WS('~|~', ft.first_name, ft.last_name, mtf.timeStamp_registered) SEPARATOR '~||~') as first_timers_data")
        )
        ->groupBy('tm.tiu_member_id', 'tm.first_name', 'tm.last_name', 'tm.phone_number', 'tm.member_role', 'tm.department_name')
        ->orderByRaw('first_timer_count DESC, tm.first_name ASC')
        ->get()
        ->toArray();
    
    // Group guides by count
    $grouped_guides = [
        'gt_8' => ['label' => 'Guides with 9+ First-Timers', 'guides' => []],
    ];
    
    for ($i = 8; $i >= 0; $i--) {
        $plural_s = ($i == 1) ? '' : 's';
        $key = 'count_' . $i;
        $grouped_guides[$key] = ['label' => "Guides with $i First-Timer$plural_s", 'guides' => []];
    }
    
    foreach ($guides as $guide) {
        $guide = (array)$guide;
        $count = (int)$guide['first_timer_count'];
        if ($count > 8) {
            $grouped_guides['gt_8']['guides'][] = $guide;
        } else {
            $key = 'count_' . $count;
            $grouped_guides[$key]['guides'][] = $guide;
        }
    }
    
    // ==========================================
    // TODAY'S CHURCH ATTENDANCE
    // ==========================================
    // Fetch members (tiu_member) who marked "I am in church today"
    $todayAttendance = DB::table('church_attendance as ca')
        ->leftJoin('tiu_member as tm', function ($join) {
            $join->on('ca.member_id', '=', 'tm.tiu_member_id')
                ->where('ca.member_type', '=', 'tiu_member');
        })
        ->leftJoin('church_type as ct', 'ca.church_type_id', '=', 'ct.id')
        ->whereDate('ca.attendance_date', today())
        ->where('tm.campus_id', $user->campus_id)
        ->select(
            'ca.*',
            'tm.first_name',
            'tm.last_name',
            'tm.phone_number as member_phone',
            'tm.campus_id',
            'ct.church_type_name'
        )
        ->orderBy('ca.date_created', 'desc')
        ->get();

    return view('admin.overview', compact('grouped_guides', 'filter_role', 'filter_gender', 'all_departments', 'todayAttendance'));
}

    public function myTask()
    {
        $user = Auth::user();
        $userId = $user->tiu_member_id;

        $assignments = DB::table('member_tracking_followup as mtf')
            ->join('first_timer as ft', 'mtf.first_timer_id', '=', 'ft.first_timer_id')
            ->where('mtf.tiu_member_id', $userId)
            ->select('mtf.*', 'ft.first_name', 'ft.last_name', 'ft.phone_number', 'ft.status')
            ->paginate(20);

        return view('member.tasks', compact('assignments'));
    }
}
