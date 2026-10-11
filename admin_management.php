<?php
require_once __DIR__ . "/../config/language.php";
require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role_id'] !== 1) {
    header("Location: ../login.php"); exit();
}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS admin_role_audit (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NOT NULL,
    target_user_id INT NOT NULL,
    old_role_id INT NOT NULL,
    new_role_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_role_audit_actor(actor_user_id),
    INDEX idx_admin_role_audit_target(target_user_id),
    INDEX idx_admin_role_audit_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/*
 * Admin handover rule:
 * - There is normally exactly one active Administrator.
 * - The current Administrator may promote exactly one employee.
 * - That creates a handover: the NEW Administrator is the only person
 *   allowed to demote the OLD Administrator.
 * - The OLD Administrator cannot demote the NEW Administrator or promote
 *   another employee while the handover is pending.
 * - After the new Administrator demotes the old one, exactly one admin remains.
 */
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS admin_handover (
    handover_id INT AUTO_INCREMENT PRIMARY KEY,
    outgoing_admin_id INT NOT NULL,
    incoming_admin_id INT NOT NULL,
    status ENUM('Pending','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    INDEX idx_handover_outgoing_status (outgoing_admin_id, status),
    INDEX idx_handover_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* The old uq_pending_incoming index incorrectly prevented a later handover
 * after an earlier handover was Completed. Pending handovers are checked by
 * the application, so the database must allow repeated Completed records. */
$idxCheck = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_handover' AND INDEX_NAME='uq_pending_incoming'");
if ($idxCheck) {
    $idxRow = mysqli_fetch_assoc($idxCheck);
    if ((int)$idxRow['cnt'] > 0) {
        @mysqli_query($conn, "ALTER TABLE admin_handover DROP INDEX uq_pending_incoming");
    }
}

$actor = (int)$_SESSION['user_id'];
$msg = '';
$msgType = 'success';

/* Is this administrator currently the outgoing admin in a pending handover? */
$pendingOutgoing = false;
$pendingOutgoingTarget = 0;
$pendingIncoming = false;
$pendingIncomingOutgoing = 0;
$hStmt = mysqli_prepare($conn, "SELECT incoming_admin_id FROM admin_handover WHERE outgoing_admin_id=? AND status='Pending' ORDER BY handover_id DESC LIMIT 1");
if ($hStmt) {
    mysqli_stmt_bind_param($hStmt, 'i', $actor);
    mysqli_stmt_execute($hStmt);
    $hRes = mysqli_stmt_get_result($hStmt);
    if ($hRes && ($hRow = mysqli_fetch_assoc($hRes))) {
        $pendingOutgoing = true;
        $pendingOutgoingTarget = (int)$hRow['incoming_admin_id'];
    }
    mysqli_stmt_close($hStmt);
}
$hInStmt = mysqli_prepare($conn, "SELECT outgoing_admin_id FROM admin_handover WHERE incoming_admin_id=? AND status='Pending' ORDER BY handover_id DESC LIMIT 1");
if ($hInStmt) {
    mysqli_stmt_bind_param($hInStmt, 'i', $actor);
    mysqli_stmt_execute($hInStmt);
    $hInRes = mysqli_stmt_get_result($hInStmt);
    if ($hInRes && ($hInRow = mysqli_fetch_assoc($hInRes))) {
        $pendingIncoming = true;
        $pendingIncomingOutgoing = (int)$hInRow['outgoing_admin_id'];
    }
    mysqli_stmt_close($hInStmt);
}


/* Repair/synchronize a pending handover whenever exactly two active Administrators exist.
 * This is intentionally tolerant of older/missing audit rows: if the current logged-in
 * Administrator is one of the two active admins, the other admin is treated as the
 * previous/outgoing administrator unless a valid audit record says otherwise.
 */
$activeAdmins = [];
$aaRes = mysqli_query($conn, "SELECT user_id FROM users WHERE role_id=1 AND status='Active' ORDER BY user_id");
if ($aaRes) { while ($aa = mysqli_fetch_assoc($aaRes)) $activeAdmins[] = (int)$aa['user_id']; }

if (count($activeAdmins) === 2) {
    $incoming = 0; $outgoing = 0;

    // Prefer the latest successful promotion audit.
    $auditRes = mysqli_query($conn, "SELECT actor_user_id, target_user_id FROM admin_role_audit WHERE new_role_id=1 AND action='promoted_employee_to_admin_pending_handover' ORDER BY audit_id DESC LIMIT 1");
    if ($auditRes && ($ar = mysqli_fetch_assoc($auditRes))) {
        $a = (int)$ar['actor_user_id'];
        $t = (int)$ar['target_user_id'];
        if (in_array($a, $activeAdmins, true) && in_array($t, $activeAdmins, true) && $a !== $t) {
            $outgoing = $a; $incoming = $t;
        }
    }

    // If the audit row is missing (for example after an old patch/recovery),
    // the logged-in admin is the safest incoming-admin candidate because only
    // the newly promoted admin is allowed to complete the handover.
    if ($incoming === 0 && in_array($actor, $activeAdmins, true)) {
        $incoming = $actor;
        foreach ($activeAdmins as $aid) {
            if ($aid !== $actor) { $outgoing = $aid; break; }
        }
    }

    if ($incoming > 0 && $outgoing > 0) {
        $pendingPair = mysqli_prepare($conn, "SELECT handover_id FROM admin_handover WHERE outgoing_admin_id=? AND incoming_admin_id=? AND status='Pending' LIMIT 1");
        $hasPair = false;
        if ($pendingPair) {
            mysqli_stmt_bind_param($pendingPair, 'ii', $outgoing, $incoming);
            mysqli_stmt_execute($pendingPair);
            $pr = mysqli_stmt_get_result($pendingPair);
            $hasPair = $pr && mysqli_num_rows($pr) > 0;
            mysqli_stmt_close($pendingPair);
        }
        if (!$hasPair) {
            // Cancel stale pending rows before creating the correct pair.
            mysqli_query($conn, "UPDATE admin_handover SET status='Cancelled' WHERE status='Pending'");
            $ins = mysqli_prepare($conn, "INSERT INTO admin_handover (outgoing_admin_id,incoming_admin_id,status) VALUES (?,?, 'Pending')");
            if ($ins) {
                mysqli_stmt_bind_param($ins, 'ii', $outgoing, $incoming);
                mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);
            }
        }
        if ($incoming === $actor) {
            $pendingIncoming = true;
            $pendingIncomingOutgoing = $outgoing;
        }
        if ($outgoing === $actor) {
            $pendingOutgoing = true;
            $pendingOutgoingTarget = $incoming;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = (int)($_POST['target_user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($target <= 0) {
        $msg = 'Invalid employee selected.'; $msgType = 'danger';
    } elseif ($target === $actor && $action === 'demote') {
        $msg = 'You cannot demote your own administrator account. The new Administrator must demote the outgoing Administrator.'; $msgType = 'danger';
    } elseif ($pendingOutgoing && $action !== 'demote') {
        $msg = 'Your administrator handover is pending. The newly promoted Administrator must demote your account first.'; $msgType = 'danger';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT user_id, full_name, username, email, phone, role_id, status FROM users WHERE user_id=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $target);
        mysqli_stmt_execute($stmt);
        $targetUser = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$targetUser || $targetUser['status'] === 'Removed') {
            $msg = 'The selected user was not found.'; $msgType = 'danger';
        } elseif ($action === 'promote' && (int)$targetUser['role_id'] === 1) {
            $msg = 'This user is already an Administrator.'; $msgType = 'warning';
        } elseif ($action === 'promote' && (int)$targetUser['role_id'] !== 1) {
            /* Only the current sole admin may start a handover. */
            $countRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role_id=1 AND status='Active'"));
            $adminCount = (int)($countRow['total'] ?? 0);
            if ($adminCount !== 1) {
                $msg = 'Promotion is allowed only when exactly one active Administrator exists.'; $msgType = 'danger';
            } else {
                $existing = mysqli_prepare($conn, "SELECT handover_id FROM admin_handover WHERE status='Pending' LIMIT 1");
                $hasPending = false;
                if ($existing) {
                    mysqli_stmt_execute($existing);
                    $er = mysqli_stmt_get_result($existing);
                    $hasPending = $er && mysqli_num_rows($er) > 0;
                    mysqli_stmt_close($existing);
                }
                if ($hasPending) {
                    $msg = 'An administrator handover is already pending. The newly promoted Administrator must complete it first.'; $msgType = 'danger';
                } elseif ($targetUser['status'] !== 'Active' || !in_array((int)$targetUser['role_id'], [2,3], true)) {
                    $msg = 'Only an active Receptionist or Teller can be promoted.'; $msgType = 'danger';
                } else {
                    $newRole = 1;
                    $oldRole = (int)$targetUser['role_id'];
                    mysqli_begin_transaction($conn);
                    $ok = false;
                    $up = mysqli_prepare($conn, "UPDATE users SET role_id=? WHERE user_id=? AND role_id IN (2,3) AND status='Active'");
                    if ($up) {
                        mysqli_stmt_bind_param($up, 'ii', $newRole, $target);
                        $ok = mysqli_stmt_execute($up) && mysqli_stmt_affected_rows($up) === 1;
                        mysqli_stmt_close($up);
                    }
                    if ($ok) {
                        $handover = mysqli_prepare($conn, "INSERT INTO admin_handover (outgoing_admin_id,incoming_admin_id,status) VALUES (?,?, 'Pending')");
                        if ($handover) {
                            mysqli_stmt_bind_param($handover, 'ii', $actor, $target);
                            $ok = mysqli_stmt_execute($handover);
                            mysqli_stmt_close($handover);
                        } else { $ok = false; }
                    }
                    if ($ok) {
                        $log = mysqli_prepare($conn, "INSERT INTO admin_role_audit (actor_user_id,target_user_id,old_role_id,new_role_id,action) VALUES (?,?,?,?,?)");
                        $roleAction = 'promoted_employee_to_admin_pending_handover';
                        if ($log) {
                            mysqli_stmt_bind_param($log,'iiiis',$actor,$target,$oldRole,$newRole,$roleAction);
                            $ok = mysqli_stmt_execute($log);
                            mysqli_stmt_close($log);
                        } else { $ok = false; }
                    }
                    if ($ok) {
                        $text = "Promoted {$targetUser['full_name']} (ID {$target}) to Administrator; handover pending from outgoing admin ID {$actor}";
                        $log2 = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, action, created_at) VALUES (?, ?, NOW())");
                        if ($log2) { mysqli_stmt_bind_param($log2,'is',$actor,$text); mysqli_stmt_execute($log2); mysqli_stmt_close($log2); }
                        mysqli_commit($conn);
                        $pendingOutgoing = true;
                        $pendingOutgoingTarget = $target;
                        $msg = 'Employee promoted successfully. The new Administrator must log in and demote the previous Administrator to complete the handover.';
                    } else {
                        mysqli_rollback($conn); $msg = 'Promotion failed.'; $msgType = 'danger';
                    }
                }
            }
        } elseif ($action === 'demote') {
            /* Only the incoming/new admin may demote the outgoing/old admin. */
            $handoverStmt = mysqli_prepare($conn, "SELECT handover_id, outgoing_admin_id, incoming_admin_id FROM admin_handover WHERE outgoing_admin_id=? AND incoming_admin_id=? AND status='Pending' LIMIT 1");
            $validHandover = false;
            $handoverId = 0;
            if ($handoverStmt) {
                mysqli_stmt_bind_param($handoverStmt, 'ii', $target, $actor);
                mysqli_stmt_execute($handoverStmt);
                $hr = mysqli_stmt_get_result($handoverStmt);
                if ($hr && ($h = mysqli_fetch_assoc($hr))) { $validHandover = true; $handoverId = (int)$h['handover_id']; }
                mysqli_stmt_close($handoverStmt);
            }
            if (!$validHandover) {
                $msg = 'This Administrator can only be demoted by the newly promoted Administrator assigned in the pending handover.'; $msgType = 'danger';
            } else {
                $newRole = 2;
                $oldRole = 1;
                mysqli_begin_transaction($conn);
                $ok = false;
                $up = mysqli_prepare($conn, "UPDATE users SET role_id=? WHERE user_id=? AND role_id=1 AND status='Active'");
                if ($up) {
                    mysqli_stmt_bind_param($up, 'ii', $newRole, $target);
                    $ok = mysqli_stmt_execute($up) && mysqli_stmt_affected_rows($up) === 1;
                    mysqli_stmt_close($up);
                }
                if ($ok) {
                    $done = mysqli_prepare($conn, "UPDATE admin_handover SET status='Completed', completed_at=NOW() WHERE handover_id=? AND status='Pending'");
                    if ($done) {
                        mysqli_stmt_bind_param($done, 'i', $handoverId);
                        $ok = mysqli_stmt_execute($done) && mysqli_stmt_affected_rows($done) === 1;
                        mysqli_stmt_close($done);
                    } else { $ok = false; }
                }
                if ($ok) {
                    $log = mysqli_prepare($conn, "INSERT INTO admin_role_audit (actor_user_id,target_user_id,old_role_id,new_role_id,action) VALUES (?,?,?,?,?)");
                    $roleAction = 'completed_admin_handover_demoted_previous_admin';
                    if ($log) {
                        mysqli_stmt_bind_param($log,'iiiis',$actor,$target,$oldRole,$newRole,$roleAction);
                        $ok = mysqli_stmt_execute($log);
                        mysqli_stmt_close($log);
                    } else { $ok = false; }
                }
                if ($ok) {
                    $text = "Completed admin handover: {$targetUser['full_name']} (ID {$target}) demoted to Receptionist by incoming admin ID {$actor}";
                    $log2 = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, action, created_at) VALUES (?, ?, NOW())");
                    if ($log2) { mysqli_stmt_bind_param($log2,'is',$actor,$text); mysqli_stmt_execute($log2); mysqli_stmt_close($log2); }
                    mysqli_commit($conn);
                    $pendingOutgoing = false;
                    $pendingOutgoingTarget = 0;
                    $msg = 'Admin handover completed successfully. You are now the only active Administrator.';
                } else {
                    mysqli_rollback($conn); $msg = 'Demotion failed.'; $msgType = 'danger';
                }
            }
        }
    }
}

$users = mysqli_query($conn, "SELECT user_id, full_name, username, email, phone, role_id, status FROM users WHERE status <> 'Removed' ORDER BY role_id, full_name");
$adminCountRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role_id=1 AND status='Active'"));
$adminCount = (int)($adminCountRow['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f5f7fa}.wrap{max-width:1200px;margin:28px auto}.role-admin{background:#198754}.role-reception{background:#003DA5}.role-teller{background:#6f42c1}.note{font-size:.92rem}
</style>
</head>
<body>
<div class="wrap px-3">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
<div><h2 class="mb-1">🛡️ Admin Management</h2><div class="text-muted">Promote employees to Administrator or demote an Administrator without deleting the account.</div></div>
<div><a href="profile_requests.php" class="btn btn-outline-success">✅ Profile Approvals</a> <a href="employees.php" class="btn btn-outline-primary">👥 Employee Management</a> <a href="dashboard.php" class="btn btn-secondary">⬅ Dashboard</a></div>
</div>
<?php if($msg): ?><div class="alert alert-<?=htmlspecialchars($msgType)?>"><?=htmlspecialchars($msg)?></div><?php endif; ?>
<div class="alert alert-info note"><strong>Active Administrators: <?= $adminCount ?></strong><br>Normally only one Administrator is active. To change the Administrator, the current Administrator promotes one employee first; then <strong>only the newly promoted Administrator</strong> can demote the previous Administrator. This completes the handover and leaves exactly one active Administrator.</div>
<?php if($pendingOutgoing): ?>
<div class="alert alert-warning"><strong>🔄 Handover Pending:</strong> You promoted employee ID <?= $pendingOutgoingTarget ?>. Log out and let the newly promoted Administrator log in; they will see a <strong>Demote</strong> button for your account. No other promotion/demotion is allowed until the handover is completed.</div>
<?php endif; ?>
<div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-dark"><tr><th>ID</th><th>Full Name</th><th>Username</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php while($u=mysqli_fetch_assoc($users)): ?>
<tr>
<td><?= (int)$u['user_id'] ?></td><td><?=htmlspecialchars($u['full_name'])?></td><td><?=htmlspecialchars($u['username'])?></td><td><?=htmlspecialchars($u['email']??'')?></td><td><?=htmlspecialchars($u['phone']??'')?></td>
<td><?php if((int)$u['role_id']===1): ?><span class="badge role-admin">Administrator</span><?php elseif((int)$u['role_id']===2): ?><span class="badge role-reception">Receptionist</span><?php else: ?><span class="badge role-teller">Teller</span><?php endif; ?></td>
<td><?= $u['status']==='Active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
<td>
<?php if((int)$u['role_id']===1): ?>
<button type="button" class="btn btn-outline-primary btn-sm" onclick="showAdminInfo(<?= (int)$u['user_id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name']), ENT_QUOTES) ?>', <?= ($pendingIncoming && $actor === (int)$u['user_id']) ? 'true' : 'false' ?>, <?= ($pendingIncoming && $actor === (int)$u['user_id']) ? $pendingIncomingOutgoing : 0 ?>)">⚙ Manage Admin</button>
<?php if($pendingIncoming && $pendingIncomingOutgoing === (int)$u['user_id']): ?>
<form method="post" class="d-inline ms-1" onsubmit="return confirm('Complete the administrator handover by demoting the previous Administrator to Receptionist?');"><input type="hidden" name="target_user_id" value="<?= (int)$u['user_id'] ?>"><input type="hidden" name="action" value="demote"><button class="btn btn-warning btn-sm">⬇ Demote Previous Admin</button></form>
<?php elseif($pendingOutgoing && $pendingOutgoingTarget === (int)$u['user_id']): ?>
<span class="badge bg-info text-dark ms-1">New Admin — Handover Pending</span>
<?php elseif((int)$u['user_id'] === $actor): ?>
<span class="text-muted ms-1">Current Administrator</span>
<?php else: ?>
<span class="text-muted ms-1">Waiting for handover</span>
<?php endif; ?>
<?php elseif($u['status']==='Active' && !$pendingOutgoing): ?>
<form method="post" class="d-inline" onsubmit="return confirm('Promote this employee to Administrator? After promotion, this employee must log in and demote the previous Administrator to complete the handover.');"><input type="hidden" name="target_user_id" value="<?= (int)$u['user_id'] ?>"><input type="hidden" name="action" value="promote"><button class="btn btn-success btn-sm">⬆ Promote to Admin</button></form>
<?php elseif($u['status']==='Active' && $pendingOutgoing): ?><span class="text-muted">Handover in progress</span>
<?php else: ?><span class="text-muted">Activate employee first</span><?php endif; ?>
</td></tr>
<?php endwhile; ?>
</tbody></table></div></div>
<div class="card mt-4 shadow-sm"><div class="card-body"><h5>🔐 How it works</h5><ul class="mb-0"><li>Employee → <strong>Promote to Admin</strong> → role becomes Administrator immediately.</li><li>Admin → <strong>Demote</strong> → role becomes Receptionist; the account is not deleted.</li><li>Only the newly promoted Administrator can demote the previous Administrator during a pending handover.</li><li>The old Administrator cannot promote or demote another account while the handover is pending.</li><li>Every promotion/demotion is recorded in the audit log and activity log.</li><li>Admin accounts use the same normal login; after promotion, the employee can log in with the Administrator role.</li></ul></div></div>

<div class="modal fade" id="adminManageModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">⚙ Manage Administrator</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="adminManageBody"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showAdminInfo(id,name,canDemote,outgoingId){
  let html = '<p><strong>'+name+'</strong> (ID '+id+')</p><p>Administrator account management is controlled by the handover process.</p>';
  if (canDemote && outgoingId > 0) {
    html += '<div class="alert alert-warning"><strong>Handover ready.</strong><br>You are the newly promoted Administrator. You can now demote the previous Administrator (ID '+outgoingId+') and complete the handover.</div>';
    html += '<form method="post" onsubmit="return confirm(\'Complete the administrator handover by demoting the previous Administrator to Receptionist?\');"><input type="hidden" name="target_user_id" value="'+outgoingId+'"><input type="hidden" name="action" value="demote"><button class="btn btn-warning">⬇ Demote Previous Admin</button></form>';
  } else {
    html += '<p class="mb-0">If a handover is pending, log in with the newly promoted Administrator to complete it.</p>';
  }
  document.getElementById('adminManageBody').innerHTML = html;
  new bootstrap.Modal(document.getElementById('adminManageModal')).show();
}
</script>
</div></body></html>
