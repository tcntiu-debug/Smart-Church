<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the work a guide did for a first timer after that guide is released.
 *
 * `member_tracking_followup` is the only place that stores both the guide
 * (`tiu_member_id`) and what they recorded (`followup_response_new`, plus the
 * legacy `followup_response` / `tiu_member_comment` columns). The 8-week
 * auto-unassign used to delete that row outright, so the last guide and the last
 * comment disappeared with it.
 *
 * The row is now copied into `member_tracking_followup_archive` before it is
 * deleted, which is what `/weeklist/guide-history` reads.
 */
class GuideArchiveService
{
    /**
     * Live follow-up table (guide + everything they recorded).
     */
    public const SOURCE_TABLE = 'member_tracking_followup';

    /**
     * Where released follow-ups are kept.
     */
    public const ARCHIVE_TABLE = 'member_tracking_followup_archive';

    /**
     * Migration that creates the archive table, applied on demand for the
     * SFTP-only hosts that cannot run `php artisan migrate` (the same mechanism
     * `app:sync-schema` and `birthdays:remind` use).
     */
    public const MIGRATION = 'database/migrations/2026_10_14_000001_create_member_tracking_followup_archive_table.php';

    /**
     * Reason recorded on rows released by the weekly retention rule.
     */
    public const REASON_EIGHT_WEEKS = '8-week auto-unassign';

    /**
     * How many weeks a first timer keeps a guide.
     */
    public const RETENTION_WEEKS = 8;

    /**
     * Columns carried over from the source row, but only when the source row
     * actually has them: this table is older than the migrations, so a column
     * can be missing (production has no `department_id`, for instance).
     */
    protected const COPY_COLUMNS = [
        'tracking_id',
        'first_timer_id',
        'tiu_member_id',
        'department_id',
        'followup_mode',
        'followup_date_time',
        'followup_response',
        'followup_rank',
        'tiu_member_comment',
        'timeStamp_registered',
        'followup_response_new',
    ];

    /**
     * Create the archive table when it is missing.
     *
     * @return bool  true when the table is available afterwards
     */
    public function ensureArchiveTable(): bool
    {
        if (Schema::hasTable(self::ARCHIVE_TABLE)) {
            return true;
        }

        $path = base_path(self::MIGRATION);

        if (! is_file($path)) {
            Log::error('Archive table missing and its migration file was not found: ' . self::MIGRATION);

            return false;
        }

        try {
            Artisan::call('migrate', ['--force' => true, '--path' => [$path]]);
        } catch (\Throwable $e) {
            Log::error('Could not create the follow-up archive table: ' . $e->getMessage());

            return false;
        }

        return Schema::hasTable(self::ARCHIVE_TABLE);
    }

