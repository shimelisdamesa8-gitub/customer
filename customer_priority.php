<?php
require_once __DIR__ . "/../config/language.php";

require_once __DIR__ . "/../config/session.php";

include("../config/database.php");


if(!isset($_SESSION['user_id']))
{
    header("Location: ../login.php");
    exit();
}



// Update Priority

if(isset($_POST['update']))
{

$customer_id=$_POST['customer_id'];

$priority_id=$_POST['priority_id'];



mysqli_query($conn,

"UPDATE customers

SET priority_id='$priority_id'

WHERE customer_id='$customer_id'"

);


}



// Get Customers

$customers=mysqli_query($conn,

"SELECT

c.customer_id,

c.full_name,

c.account_number,

c.phone,

p.priority_name


FROM customers c


JOIN priority_categories p

ON c.priority_id=p.priority_id


ORDER BY c.customer_id DESC"

);




// Get Priority

$priority=mysqli_query($conn,

"SELECT *

FROM priority_categories

ORDER BY priority_level ASC"

);


?>


<!DOCTYPE html>

<html>


<head>
    <link rel="stylesheet" href="css/customer_priority.css">

<title>
Customer Priority
</title>


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">


</head>



<body>


<div class="container mt-4">


<h2>
Customer Priority Management
</h2>


<a href="dashboard.php"

class="btn btn-dark">

Back

</a>


<br><br>



<table class="table table-bordered">


<tr>

<th>
Name
</th>

<th>
Account
</th>

<th>
Phone
</th>

<th>
Current Priority
</th>

<th>
Change
</th>

</tr>



<?php while($row=mysqli_fetch_assoc($customers)){ ?>


<tr>


<td>
<?php echo $row['full_name']; ?>
</td>


<td>
<?php echo $row['account_number']; ?>
</td>


<td>
<?php echo $row['phone']; ?>
</td>


<td>
<?php echo htmlspecialchars($row['priority_name'] ?? 'Inactive/Removed'); ?>
</td>


<td>



<form method="POST">



<input type="hidden"

name="customer_id"

value="<?php echo $row['customer_id']; ?>">



<select name="priority_id"

class="form-control">


<?php

$priority2=mysqli_query($conn,

"SELECT *

FROM priority_categories

ORDER BY priority_level ASC"

);


while($p=mysqli_fetch_assoc($priority2))

{


?>


<option value="<?php echo $p['priority_id']; ?>">

<?php echo $p['priority_name']; ?>

</option>


<?php } ?>


</select>


<br>


<button name="update"

class="btn btn-success">

UPDATE

</button>



</form>



</td>



</tr>



<?php } ?>


</table>


</div>


</body>

</html>