<?php
/**
 * API: Check if an email already exists in the users table.
 * GET ?email=someone@example.com
 * Returns JSON: { "exists": true/false }
 */
require_once '../config/db_connect.php';

header('Content-Type: application/json');

$email = trim($_GET['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['exists' => false, 'error' => 'Invalid email']);
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
$stmt->execute([$email]);
$count = $stmt->fetchColumn();

echo json_encode(['exists' => $count > 0]);
