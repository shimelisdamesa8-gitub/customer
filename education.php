<?php
require_once __DIR__ . "/../config/language.php";
require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/education_setup.php";

if (!isset($_SESSION['user_id']) || (int)($_SESSION['role_id'] ?? 0) !== 1) {
    header("Location: ../login.php");
    exit();
}



$uploadDir = __DIR__ . '/../uploads/education/';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

$allowed = [
    'mp4' => ['type' => 'Video', 'mime' => ['video/mp4']],
    'webm' => ['type' => 'Video', 'mime' => ['video/webm']],
    'ogg' => ['type' => 'Video', 'mime' => ['video/ogg']],
    'pdf' => ['type' => 'PDF', 'mime' => ['application/pdf']],
    'doc' => ['type' => 'Document', 'mime' => ['application/msword', 'application/octet-stream']],
    'docx' => ['type' => 'Document', 'mime' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip']],
    'ppt' => ['type' => 'Presentation', 'mime' => ['application/vnd.ms-powerpoint', 'application/octet-stream']],
    'pptx' => ['type' => 'Presentation', 'mime' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip']],
    'txt' => ['type' => 'Other', 'mime' => ['text/plain']],
];
$maxSize = 100 * 1024 * 1024;
$error = '';
$success = '';

function education_clean($value) {
    return trim(preg_replace('/\s+/u', ' ', (string)$value));
}
function education_safe_filename($name) {
    $base = pathinfo($name, PATHINFO_FILENAME);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', $base);
    $base = trim($base, '_');
    if ($base === '') $base = 'resource';
    return $base . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
}
function education_upload($file, $uploadDir, $allowed, $maxSize) {
    if (!isset($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'message' => 'Please select a video, PDF, or document file.'];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'The selected file could not be uploaded.'];
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
        return ['ok' => false, 'message' => 'File size must be greater than 0 and not exceed 100 MB.'];
    }
    $original = basename($file['name'] ?? '');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) return ['ok' => false, 'message' => 'This file type is not supported.'];

    $mime = '';
    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $f ? (string)finfo_file($f, $file['tmp_name']) : '';
        if ($f) finfo_close($f);
    }
    if ($mime !== '' && !in_array($mime, $allowed[$ext]['mime'], true)) {
        // Some Office files are reported as application/zip; the extension check remains strict.
        if (!in_array($ext, ['docx','pptx'], true) || $mime !== 'application/zip') {
            return ['ok' => false, 'message' => 'The file content does not match its extension.'];
        }
    }

    $stored = education_safe_filename($original);
    $destination = $uploadDir . $stored;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['ok' => false, 'message' => 'The server could not save the uploaded file.'];
    }
    return ['ok' => true, 'stored' => $stored, 'original' => $original, 'mime' => $mime ?: ($allowed[$ext]['mime'][0]), 'type' => $allowed[$ext]['type'], 'size' => (int)$file['size']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['add_video', 'add_file'], true)) {
        $title = education_clean($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = education_clean($_POST['category'] ?? 'Digital Banking');
        if ($category === '') $category = 'Digital Banking';

        $field = $action === 'add_video' ? 'video_file' : 'document_file';
        $allowedForUpload = $action === 'add_video'
            ? array_intersect_key($allowed, array_flip(['mp4','webm','ogg']))
            : array_intersect_key($allowed, array_flip(['pdf','doc','docx','ppt','pptx','txt']));

        if ($title === '') {
            $error = 'Please enter an education title.';
        } elseif (mb_strlen($title) > 200) {
            $error = 'Title must be 200 characters or less.';
        } else {
            $up = education_upload($_FILES[$field] ?? null, $uploadDir, $allowedForUpload, $maxSize);
            if (!$up['ok']) {
                $error = $up['message'];
            } else {
                $relative = 'uploads/education/' . $up['stored'];
                $stmt = mysqli_prepare($conn, "INSERT INTO education_resources (title, description, category, resource_type, file_name, file_path, mime_type, file_size, status, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)");
                mysqli_stmt_bind_param($stmt, 'sssssssii', $title, $description, $category, $up['type'], $up['original'], $relative, $up['mime'], $up['size'], $_SESSION['user_id']);
                if (mysqli_stmt_execute($stmt)) {
                    $success = ($action === 'add_video')
                        ? 'Video uploaded successfully and published on the website.'
                        : 'File uploaded successfully and published on the website.';
                } else {
                    @unlink($uploadDir . $up['stored']);
                    $error = 'Database error while saving the education resource.';
                }
                mysqli_stmt_close($stmt);
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['education_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "UPDATE education_resources SET status=IF(status='Active','Inactive','Active') WHERE education_id=?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $success = mysqli_stmt_affected_rows($stmt) > 0 ? 'Resource status updated.' : 'Resource not found.';
        mysqli_stmt_close($stmt);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['education_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "SELECT file_path FROM education_resources WHERE education_id=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($r);
        mysqli_stmt_close($stmt);
        if (!$row) {
            $error = 'Resource not found.';
        } else {
            $stmt = mysqli_prepare($conn, "DELETE FROM education_resources WHERE education_id=?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (mysqli_stmt_execute($stmt)) {
                $file = __DIR__ . '/../' . ltrim($row['file_path'], '/');
                if (is_file($file)) @unlink($file);
                $success = 'Education resource deleted successfully.';
            } else {
                $error = 'Could not delete the education resource.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

$resources = mysqli_query($conn, "SELECT education_id, title, description, category, resource_type, file_name, file_path, file_size, status, created_at FROM education_resources ORDER BY created_at DESC, education_id DESC");
function human_size($bytes) {
    $bytes = (float)$bytes;
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return number_format($bytes) . ' B';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="css/education.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Education Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h2 class="mb-1">🎓 Education Management</h2><div class="text-muted">Upload and publish banking education materials for customers.</div></div>
        <a href="dashboard.php" class="btn btn-secondary">⬅ Dashboard</a>
    </div>

    <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="hero shadow mb-4">
        <div class="row align-items-center g-3">
            <div class="col-lg-8"><h3>📚 Customer Banking Education</h3><p class="mb-0">Add videos, PDFs, Word documents and presentations. Active resources appear automatically in the public website's Banking Education section.</p></div>
            <div class="col-lg-4 text-lg-end"><span class="badge bg-light text-primary p-2">Dynamic Website Content</span></div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 upload-card video-card">
                <div class="card-header video-header text-white"><h5 class="mb-0">💾 Save & Publish</h5></div>
                <div class="card-body">
                    <p class="text-muted">Upload customer education videos separately. Supported: MP4, WebM, OGG.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_video">
                        <div class="mb-3"><label class="form-label fw-semibold">Video Title</label><input type="text" name="title" class="form-control" maxlength="200" required placeholder="e.g. How to Use Mobile Banking"></div>
                        <div class="row g-3"><div class="col-md-6"><label class="form-label fw-semibold">Category</label><input type="text" name="category" class="form-control" maxlength="100" value="Digital Banking"></div><div class="col-md-6"><label class="form-label fw-semibold">Choose Video</label><input type="file" name="video_file" class="form-control" required accept="video/mp4,video/webm,video/ogg,.mp4,.webm,.ogg"></div></div>
                        <div class="mt-3"><label class="form-label fw-semibold">Description</label><textarea name="description" class="form-control" rows="3" placeholder="Explain what customers will learn from this video."></textarea></div>
                        <div class="small-muted mt-2">Maximum video size: 100 MB.</div><button class="btn btn-video text-white mt-3 px-4">💾 Save & Publish</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 upload-card file-card">
                <div class="card-header file-header text-white"><h5 class="mb-0">💾 Save & Publish</h5></div>
                <div class="card-body">
                    <p class="text-muted">Upload PDFs and other banking education documents separately from videos.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_file">
                        <div class="mb-3"><label class="form-label fw-semibold">File Title</label><input type="text" name="title" class="form-control" maxlength="200" required placeholder="e.g. Digital Banking User Guide"></div>
                        <div class="row g-3"><div class="col-md-6"><label class="form-label fw-semibold">Category</label><input type="text" name="category" class="form-control" maxlength="100" value="Digital Banking"></div><div class="col-md-6"><label class="form-label fw-semibold">Choose File</label><input type="file" name="document_file" class="form-control" required accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,application/pdf"></div></div>
                        <div class="mt-3"><label class="form-label fw-semibold">Description</label><textarea name="description" class="form-control" rows="3" placeholder="Explain what customers will learn from this document."></textarea></div>
                        <div class="small-muted mt-2">Supported: PDF, DOC, DOCX, PPT, PPTX, TXT. Maximum size: 100 MB.</div><button class="btn btn-file text-white mt-3 px-4">💾 Save & Publish</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3"><h4 class="mb-0">Published & Saved Resources</h4><span class="text-muted">Active items are visible to customers.</span></div>
    <div class="row g-4">
    <?php if ($resources && mysqli_num_rows($resources) > 0): while ($r = mysqli_fetch_assoc($resources)): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card resource-card shadow-sm p-3">
                <div class="d-flex justify-content-between align-items-start"><div class="file-icon"><?php echo $r['resource_type']==='Video'?'🎥':($r['resource_type']==='PDF'?'📕':($r['resource_type']==='Presentation'?'📊':'📄')); ?></div><span class="badge <?php echo $r['status']==='Active'?'bg-success':'bg-secondary'; ?>"><?php echo htmlspecialchars($r['status']); ?></span></div>
                <span class="badge bg-primary-subtle text-primary badge-type mb-2 align-self-start"><?php echo htmlspecialchars($r['resource_type']); ?> · <?php echo htmlspecialchars($r['category']); ?></span>
                <h5><?php echo htmlspecialchars($r['title']); ?></h5>
                <p class="text-muted small"><?php echo nl2br(htmlspecialchars($r['description'])); ?></p>
                <div class="small-muted mb-3"><?php echo htmlspecialchars($r['file_name']); ?> · <?php echo human_size($r['file_size']); ?></div>
                <div class="mt-auto d-flex flex-wrap gap-2">
                    <a href="../<?php echo htmlspecialchars($r['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">Open</a>
                    <form method="POST"><input type="hidden" name="action" value="toggle"><input type="hidden" name="education_id" value="<?php echo (int)$r['education_id']; ?>"><button class="btn btn-sm btn-outline-warning"><?php echo $r['status']==='Active'?'Hide':'Publish'; ?></button></form>
                    <form method="POST" onsubmit="return confirm('Delete this education resource?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="education_id" value="<?php echo (int)$r['education_id']; ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
                </div>
            </div>
        </div>
    <?php endwhile; else: ?>
        <div class="col-12"><div class="alert alert-info">No education resources have been uploaded yet.</div></div>
    <?php endif; ?>
    </div>
</div>
</body>
</html>
