<?php
// auto_unassign.php - Standalone file for the cPanel cron job
// Call via HTTP: https://yourdomain.com/auto_unassign.php
// Local test: http://localhost/Smart-Church/public/auto_unassign.php
// Add ?dry-run=1 to see who would be released without changing anything.
// The follow-up of every released first timer is kept in
// member_tracking_followup_archive (see /weeklist/guide-history).

// Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Use Laravel's database and settings
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\GuideArchiveService;

// Set timezone
date_default_timezone_set('Africa/Lagos');

// ?dry-run=1 reports who would be released without writing anything.
$dryRun = isset($_GET['dry-run']) || isset($_GET['dry_run']);

echo "<h1>Starting Auto-Unassign Process...</h1>";

if ($dryRun) {
    echo "<p style='color:orange;font-weight:bold;'>DRY RUN: nothing will be written.</p>";
}

// The release itself lives in GuideArchiveService: it copies the follow-up into
// member_tracking_followup_archive before deleting it, so the last guide and the
// last comment are still visible under /weeklist/guide-history.
$result = $app->make(GuideArchiveService::class)->unassignAtEightWeeks($dryRun);

foreach ($result['lines'] as $line) {
    $style = 'color:#333;';

    if (strpos($line, '>> ERROR:') === 0) {
        $style = 'color:red;';
    } elseif (strpos($line, '>> SUCCESS:') === 0) {
        $style = 'color:green;';
    } elseif (strpos($line, '>> WARNING:') === 0) {
        $style = 'color:orange;';
    } elseif (strpos($line, '>>') === 0) {
        $style = 'color:blue;';
    }

    echo "<p style='{$style}'>" . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "</p><hr>";
}

echo "<h2>Process Complete.</h2>";
echo "<p style='font-weight:bold;'>Total records unassigned: {$result['unassigned']}</p>";
echo "<p style='font-weight:bold;'>Follow-ups kept in the archive: {$result['archived']}</p>";
