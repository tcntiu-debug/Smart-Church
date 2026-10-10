<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * "Birthday coming up" digest sent to the Welcome Center admins.
 */
class BirthdayReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var string */
    public $campusName;

    /** @var int */
    public $daysBefore;

    /** @var string */
    public $birthdayDate;

    /** @var array<int, array<string, mixed>> */
    public $birthdays;

    /**
     * @param  array<int, array<string, mixed>>  $birthdays
     */
    public function __construct(string $campusName, int $daysBefore, string $birthdayDate, array $birthdays)
    {
        $this->campusName = $campusName;
        $this->daysBefore = $daysBefore;
        $this->birthdayDate = $birthdayDate;
        $this->birthdays = $birthdays;
    }

    public function build()
    {
        $target = date('d M Y', strtotime($this->birthdayDate));
        $count = count($this->birthdays);

        if ($this->daysBefore <= 0) {
            $when = 'today ('.$target.')';
        } elseif ($this->daysBefore === 1) {
            $when = 'tomorrow ('.$target.')';
        } else {
            $when = 'in '.$this->daysBefore.' days ('.$target.')';
        }

        return $this
            ->subject(sprintf('🎂 %d birthday%s %s — %s Campus', $count, $count === 1 ? '' : 's', $when, $this->campusName))
            ->view('emails.birthday-reminder')
            ->text('emails.birthday-reminder-text');
    }
}
