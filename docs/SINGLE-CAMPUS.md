# Single campus mode

TCN Ikorodu is one church again, so the campus layer in the app is **retired**.
Ikorodu (`campus.cid = 1`) is the only campus in use: the filters no longer narrow
anything, new rows are left without a campus, and the `campus_id` that was already
on the rows has been emptied. The columns are kept so a second campus can be
opened later.

## The switch

`config/campus.php` (+ the block in `.env.example`):

| setting | default | meaning |
| --- | --- | --- |
| `SINGLE_CAMPUS` | `true` | every campus filter is inert and no campus is stamped |
| `DEFAULT_CAMPUS_ID` | `1` | the campus the church runs: what the digest, the campus-table fallback and the undo step of the retirement name (nothing is stamped with it) |
| `DEFAULT_CAMPUS_NAME` | `Ikorodu` | name used when the `campus` table has no usable row |

Turning the campus layer back on is one line - plus the undo of the data
retirement, because the ids are gone by then:

```
SINGLE_CAMPUS=false
php artisan migrate:rollback --step=1     # 2026_10_14_000003: ids back on the rows
php artisan config:clear
```

With the switch off the app behaves like the multi-campus app did: a logged-in
user only sees their own campus, a Super User sees everything, and new rows are
stamped again.

## How a call site reads now

```php
// a filter (a read: which rows are shown)
->forCurrentCampus('ft.campus_id', $userCampusId)

// a write (the column is left empty while the church runs one campus)
'campus_id' => Campus::writeId($request->campus_id),

// row identity (a read-then-rewrite: fetch the row you are about to change)
Campus::matchQuery(DB::table('task')->where('wid', $week), 'campus_id', $campusId)
```

`forCurrentCampus()` is a macro on both builders (`Illuminate\Database\Eloquent\Builder`
and `Illuminate\Database\Query\Builder`), registered in
`AppServiceProvider::boot()`. It asks `Campus::filterId()`, which is `null` in
single campus mode (so no `where()` is added) and the requested / logged-in
campus otherwise. `Campus` also exposes `isSingle()`, `defaultId()`,
`defaultName()`, `currentId()`, `filterQuery()` and `matchQuery()`.

Three rules matter when touching this area:

1. **A `where('campus_id', …)` that identifies rows to mutate is not a filter.**
   It has to match the value `writeId()` writes - `NULL` today - or the row it
   read is not the row it rewrites. That is what `Campus::matchQuery()` says out
   loud (`WeekTaskController::destroy()` uses it). `CommunityController` and
   `ManageDepartmentController` deliberately stayed on the macro instead, so a
   Super User without a campus of their own can still edit.
2. **Writes go through `Campus::writeId()`**, which is `null` in single campus
   mode: nothing is stamped with a campus nobody picks any more, and a stale form
   field cannot put campus 2 back on a row.
3. **A campus name on screen falls back, it does not crash.** The campus joins are
   `leftJoin` (`AdminFirstTimerController`), so `campus_name` comes back `null`
   and the view shows `—`; the week-task header names the campus the church runs
   when there is no id to resolve.

## The data retirement

Two migrations, applied through `app:sync-schema` / `migrate` (see below), in this
order:

| migration | what it does |
| --- | --- |
| `2026_10_14_000002_make_campus_id_nullable` | makes `campus_id` nullable on `announcements`, `birthday_reminder_logs`, `children_church`, `communities`, `department`, `first_timer`, `first_time_view_limit`, `sub_group`, `tiu_member` (the already nullable ones - `task`, `app_notifications`, `child_ceremonies`, `prayer_suggestions` - need nothing) |
| `2026_10_14_000003_retire_the_campus_id_data` | empties `campus_id` on every table that carries one. `up()` only runs while `SINGLE_CAMPUS=true`, so an accidental `migrate` on a multi-campus install cannot empty a column that still routes its rows; `down()` puts the ids back on `DEFAULT_CAMPUS_ID` |

