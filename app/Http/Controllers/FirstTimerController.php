<?php

namespace App\Http\Controllers;

use App\Models\FirstTimer;
use App\Models\ChurchType;
use App\Models\Occupation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FirstTimerController extends Controller
{

    public function getChurchTypes(Request $request)
    {
        try {
            $churchTypes = DB::table('church_type')
                ->orderBy('church_type_name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $churchTypes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getOccupations(Request $request)
    {
        try {
            $occupations = DB::table('occupation')
                ->orderBy('occ_name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $occupations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        $churchTypes = DB::table('church_type')->orderBy('church_type_name')->get();
        $occupations = DB::table('occupation')->orderBy('occ_name')->get();

        // Get logged-in user's campus
        $user = Auth::user();
        $userCampusId = $user->campus_id ?? session('campus_id', 0);
        $userCampus = null;
        if ($userCampusId) {
            $userCampus = DB::table('campus')->where('cid', $userCampusId)->value('cname');
        }
        $userCampusName = $userCampus;

        return view('first-timer.register', compact('churchTypes', 'occupations', 'userCampusName', 'userCampusId'));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'church_type_id' => 'required|integer',
                'first_name' => 'required|string|max:50',
                'last_name' => 'required|string|max:50',
                'phone_number' => 'nullable|string|max:200',
                'email' => 'nullable|email|max:50',
                'gender' => 'required|string|in:Male,Female',
                'age' => 'required|string',
                'marital_status' => 'nullable|string|max:20',
                'occupation' => 'nullable|string|max:50',
                'guest_type' => 'required|string',
                'how_did_you_hear' => 'required|string',
                'address' => 'required|string',
                'born_again' => 'required|string|in:Yes,No',
                'water_baptism' => 'required|string|in:Yes,No',
                'holy_ghost_baptism' => 'required|string|in:Yes,No',
                'parent_guardian_name' => 'nullable|string|max:200',
                'parent_guardian_phone' => 'nullable|string|max:100',
                'parent_guardian_relationship' => 'nullable|string|max:50',
            ]);

            // Get logged in user's campus_id (from user model or session fallback)
            $user = Auth::user();
            $campus_id = $user->campus_id ?? session('campus_id', 0);
            $registered_by = $user->first_name ?? 'N/A';

            // Check if phone number already exists
            if ($request->phone_number) {
                $existing = FirstTimer::where('phone_number', $request->phone_number)->first();
                if ($existing) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Phone number already registered!'
                    ], 400);
                }
            }

            // Create new first timer
            $firstTimer = FirstTimer::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone_number' => $request->phone_number ?? '',
                'email' => $request->email,
                'gender' => $request->gender,
                'age' => $request->age,
                'marital_status' => $request->marital_status,
                'occupation' => $request->occupation,
                'attendant_type' => $request->guest_type,
                'how_did_you_hear' => $request->how_did_you_hear,
                'address' => $request->address,
                'born_again' => $request->born_again,
                'water_baptism' => $request->water_baptism,
                'holy_ghost_baptism' => $request->holy_ghost_baptism,
                'parent_name' => $request->parent_guardian_name,
                'parent_phone' => $request->parent_guardian_phone,
                'relationship' => $request->parent_guardian_relationship,
                'church_type_id' => $request->church_type_id,
                'campus_id' => $campus_id,
                'registered_by' => $registered_by,
                'attendance_count' => 0,
                'status' => 'Not Assigned',
                'register_date' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registration Successful!',
                'data' => $firstTimer
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
