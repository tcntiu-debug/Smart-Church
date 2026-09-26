<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Login user and return token
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = DB::selectOne("SELECT * FROM tiu_member WHERE email = ?", [$request->email]);

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 401);
        }

        if (!password_verify($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid password'], 401);
        }

        if ($user->status == 2) {
            return response()->json(['success' => false, 'message' => 'Account disabled'], 403);
        }

        // Update login history tracking
        $this->updateLoginHistory($user);

        // Check completion statuses
        $profileComplete = $this->checkProfileComplete($user);
        $policySigned = DB::table('policy')
            ->where('tiu_member_id', $user->tiu_member_id)
            ->where('signature', 'yes')
            ->exists();

        // Create a simple token for mobile consumption (Base64 encoded ID + Timestamp)
        $token = base64_encode($user->tiu_member_id . '|' . time());

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->tiu_member_id,
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'email' => $user->email,
                'phone_number' => $user->phone_number ?? '',
                'role' => $user->member_role ?? 'Worker',
                'member_role' => $user->member_role ?? 'Worker',
                'campus_id' => $user->campus_id ?? 1,
                'church_type_id' => $user->church_type_id ?? null,
                'profile_complete' => $profileComplete,
                'policy_signed' => $policySigned
            ]
        ]);
    }

    /**
     * Get the authenticated user's profile data using the Bearer token
     */
    public function user(Request $request)
    {
        $userId = $request->user_id ?? $request->input('user_id');
        
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'User ID not found'], 401);
        }

        $user = DB::selectOne("SELECT * FROM tiu_member WHERE tiu_member_id = ?", [$userId]);

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $birthday = DB::table('birthday')->where('tiu_member_id', $userId)->first();
        $profileComplete = $this->checkProfileComplete($user);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->tiu_member_id,
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'email' => $user->email,
                'phone_number' => $user->phone_number ?? '',
                'member_role' => $user->member_role ?? 'Worker',
                'campus_id' => $user->campus_id ?? 1,
                'marital_status' => $user->marital_status,
                'age' => $user->age,
                'occupation' => $user->occupation,
                'next_of_kin_name' => $user->next_of_kin_name,
                'next_of_kin_phone' => $user->next_of_kin_phone,
                'birthday' => $birthday->birthday ?? null,
                'profile_complete' => $profileComplete
            ]
        ]);
    }

    /**
     * Policy Check Endpoint
     */
    public function checkPolicy(Request $request)
    {
        $request->validate(['user_id' => 'required']);
        $policySigned = DB::table('policy')
            ->where('tiu_member_id', $request->user_id)
            ->where('signature', 'yes')
            ->exists();

        return response()->json([
            'success' => true,
            'policy_signed' => $policySigned
        ]);
    }

    /**
     * Sign Policy Endpoint
     */
    public function signPolicy(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'name' => 'required'
        ]);

        $exists = DB::table('policy')->where('tiu_member_id', $request->user_id)->exists();

        if ($exists) {
            DB::table('policy')->where('tiu_member_id', $request->user_id)->update([
                'signature' => 'yes',
                'date_signed' => now()
            ]);
        } else {
            DB::table('policy')->insert([
                'tiu_member_id' => $request->user_id,
                'name' => $request->name,
                'signature' => 'yes',
                'date_signed' => now()
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Policy signed successfully']);
    }

    /**
     * Check Profile Completion Status
     */
    public function checkProfile(Request $request)
    {
        $request->validate(['user_id' => 'required']);

        $user = DB::selectOne("SELECT tiu_member_id, first_name, last_name, marital_status, age, occupation, next_of_kin_name, next_of_kin_phone FROM tiu_member WHERE tiu_member_id = ?", [$request->user_id]);
        $birthday = DB::table('birthday')->where('tiu_member_id', $request->user_id)->first();

        $profileComplete = !empty($user->marital_status) && !empty($user->age) && !empty($user->occupation) && 
                          !empty($user->next_of_kin_name) && !empty($user->next_of_kin_phone) && !empty($birthday);

        return response()->json([
            'success' => true,
            'profile_complete' => $profileComplete,
            'user' => $user,
            'birthday' => $birthday
        ]);
    }

    /**
     * Update Profile and Birthday Information
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'marital_status' => 'required',
            'age' => 'required',
            'occupation' => 'required',
            'next_of_kin_name' => 'required',
            'next_of_kin_phone' => 'required',
            'birthday' => 'required'
        ]);

        // Update main member profile
        DB::table('tiu_member')->where('tiu_member_id', $request->user_id)->update([
            'marital_status' => $request->marital_status,
            'age' => $request->age,
            'occupation' => $request->occupation,
            'next_of_kin_name' => $request->next_of_kin_name,
            'next_of_kin_phone' => $request->next_of_kin_phone
        ]);

        // Sync name to birthday table
        $user = DB::selectOne("SELECT first_name, last_name FROM tiu_member WHERE tiu_member_id = ?", [$request->user_id]);
        $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        // Update or Insert birthday record
        DB::table('birthday')->updateOrInsert(
            ['tiu_member_id' => $request->user_id],
            [
                'birthday' => $request->birthday,
                'Name' => $fullName
            ]
        );

        return response()->json(['success' => true, 'message' => 'Profile updated successfully']);
    }

    /**
     * Block registration via API (Admin only logic)
     */
    public function register(Request $request)
    {
        return response()->json(['success' => false, 'message' => 'Registration is admin only'], 403);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    }

    /**
     * Helper: Update or insert login history
     */
    private function updateLoginHistory($user)
    {
        $exists = DB::table('tiu_member_login')->where('tiu_member_id', $user->tiu_member_id)->exists();

        if ($exists) {
            DB::table('tiu_member_login')
                ->where('tiu_member_id', $user->tiu_member_id)
                ->update(['date_logged_in' => now()]);
        } else {
            DB::table('tiu_member_login')->insert([
                'tiu_member_id' => $user->tiu_member_id,
                'date_logged_in' => now(),
                'soure_addresss' => request()->ip() ?? '',
                'theme_settings' => 'light'
            ]);
        }
    }

    /**
     * Helper: Check if all required profile fields are filled
     */
    private function checkProfileComplete($user)
    {
        if (!$user) return false;

        $birthday = DB::table('birthday')->where('tiu_member_id', $user->tiu_member_id)->first();

        return !empty($user->marital_status) &&
               !empty($user->age) &&
               !empty($user->occupation) &&
               !empty($user->next_of_kin_name) &&
               !empty($user->next_of_kin_phone) &&
               !empty($birthday);
    }
}