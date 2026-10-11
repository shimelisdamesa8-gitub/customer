<?php
require_once __DIR__ . "/../config/language.php";

require_once __DIR__ . "/../config/session.php";

include("../config/database.php");
require_once __DIR__ . "/../config/profile_setup.php";
require_once __DIR__ . "/../config/site_setup.php";


// Admin only

if(!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1)
{
    header("Location: ../login.php");
    exit();
}


$dashUser = mysqli_fetch_assoc(mysqli_query($conn, "SELECT full_name, username, profile_photo FROM users WHERE user_id=".(int)$_SESSION['user_id']));
$dashPhoto = !empty($dashUser['profile_photo']) ? '../'.ltrim($dashUser['profile_photo'],'/') : '';
$dashInitial = strtoupper(substr(trim($dashUser['full_name'] ?? 'A'),0,1));

// ==============================
// CONTACT / SOCIAL POST MANAGEMENT
// ==============================
$contact_post_saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_contact_post'])) {
    $socialKeys = ['facebook','telegram','twitter','linkedin','whatsapp','email'];
    foreach ($socialKeys as $key) {
        $value = trim($_POST['social_'.$key] ?? '');
        site_set_setting($conn, 'social_'.$key, $value);
    }
    $contact_post_saved = true;
}

$dashboardSocial = [];
foreach (['facebook','telegram','twitter','linkedin','whatsapp','email'] as $key) {
    $dashboardSocial[$key] = site_get_setting($conn, 'social_'.$key, '');
}

// ==============================
// ADMIN NOTIFICATIONS
// ==============================
@mysqli_query($conn,"CREATE TABLE IF NOT EXISTS admin_notifications (notification_id INT AUTO_INCREMENT PRIMARY KEY,admin_user_id INT NULL,type VARCHAR(50) NOT NULL DEFAULT 'general',title VARCHAR(255) NOT NULL,message TEXT NOT NULL,link VARCHAR(500) NULL,is_read TINYINT(1) NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_admin_read (admin_user_id,is_read),INDEX idx_created (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_admin_notifications_read'])) {
    $uid=(int)$_SESSION['user_id'];
    $rq=mysqli_prepare($conn,"UPDATE admin_notifications SET is_read=1 WHERE is_read=0 AND (admin_user_id IS NULL OR admin_user_id=?)");
    if($rq){mysqli_stmt_bind_param($rq,'i',$uid);mysqli_stmt_execute($rq);mysqli_stmt_close($rq);}
    header('Location: dashboard.php'); exit();
}
$adminUid=(int)$_SESSION['user_id'];
$notificationCount=0; $notificationRows=[];
$nq=mysqli_query($conn,"SELECT notification_id,type,title,message,link,created_at,is_read FROM admin_notifications WHERE (admin_user_id IS NULL OR admin_user_id=$adminUid) ORDER BY notification_id DESC LIMIT 10");
if($nq){while($nr=mysqli_fetch_assoc($nq)){ $notificationRows[]=$nr; if((int)$nr['is_read']===0)$notificationCount++; }}

// ==============================
// STATISTICS
// ==============================


// Total Customers

$total_customer = mysqli_fetch_assoc(

    mysqli_query($conn,

    "SELECT COUNT(DISTINCT account_number) total
     FROM customers
     WHERE account_number IS NOT NULL
       AND TRIM(account_number) <> ''"

    )

);


// Today's Customers

$today_customer = mysqli_fetch_assoc(

    mysqli_query($conn,

    "SELECT COUNT(*) total
     FROM queue_records
     WHERE DATE(created_time)=CURDATE()"

    )

);


// Pending

$pending = mysqli_fetch_assoc(

    mysqli_query($conn,

    "SELECT COUNT(*) total
     FROM queue_records
     WHERE status='Pending'"

    )

);


// Serving

$serving = mysqli_fetch_assoc(

    mysqli_query($conn,

    "SELECT COUNT(*) total
     FROM queue_records
     WHERE status='Serving'"

    )

);


// Assigned

$assigned = mysqli_fetch_assoc(

    mysqli_query($conn,

    "SELECT COUNT(*) total
     FROM queue_records
     WHERE status='Assigned'"

    )

);


