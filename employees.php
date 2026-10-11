<?php
require_once __DIR__ . "/../config/language.php";

require_once __DIR__ . "/../config/session.php";

include("../config/database.php");


if(!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1)
{
    header("Location: ../login.php");
    exit();
}


$employees = mysqli_query($conn,

"SELECT

u.user_id,
u.full_name,
u.username,
u.email,
u.phone,
u.status,
u.role_id

FROM users u

WHERE u.role_id IN (2,3)
AND u.status <> 'Removed'

ORDER BY u.role_id, u.full_name"

);

?>

<!DOCTYPE html>

<html>

<head>
    <link rel="stylesheet" href="css/employees.css">

<title>
Employee Management
</title>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

</head>


<body class="bg-light">


<div class="container mt-4">

<?php if(isset($_GET['removed'])){ ?>
<div class="alert <?php echo $_GET['removed']=='1' ? 'alert-success' : 'alert-danger'; ?>">
<?php echo $_GET['removed']=='1' ? 'Employee deleted successfully.' : 'Employee deletion failed.'; ?>
</div>
<?php } ?>

<div class="d-flex justify-content-between">


<h2>
👥 Employee Management
</h2>


<div>

<a
href="add_employee.php"
class="btn btn-success">

➕ Add Employee

</a>


<a
href="admin_management.php"
class="btn btn-primary">
🛡️ Admin Management
</a>

<a
href="dashboard.php"
class="btn btn-secondary">

⬅ Dashboard

</a>

</div>


</div>


<hr>



<div class="card shadow">


<div class="card-body">


<div class="table-responsive">


<table class="table table-bordered table-hover">


<thead class="table-dark">

<tr>

<th>#</th>

<th>Full Name</th>

<th>Username</th>

<th>Email</th>

<th>Phone Number</th>

<th>Position</th>

<th>Status</th>

<th>Action</th>

</tr>

</thead>


<tbody>


<?php while($row=mysqli_fetch_assoc($employees)){ ?>


<tr>


<td>

<?php echo $row['user_id']; ?>

</td>


<td>

<?php echo htmlspecialchars($row['full_name']); ?>

</td>


<td>

<?php echo htmlspecialchars($row['username']); ?>

</td>


<td>
<?php echo htmlspecialchars($row['email'] ?? ''); ?>
</td>

<td>
<?php echo htmlspecialchars($row['phone'] ?? ''); ?>
</td>


<td>


<?php

if($row['role_id']==2)
{
    echo "Receptionist";
}
else
{
    echo "Teller";
}

?>

</td>


<td>


<?php if($row['status']=="Active"){ ?>

<span class="badge bg-success">
Active
</span>

<?php } else { ?>

<span class="badge bg-danger">
Inactive
</span>

<?php } ?>


</td>


<td>


<a
href="edit_employee.php?id=<?php echo $row['user_id']; ?>"

class="btn btn-primary btn-sm">

✏️ Edit

</a>


<a
href="reset_password.php?id=<?php echo $row['user_id']; ?>"

class="btn btn-warning btn-sm">

🔑 Reset Password

</a>


<a
href="toggle_employee.php?id=<?php echo $row['user_id']; ?>"

class="btn btn-secondary btn-sm"

onclick="return confirm('Change employee status?');">

<?php

echo ($row['status']=="Active")
? "Deactivate"
: "Activate";

?>

</a>

<a
href="remove_employee.php?id=<?php echo $row['user_id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Delete this employee permanently from the database?');">
🗑️ Remove
</a>


</td>


</tr>


<?php } ?>


</tbody>

</table>


</div>


</div>

</div>


</div>


</body>

</html>