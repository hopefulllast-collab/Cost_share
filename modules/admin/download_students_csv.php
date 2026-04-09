<?php
/**
 * download_students_csv.php
 * Downloads all new (not yet downloaded) students as a CSV file.
 * Marks downloaded students so they are not re-exported on the next run.
 * Access: Admin only.
 */
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['admin']);

// Fetch new students who have been sent but not yet downloaded
$sql = "SELECT s.id as db_id, s.student_id, s.first_name, s.middle_name, s.last_name,
               s.sex, '' AS email, d.name as dept_name, s.batch, s.current_semester
        FROM students s
        JOIN departments d ON s.department_id = d.id
        WHERE s.is_sent_to_others = 1 AND s.is_downloaded = 0
        ORDER BY d.name, s.batch, s.first_name";

$stmt = $pdo->query($sql);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($students)) {
    header("Location: view_students.php?msg=No new students to download");
    exit();
}

// Mark fetched students as downloaded
$ids = array_column($students, 'db_id');
if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $updateStmt = $pdo->prepare("UPDATE students SET is_downloaded = 1 WHERE id IN ($placeholders)");
    $updateStmt->execute($ids);
}

// Stream CSV to browser
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=new_students_' . date('Y-m-d_H-i') . '.csv');

$output = fopen('php://output', 'w');

// CSV header row
fputcsv($output, ['Student ID', 'First Name', 'Middle Name', 'Last Name', 'Sex', 'Email', 'Department', 'Batch Year', 'Semester']);

// Data rows (db_id is internal — exclude from CSV)
foreach ($students as $row) {
    unset($row['db_id']);
    fputcsv($output, $row);
}

fclose($output);
exit();
?>