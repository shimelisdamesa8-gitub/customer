<?php
require_once __DIR__ . "/../config/language.php";

require_once __DIR__ . "/../config/session.php";

include("../config/database.php");


if(!isset($_SESSION['user_id']))
{
    header("Location: ../login.php");
    exit();
}



// Add User

if(isset($_POST['add_user']))
{

$full_name=$_POST['full_name'];

$username=$_POST['username'];

$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

$role_id=$_POST['role_id'];



$sql=mysqli_query($conn,

"INSERT INTO users

(
full_name,
username,
password,
role_id,
status
)

VALUES

(
'$full_name',
'$username',
'$password',
'$role_id',
'Active'
)

"

);


if($sql)
{
$message="User Created Successfully";
}

else
{
$message="User Creation Failed";
}

}




// Get Users

$users=mysqli_query($conn,

"SELECT 

u.*,

r.role_name


FROM users u


JOIN roles r

ON u.role_id=r.role_id"

);


?>


<!DOCTYPE html>

<html>

<head>
    <link rel="stylesheet" href="css/users.css">

<title>
User Management
</title>


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">


</head>


<body class="bg-light">


<div class="container mt-4">


<h2>
👥 User Management
</h2>


<a href="dashboard.php"

class="btn btn-secondary">

Back Dashboard

</a>


<hr>



<?php

if(isset($message))
{
echo "<div class='alert alert-info'>$message</div>";
}

?>



<div class="card shadow">


<div class="card-header bg-primary text-white">

<h4>
Create New User
</h4>

</div>


<div class="card-body">


<form method="POST">



<div class="mb-3">

<label>
Full Name
</label>

<input type="text"
name="full_name"
class="form-control"
required>

</div>



<div class="mb-3">

<label>
Username
</label>

<input type="text"
name="username"
class="form-control"
required>

</div>



<div class="mb-3">

<label>
Password
</label>

<input type="password" autocomplete="new-password"
name="password"
class="form-control"
required>

</div>



<div class="mb-3">

<label>
Role
</label>


<select name="role_id"
class="form-select"
required>


<option value="2">
Receptionist
</option>


<option value="3">
Teller
</option>


</select>


</div>



<button name="add_user"

class="btn btn-success">

Create User

</button>


</form>


</div>


</div>



<br>



<div class="card shadow">


<div class="card-header bg-dark text-white">

<h4>
All Users
</h4>

</div>


<div class="card-body">


<table class="table table-bordered">


<tr>

<th>Name</th>
<th>Username</th>
<th>Role</th>
<th>Status</th>

</tr>


<?php while($u=mysqli_fetch_assoc($users)){ ?>


<tr>


<td>
<?php echo $u['full_name']; ?>
</td>


<td>
<?php echo $u['username']; ?>
</td>


<td>
<?php echo $u['role_name']; ?>
</td>


<td>
<?php echo $u['status']; ?>
</td>


</tr>


<?php } ?>


</table>


</div>


</div>


</div>


</body>

</html>