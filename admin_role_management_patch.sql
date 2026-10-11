-- Admin role management support. Safe to run once.
-- Role IDs: 1=Administrator, 2=Receptionist, 3=Teller.
-- This patch intentionally does not delete or recreate users.

CREATE TABLE IF NOT EXISTS admin_role_audit (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NOT NULL,
    target_user_id INT NOT NULL,
    old_role_id INT NOT NULL,
    new_role_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_role_audit_actor(actor_user_id),
    INDEX idx_admin_role_audit_target(target_user_id),
    INDEX idx_admin_role_audit_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
