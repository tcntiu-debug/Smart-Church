<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FofController extends Controller
{
    /**
     * Get active cohorts
     */
    public function getCohorts(Request $request)
    {
        $cohorts = DB::table('fof_cohort_setting')
            ->where('cohort_status', 'Active')
            ->orderBy('cohort_name', 'asc')
            ->get();

        return response()->json(['success' => true, 'data' => $cohorts]);
    }

    /**
     * Lookup user by email or phone
     */
    public function lookup(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email',
            'phone_number' => 'nullable|string',
        ]);

        $email = $request->email;
        $phone = $request->phone_number;

        if (empty($email) && empty($phone)) {
            return response()->json(['success' => false, 'message' => 'Email or Phone is required'], 400);
        }

        $userData = [
            'email' => $email,
            'phone_number' => $phone,
            'source' => 'new_user'
        ];

        $field = !empty($email) ? 'email' : 'phone_number';
        $lookupParam = !empty($email) ? $email : $phone;

        $member = DB::table('tiu_member')
            ->where($field, $lookupParam)
            ->first();

        if ($member) {
            $member->source = 'tiu_member';
            return response()->json(['success' => true, 'data' => $member]);
        }

        $firstTimer = DB::table('first_timer')
            ->where($field, $lookupParam)
            ->orderBy('first_timer_id', 'desc')
            ->first();

        if ($firstTimer) {
            $firstTimer->source = 'first_timer';
            return response()->json(['success' => true, 'data' => $firstTimer]);
        }

        return response()->json(['success' => true, 'data' => $userData]);
    }

    /**
     * Register for FOF
     */
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email',
            'phone_number' => 'required|string|max:20',
            'cohort_id' => 'required|integer',
        ]);

        $userId = $request->user_id;
        $user = DB::selectOne("SELECT campus_id FROM tiu_member WHERE tiu_member_id = ?", [$userId]);
        $campusId = $user->campus_id ?? 1;

        try {
            DB::beginTransaction();

            $tiuMemberId = $request->tiu_member_id;

            // Create member if new
            if (empty($tiuMemberId)) {
                $existing = DB::table('tiu_member')
                    ->where('email', $request->email)
                    ->orWhere('phone_number', $request->phone_number)
                    ->first();

                if ($existing) {
                    return response()->json(['success' => false, 'message' => 'Member already exists'], 409);
                }

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
                    'password' => password_hash('welcome123', PASSWORD_DEFAULT),
                    'date_registered' => now(),
                    'member_role' => 'Member',
                    'status' => 1,
                    'campus_id' => $campusId,
                ]);
            }

            // Check for duplicate FOF registration
            $existingFof = DB::table('fof_register_table')
                ->where('tiu_member_id', $tiuMemberId)
                ->where('cohort_id', $request->cohort_id)
                ->first();

            if ($existingFof) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Already registered for this cohort'], 409);
            }

            // Insert FOF registration
            DB::table('fof_register_table')->insert([
                'tiu_member_id' => $tiuMemberId,
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
                'registration_date' => now(),
                'campus_id' => $campusId,
            ]);

            // Handle birthday
            if ($request->bday && $request->bmonth) {
                $birthdayStr = $request->bday . ' ' . $request->bmonth;
                $fullName = $request->first_name . ' ' . $request->last_name;
                DB::table('birthday')->updateOrInsert(
                    ['tiu_member_id' => $tiuMemberId],
                    ['Name' => $fullName, 'birthday' => $birthdayStr]
                );
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Registration successful!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get FOF members list with filters
     */
    public function members(Request $request)
    {
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

        if ($request->cohort_id) {
            $query->where('r.cohort_id', $request->cohort_id);
        }
        if ($request->year) {
            $query->whereYear('r.registration_date', $request->year);
        }

        $members = $query->orderBy('r.registration_date', 'desc')->get();

        $cohorts = DB::table('fof_cohort_setting')->orderBy('cohort_name', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $members,
            'cohorts' => $cohorts
        ]);
    }

    /**
     * Get students for attendance marking
     */
    public function getStudents(Request $request)
    {
        $request->validate(['cohort_id' => 'required|integer']);

        $students = DB::table('fof_register_table')
            ->where('cohort_id', $request->cohort_id)
            ->orderBy('first_name', 'asc')
            ->get();

        $cohort = DB::table('fof_cohort_setting')
            ->where('cohort_id', $request->cohort_id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => $students,
            'cohort_name' => $cohort->cohort_name ?? ''
        ]);
    }

    /**
     * Mark attendance for a student
     */
    public function markAttendance(Request $request)
    {
        $request->validate([
            'student_id' => 'required|integer',
            'cohort' => 'required|string',
            'week' => 'required|integer|min:1|max:8',
            'status' => 'required|in:Present,Absent',
        ]);

        $userId = $request->user_id;
        $user = DB::selectOne("SELECT campus_id FROM tiu_member WHERE tiu_member_id = ?", [$userId]);
        $campusId = $user->campus_id ?? 1;

        DB::table('fof_mark_attendance_table')->updateOrInsert(
            [
                'student_id' => $request->student_id,
                'cohort' => $request->cohort,
                'week' => $request->week,
                'attendance_date' => now()->format('Y-m-d'),
            ],
            [
                'status' => $request->status,
                'finished' => 0,
                'campus_id' => $campusId,
            ]
        );

        return response()->json(['success' => true, 'message' => 'Attendance updated!']);
    }

    /**
     * Get attendance records with filters
     */
    public function viewAttendance(Request $request)
    {
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

        if ($request->cohort) {
            $query->where('a.cohort', $request->cohort);
        }
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('a.attendance_date', [$request->start_date, $request->end_date]);
        }

        $records = $query->orderBy('student_name')->orderBy('a.week')->get();

        $students = [];
        $weeks = range(1, 8);

        foreach ($records as $record) {
            if (!isset($students[$record->student_name])) {
                $students[$record->student_name] = ['cohort' => $record->cohort, 'weeks' => []];
            }
            $students[$record->student_name]['weeks'][$record->week] = $record->status;
        }

        $cohorts = DB::table('fof_cohort_setting')->orderBy('cohort_name', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $students,
            'weeks' => $weeks,
            'cohorts' => $cohorts
        ]);
    }
}
