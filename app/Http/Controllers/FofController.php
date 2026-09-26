<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class FofController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * FOF Register - display registration form (multi-step)
     */
    public function register()
    {
        $cohorts = DB::table('fof_cohort_setting')->where('cohort_status', 'Active')->orderBy('cohort_name', 'asc')->get();
        $departments = DB::table('department')
            ->where('dept_type', 'Department')
            ->where('dept_name', '!=', 'Not Applicable')
            ->orderBy('dept_name', 'asc')
            ->get();
        $occupations = DB::table('occupation')->orderBy('occ_name', 'asc')->get();

        $formState = Session::get('fof_form_state', 'initial');
        $userData = Session::get('fof_user_data', []);
        $errorMessage = Session::get('fof_error', '');

        // Clear session after retrieval
        Session::forget(['fof_form_state', 'fof_user_data', 'fof_error']);

        return view('fof.register', compact('cohorts', 'departments', 'occupations', 'formState', 'userData', 'errorMessage'));
    }

    /**
     * Lookup user by email or phone (Step 1 of registration)
     */
    public function lookup(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email',
            'phone_number' => 'nullable|string|max:30',
        ]);

        $email = $request->email;
        $phone = $request->phone_number;

        if (empty($email) && empty($phone)) {
            return redirect()->back()->with('error', 'Email or Phone Number is required to proceed.');
        }

        $userData = [];
        $userData['email'] = $email;
        $userData['phone_number'] = $phone;
        $userData['source'] = 'new_user';

        try {
            $lookupParam = !empty($email) ? $email : $phone;
            $field = !empty($email) ? 'email' : 'phone_number';

            // Lookup in tiu_member
            $member = DB::table('tiu_member')
                ->where($field, $lookupParam)
                ->first();

            if ($member) {
                $member = (array)$member;
                $member['source'] = 'tiu_member';
                $userData = $member;
            } else {
                // Lookup in first_timer
                $firstTimer = DB::table('first_timer')
                    ->where($field, $lookupParam)
                    ->orderBy('first_timer_id', 'desc')
                    ->first();

                if ($firstTimer) {
                    $ft = (array)$firstTimer;
                    $userData = array_merge($userData, $ft);
                    $userData['source'] = 'first_timer';
                }
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'A database error occurred. Please try again later.');
        }

        Session::put('fof_form_state', 'full_form');
        Session::put('fof_user_data', $userData);

        return redirect()->route('fof.register');
    }

    /**
     * Store FOF registration (full submission via AJAX)
     */
    public function store(Request $request)
    {
        // Validate
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone_number' => 'required|string|max:20',
            'gender' => 'nullable|string|max:15',
            'marital_status' => 'nullable|string|max:15',
            'cohort_id' => 'required|integer',
            'smart_request' => 'nullable|string',
            'commitment' => 'nullable|string|max:10',
            'how_heard' => 'nullable|string|max:100',
        ]);

        $campusId = Auth::user()->campus_id ?? 1;

        try {
            DB::beginTransaction();

            $tiuMemberId = $request->tiu_member_id;
            $firstTimerId = $request->first_timer_id;
            $profilePhotoPath = $request->existing_photo_path ?? '';

            // Handle photo upload
            if ($request->hasFile('profile_photo')) {
                $file = $request->file('profile_photo');
                $uploadDir = public_path('display_photo');
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $uniqueFilename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move($uploadDir, $uniqueFilename);
                $profilePhotoPath = 'display_photo/' . $uniqueFilename;
            }

            // If existing member, update their record
            if ($tiuMemberId) {
                $updateData = [
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'phone_number' => $request->phone_number,
                    'email' => $request->email,
                    'gender' => $request->gender ?? '',
                    'marital_status' => $request->marital_status ?? 'Single',
                    'age' => $request->age ?? '',
                    'occupation' => $request->occupation ?? '',
                    'residential_address' => $request->residential_address ?? '',
                ];

                if ($profilePhotoPath && $profilePhotoPath !== $request->existing_photo_path) {
                    $updateData['picture_part'] = $profilePhotoPath;
                }

                // Handle department JSON
                $deptIds = $request->department ?? [];
                if (!empty($deptIds)) {
                    $updateData['department_name'] = json_encode($deptIds);
                }

                DB::table('tiu_member')->where('tiu_member_id', $tiuMemberId)->update($updateData);
            } else {
                // Check for duplicate
                $existing = DB::table('tiu_member')
                    ->where(function($q) use ($request) {
                        $q->where('email', $request->email)
                          ->orWhere('phone_number', $request->phone_number);
                    })
                    ->first();

                if ($existing) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A member with this email or phone number already exists.'
                    ]);
                }

                // Create new member
                $defaultPassword = password_hash('welcome123', PASSWORD_DEFAULT);
                $deptIds = $request->department ?? [];
                $deptJson = !empty($deptIds) ? json_encode($deptIds) : '';

                $tiuMemberId = DB::table('tiu_member')->insertGetId([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'phone_number' => $request->phone_number,
                    'email' => $request->email,
                    'gender' => $request->gender ?? '',
                    'marital_status' => $request->marital_status ?? 'Single',
                    'age' => $request->age ?? '',
                    'occupation' => $request->occupation ?? '',
                    'residential_address' => $request->residential_address ?? '',
                    'department_name' => $deptJson,
                    'picture_part' => $profilePhotoPath,
                    'password' => $defaultPassword,
                    'date_registered' => now(),
                    'member_role' => 'Member',
                    'status' => 1,
                    'campus_id' => $campusId,
                ]);

                // Update first_timer status if applicable
                if ($firstTimerId) {
                    DB::table('first_timer')
                        ->where('first_timer_id', $firstTimerId)
                        ->update(['status' => 'Integrated']);
                }
            }

            // Handle birthday
            $bday = $request->bday;
            $bmonth = $request->bmonth;
            if (!empty($bday) && !empty($bmonth)) {
                $birthdayStr = $bday . ' ' . $bmonth;
                $fullName = $request->first_name . ' ' . $request->last_name;

                $existingBday = DB::table('birthday')
                    ->where('tiu_member_id', $tiuMemberId)
                    ->first();

                if ($existingBday) {
                    DB::table('birthday')
                        ->where('tiu_member_id', $tiuMemberId)
                        ->update(['Name' => $fullName, 'birthday' => $birthdayStr]);
                } else {
                    DB::table('birthday')->insert([
                        'tiu_member_id' => $tiuMemberId,
                        'Name' => $fullName,
                        'birthday' => $birthdayStr,
                    ]);
                }
            }

            // Check for duplicate FOF registration
            $existingFof = DB::table('fof_register_table')
                ->where('tiu_member_id', $tiuMemberId)
                ->where('cohort_id', $request->cohort_id)
                ->first();

            if ($existingFof) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'You are already registered for this FOF cohort.'
                ]);
            }

            // Insert FOF registration
            DB::table('fof_register_table')->insert([
                'tiu_member_id' => $tiuMemberId,
                'first_timer_id' => $firstTimerId,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'gender' => $request->gender ?? '',
                'marital_status' => $request->marital_status ?? 'Single',
                'cohort_id' => $request->cohort_id,
                'smart_request' => $request->smart_request ?? '',
                'commitment' => $request->commitment ?? '',
                'how_heard' => $request->how_heard ?? '',
                'how_heard_other' => ($request->how_heard === 'Other') ? ($request->how_heard_other ?? '') : '',
                'profile_photo' => $profilePhotoPath,
                'registration_date' => now(),
                'campus_id' => $campusId,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Your registration has been submitted successfully!'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FOF Submit Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * FOF Members list with filters and charts
     */
    public function members(Request $request)
    {
        $cohorts = DB::table('fof_cohort_setting')->orderBy('cohort_name', 'asc')->get();

        // Get available years from registration dates
        $years = DB::table('fof_register_table')
            ->select(DB::raw('DISTINCT YEAR(registration_date) as reg_year'))
            ->orderBy('reg_year', 'desc')
            ->pluck('reg_year')
            ->toArray();

        // Get department mapping
        $deptMap = DB::table('department')
            ->pluck('dept_name', 'dept_id')
            ->toArray();

        // Filters
        $selectedCohortId = $request->get('cohort', '');
        $selectedYear = $request->get('year', '');

        // Build query for members
        $query = DB::table('fof_register_table as r')
            ->leftJoin('fof_cohort_setting as cs', 'r.cohort_id', '=', 'cs.cohort_id')
            ->leftJoin('tiu_member as tm', 'r.tiu_member_id', '=', 'tm.tiu_member_id')
            ->select(
                'r.*',
                'cs.cohort_name',
                'tm.picture_part as member_photo',
                'tm.member_role',
                'tm.department_name'
            );

        if (!empty($selectedCohortId)) {
            $query->where('r.cohort_id', $selectedCohortId);
        }
        if (!empty($selectedYear)) {
            $query->whereYear('r.registration_date', $selectedYear);
        }

        $members = $query->orderBy('r.registration_date', 'desc')->get();

        // Chart data
        $chartQuery = DB::table('fof_register_table as r')
            ->select(
                DB::raw("DATE_FORMAT(r.registration_date, '%b %y') as registration_month_year"),
                DB::raw('COUNT(r.id) as registration_count')
            );

        if (!empty($selectedCohortId)) {
            $chartQuery->where('r.cohort_id', $selectedCohortId);
        }
        if (!empty($selectedYear)) {
            $chartQuery->whereYear('r.registration_date', $selectedYear);
        }

        $chartData = $chartQuery
            ->groupBy(DB::raw("YEAR(r.registration_date), MONTH(r.registration_date), DATE_FORMAT(r.registration_date, '%b %y')"))
            ->orderBy(DB::raw("YEAR(r.registration_date)"), 'asc')
            ->orderBy(DB::raw("MONTH(r.registration_date)"), 'asc')
            ->get();

        $chartLabels = $chartData->pluck('registration_month_year');
        $chartValues = $chartData->pluck('registration_count');

        return view('fof.members', compact(
            'members', 'cohorts', 'years',
            'selectedCohortId', 'selectedYear',
            'chartLabels', 'chartValues', 'deptMap'
        ));
    }

    /**
     * FOF View Students (for marking attendance)
     */
    public function viewStudents(Request $request)
    {
        $cohortId = $request->get('cohort_id');
        $date = $request->get('date', now()->format('Y-m-d'));

        $cohorts = DB::table('fof_cohort_setting')
            ->where('cohort_status', 'Active')
            ->orderBy('cohort_name', 'asc')
            ->get();

        $students = collect();
        $markedToday = [];

        if ($cohortId) {
            $students = DB::table('fof_register_table')
                ->where('cohort_id', $cohortId)
                ->orderBy('first_name', 'asc')
                ->get();

            // Get cohort name
            $cohort = DB::table('fof_cohort_setting')->where('cohort_id', $cohortId)->first();
            $cohortName = $cohort->cohort_name ?? '';

            // Get attendance records for today
            $markedToday = DB::table('fof_mark_attendance_table')
                ->where('cohort', $cohortName)
                ->whereDate('attendance_date', $date)
                ->pluck('status', 'student_id')
                ->toArray();
        }

        return view('fof.view-students', compact('students', 'cohorts', 'cohortId', 'date', 'markedToday'));
    }

    /**
     * Mark FOF attendance (save via AJAX)
     */
    public function markAttendance(Request $request)
    {
        $request->validate([
            'student_id' => 'required|integer',
            'cohort' => 'required|string|max:15',
            'week' => 'required|integer|min:1|max:8',
            'status' => 'required|in:Present,Absent',
        ]);

        $campusId = Auth::user()->campus_id ?? 1;
        $attendanceDate = now()->format('Y-m-d');

        try {
            DB::table('fof_mark_attendance_table')->updateOrInsert(
                [
                    'student_id' => $request->student_id,
                    'cohort' => $request->cohort,
                    'week' => $request->week,
                    'attendance_date' => $attendanceDate,
                ],
                [
                    'status' => $request->status,
                    'finished' => 0,
                    'campus_id' => $campusId,
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Attendance updated!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Finish marking week (lock attendance)
     */
    public function finishWeek(Request $request)
    {
        $request->validate([
            'cohort' => 'required|string',
            'week' => 'required|integer',
        ]);

        try {
            DB::table('fof_mark_attendance_table')
                ->where('cohort', $request->cohort)
                ->where('week', $request->week)
                ->update(['finished' => 1]);

            return response()->json([
                'status' => 'success',
                'message' => 'Attendance marking finished!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Edit week (unlock attendance)
     */
    public function editWeek(Request $request)
    {
        $request->validate([
            'cohort' => 'required|string',
            'week' => 'required|integer',
        ]);

        try {
            DB::table('fof_mark_attendance_table')
                ->where('cohort', $request->cohort)
                ->where('week', $request->week)
                ->update(['finished' => 0]);

            return response()->json([
                'status' => 'success',
                'message' => 'Attendance unlocked for editing.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * FOF View Attendance Records with filters and summary
     */
    public function viewAttendance(Request $request)
    {
        $cohorts = DB::table('fof_cohort_setting')->orderBy('cohort_name', 'asc')->get();

        $selectedCohort = $request->get('cohort', '');
        $startDate = $request->get('start_date', '');
        $endDate = $request->get('end_date', '');

        $query = DB::table('fof_mark_attendance_table as a')
            ->join('fof_register_table as r', 'a.student_id', '=', 'r.id')
            ->select(
                DB::raw("CONCAT_WS(' ', r.first_name, r.last_name) as student_name"),
                'a.week',
                'a.status',
                'a.cohort',
                'a.attendance_date'
            )
            ->where('a.finished', 1);

        if (!empty($selectedCohort)) {
            $query->where('a.cohort', $selectedCohort);
        }
        if (!empty($startDate) && !empty($endDate)) {
            $query->whereBetween('a.attendance_date', [$startDate, $endDate]);
        }

        $records = $query->orderBy('student_name')
            ->orderBy('a.week')
            ->get();

        // Process data into structured format
        $students = [];
        $weeks = range(1, 8);

        foreach ($records as $record) {
            if (!isset($students[$record->student_name])) {
                $students[$record->student_name] = [
                    'cohort' => $record->cohort,
                    'weeks' => []
                ];
            }
            $students[$record->student_name]['weeks'][$record->week] = $record->status;
        }

        // Calculate cohort summary
        $cohortSummary = [];
        foreach ($students as $studentName => $data) {
            $cohort = $data['cohort'];
            if (!isset($cohortSummary[$cohort])) {
                $cohortSummary[$cohort] = [
                    'students' => [],
                    'present_count' => 0,
                    'absent_count' => 0
                ];
            }
            $cohortSummary[$cohort]['students'][$studentName] = 1;

            foreach ($data['weeks'] as $status) {
                if ($status === 'Present') {
                    $cohortSummary[$cohort]['present_count']++;
                } else {
                    $cohortSummary[$cohort]['absent_count']++;
                }
            }
        }

        return view('fof.view-attendance', compact(
            'students', 'weeks', 'cohorts',
            'selectedCohort', 'startDate', 'endDate',
            'cohortSummary'
        ));
    }

    /**
     * Get attendance data for a cohort/week (AJAX for mark attendance page)
     */
    public function getAttendance(Request $request)
    {
        $cohortId = $request->get('cohort_id');
        $week = $request->get('week', 1);

        if (empty($cohortId)) {
            return response()->json(['error' => 'Cohort ID required'], 400);
        }

        $cohort = DB::table('fof_cohort_setting')->where('cohort_id', $cohortId)->first();
        if (!$cohort) {
            return response()->json(['error' => 'Cohort not found'], 404);
        }

        $students = DB::table('fof_register_table')
            ->where('cohort_id', $cohortId)
            ->orderBy('first_name', 'asc')
            ->get();

        $attendanceData = DB::table('fof_mark_attendance_table')
            ->where('cohort', $cohort->cohort_name)
            ->where('week', $week)
            ->get()
            ->keyBy('student_id');

        $finished = $attendanceData->isNotEmpty() && $attendanceData->first()->finished == 1;

        return response()->json([
            'cohort_name' => $cohort->cohort_name,
            'students' => $students,
            'attendance' => $attendanceData,
            'finished' => $finished,
            'week' => $week
        ]);
    }
}
