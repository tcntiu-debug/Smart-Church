# Birthday reminders (Welcome Center)

`App\Services\BirthdayReminderService` sends one digest per campus a few days
before a member's birthday to the **admins of the Welcome Center**: e-mail, an
in-app bell row (`/birthdays`) and a Telegram push, all in one run.

## When it runs

| Piece | Where |
|-------|-------|
| Command | `php artisan birthdays:remind` (`App\Console\Commands\SendBirthdayReminders`) |
| Schedule | daily at `birthday.send_at` (default `07:00`, `Africa/Lagos`) - `app/Console/Kernel.php` |
| Driven by | the single cPanel cron running `php artisan schedule:run` every minute |
| Output log | `storage/logs/birthday-reminders.log` |
| Switches | `config/birthday.php` (all env driven, nothing to code) |

`php artisan birthdays:remind --dry-run` prints exactly what would be delivered
and writes nothing. `--days=`, `--channel=`, `--campus=` and `--force` narrow a run;
`--force` ignores `birthday_reminder_logs`, the "this milestone was already sent"
guard.

## Watching it - `/birthdays/status`

The Birthdays page carries a **Reminder status** button (Super User, Admin, Admins
and Workers). It answers the three questions a silent digest leaves open, without
a shell:

| Block | What it shows |
|-------|---------------|
| Schedule | when the next run fires, in `birthday.timezone` |
| Warnings | no `birthday-reminders.log` yet (the scheduler never ran on this host), `MAIL_MAILER=log`, no recipients, missing tables |
| Tables | whether `birthday`, `tiu_member`, `birthday_reminder_logs` and `app_notifications` exist |
| Preview | the command's own `--dry-run` output - what today would send, and to whom |
| Recipients | the resolved Welcome Center admins with the address each one will get |
| Recent digests | the last rows of `birthday_reminder_logs` |
| Log tail | the last lines of `storage/logs/birthday-reminders.log` |
| Configuration | mail transport/from, recipient roles, department ids, extra e-mails, Telegram |

Opening the page sends nothing and writes no row: it only ever calls
`birthdays:remind --dry-run`. `tests/Feature/BirthdayStatusTest.php` holds that
promise (log and notification counts are unchanged after two visits).

## If today's run never happened

The scheduler is only as alive as the host cron that drives it. On cPanel the one
entry that matters (`docs/CPANEL-DEPLOYMENT.md`) is:

```
* * * * * cd /home/<account>/smart-church && php artisan schedule:run >> /dev/null 2>&1
```

With that in place the daily 07:00 run happens by itself. To send today's digest
now - for example after the cron was fixed at 09:00 - run it by hand; a milestone
that already went out is skipped unless `--force` says otherwise:

```bash
cd /home/<account>/smart-church

php artisan birthdays:remind --dry-run   # what would go out
php artisan birthdays:remind             # send it now
php artisan birthdays:remind --days=3 --channel=mail --force   # re-send one milestone
```

## Who receives it

Two filters, both configurable:

| Filter | Key | Default |
|--------|-----|---------|
| Department | `birthday.department_ids` (`BIRTHDAY_DEPARTMENT_IDS`) | `28` = Welcome Center |
| Role | `birthday.recipient_roles` (`BIRTHDAY_RECIPIENT_ROLES`) | `Super User,Admin` |

Recipients are resolved **per campus** (`recipientsFor($campusId)`) and members
with `status = 3` (deleted) are never reminded or notified. Want the non-admin
Welcome Center workers to get the digest too? `BIRTHDAY_RECIPIENT_ROLES` =
`Super User,Admin,Member` - no code change.

## A digest sent weeks early ("... has a birthday in 60 days")

Nothing in the code invents a lead time: the command only sends at the
`birthday.days` milestones (`BIRTHDAY_DAYS`), and `config/birthday.php` defaults to
`3,2,1`. A digest that says **"in 60 days"** therefore means the milestone list
that run used contained `60`. Check, in order, on the server:

```bash
cd /home/<account>/smart-church

grep -n 'BIRTHDAY_DAYS' .env                  # 60 there? that is the cause
php artisan tinker --execute="echo implode(',', config('birthday.days'));"   # what the app really reads
crontab -l | grep -i birthdays                # a cron passing --days=60 ?
tail -n 40 storage/logs/birthday-reminders.log  # "Milestones: ..." of each run
```

