<?php
// Safely add profile fields and create the profile approval table for existing installations.
if (!isset($conn) || !$conn) { return; }

$cols = mysqli_query($conn, "SHOW COLUMNS FROM users");
$existing = [];
if ($cols) {
    while ($c = mysqli_fetch_assoc($cols)) {
        $existing[$c['Field']] = true;
    }
}

if (!isset($existing['profile_photo'])) {
    @mysqli_query($conn, "ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL");
}

if (!isset($existing['phone'])) {
    @mysqli_query($conn, "ALTER TABLE users ADD COLUMN phone VARCHAR(40) NULL");
}

// Profile changes are submitted by staff and become active only after admin approval.
// Keep this query inside PHP so it is executed instead of being printed as page text.
// Notifications for admin approval requests and user approval results.
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS admin_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'general',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(500) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_read (admin_user_id,is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS user_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'general',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(500) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (user_id,is_read),
    INDEX idx_user_created (user_id,created_at),
    CONSTRAINT fk_un_user FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS profile_change_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    profile_photo VARCHAR(255) NULL,
    status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    reviewed_by INT NULL,
    INDEX idx_pcr_user_status(user_id,status),
    CONSTRAINT fk_pcr_user FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
?>
