-- Run against each BU database that will use receipt corrections and export untagging.
-- This adds the audit table only; it does not change existing transactions or export flags.
CREATE TABLE IF NOT EXISTS `transaction_audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_type` VARCHAR(20) NOT NULL,
    `transaction_id` BIGINT UNSIGNED NOT NULL,
    `transaction_no` VARCHAR(255) NOT NULL,
    `action` VARCHAR(40) NOT NULL,
    `changes` JSON NOT NULL,
    `remarks` TEXT NOT NULL,
    `actor_id` VARCHAR(255) NOT NULL,
    `actor_name` VARCHAR(255) NOT NULL,
    `actor_username` VARCHAR(255) NULL,
    `actor_employee_id` VARCHAR(255) NULL,
    `occurred_at` TIMESTAMP NOT NULL,
    PRIMARY KEY (`id`),
    KEY `transaction_audit_target` (`transaction_type`, `transaction_id`, `id`),
    KEY `transaction_audit_logs_transaction_no_index` (`transaction_no`),
    KEY `transaction_audit_logs_occurred_at_index` (`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
