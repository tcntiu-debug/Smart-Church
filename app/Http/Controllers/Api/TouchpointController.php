<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SubgroupAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TouchpointController extends Controller
{
    /**
     * Get user's touchpoint groups and members
     */
    public function index(Request $request)
    {
        $userId = $request->user_id;

        $member = DB::selectOne("SELECT subgroup FROM tiu_member WHERE tiu_member_id = ?", [$userId]);

        $touchpoints = [];

        if ($member) {
            // The column holds "23::Touch Point 2" or, for older rows, the bare name.
            $groupNames = SubgroupAssignmentService::namesForDepartment(
                $member->subgroup,
                SubgroupAssignmentService::TOUCHPOINT_DEPARTMENT_ID
            );

            $groups = DB::table('sub_group')
                ->where('department_id', SubgroupAssignmentService::TOUCHPOINT_DEPARTMENT_ID)
                ->whereIn('sub_group_name', $groupNames)
                ->orderBy('sub_group_name')
                ->get();

            foreach ($groups as $group) {
                $membersQuery = DB::table('tiu_member')
                    ->where('status', '!=', '2')
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->select('tiu_member_id', 'first_name', 'last_name', 'phone_number');

                SubgroupAssignmentService::scopeAssignedTo($membersQuery, $group->department_id, $group->sub_group_name);

                $members = $membersQuery->get();

                $touchpoints[] = [
                    'group_name' => $group->sub_group_name,
                    'lead_id' => $group->lead_id,
                    'members' => $members,
                    'member_count' => count($members)
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $touchpoints
        ]);
    }
}
