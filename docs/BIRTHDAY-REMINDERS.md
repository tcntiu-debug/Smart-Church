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
