<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceAnalysisController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $memberRole = session('member_role', 'Guest');
        $campusId = $user->campus_id;

        // Selected date (default: today)
        $selectedDate = $request->get('date', date('Y-m-d'));

        // Determine which groups this user leads
        $leadDeptIds = session('lead_of_dept', []);
        $leadHfIds = session('lead_of_house_fellowship', []);
        $leadClusterIds = session('lead_of_cluster', []);

        if (!is_array($leadDeptIds)) $leadDeptIds = [];
        if (!is_array($leadHfIds)) $leadHfIds = [];
        if (!is_array($leadClusterIds)) $leadClusterIds = [];

        $viewAs = $request->get('view', 'department'); // department, house_fellowship, cluster
        $groupId = $request->get('group_id', '');

        // All departments/house-fellowships/clusters for the user's campus
        $allDepartments = DB::table('department')
            ->where('dept_type', 'Department')
            ->orderBy('dept_name', 'asc')
            ->get();

        $allHouseFellowships = DB::table('department')
            ->where('dept_type', 'House Fellowship')
            ->orderBy('dept_name', 'asc')
            ->get();

        $allClusters = DB::table('department')
            ->where('dept_type', 'Cluster')
            ->orderBy('dept_name', 'asc')
            ->get();

        // Determine available groups based on user's role/leadership
        $availableDepartments = collect();
        $availableHFs = collect();
        $availableClusters = collect();

        $isSuperAdmin = in_array($memberRole, ['Super User', 'Admin']);

        if ($isSuperAdmin) {
            // Admin sees all groups in their campus
            $availableDepartments = $allDepartments;
            $availableHFs = $allHouseFellowships;
            $availableClusters = $allClusters;
        } else {
            // Lead sees only groups they lead
            if (!empty($leadDeptIds)) {
                $availableDepartments = $allDepartments->whereIn('dept_id', $leadDeptIds);
            }
            if (!empty($leadHfIds)) {
                $availableHFs = $allHouseFellowships->whereIn('dept_id', $leadHfIds);
            }
            if (!empty($leadClusterIds)) {
                $availableClusters = $allClusters->whereIn('dept_id', $leadClusterIds);
            }
        }

        // Members who were present on the selected date
        $presentMemberIds = DB::table('church_attendance')
            ->where('attendance_date', $selectedDate)
            ->where('member_type', 'tiu_member')
            ->pluck('member_id')
            ->toArray();

        // Absent members per group
        $absentMembers = collect();
        $presentMembersList = collect();
        $groupName = '';

        // Get church types mapping
        $churchTypeMap = DB::table('church_type')
            ->pluck('church_type_name', 'id')
            ->toArray();

        // Get community mapping
        $communityMap = DB::table('communities')
            ->pluck('community_name', 'id')
            ->toArray();

        // Get department names mapping
        $allGroupsMap = DB::table('department')
            ->pluck('dept_name', 'dept_id')
            ->toArray();

        if ($groupId) {
            // Get the selected group info
            $selectedGroup = DB::table('department')->where('dept_id', $groupId)->first();
            $groupName = $selectedGroup->dept_name ?? '';

            // Determine the JSON column to search
            $jsonColumn = '';
            if ($viewAs === 'department') $jsonColumn = 'department_name';
            elseif ($viewAs === 'house_fellowship') $jsonColumn = 'house_fellowship';
            elseif ($viewAs === 'cluster') $jsonColumn = 'cluster';

            if ($jsonColumn && $selectedGroup) {
                // Get all members in this group (active, same campus)
                $allGroupMembers = DB::table('tiu_member')
                    ->where(function($q) use ($jsonColumn, $groupId) {
                        $q->where($jsonColumn, 'LIKE', '%"' . $groupId . '"%')
                          ->orWhere($jsonColumn, 'LIKE', '%[' . $groupId . ']%')
                          ->orWhere($jsonColumn, 'LIKE', '%[' . $groupId . '%')
                          ->orWhere($jsonColumn, 'LIKE', '%' . $groupId . '%]')
                          ->orWhere($jsonColumn, 'LIKE', '%' . $groupId . '%');
                    })
                    ->where('status', 1)
                    ->orderBy('first_name', 'asc')
                    ->orderBy('last_name', 'asc')
                    ->get();

                if ($campusId) {
                    $allGroupMembers = $allGroupMembers->where('campus_id', $campusId);
                }

                // Separate present and absent
                foreach ($allGroupMembers as $member) {
                    $member->department_names = $this->convertIdsToNames($member->department_name, $allGroupsMap);
                    $member->hf_names = $this->convertIdsToNames($member->house_fellowship, $allGroupsMap);
                    $member->cluster_names = $this->convertIdsToNames($member->cluster, $allGroupsMap);
                    $member->church_type_name = $churchTypeMap[$member->church_type_id] ?? 'N/A';
                    $member->community_name = $communityMap[$member->community_id] ?? 'N/A';
                    $member->whatsapp_phone = $this->formatWhatsAppNumber($member->phone_number);

                    if (in_array($member->tiu_member_id, $presentMemberIds)) {
                        $presentMembersList->push($member);
                    } else {
                        $absentMembers->push($member);
                    }
                }
            }
        }

        // ===================================================================
        // CHILDREN ATTENDANCE ANALYSIS (always computed, not filtered by group)
        // ===================================================================
        // Children who were present on the selected date
        $presentChildIds = DB::table('children_church_attendance')
            ->where('attendance_date', $selectedDate)
            ->pluck('child_id')
            ->toArray();

        // All children in the user's campus
        $allChildrenQuery = DB::table('children_church')
            ->orderBy('child_name', 'asc');

        if ($campusId) {
            $allChildrenQuery->where('campus_id', $campusId);
        }

        $allChildren = $allChildrenQuery->get();

        // Separate present and absent children
        $presentChildren = collect();
        $absentChildren = collect();

        foreach ($allChildren as $child) {
            if (in_array($child->child_id, $presentChildIds)) {
                $presentChildren->push($child);
            } else {
                $absentChildren->push($child);
            }
        }

        $totalChildrenPresent = $presentChildren->count();
        $totalChildrenAbsent = $absentChildren->count();
        $totalChildren = $allChildren->count();

        // Summary counts
        $totalPresent = $presentMembersList->count();
        $totalAbsent = $absentMembers->count();
        $totalMembers = $totalPresent + $totalAbsent;

        return view('analytics.attendance-analysis', compact(
            'selectedDate',
            'viewAs',
            'groupId',
            'groupName',
            'availableDepartments',
            'availableHFs',
            'availableClusters',
            'absentMembers',
            'presentMembersList',
            'totalPresent',
            'totalAbsent',
            'totalMembers',
            'allDepartments',
            'allHouseFellowships',
            'allClusters',
            'isSuperAdmin',
            'presentChildren',
            'absentChildren',
            'totalChildrenPresent',
            'totalChildrenAbsent',
            'totalChildren'
        ));
    }

    /**
     * Helper: Convert JSON IDs to comma-separated names
     */
    private function convertIdsToNames($jsonIds, $map)
    {
        if (empty($jsonIds)) return 'N/A';
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
