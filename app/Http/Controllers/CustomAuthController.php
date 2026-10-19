<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Models\TiuMember;
use App\Services\MemberSessionService;
use Illuminate\Support\Facades\DB;


class CustomAuthController extends Controller
{
    /**
     * Writes the session the sidebar and the greeting are rendered from.
     *
     * @var MemberSessionService
     */
    protected $memberSession;

    public function __construct(MemberSessionService $memberSession)
    {
        $this->memberSession = $memberSession;
    }

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

            // Check that the account may sign in at all: a disabled account
            // (`status` 2) and an unverified address are refused here, and the
            // password reset asks the same question (MemberSessionService).
            if (! $this->memberSession->canSignIn($user)) {
                Log::warning('Step 5/6: Sign-in refused (status ' . $user->status . ', email_verified ' . $user->email_verified . ')');
                return back()->with('error', $this->memberSession->blockedMessage($user));
            }

            Log::info('Step 5 and 6: Account active and email verified');

            // Manually log the user in
            Auth::loginUsingId($user->tiu_member_id);
            Log::info('Step 7: User logged in with Auth::loginUsingId');

            // Fill the session the menu, the greeting and the lead views read
            $this->memberSession->start($user);
            Log::info('Step 8: Member session filled');

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
                // Legacy NOT NULL column, kept only for schema compatibility.
                // The display mode is not stored per member any more; it lives in
                // the `tiu_theme` cookie (see public/assets/js/theme.js).
                'theme_settings' => '',
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
