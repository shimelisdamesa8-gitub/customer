-- Fix notification links created by older versions of the dashboard.
-- dashboard.php is already inside /admin/, so these must be relative admin-page links.
UPDATE admin_notifications SET link='feedback.php' WHERE type='feedback' AND (link='admin/feedback.php' OR link='/admin/admin/feedback.php');
UPDATE admin_notifications SET link='profile_requests.php' WHERE type='profile_approval' AND (link='admin/profile_requests.php' OR link='/admin/admin/profile_requests.php');
