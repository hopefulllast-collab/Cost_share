<?php
/**
 * download_skipped_students_csv.php
 * Downloads all students skipped during bulk upload due to duplicate email.
 * Clears the session data after download.
 * Access: Admin only.
 */
require_once '../../includes/auth_check.php';
checkAuth(['admin']);

// Handle "Clear List" action
if (isset($_GET['clear'])) {
    unset($_SESSION['skipped_students']);
    header("Location: create_account.php?msg=" . urlencode("<span data-en='Skipped students list cleared.' data-am='የተዘለሉ ተማሪዎች ዝርዝር ጸድቷል።'>Skipped students list cleared.</span>"));
    exit();
}

// Check if there are accounts to download
if (empty($_SESSION['skipped_students'])) {
    header("Location: create_account.php?error=" . urlencode("<span data-en='No skipped students to download.' data-am='ለማውረድ ምንም የተዘለሉ ተማሪዎች የሉም።'>No skipped students to download.</span>"));
    exit();
}

$students = $_SESSION['skipped_students'];

// Stream CSV to browser
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=skipped_students_' . date('Y-m-d_H-i') . '.csv');

$output = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV header row
fputcsv($output, ['Student ID', 'First Name', 'Middle Name', 'Last Name', 'Email', 'Department', 'Batch', 'Reason for Skipping']);

// Data rows
foreach ($students as $student) {
    fputcsv($output, [
        $student['student_id'],
        $student['first_name'],
        $student['middle_name'],
        $student['last_name'],
        $student['email'],
        $student['department'],
        $student['batch'],
        $student['reason']
    ]);
}

fclose($output);

// Clear session data after download
unset($_SESSION['skipped_students']);
exit();
?>