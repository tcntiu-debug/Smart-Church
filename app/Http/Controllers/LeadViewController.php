<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class LeadViewController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Church Type Members View
     */
    public function churchTypeLead()
    {
        $user = Auth::user();
        $leadChurchTypeIds = session('lead_of_church_type', []);

        if (!is_array($leadChurchTypeIds)) {
            $leadChurchTypeIds = [];
        }

        $members = [];
        if (count($leadChurchTypeIds) > 0) {
            $members = DB::table('tiu_member')
                ->whereIn('church_type_id', $leadChurchTypeIds)
                ->orderBy('first_name', 'asc')
                ->orderBy('last_name', 'asc')
                ->get();
        }

        return view('lead.church-type', compact('members', 'leadChurchTypeIds'));
    }

    /**
     * Department/Group Members View (Department, House Fellowship, Cluster)
     */
  public function departmentLead()
{
    $user = Auth::user();

    // Debug - remove after testing
    \Log::info('=== LeadViewController Debug ===');
    \Log::info('lead_of_dept: ' . json_encode(session('lead_of_dept', [])));
    \Log::info('lead_of_house_fellowship: ' . json_encode(session('lead_of_house_fellowship', [])));
    \Log::info('lead_of_cluster: ' . json_encode(session('lead_of_cluster', [])));

    // Get IDs from session
    $leadDeptIds = session('lead_of_dept', []);
    $leadHfIds = session('lead_of_house_fellowship', []);
    $leadClusterIds = session('lead_of_cluster', []);

    if (!is_array($leadDeptIds)) $leadDeptIds = [];
    if (!is_array($leadHfIds)) $leadHfIds = [];
    if (!is_array($leadClusterIds)) $leadClusterIds = [];

    // Get all groups mapping for name lookup
    $allGroupsMap = DB::table('department')
        ->pluck('dept_name', 'dept_id')
        ->toArray();

    // Get church types mapping
    $churchTypeMap = DB::table('church_type')
        ->pluck('church_type_name', 'id')
        ->toArray();

    // Get community mapping
    $communityMap = DB::table('communities')
        ->pluck('community_name', 'id')
        ->toArray();

    // Helper function to fetch members by JSON column
    $fetchMembers = function ($ids, $columnName) use ($allGroupsMap, $churchTypeMap, $communityMap) {
        if (empty($ids)) return [];

        $members = [];

        foreach ($ids as $id) {
            // Try multiple search patterns
            $results = DB::table('tiu_member')
                ->where(function($query) use ($columnName, $id) {
                    $query->where($columnName, 'LIKE', '%"' . $id . '"%')      // matches "45"
                          ->orWhere($columnName, 'LIKE', '%[' . $id . ']%')    // matches [45]
                          ->orWhere($columnName, 'LIKE', '%[' . $id . '%')     // matches [45% (malformed)
                          ->orWhere($columnName, 'LIKE', '%' . $id . '%]')     // matches 45%]
                          ->orWhere($columnName, 'LIKE', '%' . $id . '%');     // fallback
                })
                ->orderBy('first_name', 'asc')
                ->orderBy('last_name', 'asc')
                ->get();

            foreach ($results as $member) {
                if (!isset($members[$member->tiu_member_id])) {
                    $members[$member->tiu_member_id] = $member;
                }
            }
        }

        // Add display data to each member
        foreach ($members as $member) {
            $member->department_names = $this->convertIdsToNames($member->department_name, $allGroupsMap);
            $member->hf_names = $this->convertIdsToNames($member->house_fellowship, $allGroupsMap);
            $member->cluster_names = $this->convertIdsToNames($member->cluster, $allGroupsMap);
            $member->church_type_name = $churchTypeMap[$member->church_type_id] ?? 'N/A';
            $member->community_name = $communityMap[$member->community_id] ?? 'N/A';
            $member->whatsapp_phone = $this->formatWhatsAppNumber($member->phone_number);
        }

        return array_values($members);
    };

    $departmentMembers = $fetchMembers($leadDeptIds, 'department_name');
    $hfMembers = $fetchMembers($leadHfIds, 'house_fellowship');
    $clusterMembers = $fetchMembers($leadClusterIds, 'cluster');

    return view('lead.department', compact('departmentMembers', 'hfMembers', 'clusterMembers'));
}

    /**
     * Department Only Members View (separated from combined page)
     */
    public function departmentMembersOnly()
    {
        $user = Auth::user();
        $leadDeptIds = session('lead_of_dept', []);
        $campusId = $user->campus_id;

        if (!is_array($leadDeptIds)) $leadDeptIds = [];

        // Get all groups mapping for name lookup
        $allGroupsMap = DB::table('department')
            ->pluck('dept_name', 'dept_id')
            ->toArray();

        // Get church types mapping
        $churchTypeMap = DB::table('church_type')
            ->pluck('church_type_name', 'id')
            ->toArray();

        // Get community mapping
        $communityMap = DB::table('communities')
            ->pluck('community_name', 'id')
            ->toArray();

        $members = [];
        if (!empty($leadDeptIds)) {
            $members = $this->fetchByColumn($leadDeptIds, 'department_name', $allGroupsMap, $churchTypeMap, $communityMap, $campusId);
        }

        return view('lead.department-only', compact('members'));
    }

    /**
     * House Fellowship Only Members View (separated from combined page)
     */
    public function houseFellowshipOnly()
    {
        $user = Auth::user();
        $leadHfIds = session('lead_of_house_fellowship', []);
        $campusId = $user->campus_id;

        if (!is_array($leadHfIds)) $leadHfIds = [];

        // Get all groups mapping for name lookup
        $allGroupsMap = DB::table('department')
            ->pluck('dept_name', 'dept_id')
            ->toArray();

        // Get church types mapping
        $churchTypeMap = DB::table('church_type')
            ->pluck('church_type_name', 'id')
            ->toArray();

        // Get community mapping
        $communityMap = DB::table('communities')
            ->pluck('community_name', 'id')
            ->toArray();

        $members = [];
        if (!empty($leadHfIds)) {
            $members = $this->fetchByColumn($leadHfIds, 'house_fellowship', $allGroupsMap, $churchTypeMap, $communityMap, $campusId);
        }

        return view('lead.house-fellowship', compact('members'));
    }

    /**
     * Cluster Only Members View (separated from combined page)
     */
    public function clusterOnly()
    {
        $user = Auth::user();
        $leadClusterIds = session('lead_of_cluster', []);
        $campusId = $user->campus_id;

        if (!is_array($leadClusterIds)) $leadClusterIds = [];

        // Get all groups mapping for name lookup
        $allGroupsMap = DB::table('department')
            ->pluck('dept_name', 'dept_id')
            ->toArray();

        // Get church types mapping
        $churchTypeMap = DB::table('church_type')
            ->pluck('church_type_name', 'id')
            ->toArray();

        // Get community mapping
        $communityMap = DB::table('communities')
            ->pluck('community_name', 'id')
            ->toArray();

        $members = [];
        if (!empty($leadClusterIds)) {
            $members = $this->fetchByColumn($leadClusterIds, 'cluster', $allGroupsMap, $churchTypeMap, $communityMap, $campusId);
        }

        return view('lead.cluster', compact('members'));
    }

    /**
     * First Timers Lead View
     */
    public function firstTimerLead(Request $request)
    {
        $user = Auth::user();
        $leadChurchTypeIds = session('lead_of_church_type', []);
        $campusId = $user->campus_id;

        if (!is_array($leadChurchTypeIds)) {
            $leadChurchTypeIds = [];
        }

        // Get day limit from settings
        $dayLimit = DB::table('first_time_view_limit')->value('data_limit') ?? 60;

        // Filter parameters
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $genderFilter = $request->get('gender_filter');

        // Get all departments for dropdown
        $departments = DB::table('department')
            ->where('dept_type', 'Department')
            ->orderBy('dept_name', 'asc')
            ->pluck('dept_name')
            ->toArray();

        $query = DB::table('first_timer as ft')
            ->leftJoin('church_type as ct', 'ft.church_type_id', '=', 'ct.id')
            ->leftJoin('member_tracking_followup as mtf', 'ft.first_timer_id', '=', 'mtf.first_timer_id')
            ->leftJoin('tiu_member as tm', 'mtf.tiu_member_id', '=', 'tm.tiu_member_id')
            ->select(
                'ft.*',
                'ct.church_type_name',
                DB::raw('CONCAT(tm.first_name, " ", tm.last_name) as guide_name')
            );

        // Filter by church type leadership
        if (count($leadChurchTypeIds) > 0) {
            $query->whereIn('ft.church_type_id', $leadChurchTypeIds);
        } else {
            $query->whereRaw('1 = 0'); // No results if not leading any church type
        }

        // Filter by same campus
        if ($campusId) {
            $query->where('ft.campus_id', $campusId);
        }

        // Date filters
        if ($startDate && $endDate) {
            $query->whereBetween(DB::raw('DATE(ft.register_date)'), [$startDate, $endDate]);
        } else {
            $query->where(DB::raw('DATE(ft.register_date)'), '>=', DB::raw("DATE_SUB(CURDATE(), INTERVAL $dayLimit DAY)"));
        }

        // Gender filter
        if ($genderFilter) {
            $query->where('ft.gender', $genderFilter);
        }

        $firstTimers = $query->orderBy('ft.register_date', 'desc')->get();

        // Format phone numbers for WhatsApp
        foreach ($firstTimers as $ft) {
            $ft->whatsapp_phone = $this->formatWhatsAppNumber($ft->phone_number);
            $ft->attended = $ft->attendance_count >= 1;
        }

        return view('lead.first-timer', compact('firstTimers', 'departments', 'startDate', 'endDate', 'genderFilter', 'dayLimit'));
    }

    /**
     * Helper: Fetch members by JSON column with mapping data
     */
    private function fetchByColumn($ids, $columnName, $allGroupsMap, $churchTypeMap, $communityMap, $campusId = null)
    {
        if (empty($ids)) return [];

        $members = [];

        foreach ($ids as $id) {
            $query = DB::table('tiu_member')
                ->where(function($query) use ($columnName, $id) {
                    $query->where($columnName, 'LIKE', '%"' . $id . '"%')
                          ->orWhere($columnName, 'LIKE', '%[' . $id . ']%')
                          ->orWhere($columnName, 'LIKE', '%[' . $id . '%')
                          ->orWhere($columnName, 'LIKE', '%' . $id . '%]')
                          ->orWhere($columnName, 'LIKE', '%' . $id . '%');
                });

            // Filter by same campus as the logged-in user
            if ($campusId) {
                $query->where('campus_id', $campusId);
            }

            $results = $query->orderBy('first_name', 'asc')
                ->orderBy('last_name', 'asc')
                ->get();

            foreach ($results as $member) {
                if (!isset($members[$member->tiu_member_id])) {
                    $members[$member->tiu_member_id] = $member;
                }
            }
        }

        // Add display data to each member
        foreach ($members as $member) {
            $member->department_names = $this->convertIdsToNames($member->department_name, $allGroupsMap);
            $member->hf_names = $this->convertIdsToNames($member->house_fellowship, $allGroupsMap);
            $member->cluster_names = $this->convertIdsToNames($member->cluster, $allGroupsMap);
            $member->church_type_name = $churchTypeMap[$member->church_type_id] ?? 'N/A';
            $member->community_name = $communityMap[$member->community_id] ?? 'N/A';
            $member->whatsapp_phone = $this->formatWhatsAppNumber($member->phone_number);
        }

        return array_values($members);
    }

    /**
     * Helper: Convert JSON IDs to comma-separated names
     */
    private function convertIdsToNames($jsonIds, $map)
    {
        if (empty($jsonIds)) return 'N/A';

        // If it's already a string, decode it
        if (is_string($jsonIds)) {
            $ids = json_decode($jsonIds, true);
        } else {
            $ids = $jsonIds;
        }

        if (!is_array($ids) || empty($ids)) return 'N/A';

        $names = array_filter(array_map(fn($id) => $map[$id] ?? null, $ids));
        return !empty($names) ? implode(', ', $names) : 'N/A';
    }

    /**
     * Helper: Format phone number for WhatsApp
     */
    private function formatWhatsAppNumber($phone)
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($clean) === 11 && substr($clean, 0, 1) === '0') {
            return '234' . substr($clean, 1);
        }
        return $clean;
    }
}
