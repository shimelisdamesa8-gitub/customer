<?php
require_once __DIR__ . "/../config/language.php";
require_once __DIR__ . "/../config/session.php";
include("../config/database.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1){
    header("Location: ../login.php");
    exit();
}

$report_date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])
    ? $_GET['date'] : date('Y-m-d');

/* Daily report rules:
   - Registrations/Total = queue records created on selected date.
   - Completed = services completed on selected date.
   - Service totals = registrations created on selected date.
   - Teller performance = completions made on selected date.
   - If completed records were cleared after archiving, the archived snapshot is still shown.
*/
function count_query($conn, $sql){
    $r = mysqli_query($conn, $sql);
    if(!$r) return 0;
    $row = mysqli_fetch_assoc($r);
    return (int)($row['total'] ?? 0);
}

$total = count_query($conn, "SELECT COUNT(*) total FROM queue_records WHERE DATE(created_time)='$report_date'");
$pending = count_query($conn, "SELECT COUNT(*) total FROM queue_records WHERE DATE(created_time)='$report_date' AND status='Pending'");
$verified = count_query($conn, "SELECT COUNT(*) total FROM queue_records WHERE DATE(created_time)='$report_date' AND status='Verified'");
$assigned = count_query($conn, "SELECT COUNT(*) total FROM queue_records WHERE DATE(created_time)='$report_date' AND status='Assigned'");
$serving = count_query($conn, "SELECT COUNT(*) total FROM queue_records WHERE DATE(created_time)='$report_date' AND status='Serving'");
$completed_live = count_query($conn, "SELECT COUNT(*) total FROM queue_records WHERE DATE(completed_time)='$report_date' AND status='Completed'");
$skipped = count_query($conn, "SELECT COUNT(*) total FROM queue_records WHERE DATE(created_time)='$report_date' AND status='Skipped'");
$cancelled = count_query($conn, "SELECT COUNT(*) total FROM queue_records WHERE DATE(created_time)='$report_date' AND status='Cancelled'");

$archive = null;
$ar = mysqli_query($conn, "SELECT * FROM report_archive WHERE report_date='$report_date' LIMIT 1");
if($ar) $archive = mysqli_fetch_assoc($ar);

/* Use live completion count while records exist; after Clear, show the archived completion count. */
$completed_display = $completed_live;
if($completed_live === 0 && $archive && (int)$archive['completed_customers'] > 0){
    $completed_display = (int)$archive['completed_customers'];
}

