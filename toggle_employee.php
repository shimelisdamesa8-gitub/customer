<?php

require_once __DIR__ . "/../config/session.php";

include("../config/database.php");


if(!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1)
{
    header("Location: ../login.php");
    exit();
}


if(!isset($_GET['id']))
{
    header("Location: employees.php");
    exit();
}


$user_id = intval($_GET['id']);


// Get current status

$result = mysqli_query($conn,

"SELECT status, full_name

FROM users

WHERE user_id='$user_id'

AND role_id IN (2,3)
AND status <> 'Removed'"

);


if(mysqli_num_rows($result)==0)
{
    header("Location: employees.php");
    exit();
}


$user = mysqli_fetch_assoc($result);


$new_status =
($user['status']=="Active")
? "Inactive"
: "Active";


mysqli_query($conn,

"UPDATE users

SET status='$new_status'

WHERE user_id='$user_id'

AND role_id IN (2,3)"

);


// Activity log

mysqli_query($conn,

"INSERT INTO activity_logs
(
    user_id,
    action,
    created_at
)

VALUES
(
    '{$_SESSION['user_id']}',
    'Changed employee {$user['full_name']} status to $new_status',
    NOW()
)"

);


header("Location: employees.php");

exit();

?>