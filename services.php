<?php
require_once __DIR__ . "/../config/language.php";
require_once __DIR__ . "/../config/session.php";
require_once("../config/database.php");
require_once("../config/site_setup.php");

if(!isset($_SESSION['user_id']) || (int)$_SESSION['role_id'] !== 1){
    header("Location: ../login.php");
    exit();
}

$error = "";
$success = "";
$serviceUploadDir = __DIR__ . "/../uploads/services";

function normalize_service_name($name){
    $name = trim($name);
    $name = preg_replace('/\s+/u', ' ', $name);
    return $name;
}

$available_fields = [
    'full_name' => 'Full Name',
    'phone' => 'Phone Number',
    'fan' => 'FAN',
    'account_number' => 'Account Number',
    'amount' => 'Amount'
];

function save_service_fields($conn, $service_id, $fields){
    mysqli_begin_transaction($conn);
    try {
        $del = mysqli_prepare($conn, "DELETE FROM service_fields WHERE service_id=?");
        mysqli_stmt_bind_param($del, "i", $service_id);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
        $ins = mysqli_prepare($conn, "INSERT INTO service_fields (service_id, field_key) VALUES (?,?)");
        foreach($fields as $field){
            $field = trim($field);
            if($field === '') continue;
            mysqli_stmt_bind_param($ins, "is", $service_id, $field);
            mysqli_stmt_execute($ins);
        }
        mysqli_stmt_close($ins);
        mysqli_commit($conn);
        return true;
    } catch(Throwable $e){
        mysqli_rollback($conn);
        return false;
    }
}

function service_exists($conn, $name, $exclude_id = 0){
    $sql = "SELECT service_id FROM services
            WHERE LOWER(TRIM(service_name)) = LOWER(?)
            AND service_id <> ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $name, $exclude_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);
    return $exists;
}

