<?php
/**
 * Ensures the Banking Education table exists before any page reads or writes it.
 * This makes the education feature work immediately after extracting the project,
 * without requiring a separate phpMyAdmin import.
 */
if (isset($conn) && $conn instanceof mysqli) {
    $sql = "CREATE TABLE IF NOT EXISTS education_resources (
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
        CONSTRAINT fk_education_uploaded_by FOREIGN KEY (uploaded_by)
            REFERENCES users(user_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Do not stop the public website with a fatal error here. If the FK cannot
    // be created because of an older users table, create the table without it.
    if (!mysqli_query($conn, $sql)) {
        $fallback = "CREATE TABLE IF NOT EXISTS education_resources (
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
            INDEX idx_education_type (resource_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        @mysqli_query($conn, $fallback);
    }
}