The order is not cosmetic: a row can only be left empty once the column allows it,
and the column can only go back to `NOT NULL` once no row is empty. Deploy the
code first (it writes `NULL`), then the migrations - or both together, since
nothing reads `campus_id` for identity except `matchQuery()`, which matches the
blank it writes.

`app:sync-schema` (also part of `app:post-deploy`, and on the daily schedule at
03:20) applies both files with `migrate --path`, one guarded file at a time - a
plain `migrate` aborts on this database (see `docs/CPANEL-DEPLOYMENT.md`).

## What this changes for the church

* The week list, members, first timers, announcements, departments, communities,
  sub-groups, weekly tasks, dashboards and charts are no longer narrowed by
  campus - nothing is hidden because of a campus value any more.
* The birthday digest (`php artisan birthdays:remind`) processes **one** campus.
  Campus 2 (Yaba) used to produce a "no recipients / skipped" line every day
  forever; it is no longer processed at all. `--campus=<id>` still forces a
  specific campus for support. The notification and log rows it writes carry the
  campus the app writes, i.e. none.
* The campus selectors are gone from the two registration forms
  (`member/public-register.blade.php`, the admin `member/register.blade.php`) and
  from the profile form (hidden while the switch is on). The marketplace and
  week-list campus filters are still rendered but do nothing; hiding them is a
  follow-up, not part of this change.
* The campus map (`/map-view`, `Api\MapController`) lists every community again:
  its raw `AND c.campus_id = ?` used to match nothing once the column was empty,
  in the same way a plain campus filter would have.
* A campus name that is gone degrades instead of breaking: the first-timer list
  shows `—` (the campus join was already a `leftJoin`), the week-task header
  names the campus the church runs, and the mobile payloads that report a campus
  (`/api/login`, `/api/pending-tasks`) report `DEFAULT_CAMPUS_ID` instead of a
  blank or a zero.

## Tests

* `tests/Feature/SingleCampusTest.php` - the lock-in: the switch is on, the macro
  is on both builders, a filtered query builds the *same* SQL as an unfiltered
  one, `writeId()` is null (posted campus or not), `matchQuery()` matches the
  blank campus, the columns accept a blank campus and no row carries one any
  more, the map still lists every community, a task row written without a campus
  is the row the delete finds (and a row that does carry one is left alone), a
  registration goes through without a campus, the digest is not split over
  campuses and the command names only Ikorodu. The last test flips the switch off
  in-memory and proves the campus layer comes back.
* `tests/Feature/BirthdayReminderTest.php` - made mode-aware, so it holds in both
  modes.
* `tests/Feature/SyncSchemaTest.php` - the two retirement migrations are recorded
  by `app:sync-schema`, so a later `migrate` cannot try them twice.

```
vendor\bin\phpunit --filter SingleCampusTest
vendor\bin\phpunit
```

## Inventory of the change

* ~70 `->forCurrentCampus()` call sites in 24 controllers, the two
  `scopeByCampus()` model scopes (`ChildrenChurch`, `TiuMember`) and the birthday
  service.
* The filters that were written as raw SQL were converted to `Campus::filterId()`
  too, so nothing is left narrowing on the quiet: the bar/pie charts
  (`ChartController`, month/year totals and the year list included), the pending
  task board and its touchpoint list (`PendingTasksController`) and the
  community map (`Api\MapController`, `MapController`).
* 15 write sites stamped through `Campus::writeId()`, plus the two the birthday
  service writes (`birthday_reminder_logs`, `app_notifications`).
* `CheckProfileComplete` no longer demands a campus while single campus mode is
  on.
* Row identity for the weekly tasks goes through `Campus::matchQuery()`, so the
  delete/update reads the row that was written.
* `app:sync-schema` applies the two retirement migrations (and had a latent bug
  fixed on the way: `migrate --path` was handed an absolute path, so a pending
  file came back as "File does not exist at path C:\.../C:\...").
* The `campus` rows (1 Ikorodu, 2 Yaba) and the global `CampusScope` are
  untouched: only the `campus_id` on the church's own rows is retired. The data
  that was emptied is backed up in `storage/app/campus-id-backup-2026-10-14.sql`
  on the machine where the retirement ran.

