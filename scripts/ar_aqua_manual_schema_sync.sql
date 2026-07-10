-- Manual schema sync for ar_aqua payment/WHT/overpayment changes
-- Run the TENANT DB section on each tenant database.
-- Run the MAIN DB section only on the main database that contains app_settings.

-- =========================================================
-- TENANT DB
-- =========================================================

SET @db := DATABASE();

-- 1) payment_details.wht_status
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'payment_details'
      AND COLUMN_NAME = 'wht_status'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE `payment_details` ADD COLUMN `wht_status` VARCHAR(255) NULL AFTER `wht_amount`',
    'SELECT ''payment_details.wht_status already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) payment_details.wht_clearing_date
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'payment_details'
      AND COLUMN_NAME = 'wht_clearing_date'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE `payment_details` ADD COLUMN `wht_clearing_date` DATE NULL AFTER `clearing_date`',
    'SELECT ''payment_details.wht_clearing_date already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) payment_details.overpayment_amount
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'payment_details'
      AND COLUMN_NAME = 'overpayment_amount'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE `payment_details` ADD COLUMN `overpayment_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `overage_shortage`',
    'SELECT ''payment_details.overpayment_amount already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) payment_details.floating_deducted_amount
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'payment_details'
      AND COLUMN_NAME = 'floating_deducted_amount'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE `payment_details` ADD COLUMN `floating_deducted_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `overage_shortage`',
    'SELECT ''payment_details.floating_deducted_amount already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) customer_ledger.overpayment_amount
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'customer_ledger'
      AND COLUMN_NAME = 'overpayment_amount'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE `customer_ledger` ADD COLUMN `overpayment_amount` DECIMAL(10,2) NULL AFTER `running_balance`',
    'SELECT ''customer_ledger.overpayment_amount already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6) wht_cleared_items.type
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'wht_cleared_items'
      AND COLUMN_NAME = 'type'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE `wht_cleared_items` ADD COLUMN `type` VARCHAR(255) NULL AFTER `wht_no`',
    'SELECT ''wht_cleared_items.type already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 7) wht_cleared_items.wht_no nullable
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'wht_cleared_items'
      AND COLUMN_NAME = 'wht_no'
      AND IS_NULLABLE = 'YES'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE `wht_cleared_items` MODIFY COLUMN `wht_no` VARCHAR(255) NULL',
    'SELECT ''wht_cleared_items.wht_no already nullable'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 8) Optional backfill
UPDATE `payment_details`
SET
    `overpayment_amount` = COALESCE(`overpayment_amount`, 0),
    `floating_deducted_amount` = COALESCE(`floating_deducted_amount`, 0)
WHERE `overpayment_amount` IS NULL
   OR `floating_deducted_amount` IS NULL;

-- =========================================================
-- MAIN DB
-- =========================================================

-- Switch to your main database before running this block.
SET @db := DATABASE();

-- 9) app_settings.allow_overpayment
SET @exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'app_settings'
      AND COLUMN_NAME = 'allow_overpayment'
);
SET @sql := IF(
    @exists = 0,
    'ALTER TABLE `app_settings` ADD COLUMN `allow_overpayment` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_active`',
    'SELECT ''app_settings.allow_overpayment already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `app_settings`
SET `allow_overpayment` = 1
WHERE `allow_overpayment` IS NULL;

-- =========================================================
-- OPTIONAL FK ALIGNMENT
-- =========================================================

-- Only run this if you want wht_cleared_items to cascade on delete
-- from wht_cleared, and after confirming the current FK name.
--
-- SELECT CONSTRAINT_NAME
-- FROM information_schema.KEY_COLUMN_USAGE
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND TABLE_NAME = 'wht_cleared_items'
--   AND COLUMN_NAME = 'wht_clearing_no'
--   AND REFERENCED_TABLE_NAME IS NOT NULL;
--
-- ALTER TABLE `wht_cleared_items` DROP FOREIGN KEY `your_fk_name_here`;
-- ALTER TABLE `wht_cleared_items`
--     ADD CONSTRAINT `wht_cleared_items_wht_clearing_no_foreign`
--     FOREIGN KEY (`wht_clearing_no`)
--     REFERENCES `wht_cleared`(`wht_clearing_no`)
--     ON DELETE CASCADE;
