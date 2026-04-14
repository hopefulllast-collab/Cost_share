<?php
// We do NOT require session_manager.php at the top because we manage session name manually here
require_once 'config/db_connect.php';
require_once 'includes/audit_logger.php';

$role_to_logout = $_GET['role'] ?? null;
$reason = $_GET['reason'] ?? null;

if ($role_to_logout) {
    $expected_sess = "DMU_" . strtoupper($role_to_logout);
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() !== $expected_sess) {
            session_write_close();
            session_name($expected_sess);
            session_start();
        }
    } else {
        session_name($expected_sess);
        session_start();
    }

    // Log before destroying
    if ($reason === 'timeout') {
        logAudit($pdo, 'SESSION_TIMEOUT', 'Session expired due to inactivity for role: ' . $role_to_logout);
    } else {
        logAudit($pdo, 'LOGOUT', 'User logged out from ' . $role_to_logout);
    }

    $_SESSION = [];
    session_destroy();
    setcookie($expected_sess, "", time() - 3600, "/");
} else {
    // If somehow called without role, try to use referer to find active role
    require_once 'includes/session_manager.php';
    if (isset($active_role) && $active_role) {
        $expected_sess = "DMU_" . strtoupper($active_role);
        // session_manager.php already handles the active role session switch
        if ($reason === 'timeout') {
            logAudit($pdo, 'SESSION_TIMEOUT', 'Session expired due to inactivity for role: ' . $active_role);
        } else {
            logAudit($pdo, 'LOGOUT', 'User logged out from ' . $active_role);
        }
        $_SESSION = [];
        session_destroy();
        setcookie($expected_sess, "", time() - 3600, "/");
    } elseif (session_status() !== PHP_SESSION_NONE) {
        session_destroy();
    }
}

// Redirect to index with timeout flag if applicable
if ($reason === 'timeout') {
    header("Location: index.php?session_expired=1");
} else {
    header("Location: index.php");
}
exit;
?>