# In-app notifications (the navbar bell)

Every alert the app raises is one row in `app_notifications`. The bell in
`resources/views/layouts/app.blade.php` slides them into a panel docked to the
right edge (`partials/notification-modal`) so the member never loses the page they
are working on; `/notifications` shows the same list as a page, which is both the
no-JavaScript fallback and the place where browser alerts are switched on. Web
only for now: there is no native app and no Web Push subscription, so a phone gets
the alert the next time the page asks for it (`/notifications/feed`).

**Reading an alert clears it.** "Mark read" - one row or "Mark all as read" -
deletes the row, so the list only ever holds what still needs attention and
`read_at` survives only so rows an older build marked read stay out of the way.
That is why both actions are POSTs: `POST /notifications/{id}/read` and
`POST /notifications/read-all` (`NotificationController::markRead()` /
`markAllRead()`). The panel posts them with an XHR and gets JSON back (`deleted`,
`unread`, `message`) so a row can slide out and the badge can drop without a page
load; the plain page posts the same endpoints as forms and is redirected back with
a flash message. Both answers come from `NotificationController::cleared()`.

## Who writes a row

| Type | Written by | When | Click target |
|------|------------|------|--------------|
| `caller_assignment` | `WeekListController::assignAllCallers()` (and `saveCallerAssignment()`) | a caller was handed first timer(s) to call | `/weeklist` |
| `guide_assignment` | `AdminAssignmentController::ajaxAssign()` / `assign()` / `generatePairings()` / `WeekListController::assignGuides()` | a guide was handed first timer(s) to follow up | `/my-tasks` |
| `birthday_reminder` | `BirthdayReminderService` (the `birthdays:remind` cron) | a birthday digest for the Welcome Center admins | `/birthdays` |
| `announcement` | `AnnouncementController::store()` (web) and `Api\AnnouncementController::store()` (token API) | a notice was published; editing one is silent | `/notice` |

All of them go through `App\Services\NotificationService`:

```php
$notifications = app(\App\Services\NotificationService::class);

$notifications->notify($memberId, NotificationService::TYPE_CALLER_ASSIGNMENT,
    'You have 3 new first timers to call', 'Ada, Bola and 1 other', ['url' => '/weeklist']);
```

* `notify()` writes one row and never throws - a failed alert must not fail the
  assignment that triggered it (the failure is logged instead).
* `notifyMany()` writes the same alert for several members (ids are de-duplicated).
* `notifyAudience()` writes the same alert for a whole list of members in bulk
  (250 rows per INSERT) instead of one statement per member; it de-duplicates and
  skips ids that are `0`, and like `notify()` it never throws - a failed bell write
  is logged and reported as `0`.
* `notifyEach()` writes a different message per member, keyed by member id.
* A member id of `0` is nobody (that is how a released guide is stored) and is
  skipped, as is an empty title.

Re-saving the same assignment is silent: the controllers compare against the
current `caller_assignments` / `member_tracking_followup` row and only notify when
the caller / guide actually changed.

## Publishing a notice rings every bell once

`NotificationService::announce($id, $title, $content, $campusId)` is called by the
web form (`/admin-announcements/store`) and by the token API
(`POST /api/announcements/store`), so a notice published from a phone is heard the
same way as one published from the dashboard:

* `announcementAudience()` is every living member (`status != 3`), narrowed to the
  campus the notice was written for **only** while the church runs more than one
  campus; in single campus mode rows carry `campus_id = NULL` and everybody is in.
* The title is the notice title; the body is a 180-character single-paragraph
  excerpt (`excerpt()`), so a long notice does not dump walls of text into the bell.
* The payload is `{"announcement_id": 41, "url": "/notice"}` - the bell's "Open
  notice board" button lands on `/notice`, not on the admin page.
* `update()` deliberately does not call it: correcting a typo must not shout a
  second time.

The public site cannot read `/notice` (it sits behind `auth`), so it uses the
anonymous feed instead:

```json
GET /api/public/announcements?limit=5&campus_id=1
{ "success": true, "count": 1, "data": [ { "id": 41, "title": "…",
  "content": "…", "excerpt": "…", "posted_at": "2026-10-17T09:00:00+01:00",
  "posted_at_human": "Oct 17, 2026 9:00 AM", "campus_id": null, "campus": null } ],
  "meta": { "limit": 5, "generated_at": "…" } }
```

`limit` is 1-50 (default 10) and rejected with 422 outside that range. The query
selects `id, title, content, created_at, campus_id` only - no member or PII column
is ever read - and the answer carries `Cache-Control: public, max-age=60`.
`config/cors.php` already answers `api/*` with `Access-Control-Allow-Origin: *`.

The path itself is a hosting trap: it contains `/public/`, and the repository's
root `.htaccess` (which turns the cPanel document root into a Laravel front
controller) used to answer such paths with the web server's own 404, so the
endpoint worked under `php artisan test` and 404'd on `dev.lit-grp.com`. The guard
is now `RewriteRule ^public/ - [L]` - only paths that *start* with `public/` are
left alone - and `PublicAnnouncementApiTest` reads the file to keep it that way.

## The bell's panel (and the feed behind it)

The panel is `partials/notification-modal`, docked to the right edge by the
`#notificationsModal .notif-dialog` rules in `layouts/app.blade.php`. Bootstrap 4.1
ships no offcanvas component, so the dialog is placed by hand - with the id in the
selector, so the slide-out beats Bootstrap's own `.modal.fade .modal-dialog`. It
slides in over the page the member was working on, closes on `Esc` or on a click
outside, and takes the whole screen on a phone.

