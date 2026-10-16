# Production database vs local — is it in sync?

**Answer: NO.** Comparing the production dump
(`C:\xampp\htdocs\legacy-smart-church-files\tcnikoro_smart_church.sql`, generated
Oct 14 2026 19:15) with the local database `tcnikoro_smart_church`
(checked 15 Oct 2026):

| | Production dump | Local |
|---|---|---|
| Tables | 43 | 41 |
| Columns | 326 | 348 |

The dump was imported into a scratch database `sc_prod_check` so the two can be
diffed with plain SQL (`information_schema`), without touching either live schema.

```powershell
# how the numbers above were produced (already done once)
C:\xampp\mysql\bin\mysql.exe -u root -e "DROP DATABASE IF EXISTS sc_prod_check; CREATE DATABASE sc_prod_check CHARACTER SET utf8mb4"
C:\xampp\mysql\bin\mysql.exe -u root sc_prod_check -e "source C:/xampp/htdocs/legacy-smart-church-files/tcnikoro_smart_church.sql"
```

## 1. Tables to drop by hand in production (13)

You asked for the names. These 13 tables exist in production and not in the
current code base:

| Table | Why it can go |
|-------|---------------|
| `transport_routes`, `transport_stops`, `bus_attendance` | Transport module, retired 2026-05-25 (`2026_05_25_000003_drop_transport_tables`) |
| `fof_cohort_setting`, `fof_register_table`, `fof_mark_attendance_table` | Stand-alone FOF programme, retired 2026-05-25 (`2026_05_25_000004_drop_fof_tables`) |
| `call_center`, `call_tree_assignments`, `call_tree_feedback` | old call-centre module, no reference in the code |
| `first_timer_gallery` | photo gallery of first timers, no reference in the code |
| `gift_logs` | no reference in the code |
| `member_telegram` | no reference in the code (telegram chat ids come from config) |
| `whatapp` | no reference in the code |

Ready to paste (the 6 transport / FOF ones are dropped automatically by
`php artisan app:sync-schema`, which also runs daily at 03:20 on the server; the
other 7 have no migration because the current code never touched them):

```sql
DROP TABLE IF EXISTS `bus_attendance`;
DROP TABLE IF EXISTS `transport_stops`;
DROP TABLE IF EXISTS `transport_routes`;
DROP TABLE IF EXISTS `fof_mark_attendance_table`;
DROP TABLE IF EXISTS `fof_register_table`;
DROP TABLE IF EXISTS `fof_cohort_setting`;
DROP TABLE IF EXISTS `call_tree_feedback`;
DROP TABLE IF EXISTS `call_tree_assignments`;
DROP TABLE IF EXISTS `call_center`;
DROP TABLE IF EXISTS `first_timer_gallery`;
DROP TABLE IF EXISTS `gift_logs`;
DROP TABLE IF EXISTS `member_telegram`;
DROP TABLE IF EXISTS `whatapp`;
```

Every one of these holds rows in the dump (each has at least one `INSERT` block),
so keep the dump - or export the tables - before dropping.

## 2. Do NOT drop `messages`

`messages` exists in production only, and it is still live code:
`AdminFirstTimerController` writes to it (`DB::table('messages')->insert(...)`, the
first-timer conversation). Keep it in production. It is **missing locally**, so that
screen cannot work on this machine until the table is imported or created - the
mismatch runs the other way here.

## 3. What production is missing — this is what would break

Deploying this code base to `https://tiu.tcnikorodu.org/` **will break** unless the
following are created there first. These exist locally and not in production:

**Whole tables (12)**

| Table | Needed by |
|-------|-----------|
| `campus` | 22 places in the code (filters, joins, `BirthdayReminderService`) |
| `children_church`, `children_church_attendance`, `child_ceremonies` | `Api\ChildrenChurchController`, `AttendanceAnalysisController`, `ChildCeremony` |
| `app_notifications`, `birthday_reminder_logs`, `member_tracking_followup_archive` | the notification bell, the birthday cron, `/weeklist/guide-history` |
| `migrations`, `users`, `password_resets`, `personal_access_tokens`, `failed_jobs` | framework tables (auth itself runs on `tiu_member`, see `config/auth.php`) |

The three feature tables are created by `php artisan app:sync-schema`
(`app:post-deploy`), which is idempotent and safe to run on production.

**Columns the live code reads but production does not have**

| Table | Missing in production |
|-------|----------------------|
| `first_timer` | `campus_id`, `unassign_reason` |
| `caller_assignments` | `caller_member_id` (production only has `assigned_at` / `assigned_by`) |
| `member_tracking_followup` | `department_id` |
| `task` | `campus_id`, `department_id` (production still calls it `extra`) |
| `sub_group` | `campus_id`, `department_id` (production has `department_name`) |
| `department`, `communities`, `announcements`, `first_time_view_limit` | `campus_id` |
| `church_attendance` | `created_at`, `updated_at` |
| `airtime_history` | `user_name`, `user_phone`, `status`, `created_at`, `updated_at` |
| `first_timers_updates` | `house_fellowship` (production spells it `house_felloship`) |
| all `children_church*` / `child_ceremonies` | whole table missing (see above) |

## 4. Why `php artisan migrate` is not the way to fix production

Production has no `migrations` table, so a plain `migrate` would try to replay
every migration from 2014 onward. The create-table migrations for objects that
already exist in production (`campus`, `sub_group`, `department`,
`announcements`, `first_time_view_limit`, `first_timer`, `caller_assignments`,
`children_church`, `child_ceremonies`, ...) have no `Schema::hasTable()` guard, so
the run aborts part way through - this is exactly why `app:sync-schema` exists and
why it applies one guarded file at a time.

So the production reconciliation has to be an additive script (create the missing
tables + `ALTER TABLE ... ADD COLUMN` for the list above, with the `messages` table
untouched), run once, and then verified. That is a separate step - say the word and
it can be generated from `sc_prod_check` (the diff is already reproducible, see
section 1).

Afterwards the honest verification is:

```sql
-- tables that no longer belong in production
SHOW TABLES LIKE 'fof%'; SHOW TABLES LIKE 'transport%'; SHOW TABLES LIKE 'bus_attendance';

-- the ones the code needs
SHOW COLUMNS FROM first_timer LIKE 'campus_id';
SHOW COLUMNS FROM caller_assignments LIKE 'caller_member_id';
SHOW COLUMNS FROM member_tracking_followup LIKE 'department_id';
```

One requirement on those `campus_id` columns in particular: the deployed code now
writes `NULL` into them (single campus mode - see `docs/SINGLE-CAMPUS.md`), so an
`ADD COLUMN ... NOT NULL` would reject every new row and every profile save. Add
them (or alter them) as **nullable**, and let `php artisan app:sync-schema` retire
the values that are already there (`2026_10_14_000002` makes the columns nullable,
`_000003` empties them).


