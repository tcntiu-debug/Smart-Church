<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Services\BirthdayReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class BirthdayController extends Controller
{
    /**
     * The roles that read the staff birthday list (/birthdays).
     *
     * One list, read from three places - the sidebar, the dashboard card and
     * the gate below - so it is written once here and referenced from them.
     */
    public const STAFF_ROLES = ['Super User', 'Admin', 'Admins', 'Workers'];

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * The staff birthday list: the roles canManageReminders() admits.
     */
    public function index()
    {
        return $this->birthdayList(true);
    }

    /**
     * The same list for the rest of the church.
     *
     * Every signed-in member has a birthday, so everybody gets a page to read
     * them on. What they do not get is the staff page: no reminder status (that
     * belongs to the Super User alone), and no route name a staff-only link
     * points at, so the two can be told apart in the sidebar and in the tests.
     */
    public function celebrations()
    {
        return $this->birthdayList(false);
    }

    /**
     * Display birthday list
     *
     * @param  bool  $staffPage  false for birthdays.celebrations, which everyone reads
     */
    protected function birthdayList(bool $staffPage)
    {
        if ($staffPage && ! $this->canManageReminders()) {
            return redirect('/aoverview')->with('error', 'Access Denied');
        }

        $user = Auth::user();
        $campusId = $user->campus_id ?? 0;
        $birthdays = DB::table('birthday as b')
            ->join('tiu_member as m', 'b.tiu_member_id', '=', 'm.tiu_member_id')
            ->forCurrentCampus('m.campus_id', $campusId)
            ->select('b.*', 'm.first_name', 'm.last_name', 'm.phone_number', 'm.email')
            ->orderByRaw("MONTH(b.birthday), DAY(b.birthday)")
            ->get();

        // Get today's birthdays and upcoming birthdays (next 7 days)
        $todaysBirthdays = [];
        $upcomingBirthdays = [];
        $today = Carbon::today();
        
        foreach ($birthdays as $bday) {
            if ($bday->birthday) {
                $birthdayDate = Carbon::parse($bday->birthday);
                $birthdayThisYear = Carbon::create($today->year, $birthdayDate->month, $birthdayDate->day);
                
                // Compare days only: a birthday falling today must stay day 0
                if ($birthdayThisYear->lt($today)) {
                    $birthdayThisYear->addYear();
                }
                
                $daysLeft = (int) $today->diffInDays($birthdayThisYear, false);
                
                $bday->days_left = $daysLeft;
                $bday->birthday_date = $birthdayDate->format('d M');

                if ($daysLeft === 0) {
                    // Birthday is today
                    $todaysBirthdays[] = $bday;
                } elseif ($daysLeft > 0 && $daysLeft <= 7) {
                    // Birthday within the next 7 days
                    $upcomingBirthdays[] = $bday;
                }
            }
        }

        // Sort today's birthdays by name
        usort($todaysBirthdays, function($a, $b) {
            return strcasecmp($a->Name ?? '', $b->Name ?? '');
        });

        // Sort by days left
        usort($upcomingBirthdays, function($a, $b) {
            return $a->days_left - $b->days_left;
        });

        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = Carbon::create()->month($i)->format('F');
        }
        
        $currentMonth = now()->month;
        $currentMonthName = now()->format('F');

        // The Reset button belongs to the page the reader came from: the staff
        // list or the members' list, whichever this is.
        $listRoute = $staffPage ? 'birthdays.index' : 'birthdays.celebrations';

        // The status button rides with the staff list only, and only for the
        // Super User; the members' list carries no reminder status at all.
        $showReminderStatus = $staffPage && $this->canViewReminderStatus();

        return view('birthdays.index', compact('birthdays', 'todaysBirthdays', 'upcomingBirthdays', 'months', 'currentMonth', 'currentMonthName', 'listRoute', 'showReminderStatus'));

    }

    /**
     * Reminder status and preview (see docs/BIRTHDAY-REMINDERS.md).
     *
     * Answers the questions a daily digest leaves behind on a shared host: did
     * the scheduler run, what would today's digest contain, who would receive
     * it, and is e-mail really leaving the server? The preview is the command's
     * own --dry-run, so opening this page sends nothing and writes no log row.
     */
    public function status(BirthdayReminderService $service)
    {
        if (! $this->canViewReminderStatus()) {
            return redirect('/aoverview')->with('error', 'Access Denied');
        }

        $timezone = config('birthday.timezone', 'Africa/Lagos');
        $today = $service->today();

        $tables = [];
        foreach (['birthday', 'tiu_member', 'birthday_reminder_logs', 'app_notifications'] as $table) {
            $tables[$table] = Schema::hasTable($table);
        }

        // The dry run touches four tables; refuse it when one is missing rather
        // than let the command create a table behind an admin's back.
        if (in_array(false, $tables, true)) {
            $dryRun = 'Skipped: the missing tables above have to exist before the preview can run (php artisan app:sync-schema).';
        } else {
            try {
                Artisan::call('birthdays:remind', ['--dry-run' => true]);
                $dryRun = trim(Artisan::output());
            } catch (\Throwable $e) {
                $dryRun = 'The dry run failed: '.$e->getMessage();
            }
        }

        $logPath = storage_path('logs/birthday-reminders.log');
        $logExists = is_file($logPath);
        $logModified = $logExists ? Carbon::createFromTimestamp(filemtime($logPath))->timezone($timezone) : null;
        $logTail = $logExists ? $this->tailLines($logPath) : [];

        $recentLogs = $tables['birthday_reminder_logs']
            ? DB::table('birthday_reminder_logs')
                ->orderByDesc('sent_on')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
            : collect();

        $recipients = $tables['tiu_member']
            ? $service->recipientsFor(Campus::defaultId())
            : [];

        $emailableRecipients = 0;
        foreach ($recipients as $recipient) {
            if (filter_var($recipient['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                $emailableRecipients++;
            }
        }

        // Which transport the digests actually leave on: the dedicated birthday
        // mailer when BIRTHDAY_MAIL_MAILER is set, otherwise MAIL_MAILER.
        $digestMailer = $service->mailerName();
        $digestTransport = (array) config('mail.mailers.'.$digestMailer, []);

        $channels = array_values((array) config('birthday.channels', ['email']));
        $milestones = array_values(array_map('intval', (array) config('birthday.days', [3, 2, 1])));

        $warnings = $this->reminderWarnings($logExists, $logModified, $today, $emailableRecipients, $digestMailer, $milestones);

        return view('birthdays.status', compact(
            'timezone',
            'today',
            'tables',
            'dryRun',
            'logPath',
            'logExists',
            'logModified',
            'logTail',
            'recentLogs',
            'recipients',
            'emailableRecipients',
            'warnings',
            'channels',
            'milestones',
            'digestMailer',
            'digestTransport'
        ));
    }

    /**
     * Filter birthdays by month
     *
     * Open to every signed-in member: it only re-renders the list they already
     * have on birthdays.celebrations. The month form is not where the split
     * lives - the reminder status is (see status() above).
     */
    public function filter(Request $request)
    {
        $user = Auth::user();
        $campusId = $user->campus_id ?? 0;
        $month = $request->get('month', now()->month);
        $monthName = Carbon::create()->month($month)->format('F');

        $birthdays = DB::table('birthday as b')
            ->join('tiu_member as m', 'b.tiu_member_id', '=', 'm.tiu_member_id')
            ->forCurrentCampus('m.campus_id', $campusId)
            ->whereMonth('b.birthday', $month)
            ->select('b.*', 'm.first_name', 'm.last_name', 'm.phone_number', 'm.email')
            ->orderByRaw("DAY(b.birthday)")
            ->get();

        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = Carbon::create()->month($i)->format('F');
        }

        // Keep the reader on the list they started from after a month change,
        // and the status button with the staff list only.
        $listRoute = $this->canManageReminders() ? 'birthdays.index' : 'birthdays.celebrations';
        $showReminderStatus = $this->canManageReminders() && $this->canViewReminderStatus();

        return view('birthdays.index', compact('birthdays', 'monthName', 'months', 'month', 'listRoute', 'showReminderStatus'));

    }

    /**
     * Send birthday wish via WhatsApp
     */
    public function sendWish(Request $request)
    {
        $phone = $request->phone;
        $name = $request->name;
        
        // Format phone number for WhatsApp
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strpos($phone, '0') === 0) {
            $phone = '234' . substr($phone, 1);
        } elseif (strpos($phone, '234') !== 0) {
            $phone = '234' . $phone;
        }
        
        $message = "🎂 *Happy Birthday {$name}!* 🎉\n\n";
        $message .= "May this new year bring you joy, peace, and God's abundant blessings.\n\n";
        $message .= "Warm regards,\n*TCN Ikorodu*";
        
        return response()->json([
            'success' => true,
            'message' => 'Opening WhatsApp...',
            'whatsapp_url' => "https://wa.me/{$phone}?text=" . urlencode($message)
        ]);
    }

    /**
     * Birthday pages are for the staff that keeps them: the same four roles the
     * sidebar link is shown to.
     */
    protected function canManageReminders(): bool
    {
        $role = Auth::user()->member_role ?? '';

        return in_array($role, self::STAFF_ROLES, true);
    }

    /**
     * The reminder status page belongs to the Super User alone - not to Admin,
     * not to the Welcome Center.
     *
     * It turns the digest inside out: recipients and their addresses, the
     * mailer and its transport, the Telegram switch, the raw log tail. Whoever
     * can read that can also see every address the church mails, so it stays
     * with one role. Everybody else, Admin included, is refused; they still
     * read the birthday list itself, they just never see the button on it.
     */
    protected function canViewReminderStatus(): bool
    {
        $role = Auth::user()->member_role ?? '';

        return $role === 'Super User';
    }

    /**
     * The things that silently stop a digest: no cron, a log mailer, nobody to
     * send to, and a milestone list that reminds weeks ahead of the birthday.
     *
     * @param  array<int, int>  $milestones  Config milestones, days before the birthday.
     * @return array<int, string>
     */
    protected function reminderWarnings(bool $logExists, ?Carbon $logModified, Carbon $today, int $emailableRecipients, string $digestMailer = '', array $milestones = []): array
    {
        $warnings = [];

        if (! $logExists) {
            $warnings[] = 'There is no storage/logs/birthday-reminders.log yet: the scheduler has never run birthdays:remind on this server. Check the cron entry below.';
        } elseif ($logModified === null || $logModified->copy()->startOfDay()->lt($today->copy()->startOfDay())) {
            $lastSeen = $logModified ? $logModified->format('D, d M Y g:i A') : 'an unknown date';
            $warnings[] = 'The last run logged on '.$lastSeen.', before today - today\'s digest has not gone out yet.';
        }

        $digestMailer = $digestMailer !== '' ? $digestMailer : (string) config('mail.default');
        $digestTransport = config('mail.mailers.'.$digestMailer);

        if (! is_array($digestTransport)) {
            $warnings[] = 'The digest mailer "'.$digestMailer.'" is not defined in config/mail.php. Check BIRTHDAY_MAIL_MAILER / MAIL_MAILER.';
        } elseif (in_array($digestTransport['transport'] ?? '', ['log', 'array'], true)) {
            $warnings[] = 'The digest mailer "'.$digestMailer.'" uses the "'.($digestTransport['transport'] ?? '').'" transport, so e-mail is written to a log instead of delivered. Set a real transport in .env (MAIL_MAILER, or BIRTHDAY_MAIL_MAILER=birthday for a dedicated mailbox).';
        }

        if (trim((string) config('birthday.from.address')) === '') {
            $warnings[] = 'The digest "From" address is empty (BIRTHDAY_MAIL_FROM_ADDRESS / MAIL_FROM_ADDRESS); many mail servers reject a message without a sender.';
        }

        if (in_array('telegram', (array) config('birthday.channels', []), true)
            && trim((string) config('birthday.telegram.bot_token', '')) === ''
            && empty(array_filter((array) config('birthday.telegram.chat_ids', [])))) {
            $warnings[] = 'The telegram channel is enabled but no bot token or chat ids are set, so those messages are skipped.';
        }

        $earlyMilestones = array_values(array_filter($milestones, function ($day) {
            return (int) $day > 7;
        }));

        if (!empty($earlyMilestones)) {
            $warnings[] = 'BIRTHDAY_DAYS contains '.implode(', ', array_map('intval', $earlyMilestones))
                .' day(s) before the birthday, so the digest goes out weeks early ("... has a birthday in 60 days"). '
                .'The Welcome Center digest is meant for 3, 2 and 1 day(s) to go: set BIRTHDAY_DAYS=3,2,1 in .env, then '
                .'run php artisan config:clear (or config:cache) so the change is picked up.';
        }

        if ($emailableRecipients === 0) {
            $warnings[] = 'No reminder recipient has an e-mail address, so the e-mail channel would reach nobody.';
        }

        return $warnings;
    }

    /**
     * The last lines of a log file without loading the whole thing (the digest
     * log is appended to every day and never rotated).
     *
     * @return array<int, string>
     */
    protected function tailLines(string $path, int $lines = 60): array
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $size = (int) filesize($path);
        $read = min($size, 16384);

        if ($read > 0) {
            fseek($handle, -$read, SEEK_END);
        }

        $contents = (string) fread($handle, $read);
        fclose($handle);

        $contents = trim($contents);

        if ($contents === '') {
            return [];
        }

        return array_slice(preg_split('/\r\n|\r|\n/', $contents), -$lines);
    }
}
