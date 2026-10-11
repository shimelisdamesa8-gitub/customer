-- Customer feedback -> administrator notification support
-- The PHP pages also create this table automatically when needed.
CREATE TABLE IF NOT EXISTS admin_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'general',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(500) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_read (admin_user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
