<?php
require_once __DIR__ . "/../config/language.php";
require_once __DIR__ . "/../config/session.php";
include("../config/database.php");

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role_id'] !== 1) {
    header("Location: ../login.php");
    exit();
}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS working_hours (
    working_hour_id INT AUTO_INCREMENT PRIMARY KEY,
    day_name VARCHAR(20) NOT NULL,
    open_time TIME NULL,
    close_time TIME NULL,
    status ENUM('Active','Inactive','Removed') NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_working_day (day_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$check = mysqli_query($conn, "SELECT COUNT(*) AS total FROM working_hours WHERE status <> 'Removed'");
$count = $check ? (int)mysqli_fetch_assoc($check)['total'] : 0;
if ($count === 0) {
    $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO working_hours (day_name, open_time, close_time, status) VALUES (?, ?, ?, ?)");
    foreach ($days as $day) {
        $open = in_array($day, ['Monday','Tuesday','Wednesday','Thursday','Friday'], true) ? '08:00:00' : null;
        $close = in_array($day, ['Monday','Tuesday','Wednesday','Thursday','Friday'], true) ? '17:00:00' : null;
        $status = $open !== null ? 'Active' : 'Inactive';
        mysqli_stmt_bind_param($stmt, 'ssss', $day, $open, $close, $status);
        mysqli_stmt_execute($stmt);
    }
    mysqli_stmt_close($stmt);
}

$error = '';
$success = '';

if (isset($_POST['save_hours'])) {
    $id = (int)($_POST['working_hour_id'] ?? 0);
    $day = trim($_POST['day_name'] ?? '');
    $open = trim($_POST['open_time'] ?? '');
    $close = trim($_POST['close_time'] ?? '');
    $status = ($_POST['status'] ?? 'Active') === 'Active' ? 'Active' : 'Inactive';

    if (!in_array($day, $days, true)) {
        $error = 'Invalid day selected.';
    } elseif ($open === '' || $close === '') {
        $error = 'Opening and closing time are required.';
    } elseif ($open >= $close) {
        $error = 'Closing time must be later than opening time.';
    } else {
        $dup = mysqli_prepare($conn, "SELECT working_hour_id FROM working_hours WHERE day_name=? AND working_hour_id<>? AND status<>'Removed' LIMIT 1");
        mysqli_stmt_bind_param($dup, 'si', $day, $id);
        mysqli_stmt_execute($dup);
        $dupRes = mysqli_stmt_get_result($dup);
        $exists = mysqli_num_rows($dupRes) > 0;
        mysqli_stmt_close($dup);

        if ($exists) {
            $error = 'Working hours for this day already exist. Edit the existing row instead.';
        } elseif ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE working_hours SET day_name=?, open_time=?, close_time=?, status=? WHERE working_hour_id=? AND status<>'Removed'");
            mysqli_stmt_bind_param($stmt, 'ssssi', $day, $open, $close, $status, $id);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $success = $ok ? 'Working hours updated successfully.' : 'Update failed.';
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO working_hours (day_name, open_time, close_time, status) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssss', $day, $open, $close, $status);
            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $success = $ok ? 'Working hours added successfully.' : 'Add failed.';
        }
    }
}

if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    if ($action === 'toggle') {
        $stmt = mysqli_prepare($conn, "UPDATE working_hours SET status=IF(status='Active','Inactive','Active') WHERE working_hour_id=? AND status<>'Removed'");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } elseif ($action === 'remove') {
        $stmt = mysqli_prepare($conn, "UPDATE working_hours SET status='Removed' WHERE working_hour_id=?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header('Location: working_hours.php');
    exit();
}

$edit = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM working_hours WHERE working_hour_id=? AND status<>'Removed'");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $edit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

$hours = mysqli_query($conn, "SELECT * FROM working_hours WHERE status<>'Removed' ORDER BY FIELD(day_name,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
?>
<!doctype html>
<html lang="en">
<head>
    <link rel="stylesheet" href="css/working_hours.css">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Working Hours Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-3">
<h2>🕐 Working Hours Management</h2>
<a href="dashboard.php" class="btn btn-secondary">⬅ Dashboard</a>
</div>
<?php if($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if($success): ?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif; ?>
<div class="row g-4">
<div class="col-lg-5">
<div class="card shadow-sm"><div class="card-header bg-primary text-white"><strong><?= $edit ? '✏️ Edit Working Hours' : '➕ Add Working Hours' ?></strong></div><div class="card-body">
<form method="post">
<input type="hidden" name="working_hour_id" value="<?=htmlspecialchars($edit['working_hour_id'] ?? 0)?>">
<label class="fw-bold">Day</label>
<select name="day_name" class="form-select mb-3" required>
<option value="">Select Day</option>
<?php foreach($days as $day): ?><option value="<?=$day?>" <?=($edit['day_name'] ?? '')===$day?'selected':''?>><?=$day?></option><?php endforeach; ?>
</select>
<label class="fw-bold">Opening Time</label>
<input type="time" name="open_time" class="form-control mb-3" value="<?=htmlspecialchars(substr($edit['open_time'] ?? '08:00',0,5))?>" required>
<label class="fw-bold">Closing Time</label>
<input type="time" name="close_time" class="form-control mb-3" value="<?=htmlspecialchars(substr($edit['close_time'] ?? '17:00',0,5))?>" required>
<label class="fw-bold">Status</label>
<select name="status" class="form-select mb-3"><option value="Active" <?=($edit['status'] ?? 'Active')==='Active'?'selected':''?>>Active</option><option value="Inactive" <?=($edit['status'] ?? '')==='Inactive'?'selected':''?>>Inactive</option></select>
<button name="save_hours" class="btn btn-success w-100"><?= $edit ? '💾 Update' : '➕ Add' ?></button>
<?php if($edit): ?><a href="working_hours.php" class="btn btn-outline-secondary w-100 mt-2">Cancel Edit</a><?php endif; ?>
</form></div></div>
</div>
<div class="col-lg-7">
<div class="card shadow-sm"><div class="card-header bg-dark text-white"><strong>Configured Working Hours</strong></div><div class="card-body table-responsive">
<table class="table table-bordered align-middle"><thead><tr><th>Day</th><th>Hours</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php while($row=mysqli_fetch_assoc($hours)): ?><tr>
<td><?=htmlspecialchars($row['day_name'])?></td>
<td><?=htmlspecialchars(date('g:i A', strtotime($row['open_time'])))?> – <?=htmlspecialchars(date('g:i A', strtotime($row['close_time'])))?></td>
<td><?php if($row['status']==='Active'): ?><span class="badge bg-success">Active</span><?php else: ?><span class="badge bg-secondary">Inactive</span><?php endif; ?></td>
<td class="text-nowrap"><a class="btn btn-primary btn-sm" href="working_hours.php?edit=<?=$row['working_hour_id']?>">✏️ Edit</a>
<a class="btn btn-warning btn-sm" href="working_hours.php?action=toggle&id=<?=$row['working_hour_id']?>" onclick="return confirm('Change working-hour status?');"><?= $row['status']==='Active'?'Deactivate':'Activate' ?></a>
<a class="btn btn-danger btn-sm" href="working_hours.php?action=remove&id=<?=$row['working_hour_id']?>" onclick="return confirm('Remove this working-hour entry?');">🗑️ Remove</a></td>
</tr><?php endwhile; ?>
</tbody></table></div></div>
</div></div>
</div></body></html>
