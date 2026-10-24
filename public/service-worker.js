/**
 * Smart-Church service worker.
 *
 * Three jobs:
 *
 *   1. Make the app installable. Chrome/Edge only offer "Install app" (and only
 *      let a page raise a system notification from JavaScript) when the site is
 *      served over HTTPS and has a registered service worker with a `fetch`
 *      handler. The page itself is server rendered and needs no offline cache, so
 *      the fetch handler is a network pass-through - nothing is intercepted.
 *   2. Show the notifications Web Push delivers (`push`), even when the app is
 *      closed - that is the whole point of docs/PUSH.md.
 *   3. Open the right page when a notification is tapped (`notificationclick`).
 *
 * A page can also ask this worker to show a notification (registration
 * .showNotification), which is how public/assets/js/notifications.js alerts on
 * Android - `new Notification()` throws there.
 *
 * Registered from resources/views/layouts/app.blade.php; the install button that
 * uses the resulting `beforeinstallprompt` event is
 * resources/views/partials/pwa-install-button.blade.php.
 */

var NOTIFICATION_FALLBACK_URL = '/notifications';
var DEFAULT_TITLE = 'Smart Church';
var DEFAULT_ICON = '/icons/icon-192-v2.png';
var DEFAULT_BADGE = '/icons/icon-32.png';

self.addEventListener('install', function () {
    // Take over as soon as this version is fetched, without waiting for the
    // previous worker to be released by every open tab.
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

// Network-only: the handler is what makes the app installable. No caching yet.
self.addEventListener('fetch', function () {
    // Intentionally empty - the browser falls back to its normal network request.
});

// ---------------------------------------------------------------- Web Push
//
// The push service delivers the encrypted payload that
// App\Services\WebPushService::payload() built. It arrives here whether or not
// the app is open - that is the difference between this and the page's own
// polling in public/assets/js/notifications.js.
//
// A subscription is created with `userVisibleOnly: true`, so every push MUST
// result in a notification; a browser that receives one and sees nothing shown
// eventually revokes the subscription ("quiet" budget exhausted).
self.addEventListener('push', function (event) {
    var payload = {};

    if (event.data) {
        try {
            payload = event.data.json() || {};
        } catch (e) {
            // Encrypted with a payload we did not write: show it as plain text.
            payload = { body: event.data.text() };
        }
    }

    var options = {
        body: payload.body || '',
        icon: payload.icon || DEFAULT_ICON,
        badge: payload.badge || DEFAULT_BADGE,
        // Reusing a tag replaces the earlier notification instead of stacking a
        // second one for the same alert (the page's poll uses the same tag).
        tag: payload.tag || undefined,
        data: { url: payload.url || NOTIFICATION_FALLBACK_URL },
        vibrate: [120, 60, 120]
    };

    event.waitUntil(self.registration.showNotification(payload.title || DEFAULT_TITLE, options));
});

// The browser rotated the endpoint (or dropped it). The page re-subscribes and
// posts the new endpoint; without an open page the next visit does the same,
// because notifications.js always re-checks on load.
self.addEventListener('pushsubscriptionchange', function (event) {
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                clientList[i].postMessage({ type: 'tiu:push-resubscribe' });
            }
        })
    );
});

// Notification clicks (assignment alerts, birthday digests) open the page the
// payload points at, reusing an open tab when there is one.
self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var target = NOTIFICATION_FALLBACK_URL;

    if (event.notification.data && event.notification.data.url) {
        target = event.notification.data.url;
    }

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];

                if (client.url.indexOf(target) !== -1 && 'focus' in client) {
                    return client.focus();
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(target);
            }

            return undefined;
        })
    );
});
