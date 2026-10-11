-- Security hardening for staff authentication.
-- Existing/new staff passwords must be stored with PHP password_hash().
-- This patch only adds a useful index; it does NOT attempt to reverse or guess plaintext passwords.

ALTER TABLE users ADD INDEX idx_users_username (username);
