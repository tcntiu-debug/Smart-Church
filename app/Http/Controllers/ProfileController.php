<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\TiuMember;
use App\Models\Birthday;
use App\Models\Department;
use App\Models\Community;
use App\Models\SubGroup;
use App\Models\Occupation;
use App\Models\ChurchType;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $sessionRole = $user->member_role ?? 'Member';

        // Determine which user profile to load
        $tiuMemberId = $user->tiu_member_id ?? $user->id;
        if (($sessionRole == 'Super User' || $sessionRole == 'Admin') && $request->has('tiu_member_id')) {
            $tiuMemberId = $request->tiu_member_id;
        }

        // Fetch user data
        $tiuMember = TiuMember::find($tiuMemberId);
        if (!$tiuMember) {
            abort(404, 'User profile not found');
        }

        // Fetch departments
        $allDepartments = Department::where('dept_type', 'Department')
            ->orderBy('dept_name', 'asc')
            ->get(['dept_id as id', 'dept_name as name']);

        $allClusters = Department::where('dept_type', 'Cluster')
            ->orderBy('dept_name', 'asc')
            ->get(['dept_id as id', 'dept_name as name']);

        $allHouseFellowships = Department::where('dept_type', 'House Fellowship')
            ->orderBy('dept_name', 'asc')
            ->get(['dept_id as id', 'dept_name as name']);

        // Fetch communities (filtered by user's campus)
        $campusId = $user->campus_id;
        $communities = Community::where('campus_id', $campusId)
            ->orderBy('community_name', 'asc')
            ->get();

        // Fetch subgroups
        $subGroups = SubGroup::orderBy('sub_group_name', 'asc')->get();

        // Fetch occupations
        $occupations = Occupation::orderBy('occ_name', 'asc')->get();

        // Fetch campuses
        $campuses = DB::table('campus')->orderBy('cname', 'asc')->get();

        // Fetch church types
        $churchTypes = ChurchType::orderBy('church_type_name', 'asc')->get();

        // Fetch birthday
        $birthday = $tiuMember->birthday;
        $bday = '';
        $bmonth = '';
        if ($birthday && $birthday->birthday) {
            $parts = explode(' ', $birthday->birthday);
            if (count($parts) === 2) {
                $bday = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                $bmonth = $parts[1];
            }
        }

        // Parse JSON fields
        $selectedDepartmentIds = $tiuMember->department_name ?? [];
        $selectedClusterIds = $tiuMember->cluster ?? [];
        $selectedHouseFellowshipIds = $tiuMember->house_fellowship ?? [];
        $selectedSubgroupOversight = $tiuMember->oversight_extra1 ?? [];

        return view('profile.edit', compact(
            'tiuMember',
            'allDepartments',
            'allClusters',
            'allHouseFellowships',
            'communities',
            'subGroups',
            'occupations',
            'campuses',
            'churchTypes',
            'bday',
            'bmonth',
            'selectedDepartmentIds',
            'selectedClusterIds',
            'selectedHouseFellowshipIds',
            'selectedSubgroupOversight',
            'sessionRole'
        ));
    }

    public function update(Request $request)
    {
        try {
            DB::beginTransaction();

            $tiuMemberId = $request->tiu_member_id;
            $user = Auth::user();
            $sessionRole = $user->member_role ?? 'Member';

            // Permission check
            if ($sessionRole !== 'Super User' && $sessionRole !== 'Admin' && $user->tiu_member_id != $tiuMemberId) {
                return response()->json(['success' => false, 'message' => 'You do not have permission to edit this profile.'], 403);
            }

            // Get old department IDs for email notification
            $oldMember = TiuMember::find($tiuMemberId);
            $oldDeptIds = $oldMember->department_name ?? [];

            // Handle photo upload
            $picturePath = null;
            if ($request->hasFile('profile_photo')) {
                $photo = $request->file('profile_photo');
                $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg'];

                if (!in_array($photo->getMimeType(), $allowedMimes)) {
                    return response()->json(['success' => false, 'message' => 'Invalid file type. Only JPG and PNG allowed.'], 400);
                }

                if ($photo->getSize() > 5 * 1024 * 1024) {
                    return response()->json(['success' => false, 'message' => 'File is larger than 5MB.'], 400);
                }

                $uploadDir = public_path('display_photo/');
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $extension = $photo->getClientOriginalExtension();
                $uniqueFilename = uniqid('user_' . $tiuMemberId . '_', true) . '.' . $extension;
                $photo->move($uploadDir, $uniqueFilename);
                $picturePath = 'display_photo/' . $uniqueFilename;

                // Delete old photo if exists
                if ($oldMember->picture_part && file_exists(public_path($oldMember->picture_part))) {
                    unlink(public_path($oldMember->picture_part));
                }
            }

            // Process departments with automatic assignment rules
            $deptArray = $request->departments_json ?? [];
            if (!is_array($deptArray)) {
                $deptArray = [];
            }

            $churchTypeId = $request->church_type_id;
            $maritalStatus = $request->marital_status;
            $gender = $request->gender;

            // Auto-assignment rules
            if ($churchTypeId == 1 && $maritalStatus != 'Single' && $gender == 'Male') {
                if (!in_array('30', $deptArray)) $deptArray[] = '30';
            }
            if ($churchTypeId == 1 && $maritalStatus != 'Single' && $gender == 'Female') {
                if (!in_array('31', $deptArray)) $deptArray[] = '31';
            }
            if ($churchTypeId == 1 && $maritalStatus == 'Single') {
                if (!in_array('18', $deptArray)) $deptArray[] = '18';
            }
            if ($churchTypeId == 2) {
                if (!in_array('22', $deptArray)) $deptArray[] = '22';
            }
            if ($churchTypeId == 3) {
                if (!in_array('16', $deptArray)) $deptArray[] = '16';
            }
            if ($churchTypeId == 4) {
                if (!in_array('11', $deptArray)) $deptArray[] = '11';
            }

            // Process community
            $communityId = $request->community_id;
            $communityOther = '';
            if ($communityId === 'other' || $communityId == 33) {
                $communityOther = $request->community_other;
                $communityId = ($communityId == 33) ? 33 : null;
            } else {
                $communityId = filter_var($communityId, FILTER_VALIDATE_INT);
            }

            // Update TiuMember with proper JSON encoding
            $updateData = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone_number' => $request->phone_number,
                'email' => $request->email,
                'gender' => $request->gender,
                'marital_status' => $request->marital_status,
                'age' => $request->age,
                'community_id' => $communityId,
                'community_other' => $communityOther,
                'occupation' => $request->occupation,
                'campus_id' => $request->campus_id,
                'church_type_id' => $request->church_type_id,
                'department_name' => json_encode($deptArray),
                'cluster' => json_encode($request->clusters_json ?? []),
                'house_fellowship' => json_encode($request->house_fellowships_json ?? []),
                'residential_address' => $request->residential_address,
                'next_of_kin_name' => $request->next_of_kin_name,
                'next_of_kin_phone' => $request->next_of_kin_phone,
                'status' => $request->disable_user ?? '1'
            ];

            if ($picturePath) {
                $updateData['picture_part'] = $picturePath;
            }

            if (($sessionRole === 'Super User' || $sessionRole === 'Admin') && $request->has('member_role')) {
                $updateData['member_role'] = $request->member_role;
                $updateData['subgroup'] = $request->sub_group;
                $updateData['oversight_extra1'] = json_encode($request->sub_group_oversight ?? []);
            }

            TiuMember::where('tiu_member_id', $tiuMemberId)->update($updateData);

            // Update birthday
            if ($request->bday && $request->bmonth) {
                $fullName = $request->first_name . ' ' . $request->last_name;
                $birthdayString = $request->bday . ' ' . $request->bmonth;

                Birthday::updateOrCreate(
                    ['tiu_member_id' => $tiuMemberId],
                    ['Name' => $fullName, 'birthday' => $birthdayString]
                );
            }

            // Send email notification for department changes
            $addedIds = array_diff($deptArray, $oldDeptIds);
            $removedIds = array_diff($oldDeptIds, $deptArray);

            if (!empty($addedIds) || !empty($removedIds)) {
                $this->sendDepartmentNotification($request, $addedIds, $removedIds);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Profile Update Successful']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Profile update error: ' . $e->getMessage());
            Log::error('Profile update line: ' . $e->getLine());
            return response()->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage() . ' on line ' . $e->getLine()], 500);
        }
    }

    private function sendDepartmentNotification($request, $addedIds, $removedIds)
    {
        try {
            $fullName = $request->first_name . ' ' . $request->last_name;
            $emailBody = "Name: " . $fullName . "\n\n";

            if (!empty($addedIds)) {
                $emailBody .= "Has JOINED the following department(s):\n";
                $deptNames = Department::whereIn('dept_id', $addedIds)->pluck('dept_name');
                foreach ($deptNames as $name) {
                    $emailBody .= "- " . $name . "\n";
                }
                $emailBody .= "\n";
            }

            if (!empty($removedIds)) {
                $emailBody .= "Has LEFT the following department(s):\n";
                $deptNames = Department::whereIn('dept_id', $removedIds)->pluck('dept_name');
                foreach ($deptNames as $name) {
                    $emailBody .= "- " . $name . "\n";
                }
            }

            Log::info('Department change notification', ['body' => $emailBody]);
        } catch (\Exception $e) {
            Log::error('Failed to send department notification: ' . $e->getMessage());
        }
    }

    // ==========================================
    // API METHODS FOR MOBILE APP
    // ==========================================

    public function getApiProfile(Request $request)
    {
        try {
            // Try to get user_id from middleware first
            $userId = $request->user_id;

            // If not found, try to get from authenticated user
            if (!$userId && Auth::check()) {
                $user = Auth::user();
                $userId = $user->tiu_member_id ?? $user->id;
            }

            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
            }

            $user = TiuMember::with('birthday')->find($userId);

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            $birthday = null;
            if ($user->birthday) {
                $birthday = $user->birthday->birthday;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->tiu_member_id,
                    'first_name' => $user->first_name ?? '',
                    'last_name' => $user->last_name ?? '',
                    'email' => $user->email ?? '',
                    'phone_number' => $user->phone_number ?? '',
                    'gender' => $user->gender ?? '',
                    'marital_status' => $user->marital_status ?? '',
                    'age' => $user->age ?? '',
                    'occupation' => $user->occupation ?? '',
                    'residential_address' => $user->residential_address ?? '',
                    'next_of_kin_name' => $user->next_of_kin_name ?? '',
                    'next_of_kin_phone' => $user->next_of_kin_phone ?? '',
                    'member_role' => $user->member_role ?? '',
                    'subgroup' => $user->subgroup ?? '',
                    'church_type_id' => $user->church_type_id ?? '',
                    'community_id' => $user->community_id ?? '',
                    'community_other' => $user->community_other ?? '',
                    'campus_id' => $user->campus_id ?? '',
                    'picture_part' => $user->picture_part ? asset($user->picture_part) : null,
                    'birthday' => $birthday,
                    'departments' => $user->department_name ?? [],
                    'clusters' => $user->cluster ?? [],
                    'house_fellowships' => $user->house_fellowship ?? [],
                    'oversight_subgroups' => $user->oversight_extra1 ?? []
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Profile API error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateApiProfile(Request $request)
    {
        try {
            DB::beginTransaction();

            $userId = $request->user_id;

            if (!$userId) {
                $authUser = Auth::user();
                if ($authUser) {
                    $userId = $authUser->tiu_member_id ?? $authUser->id;
                }
            }

            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'User not authenticated'], 401);
            }

            $tiuMember = TiuMember::find($userId);
            if (!$tiuMember) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            // Handle photo upload
            $picturePath = null;
            if ($request->hasFile('profile_photo')) {
                $photo = $request->file('profile_photo');
                $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg'];

                if (!in_array($photo->getMimeType(), $allowedMimes)) {
                    return response()->json(['success' => false, 'message' => 'Invalid file type. Only JPG and PNG allowed.'], 400);
                }

                if ($photo->getSize() > 5 * 1024 * 1024) {
                    return response()->json(['success' => false, 'message' => 'File is larger than 5MB.'], 400);
                }

                $uploadDir = public_path('display_photo/');
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $extension = $photo->getClientOriginalExtension();
                $uniqueFilename = 'user_' . $userId . '_' . uniqid() . '.' . $extension;
                $photo->move($uploadDir, $uniqueFilename);
                $picturePath = 'display_photo/' . $uniqueFilename;

                if ($tiuMember->picture_part && file_exists(public_path($tiuMember->picture_part))) {
                    unlink(public_path($tiuMember->picture_part));
                }
            }

            // Process departments from JSON
            $deptArray = [];
            if ($request->departments) {
                $deptArray = json_decode($request->departments, true) ?: [];
            }

            // Update user data
            $updateData = [
                'first_name' => $request->first_name ?? $tiuMember->first_name,
                'last_name' => $request->last_name ?? $tiuMember->last_name,
                'phone_number' => $request->phone_number ?? $tiuMember->phone_number,
                'email' => $request->email ?? $tiuMember->email,
                'gender' => $request->gender ?? $tiuMember->gender,
                'marital_status' => $request->marital_status ?? $tiuMember->marital_status,
                'age' => $request->age ?? $tiuMember->age,
                'occupation' => $request->occupation ?? $tiuMember->occupation,
                'residential_address' => $request->residential_address ?? $tiuMember->residential_address,
                'next_of_kin_name' => $request->next_of_kin_name ?? $tiuMember->next_of_kin_name,
                'next_of_kin_phone' => $request->next_of_kin_phone ?? $tiuMember->next_of_kin_phone,
                'church_type_id' => $request->church_type_id ?? $tiuMember->church_type_id,
                'department_name' => json_encode($deptArray),
                'cluster' => $request->clusters ?? $tiuMember->cluster ?? json_encode([]),
                'house_fellowship' => $request->house_fellowships ?? $tiuMember->house_fellowship ?? json_encode([]),
            ];

            // Handle community
            $communityId = $request->community_id;
            if ($communityId === 'other') {
                $updateData['community_id'] = null;
                $updateData['community_other'] = $request->community_other ?? '';
            } elseif ($communityId) {
                $updateData['community_id'] = $communityId;
                $updateData['community_other'] = '';
            }

            if ($picturePath) {
                $updateData['picture_part'] = $picturePath;
            }

            // Admin-only fields
            $userRole = Auth::user()->member_role ?? 'Member';
            if ($userRole === 'Super User' || $userRole === 'Admin') {
                if ($request->has('member_role')) {
                    $updateData['member_role'] = $request->member_role;
                }
                if ($request->has('subgroup')) {
                    $updateData['subgroup'] = $request->subgroup;
                }
                if ($request->has('oversight_subgroups')) {
                    $updateData['oversight_extra1'] = json_encode($request->oversight_subgroups);
                }
                if ($request->has('status')) {
                    $updateData['status'] = $request->status;
                }
            }

            $tiuMember->update($updateData);

            // Update birthday
            if ($request->birthday) {
                Birthday::updateOrCreate(
                    ['tiu_member_id' => $userId],
                    ['Name' => ($request->first_name ?? $tiuMember->first_name) . ' ' . ($request->last_name ?? $tiuMember->last_name), 'birthday' => $request->birthday]
                );
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Profile updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Profile update error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getApiDepartments(Request $request)
    {
        try {
            $departments = Department::where('dept_type', 'Department')
                ->orderBy('dept_name', 'asc')
                ->get(['dept_id as id', 'dept_name as name']);

            $clusters = Department::where('dept_type', 'Cluster')
                ->orderBy('dept_name', 'asc')
                ->get(['dept_id as id', 'dept_name as name']);

            $houseFellowships = Department::where('dept_type', 'House Fellowship')
                ->orderBy('dept_name', 'asc')
                ->get(['dept_id as id', 'dept_name as name']);

            return response()->json([
                'success' => true,
                'data' => [
                    'departments' => $departments,
                    'clusters' => $clusters,
                    'house_fellowships' => $houseFellowships
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getApiCommunities(Request $request)
    {
        try {
            // Get the authenticated user's campus
            $user = Auth::user();
            $campusId = $user->campus_id;

            $communities = Community::where('campus_id', $campusId)
                ->orderBy('community_name', 'asc')
                ->get();

            return response()->json(['success' => true, 'data' => $communities]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getApiOccupations(Request $request)
    {
        try {
            $occupations = Occupation::orderBy('occ_name', 'asc')->get();
            return response()->json(['success' => true, 'data' => $occupations]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getApiChurchTypes(Request $request)
    {
        try {
            $churchTypes = ChurchType::orderBy('church_type_name', 'asc')->get();
            return response()->json(['success' => true, 'data' => $churchTypes]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getApiSubGroups(Request $request)
    {
        try {
            $subGroups = SubGroup::orderBy('sub_group_name', 'asc')->get();
            return response()->json(['success' => true, 'data' => $subGroups]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function parseJson($value)
    {
        if (empty($value)) return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
