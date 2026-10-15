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

## Tests

`tests/Feature/BirthdayReminderTest.php` covers the recipient query (department +
role + status + campus), the month/day match, the already-sent guard and the
dry-run writing nothing.
