<?php
/**
 * upload_csv.php
 * Processes a CSV file upload to bulk-create user accounts.
 *
 * Supported CSV formats:
 *   Format A (Admin): Role, FirstName, MiddleName, LastName, Phone, Email,
 *                     StudentID, DepartmentName, BatchYear, Semester  (10 cols)
 *   Format B (Student List Export): StudentID, FirstName, MiddleName, LastName,
 *                                   Sex, DepartmentName, BatchYear, Semester  (8 cols)
 *
 * Access: Admin only.
 */
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
require_once '../../includes/mailer.php';
checkAuth(['admin']);

// Increase payload timeout in case of 100+ emails taking a minute or two
set_time_limit(600);

// ─── Helper: Parse a single CSV row into a unified associative array ──────────
function parse_csv_row(array $row): array
{
    $known_roles = ['student', 'registrar', 'department_head', 'cost_sharing_pro', 'transcript_pro', 'admin'];
    
    // Format B heuristic: 8 to 15 columns (to allow for invisible trailing excel columns) 
    $first_col_lower = strtolower(trim($row[0] ?? ''));
    $raw_count = count($row);
    
    if (($raw_count >= 8 && $raw_count <= 15) && !in_array($first_col_lower, $known_roles)) {
        return [
            'format' => 'student_list',
            'role' => 'student',
            'student_id' => trim($row[0] ?? ''),
            'first_name' => trim($row[1] ?? ''),
            'middle_name' => trim($row[2] ?? ''),
            'last_name' => trim($row[3] ?? ''),
            'phone' => '',
            'email' => trim($row[5] ?? ''),
            'dept_name' => trim($row[6] ?? ''),
            'batch' => trim($row[7] ?? ''),
            'semester' => trim($row[8] ?? ''),
            'raw_debug' => print_r($row, true)
        ];
    }

    // Format A (Admin layout)
    return [
        'format' => 'admin',
        'role' => $first_col_lower,
        'first_name' => trim($row[1] ?? ''),
        'middle_name' => trim($row[2] ?? ''),
        'last_name' => trim($row[3] ?? ''),
        'phone' => trim($row[4] ?? ''),
        'email' => trim($row[5] ?? ''),
        'student_id' => trim($row[6] ?? ''),
        'dept_name' => trim($row[7] ?? ''),
        'batch' => trim($row[8] ?? 1),
        'semester' => trim($row[9] ?? 1),
        'raw_debug' => print_r($row, true)
    ];
}
// ─────────────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    header("Location: create_account.php");
    exit();
}

$filename = $_FILES['csv_file']['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    header("Location: create_account.php?error=" . urlencode("እባክዎ ትክክለኛ የ CSV ፋይል ብቻ ይጠቀሙ (Excel .xlsx አይፈቀድም)። - Please upload a valid CSV file."));
    exit();
}

// Ensure PHP can handle Mac line endings
ini_set('auto_detect_line_endings', TRUE);

$file = $_FILES['csv_file']['tmp_name'];
$handle = fopen($file, "r");

if ($handle === false) {
    header("Location: create_account.php?error=" . urlencode("Error opening uploaded file."));
    exit();
}

// Magic Bytes check to see if the user just renamed an .xlsx file to .csv
$magic_bytes = fread($handle, 4);
if ($magic_bytes === "PK\x03\x04") {
    fclose($handle);
    header("Location: create_account.php?error=" . urlencode("እባክዎ ፋይሉን በድጋሚ Save As ብለው ወደ 'CSV (Comma delimited)' ይቀይሩት። (የ Excel ፋይልን መኮረጅ/ማሳሳት ወይም ሪኔም ማድረግ አይቻልም!) - You uploaded an Excel .xlsx file renamed as .csv. Please use Save As -> CSV."));
    exit();
}
rewind($handle);

// Strip BOM if present
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle); // No BOM, rewind to start
} else {
    // BOM present, don't rewind, stay after the BOM
}

// Detect delimiter (comma vs semicolon)
$first_line_pos = ftell($handle);
$first_line = fgets($handle);
$delimiter = ',';
if ($first_line !== false) {
    if (strpos($first_line, "\t") !== false) {
        $delimiter = "\t";
    } elseif (strpos($first_line, ';') !== false && strpos($first_line, ',') === false) {
        $delimiter = ';';
    }
}
fseek($handle, $first_line_pos); // Return to the start of the first line inside the loop

fgetcsv($handle, 0, $delimiter); // Skip header row
$rows = [];
while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
    if (empty(array_filter($data, 'trim'))) continue;
    $data = array_map(function($val) {
        return trim(str_replace("\0", "", $val));
    }, $data);
    $rows[] = $data;
}
fclose($handle);

