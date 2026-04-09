<?php
require_once '../includes/session_manager.php';
require_once '../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    // Base query for user information
    $query = "SELECT u.id, u.username, u.first_name, u.middle_name, u.last_name, 
                     u.email, u.phone, u.role, u.status, u.created_at";

    // Add role-specific joins
    if ($role === 'student') {
        $query .= ", s.student_id, s.department_id, s.batch as batch_year, s.current_semester, 
                     d.name as department_name
                     FROM users u
                     LEFT JOIN students s ON u.id = s.user_id
                     LEFT JOIN departments d ON s.department_id = d.id";
    } elseif ($role === 'department_head') {
        $query .= ", d.id as department_id, d.name as department_name
                     FROM users u
                     LEFT JOIN departments d ON u.id = d.head_user_id";
    } else {
        $query .= " FROM users u";
    }

    $query .= " WHERE u.id = :user_id";

    $stmt = $pdo->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    // Format the response
    $response = [
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'first_name' => $user['first_name'],
            'middle_name' => $user['middle_name'],
            'last_name' => $user['last_name'],
            'full_name' => trim($user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name']),
            'email' => $user['email'] ?? 'N/A',
            'phone' => $user['phone'] ?? 'N/A',
            'role' => $user['role'],
            'status' => $user['status'],
            'created_at' => $user['created_at'],
            'member_since' => date('F Y', strtotime($user['created_at']))
        ]
    ];

    // Add role-specific data
    if ($role === 'student') {
        $response['user']['student_id'] = $user['student_id'] ?? 'N/A';
        $response['user']['department'] = $user['department_name'] ?? 'N/A';
        $response['user']['batch_year'] = $user['batch_year'] ?? 'N/A';
        $response['user']['semester'] = $user['current_semester'] ?? 'N/A';
    } elseif ($role === 'department_head') {
        $response['user']['department'] = $user['department_name'] ?? 'N/A';
    }

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>