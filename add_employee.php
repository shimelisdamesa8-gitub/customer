<?php
require_once __DIR__ . "/../config/language.php";
require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role_id'] !== 1) {
    header("Location: ../login.php");
    exit();
}

// Safe migration for existing installations.
$col = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'email'");
if ($col && mysqli_num_rows($col) === 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN email VARCHAR(190) NULL AFTER username");
}
$phoneCol = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'phone'");
if ($phoneCol && mysqli_num_rows($phoneCol) === 0) { mysqli_query($conn, "ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL AFTER email"); }

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim((string)($_POST['full_name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $role_id = (int)($_POST['role_id'] ?? 0);
    $phoneDigits = preg_replace('/\D+/', '', $phone);

    if ($full_name === '' || $username === '' || $email === '' || $phone === '' || $password === '') {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid employee email address.";
    } elseif (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
        $error = "Please enter a valid employee phone number.";
    } elseif (!in_array($role_id, [2,3], true)) {
        $error = "Invalid employee role.";
    } elseif (strlen($password) < 8) {
        $error = "Initial password must be at least 8 characters.";
    } else {
        $check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username=? OR email=? LIMIT 1");
        mysqli_stmt_bind_param($check, "ss", $username, $email);
        mysqli_stmt_execute($check);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
        mysqli_stmt_close($check);

        if ($exists) {
            $error = "Username or email already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, username, email, phone, password, role_id, status, force_password_change) VALUES (?, ?, ?, ?, ?, ?, 'Active', 0)");
            mysqli_stmt_bind_param($stmt, "sssssi", $full_name, $username, $email, $phone, $hash, $role_id);
            $ok = mysqli_stmt_execute($stmt);
            $new_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            if ($ok) {
                $action = "Created employee $username (phone $phone) with role ID $role_id";
                $log = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, action, created_at) VALUES (?, ?, NOW())");
                if ($log) {
                    mysqli_stmt_bind_param($log, "is", $_SESSION['user_id'], $action);
                    mysqli_stmt_execute($log);
                    mysqli_stmt_close($log);
                }
                $success = "Employee created successfully. Email address saved for future password-reset confirmation.";
            } else {
                $error = "Employee creation failed.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="css/add_employee.css">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Employee</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
<div class="card shadow col-md-7 mx-auto">
<div class="card-header bg-primary text-white"><h4 class="mb-0">➕ Add Bank Employee</h4></div>
<div class="card-body">
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<form method="POST" autocomplete="off">
<label class="fw-bold">Full Name</label><input type="text" name="full_name" class="form-control" required>
<label class="fw-bold mt-3">Username</label><input type="text" name="username" class="form-control" required>
<label class="fw-bold mt-3">Employee Gmail / Email</label><input type="email" name="email" class="form-control" placeholder="employee@gmail.com" required>
<div class="form-text">This email will receive password-reset approval requests and the new temporary 6-digit password.</div><div class="alert alert-info mt-3 mb-0 py-2"><strong>Employee identity:</strong> each employee is uniquely distinguished by their <b>Username</b> and automatic <b>User ID</b>. If you add another Administrator, choose a different username; both can have the Administrator role.</div>
<label class="fw-bold mt-3">Initial Password</label><input type="password" name="password" class="form-control" autocomplete="new-password" minlength="8" required>
<label class="fw-bold mt-3">Phone</label><input type="tel" name="phone" class="form-control" placeholder="09XXXXXXXX / +251..." maxlength="15" required>
<label class="fw-bold mt-3">Position</label>
<select name="role_id" class="form-select" required>
<option value="">Select Position</option><option value="2">Receptionist</option><option value="3">Teller</option>
</select>
<button type="submit" name="add_employee" class="btn btn-success w-100 mt-4">CREATE EMPLOYEE</button>
<a href="employees.php" class="btn btn-secondary w-100 mt-2">⬅ Employee Management</a>
</form>
</div></div></div>
</body></html>