// ADD SERVICE
if(isset($_POST['add_service'])){
    $service_name = normalize_service_name($_POST['service_name'] ?? '');
    $selected_fields = array_values(array_intersect(array_keys($available_fields), $_POST['service_fields'] ?? []));
    $imagePath = null;

    if($service_name === ''){
        $error = "Please enter service name.";
    }elseif(mb_strlen($service_name) > 100){
        $error = "Service name must be 100 characters or less.";
    }elseif(service_exists($conn, $service_name)){
        $error = "This service already exists. Duplicate services are not allowed.";
    }else{
        if(isset($_FILES['service_image']) && ($_FILES['service_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE){
            $file = $_FILES['service_image'];
            $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            $info = @getimagesize($file['tmp_name']);
            $mime = $info['mime'] ?? '';
            if($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5*1024*1024 || !isset($allowed[$mime])){
                $error = "Optional service image must be JPG, PNG or WEBP and 5 MB or smaller.";
            }else{
                if(!is_dir($serviceUploadDir)) @mkdir($serviceUploadDir,0775,true);
                $filename = 'service_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                if(!move_uploaded_file($file['tmp_name'], $serviceUploadDir . '/' . $filename)){
                    $error = "Service image upload failed.";
                }else{
                    $imagePath = 'uploads/services/' . $filename;
                }
            }
        }
        if($error === ""){
            $stmt = mysqli_prepare($conn, "INSERT INTO services (service_name, status, image_path) VALUES (?, 'Active', ?)");
            mysqli_stmt_bind_param($stmt, "ss", $service_name, $imagePath);
            if(mysqli_stmt_execute($stmt)){
                $new_service_id = mysqli_insert_id($conn);
                if(save_service_fields($conn, $new_service_id, $selected_fields)){
                    $success = "Service added successfully.";
                }else{
                    mysqli_query($conn, "DELETE FROM services WHERE service_id=" . (int)$new_service_id);
                    $error = "Service was added but its customer fields could not be saved.";
                }
            }else{
                $error = "Failed to add service: " . mysqli_error($conn);
                if($imagePath && is_file(__DIR__.'/../'.$imagePath)) @unlink(__DIR__.'/../'.$imagePath);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// TOGGLE STATUS - POST only
if(isset($_POST['toggle_service'])){
    $service_id = (int)($_POST['service_id'] ?? 0);
    $stmt = mysqli_prepare($conn, "UPDATE services SET status = IF(status='Active','Inactive','Active') WHERE service_id=?");
    mysqli_stmt_bind_param($stmt, "i", $service_id);
    mysqli_stmt_execute($stmt);
    $changed = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if($changed > 0){
        $success = "Service status changed successfully.";
    }else{
        $error = "Service not found.";
    }
}

// UPDATE SERVICE
if(isset($_POST['update_service'])){
    $service_id = (int)($_POST['service_id'] ?? 0);
    $service_name = normalize_service_name($_POST['service_name'] ?? '');
    $selected_fields = array_values(array_intersect(array_keys($available_fields), $_POST['edit_service_fields'] ?? []));
    $newImagePath = null;

    if($service_name === ''){
        $error = "Service name cannot be empty.";
    }elseif(mb_strlen($service_name) > 100){
        $error = "Service name must be 100 characters or less.";
    }elseif(service_exists($conn, $service_name, $service_id)){
        $error = "Another service already has this name. Duplicate services are not allowed.";
    }else{
        if(isset($_FILES['edit_service_image']) && ($_FILES['edit_service_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE){
            $file = $_FILES['edit_service_image'];
            $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            $info = @getimagesize($file['tmp_name']);
            $mime = $info['mime'] ?? '';
            if($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5*1024*1024 || !isset($allowed[$mime])){
                $error = "Optional service image must be JPG, PNG or WEBP and 5 MB or smaller.";
            }else{
                if(!is_dir($serviceUploadDir)) @mkdir($serviceUploadDir,0775,true);
                $filename = 'service_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                if(move_uploaded_file($file['tmp_name'], $serviceUploadDir . '/' . $filename)){
                    $newImagePath = 'uploads/services/' . $filename;
                }else{
                    $error = "Service image upload failed.";
                }
            }
        }
        if($error === ""){
            if($newImagePath !== null){
                $oldPath = '';
                $q = mysqli_prepare($conn, "SELECT image_path FROM services WHERE service_id=?");
                mysqli_stmt_bind_param($q,"i",$service_id); mysqli_stmt_execute($q);
                $rr = mysqli_stmt_get_result($q); $old = $rr ? mysqli_fetch_assoc($rr) : null; mysqli_stmt_close($q);
                $oldPath = $old['image_path'] ?? '';
                $stmt = mysqli_prepare($conn, "UPDATE services SET service_name=?, image_path=? WHERE service_id=?");
                mysqli_stmt_bind_param($stmt, "ssi", $service_name, $newImagePath, $service_id);
                $ok = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                if($ok && $oldPath && strpos($oldPath,'uploads/services/') === 0 && is_file(__DIR__.'/../'.$oldPath)) @unlink(__DIR__.'/../'.$oldPath);
                if($ok){
                    if(save_service_fields($conn, $service_id, $selected_fields)) $success = "Service updated successfully.";
                    else $error = "Service updated, but customer fields could not be saved.";
                } else { $error = "Service update failed."; @unlink(__DIR__.'/../'.$newImagePath); }
            }else{
                $stmt = mysqli_prepare($conn, "UPDATE services SET service_name=? WHERE service_id=?");
                mysqli_stmt_bind_param($stmt, "si", $service_name, $service_id);
                if(mysqli_stmt_execute($stmt)){
                    if(save_service_fields($conn, $service_id, $selected_fields)) $success = "Service updated successfully.";
                    else $error = "Service updated, but customer fields could not be saved.";
                } else $error = "Service update failed.";
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// REMOVE SERVICE
// A service can be permanently removed only when it is not used by queue/customer records.
if(isset($_POST['remove_service'])){
    $service_id = (int)($_POST['service_id'] ?? 0);

    $service_name = '';
    $stmt = mysqli_prepare($conn, "SELECT service_name FROM services WHERE service_id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $service_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if($row = mysqli_fetch_assoc($result)) $service_name = $row['service_name'];
    mysqli_stmt_close($stmt);

    if($service_name === ''){
        $error = "Service not found.";
    }else{
        $queue_count = 0;
        $customer_count = 0;

        // queue_records uses service_id in this project.
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM queue_records WHERE service_id=?");
        if($stmt){
            mysqli_stmt_bind_param($stmt, "i", $service_id);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);
            $queue_count = (int)(mysqli_fetch_assoc($r)['total'] ?? 0);
            mysqli_stmt_close($stmt);
        }

        // customers still stores the service name for reporting/backward compatibility.
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM customers WHERE LOWER(TRIM(service_type)) = LOWER(TRIM(?))");
        if($stmt){
            mysqli_stmt_bind_param($stmt, "s", $service_name);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);
            $customer_count = (int)(mysqli_fetch_assoc($r)['total'] ?? 0);
            mysqli_stmt_close($stmt);
        }

        if($queue_count > 0 || $customer_count > 0){
            $error = "Cannot remove '$service_name' because it is already used by " . ($queue_count + $customer_count) . " record(s). Deactivate it instead.";
        }else{
            $df = mysqli_prepare($conn, "DELETE FROM service_fields WHERE service_id=?");
            mysqli_stmt_bind_param($df, "i", $service_id);
            mysqli_stmt_execute($df);
            mysqli_stmt_close($df);

            $stmt = mysqli_prepare($conn, "DELETE FROM services WHERE service_id=?");
            mysqli_stmt_bind_param($stmt, "i", $service_id);
            if(mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0){
                $success = "Service '$service_name' removed successfully.";
            }else{
                $error = "Service could not be removed.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

$services = mysqli_query($conn, "SELECT service_id, service_name, status, created_at, image_path FROM services WHERE LOWER(TRIM(service_name)) <> 'atm service' ORDER BY service_name ASC, service_id ASC");
$service_field_map = [];
$field_result = mysqli_query($conn, "SELECT service_id, field_key FROM service_fields ORDER BY service_id, field_id");
if($field_result){
    while($fr = mysqli_fetch_assoc($field_result)) $service_field_map[(int)$fr['service_id']][] = $fr['field_key'];
}
$services_error = $services ? '' : mysqli_error($conn);
?>

<!DOCTYPE html>

<html>

<head>
    <link rel="stylesheet" href="css/services.css">

<title>
Service Management
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


<div class="d-flex justify-content-between">


<h2>
🛠️ Service Management
</h2>


<a
href="dashboard.php"
class="btn btn-secondary">

⬅ Dashboard

</a>


</div>


<hr>

<?php if($services_error!=""){ ?>
<div class="alert alert-danger">
<strong>Database error:</strong> <?php echo htmlspecialchars($services_error); ?><br>
If the <code>services</code> table is missing, run <code>database/admin_management_patch.sql</code> in phpMyAdmin.
</div>
<?php } ?>

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



<!-- ADD SERVICE -->

<div class="card shadow mb-4">


<div class="card-header bg-primary text-white">

<h4>
➕ Add New Service
</h4>

</div>


<div class="card-body">


<form method="POST" enctype="multipart/form-data"
class="row g-3">


<div class="col-md-9">


<label class="fw-bold">

Service Name

</label>


<input
type="text"
name="service_name"
class="form-control"
placeholder="Example: ATM Service"
required>


</div>


<div class="col-12">
<label class="fw-bold">Customer Information Required for This Service</label>
<div class="service-field-picker">
<?php foreach($available_fields as $key=>$label){ ?>
<label class="field-check"><input type="checkbox" name="service_fields[]" value="<?php echo htmlspecialchars($key); ?>"> <span><?php echo htmlspecialchars($label); ?></span></label>
<?php } ?>
</div>
<small class="text-muted">Select only the information the customer should provide. Unselected fields will be hidden.</small>
</div>

<div class="col-md-3">
<label class="fw-bold">Optional Service Image</label>
<input type="file" name="service_image" class="form-control" accept="image/jpeg,image/png,image/webp">
<small class="text-muted">JPG, PNG or WEBP · max 5 MB</small>
</div>

<div class="col-md-3 d-flex align-items-end">


<button
type="submit"
name="add_service"
class="btn btn-success w-100">

ADD SERVICE

</button>


</div>


</form>


</div>

</div>



<!-- SERVICE LIST -->

<div class="card shadow">


<div class="card-header bg-dark text-white">

<h4>
📋 Available Services
</h4>

</div>


<div class="card-body">


<div class="table-responsive">


<table class="table table-bordered table-hover align-middle">


<thead class="table-dark">

<tr>

<th>#</th>

<th>Service Name</th>

<th>Status</th>

<th>Action</th>

</tr>

</thead>


<tbody>


<?php while($row=mysqli_fetch_assoc($services)){ ?>


<tr>


<td>

<?php echo $row['service_id']; ?>

</td>


<td>

<strong>

<?php echo htmlspecialchars($row['service_name']); ?>

</strong>

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


<form method="POST" class="d-inline">

<input type="hidden" name="service_id" value="<?php echo $row['service_id']; ?>">

<button
type="submit"
name="toggle_service"
class="btn btn-warning btn-sm"
onclick="return confirm('Change service status?');">

<?php echo ($row['status']=="Active") ? "Deactivate" : "Activate"; ?>

</button>

</form>


<button
type="button"
class="btn btn-primary btn-sm"

data-bs-toggle="modal"

data-bs-target="#edit<?php echo $row['service_id']; ?>">

✏️ Edit

</button>

<form method="POST" class="d-inline" onsubmit="return confirm('Permanently remove this service? This cannot be undone.');">

<input type="hidden" name="service_id" value="<?php echo $row['service_id']; ?>">

<button
type="submit"
name="remove_service"
class="btn btn-danger btn-sm">
🗑️ Remove
</button>

</form>

</td>


</tr>



<!-- EDIT MODAL -->

<div
class="modal fade"

id="edit<?php echo $row['service_id']; ?>"

tabindex="-1">


<div class="modal-dialog">


<div class="modal-content">


<div class="modal-header">

<h5 class="modal-title">

Edit Service

</h5>


<button
type="button"
class="btn-close"
data-bs-dismiss="modal">

</button>

</div>


<form method="POST" enctype="multipart/form-data">


<div class="modal-body">


<input
type="hidden"
name="service_id"

value="<?php echo $row['service_id']; ?>">


<label class="fw-bold">

Service Name

</label>


<input
type="text"
name="service_name"

class="form-control"

value="<?php echo htmlspecialchars($row['service_name']); ?>"

required>

<label class="fw-bold mt-3">Customer Information Required</label>
<div class="service-field-picker">
<?php foreach($available_fields as $key=>$label){ ?>
<label class="field-check"><input type="checkbox" name="edit_service_fields[]" value="<?php echo htmlspecialchars($key); ?>" <?php echo in_array($key, $service_field_map[(int)$row['service_id']] ?? [], true) ? 'checked' : ''; ?>> <span><?php echo htmlspecialchars($label); ?></span></label>
<?php } ?>
</div>
<small class="text-muted d-block mt-2">Only selected fields will appear on the customer registration page.</small>

<small class="text-muted d-block mt-2">Current image: <?php echo !empty($row['image_path']) ? 'available' : 'none'; ?></small>
<label class="fw-bold mt-2">Replace Image (optional)</label>
<input type="file" name="edit_service_image" class="form-control" accept="image/jpeg,image/png,image/webp">
<small class="text-muted">Leave empty to keep the current image.</small>

</div>


<div class="modal-footer">


<button
type="button"
class="btn btn-secondary"
data-bs-dismiss="modal">

Cancel

</button>


<button
type="submit"
name="update_service"

class="btn btn-primary">

UPDATE

</button>


</div>


</form>


</div>

</div>

</div>


<?php } ?>


</tbody>

</table>


</div>


</div>

</div>


</div>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>