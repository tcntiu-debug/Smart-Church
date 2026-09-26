<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ManageDepartmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display department list view with cascading filters, chart, and member table
     */
    public function listView()
    {
        $user = Auth::user();
        $campusId = $user->campus_id;

        $deptTypes = DB::table('department')
            ->whereIn('dept_type', ['Department', 'Cluster', 'House Fellowship'])
            ->where('campus_id', $campusId)
            ->distinct()
            ->orderBy('dept_type', 'asc')
            ->pluck('dept_type')
            ->toArray();

        $selectedDeptType = request('dept_type', '');
        $selectedDeptId = request('dept_id', '');

        return view('departments.list', compact('deptTypes', 'selectedDeptType', 'selectedDeptId'));
    }

    /**
     * AJAX: Get department names by type (cascading dropdown)
     */
    public function getDepartmentsByType(Request $request)
    {
        $deptType = $request->get('dept_type', '');
        if (empty($deptType)) {
            return response()->json([]);
        }

        $user = Auth::user();
        $campusId = $user->campus_id;

        $departments = DB::table('department')
            ->where('dept_type', $deptType)
            ->where('campus_id', $campusId)
            ->orderBy('dept_name', 'asc')
            ->select('dept_id', 'dept_name')
            ->get();

        return response()->json($departments);
    }

    /**
     * AJAX: Get chart data for department member distribution
     */
    public function getChartData(Request $request)
    {
        $deptType = $request->get('dept_type', '');
        if (empty($deptType)) {
            return response()->json(['labels' => [], 'data' => []]);
        }

        $user = Auth::user();
        $campusId = $user->campus_id;

        // Get all departments of this type for the user's campus
        $depts = DB::table('department')
            ->where('dept_type', $deptType)
            ->where('campus_id', $campusId)
            ->orderBy('dept_name', 'asc')
            ->select('dept_id', 'dept_name')
            ->get();

        if ($depts->isEmpty()) {
            return response()->json(['labels' => [], 'data' => []]);
        }

        // Determine which column in tiu_member
        $columnMap = [
            'Department' => 'department_name',
            'Cluster' => 'cluster',
            'House Fellowship' => 'house_fellowship',
        ];
        $columnToQuery = $columnMap[$deptType] ?? null;

        if (!$columnToQuery) {
            return response()->json([
                'labels' => $depts->pluck('dept_name')->toArray(),
                'data' => array_fill(0, $depts->count(), 0)
            ]);
        }

        // Initialize counts
        $counts = [];
        $deptNames = [];
        foreach ($depts as $d) {
            $counts[$d->dept_id] = 0;
            $deptNames[$d->dept_id] = $d->dept_name;
        }

        // Fetch members for the same campus and count
        $members = DB::table('tiu_member')
            ->select($columnToQuery)
            ->whereNotNull($columnToQuery)
            ->where($columnToQuery, '!=', '[]')
            ->where('campus_id', $campusId)
            ->get();

        foreach ($members as $member) {
            $jsonData = $member->{$columnToQuery};
            if (is_string($jsonData)) {
                $memberDeptIds = json_decode($jsonData, true);
                if (is_array($memberDeptIds)) {
                    foreach ($memberDeptIds as $deptId) {
                        if (array_key_exists($deptId, $counts)) {
                            $counts[$deptId]++;
                        }
                    }
                }
            }
        }

        return response()->json([
            'labels' => array_values($deptNames),
            'data' => array_values($counts)
        ]);
    }

    /**
     * AJAX: Fetch members by department type and department ID
     */
    public function fetchMembers(Request $request)
    {
        $deptType = $request->get('dept_type', '');
        $deptId = $request->get('dept_id', '');

        if (empty($deptType)) {
            return '<div class="ms-panel"><div class="ms-panel-body"><p class="text-center text-danger">Please select a department type.</p></div></div>';
        }

        $user = Auth::user();
        $campusId = $user->campus_id;

        // Determine column
        $columnMap = [
            'Department' => 'department_name',
            'Cluster' => 'cluster',
            'House Fellowship' => 'house_fellowship',
        ];
        $columnToSearch = $columnMap[$deptType] ?? null;

        if (!$columnToSearch) {
            return '<div class="ms-panel"><div class="ms-panel-body"><p class="text-center text-danger">Invalid department type selected.</p></div></div>';
        }

        // Get display name
        $deptNameForDisplay = 'All Departments';
        if (!empty($deptId) && $deptId !== 'All') {
            $deptNameForDisplay = DB::table('department')
                ->where('dept_id', $deptId)
                ->value('dept_name') ?? 'Unknown';
        }

        // Build query
        $query = DB::table('tiu_member')
            ->select('first_name', 'last_name', 'phone_number', 'gender', 'marital_status', 'occupation', 'residential_address');

        if (!empty($deptId) && $deptId !== 'All') {
            $query->where($columnToSearch, 'LIKE', '%"' . $deptId . '"%');
        } else {
            $query->whereNotNull($columnToSearch)
                  ->where($columnToSearch, '!=', '[]')
                  ->where($columnToSearch, '!=', '');
        }

        // Filter by the user's campus
        $query->where('campus_id', $campusId);

        $members = $query->orderBy('first_name', 'asc')
            ->orderBy('last_name', 'asc')
            ->get();

        // Render table
        $html = '<div class="ms-panel">';
        $html .= '<div class="ms-panel-header">';
        $html .= '<h6>Members in "' . e($deptNameForDisplay) . '" (' . e($deptType) . ')</h6>';
        $html .= '</div>';
        $html .= '<div class="ms-panel-body">';
        $html .= '<div class="table-responsive">';
        $html .= '<table class="table table-hover thead-primary data-table">';
        $html .= '<thead><tr>';
        $html .= '<th>Name</th><th>Phone</th><th>Gender</th><th>Marital Status</th><th>Occupation</th><th>Address</th>';
        $html .= '</tr></thead><tbody>';

        if ($members->count() > 0) {
            foreach ($members as $member) {
                $html .= '<tr>';
                $html .= '<td>' . e(trim($member->first_name . ' ' . $member->last_name)) . '</td>';
                $html .= '<td>' . e($member->phone_number ?? '') . '</td>';
                $html .= '<td>' . e($member->gender ?? '') . '</td>';
                $html .= '<td>' . e($member->marital_status ?? '') . '</td>';
                $html .= '<td>' . e($member->occupation ?? '') . '</td>';
                $html .= '<td>' . e($member->residential_address ?? '') . '</td>';
                $html .= '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="6" class="text-center">No members found matching this criteria.</td></tr>';
        }

        $html .= '</tbody></table>';
        $html .= '</div></div></div>';

        return $html;
    }

    /**
     * Display department management page (filtered by user's campus)
     */
    public function index()
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect('/aoverview')->with('error', 'Access Denied');
        }

        $campusId = $user->campus_id;

        $departments = DB::table('department')
            ->select('dept_id', 'dept_type', 'dept_name', 'dept_address', 'community_id', 'dept_lead', 'dept_lead_id')
            ->where('campus_id', $campusId)
            ->orderBy('dept_type', 'asc')
            ->orderBy('dept_name', 'asc')
            ->get();

        $communities = DB::table('communities')
            ->select('id', 'community_name')
            ->where('campus_id', $campusId)
            ->orderBy('community_name', 'asc')
            ->get();

        return view('departments.index', compact('departments', 'communities'));
    }

    /**
     * Store a new department (with user's campus_id)
     */
    public function store(Request $request)
    {
        $request->validate([
            'dept_name' => 'required|string|max:100',
            'dept_type' => 'required|string|in:House Fellowship,Department,Cluster',
        ]);

        $user = Auth::user();

        try {
            DB::table('department')->insert([
                'dept_name' => $request->dept_name,
                'dept_type' => $request->dept_type,
                'dept_address' => $request->dept_address ?? '',
                'community_id' => $request->community_id ? (int)$request->community_id : null,
                'campus_id' => $user->campus_id,
            ]);

            return redirect('/manage-departments')->with('success', 'Department added successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing department (only if it belongs to user's campus)
     */
    public function update(Request $request)
    {
        $request->validate([
            'dept_id' => 'required|integer',
            'dept_name' => 'required|string|max:100',
        ]);

        $user = Auth::user();

        try {
            $updated = DB::table('department')
                ->where('dept_id', $request->dept_id)
                ->where('campus_id', $user->campus_id)
                ->update([
                    'dept_name' => $request->dept_name,
                    'dept_address' => $request->dept_address ?? '',
                    'dept_lead' => $request->dept_lead ?? '',
                    'dept_lead_id' => $request->dept_lead_id ? (int)$request->dept_lead_id : null,
                    'community_id' => $request->community_id ? (int)$request->community_id : null,
                ]);

            if ($updated) {
                return redirect('/manage-departments')->with('success', 'Department updated successfully!');
            } else {
                return redirect('/manage-departments')->with('error', 'Department not found or not in your campus.');
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Delete a department (only if it belongs to user's campus)
     */
    public function destroy($id)
    {
        $user = Auth::user();

        try {
            $deleted = DB::table('department')
                ->where('dept_id', $id)
                ->where('campus_id', $user->campus_id)
                ->delete();

            if ($deleted) {
                return redirect('/manage-departments')->with('success', 'Department deleted!');
            } else {
                return redirect('/manage-departments')->with('error', 'Cannot delete: Department not found or not in your campus.');
            }
        } catch (\Exception $e) {
            return redirect('/manage-departments')->with('error', 'Cannot delete department: ' . $e->getMessage());
        }
    }

    /**
     * AJAX: Get members for a department type (for lead selection dropdown)
     */
    public function getMembers(Request $request)
    {
        $deptId = $request->input('dept_id', '');
        $deptType = $request->input('dept_type', '');

        if (empty($deptId) || empty($deptType)) {
            return response()->json([]);
        }

        // Determine which column in tiu_member to search
        $columnToSearch = '';
        switch ($deptType) {
            case 'Department':
                $columnToSearch = 'department_name';
                break;
            case 'Cluster':
                $columnToSearch = 'cluster';
                break;
            case 'House Fellowship':
                $columnToSearch = 'house_fellowship';
                break;
        }

        $members = [];
        if (!empty($columnToSearch)) {
            $searchTerm = '%"' . $deptId . '"%';
            $members = DB::table('tiu_member')
                ->where($columnToSearch, 'LIKE', $searchTerm)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->select('tiu_member_id', 'first_name', 'last_name')
                ->get()
                ->map(function ($m) {
                    return [
                        'member_id' => $m->tiu_member_id,
                        'full_name' => trim($m->first_name . ' ' . $m->last_name),
                    ];
                });
        }

        return response()->json($members);
    }
}
