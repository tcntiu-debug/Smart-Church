<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display General Settings page (view limit, church types, cohorts)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect('/aoverview')->with('error', 'Access Denied');
        }

        $update_message = '';
        $cohort_message = '';
        $church_type_message = '';

        // --- Update View Limit ---
        if ($request->isMethod('POST') && $request->has('update_view_limit')) {
            $request->validate(['data_limit' => 'required|integer|min:1']);
            $new_limit = $request->integer('data_limit');
            $existing = DB::table('first_time_view_limit')->first();
            if ($existing) {
                DB::table('first_time_view_limit')->where('id', $existing->id)->update(['data_limit' => $new_limit]);
            } else {
                DB::table('first_time_view_limit')->insert(['data_limit' => $new_limit]);
            }
            $update_message = 'View limit updated successfully.';
        }

        // --- Cohort & Church Type Management ---
        if ($request->isMethod('POST') && $request->has('action')) {
            $action = $request->input('action');

            if ($action === 'add_cohort') {
                $request->validate([
                    'cohort_name' => 'required|string|max:255',
                    'cohort_status' => 'required|in:Active,Inactive',
                ]);
                DB::table('fof_cohort_setting')->insert([
                    'cohort_name' => $request->cohort_name,
                    'cohort_status' => $request->cohort_status,
                ]);
                $cohort_message = 'Cohort added successfully.';

            } elseif ($action === 'edit_cohort') {
                $request->validate([
                    'cohort_id' => 'required|integer',
                    'edit_cohort_name' => 'required|string|max:255',
                    'edit_cohort_status' => 'required|in:Active,Inactive',
                ]);
                DB::table('fof_cohort_setting')
                    ->where('cohort_id', $request->integer('cohort_id'))
                    ->update([
                        'cohort_name' => $request->edit_cohort_name,
                        'cohort_status' => $request->edit_cohort_status,
                    ]);
                $cohort_message = 'Cohort updated successfully.';

            } elseif ($action === 'add_church_type') {
                $request->validate(['church_type_name' => 'required|string|max:255']);
                DB::table('church_type')->insert(['church_type_name' => $request->church_type_name]);
                $church_type_message = 'Church Type added successfully.';

            } elseif ($action === 'edit_church_type') {
                $request->validate([
                    'church_type_id' => 'required|integer',
                    'edit_church_type_name' => 'required|string|max:255',
                ]);
                $leadId = $request->input('church_type_lead_id') ?: null;
                DB::table('church_type')
                    ->where('id', $request->integer('church_type_id'))
                    ->update([
                        'church_type_name' => $request->edit_church_type_name,
                        'church_type_lead_id' => $leadId,
                    ]);
                $church_type_message = 'Church Type updated successfully.';
            }
        }

        // Fetch data for display
        $current_limit = DB::table('first_time_view_limit')->value('data_limit') ?? 60;
        $all_cohorts = DB::table('fof_cohort_setting')->orderBy('cohort_name')->get();
        $all_church_types = DB::table('church_type')->orderBy('church_type_name')->get();

        // Fetch leads from department table
        $all_leads = DB::select("
            SELECT DISTINCT t.tiu_member_id, t.first_name, t.last_name 
            FROM tiu_member t
            INNER JOIN department d ON t.tiu_member_id = d.dept_lead_id
            WHERE d.dept_lead_id > 0 AND d.dept_lead_id IS NOT NULL
            ORDER BY t.first_name ASC, t.last_name ASC
        ");

        return view('settings.index', compact(
            'current_limit', 'all_cohorts', 'all_church_types', 'all_leads',
            'update_message', 'cohort_message', 'church_type_message'
        ));
    }
}
