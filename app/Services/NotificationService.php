<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Writes the in-app notifications the navbar bell shows (`app_notifications`).
 *
 * App\Services\BirthdayReminderService writes the birthday digests into the same
 * table; this service is the entry point for the assignment alerts:
 *
 *   * caller_assignment - a caller was handed first timers to call
 *     (WeekListController: the "View Call List" modal)
 *   * guide_assignment  - a first timer was handed to a guide
 *     (AdminAssignmentController: drag & drop and the list form;
 *      WeekListController::assignGuides(): the modal's "Assign all callers")
 *   * weekly_task       - a guide's next week of tasks was activated
 *     (MyTaskController, when a week is completed)
 *   * announcement      - a notice was published on the notice board
 *     (AnnouncementController and the token API's AnnouncementController; the
 *      whole church is written in one go, see announce())
 *
 * NotificationController::feed() hands the unread rows back to the browser, which
 * the layout polls so a phone shows the same alert as a system notification. Web
 * only for now: no native app and no Web Push subscription, so an alert is
 * delivered the next time the page asks for it rather than through a push
 * service.
 */
class NotificationService
{
    /**
     * Types this service writes (also the values config/notifications.php lists).
     */
    public const TYPE_CALLER_ASSIGNMENT = 'caller_assignment';

    public const TYPE_GUIDE_ASSIGNMENT = 'guide_assignment';

    public const TYPE_WEEKLY_TASK = 'weekly_task';

    public const TYPE_ANNOUNCEMENT = 'announcement';

    /**
     * Whether notifications of this type should be written at all.
     *
     * @param  string|null  $type
     */
    public function isEnabled($type = null): bool
    {
        if (! config('notifications.enabled', true)) {
            return false;
        }

        if ($type === null) {
            return true;
        }

        $types = array_values((array) config('notifications.types', []));

        return $types === [] || in_array($type, $types, true);
    }

    /**
     * Write one in-app notification.
     *
     * Never throws: a failed alert must not fail the assignment that triggered
     * it, so every problem is logged and reported as false.
     *
     * @param  int  $memberId  Recipient (`tiu_member.tiu_member_id`)
     * @param  string  $type  One of the TYPE_* constants
     * @param  string  $title
     * @param  string|null  $body
     * @param  array  $data  Structured payload; `data.url` is where the click goes
     * @param  int|null  $campusId
     */
    public function notify(int $memberId, string $type, string $title, ?string $body = null, array $data = [], ?int $campusId = null): bool
    {
        // tiu_member_id = 0 is how a released guide is stored; it is nobody.
        if ($memberId <= 0 || trim($title) === '') {
            return false;
        }

        if (! $this->isEnabled($type) || ! $this->tableReady()) {
            return false;
        }

        $timestamp = now();

        try {
            DB::table('app_notifications')->insert([
                'tiu_member_id' => $memberId,
                'campus_id' => $campusId,
                'type' => $type,
                'title' => mb_substr($title, 0, 191),
                'body' => $body,
                'data' => $data === [] ? null : json_encode($data),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('In-app notification failed', [
                'member_id' => $memberId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Write the same notification for several members.
     *
     * @param  array  $memberIds
     * @return int  How many rows were written
     */
    public function notifyMany(array $memberIds, string $type, string $title, ?string $body = null, array $data = [], ?int $campusId = null): int
    {
        $written = 0;

        foreach ($this->normaliseIds($memberIds) as $memberId) {
            if ($this->notify($memberId, $type, $title, $body, $data, $campusId)) {
                $written++;
            }
        }

        return $written;
    }

    /**
     * Write the same notification for a large audience in as few statements as
     * possible.
     *
     * notifyMany() calls notify() once per member, which is right for a handful
     * of assignments but not for "everybody in church" (hundreds of rows, and a
     * schema check each): this writes one INSERT per 250 members. It never
     * throws, exactly like notify(): a notice that cannot ring the bell is still
     * published.
     *
     * @param  array  $memberIds
     * @return int  How many rows were written
     */
    public function notifyAudience(array $memberIds, string $type, string $title, ?string $body = null, array $data = [], ?int $campusId = null): int
    {
        $memberIds = $this->normaliseIds($memberIds);
        $title = trim($title);

        if ($memberIds === [] || $title === '') {
            return 0;
        }

        if (! $this->isEnabled($type) || ! $this->tableReady()) {
            return 0;
        }

        $timestamp = now();
        $title = mb_substr($title, 0, 191);
        $payload = $data === [] ? null : json_encode($data);
        $written = 0;

        foreach (array_chunk($memberIds, 250) as $chunk) {
            $rows = [];

            foreach ($chunk as $memberId) {
                $rows[] = [
                    'tiu_member_id' => $memberId,
                    'campus_id' => $campusId,
                    'type' => $type,
                    'title' => $title,
                    'body' => $body,
                    'data' => $payload,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            try {
                DB::table('app_notifications')->insert($rows);
                $written += count($rows);
            } catch (\Throwable $e) {
                Log::error('In-app notification broadcast failed', [
                    'type' => $type,
                    'members' => count($chunk),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $written;
    }

    /**
     * Every living member of the church, or of one campus when a campus id is
     * given (multi-campus mode only - in single campus mode the announcement row
     * carries no campus_id and the whole church is the audience).
     *
     * @return array<int, int>
     */
    public function announcementAudience(?int $campusId = null): array
    {
        if (! Schema::hasTable('tiu_member')) {
            return [];
        }

        $query = DB::table('tiu_member')
            ->where('tiu_member_id', '>', 0)
            // status 3 is a deleted member, and a missing status is alive.
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', '3');
            });

        if ($campusId) {
            $query->where('campus_id', $campusId);
        }

        return $this->normaliseIds($query->pluck('tiu_member_id')->all());
    }

    /**
     * Ring every member's bell about a notice-board post.
     *
     * @param  int  $announcementId  `announcements.id` of the published row
     * @param  string  $title  The title the author typed
     * @param  string|null  $content  Notice body; shortened for the bell
     * @param  int|null  $campusId  What the announcement row carries (Campus::writeId())
     * @return int  How many members were notified
     */
    public function announce(int $announcementId, string $title, ?string $content = null, ?int $campusId = null): int
    {
        $title = trim($title);

        if ($announcementId <= 0 || $title === '') {
            return 0;
        }

        return $this->notifyAudience(
            $this->announcementAudience($campusId),
            self::TYPE_ANNOUNCEMENT,
            $title,
            $this->excerpt($content),
            ['announcement_id' => $announcementId, 'url' => '/notice'],
            $campusId
        );
    }

    /**
     * One notification per recipient, each with its own message.
     *
     * @param  array<int, array{title: string, body?: string|null, data?: array}>  $messages  Keyed by member id
     */
    public function notifyEach(array $messages, string $type, ?int $campusId = null): int
    {
        $written = 0;

        foreach ($messages as $memberId => $message) {
            if (! is_array($message) || empty($message['title'])) {
                continue;
            }

            if ($this->notify(
                (int) $memberId,
                $type,
                (string) $message['title'],
                $message['body'] ?? null,
                (array) ($message['data'] ?? []),
                $campusId
            )) {
                $written++;
            }
        }

        return $written;
    }

    /**
     * A member's unread notifications, newest first.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function unreadFor(int $memberId, int $limit = 10): Collection
    {
        if ($memberId <= 0 || ! Schema::hasTable('app_notifications')) {
            return collect();
        }

        return DB::table('app_notifications')
            ->where('tiu_member_id', $memberId)
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * How many unread notifications the member has (the navbar badge).
     */
    public function unreadCount(int $memberId): int
    {
        if ($memberId <= 0 || ! Schema::hasTable('app_notifications')) {
            return 0;
        }

        return (int) DB::table('app_notifications')
            ->where('tiu_member_id', $memberId)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Where a click on the notification should land: the `url` the writer put in
     * the payload, the type's default, or the notification centre.
     */
    public function urlFor($notification): string
    {
        $data = $this->payload($notification);
        $url = (string) ($data['url'] ?? config('notifications.urls.'.($notification->type ?? ''), ''));

        return $url !== '' ? url($url) : route('notifications.index');
    }

    /**
     * The JSON payload of a notification row, decoded.
     */
    public function payload($notification): array
    {
        $data = $notification->data ?? null;

        if (is_array($data)) {
            return $data;
        }

        if (is_string($data) && $data !== '') {
            $decoded = json_decode($data, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * "Ada, Bola and 3 others" - a readable head count for a notification body.
     */
    public function names(array $names, int $max = 2): string
    {
        $names = array_values(array_filter(array_map(function ($name) {
            return trim((string) $name);
        }, $names), function ($name) {
            return $name !== '';
        }));

        if ($names === []) {
            return '';
        }

        if (count($names) <= $max) {
            return implode(', ', $names);
        }

        $shown = array_slice($names, 0, $max);
        $rest = count($names) - $max;

        return implode(', ', $shown).' and '.$rest.' other'.($rest === 1 ? '' : 's');
    }

    /**
     * The table is created by the deploy pipeline (`php artisan app:sync-schema`)
     * and by the birthday reminder command. A web request must not run a
     * migration, so a missing table is reported instead.
     */
    protected function tableReady(): bool
    {
        if (Schema::hasTable('app_notifications')) {
            return true;
        }

        Log::warning('app_notifications is missing: run `php artisan app:sync-schema` so in-app notifications can be stored.');

        return false;
    }

    /**
     * A one-paragraph version of a longer text: what the bell shows instead of
     * the whole notice.
     */
    protected function excerpt(?string $text, int $limit = 180): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

        return $text === '' ? null : Str::limit($text, $limit);
    }

    /**
     * @return array<int, int>
     */
    protected function normaliseIds(array $memberIds): array
    {
        $ids = [];

        foreach ($memberIds as $memberId) {
            $memberId = (int) $memberId;

            if ($memberId > 0) {
                $ids[$memberId] = $memberId;
            }
        }

        return array_values($ids);
    }
}
