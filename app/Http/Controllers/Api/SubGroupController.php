<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SubgroupAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubGroupController extends Controller
{
    /**
     * Get subgroups for a department
     */
    public function index(Request $request)
    {
        $request->validate(['department_id' => 'required|integer']);
        
        $userId = $request->user_id;
        $user = DB::selectOne("SELECT campus_id FROM tiu_member WHERE tiu_member_id = ?", [$userId]);
        $campusId = $user->campus_id ?? 1;

        $groups = SubgroupAssignmentService::groupsForDepartment($request->department_id, $campusId);

        return response()->json(['success' => true, 'data' => $groups]);
    }

    /**
     * Get members assigned to a subgroup
     */
    public function getMembers(Request $request)
    {
        $request->validate([
            'department_id' => 'required|integer',
            'subgroup_name' => 'required|string',
        ]);

        $deptId = $request->department_id;
        $subgroupName = $request->subgroup_name;

        $membersQuery = DB::table('tiu_member')
            ->where('status', '!=', '2')
            ->select('tiu_member_id', 'first_name', 'last_name', 'phone_number', 'email');

        // Matches "deptID::SubgroupName" and the legacy bare subgroup name.
        SubgroupAssignmentService::scopeAssignedTo($membersQuery, $deptId, $subgroupName);

        $members = $membersQuery->get();

        return response()->json(['success' => true, 'data' => $members]);
    }

    /**
     * Get my assigned subgroups
     */
    public function myGroups(Request $request)
    {
        $userId = $request->user_id;

        $member = DB::selectOne("SELECT subgroup FROM tiu_member WHERE tiu_member_id = ?", [$userId]);
        if (!$member || empty($member->subgroup)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $groups = [];
        foreach (SubgroupAssignmentService::groupsForMember($member->subgroup) as $group) {
            // Imported rows may only carry the legacy department name.
            $departmentId = SubgroupAssignmentService::departmentIdForGroup($group);
            $dept = $departmentId
                ? DB::table('department')->where('dept_id', $departmentId)->first()
                : null;

            $groups[] = [
                'dept_id' => (string) ($departmentId ?? ($group->department_id ?? '')),
                'dept_name' => $dept->dept_name ?? '',
                'subid' => $group->subid,
                'sub_group_name' => $group->sub_group_name,
                'lead_id' => $group->lead_id,
                'is_lead' => ($group->lead_id == $userId)
            ];
        }

        return response()->json(['success' => true, 'data' => $groups]);
    }
}
