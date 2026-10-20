<?php

namespace App\Http\Controllers;

use App\Services\SubgroupAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TouchpointController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display the My Touchpoint / My Groups page.
     *
     * Reads the logged-in member's 'subgroup' column, resolves each entry to a
     * subgroup (canonical "deptID::SubgroupName" or a legacy bare name), loads
     * that subgroup's members and displays them as panels.
     */
    public function index()
    {
        $user = Auth::user();
        $tiuMemberId = session('tiu_member_id');

        if (!$tiuMemberId) {
            return redirect('/mytask')->with('error', 'Member ID not found. Please log in again.');
        }

        // ──────────────────────────────────────────────
        // Read the subgroup column from the current member's record
        // ──────────────────────────────────────────────
        $member = DB::table('tiu_member')
            ->where('tiu_member_id', $tiuMemberId)
            ->select('subgroup', 'first_name', 'last_name')
            ->first();

        $myGroups = [];

        if ($member) {
            foreach (SubgroupAssignmentService::groupsForMember($member->subgroup) as $group) {
                // Members are stored either as "deptID::SubgroupName" entries or,
                // historically, as a bare subgroup name.
                $membersQuery = DB::table('tiu_member')
                    ->where('status', '!=', '2')
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->select('tiu_member_id', 'first_name', 'last_name', 'phone_number');

                SubgroupAssignmentService::scopeAssignedTo(
                    $membersQuery,
                    SubgroupAssignmentService::departmentIdForGroup($group) ?? 0,
                    $group->sub_group_name
                );

                $myGroups[] = [
                    'group_name' => $group->sub_group_name,
                    'lead_id'    => $group->lead_id ?? 0,
                    'members'    => $membersQuery->get(),
                ];
            }
        }

        $fullName = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));

        return view('touchpoint.index', compact('myGroups', 'fullName'));
    }


    /**
     * Helper: Render a member's name, handling URLs in the last_name field.
     * @param string $firstName
     * @param string $lastName
     * @return string HTML string
     */
    public static function renderMemberName($firstName, $lastName)
    {
        $safeFirstName = e(trim($firstName));
        $trimmedLastName = trim($lastName);

        if (strpos($trimmedLastName, 'https://') === 0 || strpos($trimmedLastName, 'http://') === 0) {
            $safeUrl = e($trimmedLastName);
            return $safeFirstName . ' - <a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">Click here</a>';
        }

        $safeLastName = e($trimmedLastName);
        return $safeFirstName . ' ' . $safeLastName;
    }
}
