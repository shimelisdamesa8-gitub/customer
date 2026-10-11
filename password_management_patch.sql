-- Password management patch for the local/internship installation.
-- The application also auto-creates system_settings if it is missing.
CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO system_settings (setting_key, setting_value)
VALUES ('admin_recovery_code', 'CHANGE-ME-DAWO-ADMIN-2026')
ON DUPLICATE KEY UPDATE setting_key=setting_key;
