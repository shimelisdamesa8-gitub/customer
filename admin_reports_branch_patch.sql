-- COMPLETE ADMIN REPORT + SINGLE BRANCH MANAGEMENT PATCH
-- Run this once in phpMyAdmin for bank_queue_management_system.

CREATE TABLE IF NOT EXISTS report_archive (
    archive_id INT AUTO_INCREMENT PRIMARY KEY,
    report_date DATE NOT NULL UNIQUE,
    total_customers INT NOT NULL DEFAULT 0,
    pending_customers INT NOT NULL DEFAULT 0,
    assigned_customers INT NOT NULL DEFAULT 0,
    serving_customers INT NOT NULL DEFAULT 0,
    completed_customers INT NOT NULL DEFAULT 0,
    archived_by INT NULL,
    archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_report_date (report_date),
    CONSTRAINT fk_report_archive_user
        FOREIGN KEY (archived_by) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS branches (
    branch_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(100) NOT NULL UNIQUE,
    location VARCHAR(150) DEFAULT '',
    phone VARCHAR(30) DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO branches (branch_name, location, phone, status)
VALUES ('Dawo Branch', 'Dawo Branch', '', 'Active');


-- SINGLE-BRANCH RULE:
-- Keep the first branch and remove any extra branches.
DELETE b2 FROM branches b2
JOIN branches b1 ON b1.branch_id < b2.branch_id;

-- The application displays and manages only one branch.
