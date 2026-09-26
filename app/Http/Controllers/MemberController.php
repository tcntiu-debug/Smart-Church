<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\TiuMember;
use App\Models\Occupation;
use App\Models\Department;
use App\Models\ChurchType;
use App\Models\Campus;
use App\Models\SubGroup;

class MemberController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['publicRegister', 'publicStore']);
    }

    // ============================================================
    // PUBLIC REGISTRATION (no auth required - matches legacy member-register-others.php)
    // ============================================================

    /**
     * Show public registration form
     */
    public function publicRegister()
    {
        $churchTypes = ChurchType::orderBy('church_type_name', 'asc')->get();
        $communities = DB::table('communities')->orderBy('community_name', 'asc')->get(['id', 'community_name']);
        $campuses = DB::table('campus')->orderBy('cname', 'asc')->get();

        return view('member.public-register', compact('churchTypes', 'communities', 'campuses'));
    }

    /**
     * Store public member registration (AJAX - matches member-register-others.php logic)
     */
    public function publicStore(Request $request)
    {
        $first_name = trim($request->first_name ?? '');
        $last_name = trim($request->last_name ?? '');
        $phone_number = trim($request->phone_number ?? '');
        $email = trim($request->email ?? '');
        $gender = trim($request->gender ?? '');
        $residential_address = trim($request->residential_address ?? '');
        $campus_id = $request->campus_id;
        $church_type_id = $request->church_type_id;
        $community_id_input = $request->community_id;
        $community_other_input = trim($request->community_other ?? '');
        $parent_guardian_name = trim($request->parent_guardian_name ?? '');
        $parent_guardian_phone = trim($request->parent_guardian_phone ?? '');
        $parent_guardian_relationship = trim($request->parent_guardian_relationship ?? '');

        // Basic required fields
        if (empty($first_name) || empty($last_name) || empty($gender) || empty($residential_address) || empty($church_type_id) || empty($community_id_input) || empty($campus_id)) {
            return response()->json(['success' => false, 'message' => 'All required fields must be filled.'], 400);
        }

        // Church-type-specific validation (matching member-register-others.php)
        $churchTypeName = DB::table('church_type')->where('id', $church_type_id)->value('church_type_name');
        $isChildType = ($churchTypeName === 'Jesus Tribe' || $churchTypeName === 'Children');

        if ($isChildType) {
            // Email optional for Jesus Tribe/Children
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['success' => false, 'message' => 'Invalid email address format.'], 400);
            }
            if (!empty($phone_number) && !preg_match('/^\d{11}$/', $phone_number)) {
                return response()->json(['success' => false, 'message' => 'Phone number must be 11 digits.'], 400);
            }
        } else {
            // Email required for Adult/Switch
            if (empty($email)) {
                return response()->json(['success' => false, 'message' => 'Email address is required for this Church Type.'], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['success' => false, 'message' => 'Invalid email address.'], 400);
            }
            if (empty($phone_number)) {
                return response()->json(['success' => false, 'message' => 'Phone Number is required for this Church Type.'], 400);
            }
            if (!preg_match('/^\d{11}$/', $phone_number)) {
                return response()->json(['success' => false, 'message' => 'Phone number must be 11 digits.'], 400);
            }
        }

        // Check duplicate email in tiu_member (only if email was provided)
        if (!empty($email)) {
            $existingMember = DB::table('tiu_member')->where('email', $email)->first();
            if ($existingMember) {
                return response()->json(['success' => false, 'message' => 'An account with this email already exists.'], 400);
            }

            // Check duplicate email in first_timer
            $existingFt = DB::table('first_timer')->where('email', $email)->first();
            if ($existingFt) {
                return response()->json(['success' => false, 'message' => 'User already exists. Please go to the Login page and click Forgot Password to activate your account.'], 400);
            }
        }

        // Community logic - match legacy "Others" handling
        $final_community_id = null;
        $final_community_other = '';

        if ($community_id_input === 'Other' || $community_id_input == '33') {
            $final_community_id = 33;
            $final_community_other = $community_other_input;
        } else {
            $final_community_id = (int) $community_id_input;
        }

        // Generate random 8-char password
        $password = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 8);

        try {
            DB::table('tiu_member')->insert([
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone_number' => $phone_number,
                'email' => $email,
                'gender' => $gender,
                'residential_address' => $residential_address,
                'campus_id' => (int) $campus_id,
                'church_type_id' => $church_type_id,
                'community_id' => $final_community_id,
                'community_other' => $final_community_other,
                'parent_gaudian' => $parent_guardian_name,
                'Phone' => $parent_guardian_phone,
                'Relationship' => $parent_guardian_relationship,
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

            return response()->json([
                'success' => true,
                'message' => 'Registration successful! If an email was provided, please check it to activate your account.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Could not create account. Please try again.'], 500);
        }
    }

    /**
     * Show member registration form (admin only)
     */
    public function create()
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        
        // Only Super User and Admin can register new members
        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect('/aoverview')->with('error', 'Access Denied');
        }

        $occupations = Occupation::orderBy('occ_name', 'asc')->get();
        $departments = Department::orderBy('dept_name', 'asc')->get();
        $churchTypes = ChurchType::select('id', 'church_type_name')->distinct('church_type_name')->orderBy('church_type_name', 'asc')->get();
        $campuses = DB::table('campus')->orderBy('cname', 'asc')->get();
        $subGroups = SubGroup::orderBy('sub_group_name', 'asc')->get();
        $communities = DB::table('communities')->orderBy('community_name', 'asc')->get(['id', 'community_name']);

        return view('member.register', compact('occupations', 'departments', 'churchTypes', 'campuses', 'subGroups', 'communities'));
    }

    /**
     * Store new member
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        
        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect()->back()->with('error', 'Access Denied');
        }

        $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'phone_number' => 'required|string|max:20',
            'email' => 'nullable|email|unique:tiu_member,email',
            'gender' => 'nullable|string',
            'occupation_id' => 'nullable|integer',
            'church_type_id' => 'nullable|integer',
            'campus_id' => 'nullable|integer',
            'sub_group_id' => 'nullable|integer',
            'marital_status' => 'nullable|string',
            'address' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
        ]);

        try {
            // Handle photo upload
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photo = $request->file('photo');
                $filename = time() . '_' . $photo->getClientOriginalName();
                $photo->move(public_path('member_photos'), $filename);
                $photoPath = 'member_photos/' . $filename;
            }

            // Get occupation name if occupation_id is provided
            $occupationName = null;
            if ($request->occupation_id) {
                $occupation = Occupation::find($request->occupation_id);
                $occupationName = $occupation ? $occupation->occ_name : null;
            }

            // Process departments (convert array to JSON)
            $departmentIds = $request->department_name ?? [];
            $departmentJson = json_encode($departmentIds);

            // Get campus_id from user or request
            $campusId = $request->campus_id ?? Auth::user()->campus_id ?? 1;

            // Create new member
            $member = TiuMember::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone_number' => $request->phone_number,
                'email' => $request->email,
                'gender' => $request->gender,
                'occupation' => $occupationName,
                'church_type_id' => $request->church_type_id,
                'campus_id' => $campusId,
                'subgroup' => $request->sub_group_id,
                'marital_status' => $request->marital_status,
                'residential_address' => $request->address,
                'department_name' => $departmentJson,
                'picture_part' => $photoPath,
                'member_role' => 'Member',
                'status' => 1,
                'email_verified' => 1,
                'date_registered' => now(),
                'password' => Hash::make('password123'), // Default password - should be changed on first login
            ]);

            // Update birthday if provided
            if ($request->dob) {
                DB::table('birthday')->insert([
                    'tiu_member_id' => $member->tiu_member_id,
                    'birthday' => $request->dob,
                    'Name' => $request->first_name . ' ' . $request->last_name,
                ]);
            }

            return redirect()->route('member.index')->with('success', 'Member registered successfully! Default password is: password123');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Registration failed: ' . $e->getMessage());
        }
    }

    /**
     * List all members
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        $campusId = $user->campus_id ?? 0;
        
        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect('/aoverview')->with('error', 'Access Denied');
        }

        // Build query with optional status filter (matches legacy member-view.php)
        $query = TiuMember::where('status', '!=', '3')
            ->where('campus_id', $campusId)
            ->orderBy('tiu_member_id', 'desc');

        $statusFilter = $request->get('status_filter', 'All');
        if ($statusFilter !== 'All') {
            $query->where('status', $statusFilter);
        }

        $members = $query->paginate(50);

        // Get departments for name lookup (for view)
        $departments = Department::pluck('dept_name', 'dept_id')->toArray();

        // Pass signed-in user's ID for delete permission check (like legacy: only user ID 1 can delete)
        $currentUserId = $user->tiu_member_id;

        return view('member.index', compact('members', 'departments', 'statusFilter', 'currentUserId'));
    }

    /**
     * Show member details
     */
    public function show($id)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        
        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect('/aoverview')->with('error', 'Access Denied');
        }

        $member = TiuMember::findOrFail($id);
        return view('member.show', compact('member'));
    }

    /**
     * Activate a member
     */
    public function activate($id)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        
        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect()->back()->with('error', 'Access Denied');
        }

        TiuMember::where('tiu_member_id', $id)->update(['status' => 1]);
        return redirect()->back()->with('success', 'Member activated successfully');
    }

    /**
     * Delete a member
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        
        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect()->back()->with('error', 'Access Denied');
        }

        TiuMember::where('tiu_member_id', $id)->delete();
        return redirect()->route('member.index')->with('success', 'Member deleted successfully');
    }
}