// Completed Today

$completed = mysqli_fetch_assoc(

    mysqli_query($conn,

    "SELECT COUNT(*) total
     FROM queue_records
     WHERE status='Completed'
     AND DATE(completed_time)=CURDATE()"

    )

);


// Total Employees

$employees = mysqli_fetch_assoc(

    mysqli_query($conn,

    "SELECT COUNT(*) total
     FROM users
     WHERE role_id IN (2,3)"

    )

);


// Active Tellers

$active_tellers = mysqli_fetch_assoc(

    mysqli_query($conn,

    "SELECT COUNT(*) total
     FROM users
     WHERE role_id=3
     AND status='Active'"

    )

);


// Active Receptionists

$active_receptionists = mysqli_fetch_assoc(

    mysqli_query($conn,

    "SELECT COUNT(*) total
     FROM users
     WHERE role_id=2
     AND status='Active'"

    )

);


// ==============================
// RECENT ACTIVITY
// ==============================

$activity = mysqli_query($conn,

"SELECT

a.action,
a.created_at,
u.full_name

FROM activity_logs a

JOIN users u

ON a.user_id=u.user_id

ORDER BY a.created_at DESC

LIMIT 100"

);

?>



<!DOCTYPE html>

<html>

<head>
    <link rel="stylesheet" href="css/dashboard.css">

<title>
Admin Dashboard
</title>


<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">
</head>


<body>


<div class="dashboard-layout">

<aside class="admin-sidebar" style="background-color:#EAAA00">
    <div class="sidebar-brand">
        <img src="../assets/berhan-bank-logo-white.svg" alt="Berhan Bank">
        <div><strong>Admin Panel</strong><small><?= htmlspecialchars(t("system_management")) ?></small></div>
    </div>
    <nav class="sidebar-nav">
        <a class="sidebar-link active" href="dashboard.php"><span>🏠</span> Dashboard</a>
        <a class="sidebar-link" href="../teller/dashboard.php"><span>🧑‍💼</span> Service Workspace</a>
        <a class="sidebar-link" href="employees.php"><span>👥</span> Employee Management</a>
<a href="admin_management.php" class="btn btn-primary">🛡️ Admin Management</a>
        <a class="sidebar-link" href="add_employee.php"><span>➕</span> Add Employee</a>
        <a class="sidebar-link" href="customers.php"><span>👤</span> Customer Management</a>
        <a class="sidebar-link" href="branches.php"><span>🏦</span> Branch Management</a>
        <a class="sidebar-link" href="services.php"><span>🛠️</span> Service Management</a>
        <a class="sidebar-link" href="working_hours.php"><span>🕐</span> Working Hours</a>
        <a class="sidebar-link" href="priority_management.php"><span>⭐</span> Priority Management</a>
        <a class="sidebar-link" href="education.php"><span>🎓</span> Education Management</a>
        <a class="sidebar-link homepage-post-link" href="branding.php"><span>🖼️</span> Welcome/Homepage Posts</a>
        <a class="sidebar-link" href="profile_requests.php"><span>✅</span> Profile Approvals</a>
        <a class="sidebar-link" href="feedback.php"><span>💬</span> Customer Feedback</a>
        <a class="sidebar-link" href="reports.php"><span>📊</span> Reports</a>
        <a class="sidebar-link" href="archived_reports.php"><span>🗂️</span> Archived Reports</a>
        <a class="sidebar-link" href="archive_report.php"><span>💾</span> Archive Report</a>
        <a class="sidebar-link" href="clear_report.php"><span>🧹</span> Clear Report</a>
        <a class="sidebar-link" href="change_password.php"><span>🔐</span> Change Password</a>
        <a class="sidebar-link" href="reset_test_data.php"><span>♻️</span> Reset Test Data</a>
    </nav>
    <div class="sidebar-footer">
        <a class="sidebar-link" href="../profile.php"><span>👤</span> My Profile</a>
        <a class="sidebar-link logout-link" href="../logout.php"><span>🚪</span> Logout</a>
    </div>
</aside>

<main class="dashboard-main">
<div class="container mt-4">


<!-- HEADER -->


<div style="background-color:#EAAA00">
    <div class="dashboard-top d-flex justify-content-between align-items-center gap-3 flex-wrap">
