<?php
require_once __DIR__ . "/../config/language.php";

require_once __DIR__ . "/../config/session.php";

include("../config/database.php");


if(!isset($_SESSION['user_id']))
{
    header("Location: ../login.php");
    exit();
}



$query = mysqli_query($conn,

"SELECT

activity_logs.log_id,

users.full_name,

users.username,

activity_logs.action,

activity_logs.created_at


FROM activity_logs


JOIN users

ON activity_logs.user_id = users.user_id


ORDER BY activity_logs.log_id DESC"

);


?>


<!DOCTYPE html>

<html>

<head>
    <link rel="stylesheet" href="css/activity_logs.css">


<title>
Activity Log
</title>


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">


</head>



<body>



<div class="container mt-5">


<h2>
📋 System Activity Log
</h2>


<br>


<a href="dashboard.php" class="btn btn-dark">

Back Dashboard

</a>


<br><br>



<table class="table table-bordered table-striped">


<tr>

<th>
ID
</th>


<th>
User
</th>


<th>
Username
</th>


<th>
Action
</th>


<th>
Date & Time
</th>


</tr>




<?php while($row=mysqli_fetch_assoc($query)){ ?>


<tr>


<td>

<?php echo $row['log_id']; ?>

</td>



<td>

<?php echo $row['full_name']; ?>

</td>



<td>

<?php echo $row['username']; ?>

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