-- verify-new-db.sql
--
-- The checks the migration runbook (docs/MIGRATION-smartchurch.md, section 10)
-- asks for after the new database has been imported and reconciled. Every query
-- is read-only and safe to run on production.
--
-- Part 1 counts rows: run the generated statement against the OLD database and
-- then against the NEW one and compare the two outputs. Part 2 onward is
-- schema-level and can be run on the new database alone.
--
-- phpMyAdmin: run one block at a time. Each block is its own statement.

-- ---------------------------------------------------------------------------
-- 1. Exact per-table row counts.
--
-- information_schema.TABLES.TABLE_ROWS is an InnoDB estimate, so it is not good
-- enough to prove a copy. This prints a counting script for every table; copy
-- the result, run it on the OLD database, then on the NEW one, and compare.
-- ---------------------------------------------------------------------------
SET SESSION group_concat_max_len = 16777215;

SELECT GROUP_CONCAT(
         CONCAT('SELECT ''', TABLE_NAME, ''' AS tbl, COUNT(*) AS rows_now FROM `',
                TABLE_SCHEMA, '`.`', TABLE_NAME, '`')
         ORDER BY TABLE_NAME
         SEPARATOR ' UNION ALL '
       ) AS counting_script
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE';

-- ---------------------------------------------------------------------------
-- 2. Every table the code's schema has, plus `messages`. state must be
--    'present' for all of them.
--
--    The list is the local development database's TABLE_NAME list - that is
--    what database/migrations produces, and therefore what the code reads. The
--    reconciliation (scripts/build-prod-reconciliation.ps1) is what creates the
--    ones the dump is missing; `messages` is the exception it cannot know about,
--    because no migration creates it - it arrives with the dump and
--    AdminFirstTimerController inserts into it.
-- ---------------------------------------------------------------------------
SELECT needed AS table_name,
       CASE WHEN t.TABLE_NAME IS NULL THEN 'MISSING' ELSE 'present' END AS state
FROM (
  SELECT 'airtime_history' AS needed
  UNION ALL SELECT 'announcements'
  UNION ALL SELECT 'app_notifications'
  UNION ALL SELECT 'birthday'
  UNION ALL SELECT 'birthday_reminder_logs'
  UNION ALL SELECT 'caller_assignments'
  UNION ALL SELECT 'campus'
  UNION ALL SELECT 'children_church'
  UNION ALL SELECT 'children_church_attendance'
  UNION ALL SELECT 'child_ceremonies'
  UNION ALL SELECT 'church_attendance'
  UNION ALL SELECT 'church_member_contact'
  UNION ALL SELECT 'church_type'
  UNION ALL SELECT 'communities'
  UNION ALL SELECT 'department'
  UNION ALL SELECT 'failed_jobs'
  UNION ALL SELECT 'first_timer'
  UNION ALL SELECT 'first_timers_updates'
  UNION ALL SELECT 'first_timer_hangout'
  UNION ALL SELECT 'first_timer_trash'
  UNION ALL SELECT 'first_time_view_limit'
  UNION ALL SELECT 'invite_table'
  UNION ALL SELECT 'marketplace_businesses'
  UNION ALL SELECT 'marketplace_categories'
  UNION ALL SELECT 'member_tracking_followup'
  UNION ALL SELECT 'member_tracking_followup_archive'
  UNION ALL SELECT 'messages'
  UNION ALL SELECT 'migrations'
  UNION ALL SELECT 'notice board'
  UNION ALL SELECT 'occupation'
  UNION ALL SELECT 'password_resets'
  UNION ALL SELECT 'personal_access_tokens'
  UNION ALL SELECT 'photo_gallery'
  UNION ALL SELECT 'policy'
  UNION ALL SELECT 'prayer_suggestions'
  UNION ALL SELECT 'resource_shares'
  UNION ALL SELECT 'shared_resources'
  UNION ALL SELECT 'sub_group'
  UNION ALL SELECT 'task'
  UNION ALL SELECT 'tiu_member'
  UNION ALL SELECT 'tiu_member_login'
  UNION ALL SELECT 'users'
) AS n
LEFT JOIN information_schema.TABLES t
       ON t.TABLE_SCHEMA = DATABASE()
      AND t.TABLE_NAME = n.needed
ORDER BY state = 'present', needed;

-- ---------------------------------------------------------------------------
-- 3. The columns the reconciliation has to add - the code schema's columns that
--    the dump does not have. state must be 'present' for every row.
--
--    task.department_id is the one rename: the dump keeps the same answers under
--    the old name `task.extra`, and the generated SQL moves them rather than
--    adding an empty column next to them.
-- ---------------------------------------------------------------------------
SELECT wanted_table AS table_name, wanted_column AS column_name,
       CASE WHEN c.COLUMN_NAME IS NULL THEN 'MISSING' ELSE 'present' END AS state,
       c.COLUMN_TYPE, c.IS_NULLABLE
FROM (
  SELECT 'airtime_history' AS wanted_table, 'created_at' AS wanted_column
  UNION ALL SELECT 'airtime_history', 'status'
  UNION ALL SELECT 'airtime_history', 'updated_at'
  UNION ALL SELECT 'airtime_history', 'user_name'
  UNION ALL SELECT 'airtime_history', 'user_phone'
  UNION ALL SELECT 'announcements', 'campus_id'
  UNION ALL SELECT 'caller_assignments', 'caller_member_id'
  UNION ALL SELECT 'caller_assignments', 'created_at'
  UNION ALL SELECT 'caller_assignments', 'updated_at'
  UNION ALL SELECT 'church_attendance', 'created_at'
  UNION ALL SELECT 'church_attendance', 'updated_at'
  UNION ALL SELECT 'communities', 'campus_id'
  UNION ALL SELECT 'department', 'campus_id'
  UNION ALL SELECT 'first_timer', 'campus_id'
  UNION ALL SELECT 'first_timer', 'unassign_reason'
  UNION ALL SELECT 'first_timers_updates', 'house_fellowship'
  UNION ALL SELECT 'first_time_view_limit', 'campus_id'
  UNION ALL SELECT 'member_tracking_followup', 'department_id'
  UNION ALL SELECT 'sub_group', 'campus_id'
  UNION ALL SELECT 'sub_group', 'department_id'
  UNION ALL SELECT 'task', 'campus_id'
  UNION ALL SELECT 'task', 'department_id'
) AS w
LEFT JOIN information_schema.COLUMNS c
       ON c.TABLE_SCHEMA = DATABASE()
      AND c.TABLE_NAME = w.wanted_table
      AND c.COLUMN_NAME = w.wanted_column
ORDER BY state, table_name, column_name;

-- ---------------------------------------------------------------------------
-- 4. Single campus mode: every campus_id column must be NULLABLE, otherwise a
--    row written without a campus is rejected instead of simply looking like
--    another campus (docs/SINGLE-CAMPUS.md). Expect 'YES' for every row.
--
--    tiu_member.campus_id is the interesting one: the dump has it NOT NULL and
--    it is not a column the reconciliation adds, so it only becomes nullable once
--    `php artisan app:sync-schema` has applied 2026_10_14_000002_make_campus_id_nullable.
--    A 'NO' here therefore means that command has not run yet, not that the
--    import was wrong.
-- ---------------------------------------------------------------------------
SELECT TABLE_NAME, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME = 'campus_id'
ORDER BY IS_NULLABLE DESC, TABLE_NAME;

-- ---------------------------------------------------------------------------
-- 5. Tables that came with the dump and are not in the code's schema. This is
--    the list to look at before anything is ever dropped; the migration drops
--    none of them (docs/MIGRATION-smartchurch.md section 9).
-- ---------------------------------------------------------------------------
SELECT TABLE_NAME,
       CASE
         WHEN TABLE_NAME = 'messages' THEN 'LIVE - AdminFirstTimerController inserts here'
         ELSE 'orphan - the code reads nothing here: export before any drop'
       END AS note
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('messages', 'call_center', 'call_tree_assignments',
                     'call_tree_feedback', 'first_timer_gallery', 'gift_logs',
                     'member_telegram', 'whatapp')
ORDER BY TABLE_NAME;

-- The six tables `php artisan app:sync-schema` retires (Transport, FOF). Every
-- row must read 'gone'; 'STILL PRESENT' means that command failed or has not run.
SELECT wanted AS table_name,
       CASE WHEN t.TABLE_NAME IS NULL THEN 'gone' ELSE 'STILL PRESENT' END AS state
FROM (
  SELECT 'transport_routes' AS wanted
  UNION ALL SELECT 'transport_stops'
  UNION ALL SELECT 'bus_attendance'
  UNION ALL SELECT 'fof_cohort_setting'
  UNION ALL SELECT 'fof_register_table'
  UNION ALL SELECT 'fof_mark_attendance_table'
) AS w
LEFT JOIN information_schema.TABLES t
       ON t.TABLE_SCHEMA = DATABASE()
      AND t.TABLE_NAME = w.wanted
ORDER BY state, table_name;

-- ---------------------------------------------------------------------------
-- 6. Migration state. The dump has no `migrations` table at all, which is why a
--    plain `php artisan migrate` cannot be used on it; the reconciliation creates
--    the (empty) table and `php artisan app:sync-schema` is what records the
--    files below. Every row must read 'recorded' once it has run.
-- ---------------------------------------------------------------------------
SELECT wanted AS migration,
       CASE WHEN m.migration IS NULL THEN 'PENDING' ELSE 'recorded' END AS state
FROM (
  SELECT '2026_10_09_000001_create_birthday_reminder_logs_table' AS wanted
  UNION ALL SELECT '2026_10_09_000002_create_app_notifications_table'
  UNION ALL SELECT '2026_10_14_000001_create_member_tracking_followup_archive_table'
  UNION ALL SELECT '2026_05_25_000002_drop_transport_foreign_keys_from_tiu_member_table'
  UNION ALL SELECT '2026_05_25_000003_drop_transport_tables'
  UNION ALL SELECT '2026_05_25_000004_drop_fof_tables'
  UNION ALL SELECT '2026_10_14_000002_make_campus_id_nullable'
  UNION ALL SELECT '2026_10_14_000003_retire_the_campus_id_data'
) AS w
LEFT JOIN migrations m ON m.migration = w.wanted
ORDER BY state, migration;

-- ---------------------------------------------------------------------------
-- 7. Mobile app tokens. They only survive the move while API_TOKEN_KEY is
--    byte-identical to the old value: the credential is a signed HS256 token
--    whose hash lives here, and nothing validates the `iss` claim, so every
--    outstanding phone keeps working (docs/API-TOKENS.md).
--
--    A count of 0 on a database imported from production means the
--    personal_access_tokens rows did not come across. A wrong key does not show
--    up here at all: it shows up as a 401 after the switch.
-- ---------------------------------------------------------------------------
SELECT COUNT(*) AS outstanding_tokens,
       MIN(created_at) AS oldest_issued,
       MAX(last_used_at) AS last_used
FROM personal_access_tokens;

