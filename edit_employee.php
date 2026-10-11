<?php
require_once __DIR__ . "/../config/language.php";
require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role_id'] !== 1) {
    header("Location: ../login.php"); exit();
}
$col = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'email'");
if ($col && mysqli_num_rows($col) === 0) mysqli_query($conn, "ALTER TABLE users ADD COLUMN email VARCHAR(190) NULL AFTER username");
$user_id = (int)($_GET['id'] ?? 0);
$stmt = mysqli_prepare($conn, "SELECT user_id, full_name, username, email, role_id FROM users WHERE user_id=? AND role_id IN (2,3) LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id); mysqli_stmt_execute($stmt); $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
if (!$user) die("Employee not found.");
$error=''; $success='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $full_name=trim((string)($_POST['full_name']??'')); $username=trim((string)($_POST['username']??'')); $email=trim((string)($_POST['email']??'')); $role_id=(int)($_POST['role_id']??0);
    if($full_name===''||$username===''||$email==='') $error='Full name, username and email are required.';
    elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Please enter a valid email address.';
    elseif(!in_array($role_id,[2,3],true)) $error='Invalid position.';
    else {
        $check=mysqli_prepare($conn,"SELECT user_id FROM users WHERE (username=? OR email=?) AND user_id<>? LIMIT 1"); mysqli_stmt_bind_param($check,"ssi",$username,$email,$user_id); mysqli_stmt_execute($check); $exists=mysqli_fetch_assoc(mysqli_stmt_get_result($check)); mysqli_stmt_close($check);
        if($exists) $error='Username or email already exists.';
        else {
            $up=mysqli_prepare($conn,"UPDATE users SET full_name=?, username=?, email=?, role_id=? WHERE user_id=?"); mysqli_stmt_bind_param($up,"sssii",$full_name,$username,$email,$role_id,$user_id); $ok=mysqli_stmt_execute($up); mysqli_stmt_close($up);
            if($ok){$action="Updated employee ID $user_id"; $log=mysqli_prepare($conn,"INSERT INTO activity_logs (user_id,action,created_at) VALUES (?,?,NOW())"); if($log){mysqli_stmt_bind_param($log,"is",$_SESSION['user_id'],$action);mysqli_stmt_execute($log);mysqli_stmt_close($log);} $success='Employee updated successfully.'; $stmt=mysqli_prepare($conn,"SELECT user_id,full_name,username,email,role_id FROM users WHERE user_id=?");mysqli_stmt_bind_param($stmt,'i',$user_id);mysqli_stmt_execute($stmt);$user=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));mysqli_stmt_close($stmt);} else $error='Update failed.';
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
    <link rel="stylesheet" href="css/edit_employee.css"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit Employee</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container mt-5"><div class="card shadow col-md-7 mx-auto"><div class="card-header bg-primary text-white"><h4>✏️ Edit Employee</h4></div><div class="card-body">
<?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif;?>
<form method="POST" autocomplete="off"><label class="fw-bold">Full Name</label><input name="full_name" class="form-control" value="<?=htmlspecialchars($user['full_name'])?>" required>
<label class="fw-bold mt-3">Username</label><input name="username" class="form-control" value="<?=htmlspecialchars($user['username'])?>" required>
<label class="fw-bold mt-3">Employee Gmail / Email</label><input type="email" name="email" class="form-control" value="<?=htmlspecialchars($user['email']??'')?>" required>
<label class="fw-bold mt-3">Position</label><select name="role_id" class="form-select" required><option value="2" <?=$user['role_id']==2?'selected':''?>>Receptionist</option><option value="3" <?=$user['role_id']==3?'selected':''?>>Teller</option></select>
<button type="submit" name="update_employee" class="btn btn-primary w-100 mt-4">UPDATE EMPLOYEE</button><a href="employees.php" class="btn btn-secondary w-100 mt-2">⬅ Employee Management</a></form>
</div></div></div></body></html>
