<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Web Push: the devices a member has allowed, and the delivery of the alerts the
 * bell already holds.
 *
 * Responsibilities, in the order they happen:
 *
 *   1. subscribe()   - remember the browser endpoint the page handed us
 *   2. queue()       - NotificationService turns an in-app alert into an outbox row
 *                      (no network call inside a member's request)
 *   3. sendPending() - `php artisan push:send`, every minute, delivers the rows
 *                      and drops subscriptions a push service has revoked
 *
 * Everything is wrapped: a push that cannot be sent must never fail the
 * assignment that raised it, exactly like App\Services\NotificationService.
 * With no VAPID keys configured (the default), isConfigured() is false, nothing
 * is queued or sent, and the app behaves as it did before this feature existed.
 *
 * See docs/PUSH.md.
 */
class WebPushService
{
    /** @var \App\Services\WebPushCrypto */
    protected $crypto;

    /** @var \GuzzleHttp\ClientInterface|null */
    protected $client;

    public function __construct(?WebPushCrypto $crypto = null, ?ClientInterface $client = null)
    {
        $this->crypto = $crypto ?: new WebPushCrypto();
        $this->client = $client;
    }

    /* ------------------------------------------------------------------ *
     * State
     * ------------------------------------------------------------------ */

    /**
     * Whether this server can push at all: the switch is on and both VAPID halves
     * are present and the right size.
     */
    public function isConfigured(): bool
    {
        if (! config('webpush.enabled', true)) {
            return false;
        }

        $public = (string) config('webpush.public_key', '');
        $private = (string) config('webpush.private_key', '');

        if ($public === '' || $private === '') {
            return false;
        }

        // A half-typed .env must read as "not configured" rather than blow up in
        // the middle of an assignment request.
        try {
            return strlen(WebPushCrypto::decode($public)) === 65
                && strlen(WebPushCrypto::decode($private)) === 32;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * The public half of the VAPID pair - the browser needs it to subscribe.
     * Public by definition (it is sent to every push service), so the layout may
     * hand it to the page.
     */
    public function publicKey(): ?string
    {
        return $this->isConfigured() ? (string) config('webpush.public_key') : null;
    }

    /**
     * A web request must not run a migration, so a missing table is reported
     * instead of thrown (same rule as NotificationService::tableReady()).
     */
    public function tablesReady(): bool
    {
        return Schema::hasTable('push_subscriptions') && Schema::hasTable('push_messages');
    }

    /* ------------------------------------------------------------------ *
     * Subscriptions
     * ------------------------------------------------------------------ */

    /**
     * How many devices this member has allowed.
     */
    public function devicesFor(int $memberId): int
    {
        if ($memberId <= 0 || ! Schema::hasTable('push_subscriptions')) {
            return 0;
        }

        return (int) DB::table('push_subscriptions')
            ->where('tiu_member_id', $memberId)
            ->whereNull('disabled_at')
            ->count();
    }

    /**
     * Store (or refresh) one browser subscription.
     *
     * The endpoint identifies the device, so the row is keyed by its hash: a page
     * that reloads and subscribes again updates the same row instead of growing
     * the table, and a device that has changed hands moves to the new member.
     *
     * @param  array  $data  endpoint, p256dh, auth, content_encoding (from PushSubscription.toJSON())
     * @param  string|null  $userAgent  Which browser/PWA this is
     */
    public function subscribe(int $memberId, array $data, ?string $userAgent = null): bool
    {
        if ($memberId <= 0 || ! $this->tablesReady()) {
            return false;
        }

        $endpoint = trim((string) ($data['endpoint'] ?? ''));
        $p256dh = trim((string) ($data['p256dh'] ?? ''));
        $auth = trim((string) ($data['auth'] ?? ''));

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            return false;
        }

        $hash = hash('sha256', $endpoint);
        $now = now();

        $row = [
            'tiu_member_id' => $memberId,
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'content_encoding' => mb_substr((string) ($data['content_encoding'] ?? 'aes128gcm'), 0, 20),
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
            'last_seen_at' => $now,
            'disabled_at' => null,
            'fail_count' => 0,
            'updated_at' => $now,
        ];

        try {
            $existing = DB::table('push_subscriptions')->where('endpoint_hash', $hash)->first();

            if ($existing) {
                DB::table('push_subscriptions')->where('id', $existing->id)->update($row);

                return true;
            }

            DB::table('push_subscriptions')->insert($row + [
                'endpoint_hash' => $hash,
                'created_at' => $now,
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('Web Push subscription could not be stored', [
                'member_id' => $memberId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Forget one device. Only the owner can remove their own endpoint.
     */
    public function unsubscribe(int $memberId, string $endpoint): bool
    {
        if ($memberId <= 0 || $endpoint === '' || ! Schema::hasTable('push_subscriptions')) {
            return false;
        }

        return DB::table('push_subscriptions')
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->where('tiu_member_id', $memberId)
            ->delete() > 0;
    }

    /* ------------------------------------------------------------------ *
     * Outbox
     * ------------------------------------------------------------------ */

    /**
     * Queue one alert for one member's phones.
     *
     * Called from NotificationService, so it must be cheap and must never throw:
     * with no VAPID keys, no subscription or no table it quietly does nothing.
     */
    public function queue(int $memberId, string $type, string $title, ?string $body = null, ?string $url = null, ?string $tag = null): bool
    {
        return $this->queueMany([$memberId], $type, $title, $body, $url, $tag) > 0;
    }

    /**
     * Queue the same alert for several members (one INSERT per 250 that actually
     * have a device - a member who never pressed "Enable phone alerts" gets no row).
     *
     * @param  array  $memberIds
     * @return int  How many rows were queued
     */
    public function queueMany(array $memberIds, string $type, string $title, ?string $body = null, ?string $url = null, ?string $tag = null): int
    {
        $title = trim($title);

        if ($title === '' || ! $this->isConfigured() || ! $this->tablesReady()) {
            return 0;
        }

        $memberIds = $this->membersWithDevices($memberIds);

        if ($memberIds === []) {
            return 0;
        }

        $timestamp = now();
        $queued = 0;

        foreach (array_chunk($memberIds, 250) as $chunk) {
            $rows = [];

            foreach ($chunk as $memberId) {
                $rows[] = [
                    'tiu_member_id' => $memberId,
                    'type' => mb_substr($type, 0, 60),
                    'title' => mb_substr($title, 0, 191),
                    'body' => $body === null ? null : mb_substr($body, 0, 1000),
                    'url' => $url,
                    'tag' => $tag === null ? null : mb_substr($tag, 0, 100),
                    'status' => 'queued',
                    'attempts' => 0,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            try {
                DB::table('push_messages')->insert($rows);
                $queued += count($rows);
            } catch (Throwable $e) {
                Log::error('Web Push queue write failed', [
                    'type' => $type,
                    'members' => count($chunk),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $queued;
    }

    /**
     * Which of these members own at least one live device.
     *
     * @param  array  $memberIds
     * @return array<int, int>
     */
    public function membersWithDevices(array $memberIds): array
    {
        $ids = [];

        foreach ($memberIds as $memberId) {
            $memberId = (int) $memberId;

            if ($memberId > 0) {
                $ids[$memberId] = $memberId;
            }
        }

        if ($ids === [] || ! Schema::hasTable('push_subscriptions')) {
            return [];
        }

        $found = [];

        foreach (array_chunk(array_values($ids), 500) as $chunk) {
            $rows = DB::table('push_subscriptions')
                ->whereIn('tiu_member_id', $chunk)
                ->whereNull('disabled_at')
                ->distinct()
                ->pluck('tiu_member_id');

            foreach ($rows as $memberId) {
                $found[(int) $memberId] = (int) $memberId;
            }
        }

        return array_values($found);
    }

    /* ------------------------------------------------------------------ *
     * Sending
     * ------------------------------------------------------------------ */

    /**
     * Deliver what is waiting. This is `php artisan push:send` (every minute).
     *
     * A row is claimed with a conditional UPDATE before anything is sent, so two
     * overlapping runs cannot deliver the same alert twice. A row is only marked
     * sent when at least one device accepted it; a push service answering 404/410
     * means the browser threw the subscription away, so that device is disabled
     * instead of retried for ever.
     *
     * @return array{sent: int, retried: int, failed: int, skipped: int, gone: int, devices: int}
     */
    public function sendPending(?int $limit = null): array
    {
        $summary = ['sent' => 0, 'retried' => 0, 'failed' => 0, 'skipped' => 0, 'gone' => 0, 'devices' => 0];

        if (! $this->isConfigured() || ! $this->tablesReady()) {
            return $summary;
        }

        $limit = max(1, $limit ?: (int) config('webpush.batch', 40));
        $maxAttempts = max(1, (int) config('webpush.max_attempts', 3));
        $now = now();

        $rows = DB::table('push_messages')
            ->where('status', 'queued')
            ->where(function ($query) use ($now) {
                $query->whereNull('send_after')->orWhere('send_after', '<=', $now);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            $claimed = DB::table('push_messages')
                ->where('id', $row->id)
                ->where('status', 'queued')
                ->update([
                    'status' => 'sending',
                    'attempts' => DB::raw('attempts + 1'),
                    'updated_at' => now(),
                ]);

            if ($claimed !== 1) {
                continue; // another run got there first
            }

            $attempts = (int) $row->attempts + 1;

            $devices = DB::table('push_subscriptions')
                ->where('tiu_member_id', $row->tiu_member_id)
                ->whereNull('disabled_at')
                ->get();

            if ($devices->isEmpty()) {
                $this->finish((int) $row->id, 'skipped', null);
                $summary['skipped']++;

                continue;
            }

            $payload = $this->payload([
                'title' => $row->title,
                'body' => $row->body,
                'url' => $row->url,
                'tag' => $row->tag,
                'type' => $row->type,
            ]);

            $delivered = 0;
            $error = null;

            foreach ($devices as $device) {
                $result = $this->deliver($device, $payload);
                $summary['devices']++;

                if ($result['ok']) {
                    $delivered++;
                    DB::table('push_subscriptions')->where('id', $device->id)->update([
                        'last_success_at' => now(),
                        'fail_count' => 0,
                        'updated_at' => now(),
                    ]);

                    continue;
                }

                if ($result['gone']) {
                    $summary['gone']++;
                    DB::table('push_subscriptions')->where('id', $device->id)->update([
                        'disabled_at' => now(),
                        'updated_at' => now(),
                    ]);

                    continue;
                }

                $error = $result['error'];
                DB::table('push_subscriptions')->where('id', $device->id)->update([
                    'fail_count' => DB::raw('fail_count + 1'),
                    'updated_at' => now(),
                ]);
            }

            if ($delivered > 0) {
                $this->finish((int) $row->id, 'sent', $error);
                $summary['sent']++;

                continue;
            }

            if ($attempts >= $maxAttempts) {
                $this->finish((int) $row->id, 'failed', $error);
                $summary['failed']++;

                continue;
            }

            $this->finish((int) $row->id, 'queued', $error, now()->addSeconds(max(30, (int) config('webpush.retry_seconds', 300))));
            $summary['retried']++;
        }

        return $summary;
    }

    /**
     * Send one alert right now, ignoring the outbox - what `php artisan push:test`
     * uses so an operator can make a phone buzz without waiting for an assignment.
     *
     * @return array{devices: int, sent: int, errors: array<int, string>}
     */
    public function sendNow(int $memberId, string $title, ?string $body = null, ?string $url = null): array
    {
        $summary = ['devices' => 0, 'sent' => 0, 'errors' => []];

        if ($memberId <= 0 || ! $this->isConfigured() || ! $this->tablesReady()) {
            return $summary;
        }

        $payload = $this->payload([
            'title' => $title,
            'body' => $body,
            'url' => $url ?: '/notifications',
            'tag' => null,
            'type' => 'test',
        ]);

        $devices = DB::table('push_subscriptions')
            ->where('tiu_member_id', $memberId)
            ->whereNull('disabled_at')
            ->get();

        foreach ($devices as $device) {
            $summary['devices']++;
            $result = $this->deliver($device, $payload);

            if ($result['ok']) {
                $summary['sent']++;
                DB::table('push_subscriptions')->where('id', $device->id)->update([
                    'last_success_at' => now(),
                    'fail_count' => 0,
                    'updated_at' => now(),
                ]);

                continue;
            }

            if ($result['gone']) {
                DB::table('push_subscriptions')->where('id', $device->id)->update([
                    'disabled_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $summary['errors'][] = (string) $result['error'];
        }

        return $summary;
    }

    /**
     * Hand one encrypted message to one push service.
     *
     * Never throws: every outcome becomes an array, because the caller is either a
     * cron run that must finish or a request that must not fail.
     *
     * @param  object  $device  A push_subscriptions row
     * @return array{ok: bool, gone: bool, status: int, error: string|null}
     */
    protected function deliver($device, string $payload): array
    {
        try {
            $body = $this->crypto->encrypt($payload, (string) $device->p256dh, (string) $device->auth);

            $headers = $this->crypto->vapidHeaders(
                (string) $device->endpoint,
                (string) config('webpush.public_key'),
                (string) config('webpush.private_key'),
                (string) config('webpush.subject', 'mailto:noreply@localhost')
            );

            $headers['Content-Type'] = 'application/octet-stream';
            $headers['Content-Encoding'] = 'aes128gcm';
            $headers['TTL'] = (string) max(0, (int) config('webpush.ttl', 86400));
            $headers['Urgency'] = (string) config('webpush.urgency', 'high');

            $response = $this->client()->post((string) $device->endpoint, [
                'body' => $body,
                'headers' => $headers,
                'timeout' => max(1, (int) config('webpush.timeout', 10)),
                'http_errors' => false,
            ]);

            $status = $response->getStatusCode();

            if ($status >= 200 && $status < 300) {
                return ['ok' => true, 'gone' => false, 'status' => $status, 'error' => null];
            }

            // 404 / 410: the browser is gone (uninstalled, data cleared, revoked).
            return [
                'ok' => false,
                'gone' => $status === 404 || $status === 410,
                'status' => $status,
                'error' => 'HTTP '.$status.': '.mb_substr((string) $response->getBody(), 0, 200),
            ];
        } catch (Throwable $e) {
            Log::warning('Web Push delivery failed', [
                'subscription_id' => $device->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'gone' => false, 'status' => 0, 'error' => mb_substr($e->getMessage(), 0, 200)];
        }
    }

    /**
     * The JSON the service worker's `push` handler reads.
     */
    protected function payload(array $message): string
    {
        return (string) json_encode([
            'title' => mb_substr((string) ($message['title'] ?? ''), 0, 120),
            'body' => mb_substr((string) ($message['body'] ?? ''), 0, 300),
            'url' => (string) ($message['url'] ?? '/notifications'),
            'tag' => $message['tag'] ?? null,
            'type' => (string) ($message['type'] ?? ''),
            'icon' => url((string) config('webpush.icon', '/icons/icon-192-v2.png')),
            'badge' => url((string) config('webpush.badge', '/icons/icon-32.png')),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Move an outbox row to its next state.
     *
     * @param  string  $status  queued | sending | sent | failed | skipped
     * @param  \DateTimeInterface|null  $sendAfter  When a retry may run
     */
    protected function finish(int $id, string $status, ?string $error, $sendAfter = null): void
    {
        DB::table('push_messages')->where('id', $id)->update([
            'status' => $status,
            'error' => $error === null ? null : mb_substr($error, 0, 255),
            'send_after' => $sendAfter,
            'sent_at' => $status === 'sent' ? now() : null,
            'updated_at' => now(),
        ]);
    }

    /**
     * Housekeeping: delivered/failed rows and long-dead devices go away.
     *
     * @return array{messages: int, subscriptions: int}
     */
    public function prune(?int $keepDays = null): array
    {
        $keepDays = $keepDays === null ? (int) config('webpush.keep_days', 30) : $keepDays;

        if ($keepDays <= 0 || ! $this->tablesReady()) {
            return ['messages' => 0, 'subscriptions' => 0];
        }

        $before = now()->subDays($keepDays);

        return [
            'messages' => (int) DB::table('push_messages')
                ->whereIn('status', ['sent', 'failed', 'skipped'])
                ->where('updated_at', '<', $before)
                ->delete(),
            'subscriptions' => (int) DB::table('push_subscriptions')
                ->whereNotNull('disabled_at')
                ->where('disabled_at', '<', $before)
                ->delete(),
        ];
    }

    protected function client(): ClientInterface
    {
        if ($this->client === null) {
            $this->client = new Client(['http_errors' => false]);
        }

        return $this->client;
    }
}
