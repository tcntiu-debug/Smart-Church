<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web Push (notifications on the phone, with the app closed)
    |--------------------------------------------------------------------------
    |
    | The bell in the app only rings while a page is open: it is filled by a poll
    | of /notifications/feed. Web Push is what makes the phone buzz when the app
    | is closed - the browser's push service wakes the service worker
    | (public/service-worker.js) and that raises the notification.
    |
    | This is the standard protocol (RFC 8030/8291/8292), so no vendor account is
    | needed: the site signs its own messages with the VAPID key pair below.
    | Generate the pair once with `php artisan push:keys` and paste both lines
    | into the server's .env (never commit them). Until they are set, nothing is
    | queued or sent and the button on /notifications says so - the rest of the
    | app is untouched.
    |
    | See docs/PUSH.md.
    |
    */

    // Master switch. false stops both the queueing and the sending, whatever the
    // keys say.
    'enabled' => env('WEBPUSH_ENABLED', true),

    // base64url VAPID key pair (`php artisan push:keys`).
    'public_key' => env('WEBPUSH_PUBLIC_KEY') ?: null,
    'private_key' => env('WEBPUSH_PRIVATE_KEY') ?: null,

    // Who is pushing: a mailto: or https: URL the push service can complain to.
    // Apple rejects a push whose `sub` is not a contact address, so this matters.
    'subject' => env('WEBPUSH_SUBJECT') ?: ('mailto:'.(env('MAIL_FROM_ADDRESS') ?: 'noreply@localhost')),

    // How long the push service may hold a message for a phone that is offline.
    'ttl' => (int) env('WEBPUSH_TTL', 86400),

    // How urgent the message is to the battery. "high" wakes the device at once,
    // which is what an assignment alert wants.
    'urgency' => env('WEBPUSH_URGENCY', 'high'),

    // How many queued alerts one `push:send` run may deliver. The scheduler runs
    // it every minute; a run that hits the cap leaves the rest for the next one
    // rather than holding the cron slot.
    'batch' => (int) env('WEBPUSH_BATCH', 40),

    // A failed delivery is retried this many times, this far apart, before the
    // message is marked failed (the bell still has the row either way).
    'max_attempts' => (int) env('WEBPUSH_MAX_ATTEMPTS', 3),
    'retry_seconds' => (int) env('WEBPUSH_RETRY_SECONDS', 300),

    // Seconds to wait on a push service before giving up on that device.
    'timeout' => (int) env('WEBPUSH_TIMEOUT', 10),

    // Delivered/failed rows are pruned after this many days, so the outbox does
    // not grow for ever. 0 keeps them.
    'keep_days' => (int) env('WEBPUSH_KEEP_DAYS', 30),

    // The notification's artwork (the launcher icon and the monochrome badge
    // Android draws in the status bar).
    'icon' => '/icons/icon-192-v2.png',
    'badge' => '/icons/icon-32.png',

    // Title used when a queued alert has none (should not happen).
    'fallback_title' => 'Smart Church',

];
