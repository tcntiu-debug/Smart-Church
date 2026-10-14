/**
 * Smart-Church service worker.
 *
 * Why it exists: Chrome/Edge only offer "Install app" (and only let a page show
 * a system notification from JavaScript) when the site is served over HTTPS and
 * has a registered service worker with a `fetch` handler. The page itself is
 * server rendered and needs no offline cache, so the fetch handler is a network
 * pass-through - nothing is intercepted or cached.
 *
 * Registered from resources/views/layouts/app.blade.php; the install button that
 * uses the resulting `beforeinstallprompt` event is
 * resources/views/partials/pwa-install-button.blade.php.
 */

var NOTIFICATION_FALLBACK_URL = '/home';

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
