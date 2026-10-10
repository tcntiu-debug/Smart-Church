# Smart-Church Database Architecture Summary
**Generated:** April 28, 2026

---

## Overview

The Smart-Church database consists of **24 tables** organized into **6 functional modules**. (The former Transport module - `transport_routes`, `transport_stops`, `bus_attendance` - was retired on 2026-05-25, and the Admin FOF program - `fof_cohort_setting`, `fof_register_table`, `fof_mark_attendance_table` - was retired on 2026-05-25.) The architecture follows a **campus-centric model** where most records are scoped to a specific campus (church location). The central entity is `tiu_member`, which serves as both the member profile and authentication system.

---

## Module 1: Core Church Structure

### Tables

| # | Table | Primary Key | Purpose |
|---|-------|-------------|---------|
| 1 | `campus` | `cid` | Church campus/location (e.g., Main, Youth Church) |
| 2 | `church_type` | `id` | Service types (Main Service, Youth, Children) |
| 3 | `communities` | `id` | Geographical communities within a campus |
| 4 | `department` | `dept_id` | Departments, Clusters, House Fellowships |
| 5 | `sub_group` | `subid` | Smaller groups within departments |
| 6 | `occupation` | `occ_id` | Member occupation reference list |

### Relationship Chain

```
Campus
 ├── hasMany → ChurchType (service types)
 ├── hasMany → Community
 │    └── hasMany → Department
 ├── hasMany → Department (direct)
 ├── hasMany → SubGroup
 └── hasMany → TiuMember
```

---

## Module 2: Member Management

### Tables

| # | Table | Primary Key | Purpose |
|---|-------|-------------|---------|
| 7 | `tiu_member` | `tiu_member_id` | Core member profiles & authentication |
| 8 | `tiu_member_login` | `login_id` | Member login activity tracking (its `theme_settings` column is legacy/unused — the dark/light display mode is a per-device `tiu_theme` cookie, see `docs/DISPLAY-MODE.md`) |
| 9 | `birthday` | `bid` | Member birthday records |
| 10 | `church_member_contact` | `church_member_id` | Extended contact information |
| 11 | `policy` | `pid` | Church policy acknowledgment/signatures |

### TiuMember Relationships

```
TiuMember
 ├── belongsTo → Campus
 ├── belongsTo → Community
 ├── belongsTo → ChurchType
 ├── hasOne  → Birthday
 ├── hasMany → TiuMemberLogin
 ├── hasMany → Policy
 ├── hasMany → MarketplaceBusiness (as owner)
 ├── hasMany → SharedResource (as uploader)
 ├── hasMany → ResourceShare (as recipient)
 ├── hasMany → AirtimeHistory
 ├── hasMany → Department (as lead via dept_lead_id)
 ├── hasMany → SubGroup (as lead via lead_id)
 ├── hasMany → ChurchType (as lead via church_type_lead_id)
 └── hasMany → MemberTrackingFollowup
```

---

## Module 3: First-Timer Management

### Tables

| # | Table | Primary Key | Purpose |
|---|-------|-------------|---------|
| 12 | `first_timers` (legacy) | `first_timer_id` | Original first-timer records |
| 13 | `first_timer` (legacy) | `first_timer_id` | TIU legacy first-timer records |
| 14 | `first_timers_updates` | `id` | Status updates for first-timers |
| 15 | `first_timer_hangout` | `h_id` | Hangout event registrations |
| 16 | `first_timer_trash` | `trash_id` | Soft-deleted/trashed records |
| 17 | `first_time_view_limit` | `id` | Configurable view limits per campus |
| 18 | `member_tracking_followup` | `tracking_id` | Member-to-first-timer followup tracking |

### Relationship Chain

```
FirstTimer (first_timer)
 ├── belongsTo → Campus
 ├── belongsTo → Community
 ├── belongsTo → ChurchType
 ├── hasOne  → FirstTimersUpdate
 ├── hasMany → FirstTimerHangout
 └── hasMany → MemberTrackingFollowup

FirstTimer (first_timers)
 └── hasMany → MemberTrackingFollowup
```

---

## Module 4: Transport System - RETIRED

The transport (bus route) feature was fully removed on 2026-05-25. The three
tables below were dropped, and their models, controllers, routes and views deleted.

| Table | Primary Key | Status |
|-------|-------------|--------|
| `transport_routes` | `route_id` | dropped |
| `transport_stops` | `stop_id` | dropped |
| `bus_attendance` | `attendance_id` | dropped |

Removed models: `TransportRoute`, `TransportStop`, `BusAttendance`.

> Local and server schemas are kept in step by the drop-migrations
> (`2026_05_25_000002`–`000004`), which the deploy applies with
> `php artisan app:retire-legacy` — the SFTP pipeline cannot run artisan itself.
> See `docs/CPANEL-DEPLOYMENT.md` → *Retired modules*.

---

## Module 5: Foundation of Faith (FOF) Program - RETIRED

The standalone Admin FOF program was fully removed on 2026-05-25. The three
tables below were dropped, and their models, controllers, routes, views and
navigation entries deleted. FOF remains a valid *department* on `tiu_member`;
only the programme's own tables were removed.

