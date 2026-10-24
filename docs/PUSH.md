# Phone notifications (Web Push)

The bell in the app (`docs/NOTIFICATIONS.md`) is filled by a poll: a page asks
`/notifications/feed` every `poll_seconds` and raises a system notification for
anything new. That only happens **while a page is open**. Close the app and the
phone is silent - which is exactly the complaint this feature answers.

Web Push is the browser standard for the other half: the site asks the browser's
push service (FCM on Chrome/Android, Mozilla, Apple) to wake a service worker,
and the worker shows the notification - app closed, phone in a pocket. No vendor
account, no app store, no per-message cost: the site signs its own messages with
a key pair it generates itself (VAPID, RFC 8292).

Two things were also wrong on the phone before this landed:

| Symptom | Cause |
|---------|-------|
| Alerts showed on a laptop, never on a phone | `new Notification()` throws on Chrome for Android ("Illegal constructor"). A phone only accepts a notification raised from the service worker registration; `notifications.js` now calls `registration.showNotification()`. |
| Nothing at all with the app closed | There was no push subscription and no sender - only the poll. |

## What ships

| Piece | Responsibility |
|-------|----------------|
| `app/Services/WebPushCrypto.php` | RFC 8291 message encryption (`aes128gcm`) and the RFC 8292 VAPID JWT, with the `openssl` extension only - **no Composer package**. |
| `app/Services/WebPushService.php` | Devices, the outbox, delivery, retries, pruning. Silent when not configured. |
| `app/Http/Controllers/PushSubscriptionController.php` | `GET /push/status`, `POST /push/subscribe`, `POST /push/unsubscribe`. |
| `database/migrations/2026_10_22_000001_create_push_subscriptions_and_messages_tables.php` | `push_subscriptions` (one row per device) and `push_messages` (the outbox). |
| `app/Console/Commands/SendPushNotifications.php` | `push:send` - drains the outbox. Runs every minute. |
| `app/Console/Commands/GenerateWebPushKeys.php` | `push:keys` - makes the VAPID pair. |
| `app/Console/Commands/SendTestPush.php` | `push:test` - "does the phone actually buzz?" |
| `public/service-worker.js` | `push` handler (shows the notification), `pushsubscriptionchange` (asks the page to re-subscribe), `notificationclick` (opens the target page). |
| `public/assets/js/notifications.js` | Subscribes the browser after the member allows alerts, refreshes the endpoint on every page load, and stops the poll raising duplicates once push is live. |
| `config/webpush.php` | Every knob, all overridable from `.env`. |

## Turning it on

1. **Create the tables** (one additive migration, no existing table is touched):

   ```bash
   php artisan app:sync-schema      # or: php artisan migrate --force
   ```

   `app:sync-schema` already knows this migration (`SyncSchema::$requiredTables`),
   and `php artisan app:post-deploy` runs it, so a normal deploy does the same
   thing. The daily 03:20 run is the safety net.

2. **Make the key pair** (once per site):

   ```bash
   php artisan push:keys
   ```

   It prints two lines. Paste them into the server's `.env` and never commit them:

   ```
   WEBPUSH_PUBLIC_KEY=BF...            # 87 chars, base64url
   WEBPUSH_PRIVATE_KEY=...             # 43 chars, base64url
   WEBPUSH_SUBJECT=mailto:admin@tcnikorodu.org
   ```

   The **private** key is the ability to push to this site's subscribers. Treat it
   like `APP_KEY`. Losing it means running `push:keys --force`, after which every
   member has to press "Enable phone alerts" again, because a subscription is bound
   to the public key it was created with.

3. **Clear the cached config** so the running app sees the pair:

   ```bash
   php artisan config:clear        # or php artisan app:post-deploy
   ```

4. **Confirm the cron** - no change needed if `php artisan schedule:run` already
   runs every minute (see `docs/CPANEL-DEPLOYMENT.md`). `app/Console/Kernel.php`
   adds:

   ```php
   $schedule->command('push:send')->everyMinute()->withoutOverlapping();
   $schedule->command('push:send --prune')->dailyAt('03:45');
   ```

5. **On the phone**: open `/notifications` and press **Enable browser alerts**. The
   page then says *"Phone alerts are on: they arrive even when the app is closed."*

