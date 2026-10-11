<?php
require_once __DIR__ . "/../config/language.php";

require_once __DIR__ . "/../config/session.php";

include("../config/database.php");


// Admin only

if(!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1)
{
    header("Location: ../login.php");
    exit();
}


// Archived reports

$reports = mysqli_query($conn,

"SELECT

r.*,

u.full_name AS admin_name

FROM report_archive r

LEFT JOIN users u

ON r.archived_by=u.user_id

ORDER BY r.report_date DESC,
r.archive_id DESC"

);

?>

<!DOCTYPE html>

<html>

<head>
    <link rel="stylesheet" href="css/archived_reports.css">

<title>
Archived Reports
</title>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

</head>


<body>


<div class="container mt-4">


<!-- Header -->

<div class="d-flex justify-content-between align-items-center no-print">


<div>

<h2>
💾 Archived Reports
</h2>

<p class="text-muted">

Previously saved system reports

</p>

</div>


<div>

<a
href="dashboard.php"
class="btn btn-secondary">

⬅ Dashboard

</a>


<button
onclick="window.print()"
class="btn btn-primary">

🖨 Print / Save

</button>

</div>


</div>


<hr>



<div class="card shadow">


<div class="card-header bg-dark text-white">

<h4>
📊 Report Archive
</h4>

</div>


<div class="card-body">


<?php if(mysqli_num_rows($reports)==0){ ?>


<div class="alert alert-info text-center">

No archived reports found.

</div>


<?php } else { ?>


<div class="table-responsive">


<table class="table table-bordered table-hover">


<thead class="table-dark">

<tr>

<th>Date</th>

<th>Total</th>

<th>Pending</th>

<th>Assigned</th>

<th>Serving</th>

<th>Completed</th>

<th>Archived By</th>

<th>Archived At</th>
<th class="no-print">Action</th>

</tr>

</thead>


<tbody>


<?php while($row=mysqli_fetch_assoc($reports)){ ?>


<tr>

<td>

<?php echo htmlspecialchars($row['report_date']); ?>

</td>


<td>

<?php echo $row['total_customers']; ?>

</td>


<td>

<?php echo $row['pending_customers']; ?>

</td>


<td>

<?php echo $row['assigned_customers']; ?>

</td>


<td>

<?php echo $row['serving_customers']; ?>

</td>


<td>

<span class="badge bg-success">

<?php echo $row['completed_customers']; ?>

</span>

</td>


<td>

<?php

echo $row['admin_name']

? htmlspecialchars($row['admin_name'])

: 'Admin';

?>

</td>


<td>

<?php echo htmlspecialchars($row['archived_at']); ?>

</td>
<td class="no-print">
<div class="d-flex flex-row gap-1 flex-nowrap"><a class="btn btn-sm btn-primary px-2 py-1" href="reports.php?date=<?php echo urlencode($row['report_date']); ?>">View</a><a class="btn btn-sm btn-outline-secondary px-2 py-1" href="reports.php?date=<?php echo urlencode($row['report_date']); ?>&print=1">Print</a><a class="btn btn-sm btn-outline-danger px-2 py-1" href="dashboard.php">Back</a></div>
</td>

</tr>


<?php } ?>


</tbody>

</table>


</div>


<?php } ?>


</div>

</div>


<br>


<div class="alert alert-info no-print">

<strong>Tip:</strong>

You can use <b>Print / Save</b> to save this
archive as PDF from your browser.

</div>


</div>


</body>

</html>