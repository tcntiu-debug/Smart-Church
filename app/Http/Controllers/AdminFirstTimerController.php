<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminFirstTimerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * ====================================================================
     * /fview — Admin First Timer View (Full Management)
     * ====================================================================
     * Original: tiu/first-timer-view.php
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        $userCampusId = $user->campus_id ?? session('campus_id', 0);

        // Only Super User and Admin
        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect('/mytask')->with('error', 'Access Denied');
        }

        $dayLimit = DB::table('first_time_view_limit')->value('data_limit') ?? 60;

        // Get church type for filtering
        $churchTypeId = session('church_type_id', '');
        $churchTypeName = '';
        if (!empty($churchTypeId)) {
            $ct = DB::table('church_type')->find($churchTypeId);
            $churchTypeName = $ct->church_type_name ?? '';
        }

        // Filters
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $genderFilter = $request->get('gender_filter');

        // Departments for dropdown
        $departments = DB::table('department')
            ->where('dept_type', 'Department')
            ->orderBy('dept_name', 'asc')
            ->pluck('dept_name')
            ->toArray();

        // Build query with attendance count subquery
        $attendanceSub = DB::table('first_timer_hangout')
            ->select('first_timer_id', DB::raw('COUNT(*) as attendance_count'))
            ->groupBy('first_timer_id');

        $query = DB::table('first_timer as ft')
            ->leftJoin('church_type as ct', 'ft.church_type_id', '=', 'ct.id')
            ->leftJoin('member_tracking_followup as mtf', 'ft.first_timer_id', '=', 'mtf.first_timer_id')
            ->leftJoin('tiu_member as tm', 'mtf.tiu_member_id', '=', 'tm.tiu_member_id')
            ->leftJoin('campus as cp', 'ft.campus_id', '=', 'cp.cid')
            ->leftJoinSub($attendanceSub, 'fth', 'ft.first_timer_id', '=', 'fth.first_timer_id')
            ->select(
                'ft.*',
                'ct.church_type_name',
                DB::raw('CONCAT(tm.first_name, " ", tm.last_name) as guide_name'),
                'mtf.tiu_member_id as guide_id',
                'mtf.timeStamp_registered as assigned_date',
                'cp.cname as campus_name',
                DB::raw('COALESCE(fth.attendance_count, 0) as attendance_count')
            );

        // Date filters
        if ($startDate && $endDate) {
            $query->whereBetween(DB::raw('DATE(ft.register_date)'), [$startDate, $endDate]);
        } else {
            $query->where(DB::raw('DATE(ft.register_date)'), '>=', DB::raw("DATE_SUB(CURDATE(), INTERVAL $dayLimit DAY)"));
        }

        // Gender filter
        if ($genderFilter) {
            $query->where('ft.gender', $genderFilter);
        }

        // Church type filter for "Switch" users
        if ($churchTypeName === 'Switch' && !empty($churchTypeId)) {
            $query->where('ft.church_type_id', $churchTypeId);
        }

        // CAMPUS FILTER: Only show first timers from the same campus as the logged-in user
        // Super Users see ALL first timers regardless of campus
        if ($memberRole !== 'Super User' && !empty($userCampusId)) {
            $query->where('ft.campus_id', $userCampusId);
        }

        $firstTimers = $query->orderBy('ft.register_date', 'desc')->paginate(50);

        // Status counts (also respect campus filter)
        $statusCountsQuery = DB::table('first_timer')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->when($churchTypeName === 'Switch' && !empty($churchTypeId), function ($q) use ($churchTypeId) {
                return $q->where('church_type_id', $churchTypeId);
            });

        if ($memberRole !== 'Super User' && !empty($userCampusId)) {
            $statusCountsQuery->where('campus_id', $userCampusId);
        }

        $statusCounts = $statusCountsQuery->groupBy('status')->pluck('count', 'status');

        return view('admin.first-timers', compact(
            'firstTimers',
            'departments',
            'startDate',
            'endDate',
            'genderFilter',
            'dayLimit',
            'statusCounts'
        ));
    }

    /**
     * ====================================================================
     * /fviewupdate — First Timers Update Report
     * ====================================================================
     * Original: tiu/first-timer-update.php
     */
    public function updates(Request $request)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        $userCampusId = $user->campus_id ?? session('campus_id', 0);

        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect('/mytask')->with('error', 'Access Denied');
        }

        $churchTypeId = session('church_type_id', '');
        $churchTypeName = '';
        if (!empty($churchTypeId)) {
            $ct = DB::table('church_type')->find($churchTypeId);
            $churchTypeName = $ct->church_type_name ?? '';
        }

        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $filter = $request->get('filter');

        $query = DB::table('first_timers_updates as ftu')
            ->join('first_timer as ft', 'ftu.first_timer_id', '=', 'ft.first_timer_id')
            ->select(
                'ftu.*',
                'ft.first_name',
                'ft.last_name',
                'ft.phone_number',
                'ft.campus_id'
            );

        if ($filter === 'this_week') {
            $query->whereNotNull('ftu.birthday')
                ->where('ftu.birthday', '!=', '')
                ->where('ftu.birthday', '!=', 'Pending')
                ->whereRaw('DAYOFYEAR(STR_TO_DATE(CONCAT(ftu.birthday, " ", YEAR(CURDATE())), "%d %b %Y")) BETWEEN DAYOFYEAR(CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY) AND DAYOFYEAR(CURDATE() + INTERVAL (6 - WEEKDAY(CURDATE())) DAY)');
        } elseif ($startDate && $endDate) {
            $query->whereBetween(DB::raw('DATE(ftu.created_date)'), [$startDate, $endDate]);
        } else {
            $query->whereYear('ftu.created_date', '=', date('Y'));
        }

        if ($churchTypeName === 'Switch' && !empty($churchTypeId)) {
            $query->where('ft.church_type_id', $churchTypeId);
        }

        // CAMPUS FILTER: Only show first timers from the same campus as the logged-in user
        // Super Users see ALL
        if ($memberRole !== 'Super User' && !empty($userCampusId)) {
            $query->where('ft.campus_id', $userCampusId);
        }

        $updates = $query->orderBy('ftu.created_date', 'desc')->get();

        return view('admin.first-timers-update', compact('updates', 'startDate', 'endDate', 'filter'));
    }

    /**
     * ====================================================================
     * /fthangout — Hangout Data
     * ====================================================================
     * Original: tiu/first-timer-hangout.php
     */
    public function hangout(Request $request)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';

        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect('/mytask')->with('error', 'Access Denied');
        }

        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // Only show records when a date range is selected
        if ($startDate && $endDate) {
            $hangouts = DB::table('first_timer_hangout')
                ->whereBetween(DB::raw('DATE(hangout_date)'), [$startDate, $endDate])
                ->orderBy('hangout_date', 'asc')
                ->orderBy('h_id', 'asc')
                ->get();

            $summary = DB::table('first_timer_hangout')
                ->whereBetween(DB::raw('DATE(hangout_date)'), [$startDate, $endDate])
                ->select(
                    DB::raw('YEAR(hangout_date) as yr'),
                    DB::raw('MONTHNAME(hangout_date) as mnth'),
                    DB::raw('MONTH(hangout_date) as mn'),
                    DB::raw('COUNT(*) as total')
                )
                ->groupBy('yr', 'mnth', 'mn')
                ->orderBy('yr', 'desc')
                ->orderBy('mn', 'desc')
                ->get();
        } else {
            $hangouts = collect(); // Empty collection
            $summary = collect(); // Empty summary
        }

        return view('admin.hangout', compact('hangouts', 'summary', 'startDate', 'endDate'));
    }

    /**
     * ====================================================================
     * API: Get chat history for a first timer
     * ====================================================================
     */
    public function getChatHistory(Request $request)
    {
        $firstTimerId = $request->get('first_timer_id');

        $messages = DB::table('messages as m')
            ->leftJoin('tiu_member as tm', 'm.user_id', '=', 'tm.tiu_member_id')
            ->where('m.conversation_id', $firstTimerId)
            ->select(
                'm.message',
                'm.created_at',
                'm.user_id',
                'tm.member_role',
                DB::raw("CONCAT_WS(' ', tm.first_name, tm.last_name) as user_name")
            )
            ->orderBy('m.created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'current_user_id' => Auth::user()->tiu_member_id ?? Auth::id()
        ]);
    }

    /**
     * ====================================================================
     * API: Submit chat message
     * ====================================================================
     */
    public function submitChatMessage(Request $request)
    {
        $request->validate([
            'first_timer_id' => 'required|integer',
            'message' => 'required|string',
        ]);

        $userId = Auth::user()->tiu_member_id ?? Auth::id();

        DB::table('messages')->insert([
            'conversation_id' => $request->first_timer_id,
            'user_id' => $userId,
            'message' => $request->message,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message sent.'
        ]);
    }

    /**
     * ====================================================================
     * API: Get hangout records as JSON (for auto-refresh)
     * ====================================================================
     */
    public function getHangoutData(Request $request)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';

        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return response()->json(['success' => false, 'message' => 'Access Denied']);
        }

        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if ($startDate && $endDate) {
            $hangouts = DB::table('first_timer_hangout')
                ->whereBetween(DB::raw('DATE(hangout_date)'), [$startDate, $endDate])
                ->orderBy('hangout_date', 'asc')
                ->orderBy('h_id', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'hangouts' => $hangouts,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
        }

        return response()->json(['success' => false, 'hangouts' => []]);
    }

    /**
     * ====================================================================
     * API: Get occupations list for dropdown
     * ====================================================================
     * Original: tiu/process_files/get_occupations_list.php
     */

    public function getOccupations()
    {
        $occupations = DB::table('occupation')
            ->orderBy('occ_name', 'asc')
            ->pluck('occ_name')
            ->toArray();

        return response()->json([
            'success' => true,
            'occupations' => $occupations
        ]);
    }

    /**
     * ====================================================================
     * POST: Register a first timer for hangout (from /fview modal)
     * ====================================================================
     */
    public function hangoutRegister(Request $request)
    {
        $request->validate([
            'first_timer_id' => 'required|integer',
            'occupation2' => 'required|string',
            'member_status' => 'required|string',
        ]);

        // Fetch the first timer's name and phone to save in hangout record
        $ft = DB::table('first_timer')->where('first_timer_id', $request->first_timer_id)->first();

        DB::table('first_timer_hangout')->insert([
            'first_timer_id' => $request->first_timer_id,
            'first_name' => $ft->first_name ?? '',
            'last_name' => $ft->last_name ?? '',
            'phone_number' => $ft->phone_number ?? '',
            'occupation' => $ft->occupation ?? '',
            'occupation2' => $request->occupation2,
            'member_status' => $request->member_status,
            'department' => $request->department ?? '',
            'hangout_date' => now(),
        ]);

        return redirect()->route('admin.first-timers')->with('success', 'Hangout registered successfully.');
    }

    /**
     * ====================================================================
     * POST: Perform action on a first timer (Unassigned, Disable, Integrated, Delete)
     * ====================================================================
     */
    public function performAction(Request $request)
    {
        $request->validate([
            'first_timer_id' => 'required|integer',
            'more_action' => 'required|string|in:Disable,Unassigned,Integrated,Delete',
            'unassign_reason' => 'required_if:more_action,Unassigned|string|max:1000',
        ]);

        $ftId = $request->first_timer_id;
        $action = $request->more_action;

        if ($action === 'Delete') {
            // Delete from first_timer and related records
            DB::table('member_tracking_followup')->where('first_timer_id', $ftId)->delete();
            DB::table('first_timer_hangout')->where('first_timer_id', $ftId)->delete();
            DB::table('first_timers_updates')->where('first_timer_id', $ftId)->delete();
            DB::table('first_timer')->where('first_timer_id', $ftId)->delete();

            return redirect()->route('admin.first-timers')->with('success', 'First timer deleted successfully.');
        }

        if ($action === 'Unassigned') {
            // Remove guide assignment from member_tracking_followup
            DB::table('member_tracking_followup')->where('first_timer_id', $ftId)->delete();
            DB::table('first_timer')->where('first_timer_id', $ftId)->update([
                'status' => 'Unassigned',
                'status_change_date' => now(),
                'unassign_reason' => $request->unassign_reason,
            ]);

            return redirect()->route('admin.first-timers')->with('success', 'First timer unassigned successfully.');
        }

        if ($action === 'Disable') {
            DB::table('first_timer')->where('first_timer_id', $ftId)->update([
                'status' => 'Disable',
                'status_change_date' => now(),
            ]);

            return redirect()->route('admin.first-timers')->with('success', 'First timer disabled successfully.');
        }

        if ($action === 'Integrated') {
            // Fetch the first timer data
            $ft = DB::table('first_timer')->where('first_timer_id', $ftId)->first();

            if (!$ft) {
                return redirect()->route('admin.first-timers')->with('error', 'First timer not found.');
            }

            // Check if email already exists in tiu_member
            if (!empty($ft->email)) {
                $existingMember = DB::table('tiu_member')->where('email', $ft->email)->first();
                if ($existingMember) {
                    return redirect()->route('admin.first-timers')->with('error', 'A member with this email already exists in the member database.');
                }
            }

            // Generate random 8-char password
            $password = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 8);

            try {
                DB::beginTransaction();

                // Insert into tiu_member
                $memberId = DB::table('tiu_member')->insertGetId([
                    'first_name' => $ft->first_name,
                    'last_name' => $ft->last_name,
                    'phone_number' => $ft->phone_number ?? '',
                    'email' => $ft->email ?? '',
                    'gender' => $ft->gender ?? '',
                    'marital_status' => $ft->marital_status ?? '',
                    'age' => $ft->age ?? null,
                    'occupation' => $ft->occupation ?? '',
                    'residential_address' => $ft->address ?? '',
                    'campus_id' => $ft->campus_id ?? null,
                    'church_type_id' => $ft->church_type_id ?? null,
                    'parent_gaudian' => $ft->parent_name ?? '',
                    'Phone' => $ft->parent_phone ?? '',
                    'Relationship' => $ft->relationship ?? '',
                    'member_role' => 'Member',
                    'status' => 1,
                    'email_verified' => 0,
                    'password' => Hash::make($password),
                    'date_registered' => now(),
                    'department_name' => '[]',
                    'cluster' => '[]',
                    'house_fellowship' => '[]',
                    'oversight_extra1' => '[]',
                ]);

                // Create a first_timers_updates record for the integration
                DB::table('first_timers_updates')->insert([
                    'first_timer_id' => $ft->first_timer_id,
                    'department' => '',
                    'cluster' => '',
                    'house_fellowship' => '',
                    'foundation_of_faith' => '',
                    'water_baptism' => $ft->water_baptism ?? '',
                    'holy_ghost_baptism' => $ft->holy_ghost_baptism ?? '',
                    'created_date' => now(),
                ]);

                // Update first_timer status
                DB::table('first_timer')->where('first_timer_id', $ftId)->update([
                    'status' => 'Integrated',
                    'status_change_date' => now(),
                ]);

                DB::commit();

                return redirect()->route('admin.first-timers')->with('success', "First timer integrated successfully as a member (ID: {$memberId}). Password: {$password}");
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Integration failed: ' . $e->getMessage());
                return redirect()->route('admin.first-timers')->with('error', 'Integration failed. Please check the logs for details.');
            }
        }

        return redirect()->route('admin.first-timers')->with('error', 'Invalid action.');
    }

    /**
     * ====================================================================
     * POST: Update first timer details (name, email, phone)
     * ====================================================================
     */
    public function updateDetails(Request $request)
    {
        $request->validate([
            'first_timer_id' => 'required|integer',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone_number' => 'nullable|string|max:20',
        ]);

        DB::table('first_timer')->where('first_timer_id', $request->first_timer_id)->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email ?? '',
            'phone_number' => $request->phone_number ?? '',
        ]);

        return redirect()->route('admin.first-timers')->with('success', 'First timer details updated successfully.');
    }

    /**
     * ====================================================================
     * API: Get guides names filtered by occupation and gender
     * ====================================================================
     * Original: tiu/process_files/guild_names.php
     */
    public function getGuides(Request $request)
    {
        $occupation = trim($request->get('occupation', ''));
        $gender = trim($request->get('gender', ''));

        $user = Auth::user();
        $currentUserRole = $user->member_role ?? '';
        $currentUserSubgroup = $user->subgroup ?? '';

        $query = DB::table('tiu_member')
            ->whereNotIn('status', [2, 3])
            ->select('tiu_member_id', 'first_name', 'last_name');

        // If user is a Lead, filter by subgroup
        if ($currentUserRole === 'Lead') {
            $query->where('subgroup', $currentUserSubgroup);
        }

        // Filter by occupation (unless "All")
        if ($occupation !== 'All' && !empty($occupation)) {
            $query->where('occupation', $occupation);
        }

        // Filter by gender (unless "All")
        if ($gender !== 'All' && !empty($gender)) {
            $query->where('gender', $gender);
        }

        $members = $query->orderBy('first_name')->get();

        $output = '<option value="">Select a guide</option>';
        foreach ($members as $member) {
            $name = htmlspecialchars($member->first_name . ' ' . $member->last_name);
            $output .= "<option value=\"{$member->tiu_member_id}\">{$name}</option>";
        }

        return response($output)->header('Content-Type', 'text/html');
    }
}