<div class="dashboard-brand"><img src="../assets/berhan-bank-logo-white.svg" alt="Berhan Bank"><div><h3 class="mb-0"><?= htmlspecialchars(t("admin_dashboard")) ?></h3><small>Bank Queue Management System</small></div></div>
<div class="d-flex align-items-center gap-2 flex-wrap"><div class="admin-notification-wrap">
    <button type="button" class="admin-notification-btn" id="notificationButton" aria-label="Notifications" aria-expanded="false">🔔<?php if($notificationCount>0): ?><span class="admin-notification-badge"><?= $notificationCount>99?'99+':$notificationCount ?></span><?php endif; ?></button>
    <div class="admin-notification-menu" id="notificationMenu" hidden>
        <div class="admin-notification-header"><strong>Notifications</strong><form method="post" id="markNotificationsForm" style="<?= $notificationCount>0?'':'display:none' ?>"><button name="mark_admin_notifications_read" class="btn btn-sm btn-link p-0">Mark all read</button></form></div>
        <?php if(empty($notificationRows)): ?><div class="admin-notification-empty">No notifications yet.</div><?php else: foreach($notificationRows as $nr): ?>
            <a class="admin-notification-item <?= (int)$nr['is_read']===0?'unread':'' ?>" href="<?= htmlspecialchars($nr['link'] ?: '#',ENT_QUOTES) ?>">
                <span class="notification-icon"><?= ($nr['type']==='feedback'?'💬':($nr['type']==='profile_approval'?'👤':'🔔')) ?></span>
                <span><strong><?= htmlspecialchars($nr['title']) ?></strong><small><?= htmlspecialchars($nr['message']) ?></small><em><?= htmlspecialchars($nr['created_at']) ?></em></span>
            </a>
        <?php endforeach; endif; ?>
    </div>
</div><div class="language-switcher" style="display:flex;align-items:center;gap:6px;">
    <span aria-hidden="true">🌐</span>
    <label for="siteLanguage" class="visually-hidden"><?= htmlspecialchars(t('language')) ?></label>
    <select id="siteLanguage" class="form-select form-select-sm" style="width:auto;min-width:125px;"
            onchange="window.location.href=this.value;">
        <option value="?lang=en" <?= $current_language==='en'?'selected':'' ?>>English</option>
        <option value="?lang=om" <?= $current_language==='om'?'selected':'' ?>>Afaan Oromoo</option>
        <option value="?lang=am" <?= $current_language==='am'?'selected':'' ?>>አማርኛ</option>
    </select>
</div><a class="profile-chip" href="../profile.php"><span class="profile-avatar"><?php if($dashPhoto): ?><img src="<?php echo htmlspecialchars($dashPhoto); ?>" alt="Profile"><?php else: echo htmlspecialchars($dashInitial); endif; ?></span><span><small><?= htmlspecialchars(t("my_profile")) ?></small><strong><?php echo htmlspecialchars($dashUser['full_name'] ?? 'Administrator'); ?></strong></span></a><a href="../logout.php" class="btn btn-light"><?= htmlspecialchars(t("logout")) ?></a></div>
</div>

</div>

<hr>



<!-- ADMIN HERO / VISUAL CONTROL CENTER -->
<section class="admin-hero">
    <div class="admin-hero-slides" aria-hidden="true">
        <?php
        $adminHero = mysqli_query($conn, "SELECT image_path,title FROM homepage_slides WHERE status='Active' ORDER BY sort_order ASC, slide_id ASC");
        $heroCount = 0;
        if ($adminHero) { while($hs=mysqli_fetch_assoc($adminHero)){ $heroCount++; ?>
            <div class="admin-hero-slide" style="background-image:url('<?php echo htmlspecialchars((strpos($hs['image_path'],'uploads/')===0 ? '../'.$hs['image_path'] : $hs['image_path']), ENT_QUOTES); ?>');" title="<?php echo htmlspecialchars($hs['title'] ?? ''); ?>"></div>
        <?php }} ?>
        <!-- No default hero image: images appear here only after the administrator adds them. -->
    </div>
    <div class="admin-hero-overlay"></div>
    <div class="admin-hero-content">
        <div class="admin-hero-badge">🛡️ ADMINISTRATOR CONTROL CENTER</div>
        <h1>Berhan Bank Administration</h1>
        <p>Manage customers, queues, employees, services, branches and customer support from one secure workspace.</p>
        <div class="admin-hero-points">
            <span>👥 Employee Management</span>
            <span>👤 Customer Management</span>
            <span>📊 Reports & Monitoring</span>
        </div>
    </div>
    <div class="admin-hero-dots" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
    </div>
