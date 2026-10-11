-- Optional performance indexes for receptionist/teller search.
-- Run these only if the indexes do not already exist.

CREATE INDEX idx_customers_account ON customers(account_number);
CREATE INDEX idx_customers_phone ON customers(phone);
CREATE INDEX idx_customers_name ON customers(full_name);
CREATE INDEX idx_queue_number ON queue_records(queue_number);
