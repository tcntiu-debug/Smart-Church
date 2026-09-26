<?php

namespace App\Console\Commands;

use App\Models\FirstTimer;
use App\Models\MemberTrackingFollowup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoUnassignFirstTimers extends Command
{
    protected $signature = 'firsttimers:auto-unassign';
    protected $description = 'Auto-unassign first-timers who have been assigned for 8+ weeks (runs weekly)';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Starting Auto-Unassign Process...');

        $trackings = MemberTrackingFollowup::where('tiu_member_id', '!=', 0)->get();

        if ($trackings->isEmpty()) {
            $this->info('No currently assigned first-timers to check. Process complete.');
            return 0;
        }

        $unassignedCount = 0;
        $today = now();

        foreach ($trackings as $tracking) {
            $firstTimer = FirstTimer::find($tracking->first_timer_id);

            if (!$firstTimer) {
                $this->warn("First timer ID {$tracking->first_timer_id} not found. Skipping.");
                continue;
            }

            $registrationDate = $firstTimer->register_date
                ?? $firstTimer->timeStamp_registered
                ?? $tracking->created_at;

            if (!$registrationDate) {
                $this->warn("No registration date found for First Timer ID: {$tracking->first_timer_id}. Skipping.");
                continue;
            }

            $weeksPassed = (int) floor($today->diffInDays($registrationDate) / 7);

            $this->line("Checking First Timer ID: {$tracking->first_timer_id}... Registered on: {$registrationDate}. Weeks passed: {$weeksPassed}.");

            if ($weeksPassed >= 8) {
                $this->line(">> ACTION: Unassigning First Timer ID: {$tracking->first_timer_id}...");

                try {
                    DB::beginTransaction();

                    $tracking->delete();

                    $firstTimer->update([
                        'status'             => 'Unassigned',
                        'status_change_date' => now(),
                    ]);

                    DB::commit();

                    $unassignedCount++;
                    $this->info(">> SUCCESS: Unassigned First Timer ID: {$tracking->first_timer_id}.");
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error(">> ERROR: Database error for First Timer ID: {$tracking->first_timer_id}. Transaction rolled back. Error: " . $e->getMessage());
                }
            } else {
                $this->line(">> No action needed. Less than 8 weeks.");
            }
        }

        $this->info('Process Complete.');
        $this->info("Total records unassigned: {$unassignedCount}");

        return 0;
    }
}
