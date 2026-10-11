<?php
require_once __DIR__ . "/../config/language.php";
require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role_id'] !== 1) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
        $error = "Please fill in all password fields.";
    } elseif ($new !== $confirm) {
        $error = "New password and confirmation do not match.";
    } elseif (strlen($new) < 8) {
        $error = "New password must be at least 8 characters.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE user_id=? AND role_id=1 LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user || !password_verify($current, $user['password'])) {
            $error = "Current password is incorrect.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET password=? WHERE user_id=? AND role_id=1");
            mysqli_stmt_bind_param($stmt, "si", $hash, $user_id);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);

                $action = "Admin changed own password";
                $log = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, action, created_at) VALUES (?, ?, NOW())");
                if ($log) {
                    mysqli_stmt_bind_param($log, "is", $user_id, $action);
                    mysqli_stmt_execute($log);
                    mysqli_stmt_close($log);
                }

                // Rotate the session identifier after a sensitive credential change.
                session_regenerate_id(true);
                $success = "Your password was changed successfully.";
            } else {
                mysqli_stmt_close($stmt);
                $error = "Password change failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="css/change_password.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Change Admin Password</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
  <div class="card shadow col-lg-6 mx-auto">
    <div class="card-header bg-dark text-white">
      <h4 class="mb-0">🔐 Change My Password</h4>
    </div>
    <div class="card-body">
      <?php if ($error !== ""): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if ($success !== ""): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <label class="form-label fw-bold">Current Password</label>
        <input type="password" name="current_password" class="form-control" autocomplete="current-password" required>

        <label class="form-label fw-bold mt-3">New Password</label>
        <input type="password" name="new_password" class="form-control" autocomplete="new-password" minlength="8" required>

        <label class="form-label fw-bold mt-3">Confirm New Password</label>
        <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" minlength="8" required>

        <button type="submit" class="btn btn-primary w-100 mt-4">CHANGE PASSWORD</button>
        <a href="dashboard.php" class="btn btn-secondary w-100 mt-2">⬅ Back to Dashboard</a>
      </form>
    </div>
  </div>
</div>
</body>
</html>
