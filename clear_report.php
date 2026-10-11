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


$error = "";
$success = "";
$prefill_date = isset($_GET["date"]) && preg_match("/^\\d{4}-\\d{2}-\\d{2}$/", $_GET["date"]) ? $_GET["date"] : date("Y-m-d");


// Clear only completed queue records
// for selected date

if(isset($_POST['clear_report']))
{

    $report_date = $_POST['report_date'];


    if($report_date == "")
    {
        $error = "Please select a date.";
    }

    else
    {
        // Safety rule: a report must be archived before it can be cleared.
        $archive_check = mysqli_query($conn,
            "SELECT archive_id FROM report_archive WHERE report_date='$report_date' LIMIT 1"
        );

        if(!$archive_check){
            $error = "Report archive table is missing. Run database/admin_reports_branch_patch.sql first.";
        }
        elseif(mysqli_num_rows($archive_check) == 0){
            $error = "Please archive this report first. Go to Archive Report, save the report, then clear it.";
        }
        else
        {
        // Count records first

        $check = mysqli_query($conn,

        "SELECT COUNT(*) total

        FROM queue_records

        WHERE DATE(completed_time)='$report_date'

        AND status='Completed'"

        );


        $count = mysqli_fetch_assoc($check);


        if($count['total'] == 0)
        {
            $error = "No completed records found for this date.";
        }

        else
        {

            // Delete activity logs related to completed
            // queue is NOT recommended.
            // Keep logs for history.

            $delete = mysqli_query($conn,

            "DELETE FROM queue_records

            WHERE DATE(completed_time)='$report_date'

            AND status='Completed'"

            );


            if($delete)
            {

                // Admin activity log

                $admin_id = $_SESSION['user_id'];

                mysqli_query($conn,

                "INSERT INTO activity_logs
                (
                    user_id,
                    action,
                    created_at
                )

                VALUES
                (
                    '$admin_id',
                    'Cleared completed report for $report_date',
                    NOW()
                )"

                );


                $success =
                "Completed report for $report_date cleared successfully.";

            }

            else
            {
                $error = "Failed to clear report: " . mysqli_error($conn);
            }

        }
        }

    }

}

?>



<!DOCTYPE html>

<html>

<head>
    <link rel="stylesheet" href="css/clear_report.css">

<title>
Clear Report
</title>


<meta charset="UTF-8">


<meta name="viewport"
content="width=device-width, initial-scale=1">


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

</head>


<body>


<div class="card col-md-6 p-4">


<div class="text-center">


<h2>
🧹 Clear Completed Report
</h2>


<p class="text-muted">

Remove completed queue records only after that date has been archived.

</p>


</div>



<?php if($error!=""){ ?>

<div class="alert alert-danger">

<?php echo htmlspecialchars($error); ?>

</div>

<?php } ?>



<?php if($success!=""){ ?>

<div class="alert alert-success">

<?php echo htmlspecialchars($success); ?>

</div>

<?php } ?>



<div class="alert alert-warning">

<strong>Warning:</strong>

This action permanently deletes completed
queue records for the selected date.

Make sure you have saved/exported the report
before clearing it.

</div>



<form method="POST"
onsubmit="return confirm('Are you sure you want to clear completed records for this date? This cannot be undone.');">


<label class="fw-bold">

Report Date

</label>


<input
type="date"
name="report_date"
class="form-control"
value="<?php echo htmlspecialchars($prefill_date); ?>"
required>


<br>


<button
type="submit"
name="clear_report"
class="btn btn-danger w-100">

🧹 CLEAR COMPLETED REPORT

</button>

<br><br>

<a href="archive_report.php" class="btn btn-success w-100">
💾 ARCHIVE REPORT FIRST
</a>


<br><br>


<a
href="reports.php"
class="btn btn-primary w-100">

📊 Back To Reports

</a>


<br><br>


<a
href="dashboard.php"
class="btn btn-secondary w-100">

⬅ Admin Dashboard

</a>


</form>


</div>


</body>

</html>