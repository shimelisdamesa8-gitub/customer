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


// Archive Report first. Clear Report is allowed only after this archive exists.
if(isset($_POST['archive_report']))
{

    $report_date = $_POST['report_date'];


    if($report_date == "")
    {
        $error = "Please select a report date.";
    }
    else
    {

        // Check whether already archived

        $check_archive = mysqli_query($conn,

        "SELECT archive_id

        FROM report_archive

        WHERE report_date='$report_date'

        LIMIT 1"

        );


        if(mysqli_num_rows($check_archive) > 0)
        {
            $error = "This report has already been archived.";
        }
        else
        {

            // Get statistics

            $total = mysqli_fetch_assoc(

                mysqli_query($conn,

                "SELECT COUNT(*) total

                FROM queue_records

                WHERE DATE(created_time)='$report_date'"

                )

            );


            $pending = mysqli_fetch_assoc(

                mysqli_query($conn,

                "SELECT COUNT(*) total

                FROM queue_records

                WHERE DATE(created_time)='$report_date'

                AND status='Pending'"

                )

            );


            $assigned = mysqli_fetch_assoc(

                mysqli_query($conn,

                "SELECT COUNT(*) total

                FROM queue_records

                WHERE DATE(created_time)='$report_date'

                AND status='Assigned'"

                )

            );


            $serving = mysqli_fetch_assoc(

                mysqli_query($conn,

                "SELECT COUNT(*) total

                FROM queue_records

                WHERE DATE(created_time)='$report_date'

                AND status='Serving'"

                )

            );


            $completed = mysqli_fetch_assoc(

                mysqli_query($conn,

                "SELECT COUNT(*) total

                FROM queue_records

                WHERE DATE(completed_time)='$report_date'

                AND status='Completed'"

                )

            );


            // Insert archive

            $insert = mysqli_query($conn,

            "INSERT INTO report_archive
            (
                report_date,
                total_customers,
                pending_customers,
                assigned_customers,
                serving_customers,
                completed_customers,
                archived_by
            )

            VALUES
            (
                '$report_date',
                '{$total['total']}',
                '{$pending['total']}',
                '{$assigned['total']}',
                '{$serving['total']}',
                '{$completed['total']}',
                '{$_SESSION['user_id']}'
            )"

            );


            if($insert)
            {

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
                    'Archived report for $report_date',
                    NOW()
                )"

                );


                $success =
                "Report for $report_date archived successfully.";

            }
            else
            {
                $error = "Report archive failed.";
            }

        }

    }

}

?>

<!DOCTYPE html>

<html>

<head>
    <link rel="stylesheet" href="css/archive_report.css">

<title>
Archive Report
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
💾 Archive Report
</h2>

<p class="text-muted">
Save a report snapshot first. After successful archiving, use <b>Clear Report</b> to remove completed queue records.
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


<form method="POST">


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
name="archive_report"
class="btn btn-success w-100">

💾 ARCHIVE REPORT

</button>


<br><br>


<div class="archive-action-row">
<a href="reports.php?date=<?php echo urlencode($prefill_date); ?>" class="btn btn-primary">📊 View This Report</a>
<a href="archived_reports.php" class="btn btn-dark">💾 View Archived Reports</a>
<a href="clear_report.php" class="btn btn-danger">🧹 Clear Completed Report</a>
</div>


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