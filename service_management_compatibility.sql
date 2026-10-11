-- Compatibility patch for existing installations.
-- Run once if the application database was created before Service Management.
SET @db = DATABASE();

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA=@db AND TABLE_NAME='services' AND COLUMN_NAME='status'
        ),
        'SELECT 1',
        "ALTER TABLE services ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Active'"
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA=@db AND TABLE_NAME='services' AND COLUMN_NAME='created_at'
        ),
        'SELECT 1',
        "ALTER TABLE services ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE services SET status='Active' WHERE status IS NULL OR TRIM(status)='';
