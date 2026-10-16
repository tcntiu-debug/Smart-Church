<?php

namespace App\Services;

use Illuminate\Support\Collection;
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
