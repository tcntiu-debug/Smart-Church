<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WeekListController extends Controller
{
    /**
     * Return the weekly first-timer list as JSON for the mobile app.
     * Mirrors App\Http\Controllers\WeekListController@index logic.
     */
    public function index(Request $request)
    {
        $userId = $request->user_id;

        $user = $userId
            ? DB::table('tiu_member')->where('tiu_member_id', $userId)->first()
            : null;

        $member_role = $user->member_role ?? 'Guest';
        $userCampusId = $user->campus_id ?? 0;

        // Only Workers, Admins, Super Users can access
        if (!in_array($member_role, ['Worker', 'Admin', 'Admins', 'Workers', 'Super User'])) {
            return response()->json(['success' => false, 'message' => 'Access Denied'], 403);
        }

        // Get filter parameters
        $start_date_filter = $request->get('start_date', '');
        $end_date_filter = $request->get('end_date', '');
        $guest_type_filter = $request->get('guest_type', '');
        $church_type_id_filter = $request->get('church_type_id', '');

        // Set default date range (current week: Sunday to Saturday)
        if (!$start_date_filter || !$end_date_filter) {
            $today = Carbon::today();
            $dayOfWeek = $today->dayOfWeek; // 0 = Sunday, 6 = Saturday
            $sunday = $today->copy()->subDays($dayOfWeek);
            $saturday = $sunday->copy()->addDays(6);

            $start_date_filter = $sunday->format('Y-m-d');
            $end_date_filter = $saturday->format('Y-m-d');
        }

        // Build the query
        $query = DB::table('first_timer as ft')
            ->leftJoin('church_type as ct', 'ft.church_type_id', '=', 'ct.id');

        // Campus filter - only show first timers from user's campus
        if ($member_role !== 'Super User' && !empty($userCampusId)) {
            $query->where('ft.campus_id', $userCampusId);
        }

        // Date range condition
        if ($start_date_filter && $end_date_filter) {
            $query->whereBetween('ft.register_date', [$start_date_filter . ' 00:00:00', $end_date_filter . ' 23:59:59']);
        }

        // Guest type filter
        if ($guest_type_filter) {
            $query->where('ft.attendant_type', $guest_type_filter);
        }

        // Church type filter
        if ($church_type_id_filter && $church_type_id_filter !== 'All') {
            $query->where('ft.church_type_id', $church_type_id_filter);
        }

        $rows = $query->select('ft.*', 'ct.church_type_name')
            ->orderBy('ft.register_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'start_date' => $start_date_filter,
            'end_date' => $end_date_filter,
            'data' => $rows,
        ]);
    }
}
