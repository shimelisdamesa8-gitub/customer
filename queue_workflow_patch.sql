-- customer_system queue workflow patch
-- Run these statements in phpMyAdmin if the columns do not already exist.

ALTER TABLE queue_records
    ADD COLUMN assigned_time DATETIME NULL;

ALTER TABLE queue_records
    ADD COLUMN start_time DATETIME NULL;

ALTER TABLE queue_records
    ADD COLUMN completed_time DATETIME NULL;

-- Required status values used by the application:
-- Pending, Current, Verified, Assigned, Serving, Completed, Skipped, Cancelled
