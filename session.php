<?php
// Centralized secure session configuration.
// Must be included before session_start().
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Keep the logged-in user's role synchronized with the database.
// This makes admin promotion/demotion effective without waiting for logout.
if (isset($_SESSION['user_id'])) {
    try {
        $syncConn = @mysqli_connect('localhost', 'root', '', 'bank_queue_management_system');
        if ($syncConn) {
            mysqli_set_charset($syncConn, 'utf8mb4');
            $uid = (int)$_SESSION['user_id'];
            $q = mysqli_prepare($syncConn, "SELECT role_id, status, full_name FROM users WHERE user_id=? LIMIT 1");
            if ($q) {
                mysqli_stmt_bind_param($q, 'i', $uid);
                mysqli_stmt_execute($q);
                $r = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
                mysqli_stmt_close($q);
                if (!$r || ($r['status'] ?? '') !== 'Active') {
                    $_SESSION = [];
                    if (ini_get('session.use_cookies')) {
                        $params = session_get_cookie_params();
                        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
                    }
                    session_destroy();
                } else {
                    $_SESSION['role_id'] = (int)$r['role_id'];
                    $_SESSION['full_name'] = $r['full_name'];
                }
            }
            mysqli_close($syncConn);
        }
    } catch (Throwable $e) {
        // If the sync connection is unavailable, keep the current session.
        // The normal page/database connection will still enforce access.
    }
}
