<?php

namespace App\Http\Controllers;

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
     * Reads the logged-in member's 'subgroup' JSON array column,
     * parses each "deptID::SubgroupName" entry, looks up the subgroup
     * details and members, and displays them as panels.
     */
    public function index()
    {
        $user = Auth::user();
        $tiuMemberId = session('tiu_member_id');

        if (!$tiuMemberId) {
            return redirect('/mytask')->with('error', 'Member ID not found. Please log in again.');
        }

        // ──────────────────────────────────────────────
        // Read the subgroup JSON array from the current member's record
        // ──────────────────────────────────────────────
        $member = DB::table('tiu_member')
            ->where('tiu_member_id', $tiuMemberId)
            ->select('subgroup', 'first_name', 'last_name')
            ->first();

        $myGroups = [];

        if ($member && !empty($member->subgroup)) {
            $subgroupArray = json_decode($member->subgroup, true);

            if (is_array($subgroupArray)) {
                foreach ($subgroupArray as $entry) {
                    // Entry format: "deptID::SubgroupName" e.g. "15::Touch Point 3"
                    $parts = explode('::', $entry, 2);
                    if (count($parts) < 2) {
                        continue;
                    }

                    $deptId = $parts[0];
                    $subGroupName = $parts[1];

                    // Look up the subgroup details from sub_group table
                    $group = DB::table('sub_group')
                        ->where('department_id', $deptId)
                        ->where('sub_group_name', $subGroupName)
                        ->first();

                    if (!$group) {
                        continue;
                    }

                    // Find all members with the same "deptID::SubgroupName" entry in their subgroup column
                    $members = DB::table('tiu_member')
                        ->where('subgroup', 'LIKE', '%"' . $entry . '"%')
                        ->where('status', '!=', '2')
                        ->orderBy('first_name')
                        ->orderBy('last_name')
                        ->select('tiu_member_id', 'first_name', 'last_name', 'phone_number')
                        ->get();

                    $myGroups[] = [
                        'group_name' => $subGroupName,
                        'lead_id'    => $group->lead_id ?? 0,
                        'members'    => $members,
                    ];
                }
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