$services = mysqli_query($conn, "
    SELECT c.service_type, COUNT(*) total
    FROM queue_records q
    INNER JOIN customers c ON q.customer_id=c.customer_id
    WHERE DATE(q.created_time)='$report_date'
    GROUP BY c.service_type
    ORDER BY total DESC, c.service_type ASC
");

$tellers = mysqli_query($conn, "
    SELECT u.full_name, COUNT(q.queue_id) total_completed
    FROM users u
    LEFT JOIN queue_records q
      ON u.user_id=q.teller_id
     AND q.status='Completed'
     AND DATE(q.completed_time)='$report_date'
    WHERE u.role_id IN (1,3)
    GROUP BY u.user_id, u.full_name
    ORDER BY total_completed DESC, u.full_name ASC
");

$customers = mysqli_query($conn, "
    SELECT q.queue_id, q.queue_number, c.full_name, c.phone, c.service_type,
           q.status, q.created_time, q.assigned_time, q.start_time, q.completed_time,
           u.full_name AS teller_name,
           p.priority_name, p.priority_level
    FROM queue_records q
    INNER JOIN customers c ON q.customer_id=c.customer_id
    LEFT JOIN users u ON q.teller_id=u.user_id
    LEFT JOIN priority_categories p ON c.priority_id=p.priority_id
    WHERE DATE(q.created_time)='$report_date'
    ORDER BY
      CASE WHEN p.priority_level IS NULL THEN 999999 ELSE p.priority_level END ASC,
      q.created_time ASC
");

$report_error = '';
if(!$services || !$tellers || !$customers){
    $report_error = mysqli_error($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="css/reports.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Reports</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4 mb-5">
<div class="d-flex justify-content-between align-items-center no-print">
  <div><h2>📊 Admin Reports</h2><p class="text-muted mb-0">Bank Queue Management System</p></div>
  <div>
    <a href="dashboard.php" class="btn btn-secondary">⬅ Dashboard</a>
    <button onclick="window.print()" class="btn btn-primary">🖨 Print / Save</button>
  </div>
</div>
<hr>

<div class="card shadow mb-4 no-print">
<div class="card-body">
<form method="GET" class="row g-3 align-items-end">
<div class="col-md-5">
<label class="fw-bold">Select Report Date</label>
<input type="date" name="date" class="form-control" value="<?=htmlspecialchars($report_date)?>" required>
</div>
<div class="col-md-3"><button class="btn btn-success w-100" type="submit">VIEW REPORT</button></div>
</form>
</div></div>

<?php if($report_error): ?>
<div class="alert alert-danger no-print"><strong>Report error:</strong> <?=htmlspecialchars($report_error)?></div>
<?php endif; ?>

<div class="text-center mb-4">
<h3>Bank Queue Management System</h3>
<h5>Daily Report</h5>
<p>Date: <strong><?=htmlspecialchars($report_date)?></strong></p>
</div>

<?php if($archive): ?>
<div class="alert alert-info">
<strong>📦 Archived snapshot available.</strong>
This date was archived on <?=htmlspecialchars($archive['archived_at'])?>.
If completed records were later cleared, the archived completed total remains available below.
</div>
<?php endif; ?>

<div class="row g-3">
<?php
$stats = [
 ['Total Registrations',$total,'primary'],
 ['Pending',$pending,'warning'],
 ['Verified',$verified,'secondary'],
 ['Assigned',$assigned,'info'],
 ['Serving',$serving,'dark'],
 ['Completed',$completed_display,'success'],
 ['Skipped',$skipped,'warning'],
 ['Cancelled',$cancelled,'danger']
];
foreach($stats as $st):
?>
<div class="col-6 col-md-3">
<div class="card shadow stat-card bg-<?=$st[2]?> <?=in_array($st[2],['warning','info'])?'text-dark':'text-white'?>">
<div class="card-body"><h6><?=htmlspecialchars($st[0])?></h6><h2><?=$st[1]?></h2></div>
</div>
</div>
<?php endforeach; ?>
</div>

<?php if($archive): ?>
<div class="card shadow my-4">
<div class="card-header bg-dark text-white"><h4 class="mb-0">📦 Archived Summary</h4></div>
<div class="card-body">
<div class="row text-center">
<div class="col-md-3"><strong>Total</strong><div class="fs-3"><?=$archive['total_customers']?></div></div>
<div class="col-md-3"><strong>Pending</strong><div class="fs-3"><?=$archive['pending_customers']?></div></div>
<div class="col-md-3"><strong>Assigned</strong><div class="fs-3"><?=$archive['assigned_customers']?></div></div>
<div class="col-md-3"><strong>Serving</strong><div class="fs-3"><?=$archive['serving_customers']?></div></div>
<div class="col-md-3 mt-3"><strong>Completed</strong><div class="fs-3 text-success"><?=$archive['completed_customers']?></div></div>
</div>
</div></div>
<?php endif; ?>

<div class="card shadow mb-4">
<div class="card-header bg-dark text-white"><h4>🛠️ Service Report</h4></div>
<div class="card-body table-responsive">
<table class="table table-bordered table-striped">
<thead class="table-dark"><tr><th>Service</th><th>Registrations</th></tr></thead>
<tbody>
<?php if($services && mysqli_num_rows($services)): while($row=mysqli_fetch_assoc($services)): ?>
<tr><td><?=htmlspecialchars($row['service_type'])?></td><td><?=$row['total']?></td></tr>
<?php endwhile; else: ?><tr><td colspan="2" class="text-center">No registrations for this date.</td></tr><?php endif; ?>
</tbody></table>
</div></div>

<div class="card shadow mb-4">
<div class="card-header bg-primary text-white"><h4>👨‍💼 Teller Performance</h4></div>
<div class="card-body table-responsive">
<table class="table table-bordered table-striped">
<thead class="table-dark"><tr><th>Teller</th><th>Completed Services</th></tr></thead>
<tbody>
<?php if($tellers && mysqli_num_rows($tellers)): while($row=mysqli_fetch_assoc($tellers)): ?>
<tr><td><?=htmlspecialchars($row['full_name'])?></td><td><?=$row['total_completed']?></td></tr>
<?php endwhile; else: ?><tr><td colspan="2" class="text-center">No teller records found.</td></tr><?php endif; ?>
</tbody></table>
</div></div>

<div class="card shadow mb-4">
<div class="card-header bg-success text-white"><h4>👥 Customer / Queue Details</h4></div>
<div class="card-body table-responsive">
<table class="table table-bordered table-striped align-middle">
<thead class="table-dark">
<tr><th>Queue</th><th>Customer</th><th>Phone</th><th>Priority</th><th>Service</th><th>Teller</th><th>Status</th><th>Registered</th><th>Completed</th></tr>
</thead>
<tbody>
<?php if($customers && mysqli_num_rows($customers)): while($row=mysqli_fetch_assoc($customers)): ?>
<tr>
<td><?=htmlspecialchars($row['queue_number'])?></td>
<td><?=htmlspecialchars($row['full_name'])?></td>
<td><?=htmlspecialchars($row['phone'] ?? '-')?></td>
<td><?=htmlspecialchars($row['priority_name'] ?? '-')?><?=isset($row['priority_level'])?' ('.$row['priority_level'].')':''?></td>
<td><?=htmlspecialchars($row['service_type'])?></td>
<td><?=htmlspecialchars($row['teller_name'] ?? '-')?></td>
<td><?=htmlspecialchars($row['status'])?></td>
<td><?=htmlspecialchars($row['created_time'])?></td>
<td><?=htmlspecialchars($row['completed_time'] ?? '-')?></td>
</tr>
<?php endwhile; else: ?><tr><td colspan="9" class="text-center">No customer records for this date.</td></tr><?php endif; ?>
</tbody>
</table>
</div></div>

<div class="d-flex gap-2 no-print mb-4">
<a class="btn btn-success" href="archive_report.php?date=<?=urlencode($report_date)?>">💾 Archive This Report</a>
<a class="btn btn-dark" href="archived_reports.php">📚 View Archived Reports</a>
<a class="btn btn-danger" href="clear_report.php?date=<?=urlencode($report_date)?>">🧹 Clear Completed Records</a>
</div>
</div>
</body>
</html>
