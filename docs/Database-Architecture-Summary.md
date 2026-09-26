# Smart-Church Database Architecture Summary
**Generated:** April 28, 2026

---

## Overview

The Smart-Church database consists of **30 tables** organized into **7 functional modules**. The architecture follows a **campus-centric model** where most records are scoped to a specific campus (church location). The central entity is `tiu_member`, which serves as both the member profile and authentication system.

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
| 8 | `tiu_member_login` | `login_id` | Member login activity tracking |
| 9 | `birthday` | `bid` | Member birthday records |
| 10 | `church_member_contact` | `church_member_id` | Extended contact information |
| 11 | `policy` | `pid` | Church policy acknowledgment/signatures |

### TiuMember Relationships

```
TiuMember
 ├── belongsTo → Campus
 ├── belongsTo → Community
 ├── belongsTo → ChurchType
 ├── belongsTo → TransportStop (bus_stop_id)
 ├── hasOne  → Birthday
 ├── hasMany → BusAttendance (as rider)
 ├── hasMany → BusAttendance (as marker via marked_by)
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

## Module 4: Transport System

### Tables

| # | Table | Primary Key | Purpose |
|---|-------|-------------|---------|
| 19 | `transport_routes` | `route_id` | Bus routes with driver info |
| 20 | `transport_stops` | `stop_id` | Individual bus stops on routes |
| 21 | `bus_attendance` | `attendance_id` | Member bus check-in records |

### Relationship Chain

```
Campus
 └── hasMany → TransportRoute
      ├── hasMany → TransportStop
      └── hasMany → BusAttendance

BusAttendance
 ├── belongsTo → TiuMember (rider)
 ├── belongsTo → TiuMember (marked_by)
 ├── belongsTo → TransportRoute
 └── belongsTo → TransportStop
```

---

## Module 5: Foundation of Faith (FOF) Program

### Tables

| # | Table | Primary Key | Purpose |
|---|-------|-------------|---------|
| 22 | `fof_cohort_setting` | `cohort_id` | Cohort definitions (A, B, C groups) |
| 23 | `fof_register_table` | `reg_id` | Student registrations |
| 24 | `fof_mark_attendance_table` | `id` | Weekly attendance records |

### Relationship Chain

```
Campus
 ├── hasMany → FofCohortSetting
 ├── hasMany → FofRegister
 │    └── hasMany → FofMarkAttendance
 └── hasMany → FofMarkAttendance
```

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
                          ┌─────────────────────────────────────────────────────────────────────────────┐
                          │                                    CAMPUS                                   │
                          └──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──────────┘
            ┌────────────────┘  │  │  │  │  │  │  │  │  │  │  │  │  │  │  │  │  │  │  │  │  │
            ▼                   ▼  ▼  ▼  ▼  ▼  ▼  ▼  ▼  ▼  ▼  ▼  ▼  ▼  ▼  ▼  ▼  ▼  ▼  ▼  ▼  ▼
      ChurchType         Communities Departments SubGroups TiuMembers TransportRoutes FofCohortSettings
         │                    │          │                      │           │                │
         ▼                    ▼          ▼                      ▼           ▼                ▼
   FirstTimer            Department  BusAttendance         BusAttendance TransportStops  FofRegister
         │                              │                      │                           │
         ▼                              ▼                      ▼                           ▼
   FirstTimerHangout                  FofRegister            TiuMemberLogin            FofMarkAttendance
   MemberTrackingFollowup             MarketplaceBusiness    Policy                     
   ChurchAttendance                   SharedResource         AirtimeHistory             
                                      ResourceShare          Birthday                   
                                                             ChurchMemberContact        
```

---

## Model Summary

### Existing Models (Updated with Full Relationships)
```
1.  Campus.php          - 12 relationships + fillable
2.  ChurchType.php      -  4 relationships + fillable
3.  Community.php       -  4 relationships + fillable
4.  Department.php      -  3 relationships + fillable
5.  SubGroup.php        -  2 relationships + fillable
6.  Occupation.php      -  1 relationship  + fillable
7.  TiuMember.php       - 18 relationships + fillable + JSON accessors/mutators
8.  Birthday.php        -  1 relationship  + fillable
9.  FirstTimer.php      -  4 relationships + fillable + scopes
10. FirstTimersUpdate   -  1 relationship  + fillable
11. MemberTrackingFollowup - 2 relationships + fillable + casts
```

### New Models Created (16 total)
```
12. TransportRoute.php      -  3 relationships + fillable
13. TransportStop.php       -  3 relationships + fillable
14. BusAttendance.php       -  4 relationships + fillable
15. Announcement.php        -  1 relationship  + fillable
16. ChurchAttendance.php    -  1 relationship  + fillable
17. FofCohortSetting.php    -  1 relationship  + fillable
18. FofRegister.php         -  2 relationships + fillable
19. FofMarkAttendance.php   -  2 relationships + fillable
20. MarketplaceCategory.php  -  1 relationship  + fillable
21. MarketplaceBusiness.php  -  2 relationships + fillable
22. SharedResource.php      -  2 relationships + fillable
23. ResourceShare.php       -  2 relationships + fillable
24. PhotoGallery.php        -  0 relationships + fillable (standalone)
25. TiuMemberLogin.php      -  1 relationship  + fillable
26. ChurchMemberContact.php -  0 relationships + fillable (standalone)
27. Policy.php              -  1 relationship  + fillable
28. FirstTimerHangout.php   -  1 relationship  + fillable
29. FirstTimerTrash.php     -  0 relationships + fillable (standalone)
30. FirstTimeViewLimit.php  -  1 relationship  + fillable
31. Task.php                -  0 relationships + fillable (standalone)
32. AirtimeHistory.php      -  1 relationship  + fillable
```

### Key Design Decisions

1. **Mass Assignment Protection**: All models use `$fillable` arrays listing every column from their respective migrations.

2. **Timestamps**: All models now set `public $timestamps = true;` to take advantage of Laravel's `created_at`/`updated_at` auto-management.

3. **Primary Keys**: Custom primary keys are used for legacy compatibility (e.g., `cid`, `dept_id`, `route_id`, etc.).

4. **JSON Casting**: `TiuMember` uses custom accessors/mutators for JSON fields (`department_name`, `cluster`, `house_fellowship`, `oversight_extra1`). `MemberTrackingFollowup` uses `$casts` for `followup_response_new`.

5. **Campus Centrality**: Nearly every major entity scopes by `campus_id`, making multi-campus church management straightforward.

6. **Legacy Compatibility**: Tables like `first_timer` (singular) and `first_timers` (plural) coexist for backwards compatibility with the TIU legacy system.