`GET /notifications/feed` is the one read path: the bell's panel fills itself from
it and the layout polls it for browser notifications.

```json
{
  "unread": 3,
  "notifications": [
    {
      "id": 41, "type": "caller_assignment",
      "title": "You have 3 new first timers to call", "body": "Ada, Bola and 1 other",
      "url": "https://.../weeklist", "created_at": "2026-10-14 09:12:00",
      "created_at_exact": "Wed, 14 Oct 2026 9:12 AM", "ago": "2 hours ago",
      "icon": "fa-phone", "action_label": "Open call list", "tone": "caller"
    }
  ]
}
```

* `?limit=` asks for more rows than the poll needs - capped at 50 and never below
  1; the panel asks for 30, the poll uses `feed_limit`.
* Every row is shaped by `NotificationController::present()`, so the icon, the
  button label and the timestamp have one definition and the panel and the page
  cannot drift apart. `ICONS` / `ACTIONS` / `TONES` are the per-type lookups; the
  panel paints the icon in the row's `tone` (`notif-icon-caller`, `-guide`, `-task`,
  `-birthday`) - see the `notif-*` CSS in `layouts/app.blade.php`.
* `public/assets/js/notifications.js` re-reads the feed every time the bell opens -
  it hooks Bootstrap's own `show.bs.modal`, so the click on the bell is all it takes
  (there is no second button to press) - and again when a fresh alert lands while the
  panel is open, so the list is never stale. The bell and the panel are resolved once
  the document is parsed (`locate()`), because the layout renders the panel *below*
  the script tag.
* `url` is where "Open" goes: the `url` the writer stored, the type's default from
  `config/notifications.php`, or `/notifications` when neither exists
  (`NotificationService::urlFor()`).

The panel and the page share the `notif-*` classes; the page is plain Blade with
`@csrf` POST forms, so it keeps working with JavaScript off. The bell's `href`
still points at `/notifications`, which is where the "Browser alerts" link in the
panel's footer goes.

## Config - `config/notifications.php`

| Key | .env | Default | Meaning |
|-----|------|---------|---------|
| `enabled` | `NOTIFICATIONS_ENABLED` | `true` | master switch |
| `types` | - | caller / guide / weekly task / announcement | types allowed to be written |
| `feed_limit` | `NOTIFICATIONS_FEED_LIMIT` | `10` | rows the poll gets from `/notifications/feed` (`?limit=` can ask for more, up to 50) |
| `poll_seconds` | `NOTIFICATIONS_POLL_SECONDS` | `60` | how often the layout polls (minimum 15) |
| `urls` | - | per type | fallback click target when the row has no `url` |

Removing a type from `types` leaves the feature that raises it in place; it simply
writes nothing.

## Browser (system) notifications

1. The member presses **Enable browser alerts** once - from the bell's panel
   ("Browser alerts" in its footer) or from the page at `/notifications`. That is
   the only time the browser asks for permission (`requestPermission()` in
   `public/assets/js/notifications.js`).
2. The layout polls `/notifications/feed` every `poll_seconds` (and whenever the tab
   becomes visible again). A row that has not been shown on that browser yet is
   raised as a system notification, clicking it opens the member's `url`.
3. The bell badge is refreshed from the same response, so it stays correct even
   before permission is granted; a count that grew makes the bell pulse once
   (skipped under `prefers-reduced-motion`).

Notes:

* The first poll on a browser records what is already unread instead of firing a
  burst of system notifications; "seen" ids live in `localStorage`
  (`tiu_alerts_seen`, last 50).
* Browsers without the `Notification` API (older iOS Safari) raise nothing from the
  poll - the bell, its badge and the panel work there all the same.
* On iOS the app must be added to the Home Screen before a site can raise a system
  notification - that is the "Install app" button from `partials/pwa-install-button`.

## Deploy

`app_notifications` is created by the deploy pipeline: `php artisan app:sync-schema`
(and `app:post-deploy`), which also runs on the daily scheduler. `BirthdayReminderService`
applies the same migration on its first run. If the table is missing, writes are
logged and skipped rather than breaking the request.

## Tests

`tests/Feature/AssignmentNotificationTest.php` covers the service (storage, the
`0`/blank-title guards, the config switches, de-duplication, click targets), the
call sites end to end - the drag & drop page, the week list's caller list and the
"Assign all callers" button inside its modal - including "saving the same caller /
guide twice does not notify twice", and the centre itself: the feed endpoint (only
the member's own unread rows, with `url`), the browser-alert button, "reading an
alert clears it", "mark all as read empties the list", the page's POST form, that every
authenticated page ships the right-docked bell panel with the list hooks
`public/assets/js/notifications.js` fills in, and that the script waits for the
document before it reads those hooks - reading them while it was parsed left the
panel spinning on "Loading your alerts..." for ever.

`tests/Feature/AnnouncementNotificationTest.php` covers the fan-out: publishing a
notice writes exactly one `announcement` row per living member (with the excerpt
body and the `/notice` payload), editing it writes none, and the bell offers
"Open notice board". `tests/Feature/PublicAnnouncementApiTest.php` pins the
anonymous feed: newest first, the documented keys only, `limit` validation, no
member column in the payload, and the wildcard CORS header the Vercel site needs.
