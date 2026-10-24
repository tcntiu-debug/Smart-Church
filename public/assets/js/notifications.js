/**
 * In-app notification centre (the navbar bell).
 *
 * The app writes a row to `app_notifications` when a caller is handed first timers
 * to call (WeekListController) or a guide is handed a first timer
 * (AdminAssignmentController). This script does two jobs:
 *
 *   1. It fills the bell's panel (#notificationsModal, see
 *      resources/views/partials/notification-modal.blade.php), a list docked to the
 *      right edge, so opening the bell shows the alerts without leaving the page.
 *      "Mark read" - one row or all of them - posts to the read
 *      endpoint, which deletes the row: the list only ever holds what still needs
 *      attention.
 *   2. It polls the same feed so a new alert also arrives as a browser (system)
 *      notification while the page is open, and - when the server has Web Push
 *      configured (docs/PUSH.md) - it takes out the push subscription that lets
 *      the alert arrive when the app is closed. Once that subscription is in
 *      place the poll stops raising notifications of its own and only keeps the
 *      bell badge fresh, so nothing buzzes twice.
 *
 * The browser only allows a system notification after the member clicks "Enable
 * browser alerts" once (resources/views/notifications/index.blade.php); the poll
 * keeps the badge correct on every browser either way.
 *
 * Config is handed over by resources/views/layouts/app.blade.php:
 *   window.tiuNotifications = { feedUrl, readUrl, readAllUrl, csrfToken,
 *                               panelLimit, pollSeconds, enabled, icon,
 *                               vapidPublicKey, pushStatusUrl, pushSubscribeUrl,
 *                               pushUnsubscribeUrl }
 * The bell and the panel are resolved in locate() once the document is parsed, so
 * it does not matter where in the layout the markup and this file land.
 */
