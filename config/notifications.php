<?php

return [

    /*
    |--------------------------------------------------------------------------
    | In-app notifications (the navbar bell)
    |--------------------------------------------------------------------------
    |
    | Rows are written to `app_notifications` by App\Services\NotificationService
    | (assignment alerts) and App\Services\BirthdayReminderService (birthday
    | digests). The layout polls /notifications/feed so an open page - on a phone
    | or on a laptop - turns a new row into a browser notification.
    |
    | Every value below can be tuned from .env without touching any code.
    |
    */

    // Master switch: false silences every alert written by the service.
    'enabled' => env('NOTIFICATIONS_ENABLED', true),

    // Types that may be written. Removing one here leaves the feature that
    // raises it in place; it simply writes nothing.
    'types' => [
        'caller_assignment',
        'guide_assignment',
        'weekly_task',
        'announcement',
    ],

    // How many unread notifications the poll request returns.
    'feed_limit' => (int) env('NOTIFICATIONS_FEED_LIMIT', 10),

    // Seconds between two polls of /notifications/feed (minimum 15).
    'poll_seconds' => max(15, (int) env('NOTIFICATIONS_POLL_SECONDS', 60)),

    // Where a click lands when the payload carries no `url` of its own.
    'urls' => [
        'caller_assignment' => '/weeklist',
        'guide_assignment' => '/my-tasks',
        'weekly_task' => '/my-tasks',
        'announcement' => '/notice',
    ],

];
