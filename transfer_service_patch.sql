-- Add Transfer service for customer registration.
-- Safe to run on an existing database; INSERT IGNORE prevents duplicates.
INSERT IGNORE INTO services (service_name, status) VALUES ('Transfer', 'Active');
