<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Models\TiuMember;
use Illuminate\Support\Facades\DB;


class CustomAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        try {
            Log::info('Step 1: Login attempt started for email: ' . $request->email);

            // Validate input
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            Log::info('Step 2: Validation passed');

            // Use direct database query
            $user = DB::selectOne("SELECT * FROM tiu_member WHERE email = ?", [$request->email]);

            if (!$user) {
                Log::warning('Step 3: User not found');
                return back()->withErrors(['email' => 'User not found']);
            }

            Log::info('Step 3: User found, ID: ' . $user->tiu_member_id);

            // Verify password
            if (!password_verify($request->password, $user->password)) {
                Log::warning('Step 4: Invalid password');
                return back()->withErrors(['email' => 'Invalid password']);
            }

            Log::info('Step 4: Password verified');

            // Check if account is disabled
            if ($user->status == 2) {
                Log::warning('Step 5: Account disabled');
                return back()->with('error', 'Account is currently disabled. Please contact Admin');
            }

            // Check email verification
            if ($user->email_verified != 1) {
                Log::warning('Step 6: Email not verified');
                return back()->with('error', 'Email not yet verified. Contact Admin');
            }

            Log::info('Step 5 and 6: Account active and email verified');

            // Manually log the user in
            Auth::loginUsingId($user->tiu_member_id);
            Log::info('Step 7: User logged in with Auth::loginUsingId');

            // Calculate leadership roles
            $this->calculateLeadershipRoles($user);
            Log::info('Step 8: Leadership roles calculated');

            // Update login history
            $this->updateLoginHistory($user);
            Log::info('Step 9: Login history updated');

            // Redirect to the new dashboard landing page (universal for all roles)
            Log::info('Step 10: Redirecting to /home for user');
            return redirect('/home');
        } catch (\Exception $e) {
            Log::error('Login exception: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return back()->with('error', 'Login error: ' . $e->getMessage());
        }
    }

    private function calculateLeadershipRoles($user)
    {
        $userId = $user->tiu_member_id;

        // Get department leads
        $leadDeptIds = DB::table('department')->where('dept_lead_id', $userId)->pluck('dept_id')->toArray();
        $leadHouseFellowshipIds = DB::table('department')->where('dept_lead_id', $userId)->where('dept_type', 'House Fellowship')->pluck('dept_id')->toArray();
        $leadClusterIds = DB::table('department')->where('dept_lead_id', $userId)->where('dept_type', 'Cluster')->pluck('dept_id')->toArray();

        // Get church type leads
        $leadChurchTypeIds = DB::table('church_type')->where('church_type_lead_id', $userId)->pluck('id')->toArray();

        Session::put('lead_of_dept', $leadDeptIds);
        Session::put('lead_of_house_fellowship', $leadHouseFellowshipIds);
        Session::put('lead_of_cluster', $leadClusterIds);
        Session::put('lead_of_church_type', $leadChurchTypeIds);

        $isHOD = count($leadDeptIds) > 0 || count($leadHouseFellowshipIds) > 0 || count($leadClusterIds) > 0 || count($leadChurchTypeIds) > 0 || in_array($user->member_role, ['Super User', 'Admin']);
        Session::put('userIsHODOrLead', $isHOD);

        // Set other session variables
        Session::put('first_name', $user->first_name);
        Session::put('last_name', $user->last_name);
        Session::put('tiu_member_id', $user->tiu_member_id);
        Session::put('member_role', $user->member_role);
        Session::put('subgroup', $user->subgroup ?? '');
        Session::put('church_type_id', $user->church_type_id);
        Session::put('campus_id', $user->campus_id);
        Session::put('department_name', $user->department_name);
        Session::put('house_fellowship', $user->house_fellowship);
        Session::put('cluster', $user->cluster);
        Session::put('login', true);
    }

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
                'soure_addresss' => '',
                'theme_settings' => 'light'
            ]);
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        Session::flush();
        return redirect('/');
    }

   public function showSimpleLoginForm()
{
    // Enable query logging
    DB::enableQueryLog();
    
    $result = view('auth.simple-login');
    
    // Get and log all queries
    $queries = DB::getQueryLog();
    \Log::info('Queries run:', $queries);
    
    return $result;
}
}
