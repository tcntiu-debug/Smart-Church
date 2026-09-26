<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        $touchpointGroups = DB::select("
            SELECT sg.subid, sg.sub_group_name, sg.lead_id 
            FROM sub_group sg 
            JOIN department d ON sg.department_id = d.dept_id
            JOIN tiu_member tm ON sg.sub_group_name = tm.subgroup 
            WHERE tm.tiu_member_id = ? AND d.dept_name = 'Tracking and Integration'
        ", [$userId]);

        $touchpoints = [];
        foreach ($touchpointGroups as $group) {
            $members = DB::select("
                SELECT tiu_member_id, first_name, last_name, phone_number 
                FROM tiu_member 
                WHERE subgroup = ? AND status != '2' 
                ORDER BY first_name, last_name
            ", [$group->sub_group_name]);

            $touchpoints[] = [
                'group_name' => $group->sub_group_name,
                'lead_id' => $group->lead_id,
                'members' => $members,
                'member_count' => count($members)
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $touchpoints
        ]);
    }
}
