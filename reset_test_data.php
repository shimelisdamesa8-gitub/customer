<?php
require_once __DIR__ . "/../config/language.php";
require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../config/database.php";

// Admin only
if (!isset($_SESSION['user_id']) || (int)$_SESSION['role_id'] !== 1) {
    header("Location: ../login.php");
    exit();
}

$error = '';
$success = '';

function table_exists(mysqli $conn, string $table): bool {
    $table = mysqli_real_escape_string($conn, $table);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return $result && mysqli_num_rows($result) > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_test_data'])) {
    $confirm = trim($_POST['confirm_text'] ?? '');

    if ($confirm !== 'RESET') {
        $error = 'Type RESET exactly to confirm the test-data reset.';
    } else {
        $tables = ['queue_records', 'customers', 'report_archive', 'activity_logs'];
        $cleared = [];
        $failed = [];

        mysqli_begin_transaction($conn);
        try {
            if (!mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0")) {
                throw new Exception('Could not disable foreign-key checks.');
            }

            foreach ($tables as $table) {
                if (table_exists($conn, $table)) {
                    if (!mysqli_query($conn, "TRUNCATE TABLE `$table`")) {
                        throw new Exception("Could not clear table: $table");
                    }
                    $cleared[] = $table;
                }
            }

            if (!mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1")) {
                throw new Exception('Could not restore foreign-key checks.');
            }

            mysqli_commit($conn);
            $success = 'Test data reset successfully. Customers, queue/service records, archived reports and activity logs are now zero. Admin/staff accounts and system configuration were kept.';
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");
            $error = $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <link rel="stylesheet" href="css/reset_test_data.css">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Test Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="container py-5" style="max-width:850px">
    <div class="card shadow-sm border-danger">
        <div class="card-header bg-danger text-white">
            <h3 class="mb-0">⚠️ Reset Test / Demo Data</h3>
        </div>
        <div class="card-body">
            <p>This action is for preparing the project for a fresh presentation/testing run.</p>
            <div class="alert alert-warning">
                <strong>It will permanently clear:</strong>
                <ul class="mb-0 mt-2">
                    <li>Registered customers</li>
                    <li>Queue/service records</li>
                    <li>Archived reports</li>
                    <li>Activity logs</li>
                </ul>
            </div>
            <div class="alert alert-success">
                <strong>It will keep:</strong>
                <ul class="mb-0 mt-2">
                    <li>Admin and staff accounts</li>
                    <li>Services</li>
                    <li>Priorities</li>
                    <li>Single branch configuration</li>
                </ul>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="post" onsubmit="return confirm('This will permanently delete all test/demo customers, queue records, archived reports and activity logs. Continue?');">
                <label class="form-label fw-bold">Type RESET to confirm</label>
                <input type="text" name="confirm_text" id="confirmText" class="form-control mb-2 text-uppercase" placeholder="RESET" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="5" pattern="RESET" title="Type RESET in uppercase letters only" required>
                <div id="capsHint" class="form-text mb-3">Only uppercase <strong>RESET</strong> is accepted.</div>
                <input type="hidden" name="reset_test_data" value="1">
                <button type="submit" class="btn btn-danger">🗑️ RESET TEST DATA</button>
                <a href="dashboard.php" class="btn btn-secondary ms-2">Cancel</a>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    const input = document.getElementById('confirmText');
    const hint = document.getElementById('capsHint');
    if (!input) return;

    input.addEventListener('input', function () {
        // Force uppercase so lowercase text can never be submitted.
        const pos = this.selectionStart;
        this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 5);
        try { this.setSelectionRange(pos, pos); } catch (e) {}
        hint.textContent = this.value === 'RESET'
            ? '✓ RESET is correct. You can continue.'
            : 'Only uppercase RESET is accepted.';
    });

    input.addEventListener('keydown', function (e) {
        if (e.key.length === 1 && !/[A-Za-z]/.test(e.key)) e.preventDefault();
    });

    input.addEventListener('paste', function () {
        setTimeout(() => {
            this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 5);
            this.dispatchEvent(new Event('input'));
        }, 0);
    });
})();
</script>
</body>
</html>
