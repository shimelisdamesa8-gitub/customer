-- Dynamic priority management compatibility patch
CREATE TABLE IF NOT EXISTS priority_categories (
    priority_id INT NOT NULL AUTO_INCREMENT,
    priority_name VARCHAR(100) NOT NULL,
    priority_level INT NOT NULL DEFAULT 1,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (priority_id),
    UNIQUE KEY uq_priority_name (priority_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db = DATABASE();
SET @sql = (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='priority_categories' AND COLUMN_NAME='priority_level'),'SELECT 1',"ALTER TABLE priority_categories ADD COLUMN priority_level INT NOT NULL DEFAULT 1 AFTER priority_name")); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='priority_categories' AND COLUMN_NAME='status'),'SELECT 1',"ALTER TABLE priority_categories ADD COLUMN status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active' AFTER priority_level")); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='priority_categories' AND COLUMN_NAME='created_at'),'SELECT 1',"ALTER TABLE priority_categories ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP")); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='priority_categories' AND COLUMN_NAME='updated_at'),'SELECT 1',"ALTER TABLE priority_categories ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP")); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = (SELECT IF(EXISTS(SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='customers' AND COLUMN_NAME='priority_id'),'SELECT 1',"ALTER TABLE customers ADD COLUMN priority_id INT NULL")); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO priority_categories (priority_name, priority_level, status) SELECT 'VIP',1,'Active' WHERE NOT EXISTS (SELECT 1 FROM priority_categories WHERE LOWER(TRIM(priority_name))='vip');
INSERT INTO priority_categories (priority_name, priority_level, status) SELECT 'Elderly',2,'Active' WHERE NOT EXISTS (SELECT 1 FROM priority_categories WHERE LOWER(TRIM(priority_name))='elderly');
INSERT INTO priority_categories (priority_name, priority_level, status) SELECT 'Special',3,'Active' WHERE NOT EXISTS (SELECT 1 FROM priority_categories WHERE LOWER(TRIM(priority_name))='special');
INSERT INTO priority_categories (priority_name, priority_level, status) SELECT 'Normal',4,'Active' WHERE NOT EXISTS (SELECT 1 FROM priority_categories WHERE LOWER(TRIM(priority_name))='normal');
