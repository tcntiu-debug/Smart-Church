<?php

namespace App\Console\Commands;

use App\Services\GuideArchiveService;
use Illuminate\Console\Command;

class AutoUnassignFirstTimers extends Command
{
    protected $signature = 'firsttimers:auto-unassign
                            {--dry-run : Report who would be released without changing anything}';

    protected $description = 'Auto-unassign first-timers who have been assigned for 8+ weeks (runs weekly), archiving their follow-up';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Starting Auto-Unassign Process...');

        if ($dryRun) {
            $this->warn('DRY RUN: nothing will be written.');
        }

        $result = app(GuideArchiveService::class)->unassignAtEightWeeks($dryRun);

        foreach ($result['lines'] as $line) {
            if (strpos($line, '>> ERROR:') === 0) {
                $this->error($line);
            } elseif (strpos($line, '>>') === 0) {
                $this->info($line);
            } else {
                $this->line($line);
            }
        }

        $this->info('Process Complete.');
        $this->info("Total records unassigned: {$result['unassigned']}");

        // The follow-up row is copied here before it is deleted, so the last
        // guide and the last comment survive the release (see
        // GuideArchiveService and /weeklist/guide-history).
        $this->info("Follow-ups kept in the archive: {$result['archived']}");

        // A first timer is only released once its copy is in the archive, so
        // the two totals above always match. Skipped rows are the ones left
        // alone: no first timer for the follow-up, no registration date, or an
        // archive that failed (logged as an error in the lines above).
        if ($result['skipped'] > 0) {
            $this->warn("Skipped without releasing: {$result['skipped']} (see the lines above).");
        }

        return 0;
    }
}