| Table | Primary Key | Status |
|-------|-------------|--------|
| `fof_cohort_setting` | `cohort_id` | dropped |
| `fof_register_table` | `reg_id` | dropped |
| `fof_mark_attendance_table` | `id` | dropped |

Removed models: `FofCohortSetting`, `FofRegister`, `FofMarkAttendance`.

> Local and server schemas are kept in step by the drop-migrations
> (`2026_05_25_000002`–`000004`), which the deploy applies with
> `php artisan app:retire-legacy` — the SFTP pipeline cannot run artisan itself.
> See `docs/CPANEL-DEPLOYMENT.md` → *Retired modules*.

---

## Module 6: Church Marketplace

### Tables

| # | Table | Primary Key | Purpose |
|---|-------|-------------|---------|
| 25 | `marketplace_categories` | `category_id` | Business categories (Food, Tech, etc.) |
| 26 | `marketplace_businesses` | `business_id` | Member-listed businesses |

### Relationship Chain

```
MarketplaceCategory
 └── hasMany → MarketplaceBusiness
      └── belongsTo → TiuMember (owner)
```

---

## Module 7: Resources & Communications

### Tables

| # | Table | Primary Key | Purpose |
|---|-------|-------------|---------|
| 27 | `announcements` | `id` | Campus announcements |
| 28 | `shared_resources` | `id` | Uploaded resource files |
| 29 | `resource_shares` | `id` | Resource sharing tracking |
| 30 | `photo_gallery` | `id` | Church photo gallery |
| 31 | `airtime_history` | `id` | Airtime recharge requests |
| 32 | `task` | `tid` | Weekly task/assignment questions |

---

## Entity Relationship Diagram (Text)

```
                          +-----------------------------------------------------------+
                          |                          CAMPUS                           |
                          +--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+--+------+
                             |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |  |
                             v  v  v  v  v  v  v  v  v  v  v  v  v  v  v  v  v  v
      ChurchType    Communities Departments SubGroups TiuMembers
         |               |           |          |          |
         v               v           v          v          v
   FirstTimer      Department  MarketplaceBusiness TiuMemberLogin
   FirstTimerHangout          SharedResource               Policy
   MemberTrackingFollowup     ResourceShare                Birthday
   ChurchAttendance                                        AirtimeHistory
                                                           ChurchMemberContact
```

---

## Model Summary

### Existing Models (Updated with Full Relationships)
```
1.  Campus.php          -  7 relationships + fillable
2.  ChurchType.php      -  4 relationships + fillable
3.  Community.php       -  4 relationships + fillable
4.  Department.php      -  3 relationships + fillable
5.  SubGroup.php        -  2 relationships + fillable
6.  Occupation.php      -  1 relationship  + fillable
7.  TiuMember.php       - 15 relationships + fillable + JSON accessors/mutators
8.  Birthday.php        -  1 relationship  + fillable
9.  FirstTimer.php      -  4 relationships + fillable + scopes
10. FirstTimersUpdate   -  1 relationship  + fillable
11. MemberTrackingFollowup - 2 relationships + fillable + casts
```

### New Models Created (15 total)
```
12. Announcement.php        -  1 relationship  + fillable
13. ChurchAttendance.php    -  1 relationship  + fillable
14. MarketplaceCategory.php  -  1 relationship  + fillable
15. MarketplaceBusiness.php  -  2 relationships + fillable
16. SharedResource.php      -  2 relationships + fillable
17. ResourceShare.php       -  2 relationships + fillable
18. PhotoGallery.php        -  0 relationships + fillable (standalone)
19. TiuMemberLogin.php      -  1 relationship  + fillable
20. ChurchMemberContact.php -  0 relationships + fillable (standalone)
21. Policy.php              -  1 relationship  + fillable
22. FirstTimerHangout.php   -  1 relationship  + fillable
23. FirstTimerTrash.php     -  0 relationships + fillable (standalone)
24. FirstTimeViewLimit.php  -  1 relationship  + fillable
25. Task.php                -  0 relationships + fillable (standalone)
26. AirtimeHistory.php      -  1 relationship  + fillable
```

### Key Design Decisions

1. **Mass Assignment Protection**: All models use `$fillable` arrays listing every column from their respective migrations.

2. **Timestamps**: All models now set `public $timestamps = true;` to take advantage of Laravel's `created_at`/`updated_at` auto-management.

3. **Primary Keys**: Custom primary keys are used for legacy compatibility (e.g., `cid`, `dept_id`, `route_id`, etc.).

4. **JSON Casting**: `TiuMember` uses custom accessors/mutators for JSON fields (`department_name`, `cluster`, `house_fellowship`, `oversight_extra1`). `MemberTrackingFollowup` uses `$casts` for `followup_response_new`.

5. **Campus Centrality**: Nearly every major entity scopes by `campus_id`, making multi-campus church management straightforward.

6. **Legacy Compatibility**: Tables like `first_timer` (singular) and `first_timers` (plural) coexist for backwards compatibility with the TIU legacy system.