6. **Prove it**:

   ```bash
   php artisan push:test            # the only member with a device
   php artisan push:test 123        # a specific tiu_member id
   ```

   The phone should buzz within a second or two. If it does not, the command says
   why (`no device on file`, `HTTP 401`, `HTTP 413`, ...).

## What reaches the phone

Everything the bell already writes, because `NotificationService` queues a push
next to the `app_notifications` row - no call site had to change:

| Type | Raised by |
|------|-----------|
| `caller_assignment` | a caller is handed first timers to call |
| `guide_assignment` | a first timer is handed to a guide |
| `weekly_task` | a guide's next week of tasks is activated |
| `announcement` | a notice is published (one outbox row per subscribed member) |
| `birthday_reminder` | the `birthdays:remind` digest (`BirthdayReminderService`) |

A tap opens the same URL the bell would: `/weeklist`, `/my-tasks`, `/notice`,
`/birthdays`.

## How delivery works

```
assignment  ->  app_notifications row  (instant bell)
            ->  push_messages row      (status=queued, no network call)

every minute:  push:send
                 -> claim each row with a conditional UPDATE (status=sending)
                 -> encrypt per device (RFC 8291) + sign (VAPID)
                 -> POST to the browser's push endpoint
                 -> 2xx: sent | 404/410: device disabled | other: retry, max 3
```

* **No queue worker.** The outbox table *is* the queue, drained by the per-minute
  cron; the server has never needed a long-running worker.
* **At most a minute of delay.** The bell is instant; the phone buzzes on the next
  tick. `WEBPUSH_BATCH` (default 40) caps one run, so an announcement to the whole
  church spreads over a few minutes instead of one slow run.
* **No duplicate buzz.** The poll stops raising its own system notifications once
  the browser holds a push subscription, and both routes share the same
  notification `tag`, so an alert is shown once either way.
* **Dead devices clean themselves up.** A push service answering 404/410 means the
  browser revoked the subscription (app uninstalled, data cleared); that row is
  disabled, and `push:send --prune` deletes it 30 days later.
* **Nothing is queued for a member with no device**, so the table stays small.

## When it will not work (be honest with members)

| Situation | Reality |
|-----------|---------|
| **iPhone / iPad** | Web Push needs **iOS 16.4 or newer** *and* the site added to the Home Screen (Share → Add to Home Screen). In plain Safari there is no `PushManager` at all, so only the in-app bell works there. The notification page says so. |
| **Android** | Works in Chrome/Edge/Firefox, installed or not. Installing the app (the "Install app" button) makes delivery more reliable. |
| **Battery / "app freeze" (Tecno, Infinix, itel, Xiaomi, Oppo, Samsung)** | These ROMs stop background apps aggressively. If the phone stays silent while closed, exempt Chrome (or the installed app) from battery optimisation: Settings → Apps → Chrome → Battery → *Unrestricted*. Force-stopping the browser stops push until it is opened again. |
| **Plain HTTP** | Service workers and push do not exist outside HTTPS (or `localhost`). The live site is HTTPS; a LAN IP on a dev machine is not. |
| **Data saver / no connection** | The push service holds the message for `WEBPUSH_TTL` (24h by default) and delivers when the phone is back online. |
| **A truly silent-killer phone** | Only a native app with FCM can promise delivery there. A PWA cannot - it is the ROM's decision, not the site's. |

## Security

* The VAPID private key is the whole authority. It lives in `.env` only (excluded
  from git by the existing rule). Rotating it invalidates every subscription.
* A push endpoint is a capability URL: anybody holding it can send to that browser.
  It is filed under the member who subscribed (`Auth::id()`), never exposed to
  another member, and removed on `POST /push/unsubscribe`.
* Payloads are encrypted end-to-end to the browser (RFC 8291): the push service
  relays ciphertext it cannot read.
* `push:send` and `push:test` only ever send what the member's own bell already
  holds - there is no broadcast console here.

## Configuration - `config/webpush.php`

