<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransportController extends Controller
{
    /**
     * Get active bus routes for user's campus
     */
    public function getRoutes(Request $request)
    {
        $userId = $request->user_id;
        $user = DB::selectOne("SELECT campus_id FROM tiu_member WHERE tiu_member_id = ?", [$userId]);
        $campusId = $user->campus_id ?? 0;

        $routes = DB::table('transport_routes')
            ->where('status', 'active')
            ->where('campus_id', $campusId)
            ->orderBy('route_name', 'asc')
            ->get();

        return response()->json(['success' => true, 'data' => $routes]);
    }

    /**
     * Get stops for a specific route
     */
    public function getStops(Request $request)
    {
        $request->validate(['route_id' => 'required|integer']);

        $stops = DB::table('transport_stops')
            ->where('route_id', $request->route_id)
            ->orderBy('stop_order', 'asc')
            ->get(['stop_id', 'stop_name', 'take_off_time', 'latitude', 'longitude']);

        $routeDetails = DB::table('transport_routes')
            ->where('route_id', $request->route_id)
            ->select('route_id', 'route_name', 'driver_name', 'driver_contact', 'team_lead_name', 'team_lead_contact')
            ->first();

        return response()->json([
            'success' => true,
            'data' => ['stops' => $stops, 'route_details' => $routeDetails]
        ]);
    }

    /**
     * Get the current user's bus registration
     */
    public function myRegistration(Request $request)
    {
        $userId = $request->user_id;

        $registration = DB::table('tiu_member as m')
            ->join('transport_stops as s', 'm.bus_stop_id', '=', 's.stop_id')
            ->join('transport_routes as r', 's.route_id', '=', 'r.route_id')
            ->where('m.tiu_member_id', $userId)
            ->select(
                's.stop_id',
                's.stop_name',
                's.take_off_time',
                'r.route_id',
                'r.route_name',
                'r.driver_name',
                'r.driver_contact',
                'r.team_lead_name',
                'r.team_lead_contact'
            )
            ->first();

        return response()->json(['success' => true, 'data' => $registration]);
    }

    /**
     * Save bus stop registration for current user
     */
    public function saveRegistration(Request $request)
    {
        $request->validate(['bus_stop_id' => 'required|integer']);

        $userId = $request->user_id;

        DB::table('tiu_member')
            ->where('tiu_member_id', $userId)
            ->update(['bus_stop_id' => $request->bus_stop_id]);

        return response()->json(['success' => true, 'message' => 'Registration successful!']);
    }

    /**
     * Get members for attendance marking (with filters)
     */
    public function getAttendanceMembers(Request $request)
    {
        $today = date('Y-m-d');
        $routeFilter = $request->route_id ?? 0;
        $searchQuery = $request->search ?? '';

        $query = DB::table('tiu_member AS m')
            ->join('transport_stops AS s', 'm.bus_stop_id', '=', 's.stop_id')
            ->join('transport_routes AS r', 's.route_id', '=', 'r.route_id')
            ->leftJoin('bus_attendance AS att', function($join) use ($today) {
                $join->on('m.tiu_member_id', '=', 'att.tiu_member_id')
                     ->where('att.attendance_date', '=', $today);
            })
            ->where('r.status', 'active')
            ->whereNotNull('m.bus_stop_id')
            ->select(
                'm.tiu_member_id',
                'm.first_name',
                'm.last_name',
                'm.phone_number',
                'm.bus_stop_id',
                'r.route_name',
                's.stop_name',
                'att.attendance_id AS today_record',
                'att.route_id AS att_route_id'
            );

        if ($routeFilter > 0) {
            $query->where('r.route_id', $routeFilter);
        }
        if (!empty($searchQuery)) {
            $searchSafe = '%' . $searchQuery . '%';
            $query->where(function($q) use ($searchSafe) {
                $q->where('m.first_name', 'like', $searchSafe)
                  ->orWhere('m.last_name', 'like', $searchSafe)
                  ->orWhere('m.phone_number', 'like', $searchSafe);
            });
        }

        $members = $query->orderBy('r.route_name')->orderBy('m.last_name')->get();

        $routes = DB::table('transport_routes')
            ->where('status', 'active')
            ->orderBy('route_name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $members,
            'routes' => $routes
        ]);
    }

    /**
     * Mark bus attendance for a member
     */
    public function markAttendance(Request $request)
    {
        $request->validate(['tiu_member_id' => 'required|integer']);

        $memberId = $request->tiu_member_id;
        $today = date('Y-m-d');

        $member = DB::table('tiu_member')
            ->join('transport_stops', 'tiu_member.bus_stop_id', '=', 'transport_stops.stop_id')
            ->where('tiu_member.tiu_member_id', $memberId)
            ->select(
                'tiu_member.bus_stop_id',
                'transport_stops.route_id',
                'tiu_member.first_name',
                'tiu_member.last_name',
                'tiu_member.church_type_id'
            )
            ->first();

        if (!$member) {
            return response()->json(['success' => false, 'message' => 'Member not found'], 404);
        }

        $existingBus = DB::table('bus_attendance')
            ->where('tiu_member_id', $memberId)
            ->where('attendance_date', $today)
            ->first();

        if ($existingBus) {
            return response()->json(['success' => false, 'message' => 'Already marked present today']);
        }

        $attendanceId = DB::table('bus_attendance')->insertGetId([
            'tiu_member_id' => $memberId,
            'bus_stop_id' => $member->bus_stop_id,
            'route_id' => $member->route_id,
            'attendance_date' => $today,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Also auto-create church attendance
        $existingChurch = DB::table('church_attendance')
            ->where('member_id', $memberId)
            ->where('member_type', 'tiu_member')
            ->where('attendance_date', $today)
            ->exists();

        if (!$existingChurch) {
            $fullName = trim($member->first_name . ' ' . $member->last_name);
            DB::table('church_attendance')->insert([
                'member_id' => $memberId,
                'member_type' => 'tiu_member',
                'full_name' => $fullName,
                'church_type_id' => $member->church_type_id,
                'attendance_date' => $today,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendance marked successfully',
            'attendance_id' => $attendanceId
        ]);
    }

    /**
     * View attendance analytics
     */
    public function viewAttendance(Request $request)
    {
        $startDate = $request->start_date ?? date('Y-m-d');
        $endDate = $request->end_date ?? date('Y-m-d');

        $routeChartData = DB::table('transport_routes AS r')
            ->leftJoin('bus_attendance AS a', function($join) use ($startDate, $endDate) {
                $join->on('r.route_id', '=', 'a.route_id')
                     ->whereBetween('a.attendance_date', [$startDate, $endDate]);
            })
            ->select('r.route_name', DB::raw('COUNT(a.attendance_id) as total'))
            ->groupBy('r.route_id', 'r.route_name')
            ->having('total', '>', 0)
            ->get();

        $stopChartData = DB::table('transport_stops AS s')
            ->join('bus_attendance AS a', 's.stop_id', '=', 'a.bus_stop_id')
            ->whereBetween('a.attendance_date', [$startDate, $endDate])
            ->select('s.stop_name', DB::raw('COUNT(a.attendance_id) as total'))
            ->groupBy('s.stop_id', 's.stop_name')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get();

        $tableData = DB::table('bus_attendance AS a')
            ->join('transport_routes AS r', 'a.route_id', '=', 'r.route_id')
            ->whereBetween('a.attendance_date', [$startDate, $endDate])
            ->select('a.attendance_date', 'r.route_name', DB::raw('COUNT(a.attendance_id) as total'))
            ->groupBy('a.attendance_date', 'r.route_name')
            ->orderBy('a.attendance_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'route_chart' => ['labels' => $routeChartData->pluck('route_name'), 'values' => $routeChartData->pluck('total')],
                'stop_chart' => ['labels' => $stopChartData->pluck('stop_name'), 'values' => $stopChartData->pluck('total')],
                'table_data' => $tableData
            ]
        ]);
    }
}
