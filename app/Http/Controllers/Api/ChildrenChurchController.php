<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChildrenChurchController extends Controller
{
    /**
     * Search children by name
     */
    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2']);

        $userId = $request->user_id;
        $user = DB::selectOne("SELECT campus_id FROM tiu_member WHERE tiu_member_id = ?", [$userId]);
        $campusId = $user->campus_id ?? 0;
        $query = $request->q;

        $children = DB::table('children_church')
            ->where('campus_id', $campusId)
            ->where(function ($q) use ($query) {
                $q->where('child_name', 'LIKE', "%{$query}%")
                  ->orWhere('parent_name', 'LIKE', "%{$query}%");
            })
            ->orderBy('child_name', 'asc')
            ->limit(20)
            ->get();

        $today = date('Y-m-d');
        foreach ($children as $child) {
            $child->checked_in_today = DB::table('children_church_attendance')
                ->where('child_id', $child->id)
                ->whereDate('attendance_date', $today)
                ->exists();
        }

        return response()->json([
            'success' => true,
            'data' => $children,
            'count' => count($children)
        ]);
    }

    /**
     * Check in a child
     */
    public function checkIn(Request $request)
    {
        $request->validate(['child_id' => 'required|integer']);

        $userId = $request->user_id;
        $today = date('Y-m-d');

        $alreadyCheckedIn = DB::table('children_church_attendance')
            ->where('child_id', $request->child_id)
            ->whereDate('attendance_date', $today)
            ->exists();

        if ($alreadyCheckedIn) {
            return response()->json(['success' => false, 'message' => 'Already checked in today']);
        }

        DB::table('children_church_attendance')->insert([
            'child_id' => $request->child_id,
            'checked_in_by' => $userId,
            'attendance_date' => $today,
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Child checked in successfully!']);
    }
}
