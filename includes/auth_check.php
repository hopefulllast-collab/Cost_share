<?php
require_once __DIR__ . '/session_manager.php';

// Session timeout duration in seconds (4 minutes = 240 seconds)
define('SESSION_TIMEOUT', 60);

function checkAuth($allowed_roles = [])
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../../index.php");
        exit;
    }

    // --- Session Timeout Check ---
    if (isset($_SESSION['last_timestamp'])) {
        $elapsed = time() - $_SESSION['last_timestamp'];
        if ($elapsed > SESSION_TIMEOUT) {
            // Session has expired due to inactivity
            $role = $_SESSION['role'] ?? '';
            $sess_name = session_name();

            // Destroy the session completely
            $_SESSION = [];
            session_destroy();
            if ($sess_name) {
                setcookie($sess_name, "", time() - 3600, "/");
            }

            // Redirect to logout page with timeout message
            header("Location: ../../logout.php?reason=timeout&role=" . urlencode($role));
            exit;
        }
    }
    // Update last activity timestamp
    $_SESSION['last_timestamp'] = time();

    // --- Role Authorization Check ---
    if (!empty($allowed_roles) && !in_array($_SESSION['role'] ?? '', $allowed_roles)) {
        // Unauthorized access
        die("Access Denied: You do not have permission to view this page.");
    }
}
?>
