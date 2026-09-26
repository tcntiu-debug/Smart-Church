<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class MapController extends Controller
{
    /**
     * Show the full community map with list, markers, demographics, and member loading.
     * Only shows communities for the user's campus.
     * Replicates the functionality of TIU's map.php
     */
    public function communityMap(Request $request)
    {
        $user = Auth::user();
        $campusId = $user->campus_id;

        // Fetch communities filtered by user's campus
        $communities_data = DB::select("
            SELECT c.id AS community_id, c.community_name, c.latitude, c.longitude, 
                   COUNT(t.tiu_member_id) AS member_count,
                   SUM(CASE WHEN t.gender = 'Male' THEN 1 ELSE 0 END) AS male_count,
                   SUM(CASE WHEN t.gender = 'Female' THEN 1 ELSE 0 END) AS female_count,
                   EXISTS (SELECT 1 FROM department d WHERE d.community_id = c.id) AS has_department
            FROM communities c 
            LEFT JOIN tiu_member t ON c.id = t.community_id AND t.status = '1'
            WHERE c.latitude IS NOT NULL AND c.longitude IS NOT NULL
            AND c.campus_id = ?
            GROUP BY c.id, c.community_name, c.latitude, c.longitude
            ORDER BY member_count DESC, c.community_name ASC
        ", [$campusId]);

        $communities_json = json_encode($communities_data);

        return view('maps.community', compact('communities_json'));
    }

    /**
     * AJAX handler to fetch members of a specific community
     */
    public function getCommunityMembers(Request $request)
    {
        if (!$request->ajax()) {
            abort(403);
        }

        $community_id = (int) $request->input('community_id');

        if (!$community_id) {
            return response()->json([]);
        }

        $members = DB::select("
            SELECT CONCAT_WS(' ', first_name, last_name) AS member_name, 
                   residential_address, phone_number, email
            FROM tiu_member 
            WHERE community_id = ? AND status = '1'
            ORDER BY first_name ASC, last_name ASC
        ", [$community_id]);

        return response()->json($members);
    }
}
