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
    // MEMBER LISTING HELPERS
    // ============================================================

    /**
     * Base query for the member listing (same scope as legacy member-view.php).
     */
    private function memberBaseQuery($campusId)
    {
        return TiuMember::where('status', '!=', '3')->where('campus_id', $campusId);
    }

    /**
     * Apply the status dropdown filter ('All' means no extra filter).
     */
    private function applyStatusFilter($query, $statusFilter)
    {
        if ($statusFilter === null || $statusFilter === '' || $statusFilter === 'All') {
            return $query;
        }

        return $query->where('status', $statusFilter);
    }

    /**
     * Department id => name lookup (tiu_member.department_name holds a JSON array of ids).
     */
    private function departmentMap()
    {
        return Department::pluck('dept_name', 'dept_id')->toArray();
    }

    /**
     * Normalise a phone number for wa.me links (same logic as legacy member-view.php).
     */
    private function formatWhatsAppPhone($phoneNumber)
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phoneNumber);

        if ($digits === '') {
            return '';
        }

        return substr($digits, 0, 1) === '0' ? '234' . substr($digits, 1) : $digits;
    }

    /**
     * Activation reminder link shown next to members who never verified their account.
     */
    private function whatsAppReminderLink($waPhone)
    {
        $message = 'Compliments of the season. This is the covenant nation Ikorodu smart church app admin. We notice after you registered yesterday, you are yet to activate your account. Please reach out to us if you have any difficulties. Thanks';

        return 'https://wa.me/' . $waPhone . '?text=' . urlencode($message);
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
     * AJAX data source for the member table (DataTables server-side processing).
     *
     * Searching, sorting and paging all run in SQL, so the search box covers every
     * member - not just the rows currently displayed - and the length menu
     * (10 / 50 / 100 / All) only transfers the rows that are actually needed.
     */
    public function data(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->member_role ?? '', ['Super User', 'Admin'])) {
            return response()->json([
                'draw' => (int) $request->input('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Access Denied',
            ], 403);
        }

        $campusId = $user->campus_id ?? 0;
        $statusFilter = $request->input('status_filter', 'All');

        $recordsTotal = $this->applyStatusFilter($this->memberBaseQuery($campusId), $statusFilter)->count();

        // ---------------- global search ----------------
        $query = $this->applyStatusFilter($this->memberBaseQuery($campusId), $statusFilter);
        $search = trim((string) $request->input('search.value', ''));

        if ($search !== '') {
            $like = '%' . $search . '%';

            // Department names live in the `department` table while tiu_member only
            // stores ids, so a name match is translated into an id match as well.
            $matchedDeptIds = [];
            foreach ($this->departmentMap() as $deptId => $deptName) {
                if (stripos((string) $deptName, $search) !== false) {
                    $matchedDeptIds[] = (string) $deptId;
                }
            }

            $query->where(function ($q) use ($like, $matchedDeptIds) {
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('phone_number', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('gender', 'like', $like)
                    ->orWhere('marital_status', 'like', $like)
                    ->orWhere('occupation', 'like', $like)
                    ->orWhere('member_role', 'like', $like)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$like]);

                foreach ($matchedDeptIds as $matchedDeptId) {
                    $q->orWhere('department_name', 'like', '%"' . $matchedDeptId . '"%');
                }
            });
        }

        $recordsFiltered = $query->count();

        // ---------------- ordering ----------------
        // DataTables column names mapped onto the real (sortable) SQL columns.
        // Values are static SQL expressions (never user input) paired with a whitelisted direction.
        $sortable = [
            'no' => 'tiu_member_id',
            'name' => "CONCAT(first_name, ' ', last_name)",
            'phone' => 'phone_number',
            'email' => 'email',
            'gender' => 'gender',
            'marital_status' => 'marital_status',
            'occupation' => 'occupation',
            'role' => 'member_role',
            'status' => 'status',
            'register_date' => 'date_registered',
        ];

        $ordered = false;
        foreach ((array) $request->input('order', []) as $order) {
            $columnIndex = (int) ($order['column'] ?? -1);
            $columnName = $request->input('columns.' . $columnIndex . '.data');

            if ($columnName === null || !isset($sortable[$columnName])) {
                continue;
            }

            $direction = strtolower($order['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
            $query->orderByRaw($sortable[$columnName] . ' ' . $direction);
            $ordered = true;
        }

        if (!$ordered) {
            $query->orderBy('tiu_member_id', 'desc');
        }

        // ---------------- paging (length -1 / 0 = "All" in the length menu) ----------------
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);

        if ($length <= 0) {
            $length = max($recordsFiltered, 1);
        }

        $members = $query->skip($start)->take($length)->get();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $this->memberRows($members, $start, $user),
        ]);
    }

    /**
     * Build the DataTables rows for one page of members (markup mirrors the legacy table).
     * Values are escaped here because DataTables inserts cell data as HTML.
     */
    private function memberRows($members, $start, $user)
    {
        $deptMap = $this->departmentMap();
        $canDelete = ((int) ($user->tiu_member_id ?? 0) === 1);
        $rows = [];

        foreach ($members as $offset => $member) {
            $memberId = $member->tiu_member_id;
            $fullName = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));

            // Phone + WhatsApp activation reminder for members who never verified
            $phoneHtml = htmlspecialchars((string) $member->phone_number, ENT_QUOTES, 'UTF-8');
            $waPhone = $this->formatWhatsAppPhone($member->phone_number);
            if ((string) ($member->email_verified ?? 0) === '0' && $waPhone !== '') {
                $phoneHtml .= ' <a href="' . htmlspecialchars($this->whatsAppReminderLink($waPhone), ENT_QUOTES, 'UTF-8') . '"'
                    . ' target="_blank" title="Send Activation Reminder" style="margin-left:5px;">'
                    . '<i class="fab fa-whatsapp wa-icon"></i></a>';
            }

            $registerDate = '—';
            if (!empty($member->date_registered)) {
                try {
                    $registerDate = \Carbon\Carbon::parse($member->date_registered)->format('M d, Y');
                } catch (\Exception $e) {
                    $registerDate = '—';
                }
            }

            // department_name is already an array thanks to the TiuMember accessor
            $departmentIds = is_array($member->department_name)
                ? $member->department_name
                : (json_decode($member->department_name ?? '[]', true) ?: []);

            $departmentNames = [];
            foreach ((array) $departmentIds as $departmentId) {
                if (isset($deptMap[$departmentId])) {
                    $departmentNames[] = $deptMap[$departmentId];
                }
            }

            $rows[] = [
                'no' => $start + $offset + 1,
                'name' => htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'),
                'phone' => $phoneHtml,
                'email' => htmlspecialchars((string) ($member->email ?? ''), ENT_QUOTES, 'UTF-8'),
                'gender' => htmlspecialchars((string) ($member->gender ?? ''), ENT_QUOTES, 'UTF-8'),
                'marital_status' => htmlspecialchars((string) ($member->marital_status ?? ''), ENT_QUOTES, 'UTF-8'),
                'occupation' => htmlspecialchars((string) ($member->occupation ?? ''), ENT_QUOTES, 'UTF-8'),
                'department' => htmlspecialchars(implode(', ', $departmentNames), ENT_QUOTES, 'UTF-8'),
                'role' => htmlspecialchars((string) ($member->member_role ?? 'Member'), ENT_QUOTES, 'UTF-8'),
                'status' => ((string) $member->status === '1')
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-danger">Inactive</span>',
                'register_date' => $registerDate,
                'update' => '<a class="media fs-14 p-2" href="' . htmlspecialchars(url('/profile?full_name=' . urlencode($fullName) . '&tiu_member_id=' . $memberId), ENT_QUOTES, 'UTF-8') . '"><span><i class="fas fa-paper-plane text-success"></i> Update</span></a>',
                'lead' => '<a class="media fs-14 p-2" href="' . htmlspecialchars(url('/lead?full_name=' . urlencode($fullName) . '&tiu_member_id=' . $memberId . '&lead_phone=' . urlencode($member->phone_number ?? '')), ENT_QUOTES, 'UTF-8') . '"><span><i class="fa fa-eye" aria-hidden="true"></i> View</span></a>',
                'delete' => $canDelete
                    ? '<a class="media fs-14 p-2" href="' . url('/member/delete/' . $memberId) . '" onclick="return confirm(\'Are you sure you want to permanently delete this member?\');"><span><i class="fas fa-trash-alt text-danger"></i> Delete</span></a>'
                    : '',
            ];
        }

        return $rows;
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

        // Status filter (matches legacy member-view.php). The rows themselves are
        // fetched over AJAX by DataTables - see data() below.
        $statusFilter = $request->get('status_filter', 'All');

        // Count shown in the panel header badge; data() keeps it in sync while the
        // user searches, filters or changes the page length.
        $totalMembers = $this->applyStatusFilter($this->memberBaseQuery($campusId), $statusFilter)->count();

        // Pass signed-in user's ID for delete permission check (like legacy: only user ID 1 can delete)
        $currentUserId = $user->tiu_member_id;

        return view('member.index', compact('statusFilter', 'currentUserId', 'totalMembers'));
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

        // Needed by the view to turn department_name (JSON ids) into readable names
        $departments = $this->departmentMap();

        return view('member.show', compact('member', 'departments'));
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