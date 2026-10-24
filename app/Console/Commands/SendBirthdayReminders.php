<?php

namespace App\Console\Commands;

use App\Services\BirthdayReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Sends the upcoming-birthday reminders to the Welcome Center admins.
 *
 * Runs from the scheduler (see app/Console/Kernel.php) three days, two days and
 * one day before each birthday, and can be run by hand for testing:
 *
 *   php artisan birthdays:remind --dry-run
 *   php artisan birthdays:remind --days=3 --campus=1 --channel=mail --force
 */
class SendBirthdayReminders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'birthdays:remind
                            {--days= : Comma separated milestones of days before the birthday (default from config: 3,2,1)}
                            {--campus= : Only process one campus id}
                            {--channel=* : Restrict to specific channels (mail, database, telegram)}
                            {--force : Send even when this milestone was already delivered}
                            {--dry-run : Show exactly what would be sent without sending anything}';

    /**
     * @var string
     */
    protected $description = 'E-mail + push upcoming birthdays to the Welcome Center admins';

    /**
     * The channels this command knows how to deliver on.
     *
     * @var array<int, string>
     */
    protected $availableChannels = ['mail', 'database', 'telegram'];

    /**
     * @return int
     */
    public function handle(BirthdayReminderService $service)
    {
        if (!$this->ensureTables()) {
            return 1;
        }

        $days = $this->resolveDays();
        $channels = $this->resolveChannels();
        $dryRun = (bool) $this->option('dry-run');
        $campusId = $this->option('campus') !== null ? (int) $this->option('campus') : null;

        $this->info('🎂 Welcome Center birthday reminders'.($dryRun ? ' (dry run - nothing will be sent)' : ''));

        try {
            $summary = $service->run($days, $channels, (bool) $this->option('force'), $dryRun, $campusId);
        } catch (\Exception $e) {
            $this->error('Birthday reminders failed: '.$e->getMessage());

            return 1;
        }

        foreach ($service->logLines() as $line) {
            $this->line($line);
        }

        $this->line('');
        // A dry run never sends, so it must never print "sent": the status page
        // shows this output verbatim next to the real "Recent digests" table.
        $this->line(sprintf(
            'Summary: %d digest(s) %s, %d skipped, %d failed | %d birthday(s) | %d recipient(s)',
            $dryRun ? $summary['would'] : $summary['sent'],
            $dryRun ? 'would be sent' : 'sent',
            $summary['skipped'],
            $summary['failed'],
            $summary['birthdays'],
            $summary['recipients']
        ));

        if ($dryRun) {
            if ($summary['would'] === 0 && $summary['skipped'] === 0) {
                $this->warn('Nothing would be sent. Check that a birthday exists for the requested day(s).');
            }
        } elseif ($summary['sent'] === 0 && $summary['skipped'] === 0) {
            $this->warn('Nothing was delivered. Check that a birthday exists for the requested day(s).');
        }

        return $summary['failed'] > 0 ? 1 : 0;
    }

    /**
     * Make sure the two reminder tables exist.
     *
     * The legacy database was seeded outside of the migrations table, so a plain
     * `php artisan migrate` can abort on an old migration before reaching these
     * files. Applying them one path at a time is idempotent and only ever touches
     * the two tables this feature owns.
     *
     * @return bool
     */
    protected function ensureTables(): bool
    {
        $migrations = [
            'birthday_reminder_logs' => 'database/migrations/2026_10_09_000001_create_birthday_reminder_logs_table.php',
            'app_notifications' => 'database/migrations/2026_10_09_000002_create_app_notifications_table.php',
            'member_tracking_followup_archive' => 'database/migrations/2026_10_14_000001_create_member_tracking_followup_archive_table.php',
            // The digest also goes to the admins' phones (docs/PUSH.md). One file
            // creates both tables, so the second entry finds its table present.
            'push_subscriptions' => 'database/migrations/2026_10_22_000001_create_push_subscriptions_and_messages_tables.php',
            'push_messages' => 'database/migrations/2026_10_22_000001_create_push_subscriptions_and_messages_tables.php',
        ];

        foreach ($migrations as $table => $path) {
            if (Schema::hasTable($table)) {
                continue;
            }

            $this->warn("Table '{$table}' is missing - applying {$path} ...");

            try {
                Artisan::call('migrate', ['--force' => true, '--path' => $path]);
            } catch (\Exception $e) {
                $this->error("Could not create '{$table}': ".$e->getMessage());

                return false;
            }

            if (!Schema::hasTable($table)) {
                $this->error("Migration for '{$table}' did not create the table. Run manually: php artisan migrate --path={$path}");

                return false;
            }

            $this->info("   created {$table}");
        }

        return true;
    }

    /**
     * Milestones to process, from --days or config/birthday.php.
     *
     * Scheduled runs must stay at the Welcome Center milestones (3, 2, 1 days
     * to go). A wider lead time is only useful for a manual test, so it is
     * announced instead of silently mailing the admins weeks in advance.
     *
     * @return array<int, int>
     */
    protected function resolveDays(): array
    {
        $raw = $this->option('days');

        if ($raw === null || trim((string) $raw) === '') {
            $raw = implode(',', (array) config('birthday.days', [3, 2, 1]));
        }

        $requested = array_map('intval', array_map('trim', explode(',', (string) $raw)));

        $days = array_values(array_filter(
            $requested,
            function ($day) {
                return $day >= 0 && $day <= 60;
            }
        ));

        $outOfRange = array_values(array_diff($requested, $days));

        if (!empty($outOfRange)) {
            $this->warn('Ignoring milestone(s) outside 0-60 days: '.implode(', ', $outOfRange).'.');
        }

        if (empty($days)) {
            $days = [3, 2, 1];
        }

        // Longest lead time first, so a 3,2,1 config reads "3, 2, 1" here too
        // (ascending order made the preview say "1, 2, 3" under a page that
        // showed "3, 2, 1").
        rsort($days);

        $days = array_values(array_unique($days));

        $earlyMilestones = array_values(array_filter($days, function ($day) {
            return $day > 7;
        }));

        if (!empty($earlyMilestones)) {
            $this->warn(
                'Milestone(s) beyond 7 days requested: '.implode(', ', $earlyMilestones).'. '
                .'Scheduled digests are meant for 3,2,1 days (BIRTHDAY_DAYS); a wider lead time is only expected in a manual test.'
            );
        }

        return $days;
    }

    /**
     * Channels to deliver on, from --channel or config/birthday.php.
     *
     * @return array<int, string>
     */
    protected function resolveChannels(): array
    {
        $requested = array_values(array_filter(array_map('strtolower', array_map('trim', (array) $this->option('channel')))));

        if (empty($requested)) {
            $requested = array_map('strtolower', (array) config('birthday.channels', $this->availableChannels));
        }

        $unknown = array_diff($requested, $this->availableChannels);

        foreach ($unknown as $channel) {
            $this->warn("Unknown channel '{$channel}' ignored.");
        }

        $channels = array_values(array_intersect($this->availableChannels, $requested));

        if (empty($channels)) {
            $this->warn('No known channel requested, falling back to: '.implode(', ', $this->availableChannels));

            $channels = $this->availableChannels;
        }

        return $channels;
    }
}
