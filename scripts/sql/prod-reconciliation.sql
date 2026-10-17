-- prod-reconciliation.sql
--
-- Generated 2026-10-16 12:45 by scripts/build-prod-reconciliation.ps1
--   code schema (source)   : tcnikoro_smart_church
--   production model       : sc_prod_check (an import of the production dump)
--
-- Additive only: this file never drops a table or a column. Every statement is
-- wrapped in an information_schema guard, so running it again changes nothing.
-- Apply it to the new production database with phpMyAdmin (SQL tab) or:
--   mysql -u <user> -p <database> -e "SOURCE C:/path/to/prod-reconciliation.sql"
-- then run `php artisan app:sync-schema` and scripts/sql/verify-new-db.sql.
--
-- See docs/MIGRATION-smartchurch.md section 6 before you apply it.

-- ===========================================================================
-- 1. Tables the code reads that the dump does not have.
--    CREATE TABLE comes from the code schema, so indexes and constraints match.
--    Any foreign key in these blocks needs its referenced table to exist
--    first; if one fails, create the referenced table and run this file again.
-- ===========================================================================

-- CREATE TABLE `app_notifications` (missing from the dump)
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_notifications'),
  'DO 0',
  'CREATE TABLE `app_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tiu_member_id` int(10) unsigned NOT NULL COMMENT ''Recipient (tiu_member.tiu_member_id)'',
  `campus_id` int(10) unsigned DEFAULT NULL,
  `type` varchar(60) NOT NULL DEFAULT ''birthday_reminder'',
  `title` varchar(191) NOT NULL,
  `body` text DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT ''Structured payload (member ids, dates, ...)'' CHECK (json_valid(`data`)),
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `app_notifications_unread_index` (`tiu_member_id`,`read_at`),
  KEY `app_notifications_tiu_member_id_index` (`tiu_member_id`),
  KEY `app_notifications_campus_id_index` (`campus_id`)
) ENGINE=InnoDB AUTO_INCREMENT=565 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- CREATE TABLE `birthday_reminder_logs` (missing from the dump)
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'birthday_reminder_logs'),
  'DO 0',
  'CREATE TABLE `birthday_reminder_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `campus_id` int(10) unsigned DEFAULT NULL,
  `days_before` tinyint(3) unsigned NOT NULL COMMENT ''3 = three days to go, 0 = today'',
  `reminder_date` date NOT NULL COMMENT ''The birthday date being reminded about'',
  `sent_on` date NOT NULL COMMENT ''Local date the digest went out'',
  `birthday_count` int(10) unsigned NOT NULL DEFAULT 0,
  `recipient_count` int(10) unsigned NOT NULL DEFAULT 0,
  `channels` varchar(191) NOT NULL COMMENT ''Channels actually delivered to'',
  `status` varchar(20) NOT NULL DEFAULT ''sent'' COMMENT ''sent | failed'',
  `message` text DEFAULT NULL COMMENT ''Failure reason / delivery notes'',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `birthday_reminder_unique` (`campus_id`,`days_before`,`reminder_date`),
  KEY `birthday_reminder_logs_campus_id_index` (`campus_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- CREATE TABLE `campus` (missing from the dump)
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'campus'),
  'DO 0',
  'CREATE TABLE `campus` (
  `cid` int(100) NOT NULL AUTO_INCREMENT,
  `cname` varchar(100) NOT NULL,
  PRIMARY KEY (`cid`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- CREATE TABLE `children_church` (missing from the dump)
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'children_church'),
  'DO 0',
  'CREATE TABLE `children_church` (
  `child_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_name` varchar(255) DEFAULT NULL COMMENT ''Parent/Guardian Name'',
  `child_name` varchar(255) NOT NULL COMMENT ''Child Name'',
  `dob` varchar(100) DEFAULT NULL COMMENT ''Date of Birth'',
  `parent_phone` varchar(255) DEFAULT NULL COMMENT ''Parent/Guardian Phone Number'',
  `campus_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`child_id`),
  KEY `children_church_child_name_index` (`child_name`),
  KEY `children_church_campus_id_index` (`campus_id`),
  CONSTRAINT `children_church_campus_id_foreign` FOREIGN KEY (`campus_id`) REFERENCES `campus` (`cid`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=237 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- CREATE TABLE `children_church_attendance` (missing from the dump)
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'children_church_attendance'),
  'DO 0',
  'CREATE TABLE `children_church_attendance` (
  `attendance_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `child_id` bigint(20) unsigned NOT NULL COMMENT ''FK to children_church.child_id'',
  `attendance_date` date NOT NULL COMMENT ''Date of attendance'',
  `marked_by` int(11) DEFAULT NULL COMMENT ''Admin who marked attendance (tiu_member_id)'',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `children_church_attendance_child_id_attendance_date_unique` (`child_id`,`attendance_date`),
  KEY `children_church_attendance_marked_by_foreign` (`marked_by`),
  CONSTRAINT `children_church_attendance_child_id_foreign` FOREIGN KEY (`child_id`) REFERENCES `children_church` (`child_id`) ON DELETE CASCADE,
  CONSTRAINT `children_church_attendance_marked_by_foreign` FOREIGN KEY (`marked_by`) REFERENCES `tiu_member` (`tiu_member_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- CREATE TABLE `child_ceremonies` (missing from the dump)
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'child_ceremonies'),
  'DO 0',
  'CREATE TABLE `child_ceremonies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) unsigned NOT NULL,
  `campus_id` bigint(20) unsigned DEFAULT NULL,
  `ceremony_type` enum(''naming'',''dedication'') NOT NULL,
  `status` enum(''pending'',''completed'') NOT NULL DEFAULT ''pending'',
  `naming_address` text DEFAULT NULL,
  `naming_landmarks` varchar(255) DEFAULT NULL,
  `child_gender` varchar(255) DEFAULT NULL,
  `child_position` varchar(255) DEFAULT NULL,
  `date_of_delivery` date DEFAULT NULL,
  `proposed_naming_date` date DEFAULT NULL,
  `proposed_naming_time` varchar(255) DEFAULT NULL,
  `proposed_child_names` text DEFAULT NULL,
  `parent_background` text DEFAULT NULL,
  `father_name` varchar(255) DEFAULT NULL,
  `mother_name` varchar(255) DEFAULT NULL,
  `dedication_child_name` varchar(255) DEFAULT NULL,
  `dedication_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `child_ceremonies_member_id_index` (`member_id`),
  KEY `child_ceremonies_ceremony_type_index` (`ceremony_type`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- CREATE TABLE `failed_jobs` (missing from the dump)
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'failed_jobs'),
  'DO 0',
  'CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- CREATE TABLE `member_tracking_followup_archive` (missing from the dump)
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_tracking_followup_archive'),
  'DO 0',
  'CREATE TABLE `member_tracking_followup_archive` (
  `archive_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tracking_id` bigint(20) unsigned DEFAULT NULL,
  `first_timer_id` bigint(20) unsigned DEFAULT NULL,
  `tiu_member_id` bigint(20) unsigned DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `followup_mode` varchar(50) DEFAULT NULL,
  `followup_date_time` varchar(50) DEFAULT NULL,
  `followup_response` longtext DEFAULT NULL,
  `followup_rank` varchar(50) DEFAULT NULL,
  `tiu_member_comment` varchar(255) DEFAULT NULL,
  `timeStamp_registered` varchar(50) DEFAULT NULL,
  `followup_response_new` longtext DEFAULT NULL,
  `archive_reason` varchar(100) DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`archive_id`),
  KEY `member_tracking_followup_archive_tracking_id_index` (`tracking_id`),
  KEY `member_tracking_followup_archive_first_timer_id_index` (`first_timer_id`),
  KEY `member_tracking_followup_archive_tiu_member_id_index` (`tiu_member_id`),
  KEY `member_tracking_followup_archive_archived_at_index` (`archived_at`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- CREATE TABLE `migrations` (missing from the dump)
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'migrations'),
  'DO 0',
  'CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- CREATE TABLE `password_resets` (missing from the dump)
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'password_resets'),
  'DO 0',
  'CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- CREATE TABLE `personal_access_tokens` (missing from the dump)
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personal_access_tokens'),
  'DO 0',
  'CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- CREATE TABLE `users` (missing from the dump)
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'),
  'DO 0',
  'CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ===========================================================================
-- 2. Columns the code reads that the dump does not have.
--    Definitions come from the code schema. campus_id is always added NULLABLE:
--    the deployed code writes a row without a campus (docs/SINGLE-CAMPUS.md).
-- ===========================================================================

-- ADD airtime_history.user_name
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'airtime_history' AND COLUMN_NAME = 'user_name'),
  'DO 0',
  'ALTER TABLE `airtime_history` ADD COLUMN `user_name` varchar(255) NULL DEFAULT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD airtime_history.user_phone
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'airtime_history' AND COLUMN_NAME = 'user_phone'),
  'DO 0',
  'ALTER TABLE `airtime_history` ADD COLUMN `user_phone` varchar(100) NULL DEFAULT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD airtime_history.status
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'airtime_history' AND COLUMN_NAME = 'status'),
  'DO 0',
  'ALTER TABLE `airtime_history` ADD COLUMN `status` varchar(50) NOT NULL DEFAULT ''pending'';');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD airtime_history.created_at
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'airtime_history' AND COLUMN_NAME = 'created_at'),
  'DO 0',
  'ALTER TABLE `airtime_history` ADD COLUMN `created_at` timestamp NULL DEFAULT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD airtime_history.updated_at
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'airtime_history' AND COLUMN_NAME = 'updated_at'),
  'DO 0',
  'ALTER TABLE `airtime_history` ADD COLUMN `updated_at` timestamp NULL DEFAULT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD announcements.campus_id
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements' AND COLUMN_NAME = 'campus_id'),
  'DO 0',
  'ALTER TABLE `announcements` ADD COLUMN `campus_id` int(100) NULL DEFAULT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD caller_assignments.caller_member_id
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'caller_assignments' AND COLUMN_NAME = 'caller_member_id'),
  'DO 0',
  'ALTER TABLE `caller_assignments` ADD COLUMN `caller_member_id` bigint(20) unsigned NULL DEFAULT NULL COMMENT ''tiu_member_id of the assigned caller (Touch Point 6 team)'';');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD caller_assignments.created_at
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'caller_assignments' AND COLUMN_NAME = 'created_at'),
  'DO 0',
  'ALTER TABLE `caller_assignments` ADD COLUMN `created_at` timestamp NULL DEFAULT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD caller_assignments.updated_at
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'caller_assignments' AND COLUMN_NAME = 'updated_at'),
  'DO 0',
  'ALTER TABLE `caller_assignments` ADD COLUMN `updated_at` timestamp NULL DEFAULT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD church_attendance.created_at
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'church_attendance' AND COLUMN_NAME = 'created_at'),
  'DO 0',
  'ALTER TABLE `church_attendance` ADD COLUMN `created_at` timestamp NULL DEFAULT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD church_attendance.updated_at
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'church_attendance' AND COLUMN_NAME = 'updated_at'),
  'DO 0',
  'ALTER TABLE `church_attendance` ADD COLUMN `updated_at` timestamp NULL DEFAULT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD communities.campus_id
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'communities' AND COLUMN_NAME = 'campus_id'),
  'DO 0',
  'ALTER TABLE `communities` ADD COLUMN `campus_id` int(100) NULL DEFAULT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD department.campus_id
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'department' AND COLUMN_NAME = 'campus_id'),
  'DO 0',
  'ALTER TABLE `department` ADD COLUMN `campus_id` int(100) NULL DEFAULT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD first_timer.unassign_reason
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'first_timer' AND COLUMN_NAME = 'unassign_reason'),
  'DO 0',
  'ALTER TABLE `first_timer` ADD COLUMN `unassign_reason` text NULL DEFAULT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD first_timer.campus_id
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'first_timer' AND COLUMN_NAME = 'campus_id'),
  'DO 0',
  'ALTER TABLE `first_timer` ADD COLUMN `campus_id` int(100) NULL DEFAULT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD first_timers_updates.house_fellowship
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'first_timers_updates' AND COLUMN_NAME = 'house_fellowship'),
  'DO 0',
  'ALTER TABLE `first_timers_updates` ADD COLUMN `house_fellowship` varchar(100) NOT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD first_time_view_limit.campus_id
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'first_time_view_limit' AND COLUMN_NAME = 'campus_id'),
  'DO 0',
  'ALTER TABLE `first_time_view_limit` ADD COLUMN `campus_id` int(100) NULL DEFAULT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD member_tracking_followup.department_id
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_tracking_followup' AND COLUMN_NAME = 'department_id'),
  'DO 0',
  'ALTER TABLE `member_tracking_followup` ADD COLUMN `department_id` bigint(20) unsigned NOT NULL DEFAULT 23;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD sub_group.department_id
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sub_group' AND COLUMN_NAME = 'department_id'),
  'DO 0',
  'ALTER TABLE `sub_group` ADD COLUMN `department_id` int(11) NOT NULL DEFAULT 0;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD sub_group.campus_id
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sub_group' AND COLUMN_NAME = 'campus_id'),
  'DO 0',
  'ALTER TABLE `sub_group` ADD COLUMN `campus_id` int(100) NULL DEFAULT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ADD task.campus_id
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'task' AND COLUMN_NAME = 'campus_id'),
  'DO 0',
  'ALTER TABLE `task` ADD COLUMN `campus_id` int(11) NULL DEFAULT NULL;');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- task.extra -> task.department_id (the dump still uses the old name)
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'task' AND COLUMN_NAME = 'department_id'),
  'DO 0',
  IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'task' AND COLUMN_NAME = 'extra'),
    'ALTER TABLE `task` CHANGE COLUMN `extra` `department_id` varchar(200) NULL DEFAULT NULL',
    'ALTER TABLE `task` ADD COLUMN `department_id` varchar(200) NULL DEFAULT NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ===========================================================================
-- 3. In the dump, not in the code schema. REPORTED, never dropped - a column
--    here is usually a feature that was retired, not a mistake to fix.
-- ===========================================================================
--
--   bus_attendance (54 row(s)) - retired by app:sync-schema - dropped when it runs, archive it first
--   call_center (49 row(s)) - orphan - nothing in the code reads it, archive before any drop
--   call_tree_assignments (48 row(s)) - orphan - nothing in the code reads it, archive before any drop
--   call_tree_feedback (48 row(s)) - orphan - nothing in the code reads it, archive before any drop
--   first_timer_gallery (54 row(s)) - orphan - nothing in the code reads it, archive before any drop
--   fof_cohort_setting (57 row(s)) - retired by app:sync-schema - dropped when it runs, archive it first
--   fof_mark_attendance_table (52 row(s)) - retired by app:sync-schema - dropped when it runs, archive it first
--   fof_register_table (56 row(s)) - retired by app:sync-schema - dropped when it runs, archive it first
--   gift_logs (50 row(s)) - orphan - nothing in the code reads it, archive before any drop
--   member_telegram (50 row(s)) - orphan - nothing in the code reads it, archive before any drop
--   messages (53 row(s)) - LIVE - the code writes to it
--   transport_routes (57 row(s)) - retired by app:sync-schema - dropped when it runs, archive it first
--   transport_stops (49 row(s)) - retired by app:sync-schema - dropped when it runs, archive it first
--   whatapp (52 row(s)) - orphan - nothing in the code reads it, archive before any drop
--
-- Columns present in the dump and absent from the code schema:
--   airtime_history.network varchar(20)
--   bus_attendance.attendance_id int(11)
--   bus_attendance.tiu_member_id int(11)
--   bus_attendance.route_id int(11)
--   bus_attendance.bus_stop_id int(11)
--   bus_attendance.attendance_date date
--   bus_attendance.check_in_time datetime
--   bus_attendance.marked_by int(11)
--   caller_assignments.assigned_by varchar(100)
--   caller_assignments.assigned_at timestamp
--   call_center.id int(11)
--   call_center.title enum('Mr','Mrs')
--   call_center.full_name varchar(100)
--   call_center.gender enum('Male','Female')
--   call_center.status enum('Active','Inactive')
--   call_center.created_at timestamp
--   call_tree_assignments.assignment_id int(11)
--   call_tree_assignments.caller_member_id int(11)
--   call_tree_assignments.callee_member_id int(11)
--   call_tree_assignments.assignment_date date
--   call_tree_assignments.week_of_year int(2)
--   call_tree_assignments.year int(4)
--   call_tree_assignments.status enum('pending','waiting_approval','completed')
--   call_tree_assignments.call_completed_date datetime
--   call_tree_feedback.feedback_id int(11)
--   call_tree_feedback.assignment_id int(11)
--   call_tree_feedback.feedback_text text
--   call_tree_feedback.submission_date datetime
--   first_timers_updates.house_felloship varchar(100)
--   first_timer_gallery.id int(11)
--   first_timer_gallery.image_name varchar(200)
--   first_timer_gallery.file_path varchar(100)
--   first_timer_gallery.status varchar(100)
--   first_timer_gallery.date_uploaded varchar(100)
--   fof_cohort_setting.cohort_id int(11)
--   fof_cohort_setting.cohort_name varchar(10)
--   fof_cohort_setting.cohort_status varchar(10)
--   fof_mark_attendance_table.id int(11)
--   fof_mark_attendance_table.student_id int(11)
--   fof_mark_attendance_table.cohort varchar(15)
--   fof_mark_attendance_table.week int(11)
--   fof_mark_attendance_table.attendance_date date
--   fof_mark_attendance_table.status enum('Present','Absent')
--   fof_mark_attendance_table.finished tinyint(1)
--   fof_register_table.id int(11)
--   fof_register_table.tiu_member_id int(11)
--   fof_register_table.first_timer_id int(11)
--   fof_register_table.first_name varchar(100)
--   fof_register_table.last_name varchar(100)
--   fof_register_table.email varchar(255)
--   fof_register_table.phone_number varchar(20)
--   fof_register_table.gender varchar(15)
--   fof_register_table.marital_status varchar(15)
--   fof_register_table.cohort_id int(11)
--   fof_register_table.smart_request text
--   fof_register_table.commitment varchar(10)
--   fof_register_table.how_heard varchar(100)
--   fof_register_table.how_heard_other varchar(255)
--   fof_register_table.profile_photo varchar(255)
--   fof_register_table.registration_date datetime
--   gift_logs.log_id int(11)
--   gift_logs.member_id int(11)
--   gift_logs.action_type enum('G','I','F','T')
--   gift_logs.person_name varchar(255)
--   gift_logs.notes text
--   gift_logs.log_date datetime
--   member_telegram.id int(11)
--   member_telegram.tiu_member_id int(11)
--   member_telegram.phone_number varchar(150)
--   member_telegram.telegram_chat_id varchar(150)
--   messages.id int(11)
--   messages.conversation_id int(11)
--   messages.first_timer_name varchar(100)
--   messages.user_name varchar(100)
--   messages.user_id int(11)
--   messages.message text
--   messages.created_at timestamp
--   sub_group.department_name varchar(100)
--   task.extra varchar(200)
--   transport_routes.route_id int(11)
--   transport_routes.route_name varchar(255)
--   transport_routes.driver_name varchar(255)
--   transport_routes.driver_contact varchar(50)
--   transport_routes.team_lead_name varchar(255)
--   transport_routes.team_lead_contact varchar(50)
--   transport_routes.status enum('active','inactive')
--   transport_routes.created_at timestamp
--   transport_routes.updated_at timestamp
--   transport_stops.stop_id int(11)
--   transport_stops.route_id int(11)
--   transport_stops.stop_name varchar(255)
--   transport_stops.take_off_time time
--   transport_stops.latitude decimal(10,8)
--   transport_stops.longitude decimal(11,8)
--   transport_stops.stop_order int(11)
--   whatapp.wh_id int(11)
--   whatapp.f_id int(11)
--   whatapp.first_name varchar(50)
--   whatapp.surname varchar(50)
--   whatapp.phone varchar(20)
--   whatapp.status varchar(20)
--   whatapp.date_joined varchar(20)
--   whatapp.date_removed varchar(20)
--   whatapp.extra varchar(50)

-- ===========================================================================
-- 4. The tables only production has. The reconciliation drops none of them:
--    they are listed here - with the rows each one holds - so that the archive
--    below can be taken before `php artisan app:sync-schema` removes the
--    retired Transport / FOF ones.
-- ===========================================================================
--
--   bus_attendance - 54 row(s) - retired by app:sync-schema
--   call_center - 49 row(s) - orphan - nothing in the code reads it
--   call_tree_assignments - 48 row(s) - orphan - nothing in the code reads it
--   call_tree_feedback - 48 row(s) - orphan - nothing in the code reads it
--   first_timer_gallery - 54 row(s) - orphan - nothing in the code reads it
--   fof_cohort_setting - 57 row(s) - retired by app:sync-schema
--   fof_mark_attendance_table - 52 row(s) - retired by app:sync-schema
--   fof_register_table - 56 row(s) - retired by app:sync-schema
--   gift_logs - 50 row(s) - orphan - nothing in the code reads it
--   member_telegram - 50 row(s) - orphan - nothing in the code reads it
--   messages - 53 row(s) - LIVE - the code writes to it
--   transport_routes - 57 row(s) - retired by app:sync-schema
--   transport_stops - 49 row(s) - retired by app:sync-schema
--   whatapp - 52 row(s) - orphan - nothing in the code reads it
--
-- Archive command (run against the OLD production database; keep the output off
-- the web root - it is a copy of member data):
--
--   mysqldump -u <user> -p --no-create-info --default-character-set=utf8mb4 \
--     <old_database> bus_attendance call_center call_tree_assignments call_tree_feedback first_timer_gallery fof_cohort_setting fof_mark_attendance_table fof_register_table gift_logs member_telegram messages transport_routes transport_stops whatapp \
--     > legacy-tables-<date>.sql
--
-- 6 of these are dropped by app:sync-schema on its first run, so
-- take this archive before that command - it is the only copy of their data.

