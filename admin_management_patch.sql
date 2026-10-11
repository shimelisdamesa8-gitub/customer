-- Admin management database patch
-- Run this once in phpMyAdmin if the services table does not exist.
CREATE TABLE IF NOT EXISTS services (
    service_id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL UNIQUE,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO services (service_name, status) VALUES
('Deposit','Active'),
('Withdrawal','Active'),
('Transfer','Active'),
('Account Opening','Active'),
('Loan Service','Active'),
('ATM Service','Active'),
('Book Opening','Active'),
('Other','Active');
