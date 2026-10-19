<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Services\MemberSessionService;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords {
        // A trait method is not a parent method: `parent::resetPassword()`
        // would land on Controller::__call and throw BadMethodCallException.
        // Keep the framework's versions under their own names.
        resetPassword as protected performPasswordReset;
        sendResetResponse as protected sendSuccessfulResetResponse;
    }

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

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

    /**
     * Save the new password and sign the member in.
     *
     * ResetsPasswords hashes the password, stores it, fires PasswordReset and
     * calls Auth::login() - but that is not all a sign-in does here. The left
     * menu and the greeting are rendered from session keys only the login form
     * used to write (see MemberSessionService), so a reset landed on /home as
     * "Welcome Guest" with the plain member menu, and the full menu only came
     * back after signing out and in again.
     *
     * @param  \Illuminate\Contracts\Auth\CanResetPassword  $user
     * @param  string  $password
     * @return void
     */
    protected function resetPassword($user, $password)
    {
        $this->performPasswordReset($user, $password);

        // A refused account gets no session data either; sendResetResponse()
        // signs it back out and points at the login page.
        if ($this->memberSession->canSignIn($user)) {
            $this->memberSession->start($user);
        }
    }

    /**
     * Answer a successful reset - or refuse to leave a member signed in whom the
     * login form would have turned away (disabled account, unverified address).
     * A reset only proves the mailbox and the new password; `status` and
     * `email_verified` still decide whether there is a session at all.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $response
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    protected function sendResetResponse(Request $request, $response)
    {
        $user = $request->user();

        if ($user && ! $this->memberSession->canSignIn($user)) {
            Auth::logout();

            return redirect()->route('login')->with('error', $this->memberSession->blockedMessage($user));
        }

        return $this->sendSuccessfulResetResponse($request, $response);
    }
}
