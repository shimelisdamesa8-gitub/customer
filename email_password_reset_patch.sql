-- Employee email and email-confirmed password reset workflow.
ALTER TABLE users ADD COLUMN email VARCHAR(190) NULL AFTER username;

CREATE TABLE IF NOT EXISTS password_reset_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    status ENUM('Pending','Approved','Declined','Expired','Completed') NOT NULL DEFAULT 'Pending',
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    temp_password_sent_at DATETIME NULL,
    INDEX idx_prr_token (token_hash),
    INDEX idx_prr_user_status (user_id, status),
    CONSTRAINT fk_prr_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
