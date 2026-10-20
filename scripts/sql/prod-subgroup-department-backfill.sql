-- prod-subgroup-department-backfill.sql
--
-- One-off repair for the LIVE database: sub_group.department_id is 0 on every
-- imported row, while the department is only named in the legacy
-- department_name column. The deployed code (and, before this release, the
-- profile page too) filters by department_id, so:
--
--   /group                 shows no subgroup panels at all
--   /profile (Touch Point) renders an empty dropdown for every member
--   the mobile subgroup/touchpoint APIs answer with empty lists
--
-- Running this makes those pages work on the CURRENT deploy, without waiting
-- for the release that reads either schema shape. The new code does not need
-- it, so it is safe to run before or after deploying.
--
-- Precondition: sub_group has both department_id and department_name (the
-- reconciliation in prod-reconciliation.sql adds the id column).
--
-- Safe to run twice: only rows still at 0/NULL are touched. The seven
-- department-less "Touch Point 11..18" rows stay at 0 on purpose - no member is
-- assigned to them and no department owns them.
--
--   mysql -u <user> -p <database> < prod-subgroup-department-backfill.sql
--
-- or paste it into phpMyAdmin's SQL tab with the production database selected.
-- Same statements as section 6 of prod-post-import-fixes.sql.

-- 1. department_id from the legacy department name. The leading "The" rule
--    covers the dump's "Switch" for the department table's "The Switch".
UPDATE `sub_group` AS sg
  JOIN `department` AS d
    ON TRIM(LOWER(d.dept_name)) = TRIM(LOWER(sg.department_name))
    OR TRIM(LOWER(d.dept_name)) = CONCAT('the ', TRIM(LOWER(sg.department_name)))
   SET sg.department_id = d.dept_id
 WHERE (sg.department_id IS NULL OR sg.department_id = 0)
   AND TRIM(COALESCE(sg.department_name, '')) <> '';

-- 2. department_name for rows the new code created (id only), so the legacy
--    column stays a usable index of the same row.
UPDATE `sub_group` AS sg
  JOIN `department` AS d ON d.dept_id = sg.department_id
   SET sg.department_name = d.dept_name
 WHERE (sg.department_name IS NULL OR sg.department_name = '')
   AND sg.department_id > 0;

-- 3. Verify: each department's groups with the members matching them. The
--    touch point department (23) must show seven groups, each with its members.
SELECT sg.subid,
       sg.department_id,
       sg.department_name,
       sg.sub_group_name,
       (SELECT COUNT(*)
          FROM tiu_member m
         WHERE m.subgroup LIKE CONCAT('%', sg.sub_group_name, '%')) AS matching_members
  FROM sub_group sg
 WHERE sg.department_id > 0
 ORDER BY sg.department_id, sg.sub_group_name;
