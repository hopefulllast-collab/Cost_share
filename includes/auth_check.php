<?php
require_once __DIR__ . '/session_manager.php';

function checkAuth($allowed_roles = [])
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../../index.php");
        exit;
    }

    if (!empty($allowed_roles) && !in_array($_SESSION['role'] ?? '', $allowed_roles)) {
        // Unauthorized access
        die("Access Denied: You do not have permission to view this page.");
    }
}
?>