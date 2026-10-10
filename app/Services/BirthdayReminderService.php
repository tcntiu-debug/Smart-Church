<?php

namespace App\Services;

use App\Mail\BirthdayReminderMail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Builds and dispatches the Welcome Center birthday reminders.
 *
 * Birthdays live in `birthday.birthday` as free text ("03 Mar", no year), so
 * month/day matching is done in PHP through parseBirthday() instead of SQL.
 * Every digest is written to `birthday_reminder_logs` so the daily scheduler
 * never sends the same milestone twice.
 */
class BirthdayReminderService
{
    /** @var array<int, string> Console output lines. */
    protected $lines = [];

    /** @var array<int, string> dept_id => dept_name cache. */
    protected $departmentMap = [];

    /**
     * Run every configured milestone for every campus.
     *
     * @param  array<int, int>     $days      Milestones in days before the birthday, e.g. [3, 2, 1].
     * @param  array<int, string>  $channels  mail | database | telegram.
     * @param  bool                $force     Ignore the duplicate-send log.
     * @param  bool                $dryRun    Resolve everything but send nothing.
     * @param  int|null            $campusId  Restrict to a single campus.
     * @return array<string, int>
     */
    public function run(array $days, array $channels, bool $force = false, bool $dryRun = false, ?int $campusId = null): array
    {
        $this->lines = [];

        $summary = ['logs' => 0, 'birthdays' => 0, 'recipients' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
        $today = $this->today();

        $this->lines[] = 'Today ('.$today->format('D, d M Y').')';
        $this->lines[] = 'Milestones: '.implode(', ', array_map(function ($d) {
            return $d.' day(s) before';
        }, $days));
        $this->lines[] = 'Channels: '.implode(', ', $channels).($dryRun ? '  [DRY RUN]' : '');
        $this->lines[] = '';

        foreach ($this->campuses($campusId) as $campus) {
            foreach ($days as $daysBefore) {
                $target = $today->copy()->addDays($daysBefore);

                if (!$force && $this->alreadySent((int) $campus->cid, (int) $daysBefore, $target->toDateString())) {
                    $summary['skipped']++;
                    $this->lines[] = sprintf(
                        '  skip  %s | %s | already sent for %s',
                        $campus->cname,
                        $this->milestoneLabel($daysBefore),
                        $target->format('d M Y')
                    );
                    continue;
                }

                $birthdays = $this->birthdaysOn((int) $campus->cid, $target);

                if (empty($birthdays)) {
                    $this->lines[] = sprintf(
                        '  none  %s | %s | no birthdays on %s',
                        $campus->cname,
                        $this->milestoneLabel($daysBefore),
                        $target->format('d M Y')
                    );
                    continue;
                }

                $recipients = $this->recipientsFor((int) $campus->cid);

                $result = $this->deliver($campus, (int) $daysBefore, $target, $birthdays, $recipients, $channels, $dryRun);

                $summary['logs']++;
                $summary['birthdays'] += count($birthdays);
                $summary['recipients'] += $result['recipient_count'];

                if ($result['status'] === 'sent') {
                    $summary['sent']++;
                } elseif ($result['status'] === 'skipped') {
                    $summary['skipped']++;
                } else {
                    $summary['failed']++;
                }

                $this->lines[] = sprintf(
                    '  %-5s %s | %s | %d birthday(s) | %d recipient(s) | %s%s',
                    strtoupper($result['status']),
                    $campus->cname,
                    $this->milestoneLabel($daysBefore),
                    count($birthdays),
                    $result['recipient_count'],
                    $result['channels'] ? implode(', ', $result['channels']) : 'no channel delivered',
                    $result['message'] ? ' :: '.$result['message'] : ''
                );
            }
        }

        return $summary;
    }

    /**
     * Console output lines produced by the last run().
     *
     * @return array<int, string>
     */
    public function logLines(): array
    {
        return $this->lines;
    }

    /**
     * "Today" in the church's timezone.
     */
    public function today(): Carbon
    {
        return Carbon::today(config('birthday.timezone', 'Africa/Lagos'));
    }

    /**
     * Campuses to process.
     *
     * @return Collection
     */
    protected function campuses(?int $campusId = null)
    {
        $query = DB::table('campus')->select('cid', 'cname')->orderBy('cid');

        if ($campusId) {
            $query->where('cid', $campusId);
        }

        return $query->get();
    }

    /**
     * Every member whose birthday falls on the given date, for one campus.
     *
     * @return array<int, array<string, mixed>>
     */
    public function birthdaysOn(int $campusId, Carbon $target): array
    {
        $rows = DB::table('birthday as b')
            ->join('tiu_member as m', 'm.tiu_member_id', '=', 'b.tiu_member_id')
            ->where('m.campus_id', $campusId)
            ->where(function ($query) {
                $query->whereNull('m.status')->orWhere('m.status', '!=', '3');
            })
            ->select(
                'b.tiu_member_id',
                'b.birthday',
                'b.Name',
                'm.first_name',
                'm.last_name',
                'm.phone_number',
                'm.email',
                'm.department_name',
                'm.member_role'
            )
            ->orderBy('m.first_name')
            ->get();

        $birthdays = [];

        foreach ($rows as $row) {
            $parsed = $this->parseBirthday($row->birthday);

            if (!$parsed) {
                continue;
            }

            if ((int) $parsed->month !== (int) $target->month || (int) $parsed->day !== (int) $target->day) {
                continue;
            }

            $name = trim(($row->first_name ?? '').' '.($row->last_name ?? ''));

            if ($name === '') {
                $name = trim((string) $row->Name);
            }

            if ($name === '') {
                continue;
            }

            $birthdays[] = [
                'member_id' => (int) $row->tiu_member_id,
                'name' => $name,
                'birthday_label' => $target->format('d M'),
                'birthday_date' => $target->toDateString(),
                'phone' => $row->phone_number,
                'whatsapp_url' => $this->whatsAppUrl($row->phone_number, $name),
                'email' => $row->email,
                'departments' => $this->departmentNames($row->department_name),
                'role' => $row->member_role,
            ];
        }

        return $birthdays;
    }

    /**
     * Admins of the configured departments (Welcome Center = 28) for one campus.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recipientsFor(int $campusId): array
    {
        $departmentIds = array_values(array_filter(array_map('intval', (array) config('birthday.department_ids', [28]))));
        $roles = (array) config('birthday.recipient_roles', ['Super User', 'Admin']);

        $query = DB::table('tiu_member')
            ->select('tiu_member_id', 'first_name', 'last_name', 'email', 'member_role', 'department_name')
            ->where('campus_id', $campusId)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', '3');
            })
            ->whereIn('member_role', $roles);

        if (!empty($departmentIds)) {
            $query->where(function ($q) use ($departmentIds) {
                foreach ($departmentIds as $departmentId) {
                    $q->orWhere('department_name', 'like', '%"'.$departmentId.'"%');
                }
            });
        }

        $recipients = [];

        foreach ($query->orderBy('first_name')->get() as $row) {
            $name = trim(($row->first_name ?? '').' '.($row->last_name ?? ''));

            $recipients[] = [
                'member_id' => (int) $row->tiu_member_id,
                'name' => $name !== '' ? $name : 'Admin',
                'email' => trim((string) $row->email),
                'role' => $row->member_role,
            ];
        }

        return $recipients;
    }

    /**
     * Has this campus + milestone + birthday date already been delivered?
     */
    public function alreadySent(int $campusId, int $daysBefore, string $birthdayDate): bool
    {
        return DB::table('birthday_reminder_logs')
            ->where('campus_id', $campusId)
            ->where('days_before', $daysBefore)
            ->where('reminder_date', $birthdayDate)
            ->where('status', 'sent')
            ->exists();
    }

    /**
     * Parse the free-text birthdays stored in `birthday.birthday`
     * ("03 Mar", "3 Mar 1990", "1990-03-03", ...) into a Carbon instance.
     * Returns null for blanks and unparseable values.
     */
    public function parseBirthday(?string $raw): ?Carbon
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $raw));

        if ($value === '' || !preg_match('/\d/', $value)) {
            return null;
        }

        $ignored = ['pending', 'n/a', 'na', 'none', 'unknown', '-', '0'];

        if (in_array(strtolower($value), $ignored, true)) {
            return null;
        }

        // Short month names come first: the stored format is "03 Mar".
        $formats = [
            'd M', 'j M',
            'd M Y', 'j M Y',
            'd F', 'j F',
            'd F Y', 'j F Y',
            'Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y',
            'd/m', 'd-m',
        ];

        foreach ($formats as $format) {
            if (!Carbon::hasFormat($value, $format)) {
                continue;
            }

            try {
                return Carbon::createFromFormat('!'.$format, $value);
            } catch (\Exception $e) {
                continue;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            Log::warning('BirthdayReminderService: unparseable birthday value "'.$value.'"');

            return null;
        }
    }

    /**
     * Resolve the JSON department ids stored on tiu_member into names.
     *
     * @return array<int, string>
     */
    protected function departmentNames($raw): array
    {
        $ids = json_decode((string) $raw, true);

        if (!is_array($ids)) {
            $ids = explode(',', (string) $raw);
        }

        $ids = array_values(array_filter(array_map('intval', (array) $ids)));

        if (empty($ids)) {
            return [];
        }

        if (empty($this->departmentMap)) {
            $this->departmentMap = DB::table('department')->pluck('dept_name', 'dept_id')->all();
        }

        $names = [];

        foreach ($ids as $id) {
            if (isset($this->departmentMap[$id])) {
                $names[] = $this->departmentMap[$id];
            }
        }

        return $names;
    }

    /**
     * wa.me link pre-filled with a birthday wish (same wording as /birthdays).
     */
    protected function whatsAppUrl($phone, string $name): ?string
    {
        $number = preg_replace('/[^0-9]/', '', (string) $phone);

        if ($number === '') {
            return null;
        }

        $countryCode = (string) config('birthday.wish_country_code', '234');

        if (strpos($number, '0') === 0) {
            $number = $countryCode.substr($number, 1);
        } elseif (strpos($number, $countryCode) !== 0) {
            $number = $countryCode.$number;
        }

        $message = "🎂 *Happy Birthday {$name}!* 🎉\n\n";
        $message .= "May this new year bring you joy, peace, and God's abundant blessings.\n\n";
        $message .= "Warm regards,\n*TCN Ikorodu*";

        return 'https://wa.me/'.$number.'?text='.urlencode($message);
    }

    /**
     * "today" / "1 day to go" / "3 days to go".
     */
    protected function milestoneLabel(int $daysBefore): string
    {
        if ($daysBefore <= 0) {
            return 'today';
        }

        return $daysBefore === 1 ? '1 day to go' : $daysBefore.' days to go';
    }

    /**
     * Hand one digest to every requested channel and record the result.
     *
     * @param  object  $campus  Row from the campus table (cid, cname).
     * @return array<string, mixed>
     */
    protected function deliver($campus, int $daysBefore, Carbon $target, array $birthdays, array $recipients, array $channels, bool $dryRun): array
    {
        $campusName = (string) $campus->cname;
        $campusId = (int) $campus->cid;
        $delivered = [];
        $notes = [];

        // ------------------------------------------------------------- e-mail
        if (in_array('mail', $channels, true)) {
            $emails = $this->emailRecipients($recipients);

            if (empty($emails)) {
                $notes[] = 'no e-mail address on any recipient';
            } elseif ($dryRun) {
                $delivered[] = 'mail ('.count($emails).')';
            } else {
                $sentTo = 0;

                foreach ($emails as $email => $name) {
                    try {
                        Mail::to($email, $name)->send(
                            new BirthdayReminderMail($campusName, $daysBefore, $target->toDateString(), $birthdays)
                        );
                        $sentTo++;
                    } catch (\Exception $e) {
                        $notes[] = 'mail failed for '.$email.': '.$e->getMessage();
                        Log::error('Birthday reminder mail failed', ['email' => $email, 'error' => $e->getMessage()]);
                    }
                }

                if ($sentTo > 0) {
                    $delivered[] = 'mail ('.$sentTo.')';
                }
            }
        }

        // ------------------------------------------------- in-app notification
        if (in_array('database', $channels, true)) {
            if (empty($recipients)) {
                $notes[] = 'no admin found in department '.implode('/', (array) config('birthday.department_ids', [28]));
            } elseif ($dryRun) {
                $delivered[] = 'database ('.count($recipients).')';
            } else {
                $timestamp = now();
                $rows = [];

                foreach ($recipients as $recipient) {
                    $rows[] = [
                        'tiu_member_id' => $recipient['member_id'],
                        'campus_id' => $campusId,
                        'type' => 'birthday_reminder',
                        'title' => $this->notificationTitle($daysBefore, count($birthdays), $target, $campusName),
                        'body' => $this->notificationBody($birthdays),
                        'data' => json_encode([
                            'days_before' => $daysBefore,
                            'birthday_date' => $target->toDateString(),
                            'campus_id' => $campusId,
                            'members' => array_map(function ($birthday) {
                                return $birthday['member_id'];
                            }, $birthdays),
                        ]),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                try {
                    DB::table('app_notifications')->insert($rows);
                    $delivered[] = 'database ('.count($rows).')';
                } catch (\Exception $e) {
                    $notes[] = 'in-app notification failed: '.$e->getMessage();
                    Log::error('Birthday reminder notification failed', ['error' => $e->getMessage()]);
                }
            }
        }

        // ------------------------------------------------------------ telegram
        if (in_array('telegram', $channels, true)) {
            $token = (string) config('birthday.telegram.bot_token', '');
            $chatIds = array_values(array_filter((array) config('birthday.telegram.chat_ids', [])));

            if ($token === '' || empty($chatIds)) {
                $notes[] = 'telegram not configured (set TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID)';
            } elseif ($dryRun) {
                $delivered[] = 'telegram ('.count($chatIds).')';
            } else {
                $message = $this->telegramMessage($daysBefore, $target, $campusName, $birthdays);
                $sentTo = 0;

                foreach ($chatIds as $chatId) {
                    try {
                        $response = Http::timeout(20)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                            'chat_id' => $chatId,
                            'text' => $message,
                            'parse_mode' => 'Markdown',
                            'disable_web_page_preview' => true,
                        ]);

                        if ($response->successful()) {
                            $sentTo++;
                        } else {
                            $notes[] = 'telegram '.$chatId.' error: '.substr($response->body(), 0, 200);
                        }
                    } catch (\Exception $e) {
                        $notes[] = 'telegram '.$chatId.' failed: '.$e->getMessage();
                        Log::error('Birthday reminder telegram failed', ['chat_id' => $chatId, 'error' => $e->getMessage()]);
                    }
                }

                if ($sentTo > 0) {
                    $delivered[] = 'telegram ('.$sentTo.')';
                }
            }
        }

        // A digest counts as "sent" as soon as one channel delivered it, so the
        // daily scheduler does not repeat it. Nothing delivered => retry tomorrow.
        $status = empty($delivered) ? 'failed' : 'sent';
        $note = implode('; ', $notes);

        $this->writeLog($campusId, $daysBefore, $target->toDateString(), count($birthdays), count($recipients), $delivered, $status, $note, $dryRun);

        return [
            'status' => $status,
            'channels' => $delivered,
            'message' => $note,
            'recipient_count' => count($recipients),
        ];
    }

    /**
     * Recipients that actually have a usable e-mail address, plus any extras
     * configured through BIRTHDAY_EXTRA_EMAILS.
     *
     * @return array<string, string|null>
     */
    protected function emailRecipients(array $recipients): array
    {
        $emails = [];

        foreach ($recipients as $recipient) {
            $email = trim((string) ($recipient['email'] ?? ''));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && !isset($emails[$email])) {
                $emails[$email] = $recipient['name'];
            }
        }

        foreach ((array) config('birthday.extra_emails', []) as $extra) {
            $extra = trim((string) $extra);

            if ($extra !== '' && filter_var($extra, FILTER_VALIDATE_EMAIL) && !isset($emails[$extra])) {
                $emails[$extra] = null;
            }
        }

        return $emails;
    }

    /**
     * Write (or refresh) the idempotency row for this digest.
     */
    protected function writeLog(int $campusId, int $daysBefore, string $birthdayDate, int $birthdayCount, int $recipientCount, array $delivered, string $status, string $note, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        try {
            DB::table('birthday_reminder_logs')->updateOrInsert(
                [
                    'campus_id' => $campusId,
                    'days_before' => $daysBefore,
                    'reminder_date' => $birthdayDate,
                ],
                [
                    'sent_on' => $this->today()->toDateString(),
                    'birthday_count' => $birthdayCount,
                    'recipient_count' => $recipientCount,
                    'channels' => implode(',', $delivered),
                    'status' => $status,
                    'message' => $note !== '' ? $note : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Birthday reminder log write failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Bell headline, e.g. "🎂 2 birthdays in 3 days (12 Oct) — Ikorodu Campus".
     */
    protected function notificationTitle(int $daysBefore, int $count, Carbon $target, string $campusName): string
    {
        if ($daysBefore <= 0) {
            $when = 'today';
        } elseif ($daysBefore === 1) {
            $when = 'tomorrow ('.$target->format('d M').')';
        } else {
            $when = 'in '.$daysBefore.' days ('.$target->format('d M').')';
        }

        return sprintf('🎂 %d birthday%s %s — %s Campus', $count, $count === 1 ? '' : 's', $when, $campusName);
    }

    /**
     * Plain text version stored on the in-app notification.
     */
    protected function notificationBody(array $birthdays): string
    {
        $lines = [];

        foreach ($birthdays as $index => $birthday) {
            $line = ($index + 1).'. '.$birthday['name'];

            if (!empty($birthday['departments'])) {
                $line .= ' ('.implode(', ', $birthday['departments']).')';
            }

            $lines[] = $line;
        }

        $lines[] = '';

        return implode("\n", $lines).'Open the Birthdays page to send a WhatsApp birthday wish.';
    }

    /**
     * Telegram (Markdown) push message.
     */
    protected function telegramMessage(int $daysBefore, Carbon $target, string $campusName, array $birthdays): string
    {
        if ($daysBefore <= 0) {
            $header = 'TODAY';
        } else {
            $header = $daysBefore.' DAY'.($daysBefore === 1 ? '' : 'S').' TO GO';
        }

        $lines = [];
        $lines[] = '🎂 *BIRTHDAY REMINDER - '.$header.'*';
        $lines[] = '*'.$this->escapeMarkdown($campusName).' Campus* · Welcome Center';
        $lines[] = '📅 '.$target->format('l, d M Y');
        $lines[] = '';

        foreach ($birthdays as $index => $birthday) {
            $line = ($index + 1).'. *'.$this->escapeMarkdown($birthday['name']).'*';

            if (!empty($birthday['departments'])) {
                $line .= ' - '.$this->escapeMarkdown(implode(', ', $birthday['departments']));
            }

            $lines[] = $line;

            if (!empty($birthday['phone'])) {
                $lines[] = '     📱 '.$this->escapeMarkdown($birthday['phone']);
            }
        }

        $lines[] = '';
        $lines[] = 'Sent automatically by Smart-Church';

        return $this->truncate(implode("\n", $lines), 3900);
    }

    /**
     * Escape the characters Telegram's legacy Markdown parser reacts to.
     */
    protected function escapeMarkdown(string $text): string
    {
        return str_replace(['_', '*', '[', ']', '(', ')', '~', '`'], ['\_', '\*', '\[', '\]', '\(', '\)', '\~', '\`'], $text);
    }

    /**
     * Multibyte-safe truncation.
     */
    protected function truncate(string $text, int $limit): string
    {
        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 3).'...' : $text;
    }
}
