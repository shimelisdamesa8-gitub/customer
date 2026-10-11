-- Banking Education / Learning Resource Management
CREATE TABLE IF NOT EXISTS education_resources (
    education_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'General Banking',
    resource_type ENUM('Video','PDF','Document','Presentation','Other') NOT NULL DEFAULT 'Document',
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(150) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    uploaded_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_education_status (status),
    INDEX idx_education_category (category),
    INDEX idx_education_type (resource_type),
    CONSTRAINT fk_education_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
