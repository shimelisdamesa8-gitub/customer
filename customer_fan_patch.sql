-- Add FAN support for Account Opening customer registration.
-- Run this once in phpMyAdmin. Safe to run on existing installations.

SET @db = DATABASE();
SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA=@db AND TABLE_NAME='customers' AND COLUMN_NAME='fan'
        ),
        'SELECT 1',
        "ALTER TABLE customers ADD COLUMN fan VARCHAR(100) NULL AFTER amount"
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
