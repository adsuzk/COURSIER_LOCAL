-- Safe recreate of view_device_stats without DEFINER to avoid mysqld crash
DROP TABLE IF EXISTS `view_device_stats`;

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `view_device_stats` AS
SELECT
  `d`.`device_id` AS `device_id`,
  `d`.`device_model` AS `device_model`,
  `d`.`device_brand` AS `device_brand`,
  `d`.`android_version` AS `android_version`,
  `d`.`app_version_code` AS `app_version_code`,
  `d`.`app_version_name` AS `app_version_name`,
  `d`.`last_seen` AS `last_seen`,
  `d`.`total_sessions` AS `total_sessions`,
  coalesce(`c`.`crash_count`,0) AS `crash_count`,
  CASE
    WHEN `d`.`last_seen` >= current_timestamp() - interval 1 day THEN 'ACTIVE'
    WHEN `d`.`last_seen` >= current_timestamp() - interval 7 day THEN 'INACTIVE'
    ELSE 'DORMANT'
  END AS `activity_status`,
  CASE
    WHEN `v`.`version_code` > `d`.`app_version_code` THEN 'UPDATE_NEEDED'
    ELSE 'UP_TO_DATE'
  END AS `update_status`
FROM (
  (`app_devices` `d`
    LEFT JOIN (
      SELECT `app_crashes`.`device_id` AS `device_id`, COUNT(0) AS `crash_count`
      FROM `app_crashes`
      WHERE `app_crashes`.`created_at` >= current_timestamp() - interval 30 day
      GROUP BY `app_crashes`.`device_id`
    ) `c` ON (`c`.`device_id` = `d`.`device_id`)
  )
  JOIN (
    SELECT MAX(`app_versions`.`version_code`) AS `version_code`
    FROM `app_versions`
    WHERE `app_versions`.`is_active` = 1
  ) `v`
)
WHERE `d`.`is_active` = 1;