</section>

<!-- CONTACT & SOCIAL MEDIA POST -->
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <h4 class="mb-1">📢 Contact & Social Media Post</h4>
                <p class="text-muted mb-0">Adminiin Facebook, Telegram, X/Twitter, LinkedIn, WhatsApp fi Email Contact Us irratti maxxansuuf.</p>
            </div>
            <?php if ($contact_post_saved): ?>
                <span class="badge bg-success">Saved</span>
            <?php endif; ?>
        </div>
        <form method="POST" action="dashboard.php">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label fw-bold">Facebook</label><input type="url" name="social_facebook" class="form-control" value="<?php echo htmlspecialchars($dashboardSocial['facebook']); ?>" placeholder="https://facebook.com/..."></div>
                <div class="col-md-6"><label class="form-label fw-bold">Telegram</label><input type="url" name="social_telegram" class="form-control" value="<?php echo htmlspecialchars($dashboardSocial['telegram']); ?>" placeholder="https://t.me/..."></div>
                <div class="col-md-6"><label class="form-label fw-bold">X / Twitter</label><input type="url" name="social_twitter" class="form-control" value="<?php echo htmlspecialchars($dashboardSocial['twitter']); ?>" placeholder="https://x.com/..."></div>
                <div class="col-md-6"><label class="form-label fw-bold">LinkedIn</label><input type="url" name="social_linkedin" class="form-control" value="<?php echo htmlspecialchars($dashboardSocial['linkedin']); ?>" placeholder="https://linkedin.com/company/..."></div>
                <div class="col-md-6"><label class="form-label fw-bold">WhatsApp</label><input type="url" name="social_whatsapp" class="form-control" value="<?php echo htmlspecialchars($dashboardSocial['whatsapp']); ?>" placeholder="https://wa.me/..."></div>
                <div class="col-md-6"><label class="form-label fw-bold">Email</label><input type="email" name="social_email" class="form-control" value="<?php echo htmlspecialchars($dashboardSocial['email']); ?>" placeholder="support@example.com"></div>
            </div>
            <button type="submit" name="save_contact_post" class="btn btn-primary mt-4">📢 Post / Save Contact Information</button>
        </form>
    </div>
</div>

<!-- STATISTICS -->

<div class="row">


<div class="col-md-3 mb-3">

<div class="card bg-primary text-white shadow">

<div class="card-body">

<h6>
Total Customers (Unique)
</h6>

<h2>

<?php echo $total_customer['total']; ?>

</h2>

</div>

</div>

</div>



<div class="col-md-3 mb-3">

<div class="card bg-info text-white shadow">

<div class="card-body">

<h6>
Today's Registrations
</h6>

<h2>

<?php echo $today_customer['total']; ?>

</h2>

</div>

</div>

</div>



<div class="col-md-3 mb-3">

<div class="card bg-warning text-dark shadow">

<div class="card-body">

<h6>
Waiting
</h6>

<h2>

<?php echo $pending['total']; ?>

</h2>

</div>

</div>

</div>



<div class="col-md-3 mb-3">

<div class="card bg-success text-white shadow">

<div class="card-body">

<h6>
Completed Today
</h6>

<h2>

<?php echo $completed['total']; ?>

</h2>

</div>

</div>

</div>


</div>



<div class="row">


<div class="col-md-3 mb-3">

<div class="card bg-secondary text-white shadow">

<div class="card-body">

<h6>
Assigned
</h6>

<h2>

<?php echo $assigned['total']; ?>

</h2>

</div>

</div>

</div>



<div class="col-md-3 mb-3">

<div class="card bg-danger text-white shadow">

<div class="card-body">

<h6>
Currently Serving
</h6>

<h2>