// Build department name → id lookup map
$dept_map = [];
$deptStmt = $pdo->query("SELECT id, name FROM departments");
while ($dept = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
    $dept_map[strtolower(trim($dept['name']))] = $dept['id'];
}

$success_count = 0;
$errors = [];

$pdo->beginTransaction();

try {
    foreach ($rows as $index => $row) {
        $line = $index + 2; // +1 for header, +1 to 1-index

        $parsed = parse_csv_row($row);
        $role = $parsed['role'];
        $fname = $parsed['first_name'];
        $mname = $parsed['middle_name'];
        $lname = $parsed['last_name'];
        $phone = $parsed['phone'];
        $dept_name = strtolower($parsed['dept_name']);
        
        $debug_str = htmlspecialchars($parsed['raw_debug'] ?? '');

        // Validate: names must contain letters only or spaces
        if (empty($fname) || empty($mname) || empty($lname)) {
             $errors[] = "<span data-en='Line $line: Names cannot be empty. Debug: $debug_str' data-am='መስመር $line: ስሞች ባዶ መሆን አይችሉም። (Debug: $debug_str)'>Line $line: Names cannot be empty. <b>(System parsed: $debug_str)</b></span>";
        } elseif (!preg_match('/^[A-Za-z\s]+$/', $fname) || !preg_match('/^[A-Za-z\s]+$/', $mname) || !preg_match('/^[A-Za-z\s]+$/', $lname)) {
            $errors[] = "<span data-en='Line $line: Names must contain letters only. (You provided: $fname, $mname, $lname)' data-am='መስመር $line: ስሞች ፊደላት ብቻ መሆን አለባቸው።'>Line $line: Names must contain letters only. (You provided: $fname, $mname, $lname)</span>";
        }

        // Validate: phone format for non-students (if provided)
        if ($role !== 'student' && !empty($phone)) {
            if (!preg_match('/^(\+2519|\+2517)[0-9]{8}$/', $phone)) {
                $errors[] = "<span data-en='Line $line: Invalid phone format.' data-am='መስመር $line: የተሳሳተ ስልክ ቁጥር ቅርጸት።'>Line $line: Invalid phone format.</span>";
            }
        }

        // Validate: department head uniqueness
        if ($role === 'department_head') {
            if (!isset($dept_map[$dept_name])) {
                $errors[] = "<span data-en='Line $line: Unknown department \"$dept_name\".' data-am='መስመር $line: ያልታወቀ የትምህርት ክፍል \"$dept_name\"።'>Line $line: Unknown department \"$dept_name\".</span>";
            } else {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = ? AND head_user_id IS NOT NULL");
                $checkStmt->execute([$dept_map[$dept_name]]);
                if ($checkStmt->fetchColumn() > 0) {
                    $errors[] = "<span data-en='Line $line: Department already has a head.' data-am='መስመር $line: የትምህርት ክፍሉ አስቀድሞ ኃላፊ አለው።'>Line $line: Department already has a head.</span>";
                }
            }
        }

        // Validate: single-account roles
        if (in_array($role, ['registrar', 'transcript_pro', 'cost_sharing_pro'])) {
            $roleStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
            $roleStmt->execute([$role]);
            if ($roleStmt->fetchColumn() > 0) {
                $errors[] = "<span data-en='Line $line: Cannot create more than one account for role \"$role\".' data-am='መስመር $line: ለ \"$role\" ሚና ከአንድ በላይ መለያ መፍጠር አይቻልም።'>Line $line: Cannot create more than one account for role \"$role\".</span>";
            }
        }

        // Stop validation on first error (reject the whole file)
        if (!empty($errors))
            break;
    }

    if (!empty($errors)) {
        $err_msg = urlencode("Errors: " . implode(", ", $errors));
        header("Location: create_account.php?error=$err_msg");
        exit();
    }

    // ── Phase 2: Insert all rows (validation passed) ─────────────────────────
    foreach ($rows as $raw_row) {
        $parsed = parse_csv_row($raw_row);

        $role = $parsed['role'];
        $fname = $parsed['first_name'];
        $mname = $parsed['middle_name'];
        $lname = $parsed['last_name'];
        $phone = $parsed['phone'];
        $email = $parsed['email'];
        $student_id = $parsed['student_id'];
        $dept_name = strtolower($parsed['dept_name']);
        $batch = $parsed['batch'];
        $semester = $parsed['semester'];

        $username = strtolower($fname) . rand(10, 99);
        $password_raw = ($role === 'student') ? $student_id : 'password';
        $hashed = password_hash($password_raw, PASSWORD_DEFAULT);
        $email_insert = !empty($email) ? $email : null;
        $phone_insert = !empty($phone) ? $phone : null;

        // Insert into users table
        $insertUser = $pdo->prepare("INSERT INTO users
            (username, password, role, first_name, middle_name, last_name, email, phone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insertUser->execute([$username, $hashed, $role, $fname, $mname, $lname, $email_insert, $phone_insert]);
        $user_id = $pdo->lastInsertId();

        // Role-specific linking
        if ($role === 'student') {
            $dept_id = $dept_map[$dept_name] ?? null;
            if ($dept_id) {
                // Compute current Ethiopian Calendar year
                $now = new DateTime();
                $gcYear = (int) $now->format('Y');
                $gcMonth = (int) $now->format('n');
                $gcDay = (int) $now->format('j');
                $ethYear = ($gcMonth < 9 || ($gcMonth == 9 && $gcDay < 11)) ? $gcYear - 8 : $gcYear - 7;

                $findStudent = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
                $findStudent->execute([$student_id]);
                $sid_db = $findStudent->fetchColumn();

                if ($sid_db) {
                    $pdo->prepare("UPDATE students
                        SET user_id = ?, department_id = ?, batch = ?, current_semester = ?, academic_year = COALESCE(academic_year, ?), admission_year = COALESCE(admission_year, ?)
                        WHERE id = ?")
                        ->execute([$user_id, $dept_id, $batch, $semester, $ethYear, $ethYear, $sid_db]);
                } else {
                    $pdo->prepare("INSERT INTO students
                        (user_id, student_id, department_id, batch, current_semester, academic_year, admission_year)
                        VALUES (?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$user_id, $student_id, $dept_id, $batch, $semester, $ethYear, $ethYear]);
                }
            }
        } elseif ($role === 'department_head') {
            $dept_id = $dept_map[$dept_name] ?? null;
            if ($dept_id) {
                $pdo->prepare("UPDATE departments SET head_user_id = ? WHERE id = ?")
                    ->execute([$user_id, $dept_id]);
            }
        }

        // Store created account in session for CSV download
        if (!isset($_SESSION['created_accounts'])) {
            $_SESSION['created_accounts'] = [];
        }
        $_SESSION['created_accounts'][] = [
            'username' => $username,
            'password' => $password_raw,
            'role' => $role,
            'first_name' => $fname,
            'middle_name' => $mname,
            'last_name' => $lname,
            'phone' => $phone_insert ?? '',
            'email' => $email_insert ?? '',
            'student_id' => $student_id ?? ''
        ];

        $success_count++;
    }

    $pdo->commit();

    // Send emails in bulk post-commit so the database isn't locked while waiting for SMTP
    $emails_sent = 0;
    if (isset($_SESSION['created_accounts']) && is_array($_SESSION['created_accounts'])) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $login_link = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/Cost_share/index.php";

        foreach ($_SESSION['created_accounts'] as $acc) {
            if (!empty($acc['email'])) {
                $subject = "Welcome to DMU Cost Sharing System";
                $body = "Dear " . htmlspecialchars($acc['first_name']) . " " . htmlspecialchars($acc['last_name']) . ",<br><br>";
                $body .= "Your account for the DMU Cost Sharing System has been created successfully.<br><br>";
                $body .= "<b>Your Login Credentials:</b><br>";
                $body .= "Username: <b>" . htmlspecialchars($acc['username']) . "</b><br>";
                $body .= "Password: <b>" . htmlspecialchars($acc['password']) . "</b><br><br>";
                $body .= "We recommend that you change your password immediately upon first login.<br><br>";
                $body .= "<a href='{$login_link}'>Click here to login</a><br><br>";
                $body .= "Best Regards,<br>DMU System Administration";
                
                if (sendSystemEmail($acc['email'], $acc['first_name'] . ' ' . $acc['last_name'], $subject, $body)) {
                    $emails_sent++;
                }
            }
        }
    }

    $email_notice = ($emails_sent > 0) ? " ($emails_sent email credentials sent.)" : "";
    
    $msg = urlencode("<span data-en='Success! $success_count user(s) imported.$email_notice' data-am='በተሳካ ሁኔታ! $success_count ተጠቃሚ(ዎች) ገብተዋል።$email_notice'>Success! $success_count user(s) imported.</span>");
    header("Location: create_account.php?msg=$msg");
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $err_msg = urlencode("Error: " . $e->getMessage());
    header("Location: create_account.php?error=$err_msg");
    exit();
}
?>