<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * "Activate your account" mail.
 *
 * Sent by App\Services\MemberVerificationService whenever a member row is
 * created with `email_verified = 0` (public registration, first-timer
 * integration) and by the "Resend activation email" page. The link inside it
 * is a signed route (member.verify) that sets the flag the login form reads.
 */
class MemberVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var string */
    public $firstName;

    /** @var string */
    public $verifyUrl;

    /** @var int */
    public $expiresInDays;

    public function __construct(string $firstName, string $verifyUrl, int $expiresInDays)
    {
        $this->firstName = $firstName;
        $this->verifyUrl = $verifyUrl;
        $this->expiresInDays = $expiresInDays;
    }

    public function build()
    {
        return $this
            ->subject('Activate your TCN Ikorodu Smart Church account')
            ->view('emails.member-verification')
            ->text('emails.member-verification-text');
    }
}