<?php echo $serving['total']; ?>

</h2>

</div>

</div>

</div>



<div class="col-md-3 mb-3">

<div class="card bg-dark text-white shadow">

<div class="card-body">

<h6>
Active Tellers
</h6>

<h2>

<?php echo $active_tellers['total']; ?>

</h2>

</div>

</div>

</div>



<div class="col-md-3 mb-3">

<div class="card bg-primary text-white shadow">

<div class="card-body">

<h6>
Receptionists
</h6>

<h2>

<?php echo $active_receptionists['total']; ?>

</h2>

</div>

</div>

</div>


</div>



<div class="homepage-post-card shadow-sm">
    <div class="homepage-post-card-icon">🖼️</div>
    <div class="homepage-post-card-content">
        <h4>Welcome to Our Bank — Homepage Posts</h4>
        <p>Add, hide, show, and remove the images/posts that rotate automatically in the Home page “Welcome to Our Bank” section.</p>
        <a href="branding.php" class="btn btn-success">🖼️ Manage Homepage Posts</a>
    </div>
</div>

<br>


<!-- ADMIN MANAGEMENT -->

<div class="card shadow">


<div class="card-header bg-dark text-white">

<h4>
⚙️ System Management
</h4>

</div>


<div class="card-body">


<div class="row">

<div class="col-md-4 mb-3">

<div class="card shadow text-center">

<div class="card-body">

<div style="font-size:40px;">
💾
</div>

<h5>
Archived Reports
</h5>

<p class="text-muted">

View previously saved reports

</p>

<a href="archived_reports.php" class="btn btn-dark">VIEW ARCHIVE</a>
<a href="archive_report.php" class="btn btn-success mt-2">💾 ARCHIVE REPORT</a>
<a href="clear_report.php" class="btn btn-danger mt-2">🧹 CLEAR REPORT</a>

</div>

</div>

</div>

<!-- Employees -->

<div class="col-md-4 mb-3">

<div class="card menu-card shadow text-center">

<div class="card-body">

<div class="menu-icon">
👥
</div>

<h5>
Employee Management
</h5>

<p class="text-muted">

Manage Teller and Receptionist

</p>


<a
href="employees.php"
class="btn btn-primary">

MANAGE EMPLOYEES

</a>

</div>

</div>

</div>



<!-- Add Employee -->

<div class="col-md-4 mb-3">

<div class="card menu-card shadow text-center">

<div class="card-body">

<div class="menu-icon">
➕
</div>

<h5>
Add Employee
</h5>

<p class="text-muted">

Create new Teller or Receptionist

</p>


<a
href="add_employee.php"
class="btn btn-success">

ADD EMPLOYEE

</a>

</div>

</div>

</div>



<!-- Working Hours -->

<div class="col-md-4 mb-3">

<div class="card menu-card shadow text-center">

<div class="card-body">

<div class="menu-icon">🕐</div>

<h5>Working Hours</h5>

<p class="text-muted">Add, edit, activate or deactivate service hours</p>

<a href="working_hours.php" class="btn btn-primary">MANAGE WORKING HOURS</a>

</div>

</div>

</div>


<!-- Reports -->

<div class="col-md-4 mb-3">

<div class="card menu-card shadow text-center">

<div class="card-body">

<div class="menu-icon">
📊
</div>

<h5>
Reports
</h5>

<p class="text-muted">

View daily and system reports

</p>


<a
href="reports.php"
class="btn btn-info">

VIEW REPORTS

</a>

</div>

</div>

</div>



<!-- Branch -->

<div class="col-md-4 mb-3">

<div class="card menu-card shadow text-center">

<div class="card-body">

<div class="menu-icon">
🏦
</div>

<h5>
Branch Management
</h5>

<p class="text-muted">

Manage bank branches

</p>


<a
href="branches.php"
class="btn btn-warning">

MANAGE BRANCHES

</a>


</div>

</div>

</div>



<!-- Services -->

<div class="col-md-4 mb-3">

<div class="card menu-card shadow text-center">

<div class="card-body">

<div class="menu-icon">
🛠️
</div>

<h5>
Service Management
</h5>

<p class="text-muted">

Manage banking services

</p>


<a
href="services.php"
class="btn btn-secondary">

