<?php

namespace Tests\Feature;

use App\Models\TiuMember;
use App\Services\NotificationService;
use App\Services\WebPushCrypto;
use App\Services\WebPushService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Web Push (docs/PUSH.md): the alert that reaches a phone with the app closed.
 *
 * The first test is the important one - it pins the encryption against the test
 * vector published in RFC 8291 Appendix A. Every browser decrypts according to
 * that document, so reproducing the published bytes is what makes this sender
 * interoperable rather than merely self-consistent.
 *
 * Delivery is tested with a Guzzle MockHandler: no request ever leaves the machine,
 * and the push service's answer (201 / 410 / 500) is what the assertions turn on.
 *
 * Deliberately no RefreshDatabase (same as the rest of this suite): the tests run
 * against the application's real connection inside a transaction that is rolled
 * back. The two Web Push tables are created from the migration itself when they
 * are missing, and only then dropped again.
 */
class WebPushTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_10_22_000001_create_push_subscriptions_and_messages_tables.php';

    /** @var bool  True when this test created the tables and must drop them again */
    private $createdTables = false;

    /** @var string */
    private $publicKey = '';

    /** @var string */
    private $privateKey = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTables();

        DB::beginTransaction();

        $keys = WebPushCrypto::generateKeys();
        $this->publicKey = $keys['public'];
        $this->privateKey = $keys['private'];
    }

    /* ------------------------------------------------------------------ *
     * The cryptography
     * ------------------------------------------------------------------ */

    /**
     * RFC 8291 Appendix A, byte for byte: header + ciphertext + tag.
     */
    public function test_the_encryptor_reproduces_the_rfc_8291_test_vector()
    {
        $decode = function ($value) {
            return WebPushCrypto::decode($value);
        };

        // The RFC's base64url values are wrapped, and say so: whitespace is removed
        // by WebPushCrypto::decode().
        $userPublic = $decode('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcx aOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4');
        $userPrivate = $decode('q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94');
        $serverPublic = $decode('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIg Dll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8');
        $serverPrivate = $decode('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw');
        $auth = $decode('BTBZMqHH6r4Tts7J_aSIgg');

        // The helpers the production side uses must round-trip the RFC's keys too.
        $this->assertSame(
            WebPushCrypto::encode($userPublic),
            WebPushCrypto::publicKeyFor(WebPushCrypto::encode($userPrivate)),
            'A P-256 scalar must export to the matching uncompressed point.'
        );
        $this->assertSame(
            WebPushCrypto::encode($serverPublic),
            WebPushCrypto::publicKeyFor(WebPushCrypto::encode($serverPrivate))
        );

        $body = (new WebPushCrypto())->encrypt(
            'When I grow up, I want to be a watermelon',
            WebPushCrypto::encode($userPublic),
            WebPushCrypto::encode($auth),
            [
                'salt' => $decode('DGv6ra1nlYgDCS1FRnbzlw'),
                'ephemeral_key' => WebPushCrypto::pemFromScalar($serverPrivate),
            ]
        );

        $expected = $decode('DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8')
            .$decode('8pfeW0KbunFT06SuDKoJH9Ql87S1QUrdirN6GcG7sFz1y1sqLgVi1VhjVkHsUoEsbI_0LpXMuGvnzQ');

        $this->assertSame(
            WebPushCrypto::encode($expected),
            WebPushCrypto::encode($body),
            'The aes128gcm body does not match RFC 8291 Appendix A - a browser would fail to decrypt it.'
        );
    }

    /**
     * A fresh key pair must produce a JWS a push service can verify: ES256 over the
     * base64url header and claims, 64 raw bytes, with `k` naming the same key.
     */
    public function test_the_vapid_header_carries_a_valid_es256_signature()
    {
        $header = (new WebPushCrypto())->vapidHeaders(
            'https://fcm.googleapis.com/wp/token',
            $this->publicKey,
            $this->privateKey,
            'mailto:test@example.com'
        );

        $this->assertArrayHasKey('Authorization', $header);
        $this->assertSame(1, preg_match('/^vapid t=([^,]+),k=(.+)$/', $header['Authorization'], $matches));

        [$joseHeader, $claims, $signature] = explode('.', $matches[1]);

        $this->assertSame(64, strlen(WebPushCrypto::decode($signature)), 'An ES256 signature is r||s, 64 bytes.');
        $this->assertSame($this->publicKey, $matches[2]);

        $payload = json_decode(WebPushCrypto::decode($claims), true);
        $this->assertSame('https://fcm.googleapis.com', $payload['aud']);
        $this->assertSame('mailto:test@example.com', $payload['sub']);
        $this->assertGreaterThan(time(), $payload['exp']);
        $this->assertSame(['typ' => 'JWT', 'alg' => 'ES256'], json_decode(WebPushCrypto::decode($joseHeader), true));

        // Verify it the way a push service does, from the public key alone.
        $key = openssl_pkey_get_public(WebPushCrypto::pemFromPoint(WebPushCrypto::decode($matches[2])));

        $this->assertSame(
            1,
            openssl_verify($joseHeader.'.'.$claims, $this->derSignature(WebPushCrypto::decode($signature)), $key, OPENSSL_ALGO_SHA256),
            'The VAPID JWT signature does not verify against the public key it advertises.'
        );
    }

    /**
     * Without keys the feature is inert: nothing queued, nothing sent, and no public
     * key for the page to subscribe with.
     */
    public function test_nothing_is_queued_or_sent_without_vapid_keys()
    {
        config(['webpush.public_key' => null, 'webpush.private_key' => null]);

        $push = app(WebPushService::class);

        $this->assertFalse($push->isConfigured());
        $this->assertNull($push->publicKey());
        $this->assertFalse($push->queue(11, 'guide_assignment', 'Never sent'));
        $this->assertSame(0, (int) DB::table('push_messages')->count());

        $this->assertSame(
            ['sent' => 0, 'retried' => 0, 'failed' => 0, 'skipped' => 0, 'gone' => 0, 'devices' => 0],
            $push->sendPending(10)
        );
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        if ($this->createdTables) {
            Schema::dropIfExists('push_messages');
            Schema::dropIfExists('push_subscriptions');
        }

        parent::tearDown();
    }

    /* ------------------------------------------------------------------ *
     * Devices
     * ------------------------------------------------------------------ */

    /**
     * One browser is one row: subscribing again refreshes it, and a device that has
     * changed hands moves to the member who signed in on it.
     */
    public function test_a_subscription_is_stored_once_and_follows_the_member()
    {
        $this->configure();

        $push = app(WebPushService::class);
        $subscription = $this->subscription('https://push.example.test/one');

        $this->assertTrue($push->subscribe(11, $subscription, 'Chrome on Android'));
        $this->assertTrue($push->subscribe(11, $subscription, 'Chrome on Android'));

        $this->assertSame(1, (int) DB::table('push_subscriptions')->count(), 'Re-subscribing must update, not duplicate.');
        $this->assertSame(1, $push->devicesFor(11));
        $this->assertSame('Chrome on Android', DB::table('push_subscriptions')->value('user_agent'));

        // Same browser, another account: the endpoint belongs to whoever is signed in.
        $this->assertTrue($push->subscribe(12, $subscription));

        $this->assertSame(1, (int) DB::table('push_subscriptions')->count());
        $this->assertSame(0, $push->devicesFor(11));
        $this->assertSame(1, $push->devicesFor(12));

        // Only the owner can remove it.
        $this->assertFalse($push->unsubscribe(11, $subscription['endpoint']));
        $this->assertTrue($push->unsubscribe(12, $subscription['endpoint']));
        $this->assertSame(0, $push->devicesFor(12));
    }

    /**
     * An alert is queued only for a member who has a device, which keeps the outbox
     * from collecting rows nobody can receive.
     */
    public function test_a_push_is_queued_only_for_a_member_with_a_device()
    {
        $this->configure();

        $push = app(WebPushService::class);

        $this->assertFalse($push->queue(11, 'guide_assignment', 'You have first timers'));
        $this->assertSame(0, (int) DB::table('push_messages')->count());

        $push->subscribe(11, $this->subscription());

        $this->assertTrue($push->queue(11, 'guide_assignment', 'You have first timers', 'Ada', '/my-tasks'));

        $row = DB::table('push_messages')->first();
        $this->assertSame('queued', $row->status);
        $this->assertSame('/my-tasks', $row->url);
        $this->assertSame(11, (int) $row->tiu_member_id);
        $this->assertSame(0, (int) $row->attempts);
    }

    /* ------------------------------------------------------------------ *
     * Delivery
     * ------------------------------------------------------------------ */

    /**
     * 201 Created: the push service took it. The row is done and the device is
     * stamped, which is what /notifications and the SQL in docs/PUSH.md report on.
     */
    public function test_an_accepted_push_marks_the_message_sent()
    {
        $this->configure();

        $push = $this->push([new Response(201)]);
        $push->subscribe(11, $this->subscription());
        $push->queue(11, 'guide_assignment', 'You have a first timer', 'Ada', '/my-tasks');

        $summary = $push->sendPending(10);

        $this->assertSame(1, $summary['sent']);
        $this->assertSame(1, $summary['devices']);

        $row = DB::table('push_messages')->first();
        $this->assertSame('sent', $row->status);
        $this->assertSame(1, (int) $row->attempts);
        $this->assertNotNull($row->sent_at);
        $this->assertNotNull(DB::table('push_subscriptions')->value('last_success_at'));
    }

    /**
     * 410 Gone: the browser threw the subscription away (app uninstalled, site data
     * cleared). The device is disabled so the next run does not try again.
     */
    public function test_a_revoked_subscription_is_disabled()
    {
        $this->configure();

        $push = $this->push([new Response(410)]);
        $push->subscribe(11, $this->subscription());
        $push->queue(11, 'guide_assignment', 'You have a first timer');

        $summary = $push->sendPending(10);

        $this->assertSame(1, $summary['gone']);
        $this->assertSame(0, $summary['sent']);
        $this->assertNotNull(DB::table('push_subscriptions')->value('disabled_at'));
        $this->assertSame(0, $push->devicesFor(11), 'A disabled device must stop counting.');
    }

    /**
     * Anything else (a 500 here) is retried up to `webpush.max_attempts` and then
     * given up on, so one dead endpoint cannot block the outbox for ever.
     */
    public function test_a_failing_push_service_is_retried_then_failed()
    {
        $this->configure(['webpush.max_attempts' => 2]);

        $push = $this->push([new Response(500), new Response(500)]);
        $push->subscribe(11, $this->subscription());
        $push->queue(11, 'guide_assignment', 'You have a first timer');

        $first = $push->sendPending(10);
        $this->assertSame(1, $first['retried']);
        $this->assertSame('queued', DB::table('push_messages')->value('status'));
        $this->assertNotNull(DB::table('push_messages')->value('send_after'), 'A retry has to wait for its backoff.');

        // The cron would not pick it up again before send_after; clear it to simulate
        // the next window.
        DB::table('push_messages')->update(['send_after' => null]);

        $second = $push->sendPending(10);
        $this->assertSame(1, $second['failed']);
        $this->assertSame('failed', DB::table('push_messages')->value('status'));
        $this->assertStringContainsString('HTTP 500', (string) DB::table('push_messages')->value('error'));
    }

    /**
     * A queued alert whose device has gone (unsubscribed between queue and send) is
     * dropped instead of retried.
     */
    public function test_a_message_for_a_member_without_a_device_is_skipped()
    {
        $this->configure();

        DB::table('push_messages')->insert([
            'tiu_member_id' => 99,
            'type' => 'guide_assignment',
            'title' => 'Nobody is listening',
            'status' => 'queued',
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summary = $this->push([])->sendPending(10);

        $this->assertSame(1, $summary['skipped']);
        $this->assertSame('skipped', DB::table('push_messages')->value('status'));
    }

    /* ------------------------------------------------------------------ *
     * The endpoints the page calls
     * ------------------------------------------------------------------ */

    /**
     * A guest cannot file a device, an incomplete payload is rejected, and a valid
     * one lands under the signed-in member - never under an id from the request.
     */
    public function test_the_subscribe_route_files_the_device_under_the_signed_in_member()
    {
        $this->configure();

        $member = TiuMember::query()->first();

        if (! $member) {
            $this->markTestSkipped('No member row available to authenticate the request.');
        }

        $payload = [
            'endpoint' => 'https://push.example.test/route',
            'keys' => [
                'p256dh' => $this->publicKey,
                'auth' => WebPushCrypto::encode(random_bytes(16)),
            ],
            'contentEncoding' => 'aes128gcm',
        ];

        $this->postJson('/push/subscribe', $payload)->assertStatus(401);

        $this->actingAs($member)
            ->postJson('/push/subscribe', ['endpoint' => 'https://push.example.test/route'])
            ->assertStatus(422);

        $this->actingAs($member)
            ->postJson('/push/subscribe', $payload)
            ->assertOk()
            ->assertJson(['subscribed' => true, 'devices' => 1]);

        $stored = DB::table('push_subscriptions')->first();

        $this->assertSame((int) $member->tiu_member_id, (int) $stored->tiu_member_id);
        $this->assertSame(hash('sha256', $payload['endpoint']), $stored->endpoint_hash);

        $this->actingAs($member)
            ->getJson('/push/status')
            ->assertOk()
            ->assertJson(['configured' => true, 'subscribed' => true, 'devices' => 1])
            ->assertJsonPath('publicKey', $this->publicKey);

        $this->actingAs($member)
            ->postJson('/push/unsubscribe', ['endpoint' => $payload['endpoint']])
            ->assertOk()
            ->assertJson(['removed' => true]);

        $this->assertSame(0, (int) DB::table('push_subscriptions')->count());
    }

    /**
     * The bell's alerts reach the outbox without any call site being touched: an
     * in-app notification now also queues the same alert for the member's phone.
     */
    public function test_an_in_app_alert_also_queues_a_push()
    {
        if (! Schema::hasTable('app_notifications')) {
            $this->markTestSkipped('app_notifications is missing: run `php artisan app:sync-schema`.');
        }

        $this->configure();

        $push = app(WebPushService::class);
        $push->subscribe(11, $this->subscription());

        $notifications = app(NotificationService::class);

        $this->assertTrue($notifications->notify(
            11,
            NotificationService::TYPE_GUIDE_ASSIGNMENT,
            'You have a new first timer',
            'Ada',
            ['url' => '/my-tasks']
        ));

        $row = DB::table('push_messages')->first();

        $this->assertNotNull($row, 'The bell row must come with a queued push.');
        $this->assertSame('guide_assignment', $row->type);
        $this->assertSame('You have a new first timer', $row->title);
        $this->assertSame('/my-tasks', $row->url);
        $this->assertSame('queued', $row->status);
    }

    /* ------------------------------------------------------------------ *
     * The browser side
     * ------------------------------------------------------------------ */

    /**
     * The worker must show a pushed payload (that is the whole feature) while
     * keeping the handlers the install button and notification clicks rely on.
     */
    public function test_the_service_worker_shows_pushed_notifications()
    {
        $worker = file_get_contents(public_path('service-worker.js'));

        $this->assertStringContainsString("addEventListener('push'", $worker);
        $this->assertStringContainsString('showNotification', $worker);
        $this->assertStringContainsString("addEventListener('pushsubscriptionchange'", $worker);
        $this->assertStringContainsString('tiu:push-resubscribe', $worker);

        // The PWA plumbing from docs/PWA.md must survive.
        $this->assertStringContainsString("addEventListener('install'", $worker);
        $this->assertStringContainsString("addEventListener('activate'", $worker);
        $this->assertStringContainsString("addEventListener('fetch'", $worker);
        $this->assertStringContainsString("addEventListener('notificationclick'", $worker);
    }

    /**
     * Every signed-in page hands the script the push endpoints (and the VAPID key
     * when there is one), because the subscribe happens from any page, not only
     * from /notifications.
     */
    public function test_the_layout_hands_the_push_endpoints_to_the_page()
    {
        $member = TiuMember::query()->first();

        if (! $member) {
            $this->markTestSkipped('No member row available to authenticate the page request.');
        }

        // The endpoints are only handed over once this server can actually push;
        // test_the_signed_in_pages_survive_without_a_vapid_pair() covers the other
        // half of that rule.
        $this->configure();

        $response = $this->actingAs($member)->get('/home');

        $response->assertOk();
        $response->assertSee('pushSubscribeUrl', false);
        $response->assertSee($this->asJson(route('push.subscribe')), false);
        $response->assertSee('vapidPublicKey', false);
        $response->assertSee($this->publicKey, false);

        // The centre is where the member actually turns this on, and its script now
        // reports whether the phone - not just the browser - is subscribed.
        $centre = $this->actingAs($member)->get('/notifications');

        $centre->assertOk();
        $centre->assertSee('enableBrowserAlerts', false);
        $centre->assertSee('describePhoneAlerts', false);
    }

    /**
     * A server without a VAPID pair - which is every server until `push:keys` has
     * been run and .env edited - must still serve every page.
     *
     * This is the regression that took the production site down: the layout called
     * route('push.subscribe') unconditionally from inside @auth, so a route table
     * that did not know /push/* yet (a bootstrap/cache/routes-*.php built before
     * the deploy, or views that landed ahead of routes/web.php) threw
     * RouteNotFoundException and every signed-in page answered 500 while the login
     * page stayed up. A dormant feature may not do that.
     */
    public function test_the_signed_in_pages_survive_without_a_vapid_pair()
    {
        $member = TiuMember::query()->first();

        if (! $member) {
            $this->markTestSkipped('No member row available to authenticate the page request.');
        }

        // No configure() here: config/webpush.php reads an empty pair.
        $this->assertSame('', (string) config('webpush.public_key', ''));

        $response = $this->actingAs($member)->get('/home');

        $response->assertOk();
        $response->assertSee('vapidPublicKey: null', false);
        $response->assertSee('pushSubscribeUrl: null', false);
        $response->assertDontSee($this->asJson(route('push.subscribe')), false);
        $response->assertDontSee($this->asJson(route('push.status')), false);
    }

    /* ------------------------------------------------------------------ *
     * Helpers
     * ------------------------------------------------------------------ */

    /**
     * Pretend this server has a VAPID pair.
     */
    private function configure(array $overrides = []): void
    {
        config(array_merge([
            'webpush.enabled' => true,
            'webpush.public_key' => $this->publicKey,
            'webpush.private_key' => $this->privateKey,
            'webpush.subject' => 'mailto:test@example.com',
            'webpush.max_attempts' => 3,
            'webpush.retry_seconds' => 300,
        ], $overrides));
    }

    /**
     * How a URL appears inside @json(...) in the layout: json_encode escapes the
     * forward slashes, so route('push.subscribe') is served as
     * http:\/\/host\/push\/subscribe and a literal assertSee() would miss it.
     */
    private function asJson(string $url): string
    {
        return str_replace('/', '\/', $url);
    }

    /**
     * A service whose push service answers with exactly these responses, in order.
     *
     * @param  array<int, \GuzzleHttp\Psr7\Response>  $responses
     */
    private function push(array $responses): WebPushService
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);

        return new WebPushService(new WebPushCrypto(), $client);
    }

    /**
     * What PushSubscription.getKey() would have produced for this browser.
     *
     * @return array<string, string>
     */
    private function subscription(string $endpoint = 'https://push.example.test/device'): array
    {
        return [
            'endpoint' => $endpoint,
            'p256dh' => $this->publicKey,
            'auth' => WebPushCrypto::encode(random_bytes(16)),
            'content_encoding' => 'aes128gcm',
        ];
    }

    /**
     * r||s back to the DER SEQUENCE openssl_verify() wants (the inverse of what
     * WebPushCrypto does when it signs).
     */
    private function derSignature(string $raw): string
    {
        $integer = function ($bytes) {
            $bytes = ltrim($bytes, "\x00");

            if ($bytes === '' || ord($bytes[0]) >= 0x80) {
                $bytes = "\x00".$bytes;
            }

            return "\x02".chr(strlen($bytes)).$bytes;
        };

        $body = $integer(substr($raw, 0, 32)).$integer(substr($raw, 32));

        return "\x30".chr(strlen($body)).$body;
    }

    /**
     * The feature's tables may not exist on a developer's database yet: build them
     * from the migration itself, and drop them again afterwards only if this test
     * was the one that made them.
     */
    private function ensureTables(): void
    {
        $hasSubscriptions = Schema::hasTable('push_subscriptions');
        $hasMessages = Schema::hasTable('push_messages');

        if ($hasSubscriptions && $hasMessages) {
            return;
        }

        if ($hasSubscriptions !== $hasMessages) {
            $this->markTestSkipped('Only one of the Web Push tables exists - run `php artisan app:sync-schema`.');
        }

        $migration = require base_path(self::MIGRATION);
        $migration->up();

        $this->createdTables = true;
    }
}
