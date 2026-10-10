<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Birthday Reminders (Welcome Center)
    |--------------------------------------------------------------------------
    |
    | A scheduled job sends an e-mail + push digest to the admins of the
    | Welcome Center a few days before each member's birthday. Every value
    | below can be tuned from .env without touching any code.
    |
    */

    // Days BEFORE the birthday on which a reminder is sent (3 days to go,
    // 2 days to go, 1 day to go). Add 0 to remind on the birthday itself.
    'days' => array_values(array_filter(
        array_map('intval', array_map('trim', explode(',', (string) env('BIRTHDAY_DAYS', '3,2,1')))),
        function ($day) {
            return $day >= 0 && $day <= 60;
        }
    )),

    // Timezone the birthdays are calculated in (the church's local time).
    'timezone' => env('BIRTHDAY_TIMEZONE', 'Africa/Lagos'),

    // Local time the daily reminder runs at.
    'send_at' => env('BIRTHDAY_SEND_AT', '07:00'),

    // Departments whose Admins receive the reminder. 28 = Welcome Center.
    // Comma separated so more departments can be added later.
    'department_ids' => array_values(array_filter(
        array_map('intval', array_map('trim', explode(',', (string) env('BIRTHDAY_DEPARTMENT_IDS', '28'))))
    )),

    // Member roles (tiu_member.member_role) that count as an admin recipient.
    'recipient_roles' => ['Super User', 'Admin'],

    // tiu_member.status values that are never reminded about (3 = deleted).
    'excluded_status' => ['3'],

    // Channels the digest goes out on: mail, database (in-app bell), telegram.
    'channels' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('BIRTHDAY_CHANNELS', 'mail,database,telegram')))
    )),

    // Extra e-mail recipients, comma separated (e.g. the campus pastor).
    'extra_emails' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('BIRTHDAY_EXTRA_EMAILS', '')))
    )),

    // Telegram push - re-uses the bot already configured for airtime alerts.
    // Falls back to TELEGRAM_CHAT_ID when no birthday specific chat is set.
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
        'chat_ids' => array_values(array_filter(
            array_map('trim', explode(',', (string) env(
                'BIRTHDAY_TELEGRAM_CHAT_IDS',
                (string) env('TELEGRAM_CHAT_ID', '')
            )))
        )),
    ],

    // How long a member's details are shown as a "wa.me" birthday wish link.
    'wish_country_code' => env('BIRTHDAY_WISH_COUNTRY_CODE', '234'),

];
