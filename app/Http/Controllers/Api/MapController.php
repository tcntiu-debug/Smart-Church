<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapController extends Controller
{
    /**
     * Get communities with member counts for map
     */
    public function getCommunities(Request $request)
    {
        $userId = $request->user_id;
        $user = DB::selectOne("SELECT campus_id FROM tiu_member WHERE tiu_member_id = ?", [$userId]);
        $campusId = $user->campus_id ?? 0;

        $communities = DB::select("
            SELECT c.id AS community_id, c.community_name, c.latitude, c.longitude, 
                   COUNT(t.tiu_member_id) AS member_count,
                   SUM(CASE WHEN t.gender = 'Male' THEN 1 ELSE 0 END) AS male_count,
                   SUM(CASE WHEN t.gender = 'Female' THEN 1 ELSE 0 END) AS female_count
            FROM communities c 
            LEFT JOIN tiu_member t ON c.id = t.community_id AND t.status = '1'
            WHERE c.latitude IS NOT NULL AND c.longitude IS NOT NULL
            AND c.campus_id = ?
            GROUP BY c.id, c.community_name, c.latitude, c.longitude
            ORDER BY member_count DESC, c.community_name ASC
        ", [$campusId]);

        return response()->json([
            'success' => true,
            'data' => $communities
        ]);
    }

    /**
     * Get members of a specific community
     */
    public function getCommunityMembers(Request $request)
    {
        $request->validate(['community_id' => 'required|integer']);

        $members = DB::select("
            SELECT CONCAT_WS(' ', first_name, last_name) AS member_name, 
                   residential_address, phone_number, email
            FROM tiu_member 
            WHERE community_id = ? AND status = '1'
            ORDER BY first_name ASC, last_name ASC
        ", [$request->community_id]);

        return response()->json([
            'success' => true,
            'data' => $members
        ]);
    }

}
