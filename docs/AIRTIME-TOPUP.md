# Airtime top-up

Members ask for **₦200 of airtime once per ISO week** from the top-up button in
`partials/nav`. `App\Http\Controllers\AirtimeController` owns `/airtime-request`
(web + `/api/airtime-request`), `/airtime-check` and `/airtime-history`;
`/airtime_admin` (`AdminAirtimeController`) lists every request and marks it
credited.

## The rules

| Piece | Value |
|-------|-------|
| Window | 07:00-17:59 `Africa/Lagos`, every day (`WINDOW_START_HOUR`, `WINDOW_END_HOUR`) |
| Limit | one row per member per week: `airtime_history.unique_user_week (user_id, year, week)` |
| Week key | **ISO week** - `date('o')` + `date('W')`, never `Y` |
| Stored time | `requested_at` is `now()` (application timezone, UTC); the UI shows it on the Lagos clock |

`o` and `W` must be used together. Pairing `Y` with `W` files the week of
29 December under the old year (29 Dec 2025 is 2026-W01), which collides with
the first week of January and refuses members a week they never asked for.

The duplicate-key catch is deliberately narrow: only a clash on
`unique_user_week` (or a duplicate naming the `user-year-week` tuple) counts as
a repeat request. A duplicate on any other index is a real fault and is logged,
not reworded into "you already asked this week".

## What a repeat request says

The message quotes the row that is on file, in Lagos time:

> You already requested airtime this week on Mon, 19 Oct 2026 at 10:15 AM. Only
> one request per week is allowed.

Compare it with `/airtime_admin` (which now shows `requested_at` on the Lagos
clock). Every refusal is also logged:

```
grep -n "Airtime request blocked" storage/logs/laravel.log | tail -20
```

The entry carries `row_id`, `year`, `week`, `requested_at` and `checked_via`
(`pre-check` or `unique_user_week`).

## The Telegram alert and `config:cache`

Each accepted request pushes a Telegram message to `TELEGRAM_CHAT_ID`. The
credentials are read through `config('services.telegram.*')`, **never** `env()`:
production runs `php artisan config:cache`, and a cached config means Laravel
never loads `.env`. A direct `env('TELEGRAM_BOT_TOKEN')` then returns `NULL`, the
alert is skipped, and the request still saves - the church simply hears nothing.
This is exactly what happened on the live site: requests were arriving, the
Telegram alert was not.

After deploying a change to `config/services.php` (or to `.env`):

```bash
php artisan config:clear && php artisan config:cache
php artisan tinker --execute="var_dump(config('services.telegram.bot_token') !== null);"  # must print bool(true)
```

A skipped alert is no longer silent:

```
grep -n "Telegram alert was skipped" storage/logs/laravel.log | tail -20
grep -n "Telegram notification sent successfully" storage/logs/laravel.log | tail -20
```

## Checking "everyone says they already requested"

That sentence is only ever produced by a row in `airtime_history`. Ask the
database which row:

```sql
SELECT id, user_id, user_name, user_phone, year, week, requested_at, status
FROM airtime_history
WHERE year = YEAR(CURDATE()) AND week = WEEK(CURDATE(), 3)  -- 3 = ISO week
ORDER BY requested_at DESC;
```

A member with no row for the current ISO week cannot be refused by the
pre-check. If a member has a row they do not recognise, the row's
`requested_at` and `user_phone` say who submitted it and when. `requested_at`
is UTC; add one hour for Lagos.

`/airtime-check?user_id=…` returns the same answer the member's app would get,
including the week the server is enforcing (`data.week`, e.g. `2026-W43`).

## Tests

`tests/Feature/AirtimeTopupTest.php` covers the single modal/handler, the
07:00-18:00 window, the unique index, the ISO-year key, the narrow duplicate
match, the dated repeat message, the config-cache credential rule, and one
end-to-end click (inserted inside a transaction that is rolled back).
