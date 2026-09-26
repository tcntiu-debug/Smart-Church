<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChurchAttendanceController extends Controller
{
    /**
     * Mark church attendance for today
     */
    public function markAttendance(Request $request)
    {
        $userId = $request->user_id;
        $user = DB::selectOne("SELECT * FROM tiu_member WHERE tiu_member_id = ?", [$userId]);

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $alreadyMarked = DB::table('church_attendance')
            ->where('member_id', $userId)
            ->whereDate('attendance_date', today())
            ->exists();

        if ($alreadyMarked) {
            return response()->json(['success' => false, 'message' => 'Attendance already registered today']);
        }

        DB::table('church_attendance')->insert([
            'member_id' => $userId,
            'member_type' => 'tiu_member',
            'full_name' => $user->first_name . ' ' . $user->last_name,
            'church_type_id' => $user->church_type_id ?? null,
            'attendance_date' => today(),
            'date_created' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Attendance registered!']);
    }

    /**
     * Check if user already marked attendance today
     */
    public function checkToday(Request $request)
    {
        $userId = $request->user_id;

        $marked = DB::table('church_attendance')
            ->where('member_id', $userId)
            ->whereDate('attendance_date', today())
            ->exists();

        return response()->json([
            'success' => true,
            'data' => ['attended_today' => $marked]
        ]);
    }

    /**
     * View attendance records
     */
    public function viewAttendance(Request $request)
    {
        $userId = $request->user_id;
        $user = DB::selectOne("SELECT campus_id, member_role FROM tiu_member WHERE tiu_member_id = ?", [$userId]);
        $campusId = $user->campus_id ?? 0;
        $isAdmin = in_array($user->member_role ?? '', ['Super User', 'Admin', 'Admins']);

        $startDate = $request->start_date;
        $endDate = $request->end_date;

        if (!$startDate) $startDate = today()->subDays(30)->format('Y-m-d');
        if (!$endDate) $endDate = today()->format('Y-m-d');

        $query = DB::table('church_attendance as ca')
            ->join('tiu_member as tm', 'ca.member_id', '=', 'tm.tiu_member_id')
            ->select(
                'ca.attendance_id',
                'ca.member_id',
                'tm.first_name',
                'tm.last_name',
                'tm.phone_number',
                'ca.attendance_date',
                'ca.church_type_id'
            )
            ->whereBetween('ca.attendance_date', [$startDate, $endDate]);

        if (!$isAdmin) {
            $query->where('ca.member_id', $userId);
        }

        if ($campusId) {
            $query->where('tm.campus_id', $campusId);
        }

        $records = $query->orderBy('ca.attendance_date', 'desc')->limit(100)->get();

        return response()->json([
            'success' => true,
            'data' => $records
        ]);
    }

    /**
     * Search member or first-timer by phone
     */
    public function search(Request $request)
    {
        $request->validate(['phone' => 'required|string']);

        $phone = preg_replace('/[^0-9]/', '', $request->phone);

        $results = [];

        $members = DB::table('tiu_member')
            ->where('phone_number', 'LIKE', "%{$phone}%")
            ->where('status', '!=', 3)
            ->select('tiu_member_id as id', 'first_name', 'last_name', 'phone_number', 'church_type_id',
                DB::raw("'tiu_member' as member_type"))
            ->limit(5)
            ->get();

        foreach ($members as $m) {
            $results[] = $m;
        }

        $firstTimers = DB::table('first_timer')
            ->where('phone_number', 'LIKE', "%{$phone}%")
            ->where('status', '!=', 'Integrated')
            ->select('first_timer_id as id', 'first_name', 'last_name', 'phone_number', 'church_type_id',
                DB::raw("'first_timer' as member_type"))
            ->limit(5)
            ->get();

        foreach ($firstTimers as $ft) {
            $results[] = $ft;
        }

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }
}
