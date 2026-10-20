<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Services\SubgroupAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubGroupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display the subgroup management page
     */
    public function index()
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        $campusId = $user->campus_id ?? 1;

        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect('/mytask')->with('error', 'Access Denied');
        }

        // Fetch departments that support subgroup functionality
        $dept_ids = [15, 22, 23, 30, 31];
        $departments = DB::table('department')
            ->whereIn('dept_id', $dept_ids)
            ->orderBy('dept_name')
            ->select('dept_id', 'dept_name')
            ->get();

        return view('subgroup.index', compact('departments', 'campusId'));
    }

    /**
     * AJAX: Create a new subgroup
     */
    public function create(Request $request)
    {
        if (!in_array(Auth::user()->member_role, ['Super User', 'Admin'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized access.'], 403);
        }

        $departmentId = (int) $request->input('department_id', 0);
        $subGroupName = trim($request->input('sub_group_name', ''));

        if (empty($departmentId) || empty($subGroupName)) {
            return response()->json(['status' => 'error', 'message' => 'Both department and subgroup name are required.']);
        }

        $dept = DB::table('department')
            ->where('dept_id', $departmentId)
            ->first();

        if (!$dept) {
            return response()->json(['status' => 'error', 'message' => 'Department not found.']);
        }

        $campusId = Auth::user()->campus_id ?? 1;

        // Compare names, not ids: imported rows still have department_id = 0 and
        // are only identified by their legacy department_name.
        foreach (SubgroupAssignmentService::groupNamesForDepartment($departmentId, $campusId) as $existingName) {
            if (strcasecmp($existingName, $subGroupName) === 0) {
                return response()->json(['status' => 'error', 'message' => 'This subgroup already exists for the selected department.']);
            }
        }

        $row = ['sub_group_name' => $subGroupName];

        // Write every department shape the table has, so legacy readers (and a
        // deploy that has not picked up this code yet) still find the row.
        if (SubgroupAssignmentService::subGroupHasColumn('department_id')) {
            $row['department_id'] = $departmentId;
        }

        if (SubgroupAssignmentService::subGroupHasColumn('department_name')) {
            $row['department_name'] = $dept->dept_name;
        }

        if (SubgroupAssignmentService::subGroupHasColumn('campus_id')) {
            $row['campus_id'] = Campus::writeId($campusId);
        }

        DB::table('sub_group')->insert($row);

        return response()->json(['status' => 'success', 'message' => 'Subgroup created successfully!']);
    }

    /**
     * AJAX: Delete a subgroup
     */
    public function destroy(Request $request)
    {
        if (!in_array(Auth::user()->member_role, ['Super User', 'Admin'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized access.'], 403);
        }

        $subid = (int) $request->input('subid', 0);

        if (!$subid) {
            return response()->json(['status' => 'error', 'message' => 'Invalid Subgroup ID provided.']);
        }

        $deleted = DB::table('sub_group')->where('subid', $subid)->delete();

        if ($deleted > 0) {
            return response()->json(['status' => 'success', 'message' => 'Subgroup deleted successfully.']);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Subgroup not found or already deleted.']);
        }
    }

    // ===========================================================
    // FETCH ASSIGNMENT DATA
    // ===========================================================

    /**
     * AJAX: Fetch assignment data (returns HTML partial).
     * ALL departments use ONLY the 'subgroup' column.
     */
    public function fetchAssignmentData(Request $request)
    {
        $deptId = (int) $request->input('department', 0);
        $campusId = (int) $request->input('campus_id', 0);

        if (empty($deptId)) {
            return '<p class="text-danger">Invalid department selected.</p>';
        }

        if ($campusId === 0) {
            $campusId = Auth::user()->campus_id ?? 1;
        }

        $dept = DB::table('department')->where('dept_id', $deptId)->first();
        if (!$dept) {
            return '<p class="text-danger">Department not found.</p>';
        }
        $department = $dept->dept_name;

        $columnToCheck = 'subgroup';
        $deptPrefix = $deptId . '::';

        // Subgroups first: the unassigned list needs to know which legacy bare
        // names actually count as an assignment for this department.
        //
        // Read through the service: production still identifies these rows by
        // the legacy department_name column (department_id = 0), which a plain
        // where('department_id') silently skips.
        $groups = SubgroupAssignmentService::groupsForDepartment($deptId, $campusId);

        $groupNames = $groups->pluck('sub_group_name')->all();

        // -------------------------------------------------------
        // Fetch unassigned members (no assignment for this department)
        // -------------------------------------------------------
        $unassignedMembers = collect();

        $notAssignedClosure = function ($q) use ($deptPrefix) {
            $q->whereNull('subgroup')
              ->orWhere('subgroup', '')
              ->orWhere('subgroup', '[]')
              ->orWhere('subgroup', 'unassigned')
              ->orWhere('subgroup', 'NOT LIKE', '%"' . $deptPrefix . '%');
        };

        if ($deptId === 23) {
            // Department 23 – members with dept 23 in their department_name
            $unassignedMembers = DB::table('tiu_member')
                ->where('department_name', 'LIKE', '%"23"%')
                ->where($notAssignedClosure)
                ->where('status', '!=', '2')
                ->forCurrentCampus('campus_id', $campusId)
                ->select('tiu_member_id', 'first_name', 'last_name', 'subgroup')
                ->get();

        } elseif ($deptId === 22) {
            // Tracking & Integration – members with church_type 'Switch'
            $unassignedMembers = DB::table('tiu_member')
                ->join('church_type', 'tiu_member.church_type_id', '=', 'church_type.id')
                ->where('church_type.church_type_name', 'Switch')
                ->where($notAssignedClosure)
                ->where('tiu_member.status', '!=', '2')
                ->forCurrentCampus('tiu_member.campus_id', $campusId)
                ->select('tiu_member.tiu_member_id', 'tiu_member.first_name', 'tiu_member.last_name', 'tiu_member.subgroup')
                ->get();

        } elseif ($deptId === 30) {
            // Men's Fellowship – Male, Adult church type
            $unassignedMembers = DB::table('tiu_member')
                ->join('church_type', 'tiu_member.church_type_id', '=', 'church_type.id')
                ->where('tiu_member.gender', 'Male')
                ->where('church_type.church_type_name', 'Adult')
                ->where($notAssignedClosure)
                ->where('tiu_member.status', '!=', '2')
                ->forCurrentCampus('tiu_member.campus_id', $campusId)
                ->select('tiu_member.tiu_member_id', 'tiu_member.first_name', 'tiu_member.last_name', 'tiu_member.subgroup')
                ->get();

        } elseif ($deptId === 31) {
            // Women's Fellowship – Female, Adult church type
            $unassignedMembers = DB::table('tiu_member')
                ->join('church_type', 'tiu_member.church_type_id', '=', 'church_type.id')
                ->where('tiu_member.gender', 'Female')
                ->where('church_type.church_type_name', 'Adult')
                ->where($notAssignedClosure)
                ->where('tiu_member.status', '!=', '2')
                ->forCurrentCampus('tiu_member.campus_id', $campusId)
                ->select('tiu_member.tiu_member_id', 'tiu_member.first_name', 'tiu_member.last_name', 'tiu_member.subgroup')
                ->get();

        } elseif ($deptId === 15) {
            // Foundation of Faith – tiu_member records tagged with department 15
            $unassignedMembers = DB::table('tiu_member')
                ->where('department_name', 'LIKE', '%"15"%')
                ->where($notAssignedClosure)
                ->where('status', '!=', '2')
                ->forCurrentCampus('campus_id', $campusId)
                ->select('tiu_member_id', 'first_name', 'last_name', 'subgroup')
                ->get();
        } else {
            return '<p class="text-danger">Configuration error for this department.</p>';
        }

        // A bare legacy value ("Touch Point 2") is only an assignment when that
        // name is a subgroup of this department; otherwise the member stays here.
        $unassignedMembers = $unassignedMembers->filter(function ($member) use ($deptId, $groupNames) {
            return !SubgroupAssignmentService::isAssignedToAny($member->subgroup, $deptId, $groupNames);
        })->values();

        // -------------------------------------------------------
        // Render HTML
        // -------------------------------------------------------
        $html = '<div class="ms-panel">
            <div class="ms-panel-header">
                <h6>Assign Members for: ' . e($department) . '</h6>
            </div>
            <div class="ms-panel-body">
                <div class="row">
                    <div class="col-lg-6" style="border: 1px solid #ccc; margin-bottom: 10px; border-radius: 10px;">
                        <h4 class="section-title" style="color: #8B0A0A;">Members Not Yet Assigned</h4>
                        <ul class="ms-list ms-sortable-list" id="unassigned-list" data-update-column="subgroup">';

        $i = 1;
        foreach ($unassignedMembers as $member) {
            $nameHtml = $this->renderMemberName($member->first_name, $member->last_name);
            $displayMemberId = $member->tiu_member_id;
            $html .= '<li class="ms-list-item bordered media" data-member-id="' . $displayMemberId . '">
                <div class="media-body">
                    <h5>' . $i . '. ' . $nameHtml . '</h5>
                </div>
            </li>';
            $i++;
        }

        $html .= '</ul></div>';

        $html .= '<div class="col-lg-6"><div class="row">';

        foreach ($groups as $group) {
            $listId = str_replace(' ', '-', $group->sub_group_name);
            $html .= '<div class="col-md-12 subgroup-panel" id="subgroup-panel-' . $group->subid . '" style="border: 1px solid #ccc; margin-bottom: 10px; border-radius: 10px;">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="section-title" style="color: green;">' . e($group->sub_group_name) . '</h4>
                    <a href="#" class="delete-subgroup text-danger" data-subid="' . $group->subid . '" title="Delete this subgroup"><i class="fas fa-trash"></i></a>
                </div>
                <ul class="ms-list ms-droppable ms-sortable-list" id="' . e($listId) . '-list" data-update-column="subgroup" data-subgroup-name="' . e($group->sub_group_name) . '">';

            // Fetch assigned members: "deptID::SubgroupName" or a legacy bare name.
            $assignedMembers = SubgroupAssignmentService::scopeAssignedTo(
                DB::table('tiu_member')
                    ->where('status', '!=', '2')
                    ->forCurrentCampus('campus_id', $campusId),
                $deptId,
                $group->sub_group_name
            )
                ->select('tiu_member_id', 'first_name', 'last_name')
                ->get();

            $j = 1;
            foreach ($assignedMembers as $member) {
                $role = ($member->tiu_member_id == $group->lead_id) ? 'Lead' : 'Member';
                $rawFullName = trim($member->first_name . ' ' . $member->last_name);
                $nameHtml = $this->renderMemberName($member->first_name, $member->last_name);

                $html .= '<li class="ms-list-item bordered media" data-member-id="' . $member->tiu_member_id . '">
                    <div class="media-body d-flex justify-content-between align-items-center">
                        <span>' . $j . '. ' . $nameHtml . '</span>
                        <div>
                            <span class="badge badge-' . ($role === 'Lead' ? 'success' : 'light') . ' mr-2">' . $role . '</span>
                            <a href="#" class="promote-to-lead"
                               data-member-id="' . $member->tiu_member_id . '"
                               data-member-name="' . e($rawFullName) . '"
                               data-subgroup-id="' . $group->subid . '"
                               title="Promote to Lead">
                                <i class="fas fa-crown text-warning"></i>
                            </a>
                        </div>
                    </div>
                </li>';
                $j++;
            }

            $html .= '</ul></div>';
        }

        $html .= '</div></div></div></div></div>';

        return $html;
    }

    // ===========================================================
    // UPDATE ASSIGNMENT (drag-and-drop)
    // ===========================================================

    /**
     * AJAX: Update subgroup assignment via drag-and-drop.
     * ONLY writes to the 'subgroup' column. Stores JSON array of "deptID::SubgroupName" keys.
     */
    public function updateAssignment(Request $request)
    {
        $memberId = (int) $request->input('member_id', 0);
        $newSubgroup = trim($request->input('new_subgroup', ''));
        $departmentId = (int) $request->input('department_id', 0);

        if (empty($memberId)) {
            return response('Invalid Member ID.', 400);
        }

        if (empty($departmentId)) {
            return response('Invalid Department ID.', 400);
        }

        // Read current subgroup value
        $currentData = DB::table('tiu_member')
            ->where('tiu_member_id', $memberId)
            ->value('subgroup');

        // Replaces this department's entry (prefixed or legacy bare name) and
        // writes the canonical "deptID::SubgroupName" shape.
        $valueToSave = SubgroupAssignmentService::apply($currentData, $departmentId, $newSubgroup);

        DB::table('tiu_member')
            ->where('tiu_member_id', $memberId)
            ->update(['subgroup' => $valueToSave]);

        // Handle Foundation of Faith (dept 15) special case:
        // Ensure the member has department 15 in their department_name so they appear correctly.
        if ($departmentId == 15) {
            $member = DB::table('tiu_member')
                ->where('tiu_member_id', $memberId)
                ->first();

            if ($member) {
                $deptNames = [];
                if (!empty($member->department_name)) {
                    $decoded = json_decode($member->department_name, true);
                    if (is_array($decoded)) {
                        $deptNames = $decoded;
                    }
                }

                if (!in_array('15', $deptNames)) {
                    $deptNames[] = '15';
                    DB::table('tiu_member')
                        ->where('tiu_member_id', $memberId)
                        ->update(['department_name' => json_encode(array_values($deptNames))]);
                }
            }
        }

        return response("Update successful for member ID $memberId. subgroup set to '$valueToSave'.");
    }

    /**
     * AJAX: Promote a member to lead within a subgroup
     */
    public function promoteToLead(Request $request)
    {
        if (!in_array(Auth::user()->member_role, ['Super User', 'Admin'])) {
            return response()->json(['status' => 'error', 'message' => 'You do not have permission to perform this action.'], 403);
        }

        $memberId = (int) $request->input('tiu_member_id', 0);
        $subgroupId = (int) $request->input('sub_group_id', 0);

        if (!$memberId || !$subgroupId) {
            return response()->json(['status' => 'error', 'message' => 'Invalid member or subgroup ID provided.']);
        }

        DB::beginTransaction();

        try {
            $group = DB::table('sub_group')
                ->where('subid', $subgroupId)
                ->first();

            if (!$group) {
                DB::rollBack();

                return response()->json(['status' => 'error', 'message' => 'Subgroup not found.'], 404);
            }

            $currentLead = $group->lead_id;

            if ($currentLead) {
                DB::table('tiu_member')
                    ->where('tiu_member_id', $currentLead)
                    ->update(['member_role' => 'Member']);
            }

            $member = DB::table('tiu_member')
                ->where('tiu_member_id', $memberId)
                ->first();

            $memberUpdate = ['member_role' => 'Lead'];

            // Keep the assignment column in step with the panel the member now
            // leads, without pulling them out of a group they already belong to.
            if ($member) {
                $departmentId = SubgroupAssignmentService::departmentIdForGroup($group);

                // Imported rows may not name a real department; leave the
                // assignment untouched rather than writing a "0::Name" entry.
                if ($departmentId !== null) {
                    $assignedNames = SubgroupAssignmentService::namesForDepartment($member->subgroup, $departmentId);

                    if (empty($assignedNames) || in_array($group->sub_group_name, $assignedNames, true)) {
                        $memberUpdate['subgroup'] = SubgroupAssignmentService::apply(
                            $member->subgroup,
                            $departmentId,
                            $group->sub_group_name
                        );
                    }
                }
            }

            DB::table('tiu_member')
                ->where('tiu_member_id', $memberId)
                ->update($memberUpdate);

            DB::table('sub_group')
                ->where('subid', $subgroupId)
                ->update(['lead_id' => $memberId]);

            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Promotion successful! The view will now refresh.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'An error occurred during promotion: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Helper: Render member name (handles URLs in last name)
     */
    private function renderMemberName($firstName, $lastName)
    {
        $trimmedFirstName = trim($firstName);
        $trimmedLastName = trim($lastName);

        if (strpos($trimmedLastName, 'https://') === 0 || strpos($trimmedLastName, 'http://') === 0) {
            $safeFirstName = e($trimmedFirstName);
            $safeUrl = e($trimmedLastName);
            return $safeFirstName . ' <a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">click here</a>';
        } else {
            return e($trimmedFirstName . ' ' . $trimmedLastName);
        }
    }
}
