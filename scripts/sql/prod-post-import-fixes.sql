-- prod-post-import-fixes.sql
--
-- Non-additive repairs for a new production database that was rebuilt from the
-- production dump (docs/MIGRATION-smartchurch.md, section 6). Apply it AFTER
-- prod-reconciliation.sql, and before scripts/sql/verify-new-db.sql.
--
-- Why a second file: prod-reconciliation.sql is additive by design - it only
-- creates what is missing. The five problems below live in objects the dump
-- already has, so no amount of "create if missing" can reach them. Three of
-- them are also invisible locally, because a local XAMPP MariaDB runs without
-- STRICT_TRANS_TABLES while cPanel's runs with it.
--
-- Precondition: the database was built by importing the production dump, so
-- every table and column named here exists. Nothing here drops or renames a
-- column, and every statement is safe to run twice.
--
--   mysql -u <user> -p <database> < prod-post-import-fixes.sql
--
-- or paste it into phpMyAdmin's SQL tab with the new database selected.

-- ---------------------------------------------------------------------------
-- 1. airtime_history.status - the stored default is the text 'pending',
--    quotes included.
--
-- prod-reconciliation.sql created this column as NOT NULL DEFAULT '''pending'''
-- because MariaDB reports a string default already quoted ('pending') and the
-- generator wrapped it again. The five rows that already existed took that
-- default, so they read 'pending' with the quotes in the Credit History table
-- (/airtime-admin). MODIFY fixes the default for future inserts, the UPDATE
-- repairs the rows. AirtimeController always passes status => 'pending'
-- explicitly, so no new row is affected either way.
-- ---------------------------------------------------------------------------
ALTER TABLE `airtime_history`
  MODIFY COLUMN `status` varchar(50) NOT NULL DEFAULT 'pending';

UPDATE `airtime_history`
   SET `status` = 'pending'
 WHERE `status` = '''pending''';

-- ---------------------------------------------------------------------------
-- 2. caller_assignments - caller_name is narrower than the code, and the
--    first_timer_id key is not unique.
--
-- caller_name: the code schema allows 150 characters and NULL; the dump has
--   varchar(100) NOT NULL. saveCallerAssignment() writes
--   trim(first_name + ' ' + last_name), which a long name pushes past 100.
-- first_timer_id: WeekListController::saveCallerAssignment() writes the row
--   with updateOrInsert(['first_timer_id' => ...]), which is only race-safe
--   when the key is UNIQUE. The dump has a plain KEY. Guarded: the index is
--   added only when it is missing and no duplicate exists, so a database that
--   already has duplicates keeps loading (fix those rows, then run this again).
-- ---------------------------------------------------------------------------
ALTER TABLE `caller_assignments`
  MODIFY COLUMN `caller_name` varchar(150) NULL DEFAULT NULL;

SET @duplicates := (
  SELECT COUNT(*) FROM (
    SELECT `first_timer_id`
      FROM `caller_assignments`
     GROUP BY `first_timer_id`
    HAVING COUNT(*) > 1
  ) AS grouped
);

SET @sql := IF(
  @duplicates > 0
    OR EXISTS(SELECT 1 FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'caller_assignments'
                 AND INDEX_NAME = 'caller_assignments_first_timer_id_unique'),
  'DO 0',
  CONCAT('ALTER TABLE `caller_assignments` ',
         IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'caller_assignments'
                      AND INDEX_NAME = 'first_timer_id'),
            'DROP INDEX `first_timer_id`, ', ''),
         'ADD UNIQUE KEY `caller_assignments_first_timer_id_unique` (`first_timer_id`)')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 3. sub_group.department_name - NOT NULL with no default, and the new code
