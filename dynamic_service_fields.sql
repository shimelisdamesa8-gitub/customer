-- Dynamic service customer-field configuration
-- Run once in phpMyAdmin after the existing service/customer tables are installed.

CREATE TABLE IF NOT EXISTS service_fields (
    field_id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    field_key VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_service_field (service_id, field_key),
    INDEX idx_service_fields_service (service_id),
    CONSTRAINT fk_service_fields_service
        FOREIGN KEY (service_id) REFERENCES services(service_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Give existing services a sensible initial configuration.
-- Admin can change these selections later from Service Management.
INSERT IGNORE INTO service_fields (service_id, field_key)
SELECT service_id, 'full_name' FROM services
WHERE status='Active';

INSERT IGNORE INTO service_fields (service_id, field_key)
SELECT service_id, 'phone' FROM services
WHERE status='Active';

INSERT IGNORE INTO service_fields (service_id, field_key)
SELECT service_id, 'fan' FROM services
WHERE status='Active'
  AND LOWER(TRIM(service_name)) = 'account opening';

INSERT IGNORE INTO service_fields (service_id, field_key)
SELECT service_id, 'account_number' FROM services
WHERE status='Active'
  AND LOWER(TRIM(service_name)) IN ('deposit','withdrawal','transfer','fund transfer','loan service','atm service');

INSERT IGNORE INTO service_fields (service_id, field_key)
SELECT service_id, 'amount' FROM services
WHERE status='Active'
  AND LOWER(TRIM(service_name)) IN ('deposit','withdrawal','transfer','fund transfer','loan service');
