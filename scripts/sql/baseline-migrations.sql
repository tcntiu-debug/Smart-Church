-- baseline-migrations.sql
--
-- Record the migration history this database already has, so a plain
-- `php artisan migrate` stops trying to replay 2014's create-table files against
-- tables that came from the legacy production dump.
--
-- Symptom it fixes (cPanel Terminal, after app:post-deploy --migrate):
--
--   FAILED: migrate - SQLSTATE[42S01]: Base table or view already exists: 1050
--   Table 'users' already exists (SQL: create table `users` ...)
--
-- Why: production never had a `migrations` table (docs/PROD-DB-DIFF.md section 4).
-- The first `migrate` run creates an empty one ("Migration table created
-- successfully.") and then starts at 2014_10_12_000000_create_users_table, whose
-- table - with every other legacy table - is already there.
--
-- This file only writes `migrations`. It never touches a business table: every
-- name below is a migration whose effect this database already carries (created
-- by the dump, by app:sync-schema, by birthdays:remind, or by hand in
-- phpMyAdmin), which is exactly what a schema that predates the `migrations` table
-- means. The `WHERE NOT EXISTS` guard makes it safe to paste twice, and a
-- migration added later is not in this list, so it still runs the normal way.
--
-- Apply it once per database:
--
--   mysql -u <user> -p <database> < baseline-migrations.sql
--
-- or paste it into phpMyAdmin's SQL tab with the database selected.
-- Then, in cPanel Terminal:
--
--   php artisan migrate:status     # every row "Ran"
--   php artisan migrate --force    # "Nothing to migrate."
--
-- The equivalent one-liner, if you would rather not open phpMyAdmin (tinker is a
-- production dependency, so it is on the server):
--
--   php artisan tinker --execute='foreach (glob(base_path("database/migrations/*.php")) as $file) { $name = basename($file, ".php"); if (! DB::table("migrations")->where("migration", $name)->exists()) { DB::table("migrations")->insert(["migration" => $name, "batch" => 1]); } } echo DB::table("migrations")->count()." migrations recorded".PHP_EOL;'

-- Laravel 8's own definition, in case this run is the first thing to touch it.
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT `v`.`migration`, 1 AS `batch`
FROM (
  SELECT '2014_10_12_000000_create_users_table' AS migration
  UNION ALL SELECT '2014_10_12_100000_create_password_resets_table'
  UNION ALL SELECT '2019_08_19_000000_create_failed_jobs_table'
  UNION ALL SELECT '2019_12_14_000001_create_personal_access_tokens_table'
  UNION ALL SELECT '2026_01_01_000001_create_first_timers_table'
  UNION ALL SELECT '2026_01_01_000002_create_member_tracking_followup_table'
  UNION ALL SELECT '2026_01_01_000003_create_first_timers_updates_table'
  UNION ALL SELECT '2026_01_01_000004_create_departments_table'
  UNION ALL SELECT '2026_01_01_000005_create_communities_table'
  UNION ALL SELECT '2026_04_28_000001_create_campus_table'
  UNION ALL SELECT '2026_04_28_000002_create_church_type_table'
  UNION ALL SELECT '2026_04_28_000003_create_occupation_table'
  UNION ALL SELECT '2026_04_28_000004_create_sub_group_table'
  UNION ALL SELECT '2026_04_28_000005_create_tiu_member_table'
  UNION ALL SELECT '2026_04_28_000006_create_department_table'
  UNION ALL SELECT '2026_04_28_000008_add_missing_fields_to_communities_table'
  UNION ALL SELECT '2026_04_28_000009_create_birthday_table'
  UNION ALL SELECT '2026_04_28_000010_create_first_timer_legacy_table'
  UNION ALL SELECT '2026_04_28_000013_create_announcements_table'
  UNION ALL SELECT '2026_04_28_000014_create_church_attendance_table'
  UNION ALL SELECT '2026_04_28_000018_create_marketplace_categories_table'
  UNION ALL SELECT '2026_04_28_000019_create_marketplace_businesses_table'
  UNION ALL SELECT '2026_04_28_000020_create_shared_resources_table'
  UNION ALL SELECT '2026_04_28_000021_create_resource_shares_table'
  UNION ALL SELECT '2026_04_28_000022_create_photo_gallery_table'
  UNION ALL SELECT '2026_04_28_000023_create_tiu_member_login_table'
  UNION ALL SELECT '2026_04_28_000024_create_church_member_contact_table'
  UNION ALL SELECT '2026_04_28_000025_create_policy_table'
  UNION ALL SELECT '2026_04_28_000026_create_first_timer_hangout_table'
  UNION ALL SELECT '2026_04_28_000027_create_first_timer_trash_table'
  UNION ALL SELECT '2026_04_28_000028_create_first_time_view_limit_table'
  UNION ALL SELECT '2026_04_28_000029_create_task_table'
  UNION ALL SELECT '2026_04_28_000030_create_airtime_history_table'
  UNION ALL SELECT '2026_05_08_000001_create_prayer_suggestions_table'
  UNION ALL SELECT '2026_05_13_000001_create_children_church_table'
  UNION ALL SELECT '2026_05_13_000002_create_children_church_attendance_table'
  UNION ALL SELECT '2026_05_13_000003_create_child_ceremonies_table'
  UNION ALL SELECT '2026_05_13_000004_add_status_to_child_ceremonies_table'
  UNION ALL SELECT '2026_05_13_000005_remove_unused_columns_from_child_ceremonies'
  UNION ALL SELECT '2026_05_13_000006_add_campus_id_to_child_ceremonies'
  UNION ALL SELECT '2026_05_16_000001_add_unassign_reason_to_first_timer_table'
  UNION ALL SELECT '2026_05_16_220621_add_timestamps_to_church_attendance_table'
  UNION ALL SELECT '2026_05_22_162728_add_campus_id_to_task_table'
  UNION ALL SELECT '2026_05_24_123348_rename_extra_to_department_id_in_task_table'
  UNION ALL SELECT '2026_05_24_205010_add_department_id_to_member_tracking_followup_table'
  UNION ALL SELECT '2026_05_25_000001_create_caller_assignments_table'
  UNION ALL SELECT '2026_05_25_000002_drop_transport_foreign_keys_from_tiu_member_table'
  UNION ALL SELECT '2026_05_25_000003_drop_transport_tables'
  UNION ALL SELECT '2026_05_25_000004_drop_fof_tables'
  UNION ALL SELECT '2026_10_09_000001_create_birthday_reminder_logs_table'
  UNION ALL SELECT '2026_10_09_000002_create_app_notifications_table'
  UNION ALL SELECT '2026_10_14_000001_create_member_tracking_followup_archive_table'
  UNION ALL SELECT '2026_10_14_000002_make_campus_id_nullable'
  UNION ALL SELECT '2026_10_14_000003_retire_the_campus_id_data'
  UNION ALL SELECT '2026_10_18_000001_add_remember_token_to_tiu_member_table'
  UNION ALL SELECT '2026_10_20_000001_add_unique_user_week_to_airtime_history'
) AS `v`
WHERE NOT EXISTS (SELECT 1 FROM `migrations` AS `m` WHERE `m`.`migration` = `v`.`migration`);

-- Expect 56 (every file in database/migrations at the time of writing).
SELECT COUNT(*) AS recorded FROM `migrations`;
SELECT `migration` FROM `migrations` ORDER BY `migration` DESC LIMIT 3;