--    never writes it.
--
-- SubGroupController::create() inserts only department_id, sub_group_name and
-- campus_id. Under cPanel's strict sql_mode that is error 1364 ("Field
-- 'department_name' doesn't have a default value") and the AJAX endpoint
-- answers 500. The column is left in place - the legacy screens still read it -
-- and given an empty default so the insert succeeds.
-- ---------------------------------------------------------------------------
ALTER TABLE `sub_group`
  MODIFY COLUMN `department_name` varchar(100) NOT NULL DEFAULT '';

-- ---------------------------------------------------------------------------
-- 4. first_timers_updates.house_felloship - same shape as 3.
--
-- No current code path inserts into this table (the FirstTimerUpdate model is
-- never created through), so this is insurance rather than a live bug: the
-- column is NOT NULL without a default, and any future writer that does not
-- mention it would hit 1364 on the server and not locally.
-- ---------------------------------------------------------------------------
ALTER TABLE `first_timers_updates`
  MODIFY COLUMN `house_felloship` varchar(100) NOT NULL DEFAULT '';

-- ---------------------------------------------------------------------------
-- 5. airtime_history - the three indexes the code schema has and the dump
--    does not.
--
-- AirtimeController checks "already requested this week" with
-- user_id + year + week on every request, and /airtime-admin orders by
-- requested_at. Five rows today, so this is about the shape of the table rather
-- than speed today. Guarded on idx_year_week: all three were created by the
-- same migration, so testing one is enough.
-- ---------------------------------------------------------------------------
SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'airtime_history'
            AND INDEX_NAME = 'idx_year_week'),
  'DO 0',
  'ALTER TABLE `airtime_history` ADD INDEX `idx_user_id` (`user_id`), ADD INDEX `idx_year_week` (`year`, `week`), ADD INDEX `idx_status` (`status`)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 6. sub_group.department_id - the reconciliation added the column with
--    DEFAULT 0 and never filled it, so every read that filters by the id
--    (/group panels, the Touch Point select on /profile, the mobile subgroup
--    API) returned nothing on the live database.
--
-- The dump only names the department in the legacy department_name column.
-- Copy it across, accepting a leading "The" (the dump says "Switch", the
-- department table says "The Switch"). Safe to run twice: only rows still at
-- 0/NULL are touched. The department-less "Touch Point 11..18" rows stay at 0
-- on purpose - no member is assigned to them and no department owns them.
-- ---------------------------------------------------------------------------
UPDATE `sub_group` AS sg
  JOIN `department` AS d
    ON TRIM(LOWER(d.dept_name)) = TRIM(LOWER(sg.department_name))
    OR TRIM(LOWER(d.dept_name)) = CONCAT('the ', TRIM(LOWER(sg.department_name)))
   SET sg.department_id = d.dept_id
 WHERE (sg.department_id IS NULL OR sg.department_id = 0)
   AND TRIM(COALESCE(sg.department_name, '')) <> '';

-- The other direction, for rows the new code created (id only).
UPDATE `sub_group` AS sg
  JOIN `department` AS d ON d.dept_id = sg.department_id
   SET sg.department_name = d.dept_name
 WHERE (sg.department_name IS NULL OR sg.department_name = '')
   AND sg.department_id > 0;

-- ---------------------------------------------------------------------------
-- Not fixed here, on purpose
--
--   tiu_member.campus_id is NOT NULL in the dump. `php artisan app:sync-schema`
--   is what makes it nullable and empties it (migrations
--   2026_10_14_000002_make_campus_id_nullable and ..._000003_retire_the_campus_id_data),
--   and it is scheduled on the server. Doing it here as well would mean two
--   definitions of the same change, so this file stays out of it.
--
--   tiu_member.bus_stop_id keeps its 146 values. The column stays, its two
--   foreign keys to the long-gone transport_stops are dropped by app:sync-schema
--   (2026_05_25_000002) - they are dangling in the dump, which is why importing
--   it prints ERROR 1005 errno 150 for tiu_member. The import finishes anyway;
--   the constraint never gets created, which is the state the code wants.
-- ---------------------------------------------------------------------------