MANAGE SERVICES

</a>

</div>

</div>

</div>



<!-- Education Management -->

<div class="col-md-4 mb-3">

<div class="card menu-card shadow text-center">

<div class="card-body">

<div class="menu-icon">🎓</div>

<h5>Education Management</h5>

<p class="text-muted">Upload videos, PDFs and documents for customer education</p>

<a href="education.php" class="btn btn-success">MANAGE EDUCATION</a>

</div>

</div>

</div>


<!-- Priorities -->

<div class="col-md-4 mb-3">

<div class="card menu-card shadow text-center">

<div class="card-body">

<div class="menu-icon">
⭐
</div>

<h5>
Priority Management
</h5>

<p class="text-muted">

Add, edit, activate, deactivate or remove priorities

</p>

<a
href="priority_management.php"
class="btn btn-warning">

MANAGE PRIORITIES

</a>

</div>

</div>

</div>



<!-- Admin Password -->

<div class="col-md-4 mb-3">

<div class="card menu-card shadow text-center">

<div class="card-body">

<div class="menu-icon">🔐</div>

<h5>My Password</h5>

<p class="text-muted">Change your own admin password securely</p>

<a href="change_password.php" class="btn btn-primary">CHANGE PASSWORD</a>

</div>

</div>

</div>


<!-- Customers -->

<div class="col-md-4 mb-3">

<div class="card menu-card shadow text-center">

<div class="card-body">

<div class="menu-icon">
👤
</div>

<h5>
Customer Management
</h5>

<p class="text-muted">

View registered customers

</p>


<a
href="customers.php"
class="btn btn-dark">

VIEW CUSTOMERS

</a>

<a
href="reset_test_data.php"
class="btn btn-danger ms-2">

🧹 RESET TEST DATA

</a>

</div>

</div>

</div>


</div>

</div>

</div>



<br>



<!-- RECENT ACTIVITY -->

<div class="card shadow">


<div class="card-header bg-dark text-white">

<h4>
📋 Recent Activity
</h4>

</div>


<div class="card-body">


<div class="table-responsive">


<table class="table table-bordered table-hover">


<thead class="table-dark">

<tr>

<th>User</th>

<th>Action</th>

<th>Date</th>
<th>Info</th>

</tr>

</thead>


<tbody>


<?php $activityIndex=0; while($row=mysqli_fetch_assoc($activity)){ $activityIndex++; ?>
<tr class="activity-row <?php echo $activityIndex>5 ? 'd-none extra-activity' : ''; ?>">

<td>

<?php echo htmlspecialchars($row['full_name']); ?>

</td>


<td><?php echo htmlspecialchars($row['action']); ?></td>
<td><?php echo htmlspecialchars($row['created_at']); ?></td>
<td><button type="button" class="btn btn-sm btn-outline-primary activity-more" data-user="<?php echo htmlspecialchars($row['full_name'], ENT_QUOTES); ?>" data-action="<?php echo htmlspecialchars($row['action'], ENT_QUOTES); ?>" data-date="<?php echo htmlspecialchars($row['created_at'], ENT_QUOTES); ?>">More Info</button></td>

</tr>


<?php } ?>


</tbody>


</table>


</div>


</div>


</div>


</div>
<?php if ($activityIndex > 5): ?>
<div class="text-center mt-3"><button type="button" id="activityExpandBtn" class="btn btn-outline-primary">ⓘ More Info — Show Older Activities</button></div>
<?php endif; ?>
</div>

