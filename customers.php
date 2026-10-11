<?php
require_once __DIR__ . "/../config/language.php";
require_once __DIR__ . "/../config/session.php";
include("../config/database.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1){
    header("Location: ../login.php");
    exit();
}

$search = trim($_GET['search'] ?? '');
$where = '';
if($search !== ''){
    $safe = mysqli_real_escape_string($conn, $search);
    $where = "WHERE c.full_name LIKE '%$safe%'
              OR c.account_number LIKE '%$safe%'
              OR c.phone LIKE '%$safe%'
              OR c.service_type LIKE '%$safe%'";
}

$sql = "SELECT
            c.customer_id, c.full_name, c.account_number, c.phone,
            c.amount, c.branch, c.service_type, c.priority_id,
            q.queue_number, q.status, q.created_time
        FROM customers c
        LEFT JOIN queue_records q ON q.customer_id = c.customer_id
        $where
        ORDER BY c.customer_id DESC";
$customers = mysqli_query($conn, $sql);
$db_error = $customers ? '' : mysqli_error($conn);
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="css/customers.css">
<title>Customer Management</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4 mb-5">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
<div><h2>👤 Customer Management</h2><p class="text-muted mb-0">View and search registered customers and queue information.</p></div>
<a href="dashboard.php" class="btn btn-secondary">⬅ Dashboard</a>
</div>
<hr>

<div class="card shadow mb-4">
<div class="card-body">
<form method="GET" class="row g-2">
<div class="col-md-10">
<input type="text" name="search" class="form-control"
       value="<?php echo htmlspecialchars($search); ?>"
       placeholder="Search by name, account number, phone or service">
</div>
<div class="col-md-2 d-grid">
<button class="btn btn-primary">🔎 Search</button>
</div>
</form>
</div>
</div>

<?php if($db_error): ?>
<div class="alert alert-danger">
<strong>Database error:</strong> <?php echo htmlspecialchars($db_error); ?>
</div>
<?php endif; ?>

<div class="card shadow">
<div class="card-header bg-dark text-white"><h4 class="mb-0">📋 Registered Customers</h4></div>
<div class="card-body">
<div class="table-responsive">
<table class="table table-bordered table-hover">
<thead class="table-dark">
<tr>
<th>#</th><th>Name</th><th>Account</th><th>Phone</th><th>Service</th>
<th>Priority</th><th>Queue</th><th>Status</th><th>Registered</th>
</tr>
</thead>
<tbody>
<?php if($customers && mysqli_num_rows($customers)>0): ?>
<?php while($row=mysqli_fetch_assoc($customers)): ?>
<tr>
<td><?php echo (int)$row['customer_id']; ?></td>
<td><?php echo htmlspecialchars($row['full_name']); ?></td>
<td><?php echo htmlspecialchars($row['account_number']); ?></td>
<td><?php echo htmlspecialchars($row['phone']); ?></td>
<td><?php echo htmlspecialchars($row['service_type']); ?></td>
<td>
<?php
$priority_lookup = mysqli_query($conn, "SELECT priority_id, priority_name FROM priority_categories");
$priority_names = [];
while ($priority_lookup && $pr = mysqli_fetch_assoc($priority_lookup)) {
    $priority_names[(int)$pr['priority_id']] = $pr['priority_name'];
}
echo htmlspecialchars($priority_names[(int)$row['priority_id']] ?? 'Unknown');
?>
</td>
<td><strong><?php echo htmlspecialchars($row['queue_number'] ?? '-'); ?></strong></td>
<td>
<?php
$status=$row['status'] ?? '-';
$cls='secondary';
if($status==='Pending' || $status==='Current' || $status==='Verified') $cls='warning';
elseif($status==='Assigned' || $status==='Serving') $cls='primary';
elseif($status==='Completed') $cls='success';
elseif($status==='Skipped' || $status==='Cancelled') $cls='danger';
?>
<span class="badge bg-<?php echo $cls; ?>"><?php echo htmlspecialchars($status); ?></span>
</td>
<td><?php echo htmlspecialchars($row['created_time'] ?? '-'); ?></td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="9" class="text-center text-muted">No customers found.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</body>
</html>
