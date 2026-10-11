<?php
require_once __DIR__ . "/../config/language.php";
session_start();
include("../config/database.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1){
    header("Location: ../login.php");
    exit();
}

$customer_id = (int)($_GET['id'] ?? 0);
if($customer_id <= 0){ die('Invalid customer ID.'); }

$sql = "SELECT
            c.customer_id,
            c.full_name,
            c.account_number,
            c.phone,
            c.amount,
            c.branch,
            c.service_type,
            c.priority_id,
            q.queue_number,
            q.status AS queue_status,
            q.created_time AS queue_created_time
        FROM customers c
        LEFT JOIN queue_records q ON q.customer_id = c.customer_id
        WHERE c.customer_id = $customer_id
        ORDER BY q.queue_id DESC
        LIMIT 1";

$result = mysqli_query($conn, $sql);
if(!$result){ die('Database error: '.htmlspecialchars(mysqli_error($conn))); }
$customer = mysqli_fetch_assoc($result);
if(!$customer){ die('Customer not found.'); }

$priority_names = [1=>'VIP',2=>'Elderly',3=>'Special',4=>'Normal'];
$priority = $priority_names[(int)$customer['priority_id']] ?? 'Normal';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="css/customer_view.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>View Customer</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h2 class="mb-1">👤 Customer Information</h2><p class="text-muted mb-0">All information entered during customer registration.</p></div>
  <a href="customers.php" class="btn btn-secondary">⬅ Back to Customers</a>
</div>

<div class="card shadow">
<div class="card-header bg-dark text-white"><h4 class="mb-0">Complete Customer Details</h4></div>
<div class="card-body">
<div class="row g-4">
  <div class="col-md-6"><div class="label">Customer ID</div><div class="value"><?php echo (int)$customer['customer_id']; ?></div></div>
  <div class="col-md-6"><div class="label">Full Name</div><div class="value"><?php echo htmlspecialchars($customer['full_name']); ?></div></div>
  <div class="col-md-6"><div class="label">Account Number</div><div class="value"><?php echo htmlspecialchars($customer['account_number']); ?></div></div>
  <div class="col-md-6"><div class="label">Phone Number</div><div class="value"><?php echo htmlspecialchars($customer['phone']); ?></div></div>
  <div class="col-md-6"><div class="label">Amount</div><div class="value"><?php echo htmlspecialchars($customer['amount']); ?></div></div>
  <div class="col-md-6"><div class="label">Branch</div><div class="value"><?php echo htmlspecialchars($customer['branch']); ?></div></div>
  <div class="col-md-6"><div class="label">Service</div><div class="value"><?php echo htmlspecialchars($customer['service_type']); ?></div></div>
  <div class="col-md-6"><div class="label">Priority</div><div class="value"><?php echo htmlspecialchars($priority); ?></div></div>
  <div class="col-md-6"><div class="label">Queue Number</div><div class="value"><?php echo htmlspecialchars($customer['queue_number'] ?? '-'); ?></div></div>
  <div class="col-md-6"><div class="label">Queue Status</div><div class="value"><?php echo htmlspecialchars($customer['queue_status'] ?? '-'); ?></div></div>
  <div class="col-md-6"><div class="label">Registered / Queue Created</div><div class="value"><?php echo htmlspecialchars($customer['queue_created_time'] ?? '-'); ?></div></div>
</div>
</div>
</div>
</div>
</body>
</html>
