<?php
// auto_unassign.php - Standalone file for cPanel cron job
// Call via HTTP: https://yourdomain.com/auto_unassign.php
// Local test: http://localhost/Smart-Church/public/auto_unassign.php

// Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Use Laravel's database and settings
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\FirstTimer;
use App\Models\MemberTrackingFollowup;

// Set timezone
date_default_timezone_set('Africa/Lagos');

echo "<h1>Starting Auto-Unassign Process...</h1>";

// 1. Find all first-timers currently assigned to a guide
$trackings = MemberTrackingFollowup::where('tiu_member_id', '!=', 0)->get();

if ($trackings->isEmpty()) {
    echo "<p style='color:green;'>No currently assigned first-timers to check. Process complete.</p>";
    exit();
}

$unassignedCount = 0;
$today = now();

// 2. Loop through each assigned record
foreach ($trackings as $tracking) {
    $firstTimer = FirstTimer::find($tracking->first_timer_id);

    if (!$firstTimer) {
        echo "<p style='color:orange;'>First timer ID {$tracking->first_timer_id} not found. Skipping.</p>";
        continue;
    }

    // Determine the registration date
    $registrationDate = $firstTimer->register_date
        ?? $firstTimer->timeStamp_registered
        ?? $tracking->created_at;

    if (!$registrationDate) {
        echo "<p style='color:orange;'>No registration date found for First Timer ID: {$tracking->first_timer_id}. Skipping.</p><hr>";
        continue;
    }

    // Calculate weeks passed
    $weeksPassed = (int) floor($today->diffInDays($registrationDate) / 7);

    echo "<p>Checking First Timer ID: <strong>{$tracking->first_timer_id}</strong>... Registered on: {$registrationDate}. Weeks passed: <strong>{$weeksPassed}</strong>.</p>";

    // 3. Check if 8+ weeks have passed
    if ($weeksPassed >= 8) {
        echo "<p style='color:blue;'>&raquo; ACTION: Unassigning First Timer ID: {$tracking->first_timer_id}...</p>";

        try {
            DB::beginTransaction();

            // Delete the tracking record
            $tracking->delete();

            // Update first_timer status to 'Unassigned'
            $firstTimer->update([
                'status'             => 'Unassigned',
                'status_change_date' => now(),
            ]);

            DB::commit();

            $unassignedCount++;
            echo "<p style='color:green;'>&raquo; SUCCESS: Unassigned First Timer ID: {$tracking->first_timer_id}.</p><hr>";
        } catch (\Exception $e) {
            DB::rollBack();
            echo "<p style='color:red;'>&raquo; ERROR: Database error for First Timer ID: {$tracking->first_timer_id}. Transaction rolled back. Error: " . $e->getMessage() . "</p><hr>";
        }
    } else {
        echo "<p>&raquo; No action needed. Less than 8 weeks.</p><hr>";
    }
}

echo "<h2>Process Complete.</h2>";
echo "<p style='font-weight:bold;'>Total records unassigned: {$unassignedCount}</p>";
