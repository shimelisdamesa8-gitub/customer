<?php
require_once __DIR__ . "/../config/language.php";
require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role_id'] !== 1) {
    header("Location: ../login.php");
    exit();
}

/* Ensure the existing priority table is compatible with the application.
   Older databases may already have priority_categories without status/level columns. */
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS priority_categories (
    priority_id INT NOT NULL AUTO_INCREMENT,
    priority_name VARCHAR(100) NOT NULL,
    priority_level INT NOT NULL DEFAULT 1,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (priority_id),
    UNIQUE KEY uq_priority_name (priority_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/* Migrate old installations safely. */
$dbName = mysqli_real_escape_string($conn, $database);
$priorityColumns = [];
$colResult = mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$dbName' AND TABLE_NAME='priority_categories'");
while ($c = mysqli_fetch_assoc($colResult)) { $priorityColumns[$c['COLUMN_NAME']] = true; }

if (!isset($priorityColumns['priority_level'])) {
    mysqli_query($conn, "ALTER TABLE priority_categories ADD COLUMN priority_level INT NOT NULL DEFAULT 1 AFTER priority_name");
}
if (!isset($priorityColumns['status'])) {
    mysqli_query($conn, "ALTER TABLE priority_categories ADD COLUMN status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active' AFTER priority_level");
}
if (!isset($priorityColumns['created_at'])) {
    mysqli_query($conn, "ALTER TABLE priority_categories ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
}
if (!isset($priorityColumns['updated_at'])) {
    mysqli_query($conn, "ALTER TABLE priority_categories ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
}

/* Customer table may predate dynamic priority support. */
$customerColumns = [];
$custResult = mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$dbName' AND TABLE_NAME='customers'");
while ($c = mysqli_fetch_assoc($custResult)) { $customerColumns[$c['COLUMN_NAME']] = true; }
if (!isset($customerColumns['priority_id'])) {
    mysqli_query($conn, "ALTER TABLE customers ADD COLUMN priority_id INT NULL");
}

mysqli_query($conn, "UPDATE priority_categories SET status='Active' WHERE status IS NULL OR TRIM(status)=''");


$defaults = [
    ['VIP',1], ['Elderly',2], ['Special',3], ['Normal',4]
];
foreach ($defaults as $d) {
    $stmt = mysqli_prepare($conn, "INSERT INTO priority_categories (priority_name, priority_level, status)
        SELECT ?, ?, 'Active' FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM priority_categories WHERE LOWER(TRIM(priority_name))=LOWER(TRIM(?)))");
    mysqli_stmt_bind_param($stmt, "sis", $d[0], $d[1], $d[0]);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$message = "";
$message_type = "success";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $priority_id = (int)($_POST['priority_id'] ?? 0);

    if ($action === 'add') {
        $name = trim($_POST['priority_name'] ?? '');
        $level = (int)($_POST['priority_level'] ?? 1);
        if ($name === '' || $level < 1) {
            $message = "Priority name and level are required.";
            $message_type = "danger";
        } else {
            $stmt = mysqli_prepare($conn, "SELECT priority_id FROM priority_categories WHERE LOWER(TRIM(priority_name))=LOWER(TRIM(?)) LIMIT 1");
            mysqli_stmt_bind_param($stmt, "s", $name);
            mysqli_stmt_execute($stmt);
            $exists = mysqli_stmt_get_result($stmt);
            mysqli_stmt_close($stmt);
            if (mysqli_num_rows($exists) > 0) {
                $message = "This priority already exists.";
                $message_type = "warning";
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO priority_categories (priority_name, priority_level, status) VALUES (?, ?, 'Active')");
                mysqli_stmt_bind_param($stmt, "si", $name, $level);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $message = "Priority added successfully.";
            }
        }
    } elseif ($action === 'edit') {
        $name = trim($_POST['priority_name'] ?? '');
        $level = (int)($_POST['priority_level'] ?? 1);
        if ($priority_id <= 0 || $name === '' || $level < 1) {
            $message = "Invalid priority information.";
            $message_type = "danger";
        } else {
            $stmt = mysqli_prepare($conn, "SELECT priority_id FROM priority_categories WHERE priority_id<>? AND LOWER(TRIM(priority_name))=LOWER(TRIM(?)) LIMIT 1");
            mysqli_stmt_bind_param($stmt, "is", $priority_id, $name);
            mysqli_stmt_execute($stmt);
            $exists = mysqli_stmt_get_result($stmt);
            mysqli_stmt_close($stmt);
            if (mysqli_num_rows($exists) > 0) {
                $message = "Another priority already uses that name.";
                $message_type = "warning";
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE priority_categories SET priority_name=?, priority_level=? WHERE priority_id=?");
                mysqli_stmt_bind_param($stmt, "sii", $name, $level, $priority_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $message = "Priority updated successfully.";
            }
        }
    } elseif ($action === 'toggle') {
        $stmt = mysqli_prepare($conn, "UPDATE priority_categories SET status=IF(status='Active','Inactive','Active') WHERE priority_id=?");
        mysqli_stmt_bind_param($stmt, "i", $priority_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $message = "Priority status updated.";
    } elseif ($action === 'remove') {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM customers WHERE priority_id=?");
        mysqli_stmt_bind_param($stmt, "i", $priority_id);
        mysqli_stmt_execute($stmt);
        $used = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ((int)$used['total'] > 0) {
            $message = "This priority is already used by customers, so it cannot be permanently removed. Deactivate it instead.";
            $message_type = "warning";
        } else {
            $stmt = mysqli_prepare($conn, "DELETE FROM priority_categories WHERE priority_id=?");
            mysqli_stmt_bind_param($stmt, "i", $priority_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $message = "Priority removed successfully.";
        }
    }
}

$priorities = mysqli_query($conn, "SELECT p.*,
    (SELECT COUNT(*) FROM customers c WHERE c.priority_id=p.priority_id) AS customer_count
    FROM priority_categories p ORDER BY p.priority_level ASC, p.priority_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="css/priority_management.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Priority Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h2>⭐ Priority Management</h2><p class="text-muted mb-0">Manage customer priorities dynamically</p></div>
    <a href="dashboard.php" class="btn btn-dark">← Back</a>
</div>

<?php if ($message !== ""): ?>
<div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
<div class="card-header bg-success text-white"><strong>➕ Add Priority</strong></div>
<div class="card-body">
<form method="POST" class="row g-2">
<input type="hidden" name="action" value="add">
<div class="col-md-6"><input name="priority_name" class="form-control" placeholder="Priority name (e.g. Pregnant)" required></div>
<div class="col-md-3"><input type="number" name="priority_level" min="1" class="form-control" placeholder="Level" required></div>
<div class="col-md-3"><button class="btn btn-success w-100">ADD</button></div>
</form>
</div></div>

<div class="card shadow-sm">
<div class="card-header bg-dark text-white"><strong>Priority List</strong></div>
<div class="card-body table-responsive">
<table class="table table-bordered table-hover align-middle">
<thead class="table-dark"><tr><th>#</th><th>Priority</th><th>Level</th><th>Status</th><th>Customers Using</th><th>Actions</th></tr></thead>
<tbody>
<?php while ($p = mysqli_fetch_assoc($priorities)): ?>
<tr>
<td><?php echo (int)$p['priority_id']; ?></td>
<td>
<form method="POST" class="row g-1">
<input type="hidden" name="action" value="edit">
<input type="hidden" name="priority_id" value="<?php echo (int)$p['priority_id']; ?>">
<div class="col-md-7"><input name="priority_name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($p['priority_name']); ?>" required></div>
<div class="col-md-3"><input type="number" name="priority_level" min="1" class="form-control form-control-sm" value="<?php echo (int)$p['priority_level']; ?>" required></div>
<div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Edit</button></div>
</form>
</td>
<td><?php echo (int)$p['priority_level']; ?></td>
<td><span class="badge bg-<?php echo $p['status']==='Active'?'success':'secondary'; ?>"><?php echo htmlspecialchars($p['status']); ?></span></td>
<td><?php echo (int)$p['customer_count']; ?></td>
<td class="text-nowrap">
<form method="POST" class="d-inline">
<input type="hidden" name="action" value="toggle">
<input type="hidden" name="priority_id" value="<?php echo (int)$p['priority_id']; ?>">
<button class="btn btn-sm btn-<?php echo $p['status']==='Active'?'warning':'success'; ?>"><?php echo $p['status']==='Active'?'Deactivate':'Activate'; ?></button>
</form>
<form method="POST" class="d-inline" onsubmit="return confirm('Remove this priority?');">
<input type="hidden" name="action" value="remove">
<input type="hidden" name="priority_id" value="<?php echo (int)$p['priority_id']; ?>">
<button class="btn btn-sm btn-danger">Remove</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</tbody></table>
</div></div>
</div>
</body></html>
