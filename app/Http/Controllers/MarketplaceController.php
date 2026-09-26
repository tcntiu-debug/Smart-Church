<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MarketplaceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function admin()
    {
        $pendingApproval = DB::table('marketplace_businesses as mb')
            ->leftJoin('marketplace_categories as mc', 'mb.category_id', '=', 'mc.category_id')
            ->leftJoin('tiu_member as tm', 'mb.tiu_member_id', '=', 'tm.tiu_member_id')
            ->where('mb.status', 'pending_approval')
            ->select('mb.*', 'mc.category_name', 'tm.first_name', 'tm.last_name')
            ->orderBy('mb.created_at', 'desc')
            ->get();

        $pendingDeactivation = DB::table('marketplace_businesses as mb')
            ->leftJoin('marketplace_categories as mc', 'mb.category_id', '=', 'mc.category_id')
            ->leftJoin('tiu_member as tm', 'mb.tiu_member_id', '=', 'tm.tiu_member_id')
            ->where('mb.status', 'pending_deactivation')
            ->select('mb.*', 'mc.category_name', 'tm.first_name', 'tm.last_name')
            ->orderBy('mb.created_at', 'desc')
            ->get();

        $activeBusinesses = DB::table('marketplace_businesses as mb')
            ->leftJoin('marketplace_categories as mc', 'mb.category_id', '=', 'mc.category_id')
            ->leftJoin('tiu_member as tm', 'mb.tiu_member_id', '=', 'tm.tiu_member_id')
            ->where('mb.status', 'active')
            ->select('mb.*', 'mc.category_name', 'tm.first_name', 'tm.last_name')
            ->orderBy('mb.business_name', 'asc')
            ->get();

        return view('marketplace.admin', compact('pendingApproval', 'pendingDeactivation', 'activeBusinesses'));
    }

    public function myBusinesses(Request $request)
    {
        $userId = Auth::user()->tiu_member_id ?? Auth::user()->id;

        // Handle deactivation POST request
        if ($request->isMethod('post') && $request->has('deactivate_business')) {
            $businessId = (int) $request->input('business_id');
            DB::table('marketplace_businesses')
                ->where('business_id', $businessId)
                ->where('tiu_member_id', $userId)
                ->update(['status' => 'pending_deactivation', 'updated_at' => now()]);

            return redirect()->route('marketplace.my-businesses')->with('success', 'Deactivation requested!');
        }

        // Check if editing a business
        $editId = $request->get('edit');
        $businessToEdit = null;

        if ($editId) {
            $businessToEdit = DB::table('marketplace_businesses')
                ->where('business_id', $editId)
                ->where('tiu_member_id', $userId)
                ->first();
        }

        // Get all businesses for the user
        $businesses = DB::table('marketplace_businesses as mb')
            ->leftJoin('marketplace_categories as mc', 'mb.category_id', '=', 'mc.category_id')
            ->where('mb.tiu_member_id', $userId)
            ->select('mb.*', 'mc.category_name')
            ->orderBy('mb.created_at', 'desc')
            ->get();

        // Get all categories for dropdown
        $categories = DB::table('marketplace_categories')
            ->where('is_active', 1)
            ->orderBy('category_name', 'asc')
            ->get();

        $formMode = $businessToEdit ? 'edit' : 'create';

        return view('marketplace.my-businesses', compact('businesses', 'categories', 'businessToEdit', 'formMode'));
    }

    public function storeBusiness(Request $request)
    {
        $userId = Auth::user()->tiu_member_id ?? Auth::user()->id;
        $businessId = $request->business_id ?? 0;

        $request->validate([
            'business_name' => 'required|string|max:255',
            'business_details' => 'required|string',
            'category_id' => 'required|integer|exists:marketplace_categories,category_id',
            'contact_email' => 'nullable|email|max:100',
            'contact_phone' => 'nullable|string|max:50',
            'office_address' => 'nullable|string',
        ]);

        if ($businessId == 0) {
            // New business - require terms agreement
            $request->validate([
                'agreed_to_terms' => 'required|accepted',
            ]);

            DB::table('marketplace_businesses')->insert([
                'tiu_member_id' => $userId,
                'business_name' => $request->business_name,
                'business_details' => $request->business_details,
                'category_id' => $request->category_id,
                'contact_email' => $request->contact_email ?? '',
                'contact_phone' => $request->contact_phone ?? '',
                'office_address' => $request->office_address ?? '',
                'status' => 'pending_approval',
                'agreed_to_terms' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->route('marketplace.my-businesses')->with('success', 'Your new business has been submitted for approval!');
        } else {
            // Update existing business
            DB::table('marketplace_businesses')
                ->where('business_id', $businessId)
                ->where('tiu_member_id', $userId)
                ->update([
                    'business_name' => $request->business_name,
                    'business_details' => $request->business_details,
                    'category_id' => $request->category_id,
                    'contact_email' => $request->contact_email ?? '',
                    'contact_phone' => $request->contact_phone ?? '',
                    'office_address' => $request->office_address ?? '',
                    'updated_at' => now(),
                ]);

            return redirect()->route('marketplace.my-businesses')->with('success', 'Your business profile has been updated successfully!');
        }
    }

    public function approve(Request $request)
    {
        DB::table('marketplace_businesses')
            ->where('business_id', $request->business_id)
            ->update(['status' => 'active', 'updated_at' => now()]);

        return redirect()->back()->with('success', 'Business approved!');
    }

    public function deactivate(Request $request)
    {
        DB::table('marketplace_businesses')
            ->where('business_id', $request->business_id)
            ->update(['status' => 'inactive', 'updated_at' => now()]);

        return redirect()->back()->with('success', 'Business deactivated!');
    }

    public function requestDeactivation(Request $request)
    {
        $userId = Auth::user()->tiu_member_id ?? Auth::user()->id;
        $businessId = $request->business_id;

        DB::table('marketplace_businesses')
            ->where('business_id', $businessId)
            ->where('tiu_member_id', $userId)
            ->update(['status' => 'pending_deactivation', 'updated_at' => now()]);

        return redirect()->back()->with('success', 'Deactivation requested!');
    }

    public function purchase(Request $request)
    {
        $search = $request->get('search');
        $categoryId = $request->get('category');
        $campusId = $request->get('campus');

        $query = DB::table('marketplace_businesses as mb')
            ->leftJoin('marketplace_categories as mc', 'mb.category_id', '=', 'mc.category_id')
            ->leftJoin('tiu_member as tm', 'mb.tiu_member_id', '=', 'tm.tiu_member_id')
            ->where('mb.status', 'active')
            ->select(
                'mb.business_id',
                'mb.business_name',
                'mb.business_details',
                'mb.contact_email',
                'mb.contact_phone',
                'mb.office_address',
                'mc.category_id',
                'mc.category_name',
                'tm.tiu_member_id',
                'tm.first_name',
                'tm.last_name',
                'tm.phone_number as owner_phone',
                'tm.department_name',
                'tm.member_role',
                'tm.campus_id'
            );

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('mb.business_name', 'like', "%{$search}%")
                    ->orWhere('mb.business_details', 'like', "%{$search}%");
            });
        }

        // Apply category filter
        if ($categoryId) {
            $query->where('mb.category_id', $categoryId);
        }

        // Apply campus filter
        if ($campusId) {
            $query->where('tm.campus_id', $campusId);
        }

        $businesses = $query->orderBy('mc.category_name', 'asc')
            ->orderBy('mb.business_name', 'asc')
            ->paginate(10);

        // Get all categories for filter dropdown
        $categories = DB::table('marketplace_categories')
            ->where('is_active', 1)
            ->orderBy('category_name', 'asc')
            ->get();

        // Get all campuses for filter dropdown
        $campuses = DB::table('campus')
            ->orderBy('cname', 'asc')
            ->get();

        // Get department names for each business owner
        $allDepartments = DB::table('department')
            ->pluck('dept_name', 'dept_id')
            ->toArray();

        foreach ($businesses as $business) {
            // Parse department JSON
            $deptIds = json_decode($business->department_name ?? '[]', true);
            $deptNames = [];
            if (is_array($deptIds)) {
                foreach ($deptIds as $deptId) {
                    if (isset($allDepartments[$deptId])) {
                        $deptNames[] = $allDepartments[$deptId];
                    }
                }
            }
            $business->department_names = !empty($deptNames) ? implode(', ', $deptNames) : 'None';
            $business->owner_full_name = trim(($business->first_name ?? '') . ' ' . ($business->last_name ?? ''));
        }

        // Group by category name
        $grouped = $businesses->groupBy('category_name');

        return view('marketplace.purchase', compact('grouped', 'categories', 'campuses', 'businesses'));
    }
}
