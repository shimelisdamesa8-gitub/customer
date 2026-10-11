-- OPTIONAL MANUAL RESET FOR PRESENTATION/TESTING
-- WARNING: This permanently deletes transactional/demo data.
-- It keeps users, services, priorities and branch configuration.

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE queue_records;
TRUNCATE TABLE customers;
TRUNCATE TABLE report_archive;
TRUNCATE TABLE activity_logs;
SET FOREIGN_KEY_CHECKS = 1;

-- No customer, queue, archive or activity data remains after this reset.
