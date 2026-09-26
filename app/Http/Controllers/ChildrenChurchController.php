<?php

namespace App\Http\Controllers;

use App\Models\ChildrenChurch;
use App\Models\ChildrenChurchAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChildrenChurchController extends Controller
{
    /**
     * Display the children check-in page.
     */
    public function index()
    {
        return view('children-church.index');
    }

    /**
     * Search for children by name/surname (AJAX).
     * Real-time search filtered by admin's campus.
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        $user = Auth::user();
        $campusId = $user->campus_id ?? 0;

        if (empty(trim($query))) {
            return response()->json([
                'success' => true,
                'data' => [],
                'count' => 0
            ]);
        }

        // Search by child name OR parent name within same campus
        $children = ChildrenChurch::where('campus_id', $campusId)
            ->where(function ($q) use ($query) {
                $q->where('child_name', 'LIKE', "%{$query}%")
                  ->orWhere('parent_name', 'LIKE', "%{$query}%");
            })
            ->orderBy('child_name', 'asc')
            ->limit(20)
            ->get();

        // Get today's date
        $today = now()->format('Y-m-d');

        // Check if already marked today for each child
        $attendanceToday = ChildrenChurchAttendance::whereIn('child_id', $children->pluck('child_id'))
            ->where('attendance_date', $today)
            ->pluck('child_id')
            ->toArray();

        $results = [];
        foreach ($children as $child) {
            $results[] = [
                'child_id'      => $child->child_id,
                'child_name'    => $child->child_name,
                'parent_name'   => $child->parent_name ?? '',
                'dob'           => $child->dob ?? '',
                'parent_phone'  => $child->parent_phone ?? '',
                'already_marked'=> in_array($child->child_id, $attendanceToday),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $results,
            'count'   => count($results),
        ]);
    }

    /**
     * Mark attendance for a child.
     */
    public function markAttendance(Request $request)
    {
        $request->validate([
            'child_id' => 'required|exists:children_church,child_id',
        ]);

        $user = Auth::user();
        $userId = $user->tiu_member_id ?? $user->id ?? 0;
        $today = now()->format('Y-m-d');

        try {
            // Check if already marked today
            $exists = ChildrenChurchAttendance::where('child_id', $request->child_id)
                ->where('attendance_date', $today)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'This child has already been marked for today.'
                ]);
            }

            // Mark attendance
            ChildrenChurchAttendance::create([
                'child_id'         => $request->child_id,
                'attendance_date'  => $today,
                'marked_by'        => $userId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attendance marked successfully! Tag has been issued.'
            ]);
        } catch (\Exception $e) {
            Log::error('Children Church Attendance Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error marking attendance: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Store a new child record.
     */
    public function store(Request $request)
    {
        $request->validate([
            'child_name'  => 'required|string|max:255',
            'parent_name' => 'nullable|string|max:255',
            'dob'         => 'nullable|string|max:100',
            'parent_phone'=> 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $campusId = $user->campus_id ?? 0;

        try {
            $child = ChildrenChurch::create([
                'child_name'   => $request->child_name,
                'parent_name'  => $request->parent_name ?? '',
                'dob'          => $request->dob ?? '',
                'parent_phone' => $request->parent_phone ?? '',
                'campus_id'    => $campusId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Child registered successfully!',
                'data'    => [
                    'child_id'      => $child->child_id,
                    'child_name'    => $child->child_name,
                    'parent_name'   => $child->parent_name,
                    'dob'           => $child->dob,
                    'parent_phone'  => $child->parent_phone,
                    'already_marked'=> false,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Children Church Store Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error registering child: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Show the children records management page.
     * Uses DataTables for client-side search/sort/pagination (same as mview).
     */
    public function manage(Request $request)
    {
        $user = Auth::user();
        $campusId = $user->campus_id ?? 0;

        $children = ChildrenChurch::where('campus_id', $campusId)
            ->orderBy('child_name', 'asc')
            ->get();

        return view('children-church.manage', compact('children'));
    }

    /**
     * Update a child's record.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'child_name'   => 'required|string|max:255',
            'parent_name'  => 'nullable|string|max:255',
            'dob'          => 'nullable|string|max:100',
            'parent_phone' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $campusId = $user->campus_id ?? 0;

        $child = ChildrenChurch::where('campus_id', $campusId)
            ->findOrFail($id);

        try {
            $child->update([
                'child_name'   => $request->child_name,
                'parent_name'  => $request->parent_name ?? '',
                'dob'          => $request->dob ?? '',
                'parent_phone' => $request->parent_phone ?? '',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Child record updated successfully!'
            ]);
        } catch (\Exception $e) {
            Log::error('Children Church Update Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating child record: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get today's attendance report (for admin to view who has checked in).
     */
    public function todayAttendance(Request $request)
    {
        $user = Auth::user();
        $campusId = $user->campus_id ?? 0;
        $today = now()->format('Y-m-d');

        $attendances = ChildrenChurchAttendance::whereHas('child', function ($q) use ($campusId) {
                $q->where('campus_id', $campusId);
            })
            ->where('attendance_date', $today)
            ->with('child')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $attendances,
            'count'   => $attendances->count(),
        ]);
    }
}
