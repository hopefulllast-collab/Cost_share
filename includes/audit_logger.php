<?php
/**
 * Audit Logger Helper
 * Logs user actions to the audit_logs table for security and usage tracking.
 *
 * Usage: require_once 'audit_logger.php'; logAudit($pdo, 'ACTION_NAME', 'Description');
 */

function logAudit($pdo, $action, $description = '')
{
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['name'] ?? 'System';
    $role = $_SESSION['role'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, username, role, action, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $username, $role, $action, $description, $ip]);
    } catch (PDOException $e) {
        // Silently fail - logging should never break the application
        error_log("Audit log error: " . $e->getMessage());
    }
}
?>