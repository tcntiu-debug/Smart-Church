<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChartController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display the Bar Charts Report page.
     */
    public function barcharts(Request $request)
    {
        $user = Auth::user();
        $campusId = $user->campus_id;

        // Determine date range
        $startDate = $request->input('start_date', date('Y-m-01', strtotime('last month')));
        $endDate   = $request->input('end_date', date('Y-m-t', strtotime('last month')));
        $churchTypeFilterId = $request->input('church_type_id', '');

        // Fetch distinct labels for legends
        $genderGroups          = $this->fetchDistinctValues('gender');
        $ageGroups             = $this->fetchDistinctValues('age');
        $occupationGroups      = $this->fetchDistinctValues('occupation');
        $attendantTypeGroups   = $this->fetchDistinctValues('attendant_type');
        $churchTypeGroups      = $this->fetchChurchTypes();
        $statusGroups          = $this->fetchDistinctValues('status');
        $maritalStatusGroups   = $this->fetchDistinctValues('marital_status');
        $howDidYouHearGroups   = $this->fetchDistinctValues('how_did_you_hear');
        $bornAgainGroups       = $this->fetchDistinctValues('born_again');
        $waterBaptismGroups    = $this->fetchDistinctValues('water_baptism');
        $holyGhostBaptismGroups = $this->fetchDistinctValues('holy_ghost_baptism');

        // Build conditions for main query
        $conditions = [];
        $bindings   = [];
        $conditions[] = "ft.campus_id = ?";
        $bindings[] = (int) $campusId;
        $conditions[] = "DATE(ft.register_date) BETWEEN ? AND ?";
        $bindings[] = $startDate;
        $bindings[] = $endDate;

        if (!empty($churchTypeFilterId) && $churchTypeFilterId !== 'All') {
            $conditions[] = "ft.church_type_id = ?";
            $bindings[]   = (int) $churchTypeFilterId;
        }

        $whereClause = implode(' AND ', $conditions);

        $sql = "
            SELECT 
                YEAR(ft.register_date) AS year, 
                MONTH(ft.register_date) AS month, 
                ft.gender, ft.age, ft.occupation, ft.attendant_type, 
                ct.church_type_name, 
                ft.status, ft.marital_status, ft.how_did_you_hear, 
                ft.born_again, ft.water_baptism, ft.holy_ghost_baptism, 
                COUNT(ft.first_timer_id) AS count 
            FROM first_timer ft
            LEFT JOIN church_type ct ON ft.church_type_id = ct.id
            WHERE {$whereClause}
            GROUP BY year, month, ft.gender, ft.age, ft.occupation, ft.attendant_type, 
                     ct.church_type_name, ft.status, ft.marital_status, ft.how_did_you_hear, 
                     ft.born_again, ft.water_baptism, ft.holy_ghost_baptism
            ORDER BY year, month
        ";

        $rows = DB::select($sql, $bindings);

        $months = [];
        $genderData = []; $ageData = []; $occupationData = [];
        $attendantTypeData = []; $churchTypeData = []; $statusData = [];
        $maritalStatusData = []; $howDidYouHearData = []; $bornAgainData = [];
        $waterBaptismData = []; $holyGhostBaptismData = [];

        foreach ($rows as $row) {
            $monthLabel = date('M Y', strtotime($row->year . '-' . $row->month . '-01'));
            if (!in_array($monthLabel, $months)) {
                $months[] = $monthLabel;
            }
            if (!empty($row->gender))              $genderData[$monthLabel][$row->gender] = ($genderData[$monthLabel][$row->gender] ?? 0) + $row->count;
            if (!empty($row->age))                  $ageData[$monthLabel][$row->age] = ($ageData[$monthLabel][$row->age] ?? 0) + $row->count;
            if (!empty($row->occupation))           $occupationData[$monthLabel][$row->occupation] = ($occupationData[$monthLabel][$row->occupation] ?? 0) + $row->count;
            if (!empty($row->attendant_type))       $attendantTypeData[$monthLabel][$row->attendant_type] = ($attendantTypeData[$monthLabel][$row->attendant_type] ?? 0) + $row->count;
            if (!empty($row->church_type_name))     $churchTypeData[$monthLabel][$row->church_type_name] = ($churchTypeData[$monthLabel][$row->church_type_name] ?? 0) + $row->count;
            if (!empty($row->status))               $statusData[$monthLabel][$row->status] = ($statusData[$monthLabel][$row->status] ?? 0) + $row->count;
            if (!empty($row->marital_status))       $maritalStatusData[$monthLabel][$row->marital_status] = ($maritalStatusData[$monthLabel][$row->marital_status] ?? 0) + $row->count;
            if (!empty($row->how_did_you_hear))     $howDidYouHearData[$monthLabel][$row->how_did_you_hear] = ($howDidYouHearData[$monthLabel][$row->how_did_you_hear] ?? 0) + $row->count;
            if (!empty($row->born_again))           $bornAgainData[$monthLabel][$row->born_again] = ($bornAgainData[$monthLabel][$row->born_again] ?? 0) + $row->count;
            if (!empty($row->water_baptism))        $waterBaptismData[$monthLabel][$row->water_baptism] = ($waterBaptismData[$monthLabel][$row->water_baptism] ?? 0) + $row->count;
            if (!empty($row->holy_ghost_baptism))   $holyGhostBaptismData[$monthLabel][$row->holy_ghost_baptism] = ($holyGhostBaptismData[$monthLabel][$row->holy_ghost_baptism] ?? 0) + $row->count;
        }

        // Year-over-Year Comparison
        $compY1   = $request->input('comp_y1', date('Y', strtotime('-1 year')));
        $compY2   = $request->input('comp_y2', date('Y'));
        $compM    = $request->input('comp_m', date('n'));
        $compType = $request->input('comp_type', 'month');

        $yearsFound = $this->getAvailableYears($campusId);

        if ($compType === 'year') {
            $totalY1 = $this->getCompTotalYear($compY1, $campusId);
            $totalY2 = $this->getCompTotalYear($compY2, $campusId);
            $compMonthName = null;
            $comparisonTitle = "Full Year Comparison: {$compY1} vs {$compY2}";
            $chartDatasetLabel = "Total First Timers (Full Year)";
            $chartLabels = [$compY1, $compY2];
            $summaryText = "<strong>{$compY1} total: {$totalY1}</strong> &nbsp;|&nbsp; <strong>{$compY2} total: {$totalY2}</strong>";
        } else {
            $totalY1 = $this->getCompTotalMonth($compY1, $compM, $campusId);
            $totalY2 = $this->getCompTotalMonth($compY2, $compM, $campusId);
            $compMonthName = date("F", mktime(0, 0, 0, $compM, 10));
            $comparisonTitle = "Total First Timers Comparison: {$compMonthName} ({$compY1} vs {$compY2})";
            $chartDatasetLabel = "Total First Timers in {$compMonthName}";
            $chartLabels = [$compY1, $compY2];
            $summaryText = "<strong>{$compY1} ({$compMonthName}): {$totalY1}</strong> &nbsp;|&nbsp; <strong>{$compY2} ({$compMonthName}): {$totalY2}</strong>";
        }

        // Fetch church types for the filter dropdown
        $churchTypes = DB::table('church_type')->orderBy('church_type_name')->get();
        $currentChurchFilter = $churchTypeFilterId;

        return view('charts.barcharts', compact(
            'startDate', 'endDate', 'churchTypes', 'currentChurchFilter',
            'months',
            'genderGroups', 'ageGroups', 'occupationGroups', 'attendantTypeGroups',
            'churchTypeGroups', 'statusGroups', 'maritalStatusGroups', 'howDidYouHearGroups',
            'bornAgainGroups', 'waterBaptismGroups', 'holyGhostBaptismGroups',
            'genderData', 'ageData', 'occupationData', 'attendantTypeData',
            'churchTypeData', 'statusData', 'maritalStatusData', 'howDidYouHearData',
            'bornAgainData', 'waterBaptismData', 'holyGhostBaptismData',
            // Comparison
            'compY1', 'compY2', 'compM', 'compType',
            'yearsFound',
            'totalY1', 'totalY2', 'compMonthName',
            'comparisonTitle', 'chartDatasetLabel', 'chartLabels', 'summaryText'
        ));
    }

    /**
     * Display the Pie Charts Report page.
     */
    public function piecharts(Request $request)
    {
        $user = Auth::user();
        $campusId = $user->campus_id;

        $startDate = $request->input('start_date', date('Y-m-01', strtotime('last month')));
        $endDate   = $request->input('end_date', date('Y-m-t', strtotime('last month')));
        $churchTypeFilterId = $request->input('church_type_id', '');

        // Build conditions
        $conditions = [];
        $bindings   = [];
        $conditions[] = "ft.campus_id = ?";
        $bindings[] = (int) $campusId;
        $conditions[] = "DATE(ft.register_date) BETWEEN ? AND ?";
        $bindings[] = $startDate;
        $bindings[] = $endDate;

        if (!empty($churchTypeFilterId) && $churchTypeFilterId !== 'All') {
            $conditions[] = "ft.church_type_id = ?";
            $bindings[]   = (int) $churchTypeFilterId;
        }

        $whereClause = "WHERE " . implode(' AND ', $conditions);

        // Fetch data for all charts
        $genderData        = $this->fetchChartPieData('gender', $whereClause, $bindings);
        $ageData           = $this->fetchChartPieData('age', $whereClause, $bindings);
        $attendantData     = $this->fetchAttendantTypeData($whereClause, $bindings);
        $occupationData    = $this->fetchChartPieData('occupation', $whereClause, $bindings);
        $churchData        = $this->fetchChurchTypePieData($whereClause, $bindings);
        $statusData        = $this->fetchChartPieData('status', $whereClause, $bindings);
        $maritalStatusData = $this->fetchChartPieData('marital_status', $whereClause, $bindings);
        $howDidYouHearData = $this->fetchChartPieData('how_did_you_hear', $whereClause, $bindings);
        $bornAgainData     = $this->fetchChartPieData('born_again', $whereClause, $bindings);
        $waterBaptismData  = $this->fetchChartPieData('water_baptism', $whereClause, $bindings);
        $holyGhostBaptismData = $this->fetchChartPieData('holy_ghost_baptism', $whereClause, $bindings);

        // Fetch church types for filter dropdown
        $churchTypes = DB::table('church_type')->orderBy('church_type_name')->get();
        $currentChurchFilter = $churchTypeFilterId;

        return view('charts.piecharts', compact(
            'startDate', 'endDate', 'churchTypes', 'currentChurchFilter',
            'genderData', 'ageData', 'attendantData', 'occupationData',
            'churchData', 'statusData', 'maritalStatusData', 'howDidYouHearData',
            'bornAgainData', 'waterBaptismData', 'holyGhostBaptismData'
        ));
    }

    // ─── Helper Methods ────────────────────────────────────────────────────

    private function fetchDistinctValues($column)
    {
        $rows = DB::select("SELECT DISTINCT `{$column}` AS val FROM first_timer WHERE `{$column}` IS NOT NULL AND `{$column}` != '' ORDER BY `{$column}` ASC");
        return array_map(fn($r) => $r->val, $rows);
    }

    private function fetchChurchTypes()
    {
        $rows = DB::select("SELECT church_type_name FROM church_type ORDER BY church_type_name ASC");
        return array_map(fn($r) => $r->church_type_name, $rows);
    }

    private function getCompTotalMonth($yr, $mon, $campusId = null)
    {
        $yr = (int) $yr;
        $mon = (int) $mon;
        if ($campusId) {
            $result = DB::selectOne("SELECT COUNT(first_timer_id) as total FROM first_timer WHERE campus_id = ? AND YEAR(register_date) = ? AND MONTH(register_date) = ?", [$campusId, $yr, $mon]);
        } else {
            $result = DB::selectOne("SELECT COUNT(first_timer_id) as total FROM first_timer WHERE YEAR(register_date) = ? AND MONTH(register_date) = ?", [$yr, $mon]);
        }
        return $result->total ?? 0;
    }

    private function getCompTotalYear($yr, $campusId = null)
    {
        $yr = (int) $yr;
        if ($campusId) {
            $result = DB::selectOne("SELECT COUNT(first_timer_id) as total FROM first_timer WHERE campus_id = ? AND YEAR(register_date) = ?", [$campusId, $yr]);
        } else {
            $result = DB::selectOne("SELECT COUNT(first_timer_id) as total FROM first_timer WHERE YEAR(register_date) = ?", [$yr]);
        }
        return $result->total ?? 0;
    }

    private function getAvailableYears($campusId = null)
    {
        if ($campusId) {
            $rows = DB::select("SELECT DISTINCT YEAR(register_date) as yr FROM first_timer WHERE campus_id = ? ORDER BY yr DESC", [$campusId]);
        } else {
            $rows = DB::select("SELECT DISTINCT YEAR(register_date) as yr FROM first_timer ORDER BY yr DESC");
        }
        $years = array_map(fn($r) => $r->yr, $rows);
        if (empty($years)) {
            $years = [date('Y'), date('Y') - 1];
        }
        return $years;
    }

    private function fetchChartPieData($column, $whereClause, $bindings)
    {
        $sql = "SELECT `{$column}` AS label, COUNT(*) AS count FROM first_timer ft {$whereClause} AND `{$column}` IS NOT NULL AND `{$column}` != '' GROUP BY `{$column}`";
        $rows = DB::select($sql, $bindings);
        $data = [];
        foreach ($rows as $row) {
            $data[$row->label] = (int) $row->count;
        }
        return $data;
    }

    private function fetchAttendantTypeData($whereClause, $bindings)
    {
        $sql = "
            SELECT
                CASE
                    WHEN attendant_type = 'New To TCN (Will Join TCN)' THEN 'Will Join'
                    WHEN attendant_type = 'New To TCN (May Join TCN)' THEN 'May Join'
                    WHEN attendant_type = 'TCN Member (Relocating to Ikd)' THEN 'TCN Relocate'
                    WHEN attendant_type = 'TCN Member (In Transit)' THEN 'TCN Transit'
                    ELSE attendant_type
                END AS label,
                COUNT(*) AS count
            FROM first_timer ft
            {$whereClause} AND ft.attendant_type IS NOT NULL AND ft.attendant_type != ''
            GROUP BY label
        ";
        $rows = DB::select($sql, $bindings);
        $data = [];
        foreach ($rows as $row) {
            $data[$row->label] = (int) $row->count;
        }
        return $data;
    }

    private function fetchChurchTypePieData($whereClause, $bindings)
    {
        $sql = "
            SELECT ct.church_type_name AS label, COUNT(ft.first_timer_id) AS count 
            FROM first_timer ft
            JOIN church_type ct ON ft.church_type_id = ct.id
            {$whereClause}
            GROUP BY ct.church_type_name
        ";
        $rows = DB::select($sql, $bindings);
        $data = [];
        foreach ($rows as $row) {
            $data[$row->label] = (int) $row->count;
        }
        return $data;
    }
}
