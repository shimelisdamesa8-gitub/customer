<?php
require_once __DIR__ . "/../config/language.php";
require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/mail.php";

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role_id'] !== 1) { header("Location: ../login.php"); exit(); }

// Safe migrations for existing installations.
$col = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'force_password_change'");
if ($col && mysqli_num_rows($col) === 0) mysqli_query($conn, "ALTER TABLE users ADD COLUMN force_password_change TINYINT(1) NOT NULL DEFAULT 0");
$col = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'email'");
if ($col && mysqli_num_rows($col) === 0) mysqli_query($conn, "ALTER TABLE users ADD COLUMN email VARCHAR(190) NULL AFTER username");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS password_reset_requests (request_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,token_hash CHAR(64) NOT NULL,status ENUM('Pending','Approved','Declined','Expired','Completed') NOT NULL DEFAULT 'Pending',expires_at DATETIME NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,approved_at DATETIME NULL,temp_password_sent_at DATETIME NULL,INDEX idx_prr_token(token_hash),INDEX idx_prr_user_status(user_id,status),CONSTRAINT fk_prr_user FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$user_id=(int)($_GET['id']??0);
$stmt=mysqli_prepare($conn,"SELECT user_id,full_name,username,email,role_id,status FROM users WHERE user_id=? AND role_id IN (2,3) LIMIT 1"); mysqli_stmt_bind_param($stmt,'i',$user_id); mysqli_stmt_execute($stmt); $user=mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
if(!$user) die('Employee not found.');
$error=''; $success=''; $pending=null;

// Only one active request at a time.
$st=mysqli_prepare($conn,"SELECT request_id,status,expires_at,created_at FROM password_reset_requests WHERE user_id=? AND status='Pending' ORDER BY request_id DESC LIMIT 1"); mysqli_stmt_bind_param($st,'i',$user_id); mysqli_stmt_execute($st); $pending=mysqli_fetch_assoc(mysqli_stmt_get_result($st)); mysqli_stmt_close($st);
if($pending && strtotime($pending['expires_at']) < time()) { mysqli_query($conn,"UPDATE password_reset_requests SET status='Expired' WHERE request_id=".(int)$pending['request_id']); $pending=null; }

if($_SERVER['REQUEST_METHOD']==='POST') {
    if(trim((string)($_POST['confirm']??''))!=='RESET') $error='Type RESET exactly in capital letters to start the employee confirmation process.';
    elseif(!filter_var($user['email']??'',FILTER_VALIDATE_EMAIL)) $error='This employee has no valid email address. Edit the employee and add a Gmail/email address first.';
    elseif($pending) $error='A password-reset approval request is already waiting for this employee.';
    else {
        $token=bin2hex(random_bytes(32)); $hash=hash('sha256',$token); $expires=date('Y-m-d H:i:s',time()+600);
        $ins=mysqli_prepare($conn,"INSERT INTO password_reset_requests (user_id,token_hash,status,expires_at) VALUES (?,?,'Pending',?)"); mysqli_stmt_bind_param($ins,'iss',$user_id,$hash,$expires); $ok=mysqli_stmt_execute($ins); $request_id=mysqli_insert_id($conn); mysqli_stmt_close($ins);
        if($ok){
            $yes=app_url('confirm_password_reset.php?token='.urlencode($token).'&decision=yes');
            $no=app_url('confirm_password_reset.php?token='.urlencode($token).'&decision=no');
            $html='<div style="font-family:Arial,sans-serif;max-width:650px;margin:auto;padding:25px;border:1px solid #ddd;border-radius:10px">'
                .'<h2>Password Reset Confirmation</h2><p>Hello <strong>'.htmlspecialchars($user['full_name']).'</strong>,</p>'
                .'<p>The bank administrator has requested to reset your account password. Do you approve this request?</p>'
                .'<p><strong>This request expires in 10 minutes.</strong></p>'
                .'<p><a href="'.htmlspecialchars($yes).'" style="display:inline-block;padding:12px 20px;background:#198754;color:#fff;text-decoration:none;border-radius:6px;margin-right:8px">YES, I APPROVE</a>'
                .'<a href="'.htmlspecialchars($no).'" style="display:inline-block;padding:12px 20px;background:#dc3545;color:#fff;text-decoration:none;border-radius:6px">NO, DECLINE</a></p>'
                .'<p>If you did not expect this request, choose NO and inform the administrator.</p></div>';
            if(send_smtp_mail($user['email'],'Confirm employee password reset',$html)){
                $action="Password reset approval requested for employee ID $user_id"; $log=mysqli_prepare($conn,"INSERT INTO activity_logs (user_id,action,created_at) VALUES (?,?,NOW())"); if($log){mysqli_stmt_bind_param($log,'is',$_SESSION['user_id'],$action);mysqli_stmt_execute($log);mysqli_stmt_close($log);}
                $success='Approval request sent to the employee email. The employee must choose YES before the new 6-digit temporary password is generated and emailed.';
                $pending=['request_id'=>$request_id,'status'=>'Pending','expires_at'=>$expires,'created_at'=>date('Y-m-d H:i:s')];
            } else {
                mysqli_query($conn,"UPDATE password_reset_requests SET status='Expired' WHERE request_id=".(int)$request_id);
                $error='The approval request was created but the email could not be sent. Check config/mail.php SMTP settings and try again.';
            }
        } else $error='Could not create the password-reset request.';
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
    <link rel="stylesheet" href="css/reset_password.css"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset Employee Password</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container mt-5"><div class="card shadow col-lg-7 mx-auto"><div class="card-header bg-warning"><h4 class="mb-0">🔐 Reset Employee Password</h4></div><div class="card-body">
<p>Employee: <strong><?=htmlspecialchars($user['full_name'])?></strong> (<?=htmlspecialchars($user['username'])?>)</p>
<p>Email: <strong><?=htmlspecialchars($user['email']??'Not set')?></strong></p>
<?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif;?>
<?php if($pending):?><div class="alert alert-info"><strong>⏳ Waiting for employee approval.</strong><br>The confirmation email has been sent. It expires at <?=htmlspecialchars($pending['expires_at'])?>. Refresh this page after the employee responds.</div><?php endif;?>
<div class="alert alert-warning"><strong>New workflow:</strong> Admin starts the reset → employee receives a YES/NO email → only after YES is selected, the system generates a random 6-digit temporary password → the temporary password is emailed to the employee → employee logs in and is forced to choose a new private password.</div>
<?php if(!$pending):?><form method="POST" autocomplete="off"><label class="fw-bold">Type RESET to confirm</label><input type="text" name="confirm" class="form-control text-uppercase" required maxlength="5"><button type="submit" class="btn btn-warning w-100 mt-4">SEND RESET APPROVAL REQUEST</button></form><?php endif;?>
<a href="employees.php" class="btn btn-secondary w-100 mt-2">⬅ Employee Management</a>
</div></div></div></body></html>
