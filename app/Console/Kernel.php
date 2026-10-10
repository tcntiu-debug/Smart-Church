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

        // Retired-module clean-up: applies the Transport / FOF drop-migrations so
        // the tables of the removed modules disappear from the database without
        // anyone logging into phpMyAdmin (the SFTP-only account cannot run artisan
        // from the deploy pipeline). The command is idempotent, so it is safe to
        // keep scheduled: after the first successful run every later tick reports
        // "Nothing to migrate". It is also part of `php artisan app:post-deploy`.
        $schedule->command('app:retire-legacy')
            ->dailyAt('03:20')
            ->timezone(config('birthday.timezone', 'Africa/Lagos'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/legacy-retirement.log'));

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
