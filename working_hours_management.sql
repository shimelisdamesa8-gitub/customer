CREATE TABLE IF NOT EXISTS working_hours (
    working_hour_id INT AUTO_INCREMENT PRIMARY KEY,
    day_name VARCHAR(20) NOT NULL,
    open_time TIME NULL,
    close_time TIME NULL,
    status ENUM('Active','Inactive','Removed') NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_working_day (day_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO working_hours (day_name, open_time, close_time, status) VALUES
('Monday','08:00:00','17:00:00','Active'),
('Tuesday','08:00:00','17:00:00','Active'),
('Wednesday','08:00:00','17:00:00','Active'),
('Thursday','08:00:00','17:00:00','Active'),
('Friday','08:00:00','17:00:00','Active'),
('Saturday',NULL,NULL,'Inactive'),
('Sunday',NULL,NULL,'Inactive');
