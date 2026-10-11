<?php
require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../config/database.php";

if(!isset($_SESSION['user_id']) || (int)$_SESSION['role_id'] !== 1){
    header("Location: ../login.php");
    exit();
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($user_id <= 0){
    header("Location: employees.php?removed=0");
    exit();
}

$stmt = mysqli_prepare($conn,
    "SELECT full_name, username FROM users
     WHERE user_id=? AND role_id IN (2,3) LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$employee = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if(!$employee){
    header("Location: employees.php?removed=0");
    exit();
}

mysqli_begin_transaction($conn);
$ok = true;

/*
 * Remove records that have foreign-key references to users.user_id first.
 * This makes employee removal a real database DELETE rather than a
 * "Removed" status. Only rows belonging to this employee are affected.
 */
$fk = mysqli_query($conn, "
    SELECT DISTINCT TABLE_NAME, COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
      AND REFERENCED_TABLE_NAME = 'users'
      AND REFERENCED_COLUMN_NAME = 'user_id'
      AND TABLE_NAME <> 'users'
");
if($fk){
    while($ref = mysqli_fetch_assoc($fk)){
        $table = str_replace('`','``',$ref['TABLE_NAME']);
        $column = str_replace('`','``',$ref['COLUMN_NAME']);
        $sql = "DELETE FROM `{$table}` WHERE `{$column}`=?";
        $q = mysqli_prepare($conn,$sql);
        if(!$q){
            $ok=false; break;
        }
        mysqli_stmt_bind_param($q,"i",$user_id);
        if(!mysqli_stmt_execute($q)){
            $ok=false;
            mysqli_stmt_close($q);
            break;
        }
        mysqli_stmt_close($q);
    }
}

if($ok){
    $q = mysqli_prepare($conn, "DELETE FROM users WHERE user_id=? AND role_id IN (2,3) LIMIT 1");
    if(!$q){
        $ok=false;
    } else {
        mysqli_stmt_bind_param($q,"i",$user_id);
        if(!mysqli_stmt_execute($q) || mysqli_stmt_affected_rows($q)!==1) $ok=false;
        mysqli_stmt_close($q);
    }
}

if($ok){
    mysqli_commit($conn);
    header("Location: employees.php?removed=1");
    exit();
}

mysqli_rollback($conn);
header("Location: employees.php?removed=0");
exit();
?>
