<?php

namespace App\Http\Controllers;

use App\Models\ChurchAttendance;
use App\Models\TiuMember;
use App\Models\FirstTimer;
use App\Models\ChurchType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    /**
     * Display clock-in page (public)
     */
    public function clockIn()
    {
        return view('attendance.clock-in');
    }

    /**
     * Search for a member/first-timer by phone number (AJAX)
     */
    public function search(Request $request)
    {
        $phone = preg_replace('/[^0-9]/', '', $request->phone);

        $results = [];

        // Search in tiu_member
        $members = DB::table('tiu_member')
            ->where('phone_number', 'LIKE', "%{$phone}%")
            ->where('status', '!=', 3)
            ->select('tiu_member_id as id', 'first_name', 'last_name', 'phone_number', 'church_type_id',
                DB::raw("'tiu_member' as member_type"), DB::raw("'' as status"))
            ->limit(5)
            ->get();

        foreach ($members as $m) {
            $results[] = $m;
        }

        // Search in first_timer
        $firstTimers = DB::table('first_timer')
            ->where('phone_number', 'LIKE', "%{$phone}%")
            ->where('status', '!=', 'Integrated')
            ->select('first_timer_id as id', 'first_name', 'last_name', 'phone_number', 'church_type_id',
                DB::raw("'first_timer' as member_type"), 'status')
            ->limit(5)
            ->get();

        foreach ($firstTimers as $ft) {
            $results[] = $ft;
        }

        return response()->json([
            'success' => true,
            'data' => $results,
            'count' => count($results)
        ]);
    }

    /**
     * Submit attendance
     */
    public function submit(Request $request)
    {
        try {
            DB::beginTransaction();

            $attendanceData = $request->attendance;
            if (!is_array($attendanceData) || empty($attendanceData)) {
                return redirect()->back()->with('error', 'No attendees selected');
            }

            foreach ($attendanceData as $item) {
                // Format: id|type|name|church_id
                $parts = explode('|', $item);
                $id = $parts[0];
                $type = $parts[1];
                $name = $parts[2] ?? '';
                $churchId = $parts[3] ?? 0;
                $today = now()->format('Y-m-d');
                $now = now();

                // Insert into church_attendance
                ChurchAttendance::create([
                    'member_id' => $id,
                    'member_type' => $type,
                    'full_name' => $name,
                    'church_type_id' => $churchId,
                    'attendance_date' => $today,
                ]);

                // If first_timer, set to Integrated
                if ($type === 'first_timer') {
                    $nameParts = explode(' ', $name);
                    $firstName = $nameParts[0] ?? '';
                    $lastName = $nameParts[1] ?? '';

                    DB::table('first_timer')
                        ->where('first_timer_id', $id)
                        ->update([
                            'status' => 'Integrated',
                            'status_change_date' => $now
                        ]);
                }
            }

            DB::commit();

            return redirect('/attendance')->with('success', 'Clocked In Successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Attendance submission error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error submitting attendance');
        }
    }

    /**
     * Display attendance report
     */
    public function report(Request $request)
    {
        $selectedDate = $request->get('report_date', now()->startOfWeek()->format('Y-m-d'));
        $churchTypeId = $request->get('church_type', 0);
        $memberTypeFilter = $request->get('member_type', 'both');

        // Validate date
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = now()->format('Y-m-d');
        }

        $churchTypes = ChurchType::orderBy('church_type_name', 'asc')->get();

        // Missing members
        $missingMembers = [];
        $missingFirstTimers = [];

        if ($memberTypeFilter === 'both' || $memberTypeFilter === 'members') {
            $query = DB::table('tiu_member as m')
                ->leftJoin('church_attendance as a', function ($join) use ($selectedDate) {
                    $join->on('m.tiu_member_id', '=', 'a.member_id')
                        ->where('a.member_type', '=', 'tiu_member')
                        ->whereDate('a.attendance_date', '=', $selectedDate);
                })
                ->whereNull('a.attendance_id');

            if ($churchTypeId > 0) {
                $query->where('m.church_type_id', $churchTypeId);
            }

            $missingMembers = $query->select('m.tiu_member_id', 'm.first_name', 'm.last_name', 'm.phone_number')
                ->orderBy('m.last_name')
                ->get();
        }

        if ($memberTypeFilter === 'both' || $memberTypeFilter === 'first_timers') {
            $query = DB::table('first_timer as f')
                ->leftJoin('church_attendance as a', function ($join) use ($selectedDate) {
                    $join->on('f.first_timer_id', '=', 'a.member_id')
                        ->where('a.member_type', '=', 'first_timer')
                        ->whereDate('a.attendance_date', '=', $selectedDate);
                })
                ->whereNull('a.attendance_id')
                ->where('f.status', '!=', 'Integrated');

            if ($churchTypeId > 0) {
                $query->where('f.church_type_id', $churchTypeId);
            }

            $missingFirstTimers = $query->select('f.first_timer_id', 'f.first_name', 'f.last_name', 'f.phone_number', 'f.status')
                ->orderBy('f.last_name')
                ->get();
        }

        return view('attendance.report', compact(
            'selectedDate', 'churchTypeId', 'memberTypeFilter',
            'churchTypes', 'missingMembers', 'missingFirstTimers'
        ));
    }
}
