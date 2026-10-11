<?php
require_once __DIR__ . "/../config/language.php";

require_once __DIR__ . "/../config/session.php";

include("../config/database.php");


if(!isset($_SESSION['user_id']))
{
    header("Location: ../login.php");
    exit();
}



// Get Activity Logs

$query=mysqli_query($conn,

"SELECT

a.action,

a.created_at,

u.full_name,

r.role_name


FROM activity_logs a


JOIN users u

ON a.user_id=u.user_id


JOIN roles r

ON u.role_id=r.role_id



ORDER BY a.created_at DESC"

);



?>


<!DOCTYPE html>

<html>


<head>
    <link rel="stylesheet" href="css/activity_log.css">

<title>
Activity Logs
</title>


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">


</head>



<body>


<div class="container mt-4">


<h2>
📋 System Activity Log
</h2>


<a href="dashboard.php"

class="btn btn-dark">

Back Dashboard

</a>


<br><br>



<table class="table table-bordered table-striped">


<tr>

<th>
User
</th>

<th>
Role
</th>

<th>
Action
</th>

<th>
Date
</th>

</tr>



<?php while($row=mysqli_fetch_assoc($query)){ ?>


<tr>


<td>

<?php echo $row['full_name']; ?>

</td>


<td>

<?php echo $row['role_name']; ?>

</td>


<td>

<?php echo $row['action']; ?>

</td>


<td>

<?php echo $row['created_at']; ?>

</td>


</tr>


<?php } ?>


</table>


</div>


</body>


</html>