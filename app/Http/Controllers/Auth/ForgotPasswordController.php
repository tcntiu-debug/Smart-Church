<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * The address a member is told to use when the mail cannot go out at all.
     *
     * Kept generic on purpose: the reason (SMTP credentials, a missing
     * `password_resets` table, a log mailer) belongs in storage/logs/laravel.log,
     * not on a public login page.
     */
    protected const UNDELIVERABLE = 'We could not send the reset e-mail just now. Please try again in a minute, or contact the church office.';

    /**
     * Send the reset link, but never answer "link sent" when it did not go out.
     *
     * The framework's version reports success as soon as the token row exists,
     * and it lets a failing mail transport bubble up as a 500 with an HTML body
     * - which the modal on `/` can only render as "undefined". Two things used
     * to fail that way here:
     *
     *   1. `App\Models\TiuMember` had no `Notifiable` trait, so the notification
     *      threw `BadMethodCallException` after the token was written;
     *   2. production had no `password_resets` table (see `app:sync-schema`),
     *      so the broker threw before the token was even written.
     *
     * Both now answer with a JSON body the modal can read, and the token is
     * removed again so the 60 second throttle does not block the retry.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function sendResetLinkEmail(Request $request)
    {
        $this->validateEmail($request);

        if ($this->mailerCannotDeliver()) {
            return $this->undeliverableResponse($request);
        }

        $member = null;
        $failure = null;

        try {
            $status = $this->broker()->sendResetLink(
                $this->credentials($request),
                function ($user, $token) use (&$member, &$failure) {
                    $member = $user;

                    try {
                        $user->sendPasswordResetNotification($token);
                    } catch (\Throwable $e) {
                        $failure = $e;
                    }
                }
            );
        } catch (\Throwable $e) {
            // Missing token table, database down, ... - nothing was sent.
            $failure = $e;
            $status = null;
        }

        if ($failure !== null) {
            $this->forgetToken($member);
            $this->logFailure($request, $failure);

            return $this->undeliverableResponse($request);
        }

        if ($status === Password::RESET_LINK_SENT) {
            return $this->sentResponse($request, trans($status));
        }

        return $this->failedResponse($request, trans($status ?: Password::INVALID_USER));
    }

    /**
     * A `log` (or `array`) mailer writes the message to a file instead of
     * delivering it. In development that is how you read the link; on the
     * production site it would mean telling a member "sent" when nothing left
     * the server, so there the request is refused instead.
     *
     * @return bool
     */
    protected function mailerCannotDeliver(): bool
    {
        $mailer = (string) config('mail.default');

        if (! in_array($mailer, ['log', 'array'], true)) {
            return false;
        }

        $reason = 'MAIL_MAILER is "' . $mailer . '": a password reset link would be written to the log instead of being delivered.';

        if (app()->environment('production')) {
            // Error level on purpose: a production .env sets LOG_LEVEL=error,
            // so a warning about the one thing that stops the mail would never
            // be written to the log.
            Log::error($reason . ' The request was refused.');

            return true;
        }

        Log::warning($reason . ' On this environment the link is in storage/logs/laravel.log.');

        return false;
    }

    /**
     * The token was already stored when the mail failed. Dropping it lets the
     * member retry straight away instead of hitting "Please wait before
     * retrying." for a minute over a link that never existed.
     *
     * @param  \Illuminate\Contracts\Auth\CanResetPassword|null  $member
     * @return void
     */
    protected function forgetToken($member): void
    {
        if ($member === null) {
            return;
        }

        try {
            $this->broker()->getRepository()->delete($member);
        } catch (\Throwable $e) {
            // The token table is what failed in the first place; nothing to clean.
        }
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return void
     */
    protected function logFailure(Request $request, \Throwable $e): void
    {
        Log::error('Password reset link could not be sent to ' . $request->input('email') . ': ' . $e->getMessage(), [
            'exception' => get_class($e),
            'mailer' => config('mail.default'),
            'mail_host' => config('mail.mailers.' . config('mail.default') . '.host'),
            'mail_port' => config('mail.mailers.' . config('mail.default') . '.port'),
            // The mailbox, not the password: a one-character difference between
            // `MAIL_USERNAME` and the mailbox that really exists is the usual
            // reason for "Failed to authenticate on SMTP server", and it is only
            // visible here - the modal never shows a reason.
            'mail_username' => config('mail.mailers.' . config('mail.default') . '.username'),
            'mail_encryption' => config('mail.mailers.' . config('mail.default') . '.encryption'),
            'from' => config('mail.from.address'),
        ]);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $message
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    protected function sentResponse(Request $request, string $message)
    {
        return $request->wantsJson()
            ? new JsonResponse(['message' => $message], 200)
            : back()->with('status', $message);
    }

    /**
     * Unknown address, or a second request inside the throttle window.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $message
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    protected function failedResponse(Request $request, string $message)
    {
        return $request->wantsJson()
            ? new JsonResponse(['message' => $message, 'errors' => ['email' => [$message]]], 422)
            : back()->withInput($request->only('email'))->withErrors(['email' => $message]);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    protected function undeliverableResponse($request)
    {
        $message = self::UNDELIVERABLE;

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message, 'errors' => ['email' => [$message]]], 500)
            : back()->withInput($request->only('email'))->withErrors(['email' => $message]);
    }
}
