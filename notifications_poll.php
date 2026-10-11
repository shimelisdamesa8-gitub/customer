<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Unauthorized']);
    exit;
}
$uid=(int)$_SESSION['user_id'];
@mysqli_query($conn,"CREATE TABLE IF NOT EXISTS admin_notifications (notification_id INT AUTO_INCREMENT PRIMARY KEY,admin_user_id INT NULL,type VARCHAR(50) NOT NULL DEFAULT 'general',title VARCHAR(255) NOT NULL,message TEXT NOT NULL,link VARCHAR(500) NULL,is_read TINYINT(1) NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_admin_read (admin_user_id,is_read),INDEX idx_created (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$rows=[]; $count=0;
$q=mysqli_query($conn,"SELECT notification_id,type,title,message,link,created_at,is_read FROM admin_notifications WHERE (admin_user_id IS NULL OR admin_user_id=$uid) ORDER BY notification_id DESC LIMIT 10");
if($q){ while($r=mysqli_fetch_assoc($q)){ $r['notification_id']=(int)$r['notification_id']; $r['is_read']=(int)$r['is_read']; $rows[]=$r; if(!$r['is_read']) $count++; }}
echo json_encode(['ok'=>true,'count'=>$count,'notifications'=>$rows], JSON_UNESCAPED_UNICODE);
