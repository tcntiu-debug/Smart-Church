<?php

namespace App\Services;

use App\Mail\MemberVerificationMail;
use App\Models\TiuMember;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * The "activate your account" link a member is mailed when an account is
 * created with `email_verified = 0`.
 *
 * Registration used to insert the row and tell the member to check an inbox
 * that never received anything (docs/MEMBER-VERIFICATION.md): the legacy
 * activation mail was not ported to the Laravel tree, while the "Forgot
 * Password" mail was, so only that one arrived. Everything the flow needs now
 * lives here - the signed link, the mail, the flag - so public registration,
 * the first-timer integration and the resend page share one implementation.
 */
class MemberVerificationService
{
    /**
     * How long an activation link stays usable: long enough for a member who
     * reads their mail a few days later, short enough that a link forwarded by
     * mistake is not a permanent key to the account.
     */
    public const LINK_DAYS = 7;

    /**
     * Anti-tamper half of the link, next to the HMAC the signed URL carries.
     * It follows MustVerifyEmail's idea: the hash binds the link to the address
     * the account had when it was mailed, so a later change breaks the link.
     */
    public function hash(TiuMember $member): string
    {
        return sha1((string) $member->email);
    }

    /**
     * The signed activation URL that goes into the mail.
     */
    public function verificationUrl(TiuMember $member): string
    {
        return URL::temporarySignedRoute('member.verify', now()->addDays(self::LINK_DAYS), [
            'id' => $member->getKey(),
            'hash' => $this->hash($member),
        ]);
    }

    /**
     * What the link does: the column the login form reads
     * (MemberSessionService::canSignIn()).
     */
    public function markVerified(TiuMember $member): void
    {
        $member->forceFill(['email_verified' => 1])->save();
    }

    /**
     * Mail the activation link.
     *
     * Never throws: a mail failure must not turn a successful registration (or
     * a first-timer integration) into an error. The caller is told whether the
     * message left the server so it can answer the member honestly.
     */
    public function send(TiuMember $member): bool
    {
        $email = trim((string) $member->email);

        if ($email === '') {
            Log::warning('Activation e-mail not sent: member '.$member->getKey().' has no e-mail address.');

            return false;
        }

        if ((string) $member->email_verified === '1') {
            Log::info('Activation e-mail not sent: '.$email.' is already verified.');

            return false;
        }

        if ($this->mailerCannotDeliver()) {
            return false;
        }

        $name = trim((string) $member->first_name.' '.(string) $member->last_name);

        try {
            Mail::to($email, $name !== '' ? $name : null)->send(
                new MemberVerificationMail(
                    (string) $member->first_name,
                    $this->verificationUrl($member),
                    self::LINK_DAYS
                )
            );

            Log::info('Activation e-mail sent to '.$email);

            return true;
        } catch (\Throwable $e) {
            Log::error('Activation e-mail could not be sent to '.$email.': '.$e->getMessage(), [
                'exception' => get_class($e),
                'member' => $member->getKey(),
                'mailer' => config('mail.default'),
                'mail_host' => config('mail.mailers.'.config('mail.default').'.host'),
                'mail_port' => config('mail.mailers.'.config('mail.default').'.port'),
                // The mailbox, not the password: a one-character difference
                // between MAIL_USERNAME and the mailbox that really exists is
                // the usual reason for "Failed to authenticate on SMTP server".
                'mail_username' => config('mail.mailers.'.config('mail.default').'.username'),
                'mail_encryption' => config('mail.mailers.'.config('mail.default').'.encryption'),
                'from' => config('mail.from.address'),
            ]);

            return false;
        }
    }

    /**
     * A `log` (or `array`) mailer writes the message to a file instead of
     * delivering it. In development that is how you read the link; on the
     * production site it would mean telling a member "check your inbox" while
     * nothing left the server. Same rule the password reset applies.
     */
    protected function mailerCannotDeliver(): bool
    {
        $mailer = (string) config('mail.default');

        if (! in_array($mailer, ['log', 'array'], true)) {
            return false;
        }

        $reason = 'MAIL_MAILER is "'.$mailer.'": an activation link would be written to the log instead of being delivered.';

        if (app()->environment('production')) {
            // Error level on purpose: a production .env sets LOG_LEVEL=error,
            // so a warning about the one thing that stops the mail would never
            // be written to the log.
            Log::error($reason.' The activation e-mail was not sent.');

            return true;
        }

        Log::warning($reason.' On this environment the link is in storage/logs/laravel.log.');

        return false;
    }
}