    /**
     * Copy one follow-up row into the archive.
     *
     * @param  object  $row     a row of self::SOURCE_TABLE
     * @param  string  $reason  why the follow-up is being retired
     */
    public function archiveRow(object $row, string $reason): bool
    {
        if (! $this->ensureArchiveTable()) {
            return false;
        }

        $now = now();

        $payload = [
            'archive_reason' => $reason,
            'archived_at'    => $now,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];

        foreach (self::COPY_COLUMNS as $column) {
            if (property_exists($row, $column)) {
                $payload[$column] = $row->{$column};
            }
        }

        try {
            return (bool) DB::table(self::ARCHIVE_TABLE)->insert($payload);
        } catch (\Throwable $e) {
            Log::error('Could not archive follow-up row: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Release every first timer who has had a guide for self::RETENTION_WEEKS or
     * more: archive the follow-up row, delete it, and mark the first timer
     * "Unassigned".
     *
     * Mirrors the old `firsttimers:auto-unassign` command, which deleted the row
     * without keeping a copy - the archive is the only difference.
     *
     * @param  bool  $dryRun             report what would happen without writing anything
     * @param  array $onlyFirstTimerIds  release only these first timers (empty = every one
     *                                   that qualifies); used for a single manual release
     *                                   and by the suite, which must not touch the rest of
     *                                   the table
     * @return array{checked: int, unassigned: int, archived: int, skipped: int, dry_run: bool, lines: array<int, string>}
     */
    public function unassignAtEightWeeks(bool $dryRun = false, array $onlyFirstTimerIds = []): array
    {
        $result = [
            'checked'    => 0,
            'unassigned' => 0,
            'archived'   => 0,
            'skipped'    => 0,
            'dry_run'    => $dryRun,
            'lines'      => [],
        ];

        $today = now();

        // first_timer_id = 0 rows are orphans left by old imports: they have no
        // first timer to release, so they are not even checked.
        $query = DB::table(self::SOURCE_TABLE)
            ->where('tiu_member_id', '!=', 0)
            ->where('first_timer_id', '>', 0);

        if (! empty($onlyFirstTimerIds)) {
            $query->whereIn('first_timer_id', array_map('intval', $onlyFirstTimerIds));
        }

        $trackings = $query->get();

        if ($trackings->isEmpty()) {
            $result['lines'][] = 'No currently assigned first-timers to check. Process complete.';

            return $result;
        }

        foreach ($trackings as $tracking) {
            $result['checked']++;

            $firstTimer = DB::table('first_timer')
                ->where('first_timer_id', $tracking->first_timer_id)
                ->first();

            if (! $firstTimer) {
                $result['skipped']++;
                $result['lines'][] = "First timer ID {$tracking->first_timer_id} not found. Skipping.";

                continue;
            }

            $registrationDate = $firstTimer->register_date
                ?? ($firstTimer->timeStamp_registered ?? ($tracking->created_at ?? null));

            if (! $registrationDate) {
                $result['skipped']++;
                $result['lines'][] = "No registration date found for First Timer ID: {$tracking->first_timer_id}. Skipping.";

                continue;
            }

            $weeksPassed = (int) floor($today->diffInDays($registrationDate) / 7);

            $result['lines'][] = "Checking First Timer ID: {$tracking->first_timer_id}... Registered on: {$registrationDate}. Weeks passed: {$weeksPassed}.";

            if ($weeksPassed < self::RETENTION_WEEKS) {
                $result['lines'][] = '>> No action needed. Less than 8 weeks.';

                continue;
            }

            if ($dryRun) {
                $result['unassigned']++;
                $result['lines'][] = ">> DRY RUN: would archive and unassign First Timer ID: {$tracking->first_timer_id}.";

                continue;
            }

            $result['lines'][] = ">> ACTION: Unassigning First Timer ID: {$tracking->first_timer_id}...";

            try {
                DB::beginTransaction();

                $archived = $this->archiveRow($tracking, self::REASON_EIGHT_WEEKS);

                if (! $archived) {
                    // Never drop a guide's work: without the archived copy the
                    // delete below would lose the last comment for good, so this
                    // first timer keeps its guide and is retried next week.
                    DB::rollBack();

                    $result['skipped']++;
                    $result['lines'][] = ">> ERROR: the follow-up of First Timer ID: {$tracking->first_timer_id} could not be archived, so it was NOT released (see the log).";

                    continue;
                }

                DB::table(self::SOURCE_TABLE)
                    ->where('tracking_id', $tracking->tracking_id)
                    ->delete();

                DB::table('first_timer')
                    ->where('first_timer_id', $tracking->first_timer_id)
                    ->update([
                        'status'             => 'Unassigned',
                        'status_change_date' => now(),
                    ]);

                DB::commit();

                $result['unassigned']++;
                $result['archived']++;
                $result['lines'][] = ">> SUCCESS: Unassigned First Timer ID: {$tracking->first_timer_id}.";
            } catch (\Exception $e) {
                DB::rollBack();
                $result['lines'][] = ">> ERROR: Database error for First Timer ID: {$tracking->first_timer_id}. Transaction rolled back. Error: " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * The last thing a guide recorded for a first timer.
     *
     * Read from `followup_response_new` (the guide's task log) and falling back
     * to the legacy comment columns, so an archived row can still answer "who
     * guided them, and what did they say?".
     *
     * @param  object  $row  a row of self::SOURCE_TABLE or self::ARCHIVE_TABLE
     * @return array{comment: ?string, outcome: ?string, task: ?string, logged_at: ?string, week: ?string, guide_id: mixed}
     */
    public static function lastActivity(object $row): array
    {
        $activity = [
            'comment'   => null,
            'outcome'   => null,
            'task'      => null,
            'logged_at' => null,
            'week'      => null,
            'guide_id'  => null,
        ];

        $withComment = null;
        $newest = null;

        foreach (self::logEntries($row) as $entry) {
            if ($newest === null || ($entry['logged_at'] ?? '') >= ($newest['logged_at'] ?? '')) {
                $newest = $entry;
            }

            if ($entry['comment'] === null) {
                continue;
            }

            if ($withComment === null || ($entry['logged_at'] ?? '') >= ($withComment['logged_at'] ?? '')) {
                $withComment = $entry;
            }
        }

        $activity = $withComment ?? $newest ?? $activity;

        if ($activity['comment'] === null) {
            $activity['comment'] = self::plainText($row->tiu_member_comment ?? null);
        }

        if ($activity['comment'] === null) {
            $activity['comment'] = self::plainText($row->followup_response ?? null);
        }

        return $activity;
    }

    /**
     * Every logged entry of a guide's task log.
     *
     * Shape: {"week1": {"task 1": {"text": ..., "logs": [{timestamp, guide_id,
     * outcome, comment}]}}, "week2": {...}} - but only the pieces that are there
     * are used, because not every record is shaped the same.
     *
     * @return array<int, array{comment: ?string, outcome: ?string, task: ?string, logged_at: ?string, week: ?string, guide_id: mixed}>
     */
    protected static function logEntries(object $row): array
    {
        $decoded = json_decode((string) ($row->followup_response_new ?? ''), true);

        if (! is_array($decoded)) {
            return [];
        }

        $entries = [];

        foreach ($decoded as $week => $tasks) {
            if (! is_array($tasks)) {
                continue;
            }

            foreach ($tasks as $task) {
                if (! is_array($task) || ! isset($task['logs']) || ! is_array($task['logs'])) {
                    continue;
                }

                foreach ($task['logs'] as $log) {
                    if (! is_array($log)) {
                        continue;
                    }

                    $comment = trim((string) ($log['comment'] ?? ''));
                    $stamp = trim((string) ($log['timestamp'] ?? ''));

                    if ($comment === '' && $stamp === '') {
                        continue;
                    }

                    $entries[] = [
                        'comment'   => $comment !== '' ? $comment : null,
                        'outcome'   => $log['outcome'] ?? null,
                        'task'      => self::plainText($task['text'] ?? null),
                        'logged_at' => $stamp !== '' ? $stamp : null,
                        'week'      => is_string($week) ? $week : null,
                        'guide_id'  => $log['guide_id'] ?? null,
                    ];
                }
            }
        }

        return $entries;
    }

    /**
     * Human-readable one-liner out of an HTML-ish task text (or comment).
     */
    public static function plainText($value, int $limit = 160): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5);
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $text)));

        if ($text === '' || $text === '{}') {
            return null;
        }

        return strlen($text) > $limit ? substr($text, 0, $limit - 3) . '...' : $text;
    }
}
