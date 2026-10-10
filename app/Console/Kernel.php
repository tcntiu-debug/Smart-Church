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
