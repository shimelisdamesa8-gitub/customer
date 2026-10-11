<?php
// Dynamic site content setup. Safe to include after database.php.
if (!isset($conn) || !$conn) { return; }

@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS homepage_slides (
    slide_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$cols = @mysqli_query($conn, "SHOW COLUMNS FROM services");
$serviceCols = [];
if ($cols) { while ($c = mysqli_fetch_assoc($cols)) $serviceCols[$c['Field']] = true; }
if (!isset($serviceCols['image_path'])) {
    @mysqli_query($conn, "ALTER TABLE services ADD COLUMN image_path VARCHAR(255) NULL");
}

function site_get_setting($conn, $key, $default = '') {
    $stmt = @mysqli_prepare($conn, "SELECT setting_value FROM site_settings WHERE setting_key=? LIMIT 1");
    if (!$stmt) return $default;
    mysqli_stmt_bind_param($stmt, "s", $key);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return ($row && isset($row['setting_value'])) ? $row['setting_value'] : $default;
}
function site_set_setting($conn, $key, $value) {
    $stmt = @mysqli_prepare($conn, "INSERT INTO site_settings(setting_key,setting_value) VALUES(?,?)
        ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, "ss", $key, $value);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

// Site-wide branding colors.
site_set_setting($conn, 'primary_color', site_get_setting($conn, 'primary_color', '#003DA5'));
site_set_setting($conn, 'secondary_color', site_get_setting($conn, 'secondary_color', '#EAAA00'));
// One master background color controlled from Admin > Branding.
site_set_setting($conn, 'global_background_color', site_get_setting($conn, 'global_background_color', '#F7F9FC'));
site_set_setting($conn, 'apply_global_background', site_get_setting($conn, 'apply_global_background', '1'));
$sectionBrandDefaults = [
    'navbar_color'=>'#003DA5','hero_color'=>'#003DA5','services_color'=>'#f8f9fa','education_color'=>'#f8f9fa',
    'working_hours_color'=>'#ffffff','about_color'=>'#f8f9fa','feedback_color'=>'#ffffff','contact_color'=>'#ffffff','footer_color'=>'#003DA5','button_color'=>'#EAAA00'
];
foreach ($sectionBrandDefaults as $k=>$v) { site_set_setting($conn, $k, site_get_setting($conn, $k, $v)); }
// ATM Service is intentionally removed from the public service catalogue.
@mysqli_query($conn, "UPDATE services SET status='Inactive' WHERE LOWER(TRIM(service_name))='atm service'");



// === BERHAN BANK BRAND INITIALIZATION ===
// The packaged system is configured for Berhan Bank. Existing administrator
// customizations are preserved after the initial values are set.
$__berhanDefaults = [
    'primary_color'=>'#003DA5',
    'secondary_color'=>'#EAAA00',
    'global_background_color'=>'#F7F9FC',
    'navbar_color'=>'#003DA5',
    'hero_color'=>'#003DA5',
    'footer_color'=>'#003DA5',
    'button_color'=>'#EAAA00',
    'site_name'=>'Berhan Bank',
    'footer_copyright'=>'Berhan Bank',
    'logo_path'=>'assets/berhan-bank-logo.svg'
];
foreach ($__berhanDefaults as $__k=>$__v) {
    if (site_get_setting($conn,$__k,'') === '') {
        site_set_setting($conn,$__k,$__v);
    }
}
unset($__berhanDefaults,$__k,$__v);

function site_upload_image($field, $dir, $prefix = 'img_') {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    if ($_FILES[$field]['size'] > 6 * 1024 * 1024) return null;
    $tmp = $_FILES[$field]['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info) return null;
    $mime = $info['mime'] ?? '';
    $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/svg+xml'=>'svg'];
    if (!isset($extMap[$mime])) return null;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = $prefix . bin2hex(random_bytes(8)) . '.' . $extMap[$mime];
    $target = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
    if (!@move_uploaded_file($tmp, $target)) return null;
    return $name;
}
