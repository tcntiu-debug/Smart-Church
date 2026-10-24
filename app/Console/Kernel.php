<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Welcome Center birthday reminders - e-mail + push digest sent at the
        // "3 days to go", "2 days to go" and "1 day to go" milestones.
        // A single cPanel cron running `php artisan schedule:run` every minute
        // drives this (and every future schedule) - see docs/CPANEL-DEPLOYMENT.md.
        $schedule->command('birthdays:remind')
            ->dailyAt(config('birthday.send_at', '07:00'))
            ->timezone(config('birthday.timezone', 'Africa/Lagos'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/birthday-reminders.log'));

        // Schema sync: the deploy pipeline is SFTP-only and cannot run artisan on
        // the server, so this applies the migrations the server needs (it creates
        // the notification tables and drops the tables of the retired Transport /
        // FOF modules). It is idempotent, so it is safe to keep scheduled: once
        // everything is in step every later tick reports "Nothing to migrate".
        // The same command is part of `php artisan app:post-deploy`.
        $schedule->command('app:sync-schema')
            ->dailyAt('03:20')
            ->timezone(config('birthday.timezone', 'Africa/Lagos'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/schema-sync.log'));

        // 8-week retention: a first timer keeps a guide for eight weeks and is then
        // released. The follow-up is copied into member_tracking_followup_archive
        // before the row is deleted, so /weeklist/guide-history can still show the
        // last guide and the last comment. Weekly, and safe to run twice: a
        // released first timer no longer has a member_tracking_followup row, so a
        // second run finds nothing to do - that is why the hand-made cPanel cron
        // hitting public/auto_unassign.php (which runs the same service) can stay
        // in place without clashing with this entry.
        $schedule->command('firsttimers:auto-unassign')
            ->weeklyOn(1, '04:10')
            ->timezone(config('birthday.timezone', 'Africa/Lagos'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/auto-unassign.log'));

        // Web Push: the alerts NotificationService has queued, delivered to the
        // members' phones (docs/PUSH.md). Every minute, because the outbox table
        // IS the queue - the server has no queue worker, and the same per-minute
        // `schedule:run` cron already drives everything else here. withoutOverlapping
        // keeps a slow push service from stacking cron processes.
        $schedule->command('push:send')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/push.log'));

        // Once a day: forget delivered alerts and the devices a push service has
        // revoked (404/410), so neither table grows for ever.
        $schedule->command('push:send --prune')
            ->dailyAt('03:45')
            ->timezone(config('birthday.timezone', 'Africa/Lagos'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/push.log'));

        // $schedule->command('inspire')->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
