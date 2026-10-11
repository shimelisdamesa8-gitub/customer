<?php
require_once __DIR__ . "/../config/language.php";

include("../config/database.php");

$error = "";


// ==================================================
// REGISTER CUSTOMER
// ==================================================

if(isset($_POST['register']))
{

    $full_name =
    trim($_POST['full_name']);

    $account_number =
    trim($_POST['account_number'] ?? '');

    $phone =
    trim($_POST['phone']);

    $amount =
    trim($_POST['amount'] ?? '');

    $fan =
    trim($_POST['fan'] ?? '');

    // Never trust the branch name posted by the browser.
    // Use the single currently active branch managed by Admin.
    $branch = '';
    $active_branch_stmt = mysqli_prepare($conn,
        "SELECT branch_name FROM branches WHERE status='Active' ORDER BY branch_id ASC LIMIT 1"
    );
    mysqli_stmt_execute($active_branch_stmt);
    $active_branch_result = mysqli_stmt_get_result($active_branch_stmt);
    $active_branch_row = mysqli_fetch_assoc($active_branch_result);
    mysqli_stmt_close($active_branch_stmt);

    if (!$active_branch_row) {
        die("No active branch is available. Please ask the Admin to activate the branch.");
    }

    $branch = $active_branch_row['branch_name'];

    $service_id =
    intval($_POST['service_id'] ?? 0);

    $service_type = '';
    $requires_account = false;
    $requires_amount = false;
    $requires_fan = false;

    $priority_id =
    intval($_POST['priority_id']);


    // ==================================================
    // VALIDATION
    // ==================================================
    // Service fields are configured by Admin. Only selected
    // fields are required and stored; all other fields stay empty.

    // Validate that the selected priority is currently active.
    $priority_stmt = mysqli_prepare($conn,
        "SELECT priority_id FROM priority_categories
         WHERE priority_id=? AND status='Active' LIMIT 1"
    );
    mysqli_stmt_bind_param($priority_stmt, "i", $priority_id);
    mysqli_stmt_execute($priority_stmt);
    $priority_result = mysqli_stmt_get_result($priority_stmt);
    $priority_row = mysqli_fetch_assoc($priority_result);
    mysqli_stmt_close($priority_stmt);

    if(!$priority_row)
    {
        die("Invalid or inactive priority selected.");
    }


    // ==================================================
    // VALIDATE ACTIVE SERVICE
    // ==================================================

    $service_stmt = mysqli_prepare($conn,
        "SELECT service_name FROM services WHERE service_id=? AND status='Active' LIMIT 1"
    );
    mysqli_stmt_bind_param($service_stmt, "i", $service_id);
    mysqli_stmt_execute($service_stmt);
    $service_result = mysqli_stmt_get_result($service_stmt);
    $service_row = mysqli_fetch_assoc($service_result);
    mysqli_stmt_close($service_stmt);

    if(!$service_row)
    {
        die("Invalid or inactive service selected.");
    }

    $service_type = $service_row['service_name'];

    // Read the exact fields configured by Admin for this service.
    $configured_fields = [];
    $field_stmt = mysqli_prepare($conn, "SELECT field_key FROM service_fields WHERE service_id=? ORDER BY field_id");
    mysqli_stmt_bind_param($field_stmt, "i", $service_id);
    mysqli_stmt_execute($field_stmt);
    $field_result = mysqli_stmt_get_result($field_stmt);
    while($fr = mysqli_fetch_assoc($field_result)) $configured_fields[] = $fr['field_key'];
    mysqli_stmt_close($field_stmt);

    $requires_full_name = in_array('full_name', $configured_fields, true);
    $requires_phone = in_array('phone', $configured_fields, true);
    $requires_account = in_array('account_number', $configured_fields, true);
    $requires_amount = in_array('amount', $configured_fields, true);
    $requires_fan = in_array('fan', $configured_fields, true);

    if($requires_full_name && !preg_match("/^[\p{L}]+(?:[\s'\-]+[\p{L}]+)+$/u", $full_name))
        die("Full Name is required and must contain at least two names separated by a space.");

    if($requires_phone && !preg_match("/^[0-9]{10}$/", $phone))
        die("Phone Number must be exactly 10 digits.");

    if($requires_account && !preg_match("/^[0-9]{13}$/", $account_number))
        die("Account Number must be exactly 13 digits for this service.");

    if($requires_amount && ($amount === '' || (float)$amount < 1))
        die("Amount is required and must be greater than zero for this service.");

    if($requires_fan && ($fan === '' || !preg_match("/^[A-Za-z0-9\-\/ ]+$/", $fan)))
        die("FAN is required for this service.");

    // Never accept values for fields that Admin did not configure.
    if(!$requires_full_name) $full_name = '';
    if(!$requires_phone) $phone = '';
    if(!$requires_account) $account_number = '';
    if(!$requires_amount) $amount = 0;
    if(!$requires_fan) $fan = '';


    // ==================================================
    // CHECK ACTIVE QUEUE
    // ==================================================
    //
    // Customer is allowed to register again
    // if previous service was Completed.
    //
    // Active statuses:
    // Pending
    // Current
    // Assigned
    // Serving
    //
    // ==================================================

    // Check whether the optional FAN column exists so older databases do not crash.
    $fan_column_check = mysqli_query($conn,
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers' AND COLUMN_NAME='fan'
         LIMIT 1"
    );
    $has_fan_column = ($fan_column_check && mysqli_num_rows($fan_column_check) > 0);

    $fan_condition = $has_fan_column
        ? "OR (c.fan='" . mysqli_real_escape_string($conn, $fan) . "' AND '" . mysqli_real_escape_string($conn, $fan) . "' <> '')"
        : '';

    $safe_account = mysqli_real_escape_string($conn, $account_number);
    $safe_phone = mysqli_real_escape_string($conn, $phone);

    $active_check = mysqli_query($conn,

    "SELECT q.queue_id, q.queue_number, q.status
     FROM queue_records q
     JOIN customers c ON q.customer_id=c.customer_id
     WHERE (
        (c.account_number='$safe_account' AND '$safe_account' <> '')
        OR (c.phone='$safe_phone' AND '$safe_phone' <> '')
        $fan_condition
     )
     AND q.status IN ('Pending','Current','Assigned','Serving')
     LIMIT 1"
    );


    if(mysqli_num_rows($active_check)>0)
    {

        $active =
        mysqli_fetch_assoc($active_check);


        die("

        <div style='
            text-align:center;
            margin-top:100px;
            font-family:Arial;
        '>

            <h2>⚠️ Customer Already in Queue</h2>

            <p>
            This customer already has an active queue.
            </p>

            <h3>
            Queue Number:
            {$active['queue_number']}
            </h3>

            <p>
            Status:
            {$active['status']}
            </p>

            <a href='register.php'
            style='
                display:inline-block;
                padding:10px 20px;
                background:#003DA5;
                color:white;
                text-decoration:none;
                border-radius:5px;
            '>

            Back to Registration

            </a>

        </div>

        ");

    }


    // ==================================================
    // CUSTOMER INSERT
    // ==================================================

    $sql = mysqli_query($conn,

    "INSERT INTO customers
    (
        full_name,
        account_number,
        phone,
        amount,
        fan,
        branch,
        service_type,
        priority_id
    )

    VALUES
    (
        '$full_name',
        '$account_number',
        '$phone',
        '$amount',
        '$fan',
        '$branch',
        '$service_type',
        '$priority_id'
    )"

    );


    if(!$sql)
    {
        die("Customer Registration Failed.");
    }


    $customer_id =
    mysqli_insert_id($conn);


    // ==================================================
    // GENERATE QUEUE NUMBER
    // ==================================================

    $count = mysqli_fetch_assoc(

        mysqli_query($conn,

        "SELECT COUNT(*) total

        FROM queue_records

        WHERE DATE(created_time)=CURDATE()"

        )

    );


    $number =
    $count['total'] + 1;


    $queue_number =
    "A" .
    str_pad(
        $number,
        3,
        "0",
        STR_PAD_LEFT
    );


    // ==================================================
    // INSERT QUEUE
    // ==================================================

    $queue = mysqli_query($conn,

    "INSERT INTO queue_records
    (
        customer_id,
        queue_number,
        service_id,
        status,
        created_time
    )

    VALUES
    (
        '$customer_id',
        '$queue_number',
        '$service_id',
        'Pending',
        NOW()
    )"

    );


    if(!$queue)
    {

        // Remove customer if queue creation fails

        mysqli_query($conn,

        "DELETE FROM customers

        WHERE customer_id='$customer_id'"

        );


        die("Queue Creation Failed.");

    }


    // ==================================================
    // SUCCESS PAGE
    // ==================================================

?>

<!DOCTYPE html>

<html>

<head>
    <link rel="stylesheet" href="css/register_process.css">

<title>
Registration Successful
</title>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1">


<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">


</head>


<body
class="bg-light">


<div
class="container mt-5">


<div
class="card shadow text-center col-md-6 mx-auto p-5">


<h2>
✅ Registration Successful
</h2>


<p class="text-muted">

Your customer registration has been completed.

</p>


<h4>
Your Queue Number
</h4>


<h1 class="text-primary">

<?php echo $queue_number; ?>

</h1>


<p>

Please wait until your number is called.

</p>


<a
href="register.php"
class="btn btn-success">

Register Another Customer

</a>


<br><br>


<a
href="../index.php"
class="btn btn-secondary">

⬅ Back Home

</a>


</div>


</div>


</body>

</html>


<?php

}

else

{

    header("Location: register.php");

    exit();

}

?>