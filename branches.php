<?php
require_once __DIR__ . "/../config/language.php";
require_once __DIR__ . "/../config/session.php";
include("../config/database.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1){
    header("Location: ../login.php");
    exit();
}

$error = "";
$success = "";

/*
 * This application is a SINGLE-BRANCH system.
 * Older databases may not have phone/status columns, so add them safely.
 */
function column_exists($conn, $table, $column){
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $r = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $r && mysqli_num_rows($r) > 0;
}

// Make the existing branches table compatible with this page.
$branches_table = mysqli_query($conn, "SHOW TABLES LIKE 'branches'");
if(!$branches_table || mysqli_num_rows($branches_table) === 0){
    $error = "The branches table does not exist. Please create the branches table first.";
} else {
    if(!column_exists($conn, 'branches', 'phone')){
        if(!mysqli_query($conn, "ALTER TABLE branches ADD COLUMN phone VARCHAR(30) NOT NULL DEFAULT ''")){
            $error = "Could not add the phone field: " . mysqli_error($conn);
        }
    }
    if(!$error && !column_exists($conn, 'branches', 'status')){
        if(!mysqli_query($conn, "ALTER TABLE branches ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Active'")){
            $error = "Could not add the status field: " . mysqli_error($conn);
        }
    }
    if(!$error && !column_exists($conn, 'branches', 'location')){
        if(!mysqli_query($conn, "ALTER TABLE branches ADD COLUMN location VARCHAR(150) NOT NULL DEFAULT ''")){
            $error = "Could not add the location field: " . mysqli_error($conn);
        }
    }
}

/* Edit the single branch. */
if(!$error && isset($_POST['update_branch'])){
    $id = intval($_POST['branch_id'] ?? 0);
    $name = trim($_POST['branch_name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if($id <= 0 || $name === ""){
        $error = "Branch name is required.";
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE branches SET branch_name=?, location=?, phone=? WHERE branch_id=?");
        mysqli_stmt_bind_param($stmt, "sssi", $name, $location, $phone, $id);
        if(mysqli_stmt_execute($stmt)){
            $success = "Branch updated successfully.";
        } else {
            $error = mysqli_errno($conn) == 1062 ? "This branch name already exists." : mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

/* Activate/deactivate the single branch. */
if(!$error && isset($_GET['toggle'])){
    $id = intval($_GET['toggle']);
    $stmt = mysqli_prepare($conn, "UPDATE branches SET status=IF(status='Active','Inactive','Active') WHERE branch_id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header("Location: branches.php");
    exit();
}

/* Remove the branch. This may leave the system with zero branches; it will NOT recreate it. */
if(!$error && isset($_GET['remove'])){
    $id = intval($_GET['remove']);
    $stmt = mysqli_prepare($conn, "DELETE FROM branches WHERE branch_id=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    if(mysqli_stmt_execute($stmt)){
        $success = "Branch removed successfully.";
    } else {
        $error = "Branch could not be removed. It may be in use by other records.";
    }
    mysqli_stmt_close($stmt);
}

/* Display only one branch. Extra old branches are never shown. */
$branches = (!$error) ? mysqli_query($conn, "SELECT branch_id, branch_name, location, phone, status FROM branches ORDER BY branch_id ASC LIMIT 1") : false;
$db_error = $branches ? "" : (!$error ? mysqli_error($conn) : $error);
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="css/branches.css">
<title>Branch Management</title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2>🏦 Branch Management</h2>
            <p class="text-muted mb-0">Single branch system — only one branch can be managed.</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">⬅ Dashboard</a>
    </div>
    <hr>

    <?php if($db_error): ?><div class="alert alert-danger"><b>Database error:</b> <?php echo htmlspecialchars($db_error); ?></div><?php endif; ?>
    <?php if($error && !$db_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="card shadow">
        <div class="card-header bg-dark text-white"><h5 class="mb-0">📋 Single Branch</h5></div>
        <div class="card-body">
            <?php if(!$branches): ?>
                <div class="alert alert-danger">Branches table is not available or could not be read.</div>
            <?php elseif(mysqli_num_rows($branches) == 0): ?>
                <div class="alert alert-info">No branch is currently registered. Because this is a single-branch system, add a branch from the database/setup only when needed.</div>
            <?php else: ?>
                <?php $row = mysqli_fetch_assoc($branches); ?>
                <?php $phone = $row['phone'] ?? ''; $status = $row['status'] ?? 'Active'; $location = $row['location'] ?? ''; ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-dark"><tr><th>#</th><th>Branch</th><th>Location</th><th>Phone</th><th>Status</th><th style="width:220px">Actions</th></tr></thead>
                        <tbody><tr>
                            <td><?php echo (int)$row['branch_id']; ?></td>
                            <td><b><?php echo htmlspecialchars($row['branch_name']); ?></b></td>
                            <td><?php echo htmlspecialchars($location); ?></td>
                            <td><?php echo htmlspecialchars($phone); ?></td>
                            <td><span class="badge <?php echo $status==='Active'?'bg-success':'bg-danger'; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                            <td>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editBranch">✏️ Edit</button>
                                <a class="btn btn-warning btn-sm" href="branches.php?toggle=<?php echo (int)$row['branch_id']; ?>" onclick="return confirm('Change branch status?')"><?php echo $status==='Active'?'Deactivate':'Activate'; ?></a>
                                <a class="btn btn-danger btn-sm" href="branches.php?remove=<?php echo (int)$row['branch_id']; ?>" onclick="return confirm('Are you sure you want to remove this branch?')">🗑️ Remove</a>
                            </td>
                        </tr></tbody>
                    </table>
                </div>

                <div class="modal fade" id="editBranch" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">✏️ Edit Branch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <form method="POST"><div class="modal-body">
                        <input type="hidden" name="branch_id" value="<?php echo (int)$row['branch_id']; ?>">
                        <label class="fw-bold">Branch Name</label><input class="form-control mb-3" name="branch_name" value="<?php echo htmlspecialchars($row['branch_name']); ?>" required>
                        <label class="fw-bold">Location</label><input class="form-control mb-3" name="location" value="<?php echo htmlspecialchars($location); ?>">
                        <label class="fw-bold">Phone</label><input class="form-control" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                    </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" name="update_branch" class="btn btn-primary">UPDATE</button></div></form>
                </div></div></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
