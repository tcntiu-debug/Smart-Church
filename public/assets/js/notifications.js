/**
 * In-app notification alerts (the navbar bell).
 *
 * The app writes a row to `app_notifications` when a caller is handed first
 * timers to call (WeekListController) or a guide is handed a first timer
 * (AdminAssignmentController). This script polls /notifications/feed and raises
 * a system notification through the browser, so an alert reaches a phone or a
 * laptop that has the page open - web only for now, there is no native app and
 * no Web Push subscription yet.
 *
 * The browser only allows a system notification after the member clicks
 * "Enable browser alerts" once (see resources/views/notifications/index.blade.php);
 * until then the poll still keeps the navbar badge honest.
 *
 * Config is handed over by resources/views/layouts/app.blade.php:
 *   window.tiuNotifications = { feedUrl, pollSeconds, enabled, icon }
 */
(function (window, document) {
    'use strict';

    var config = window.tiuNotifications;

    if (!config || !config.feedUrl || config.enabled === false) {
        return;
    }

    // No Notification support (e.g. older iOS Safari): polling would only keep a
    // badge in step that the next page load refreshes anyway.
    if (typeof window.Notification !== 'function') {
        return;
    }

    var SEEN_KEY = 'tiu_alerts_seen';
    var MAX_SEEN = 50;
    var LINGER_MS = 20000;

    /**
     * Ids already shown on this browser. `null` means "never polled here", so the
     * first poll records what is unread instead of firing a burst of alerts.
     */
    function readSeen() {
        try {
            var raw = window.localStorage.getItem(SEEN_KEY);
            var ids = raw ? JSON.parse(raw) : null;

            return Array.isArray(ids) ? ids.map(String) : null;
        } catch (e) {
            return null;
        }
    }

    function writeSeen(ids) {
        try {
            window.localStorage.setItem(SEEN_KEY, JSON.stringify(ids.slice(-MAX_SEEN)));
        } catch (e) {
            // Private mode / storage full: alerts still work, they may repeat.
        }
    }

    function granted() {
        return window.Notification.permission === 'granted';
    }

    function updateBadge(count) {
        var bell = document.querySelector('.ms-notif-bell');

        if (!bell) {
            return;
        }

        var badge = bell.querySelector('.badge');

        if (count <= 0) {
            if (badge) {
                badge.parentNode.removeChild(badge);
            }

            return;
        }

        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'badge badge-danger badge-pill';
            badge.style.cssText = 'position:absolute;top:-8px;right:-12px;font-size:10px;';
            bell.appendChild(badge);
        }

        badge.textContent = count > 9 ? '9+' : String(count);
    }

    function show(row) {
        try {
            var note = new window.Notification(row.title, {
                body: row.body || '',
                icon: config.icon || undefined,
                tag: 'tiu-notification-' + row.id
            });

            note.onclick = function () {
                window.focus();

                if (row.url) {
                    window.location.href = row.url;
                }

                note.close();
            };

            window.setTimeout(function () {
                note.close();
            }, LINGER_MS);
        } catch (e) {
            // A blocked or unsupported case must never break the page.
        }
    }

    function poll() {
        var seen = readSeen();
        var returning = seen !== null;

        seen = seen || [];

        window.fetch(config.feedUrl, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return response.ok ? response.json() : null;
        }).then(function (data) {
            if (!data || !Array.isArray(data.notifications)) {
                return;
            }

            updateBadge(parseInt(data.unread, 10) || 0);

            var fresh = data.notifications.filter(function (row) {
                return seen.indexOf(String(row.id)) === -1;
            });

            // Alert only on a browser we have polled before: on a first visit the
            // backlog would arrive as a dozen system notifications at once.
            if (granted() && returning) {
                fresh.forEach(show);
            }

            writeSeen(seen.concat(fresh.map(function (row) {
                return String(row.id);
            })));
        }).catch(function () {
            // Offline / logged out: the next tick tries again.
        });
    }

    /**
     * Asked by the button on the notification centre page. The promise resolves
     * with the permission string so the page can update its button.
     */
    config.requestPermission = function () {
        if (window.Notification.permission !== 'default') {
            return Promise.resolve(window.Notification.permission);
        }

        return window.Notification.requestPermission();
    };

    /**
     * Whether this browser can raise a system notification right now.
     */
    config.alertsAllowed = granted;

    function start() {
        poll();

        window.setInterval(poll, Math.max(15, parseInt(config.pollSeconds, 10) || 60) * 1000);

        // Returning to the tab is exactly when a member wants the latest news.
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                poll();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})(window, document);
