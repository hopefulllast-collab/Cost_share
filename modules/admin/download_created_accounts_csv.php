<?php
/**
 * download_created_accounts_csv.php
 * Downloads all newly created accounts (stored in session) as a CSV file.
 * Clears the session data after download.
 * Access: Admin only.
 */
require_once '../../includes/auth_check.php';
checkAuth(['admin']);

// Handle "Clear List" action
if (isset($_GET['clear'])) {
    unset($_SESSION['created_accounts']);
    header("Location: create_account.php?msg=" . urlencode("<span data-en='Account list cleared.' data-am='የመለያ ዝርዝሩ ተጽድቷል።'>Account list cleared.</span>"));
    exit();
}

// Check if there are accounts to download
if (empty($_SESSION['created_accounts'])) {
    header("Location: create_account.php?error=" . urlencode("<span data-en='No new accounts to download.' data-am='ለማውረድ አዲስ መለያዎች የሉም።'>No new accounts to download.</span>"));
    exit();
}

$accounts = $_SESSION['created_accounts'];

// Stream CSV to browser
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=created_accounts_' . date('Y-m-d_H-i') . '.csv');

$output = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV header row
fputcsv($output, ['Username', 'Password', 'Role', 'First Name', 'Middle Name', 'Last Name', 'Phone', 'Email', 'Student ID']);

// Data rows
foreach ($accounts as $account) {
    fputcsv($output, [
        $account['username'],
        $account['password'],
        $account['role'],
        $account['first_name'],
        $account['middle_name'],
        $account['last_name'],
        $account['phone'],
        $account['email'],
        $account['student_id']
    ]);
}

fclose($output);

// Clear session data after download
unset($_SESSION['created_accounts']);
exit();
?>