(function (window, document) {
    'use strict';

    var config = window.tiuNotifications;

    if (!config || !config.feedUrl || config.enabled === false) {
        return;
    }

    var SEEN_KEY = 'tiu_alerts_seen';
    var MAX_SEEN = 50;
    var LINGER_MS = 20000;
    var ROW_EXIT_MS = 160;
    var PANEL_LIMIT = parseInt(config.panelLimit, 10) || 30;
    var EMPTY_TITLE = 'You are all caught up.';
    var EMPTY_NOTE = 'New caller and guide assignments appear here.';

    // Web Push (docs/PUSH.md). `pushActive` turns true once this browser's
    // subscription has been filed with the server - from then on the server wakes
    // the phone and the poll stops raising its own system notifications, so an
    // alert cannot buzz twice.
    var pushActive = false;
    var pushBusy = false;

    // The bell and the panel are resolved in locate(), once the document has been
    // parsed - never here: this script tag sits above
    // @include('partials.notification-modal') in layouts/app.blade.php, so while
    // this file is being parsed the panel does not exist yet. Reading it here left
    // `canRenderPanel` false, and the bell then opened a panel whose only content
    // was the "Loading your alerts..." line, forever.
    var bell = null;
    var badge = null;
    var panel = null;
    var list = null;
    var loading = null;
    var empty = null;
    var countChip = null;
    var markAllButton = null;

    // Older iOS Safari has no Notification API: the panel still works there, only
    // the system notification is skipped.
    var canNotify = typeof window.Notification === 'function';
    var canRenderPanel = false;
    var unread = 0;
    var synced = false;
    var panelBusy = false;

    /* ------------------------------------------------------------------ *
     * Requests
     * ------------------------------------------------------------------ */

    function jsonFetch(url, options) {
        var settings = options || {};

        settings.credentials = 'same-origin';
        settings.headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };

        if (settings.method === 'POST' && config.csrfToken) {
            settings.headers['X-CSRF-TOKEN'] = config.csrfToken;
        }

        return window.fetch(url, settings).then(function (response) {
            return response.ok ? response.json() : null;
        });
    }

    /**
     * Reading an alert clears it, so both "read" calls are POSTs.
     *
     * `keepalive` keeps the request alive when the click also navigates away.
     */
    function post(url, keepalive) {
        return jsonFetch(url, { method: 'POST', keepalive: keepalive === true });
    }

    function readUrl(id) {
        return String(config.readUrl || '').replace('__ID__', id);
    }

    function feedUrl(limit) {
        var url = String(config.feedUrl);

        if (!limit) {
            return url;
        }

        return url + (url.indexOf('?') === -1 ? '?' : '&') + 'limit=' + encodeURIComponent(limit);
    }

    function closest(node, selector) {
        while (node && node.nodeType === 1) {
            var matches = node.matches || node.msMatchesSelector;

            if (matches && matches.call(node, selector)) {
                return node;
            }

            node = node.parentNode;
        }

        return null;
    }

    /**
     * Resolve the bell and the panel. Called from start() - i.e. once the whole
     * document has been parsed - because the markup they point at is rendered below
     * this script tag in layouts/app.blade.php.
     */
    function locate() {
        bell = document.querySelector('.ms-notif-bell');
        badge = document.getElementById('notifBadge');
        panel = document.getElementById('notificationsModal');
        list = document.getElementById('notifModalList');
        loading = document.getElementById('notifModalLoading');
        empty = document.getElementById('notifModalEmpty');
        countChip = document.getElementById('notifModalCount');
        markAllButton = document.getElementById('notifMarkAll');

        // No panel on this page (or a cached one from before it existed): the badge
        // and the poll still do their job, they just have nowhere to render a list.
        canRenderPanel = !!(panel && list);
    }

    /* ------------------------------------------------------------------ *
     * The bell badge
     * ------------------------------------------------------------------ */

    /**
     * Keep the badge on the bell in step with the server. A count that grew makes
     * the badge pulse, so a member who is not looking at the bell notices a new
     * alert even before opening it.
     */
    function updateUnread(count) {
        var grew = synced && count > unread;

        unread = count;
        synced = true;

        if (badge) {
            badge.textContent = count > 9 ? '9+' : String(count);
            badge.hidden = count <= 0;
        }

        if (grew && bell) {
            bell.classList.add('is-bell-fresh');

            window.setTimeout(function () {
                bell.classList.remove('is-bell-fresh');
            }, 3600);
        }
    }

    /* ------------------------------------------------------------------ *
     * The panel
     * ------------------------------------------------------------------ */

    function element(tag, className, text) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (text) {
            node.textContent = text;
        }

        return node;
    }

    /**
     * One alert, built from a feed row. textContent throughout: a title or a body
     * is data and must never be parsed as markup.
     */
    function buildRow(row) {
        var item = element('li', 'notif-item');
        item.setAttribute('data-notification-id', row.id);

        var icon = element('span', 'notif-icon notif-icon-' + (row.tone || 'caller'));
        icon.appendChild(element('i', 'fas ' + (row.icon || 'fa-bell')));

        var main = element('div', 'notif-main');
        main.appendChild(element('div', 'notif-title', row.title));

        if (row.body) {
            main.appendChild(element('p', 'notif-body', row.body));
        }

        var meta = element('div', 'notif-meta');
        meta.appendChild(element('span', 'notif-ago', row.ago || 'Just now'));

        if (row.created_at_exact) {
            meta.appendChild(document.createTextNode(' \u00b7 '));

            var exact = element('span', 'notif-exact', row.created_at_exact);
            exact.title = row.created_at || '';
            meta.appendChild(exact);
        }

        main.appendChild(meta);

        var actions = element('div', 'notif-actions');

        if (row.url) {
            var open = element('a', 'btn btn-sm btn-outline-primary notif-open', row.action_label || 'Open');
            open.href = row.url;
            open.title = 'Open the page this alert is about and clear the alert';
            actions.appendChild(open);
        }

        var dismiss = element('button', 'btn btn-sm btn-outline-secondary notif-dismiss');
        dismiss.type = 'button';
        dismiss.title = 'Mark read and remove this alert';
        dismiss.setAttribute('aria-label', 'Mark read and remove this alert');
        dismiss.innerHTML = '<i class="fas fa-check"></i>';
        actions.appendChild(dismiss);

        item.appendChild(icon);
        item.appendChild(main);
        item.appendChild(actions);

        return item;
    }

    function setCount(count) {
        if (countChip) {
            countChip.textContent = count === 1 ? '1 unread' : count + ' unread';
            countChip.hidden = count <= 0;
        }

        if (markAllButton) {
            markAllButton.disabled = count <= 0;
        }

        if (empty) {
            empty.hidden = count > 0;
        }
    }

    function setLoading(on) {
        panelBusy = on;

        if (loading) {
            loading.hidden = !on;
        }

        if (on) {
            if (list) {
                list.innerHTML = '';
            }

            if (empty) {
                empty.hidden = true;
            }
        }
    }

    /**
     * The empty block says two different things: "nothing to do" on a good day and
     * "we could not reach the server" when the feed failed.
     */
    function setEmptyCopy(title, note) {
        if (!empty) {
            return;
        }

        var heading = empty.querySelector('.notif-empty-title');
        var detail = empty.querySelector('.notif-empty-note');

        if (heading) {
            heading.textContent = title;
        }

        if (detail) {
            detail.textContent = note;
        }
    }

    function renderPanel(rows) {
        list.innerHTML = '';

        rows.forEach(function (row) {
            list.appendChild(buildRow(row));
        });

        setEmptyCopy(EMPTY_TITLE, EMPTY_NOTE);
        setCount(rows.length);
    }

    function showPanelError() {
        if (list) {
            list.innerHTML = '';
        }

        setEmptyCopy('Could not load your alerts.', 'Check your connection and open the bell again.');

        if (empty) {
            empty.hidden = false;
        }

        if (countChip) {
            countChip.hidden = true;
        }
    }

    /**
     * Read the feed and fill the panel. Called every time the bell opens, so
     * whatever was assigned while the page sat open shows up too.
     */
    function loadPanel() {
        if (!canRenderPanel || panelBusy) {
            return;
        }

        setLoading(true);

        jsonFetch(feedUrl(PANEL_LIMIT)).then(function (data) {
            setLoading(false);

            if (!data || !Array.isArray(data.notifications)) {
                showPanelError();
                return;
            }

            renderPanel(data.notifications);
            updateUnread(parseInt(data.unread, 10) || 0);
        }).catch(function () {
            setLoading(false);
            showPanelError();
        });
    }

    /**
     * Slide one row out, then take it off the list. The table row is already gone
     * server-side; this is only the farewell.
     */
    function removeRow(item) {
        item.classList.add('notif-item-leaving');

        window.setTimeout(function () {
            if (item.parentNode) {
                item.parentNode.removeChild(item);
            }

            if (list) {
                setCount(list.querySelectorAll('li.notif-item').length);
            }
        }, ROW_EXIT_MS);
    }

    /**
     * "Mark read" on one alert: POST it away, then drop the row and the count.
     */
    function clearRow(item, id, button) {
        if (button) {
            button.disabled = true;
        }

        post(readUrl(id)).then(function (data) {
            if (!data) {
                if (button) {
                    button.disabled = false;
                }

                return;
            }

            removeRow(item);
            updateUnread(parseInt(data.unread, 10) || 0);
        }).catch(function () {
            if (button) {
                button.disabled = false;
            }
        });
    }

    function onListClick(event) {
        var item = closest(event.target, 'li.notif-item');

        if (!item) {
            return;
        }

        var id = item.getAttribute('data-notification-id');
        var dismiss = closest(event.target, '.notif-dismiss');

        if (dismiss) {
            event.preventDefault();
            clearRow(item, id, dismiss);

            return;
        }

        // Following "Open" means the member has seen it: clear the alert first, then
        // let the browser continue. keepalive sees the POST through the navigation.
        if (closest(event.target, '.notif-open')) {
            post(readUrl(id), true).then(function (data) {
                if (data) {
                    updateUnread(parseInt(data.unread, 10) || 0);
                }
            }).catch(function () {
                // The link still works; the alert simply stays until it is read.
            });
        }
    }

    /**
     * "Mark all as read": one request clears the lot.
     */
    function onMarkAll() {
        if (!markAllButton || markAllButton.disabled) {
            return;
        }

        markAllButton.disabled = true;

        post(config.readAllUrl).then(function (data) {
            if (!data) {
                markAllButton.disabled = false;

                return;
            }

            if (list) {
                list.innerHTML = '';
            }

            setEmptyCopy(EMPTY_TITLE, EMPTY_NOTE);
            setCount(0);
            updateUnread(parseInt(data.unread, 10) || 0);
        }).catch(function () {
            markAllButton.disabled = false;
        });
    }

    /**
     * Bootstrap opens the panel from the bell's data-toggle. Hooking its own open
     * event - instead of the click on the bell - re-reads the list however the panel
     * was opened (bell, keyboard, a `.modal('show')` somewhere else) and starts the
     * read while the panel is still sliding in, so the member never sits in front of
     * a spinner that nothing is fetching.
     */
    function wirePanel() {
        if (!canRenderPanel) {
            return;
        }

        list.addEventListener('click', onListClick);

        if (markAllButton) {
            markAllButton.addEventListener('click', onMarkAll);
        }

        // jQuery + Bootstrap 4.1 are always on the page (layouts/app.blade.php). If
        // they are not, the panel cannot open either, so there is nothing to hook.
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
            window.jQuery(panel).on('show.bs.modal', loadPanel);
        }
    }

    function panelOpen() {
        return !!(panel && panel.classList.contains('show'));
    }

    /* ------------------------------------------------------------------ *
     * System notifications (browser alerts)
     * ------------------------------------------------------------------ */

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
        return canNotify && window.Notification.permission === 'granted';
    }

    function show(row) {
        var options = {
            body: row.body || '',
            icon: config.icon || undefined,
            // The same tag the push payload uses, so an alert that somehow arrives
            // by both routes collapses into one notification instead of two.
            tag: 'tiu-notification-' + row.id,
            data: { url: row.url || '' }
        };

        // Android Chrome refuses `new Notification()` ("Illegal constructor"): on a
        // phone a notification has to come from the service worker registration.
        // That is why a phone showed nothing while a laptop did.
        if (navigator.serviceWorker && navigator.serviceWorker.ready) {
            navigator.serviceWorker.ready.then(function (registration) {
                return registration.showNotification(row.title, options);
            }).catch(function () {
                legacyShow(row, options);
            });

            return;
        }

        legacyShow(row, options);
    }

    /**
     * The desktop path, for a browser with no service worker (or one that has not
     * activated yet). Never throws: a blocked or unsupported browser must not break
     * the page.
     */
    function legacyShow(row, options) {
        try {
            var note = new window.Notification(row.title, options);

            note.onclick = function () {
                window.focus();
                note.close();
            };

            window.setTimeout(function () {
                note.close();
            }, LINGER_MS);
        } catch (e) {
            // Nothing to do: the bell and its badge still work.
        }
    }

    /* ------------------------------------------------------------------ *
     * Web Push - the alert that arrives with the app closed
     * ------------------------------------------------------------------ */

    /**
     * Whether this browser can hold a push subscription: HTTPS, a service worker,
     * the Push API, and a VAPID public key from the server (window.tiuNotifications
     * .vapidPublicKey is null until `php artisan push:keys` has been run and the
     * keys are in .env).
     */
    function pushSupported() {
        return !!(config.vapidPublicKey
            && config.pushSubscribeUrl
            && window.isSecureContext
            && navigator.serviceWorker
            && 'PushManager' in window);
    }

    /**
     * base64url -> Uint8Array, which is what applicationServerKey wants.
     */
    function applicationServerKey(value) {
        var base64 = String(value).replace(/-/g, '+').replace(/_/g, '/');
        var padding = new Array((4 - (base64.length % 4)) % 4 + 1).join('=');
        var raw = window.atob(base64 + padding);
        var bytes = new Uint8Array(raw.length);

        for (var i = 0; i < raw.length; i++) {
            bytes[i] = raw.charCodeAt(i);
        }

        return bytes;
    }

    function postJson(url, payload) {
        return window.fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': config.csrfToken || ''
            },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.ok ? response.json() : null;
        });
    }

    /**
     * Make sure this browser has a push subscription and that the server knows the
     * endpoint. Safe to call on every page load: an existing subscription is reused
     * and the endpoint simply refreshed.
     */
    function ensurePushSubscription() {
        if (!pushSupported() || !granted() || pushBusy) {
            return Promise.resolve(pushActive);
        }

        pushBusy = true;

        return navigator.serviceWorker.ready.then(function (registration) {
            return registration.pushManager.getSubscription().then(function (existing) {
                if (existing) {
                    return existing;
                }

                return registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: applicationServerKey(config.vapidPublicKey)
                });
            });
        }).then(function (subscription) {
            var json = subscription.toJSON();

            return postJson(config.pushSubscribeUrl, {
                endpoint: json.endpoint,
                keys: json.keys || {},
                contentEncoding: (subscription.options && subscription.options.contentEncoding) || 'aes128gcm'
            }).then(function (data) {
                pushActive = !!(data && data.subscribed);

                return pushActive;
            });
        }).catch(function () {
            pushActive = false;

            return false;
        }).then(function (active) {
            pushBusy = false;

            return active;
        });
    }

    /**
     * The answer the notification page shows next to its button:
     * "unsupported" | "subscribed" | "failed".
     */
    config.pushState = function () {
        if (!pushSupported()) {
            return Promise.resolve('unsupported');
        }

        return ensurePushSubscription().then(function (active) {
            return active ? 'subscribed' : 'failed';
        });
    };

    /* ------------------------------------------------------------------ *
     * The poll
     * ------------------------------------------------------------------ */

    function poll() {
        var seen = readSeen();
        var returning = seen !== null;

        seen = seen || [];

        jsonFetch(feedUrl()).then(function (data) {
            if (!data || !Array.isArray(data.notifications)) {
                return;
            }

            updateUnread(parseInt(data.unread, 10) || 0);

            var fresh = data.notifications.filter(function (row) {
                return seen.indexOf(String(row.id)) === -1;
            });

            writeSeen(seen.concat(fresh.map(function (row) {
                return String(row.id);
            })));

            // Alert only on a browser we have polled before: on a first visit the
            // backlog would arrive as a dozen system notifications at once. Skipped
            // once Web Push is carrying the alerts - the server wakes the phone and
            // this poll only keeps the bell badge honest.
            if (granted() && returning && !pushActive) {
                fresh.forEach(show);
            }

            // A new alert that lands while the panel is open belongs in the list too.
            if (fresh.length && panelOpen()) {
                loadPanel();
            }
        }).catch(function () {
            // Offline / logged out: the next tick tries again.
        });
    }

    /**
     * Asked by the button on the notification centre page. The promise resolves
     * with the permission string so the page can update its button.
     *
     * Granting the permission is also the moment to take out the push subscription
     * (docs/PUSH.md): that is what makes the alert arrive when the app is closed,
     * and the browser only allows subscribe() after the member has agreed.
     */
    config.requestPermission = function () {
        if (!canNotify || window.Notification.permission !== 'default') {
            return Promise.resolve(canNotify ? window.Notification.permission : 'denied');
        }

        return window.Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') {
                return permission;
            }

            return ensurePushSubscription().then(function () {
                return permission;
            });
        });
    };

    /**
     * Whether this browser can raise a system notification right now.
     */
    config.alertsAllowed = granted;

    function start() {
        locate();

        // A browser without the Notification API still gets the panel (older iOS
        // Safari); one with neither has nothing left to do.
        if (!canNotify && !canRenderPanel) {
            return;
        }

        wirePanel();
        poll();

        window.setInterval(poll, Math.max(15, parseInt(config.pollSeconds, 10) || 60) * 1000);

        // Returning to a tab is exactly when a member wants the latest news.
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                poll();
            }
        });

        // Phone alerts. A member who already allowed notifications must not have to
        // press anything again: the browser can drop a subscription between visits
        // (data cleared, endpoint rotated), so it is re-checked here and the server
        // is told about the endpoint every time.
        if (granted()) {
            ensurePushSubscription();
        }

        // The service worker asks for that re-check when the endpoint changes.
        if (navigator.serviceWorker && navigator.serviceWorker.addEventListener) {
            navigator.serviceWorker.addEventListener('message', function (event) {
                if (event.data && event.data.type === 'tiu:push-resubscribe') {
                    ensurePushSubscription();
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})(window, document);
