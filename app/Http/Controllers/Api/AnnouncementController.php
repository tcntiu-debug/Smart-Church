<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    /**
     * Get all announcements for the user's campus
     */
    public function index(Request $request)
    {
        $userId = $request->user_id;
        $user = DB::selectOne("SELECT campus_id FROM tiu_member WHERE tiu_member_id = ?", [$userId]);
        $campusId = $user->campus_id ?? 0;

        $announcements = DB::table('announcements')
            ->where('campus_id', $campusId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $announcements
        ]);
    }

    /**
     * Create a new announcement
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
        ]);

        $userId = $request->user_id;
        $user = DB::selectOne("SELECT campus_id FROM tiu_member WHERE tiu_member_id = ?", [$userId]);
        $campusId = $user->campus_id ?? 0;

        $id = DB::table('announcements')->insertGetId([
            'title' => $request->title,
            'content' => $request->content ?? '',
            'created_at' => now(),
            'campus_id' => $campusId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Announcement posted successfully!',
            'data' => ['id' => $id]
        ]);
    }

    /**
     * Delete an announcement
     */
    public function destroy($id)
    {
        DB::table('announcements')->where('id', $id)->delete();
        return response()->json([
            'success' => true,
            'message' => 'Announcement deleted!'
        ]);
    }
}
