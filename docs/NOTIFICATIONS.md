# In-app notifications (the navbar bell)

Every alert the app raises is one row in `app_notifications`, shown by the bell in
`resources/views/layouts/app.blade.php` and listed on `/notifications`. Web only
for now: there is no native app and no Web Push subscription, so a phone gets the
alert the next time the page asks for it (`/notifications/feed`).

## Who writes a row

| Type | Written by | When | Click target |
|------|------------|------|--------------|
| `caller_assignment` | `WeekListController::assignAllCallers()` (and `saveCallerAssignment()`) | a caller was handed first timer(s) to call | `/weeklist` |
| `guide_assignment` | `AdminAssignmentController::ajaxAssign()` / `assign()` / `generatePairings()` | a guide was handed first timer(s) to follow up | `/my-tasks` |
| `birthday_reminder` | `BirthdayReminderService` (the `birthdays:remind` cron) | a birthday digest for the Welcome Center admins | `/birthdays` |

All of them go through `App\Services\NotificationService`:

```php
$notifications = app(\App\Services\NotificationService::class);

$notifications->notify($memberId, NotificationService::TYPE_CALLER_ASSIGNMENT,
    'You have 3 new first timers to call', 'Ada, Bola and 1 other', ['url' => '/weeklist']);
```

* `notify()` writes one row and never throws - a failed alert must not fail the
  assignment that triggered it (the failure is logged instead).
* `notifyMany()` writes the same alert for several members (ids are de-duplicated).
* `notifyEach()` writes a different message per member, keyed by member id.
* A member id of `0` is nobody (that is how a released guide is stored) and is
  skipped, as is an empty title.

Re-saving the same assignment is silent: the controllers compare against the
current `caller_assignments` / `member_tracking_followup` row and only notify when
the caller / guide actually changed.

## Config - `config/notifications.php`

| Key | .env | Default | Meaning |
|-----|------|---------|---------|
| `enabled` | `NOTIFICATIONS_ENABLED` | `true` | master switch |
| `types` | - | caller / guide / weekly task | types allowed to be written |
| `feed_limit` | `NOTIFICATIONS_FEED_LIMIT` | `10` | rows returned by `/notifications/feed` |
| `poll_seconds` | `NOTIFICATIONS_POLL_SECONDS` | `60` | how often the layout polls (minimum 15) |
| `urls` | - | per type | fallback click target when the row has no `url` |

Removing a type from `types` leaves the feature that raises it in place; it simply
writes nothing.

## Browser (system) notifications

1. The member opens `/notifications` and presses **Enable browser alerts** once.
   That is the only time the browser asks for permission (`requestPermission()` in
   `public/assets/js/notifications.js`).
2. The layout polls `/notifications/feed` every `poll_seconds` (and whenever the tab
   becomes visible again). A row that has not been shown on that browser yet is
   raised as a system notification, clicking it opens the member's `url`.
3. The bell badge is refreshed from the same response, so it stays correct even
   before permission is granted.

Notes:

* The first poll on a browser records what is already unread instead of firing a
  burst of system notifications; "seen" ids live in `localStorage`
  (`tiu_alerts_seen`, last 50).
* Browsers without the `Notification` API (older iOS Safari) are skipped entirely.
* On iOS the app must be added to the Home Screen before a site can raise a system
  notification - that is the "Install app" button from `partials/pwa-install-button`.

## Deploy

`app_notifications` is created by the deploy pipeline: `php artisan app:sync-schema`
(and `app:post-deploy`), which also runs on the daily scheduler. `BirthdayReminderService`
applies the same migration on its first run. If the table is missing, writes are
logged and skipped rather than breaking the request.

## Tests

`tests/Feature/AssignmentNotificationTest.php` covers the service (storage, the
`0`/blank-title guards, the config switches, de-duplication, click targets) and the
three call sites end to end, including "saving the same caller / guide twice does not
notify twice".