</div>
</main>
</div>
<div class="modal fade" id="activityInfoModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">📋 Activity Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p><strong>User:</strong> <span id="activityUser"></span></p><p><strong>Action:</strong> <span id="activityAction"></span></p><p><strong>Date:</strong> <span id="activityDate"></span></p></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
 const slides=[...document.querySelectorAll('.admin-hero-slide')];
 if(slides.length){ let i=0; slides.forEach(x=>x.style.opacity='0'); slides[0].style.opacity='1'; setInterval(()=>{slides[i].style.opacity='0';i=(i+1)%slides.length;slides[i].style.opacity='1';},3000); }
 const expandBtn=document.getElementById('activityExpandBtn'); if(expandBtn){expandBtn.addEventListener('click',()=>{document.querySelectorAll('.extra-activity').forEach(r=>r.classList.remove('d-none')); expandBtn.textContent='⌃ Show Less'; expandBtn.onclick=()=>{document.querySelectorAll('.extra-activity').forEach(r=>r.classList.add('d-none')); expandBtn.textContent='ⓘ More Info — Show Older Activities';};});}
 document.querySelectorAll('.activity-more').forEach(btn=>btn.addEventListener('click',function(){document.getElementById('activityUser').textContent=this.dataset.user;document.getElementById('activityAction').textContent=this.dataset.action;document.getElementById('activityDate').textContent=this.dataset.date;new bootstrap.Modal(document.getElementById('activityInfoModal')).show();}));
})();
</script>

<footer class="berhan-footer">
  <div class="berhan-footer-inner">
    <div class="berhan-footer-brand"><img src="../assets/berhan-bank-logo-white.svg" alt="Berhan Bank"><div><strong>Berhan Bank</strong><small>Experience Stress Free Banking</small></div></div>
    <div class="berhan-footer-copy">© <?php echo date("Y"); ?> Berhan Bank S.C. · Bank Queue Management System</div>
    <div class="berhan-footer-contact">Customer Support: <strong>8292</strong> · <a href="https://berhanbanksc.com/" target="_blank" rel="noopener">berhanbanksc.com</a></div>
  </div>
</footer>

<script>
(function(){
 const btn=document.getElementById('notificationButton'), menu=document.getElementById('notificationMenu');
 if(!btn||!menu)return;
 const endpoint='notifications_poll.php';
 let lastTopId=<?php echo !empty($notificationRows) ? (int)$notificationRows[0]['notification_id'] : 0; ?>;
 function esc(v){const d=document.createElement('div');d.textContent=v==null?'':String(v);return d.innerHTML;}
 function render(data){
   const badge=btn.querySelector('.admin-notification-badge');
   if(data.count>0){
     if(badge) badge.textContent=data.count>99?'99+':data.count;
     else {const b=document.createElement('span');b.className='admin-notification-badge';b.textContent=data.count>99?'99+':data.count;btn.appendChild(b);}
   } else if(badge) badge.remove();
   const form=document.getElementById('markNotificationsForm'); if(form) form.style.display=data.count>0?'':'none';
   const items=data.notifications||[];
   if(!items.length){menu.innerHTML='<div class="admin-notification-header"><strong>Notifications</strong></div><div class="admin-notification-empty">No notifications yet.</div>';return;}
   let html='<div class="admin-notification-header"><strong>Notifications</strong><form method="post" id="markNotificationsForm"><button name="mark_admin_notifications_read" class="btn btn-sm btn-link p-0">Mark all read</button></form></div>';
   items.forEach(function(n){const icon=n.type==='feedback'?'💬':(n.type==='profile_approval'?'👤':'🔔'); html+='<a class="admin-notification-item '+(!Number(n.is_read)?'unread':'')+'" href="'+esc(n.link||'#')+'"><span class="notification-icon">'+icon+'</span><span><strong>'+esc(n.title)+'</strong><small>'+esc(n.message)+'</small><em>'+esc(n.created_at)+'</em></span></a>';});
   menu.innerHTML=html;
 }
 async function poll(){
   try{const r=await fetch(endpoint+'?t='+Date.now(),{cache:'no-store',credentials:'same-origin'}); if(!r.ok)return; const data=await r.json(); if(!data.ok)return; const top=data.notifications&&data.notifications.length?Number(data.notifications[0].notification_id):0; if(top>lastTopId){lastTopId=top; render(data); if(!menu.hidden){menu.hidden=false;btn.setAttribute('aria-expanded','true');}} else {render(data);} }
   catch(e){}
 }
 btn.addEventListener('click',function(e){e.stopPropagation();const open=!menu.hidden;menu.hidden=open;btn.setAttribute('aria-expanded',String(!open));});
 document.addEventListener('click',function(){menu.hidden=true;btn.setAttribute('aria-expanded','false');});
 menu.addEventListener('click',function(e){e.stopPropagation();});
 setInterval(poll,3000); poll();
})();
</script>
</body>

</html>