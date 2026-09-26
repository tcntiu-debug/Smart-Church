<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display bus route registration page
     */
    public function register()
    {
        $user = Auth::user();
        $memberId = $user->tiu_member_id ?? $user->id;
        $campusId = $user->campus_id ?? 0;

        // Get active routes for the user's campus only
        $routes = DB::table('transport_routes')
            ->where('status', 'active')
            ->where('campus_id', $campusId)
            ->orderBy('route_name', 'asc')
            ->get();

        // Get current registration
        $currentRegistration = DB::table('tiu_member as m')
            ->join('transport_stops as s', 'm.bus_stop_id', '=', 's.stop_id')
            ->join('transport_routes as r', 's.route_id', '=', 'r.route_id')
            ->where('m.tiu_member_id', $memberId)
            ->select(
                's.stop_name',
                's.take_off_time',
                'r.route_name',
                'r.driver_name',
                'r.driver_contact',
                'r.team_lead_name',
                'r.team_lead_contact'
            )
            ->first();

        return view('transport.register', compact('routes', 'currentRegistration'));
    }

    /**
     * Get stops for a route (AJAX)
     */
    public function getStops(Request $request)
    {
        $routeId = $request->route_id;
        
        if (!$routeId) {
            return response()->json(['stops' => [], 'route_details' => null]);
        }
        
        $stops = DB::table('transport_stops')
            ->where('route_id', $routeId)
            ->orderBy('stop_order', 'asc')
            ->get(['stop_id', 'stop_name', 'take_off_time']);
        
        $routeDetails = DB::table('transport_routes')
            ->where('route_id', $routeId)
            ->select('driver_name', 'driver_contact', 'team_lead_name', 'team_lead_contact')
            ->first();
        
        return response()->json([
            'stops' => $stops,
            'route_details' => $routeDetails
        ]);
    }

    /**
     * Save member's bus stop registration
     */
    public function saveRegistration(Request $request)
    {
        $busStopId = $request->bus_stop_id;
        $memberId = Auth::user()->tiu_member_id ?? Auth::user()->id;
        
        if (!$busStopId || !$memberId) {
            return response()->json(['success' => false, 'message' => 'Invalid selection.']);
        }
        
        try {
            DB::table('tiu_member')
                ->where('tiu_member_id', $memberId)
                ->update(['bus_stop_id' => $busStopId]);
            
            return response()->json(['success' => true, 'message' => 'Registration successful! Your transport details have been updated.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    /**
     * Display bus route admin page
     */
    public function adminRoutes()
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        
        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect('/aoverview')->with('error', 'Access Denied');
        }

        $routes = DB::table('transport_routes')
            ->orderBy('route_name', 'asc')
            ->get();

        return view('transport.admin-routes', compact('routes'));
    }

    /**
     * Save a route (create or update) with its stops
     */
    public function saveRoute(Request $request)
    {
        $routeId = $request->input('route_id');
        $isUpdate = !empty($routeId);

        try {
            DB::beginTransaction();

            if ($isUpdate) {
                DB::table('transport_routes')
                    ->where('route_id', $routeId)
                    ->update([
                        'route_name' => $request->route_name,
                        'driver_name' => $request->driver_name ?? '',
                        'driver_contact' => $request->driver_contact ?? '',
                        'team_lead_name' => $request->team_lead_name ?? '',
                        'team_lead_contact' => $request->team_lead_contact ?? '',
                        'status' => $request->status ?? 'active',
                    ]);
            } else {
                $routeId = DB::table('transport_routes')->insertGetId([
                    'route_name' => $request->route_name,
                    'driver_name' => $request->driver_name ?? '',
                    'driver_contact' => $request->driver_contact ?? '',
                    'team_lead_name' => $request->team_lead_name ?? '',
                    'team_lead_contact' => $request->team_lead_contact ?? '',
                    'status' => $request->status ?? 'active',
                ]);
            }

            // Synchronize bus stops
            $submittedStopIds = [];
            $stopNames = $request->input('stop_name', []);
            $stopIds = $request->input('stop_id', []);
            $takeOffTimes = $request->input('take_off_time', []);
            $latitudes = $request->input('latitude', []);
            $longitudes = $request->input('longitude', []);

            foreach ($stopNames as $index => $name) {
                if (empty(trim($name))) continue;
                $stopOrder = $index + 1;
                $existingStopId = !empty($stopIds[$index]) ? (int)$stopIds[$index] : null;
                $takeOffTime = $takeOffTimes[$index] ?? null;
                $latitude = !empty($latitudes[$index]) ? (float)$latitudes[$index] : null;
                $longitude = !empty($longitudes[$index]) ? (float)$longitudes[$index] : null;

                if ($existingStopId) {
                    DB::table('transport_stops')
                        ->where('stop_id', $existingStopId)
                        ->where('route_id', $routeId)
                        ->update([
                            'stop_name' => $name,
                            'take_off_time' => $takeOffTime,
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'stop_order' => $stopOrder,
                        ]);
                    $submittedStopIds[] = $existingStopId;
                } else {
                    $newId = DB::table('transport_stops')->insertGetId([
                        'route_id' => $routeId,
                        'stop_name' => $name,
                        'take_off_time' => $takeOffTime,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'stop_order' => $stopOrder,
                    ]);
                    $submittedStopIds[] = $newId;
                }
            }

            // Remove deleted stops
            if ($isUpdate && !empty($submittedStopIds)) {
                DB::table('transport_stops')
                    ->where('route_id', $routeId)
                    ->whereNotIn('stop_id', $submittedStopIds)
                    ->delete();
            } elseif ($isUpdate && empty($submittedStopIds)) {
                DB::table('transport_stops')
                    ->where('route_id', $routeId)
                    ->delete();
            }

            DB::commit();

            $message = 'Route saved successfully!';
            if ($isUpdate && empty($submittedStopIds)) {
                $message = 'Route details saved and all associated bus stops have been removed.';
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $message]);
            }

            return redirect('/bus-route-admin')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $errorMsg = 'Error: ' . $e->getMessage();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $errorMsg], 500);
            }
            return redirect()->back()->with('error', $errorMsg);
        }
    }

    /**
     * Get a single route with its stops for editing (AJAX)
     */
    public function getRoute($id)
    {
        $route = DB::table('transport_routes')->where('route_id', $id)->first();
        if (!$route) {
            return response()->json(['error' => 'Route not found.']);
        }

        $stops = DB::table('transport_stops')
            ->where('route_id', $id)
            ->orderBy('stop_order', 'asc')
            ->select('stop_id', 'stop_name', 'take_off_time', 'latitude', 'longitude')
            ->get();

        $routeData = (array) $route;
        $routeData['stops'] = (array) $stops;

        return response()->json(['data' => $routeData, 'stops' => $stops]);
    }

    /**
     * Get route details with stops and registered members for the viewer (AJAX)
     */
    public function getRouteDetails(Request $request)
    {
        $routeId = $request->input('route_id');

        if (!$routeId) {
            return response()->json(['stops' => [], 'members_html' => '']);
        }

        $stops = DB::table('transport_stops')
            ->where('route_id', $routeId)
            ->orderBy('stop_order', 'asc')
            ->select('stop_name', 'take_off_time', 'latitude', 'longitude')
            ->get();

        $members = DB::table('tiu_member AS m')
            ->join('transport_stops AS s', 'm.bus_stop_id', '=', 's.stop_id')
            ->where('s.route_id', $routeId)
            ->orderBy('s.stop_order')
            ->orderBy('m.last_name')
            ->select('m.first_name', 'm.last_name', 'm.phone_number', 's.stop_name')
            ->get();

        if ($members->count() > 0) {
            $html = '<div class="table-responsive">
                        <table class="table table-striped table-bordered ms-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Full Name</th>
                                    <th>Contact Phone</th>
                                    <th>Bus Stop</th>
                                </tr>
                            </thead>
                            <tbody>';
            $count = 1;
            foreach ($members as $member) {
                $fullName = e($member->first_name . ' ' . $member->last_name);
                $phone = e($member->phone_number ?? 'N/A');
                $stopName = e($member->stop_name ?? 'N/A');
                $html .= "<tr><td>{$count}</td><td>{$fullName}</td><td>{$phone}</td><td><strong>{$stopName}</strong></td></tr>";
                $count++;
            }
            $html .= '</tbody></table></div>';
        } else {
            $html = '<p class="text-center">No members are currently registered for this route.</p>';
        }

        return response()->json([
            'stops' => $stops,
            'members_html' => $html,
        ]);
    }

    /**
     * Display daily bus attendance marking page (from TIU transport_mark_attendance.php)
     */
    public function markAttendance()
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        $isAdmin = in_array($memberRole, ['Super User', 'Admin']);

        $routeFilter = request()->get('route_id', 0);
        $searchQuery = request()->get('search', '');
        $today = date('Y-m-d');

        // Get active routes for filter dropdown
        $routes = DB::table('transport_routes')
            ->where('status', 'active')
            ->orderBy('route_name', 'asc')
            ->get();

        // Get members with bus routes assigned + today's attendance
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

        $members = $query->orderBy('r.route_name', 'asc')
                         ->orderBy('m.last_name', 'asc')
                         ->get();

        return view('transport.mark-attendance', compact('routes', 'members', 'routeFilter', 'searchQuery', 'today', 'isAdmin'));
    }

    /**
     * AJAX: Mark a member present for today's bus attendance
     * Also auto-creates a church attendance record for the same day.
     */
    public function markAttendanceAjax(Request $request)
    {
        $memberId = $request->input('tiu_member_id');
        $today = date('Y-m-d');

        try {
            // Get the member's route info and personal details
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
                return response()->json(['success' => false, 'message' => 'Member not found or no bus route assigned.']);
            }

            // Check if bus attendance already marked
            $existingBus = DB::table('bus_attendance')
                ->where('tiu_member_id', $memberId)
                ->where('attendance_date', $today)
                ->first();

            if ($existingBus) {
                return response()->json(['success' => false, 'message' => 'Already marked present today.']);
            }

            // Insert bus attendance record
            $attendanceId = DB::table('bus_attendance')->insertGetId([
                'tiu_member_id' => $memberId,
                'bus_stop_id' => $member->bus_stop_id,
                'route_id' => $member->route_id,
                'attendance_date' => $today,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Auto-create church attendance record for this member on the same day
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
                'message' => 'Attendance marked successfully.',
                'attendance_id' => $attendanceId,
            ]);
        } catch (\Exception $e) {
            Log::error('Bus Mark Attendance Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
    /**
     * AJAX: Revert (unmark) today's attendance - also removes associated church attendance record
     */
    public function revertAttendance(Request $request)
    {
        $attendanceId = $request->input('attendance_id');

        try {
            // Get the bus attendance record before deleting it
            $busRecord = DB::table('bus_attendance')
                ->where('attendance_id', $attendanceId)
                ->first();

            if (!$busRecord) {
                return response()->json(['success' => false, 'message' => 'Attendance record not found.']);
            }

            $memberId = $busRecord->tiu_member_id;
            $attendanceDate = $busRecord->attendance_date;

            // Delete the bus attendance record
            DB::table('bus_attendance')
                ->where('attendance_id', $attendanceId)
                ->delete();

            // Also remove the associated church attendance record
            DB::table('church_attendance')
                ->where('member_id', $memberId)
                ->where('member_type', 'tiu_member')
                ->where('attendance_date', $attendanceDate)
                ->delete();

            return response()->json(['success' => true, 'message' => 'Attendance reverted.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX: Delete member's bus registration and all attendance records
     */
    public function deleteMemberRegistration(Request $request)
    {
        $memberId = $request->input('tiu_member_id');

        try {
            DB::beginTransaction();

            // Delete attendance records
            DB::table('bus_attendance')
                ->where('tiu_member_id', $memberId)
                ->delete();

            // Clear bus_stop_id
            DB::table('tiu_member')
                ->where('tiu_member_id', $memberId)
                ->update(['bus_stop_id' => null]);

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Member registration deleted successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Display transport attendance view with charts (from TIU transport_view_attendance.php)
     */
    public function viewAttendance()
    {
        $user = Auth::user();

        $startDate = request()->get('start_date', date('Y-m-d'));
        $endDate = request()->get('end_date', date('Y-m-d'));

        // 1. Route Distribution Chart Data
        $routeChartData = DB::table('transport_routes AS r')
            ->leftJoin('bus_attendance AS a', function($join) use ($startDate, $endDate) {
                $join->on('r.route_id', '=', 'a.route_id')
                     ->whereBetween('a.attendance_date', [$startDate, $endDate]);
            })
            ->select('r.route_name', DB::raw('COUNT(a.attendance_id) as total'))
            ->groupBy('r.route_id', 'r.route_name')
            ->having('total', '>', 0)
            ->get();

        $routeLabels = $routeChartData->pluck('route_name')->toArray();
        $routeCounts = $routeChartData->pluck('total')->toArray();

        // 2. Top Bus Stops Chart Data
        $stopChartData = DB::table('transport_stops AS s')
            ->join('bus_attendance AS a', 's.stop_id', '=', 'a.bus_stop_id')
            ->whereBetween('a.attendance_date', [$startDate, $endDate])
            ->select('s.stop_name', DB::raw('COUNT(a.attendance_id) as total'))
            ->groupBy('s.stop_id', 's.stop_name')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get();

        $stopLabels = $stopChartData->pluck('stop_name')->toArray();
        $stopCounts = $stopChartData->pluck('total')->toArray();

        // 3. Table Data - Daily attendance by route
        $tableData = DB::table('bus_attendance AS a')
            ->join('transport_routes AS r', 'a.route_id', '=', 'r.route_id')
            ->whereBetween('a.attendance_date', [$startDate, $endDate])
            ->select('a.attendance_date', 'r.route_name', DB::raw('COUNT(a.attendance_id) as total'))
            ->groupBy('a.attendance_date', 'r.route_name')
            ->orderBy('a.attendance_date', 'desc')
            ->orderBy('total', 'desc')
            ->get();

        // 4. All routes for the filter dropdown
        $routes = DB::table('transport_routes')
            ->orderBy('route_name', 'asc')
            ->get();

        return view('transport.view-attendance', compact(
            'routeLabels', 'routeCounts',
            'stopLabels', 'stopCounts',
            'tableData', 'startDate', 'endDate',
            'routes'
        ));
    }

    /**
     * Display admin transport registration page with lookup
     */
    public function adminRegister()
    {
        $user = Auth::user();
        $campusId = $user->campus_id ?? 0;

        $routes = DB::table('transport_routes')
            ->where('status', 'active')
            ->where('campus_id', $campusId)
            ->orderBy('route_name', 'asc')
            ->get();

        return view('transport.admin-register', compact('routes'));
    }



    /**
     * AJAX: Lookup member by email or phone for transport registration (same campus only)
     */
    public function lookupMember(Request $request)
    {
        $email = $request->input('email');
        $phone = $request->input('phone_number');
        $user = Auth::user();
        $campusId = $user->campus_id ?? 0;

        if (empty($email) && empty($phone)) {
            return response()->json(['success' => false, 'message' => 'Email or Phone is required.']);
        }

        try {
            $userData = null;

            // Check tiu_member first - restrict to admin's own campus
            $query = DB::table('tiu_member')->where('campus_id', $campusId);
            if (!empty($email)) {
                $userData = (clone $query)->where('email', $email)->first();
            } elseif (!empty($phone)) {
                $userData = (clone $query)->where('phone_number', $phone)->first();
            }

            if ($userData) {
                $userData->source = 'tiu_member';

                // Check if member has an existing bus route registration
                $userData->current_route = null;
                $userData->current_stop = null;
                if (!empty($userData->bus_stop_id)) {
                    $routeInfo = DB::table('transport_stops AS s')
                        ->join('transport_routes AS r', 's.route_id', '=', 'r.route_id')
                        ->where('s.stop_id', $userData->bus_stop_id)
                        ->select('r.route_name', 's.stop_name', 's.take_off_time')
                        ->first();
                    if ($routeInfo) {
                        $userData->current_route = $routeInfo->route_name;
                        $userData->current_stop = $routeInfo->stop_name . ' (' . $routeInfo->take_off_time . ')';
                    }
                }

                return response()->json(['success' => true, 'data' => $userData]);
            }

            // Check first_timer
            $ftData = null;
            if (!empty($email)) {
                $ftData = DB::table('first_timer')->where('email', $email)->orderBy('first_timer_id', 'desc')->first();
            } elseif (!empty($phone)) {
                $ftData = DB::table('first_timer')->where('phone_number', $phone)->orderBy('first_timer_id', 'desc')->first();
            }

            if ($ftData) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'source' => 'first_timer',
                        'first_name' => $ftData->first_name ?? '',
                        'last_name' => $ftData->last_name ?? '',
                        'gender' => $ftData->gender ?? '',
                        'residential_address' => $ftData->address ?? '',
                        'email' => $ftData->email ?? $email,
                        'phone_number' => $ftData->phone_number ?? $phone,
                        'first_timer_id' => $ftData->first_timer_id ?? null,
                        'picture_part' => $ftData->picture_part ?? null,
                    ]
                ]);
            }

            // New user
            return response()->json([
                'success' => true,
                'data' => [
                    'source' => 'new_user',
                    'email' => $email,
                    'phone_number' => $phone,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    /**
     * AJAX: Submit admin transport registration
     */
    public function adminRegisterSubmit(Request $request)
    {
        $email = $request->input('email');
        $phone = $request->input('phone_number');
        $source = $request->input('source', 'new_user');
        $routeId = $request->input('route_id');
        $busStopId = $request->input('bus_stop_id');

        try {
            DB::beginTransaction();

            $memberId = $request->input('tiu_member_id');

            if ($source === 'first_timer' || $source === 'new_user') {
                if (empty($memberId)) {
                    // Create new member
                    $firstName = $request->input('first_name');
                    $lastName = $request->input('last_name');
                    $gender = $request->input('gender');
                    $address = $request->input('residential_address', '');

                    if (empty($firstName) || empty($lastName) || empty($gender)) {
                        return response()->json(['success' => false, 'message' => 'First name, last name, and gender are required for new members.']);
                    }

                    $memberId = DB::table('tiu_member')->insertGetId([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => $email,
                        'phone_number' => $phone,
                        'gender' => $gender,
                        'residential_address' => $address,
                        'campus_community' => session('campus_community', ''),
                        'bus_stop_id' => $busStopId,
                        'member_role' => 'Member',
                        'department_name' => '[]',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Update bus_stop_id for the member
            if ($memberId) {
                DB::table('tiu_member')
                    ->where('tiu_member_id', $memberId)
                    ->update(['bus_stop_id' => $busStopId]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transport registration successful!',
                'redirect' => url('/transport-register'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

}
