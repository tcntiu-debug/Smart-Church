<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        $groups = DB::table('sub_group')
            ->where('department_id', $request->department_id)
            ->where('campus_id', $campusId)
            ->orderBy('sub_group_name')
            ->get();

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
        $searchKey = $deptId . '::' . $subgroupName;

        $members = DB::table('tiu_member')
            ->where('subgroup', 'LIKE', '%"' . $searchKey . '"%')
            ->where('status', '!=', '2')
            ->select('tiu_member_id', 'first_name', 'last_name', 'phone_number', 'email')
            ->get();

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

        $subgroupData = json_decode($member->subgroup, true);
        if (!is_array($subgroupData)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $groups = [];
        foreach ($subgroupData as $key) {
            $parts = explode('::', $key);
            if (count($parts) == 2) {
                $deptId = $parts[0];
                $groupName = $parts[1];
                
                $dept = DB::table('department')->where('dept_id', $deptId)->first();
                $group = DB::table('sub_group')
                    ->where('department_id', $deptId)
                    ->where('sub_group_name', $groupName)
                    ->first();

                if ($group) {
                    $groups[] = [
                        'dept_id' => $deptId,
                        'dept_name' => $dept->dept_name ?? '',
                        'subid' => $group->subid,
                        'sub_group_name' => $group->sub_group_name,
                        'lead_id' => $group->lead_id,
                        'is_lead' => ($group->lead_id == $userId)
                    ];
                }
            }
        }

        return response()->json(['success' => true, 'data' => $groups]);
    }
}