* `BIRTHDAY_DAYS` is read by `config/birthday.php` (accepted range `0-60`), so a
  leftover `BIRTHDAY_DAYS=60` from a test mails the admins two months early - and
  because it replaces `3,2,1`, the intended milestones are never sent at all.
* A cached config keeps the old value after `.env` is edited. After fixing it run
  `php artisan config:clear` (or `php artisan config:cache`).
* `/birthdays/status` shows the milestones the app is really using under
  **Schedule** (`BIRTHDAY_DAYS`), repeats them at the top of the dry-run preview
  ("Milestones: ..."), and now raises a warning when a milestone is more than
  7 days out. A manual `birthdays:remind --days=60` prints the same warning.
* The already-sent guard is keyed on **milestone + birthday date**, so the wrong
  `60`-day row does not suppress the real `3`, `2` and `1` day digests for that
  birthday. Only a run of the *same* milestone has to be repeated with `--force`.

Correct setting:

```dotenv
BIRTHDAY_DAYS=3,2,1
```

## Which mailbox the digest uses

By default there is **no separate birthday mail setting**: the digest goes out on
`MAIL_MAILER`, the same transport that sends the password-reset link. A working
password-reset e-mail therefore proves the SMTP account itself is fine.

If the Welcome Center should send from its own mailbox, opt in with
`BIRTHDAY_MAIL_MAILER=birthday` and fill in the `BIRTHDAY_MAIL_*` values. Every
one of them falls back to the matching `MAIL_*` value, so only the keys that
really differ have to be set:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=mail.lit-grp.com
MAIL_PORT=587
MAIL_USERNAME=noreply@lit-grp.com
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@lit-grp.com"

# Digest only - password reset keeps using the MAIL_* account above.
BIRTHDAY_MAIL_MAILER=birthday
BIRTHDAY_MAIL_HOST=mail.lit-grp.com
BIRTHDAY_MAIL_PORT=587
BIRTHDAY_MAIL_USERNAME=welcome@lit-grp.com
BIRTHDAY_MAIL_PASSWORD=...
BIRTHDAY_MAIL_ENCRYPTION=tls
BIRTHDAY_MAIL_FROM_ADDRESS="welcome@lit-grp.com"
BIRTHDAY_MAIL_FROM_NAME="Welcome Center"
```

The transport is the `birthday` mailer in `config/mail.php`;
`BIRTHDAY_MAIL_MAILER` must name a mailer defined there. An unknown name makes
every digest fail with "mailer [x] is not defined" and is reported as a warning
on `/birthdays/status`. After editing `.env`, clear a cached config
(`php artisan config:clear`, or run `config:cache` again).

### A digest that never arrived in dev

Work through `/birthdays/status` top to bottom - it shows all of this without a
shell:

1. **Warnings** - "There is no storage/logs/birthday-reminders.log yet" means the
   scheduler has never run `birthdays:remind` on that host. The digest is only
   as alive as the cron entry; dev has **no shell** (`docs/DEPLOY-dev.lit-grp.com.md`),
   so the only trigger is a cPanel **Cron Jobs** entry running
   `php artisan schedule:run` every minute.
2. **Preview (dry run)** - lines ending `no birthdays on ...` mean nothing is due.
   The command only sends at the `BIRTHDAY_DAYS` milestones (`3,2,1` by default),
   and a run with no birthday in that window sends nothing at all.
3. **Recipients** - an empty list means nobody matches
   `BIRTHDAY_DEPARTMENT_IDS` (`28` = Welcome Center) **and**
   `BIRTHDAY_RECIPIENT_ROLES` (`Super User,Admin`) **and** has an e-mail address.
   This is independent of the mail transport.
4. **Recent digests** - a `sent` row for that milestone/date means the digest
   already went out and the duplicate guard is skipping it; re-send with
   `php artisan birthdays:remind --days=3 --force`.
5. **`storage/logs/laravel.log`** - SMTP errors are caught per recipient and do
   not stop the run (the in-app bell and Telegram still deliver), so search for
   `Birthday reminder mail failed`. That line carries the real SMTP reason
   (authentication, sender rejected, connection refused...).
6. **By hand** (where a shell exists) - `php artisan birthdays:remind --dry-run`
   prints exactly what would be sent and to whom; `--days=N --force` re-sends one
   milestone. On dev there is no shell, so use the page's preview and the cron.

## Verified against the real data (15 Oct 2026)

```
$ php artisan birthdays:remind --days=9 --dry-run
  SENT  Ikorodu | 9 days to go | 1 birthday(s) | 2 recipient(s) | mail (2), database (2), telegram (1)
  none  Yaba    | 9 days to go | no birthdays on 24 Oct 2026
