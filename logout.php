<?php
// We do NOT require session_manager.php at the top because we manage session name manually here
require_once 'config/db_connect.php';
require_once 'includes/audit_logger.php';

$role_to_logout = $_GET['role'] ?? null;

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
    
    $_SESSION = [];
    session_destroy();
    setcookie($expected_sess, "", time() - 3600, "/");

    logAudit($pdo, 'LOGOUT', 'User logged out from ' . $role_to_logout);
} else {
    // If somehow called without role, try to use referer to find active role
    require_once 'includes/session_manager.php';
    if (isset($active_role) && $active_role) {
        $expected_sess = "DMU_" . strtoupper($active_role);
        // session_manager.php already handles the active role session switch
        $_SESSION = [];
        session_destroy();
        setcookie($expected_sess, "", time() - 3600, "/");
        logAudit($pdo, 'LOGOUT', 'User logged out from ' . $active_role);
    } elseif (session_status() !== PHP_SESSION_NONE) {
        session_destroy();
    }
}

header("Location: index.php");
exit;
?>