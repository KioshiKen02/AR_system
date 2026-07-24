-- Manual schema + announcement update for deferred WHT export handling
-- Run the TENANT DB section on each tenant database.
-- Run the MAIN DB section only on the main database that contains announcements/app_settings.
--
-- Behavior after this update:
-- 1) Floating check still blocks export for that detail.
-- 2) Floating WHT no longer blocks the base payment lines.
-- 3) Cleared WHT exports inline if the payment is still not yet exported.
-- 4) If the payment was already exported before WHT was cleared, the cleared WHT
--    can export later as a standalone WHT entry using the WHT clearing date range.

-- =========================================================
-- TENANT DB
-- =========================================================

SET @db := DATABASE();

-- 1) payment_details.wht_exported_at
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'payment_details'
      AND COLUMN_NAME = 'wht_exported_at'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE `payment_details` ADD COLUMN `wht_exported_at` TIMESTAMP NULL AFTER `wht_clearing_date`',
    'SELECT ''payment_details.wht_exported_at already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =========================================================
-- OPTIONAL TENANT BACKFILL
-- =========================================================
--
-- IMPORTANT:
-- Existing historical rows cannot be perfectly classified automatically.
--
-- If you run the backfill below, already-exported + cleared WHT rows will be marked
-- as exported now, which helps avoid duplicate WHT export for old history.
-- But it will also prevent any old already-exported payments with newly-cleared WHT
-- from being exported later.
--
-- If you want this feature to apply only from today forward, run the backfill.
-- If you want old already-exported payments with cleared WHT to still be picked up
-- by the new logic, DO NOT run the backfill.
--
-- UPDATE `payment_details` pd
-- INNER JOIN `payment` p ON p.`payment_no` = pd.`payment_no`
-- SET pd.`wht_exported_at` = NOW()
-- WHERE p.`exported` = 1
--   AND COALESCE(pd.`wht_amount`, 0) > 0
--   AND pd.`wht_status` = 'Cleared'
--   AND pd.`wht_exported_at` IS NULL;

-- =========================================================
-- MAIN DB
-- =========================================================

SET @db := DATABASE();

-- 2) Announcements table existence check
SET @announcements_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'announcements'
);

SET @announcement_app_setting_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'announcement_app_setting'
);

-- 3) Insert announcement once (applies to all tenants)
SET @announcement_title := 'Payment Export Update: WHT Handling';
SET @announcement_message := 'Payment export now allows base payment lines to generate even when only the WHT is still floating. Floating check transactions remain blocked. WHT lines are exported only when cleared. If a payment was already exported before the WHT was cleared, the cleared WHT will be exported separately on a later export using the WHT clearing date range.';

SET @announcement_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES t
    WHERE t.TABLE_SCHEMA = @db
      AND t.TABLE_NAME = 'announcements'
);

SET @existing_announcement := IF(
    @announcement_exists = 1,
    (
        SELECT COUNT(*)
        FROM `announcements`
        WHERE `title` = @announcement_title
          AND `deleted_at` IS NULL
    ),
    0
);

SET @sql := IF(
    @announcements_exists = 0,
    'SELECT ''announcements table not found in current DB'' AS info',
    IF(
        @existing_announcement > 0,
        'SELECT ''Announcement already exists'' AS info',
        CONCAT(
            'INSERT INTO `announcements` (`title`, `message`, `applies_to_all`, `is_active`, `show_banner`, `show_modal`, `is_dismissible`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (',
            QUOTE(@announcement_title), ', ',
            QUOTE(@announcement_message), ', ',
            '1, 1, 1, 0, 1, NULL, NULL, NOW(), NOW())'
        )
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Optional tenant-targeted mapping example
-- Only use this if you want a future announcement to target selected app_settings
-- instead of applies_to_all = 1.
--
-- SELECT id, app_name FROM app_settings ORDER BY app_name;
-- INSERT IGNORE INTO announcement_app_setting (announcement_id, app_setting_id)
-- VALUES (123, 1), (123, 2);
