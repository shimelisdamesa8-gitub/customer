-- Service Management hardening
-- Run once in phpMyAdmin after the services table already exists.
-- It consolidates case/outer-space duplicates and moves references to the keeper service.

START TRANSACTION;

CREATE TEMPORARY TABLE service_duplicate_map AS
SELECT
    s.service_id AS duplicate_id,
    MIN(k.service_id) AS keeper_id
FROM services s
JOIN services k
    ON LOWER(TRIM(s.service_name)) = LOWER(TRIM(k.service_name))
   AND k.service_id <= s.service_id
GROUP BY s.service_id
HAVING MIN(k.service_id) <> s.service_id;

-- Keep queue history valid when a duplicate service is removed.
UPDATE queue_records q
JOIN service_duplicate_map m ON m.duplicate_id = q.service_id
SET q.service_id = m.keeper_id;

-- Keep the legacy customer service name in sync with the surviving service.
UPDATE customers c
JOIN services duplicate_service
    ON LOWER(TRIM(c.service_type)) = LOWER(TRIM(duplicate_service.service_name))
JOIN service_duplicate_map m
    ON m.duplicate_id = duplicate_service.service_id
JOIN services keeper_service
    ON keeper_service.service_id = m.keeper_id
SET c.service_type = keeper_service.service_name;

DELETE s
FROM services s
JOIN service_duplicate_map m ON m.duplicate_id = s.service_id;

DROP TEMPORARY TABLE service_duplicate_map;

-- Normalize leading/trailing spaces in the surviving names.
UPDATE services
SET service_name = TRIM(service_name)
WHERE service_name <> TRIM(service_name);

-- Make the name comparison explicitly case-insensitive.
ALTER TABLE services
    MODIFY service_name VARCHAR(100)
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci
    NOT NULL;

-- The existing UNIQUE constraint on service_name remains the final database-level guard.
COMMIT;
