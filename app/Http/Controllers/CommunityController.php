<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommunityController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display community management page (filtered by user's campus)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect('/aoverview')->with('error', 'Access Denied');
        }

        $campusId = $user->campus_id;

        $editId = $request->input('edit_id');
        $isEditMode = !empty($editId) && is_numeric($editId);
        $communityData = null;

        // Fetch communities for the user's campus only
        $allCommunities = DB::table('communities')
            ->select('id', 'community_name', 'latitude', 'longitude', 'campus_id')
            ->where('campus_id', $campusId)
            ->orderBy('community_name', 'asc')
            ->get();

        // If in edit mode, fetch the community data (only if it belongs to user's campus)
        if ($isEditMode) {
            $communityData = DB::table('communities')
                ->where('id', $editId)
                ->where('campus_id', $campusId)
                ->select('community_name', 'latitude', 'longitude')
                ->first();

            if (!$communityData) {
                return redirect('/config-community')->with('error', 'Community not found or not in your campus.');
            }
        }

        return view('community.index', compact('allCommunities', 'isEditMode', 'communityData', 'editId'));
    }

    /**
     * Store or update a community (with user's campus_id)
     */
    public function store(Request $request)
    {
        $request->validate([
            'community_name' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $user = Auth::user();
        $campusId = $user->campus_id;
        $communityId = $request->input('community_id');
        $communityName = trim($request->community_name);
        $latitude = $request->latitude;
        $longitude = $request->longitude;

        try {
            if (!empty($communityId) && is_numeric($communityId)) {
                // UPDATE existing record (only if it belongs to user's campus)
                $updated = DB::table('communities')
                    ->where('id', $communityId)
                    ->where('campus_id', $campusId)
                    ->update([
                        'community_name' => $communityName,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                    ]);

                if ($updated) {
                    return redirect('/config-community?edit_id=' . $communityId)
                        ->with('success', 'Community "<strong>' . e($communityName) . '</strong>" has been updated.');
                } else {
                    return redirect('/config-community')
                        ->with('error', 'Community not found or not in your campus.');
                }
            } else {
                // INSERT new record with campus_id
                DB::table('communities')->insert([
                    'community_name' => $communityName,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'campus_id' => $campusId,
                ]);

                return redirect('/config-community')
                    ->with('success', 'Community "<strong>' . e($communityName) . '</strong>" has been added.');
            }
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            if (DB::getPdo()->errorCode() == 23000 && str_contains($errorMsg, '1062')) {
                $errorMsg = 'A community with this name already exists.';
            }
            return redirect()->back()->with('error', 'Error: ' . $errorMsg)->withInput();
        }
    }

    /**
     * Delete a community (only if it belongs to user's campus)
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $campusId = $user->campus_id;

        try {
            $deleted = DB::table('communities')
                ->where('id', $id)
                ->where('campus_id', $campusId)
                ->delete();

            if ($deleted) {
                return redirect('/config-community')->with('success', 'Community deleted successfully!');
            } else {
                return redirect('/config-community')->with('error', 'Cannot delete: Community not found or not in your campus.');
            }
        } catch (\Exception $e) {
            return redirect('/config-community')->with('error', 'Cannot delete community: ' . $e->getMessage());
        }
    }
}