Summary: 1 digest(s) sent, 0 skipped, 0 failed | 1 birthday(s) | 2 recipient(s)
```

* `recipientsFor()` on this database: campus 1 (Ikorodu) -> **Adedeji Ayanlaja**
  (`Admin`, id 197) and **Ebunoluwa Adekeye** (`Admin`, id 84); campus 2 (Yaba)
  -> none, so Yaba is simply skipped.
* 9 Welcome Center members exist (ids 197, 84, 205, 442, 524, 324, 454, 122, 301);
  the seven with role `Member` are **not** recipients with the default roles.
* `birthdaysOn()` walks all 228 `birthday` rows and matches on month/day, so the
  stored format (the column is not a `DATE`) is handled by `parseBirthday()` -
  "03 Mar", "3 Mar 1990" and "1990-03-03" all work. Do not try to read that column
  with `DATE_FORMAT()` in SQL: it silently returns NULL for every row.
* On the default milestones (`3,2,1`) the next three days hold no birthday, which
  is why a plain `--dry-run` prints `0 birthday(s)` and delivers nothing - the run
  itself is healthy.
* **`0 recipient(s)` is not a verdict on the admin list.** When a day has no
  birthdays the service `continue`s *before* it resolves recipients
  (`BirthdayReminderService.php:75-85`), so the count is only ever computed for a day
  that actually has one - a healthy run with nothing due therefore reports zero
  recipients even when the Welcome Center admins are configured correctly. Ask the
  audience directly instead (the campus argument is inert in single-campus mode):

  ```bash
  php artisan tinker --execute='echo count(app(App\Services\BirthdayReminderService::class)->recipientsFor(0)), PHP_EOL;'
  ```

  A non-zero answer is the proof that the next real birthday reaches someone; `0`
  means `BIRTHDAY_DEPARTMENT_IDS` / `BIRTHDAY_RECIPIENT_ROLES` match nobody in this
  database - fix that before the first birthday, not after.

## Verified end to end in dev (17 Oct 2026)

`MAIL_HOST` in dev is `mailhog` and nothing listens on it, so the run was pointed
at the log mailer for the check:

```
$ MAIL_MAILER=log php artisan birthdays:remind --days=11 --channel=mail --channel=database --force
  SENT  Ikorodu | 11 days to go | 1 birthday(s) | 2 recipient(s) | mail (2), database (2)
```

* delivery: `storage/logs/laravel.log` holds the digest HTML ("BIRTHDAY REMINDER -
  Ikorodu Campus ... 1 member has a birthday in 11 DAYS (Wednesday, 28 Oct 2026)")
  with the member's `wa.me` wish link, and one message per recipient.
* push: two `app_notifications` rows of type `birthday_reminder` (members 197 and
  84) were written, so the bell carries the same digest.
* audit: `birthday_reminder_logs` gained one row (`days_before = 11`,
  `reminder_date = 2026-10-28`, `sent_on = 2026-10-17`,
  `channels = "mail (2),database (2)"`, `status = sent`).
* the probe row and the two notifications were deleted afterwards so the dev
  database is back to its pre-check state; `--days=11` is not a scheduled
  milestone, so nothing was suppressed.

## Tests

`tests/Feature/BirthdayReminderTest.php` covers the recipient query (department +
role + status + campus), the month/day match, the already-sent guard and the
dry-run writing nothing. `tests/Feature/BirthdayStatusTest.php` covers the status
page: a guest is sent to the login page, an ordinary member is refused, an admin
sees the preview and the scheduler hint, and opening it (or running the dry run)
leaves `birthday_reminder_logs` / `app_notifications` untouched.
