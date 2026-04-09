<?php
require_once '../includes/session_manager.php';
require_once '../config/db_connect.php';

// Auth check - only registrar and admin can search
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['registrar', 'admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$student_id = trim($_GET['student_id'] ?? '');

if (empty($student_id)) {
    echo json_encode(['success' => false, 'error' => 'Student ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT s.student_id, s.user_id, s.first_name, s.middle_name, s.last_name, s.sex, 
               s.department_id, d.name as department_name, s.batch, 
               s.current_semester, s.academic_year, s.status
        FROM students s
        LEFT JOIN departments d ON s.department_id = d.id
        WHERE s.student_id = ?
        LIMIT 1
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        echo json_encode([
            'success' => true,
            'student' => $student
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Student not found'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
