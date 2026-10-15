# Single campus mode

TCN Ikorodu is one church again, so the campus filter layer in the app is
**retired**. Ikorodu (`campus.cid = 1`) is the only campus in use; the `campus_id`
columns are kept on the tables, and the filters are kept in the code, but they no
longer narrow anything.

## The switch

`config/campus.php` (+ the block in `.env.example`):

| setting | default | meaning |
| --- | --- | --- |
| `SINGLE_CAMPUS` | `true` | every campus filter is inert |
| `DEFAULT_CAMPUS_ID` | `1` | campus stamped on new rows (Ikorodu) |
| `DEFAULT_CAMPUS_NAME` | `Ikorodu` | name used when the `campus` table has no usable row |

Turning it back on is one line - no code change, no redeploy of 24 controllers:

```
SINGLE_CAMPUS=false
php artisan config:clear
```

With the switch off the app behaves like the multi-campus app did: a logged-in
user only sees their own campus, a Super User sees everything.

## How a call site reads now

```php
// a filter (a read: which rows are shown)
->forCurrentCampus('ft.campus_id', $userCampusId)

// a write (the column stays filled, a stale form field cannot stamp campus 2)
'campus_id' => Campus::writeId($request->campus_id),
```

`forCurrentCampus()` is a macro on both builders (`Illuminate\Database\Eloquent\Builder`
and `Illuminate\Database\Query\Builder`), registered in
`AppServiceProvider::boot()`. It asks `Campus::filterId()`, which is `null` in
single campus mode (so no `where()` is added) and the requested / logged-in
campus otherwise. `Campus` also exposes `isSingle()`, `defaultId()`,
`defaultName()`, `currentId()` and `filterQuery()`.

Two rules matter when touching this area:

1. **A `where('campus_id', …)` that identifies rows to mutate is not a filter.**
   `->where('campus_id', $x)->update(…)` / `->delete()` stay as they are
   (`CommunityController`, `ManageDepartmentController` are the pairs that were
   converted to the macro on purpose, so a Super User without a campus of their
   own can still edit; `WeekTaskController::destroy()` keeps its three plain
   `where()`s so the row it reads is the row it rewrites).
2. **Writes go through `Campus::writeId()`**, so nothing new is stamped with a
   campus the church does not run.

## What this changes for the church

* The week list, members, first timers, announcements, departments, communities,
  sub-groups, weekly tasks, dashboards and charts are no longer narrowed by
  campus - rows whose `campus_id` is `0`, `NULL` or `2` are visible again instead
  of being silently hidden.
* The birthday digest (`php artisan birthdays:remind`) processes **one** campus.
  Campus 2 (Yaba) used to produce a "no recipients / skipped" line every day
  forever; it is no longer processed at all. `--campus=<id>` still forces a
  specific campus for support, and the campus id is still written into the
  notification and log rows.
* The campus selector still rendered by a few pages (member registration,
  profile, week list, children church, marketplace) is now decoration: the value
  posted is ignored (a write is stamped with `DEFAULT_CAMPUS_ID`). Hiding those
  selectors is a follow-up, not part of this change.

## Tests

* `tests/Feature/SingleCampusTest.php` - the lock-in: the switch is on, the macro
  is on both builders, a filtered query builds the *same* SQL as an unfiltered
  one, every campus is visible again, `writeId()` ignores a posted campus, the
  digest is not split over campuses and the command names only Ikorodu. The last
  test flips the switch off in-memory and proves the campus layer comes back.
* `tests/Feature/BirthdayReminderTest.php` - made mode-aware, so it holds in both
  modes.

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
  community map (`Api\MapController`).
* 15 write sites stamped through `Campus::writeId()`.
* `CheckProfileComplete` no longer demands a campus while single campus mode is
  on (it is stamped on writes, so it must not block anybody).
* Nothing in the database was migrated or deleted: the `campus` rows (1 Ikorodu,
  2 Yaba), every `campus_id` value and the global `CampusScope` are untouched.