| Key | `.env` | Default | Meaning |
|-----|--------|---------|---------|
| `enabled` | `WEBPUSH_ENABLED` | `true` | master switch |
| `public_key` / `private_key` | `WEBPUSH_PUBLIC_KEY` / `WEBPUSH_PRIVATE_KEY` | - | the VAPID pair (`push:keys`) |
| `subject` | `WEBPUSH_SUBJECT` | `mailto:` + `MAIL_FROM_ADDRESS` | contact address in the JWT; Apple rejects a push without a usable one |
| `ttl` | `WEBPUSH_TTL` | `86400` | how long the push service holds a message for an offline phone |
| `urgency` | `WEBPUSH_URGENCY` | `high` | wakes the device immediately |
| `batch` | `WEBPUSH_BATCH` | `40` | outbox rows one `push:send` run may deliver |
| `max_attempts` / `retry_seconds` | `WEBPUSH_MAX_ATTEMPTS` / `WEBPUSH_RETRY_SECONDS` | `3` / `300` | retry policy |
| `timeout` | `WEBPUSH_TIMEOUT` | `10` | seconds to wait on one push service |
| `keep_days` | `WEBPUSH_KEEP_DAYS` | `30` | prune window for sent rows and dead devices (`0` keeps them) |

With no keys set, `isConfigured()` is false: nothing is queued, nothing is sent,
the notification page says so, and the rest of the app behaves exactly as before.

## Troubleshooting

```bash
php artisan push:send --limit=5   # a small, visible run; prints a summary
tail -n 50 storage/logs/push.log  # what the cron wrote
tail -n 50 storage/logs/laravel.log
```

| Symptom | Check |
|---------|-------|
| `push:send` says "not configured" | the two `WEBPUSH_*` keys in `.env` on **this** server, then `php artisan config:clear` |
| "no device on file" | the member must press **Enable browser alerts** on `/notifications` *on that phone* (every browser is its own device) |
| Page says "Browser alerts are blocked" | the member answered *Block* once; unblock in the browser's site settings, then reload |
| iPhone says the browser cannot show notifications | not installed: Share → **Add to Home Screen**, open the installed app, then try again |
| `HTTP 401 / 403` in the log | the VAPID pair changed after this device subscribed - the member must re-subscribe (or `push:unsubscribe`, then enable again) |
| `HTTP 413` | payload too large (a notice body is capped well below the 4 KB limit; report it) |
| Row stays `queued` | the cron is not running `schedule:run`, or `withoutOverlapping` is stuck - see `docs/CPANEL-DEPLOYMENT.md` |
| Sent, but the phone is silent | battery optimisation / force-stopped browser (see the table above) |
| Phone buzzed before, silent after a redeploy | a rotated `WEBPUSH_*` pair: every device must re-subscribe |

```sql
-- who is reachable?
SELECT s.tiu_member_id, COUNT(*) AS devices, MAX(s.last_success_at) AS last_ok
FROM push_subscriptions s WHERE s.disabled_at IS NULL GROUP BY s.tiu_member_id;

-- what is stuck?
SELECT status, COUNT(*) FROM push_messages GROUP BY status;
SELECT id, tiu_member_id, attempts, LEFT(error, 120) FROM push_messages
WHERE status = 'failed' ORDER BY id DESC LIMIT 10;
```

## Tests

`tests/Feature/WebPushTest.php`:

* **the RFC 8291 Appendix A test vector** - the encryptor must reproduce the
  published body *and* tag byte for byte, which is what proves the implementation
  is interoperable with a browser and not merely self-consistent;
* the VAPID JWT is a valid ES256 signature over the right claims, with `k`
  matching the private key;
* nothing is configured and nothing is queued without keys;
* a subscription is stored once (idempotent by endpoint) and moves with the member
  if a different account signs in on the same browser;
* `POST /push/subscribe` needs a session, validates the payload and files the
  endpoint under the signed-in member; `POST /push/unsubscribe` only removes your
  own;
* `push:send`: a 201 marks the row sent and the device touched, a 410 disables the
  device, a 500 retries and then fails, and a member with no device is skipped;
* `service-worker.js` still registers a `push` handler that calls
  `showNotification`, alongside the `install` / `fetch` / `notificationclick`
  handlers the install button depends on.

```bash
vendor/bin/phpunit --filter WebPushTest
```
