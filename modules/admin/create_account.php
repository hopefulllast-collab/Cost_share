<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
require_once '../../includes/audit_logger.php';
require_once '../../includes/mailer.php';
checkAuth(['admin']);

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = $_GET['error'] ?? "";

// Fetch Departments
$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $role = $_POST['role'];
    $fname = trim($_POST['first_name']);
    $mname = trim($_POST['middle_name']);
    $lname = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);

    // Auto-generate username (First Name)
    $username = strtolower($fname);
    // Handle duplicate usernames if any (simple append logic could be added, but per req: use firstname)
    // Note: Req says "taking the first name as a username". 
    // A more robust system would handle duplicates, but we'll stick to req.

    // Password Logic
    if ($role == 'student') {
        $student_id = trim($_POST['student_id']);
        $password_raw = $student_id; // Default password for students is their ID
    } else {
        // For staff, require password
        $password_raw = $_POST['password'] ?? '';
        if (empty($password_raw)) {
            $error = "<span data-en='Password is required for staff accounts.' data-am='áˆˆáˆ°áˆ«á‰°áŠ› áˆ˜áˆˆá‹«á‹Žá‰½ á‹¨á‹­áˆˆá á‰ƒáˆ á‹«áˆµáˆáˆáŒ‹áˆá¢'>Password is required for staff accounts.</span>";
        }
    }

    $student_id = ""; // re-init

    // Validation
    if (!ctype_alpha($fname) || !ctype_alpha($mname) || !ctype_alpha($lname)) {
        $error = "<span data-en='Names must contain only characters.' data-am='áˆµáˆžá‰½ áŠá‹°áˆ‹á‰µáŠ• á‰¥á‰» áˆ˜á‹«á‹ áŠ áˆˆá‰£á‰¸á‹á¢'>Names must contain only characters.</span>";
    } else {
        // Validate Phone/Email only for Non-Students
        if (true) {
            // Optional phone validation for students
            if (!empty($phone) || $role != 'student') {
                if (!preg_match('/^(\+2519|\+2517)[0-9]{8}$/', $phone)) {
                    $error = "<span data-en='Phone must start with +2519 or +2517 and have 8 digits after.' data-am='áˆµáˆáŠ­ á‰áŒ¥áˆ­ á‰  +2519 á‹ˆá‹­áˆ +2517 áˆ˜áŒ€áˆ˜áˆ­ áŠ¥áŠ“ áŠ¨áŠ‹áˆ‹ 8 áŠ áˆƒá‹žá‰½ áˆŠáŠ–áˆ©á‰µ á‹­áŒˆá‰£áˆá¢'>Phone must start with +2519 or +2517 and have 8 digits after.</span>";
                }
            }
            if (!$error && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "<span data-en='Invalid email format.' data-am='á‹¨áŠ¢áˆœáˆ á‰…áˆ­áŒ¸á‰µ á‰µáŠ­áŠ­áˆ áŠ á‹­á‹°áˆˆáˆá¢'>Invalid email format.</span>";
            }
        }

        // Role Specific Checks
        if (!$error && $role == 'student') {
            $student_id = trim($_POST['student_id']);
            $dept_id = $_POST['department_id'];
            $batch = $_POST['batch'];
            $academic_year = $_POST['academic_year'];
            $semester = $_POST['semester'];

            // Check if student exists in registrar records (students table)
            $stmt = $pdo->prepare("SELECT id, user_id FROM students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            $student_record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student_record) {
                $error = "<span data-en='Student ID " . $student_id . " not found in Registrar records.' data-am='á‹¨á‰°áˆ›áˆª áˆ˜á‰³á‹ˆá‰‚á‹« " . $student_id . " á‰ áˆ¬áŒ…áˆµá‰µáˆ«áˆ­ áˆ˜á‹›áŒá‰¥á‰µ á‹áˆµáŒ¥ áŠ áˆá‰°áŒˆáŠ˜áˆá¢'>Student ID " . $student_id . " not found in Registrar records.</span>";
            } elseif ($student_record['user_id']) {
                $error = "<span data-en='Student ID " . $student_id . " already has an account.' data-am='á‹¨á‰°áˆ›áˆª áˆ˜á‰³á‹ˆá‰‚á‹« " . $student_id . " áŠ áˆµá‰€á‹µáˆž áˆ˜áˆˆá‹« áŠ áˆˆá‹á¢'>Student ID " . $student_id . " already has an account.</span>";
            }

        } elseif (!$error && $role == 'department_head') {
            $dept_id = $_POST['department_id'];

            // Check unique dept head
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = ? AND head_user_id IS NOT NULL");
            $stmt->execute([$dept_id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "<span data-en='Assigned a department head already, you can not assign.' data-am='áˆˆá‹šáˆ… áŠ­ááˆ á‹¨á‰µáˆáˆ…áˆ­á‰µ áŠ­ááˆ áŠƒáˆ‹áŠ á‰°áˆ˜á‹µá‰§áˆá£ áˆ˜áˆ˜á‹°á‰¥ áŠ á‹­á‰½áˆ‰áˆá¢'>Assigned a department head already, you can not assign.</span>";
            }
        } elseif (!$error && in_array($role, ['registrar', 'transcript_pro', 'cost_sharing_pro', 'academic_vp'])) {
            // Check single account limit
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
            $stmt->execute([$role]);
            if ($stmt->fetchColumn() > 0) {
                $error = "<span data-en='No, you can not create more than one account in this role' data-am='áŠ á‹­á£ á‰ á‹šáˆ… áˆšáŠ“ áŠ¨áŠ áŠ•á‹µ á‰ áˆ‹á‹­ áˆ˜áˆˆá‹« áˆ˜ááŒ áˆ­ áŠ á‹­á‰½áˆ‰áˆ'>No, you can not create more than one account in this role</span>";
            }
        }
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();

            // Check username uniqueness
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $username = $username . rand(10, 99);
            }

            $hashed_pass = password_hash($password_raw, PASSWORD_DEFAULT);

            // Handle optional phone/email for students
            $email_insert = !empty($email) ? $email : null;
            $phone_insert = !empty($phone) ? $phone : null;

            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, first_name, middle_name, last_name, email, phone) 
                                   VALUES (:u, :p, :r, :f, :m, :l, :e, :ph)");
            $stmt->execute([
                ':u' => $username,
                ':p' => $hashed_pass,
                ':r' => $role,
                ':f' => $fname,
                ':m' => $mname,
                ':l' => $lname,
                ':e' => $email_insert,
                ':ph' => $phone_insert
            ]);
            $user_id = $pdo->lastInsertId();

            if ($role == 'student') {
                // Associate User Account with Existing Student Record
                // We trust validation above that student exists
                $stmt = $pdo->prepare("UPDATE students SET user_id = :uid, department_id = :did, batch = :batch, academic_year = :ay, current_semester = :sem, first_name = :fname, middle_name = :mname, last_name = :lname WHERE student_id = :sid");
                $stmt->execute([
                    ':uid' => $user_id,
                    ':sid' => $student_id,
                    ':did' => $dept_id,
                    ':batch' => $batch,
                    ':ay' => $academic_year,
                    ':sem' => $semester,
                    ':fname' => $fname,
                    ':mname' => $mname,
                    ':lname' => $lname
                ]);
            } elseif ($role == 'department_head') {
                $stmt = $pdo->prepare("UPDATE departments SET head_user_id = :uid WHERE id = :did");
                $stmt->execute([':uid' => $user_id, ':did' => $dept_id]);
            }

            $pdo->commit();
            logAudit($pdo, 'USER_CREATED', 'Created ' . $role . ' account: ' . $username . ' (ID: ' . $user_id . ')');

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

            // Send Welcome Email if email exists
            $email_status_msg = "";
            if (!empty($email_insert)) {
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $login_link = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/Cost_share/index.php";

                $subject = "Welcome to DMU Cost Sharing System";
                $body = "Dear " . htmlspecialchars($fname) . " " . htmlspecialchars($lname) . ",<br><br>";
                $body .= "Your account for the DMU Cost Sharing System has been created successfully.<br><br>";
                $body .= "<b>Your Login Credentials:</b><br>";
                $body .= "Username: <b>" . htmlspecialchars($username) . "</b><br>";
                $body .= "Password: <b>" . htmlspecialchars($password_raw) . "</b><br><br>";
                $body .= "We recommend that you change your password immediately upon first login.<br><br>";
                $body .= "<a href='{$login_link}'>Click here to login</a><br><br>";
                $body .= "Best Regards,<br>DMU System Administration";

                if (sendSystemEmail($email_insert, $fname . ' ' . $lname, $subject, $body)) {
                    $email_status_msg = "<br><small style='color:green;' data-en='Login credentials sent to email.' data-am='á‹¨áˆ˜áŒá‰¢á‹« áˆ˜áˆ¨áŒƒ á‹ˆá‹° áŠ¢áˆœáˆ á‰°áˆáŠ³áˆá¢'>Login credentials sent to email.</small>";
                } else {
                    $email_status_msg = "<br><small style='color:red;' data-en='Could not send email credentials.' data-am='á‹¨áŠ¢áˆœáˆ áˆ˜áˆ¨áŒƒ áˆ˜áˆ‹áŠ­ áŠ áˆá‰°á‰»áˆˆáˆá¢'>Could not send email credentials.</small>";
                }
            }

            $_SESSION["flash_success"] = "<span data-en='Account created successfully!' data-am='áˆ˜áˆˆá‹«á‹ á‰ á‰°áˆ³áŠ« áˆáŠ”á‰³ á‰°áˆáŒ¥áˆ¯áˆ!'>Account created successfully!</span> Username: $username" . $email_status_msg;
            header("Location: " . $_SERVER["PHP_SELF"]);
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "<span data-en='Error creating account: ' data-am='áˆ˜áˆˆá‹« áˆ²áˆáŒ áˆ­ áˆµáˆ…á‰°á‰µ: '>Error creating account: </span>" . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Create Account - Admin" data-am="áˆ˜áˆˆá‹« ááŒ áˆ­ - áŠ áˆµá‰°á‹³á‹³áˆª">Create Account - Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .hidden {
            display: none;
        }

        .form-section {
            border-top: 1px solid #eee;
            padding-top: 20px;
            margin-top: 20px;
        }

        /* Student ID Search Styles */
        .student-search-row {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .student-search-row .search-input-wrap {
            flex: 1;
        }

        .student-search-btn {
            padding: 9px 18px;
            background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            height: 40px;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .student-search-btn:hover {
            background: linear-gradient(135deg, #0d47a1 0%, #0a3780 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(13, 71, 161, 0.3);
        }

        .student-search-btn .spinner {
            display: none;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        .student-search-btn.loading .spinner {
            display: inline-block;
        }

        .student-search-btn.loading .btn-text {
            display: none;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .search-feedback {
            margin-top: 8px;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            display: none;
            align-items: center;
            gap: 6px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .search-feedback.found {
            display: flex;
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        .search-feedback.not-found {
            display: flex;
            background: #fff3e0;
            color: #e65100;
            border: 1px solid #ffcc02;
        }

        .search-feedback.error {
            display: flex;
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }

        .auto-filled {
            background-color: #f0f7f0 !important;
            border-color: #4caf50 !important;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>
            <div class="main-content">
              <!--  <div class="top-bar">
                    <h2 data-en="Create Account" data-am="áˆ˜áˆˆá‹« ááŒ áˆ­">Create Account</h2>
                </div>-->

                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>
                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>

                <?php if (!empty($_SESSION['created_accounts'])): ?>
                    <div class="card"
                        style="margin-bottom: 20px; background: #e8f5e9; border-left: 4px solid #2e7d32; padding: 15px;">
                        <div
                            style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <strong style="font-size: 15px;">
                                    <span
                                        data-en="<?php echo count($_SESSION['created_accounts']); ?> account(s) ready for download"
                                        data-am="<?php echo count($_SESSION['created_accounts']); ?> áˆ˜áˆˆá‹«(á‹Žá‰½) áˆˆáˆ›á‹áˆ¨á‹µ á‹áŒáŒ áŠ“á‰¸á‹">
                                        <?php echo count($_SESSION['created_accounts']); ?> account(s) ready for download
                                    </span>
                                </strong>
                                <p style="margin: 5px 0 0; font-size: 13px; color: #555;">
                                    <span data-en="Download includes username and password for each account."
                                        data-am="áˆ›á‹áˆ¨á‹± áˆˆáŠ¥á‹«áŠ•á‹³áŠ•á‹± áˆ˜áˆˆá‹« á‹¨á‰°áŒ á‰ƒáˆš áˆµáˆ áŠ¥áŠ“ á‹¨á‹­áˆˆá á‰ƒáˆ á‹«áŠ«á‰µá‰³áˆá¢">
                                        Download includes username and password for each account.
                                    </span>
                                </p>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <a href="download_created_accounts_csv.php" class="btn-primary"
                                    style="color:#fff; background-color:#2e7d32; padding: 8px 20px; text-decoration: none; border-radius: 4px; font-size: 14px;"
                                    data-en="â¬‡ Download CSV" data-am="â¬‡ CSV áŠ á‹áˆ­á‹µ">â¬‡ Download CSV</a>
                                <a href="download_created_accounts_csv.php?clear=1" class="btn-secondary"
                                    style="color:#fff; background-color:#757575; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-size: 13px;"
                                    data-en="Clear List" data-am="á‹áˆ­á‹áˆ©áŠ• áŠ áŒ½á‹³"
                                    onclick="event.preventDefault(); var dest = this.href; Swal.fire({title: 'Are you sure?', text: 'Are you sure you want to clear the list without downloading?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3085d6', confirmButtonText: 'Yes, clear it!'}).then((result) => { if (result.isConfirmed) { window.location.href = dest; } });">Clear
                                    List</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <form method="POST" id="createForm">
                        <!-- CSV Upload Section could go here, but let's stick to manual first as per primary flow -->

                        <div class="form-group">
                            <label data-en="Role" data-am="áˆšáŠ“">Role</label>
                            <select name="role" id="roleSelect" onchange="toggleFields()" required>
                                <option data-en="Select Role" data-am="áˆšáŠ“ á‹­áˆáˆ¨áŒ¡" value="">Select Role</option>
                                <option data-en="Student" data-am="á‰°áˆ›áˆª" value="student">Student</option>
                                <option data-en="Department Head" data-am="á‹¨á‰µáˆáˆ…áˆ­á‰µ áŠ­ááˆ áŠƒáˆ‹áŠ" value="department_head">
                                    Department Head</option>
                                <option data-en="Registrar Head" data-am="á‹¨áˆ˜áˆ˜á‹áŒˆá‰¢á‹« áŠƒáˆ‹áŠ" value="registrar">Registrar Head
                                </option>
                                <option data-en="Cost Sharing Professional" data-am="á‹¨á‹ˆáŒª áˆ˜áŒ‹áˆ«á‰µ á‰£áˆˆáˆ™á‹«"
                                    value="cost_sharing_pro">Cost Sharing Professional</option>
                                <option data-en="Official Transcript Professional" data-am="á‹¨áŠ¦áŠáˆ´áˆ á‰µáˆ«áŠ•áˆµáŠ­áˆªá•á‰µ á‰£áˆˆáˆ™á‹«"
                                    value="transcript_pro">Official Transcript Professional</option>
                                <option data-en="Academic Vice President" data-am="á‹¨áŠ áŠ«á‹³áˆšáŠ­ áˆ/á•áˆ¬á‹šá‹³áŠ•á‰µ" value="academic_vp">
                                    Academic Vice President</option>
                            </select>
                        </div>

                        <!-- Moved Student ID Search to top for better workflow -->
                        <div class="form-group hidden form-section" id="studentIdGroup"
                            style="border-top: none; padding-top: 0; margin-top: 5px;">
                            <label data-en="Student ID" data-am="á‰°áˆ›áˆª áˆ˜áˆˆá‹« á‰áŒ¥áˆ­">Student ID</label>
                            <div class="student-search-row">
                                <div class="search-input-wrap">
                                    <input type="text" name="student_id" id="studentIdInput" placeholder="Student ID"
                                        data-en="Student ID" data-am="á‰°áˆ›áˆª áˆ˜áˆˆá‹« á‰áŒ¥áˆ­" data-en-placeholder="Student ID"
                                        data-am-placeholder="á‰°áˆ›áˆª áˆ˜áˆˆá‹« á‰áŒ¥áˆ­">
                                </div>
                                <button type="button" class="student-search-btn" id="studentSearchBtn"
                                    onclick="searchStudentForAccount()">
                                    <span class="spinner"></span>
                                    <i class="fas fa-search btn-text"></i>
                                    <span class="btn-text" data-en="Search" data-am="áˆáˆáŒ">Search</span>
                                </button>
                            </div>
                            <div class="search-feedback" id="studentSearchFeedback"></div>
                        </div>

                        <div class="form-group three-col"
                            style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                            <div>
                                <label data-en="First Name" data-am="á‹¨áˆ˜áŒ€áˆ˜áˆªá‹« áˆµáˆ">First Name</label>
                                <input type="text" name="first_name" id="firstNameInput" required pattern="[A-Za-z]+"
                                    placeholder="First Name" data-en="First Name" data-am="á‹¨áˆ˜áŒ€áˆ˜áˆªá‹« áˆµáˆ"
                                    data-en-placeholder="First Name" data-am-placeholder="á‹¨áˆ˜áŒ€áˆ˜áˆªá‹« áˆµáˆ">
                            </div>
                            <div>
                                <label data-en="Middle Name" data-am="á‹¨áŠ á‰£á‰µ áˆµáˆ">Middle Name</label>
                                <input type="text" name="middle_name" id="middleNameInput" required pattern="[A-Za-z]+"
                                    placeholder="Middle Name" data-en="Middle Name" data-am="á‹¨áŠ á‰£á‰µ áˆµáˆ"
                                    data-en-placeholder="Middle Name" data-am-placeholder="á‹¨áŠ á‰£á‰µ áˆµáˆ">
                            </div>
                            <div>
                                <label data-en="Last Name" data-am="á‹¨áŠ á‹«á‰µ áˆµáˆ">Last Name</label>
                                <input type="text" name="last_name" id="lastNameInput" required pattern="[A-Za-z]+"
                                    placeholder="Last Name" data-en="Last Name" data-am="á‹¨áŠ á‹«á‰µ áˆµáˆ"
                                    data-en-placeholder="Last Name" data-am-placeholder="á‹¨áŠ á‹«á‰µ áˆµáˆ">
                            </div>
                        </div>

                        <div class="form-group two-col" id="contactGroup">
                            <div>
                                <label data-en="Phone (+2519... or +2517...)"
                                    data-am="áˆµáˆáŠ­ á‰áŒ¥áˆ­ (+2519... á‹ˆá‹­áˆ +2517...)">Phone (+2519... or +2517...)</label>
                                <input type="text" name="phone" id="phoneInput" placeholder="+251..." data-en="+251..."
                                    data-en-placeholder="+251..." data-am-placeholder="+251...">
                            </div>
                            <div>
                                <label data-en="Email" data-am="áŠ¢áˆœáˆ">Email</label>
                                <input type="email" name="email" id="emailInput" placeholder="example@email.com"
                                    data-en="example@email.com" data-am="example@email.com"
                                    data-en-placeholder="example@email.com" data-am-placeholder="example@email.com"
                                    required>
                            </div>
                        </div>

                        <!-- Password Field (For Non-Students) -->
                        <div class="form-group" id="passwordGroup">
                            <label data-en="Password" data-am="á‹¨á‹­áˆˆá á‰ƒáˆ">Password</label>
                            <input type="password" name="password" id="passwordInput" placeholder="Enter password"
                                data-en="Enter password" data-am="á‹¨á‹­áˆˆá á‰ƒáˆ á‹«áˆµáŒˆá‰¡" data-en-placeholder="Enter password"
                                data-am-placeholder="á‹¨á‹­áˆˆá á‰ƒáˆ á‹«áˆµáŒˆá‰¡">
                            <small data-en="CAUTION: Remember For students, the password will be their Student ID."
                                data-am="áŒ¥áŠ•á‰ƒá‰„ á‹«áˆµá‰³á‹áˆ±á£ áˆˆá‰°áˆ›áˆªá‹Žá‰½á£ á‹¨á‹­áˆˆá á‰ƒáˆ‰ á‹¨á‰°áˆ›áˆª áˆ˜á‰³á‹ˆá‰‚á‹«á‰¸á‹ á‹­áˆ†áŠ“áˆá¢"
                                style="color: red; font-weight: bold; font-size: 12px; display:block; margin-top:5px;">CAUTION:
                                Remember
                                For students, the password will be their Student ID.</small>
                        </div>

                        <!-- Student Specific -->
                        <div id="studentFields" class="hidden form-section">
                            <div class="form-group hidden" id="studentIdGroupOld">
                                <label data-en="Student ID" data-am="á‰°áˆ›áˆª áˆ˜áˆˆá‹« á‰áŒ¥áˆ­">Student ID</label>
                                <div class="student-search-row">
                                    <div class="search-input-wrap">
                                        <input type="text" name="student_id_old" id="studentIdInputOld"
                                            placeholder="Student ID" data-en="Student ID" data-am="á‰°áˆ›áˆª áˆ˜áˆˆá‹« á‰áŒ¥áˆ­"
                                            data-en-placeholder="Student ID" data-am-placeholder="á‰°áˆ›áˆª áˆ˜áˆˆá‹« á‰áŒ¥áˆ­">
                                    </div>
                                    <button type="button" class="student-search-btn" id="studentSearchBtnOld"
                                        onclick="searchStudentForAccount()">
                                        <span class="spinner"></span>
                                        <i class="fas fa-search btn-text"></i>
                                        <span class="btn-text" data-en="Search" data-am="áˆáˆáŒ">Search</span>
                                    </button>
                                </div>
                                <div class="search-feedback" id="studentSearchFeedback"></div>
                            </div>
                            <div class="form-group">
                                <label data-en="Department" data-am="á‰µáˆáˆ…áˆ­á‰µ áŠ­ááˆ">Department</label>
                                <select name="department_id" id="deptSelect" onchange="updateSemesters()">
                                    <option value="" data-en="Select Department" data-am="á‰µáˆáˆ…áˆ­á‰µ áŠ­ááˆ á‹­áˆáˆ¨áŒ¡">Select
                                        Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" data-name="<?php echo $dept['name']; ?>">
                                            <?php echo $dept['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group two-col">
                                <div>
                                    <label data-en="Academic Year (e.g., 2017)"
                                        data-am="á‰µáˆáˆ…áˆ­á‰µ á‹˜áˆ˜áŠ• (áˆˆáˆáˆ³áˆŒá¡ 2017)">Academic Year (e.g.,
                                        2017)</label>
                                    <input type="text" name="academic_year" id="academicYearInput" placeholder="2017"
                                        data-en="2017" data-en-placeholder="2017" data-am-placeholder="2017">
                                </div>
                                <div>
                                    <label data-en="Year of Study" data-am="á‹¨áŒ¥áŠ“á‰µ á‹“áˆ˜á‰µ">Year of Study</label>
                                    <select name="batch" id="batchSelect">
                                        <!-- Populated by JS -->
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label data-en="Semester" data-am="á‹ˆáˆ°áŠ á‰µáˆáˆ…áˆ­á‰µ">Semester</label>
                                <select name="semester" id="semesterSelect">
                                    <option value="" data-en="All Sem" data-am="áˆáˆ‰áˆ áˆ´áˆšáˆµá‰°áˆ­">All Sem</option>
                                </select>
                            </div>
                        </div>

                        <button data-en="Create Account" data-am="áˆ˜áˆˆá‹« ááŒ áˆ­" type="submit" name="create_user"
                            class="btn-primary" style="color:#ffffff; background-color:#000000; margin-top:20px;">Create
                            Account</button>
                    </form>
                </div>

                <!-- CSV Upload Block -->
                <div class="card mt-20 hidden" id="csvUploadSection">
                    <h3 data-en="Or Create Account by Uploading CSV" data-am="á‹ˆá‹­áˆ CSV á‰ áˆ˜áˆµá‰€áˆ áˆ˜áˆˆá‹« ááŒ áˆ­">Or Create Account
                        by Uploading CSV</h3>
                    <form method="POST" enctype="multipart/form-data" action="upload_csv.php">
                        <input type="file" name="csv_file" accept=".csv" required>
                        <button type="submit" name="create_account" class="btn-primary" data-en="Create Account"
                            data-am="áˆ˜áˆˆá‹« á‹­ááŒ áˆ©">Create Account</button>
                    </form>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>

    <script>
        function toggleFields() {
            const role = document.getElementById('roleSelect').value;
            const studentFields = document.getElementById('studentFields');
            const passwordGroup = document.getElementById('passwordGroup');
            const passwordInput = document.getElementById('passwordInput');
            const contactGroup = document.getElementById('contactGroup');
            const phoneInput = document.getElementById('phoneInput');
            const emailInput = document.getElementById('emailInput');

            // Handle Password Field visibility
            if (role === 'student') {
                passwordGroup.classList.add('hidden');
                passwordInput.removeAttribute('required');

                // Keep contact group visible, phone is optional but email is required
                contactGroup.classList.remove('hidden');
                phoneInput.removeAttribute('required');
                emailInput.setAttribute('required', 'required');
            } else {
                passwordGroup.classList.remove('hidden');
                passwordInput.setAttribute('required', 'required');

                // Show Phone/Email for staff and make required
                contactGroup.classList.remove('hidden');
                phoneInput.setAttribute('required', 'required');
                emailInput.setAttribute('required', 'required');
            }

            // Academic Year Required Logic (Only for Students)
            const acYearInput = document.getElementsByName('academic_year')[0];
            if (role === 'student') {
                acYearInput.setAttribute('required', 'required');
            } else {
                acYearInput.removeAttribute('required');
            }

            if (role === 'student' || role === 'department_head') {
                studentFields.classList.remove('hidden');
                // For dept head, hide batch/sem/student_id but keep dept
                if (role === 'department_head') {
                    document.getElementById('studentIdGroup').classList.add('hidden');
                    document.getElementById('batchSelect').closest('.form-group.two-col').classList.add('hidden');
                    document.getElementById('semesterSelect').closest('.form-group').classList.add('hidden');
                } else {
                    document.getElementById('studentIdGroup').classList.remove('hidden');
                    document.getElementById('batchSelect').closest('.form-group.two-col').classList.remove('hidden');
                    document.getElementById('semesterSelect').closest('.form-group').classList.remove('hidden');
                }
            } else {
                studentFields.classList.add('hidden');
                document.getElementById('studentIdGroup').classList.add('hidden');
            }

            // Toggle CSV Upload Section
            const csvSection = document.getElementById('csvUploadSection');
            if (role === 'student') {
                csvSection.classList.remove('hidden');
            } else {
                csvSection.classList.add('hidden');
            }
        }

        function updateSemesters() {
            const deptSelect = document.getElementById('deptSelect');
            const selectedOption = deptSelect.options[deptSelect.selectedIndex];
            const deptName = selectedOption.getAttribute('data-name');

            const batchSelect = document.getElementById('batchSelect');
            const semSelect = document.getElementById('semesterSelect');

            batchSelect.innerHTML = '';
            semSelect.innerHTML = '';

            // Logic for Remedial/Freshman
            if (deptName === 'Remedial') {
                let optB = document.createElement('option'); optB.value = '1'; optB.selected = true; batchSelect.appendChild(optB);
                let optS = document.createElement('option'); optS.value = '1'; optS.selected = true; semSelect.appendChild(optS);
            } else if (deptName === 'Freshman') {
                let optB = document.createElement('option'); optB.value = '1'; optB.text = '1'; batchSelect.appendChild(optB);

                let opt1 = document.createElement('option'); opt1.value = '1'; opt1.text = '1'; semSelect.appendChild(opt1);
                let opt2 = document.createElement('option'); opt2.value = '2'; opt2.text = '2'; semSelect.appendChild(opt2);
            } else {
                // Regular Depts -> Year 2-6
                for (let i = 2; i <= 6; i++) {
                    let opt = document.createElement('option');
                    opt.value = i;
                    opt.innerText = i;
                    batchSelect.appendChild(opt);
                }
                semSelect.innerHTML = '<option data-en="First Semester" data-am="áˆ˜áŒ€áˆ˜áˆªá‹«á‹ á‹ˆáˆ°áŠ á‰µáˆáˆ…áˆ­á‰µ  " value="1">1</option><option data-en="Second Semester" data-am="áˆáˆˆá‰°áŠ›á‹ á‹ˆáˆ°áŠ á‰µáˆáˆ…áˆ­á‰µ" value="2">2</option>';
            }
            // Refresh language for new elements
            const currentLang = localStorage.getItem('dmu_lang') || 'en';
            setLanguage(currentLang);
        }
        // Enter key support for student ID search
        document.getElementById('studentIdInput').addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && document.getElementById('roleSelect').value === 'student') {
                e.preventDefault();
                searchStudentForAccount();
            }
        });

        function searchStudentForAccount() {
            var studentIdInput = document.getElementById('studentIdInput');
            var studentId = studentIdInput.value.trim();
            var feedback = document.getElementById('studentSearchFeedback');
            var searchBtn = document.getElementById('studentSearchBtn');

            if (!studentId) {
                feedback.className = 'search-feedback error';
                feedback.innerHTML = '<i class="fas fa-exclamation-circle"></i> <span data-en="Please enter a Student ID" data-am="áŠ¥á‰£áŠ­á‹Ž á‹¨á‰°áˆ›áˆª áˆ˜á‰³á‹ˆá‰‚á‹« á‹«áˆµáŒˆá‰¡">Please enter a Student ID</span>';
                studentIdInput.focus();
                if (typeof updateLanguage === 'function') updateLanguage();
                return;
            }

            // Show loading
            searchBtn.classList.add('loading');
            feedback.className = 'search-feedback';
            feedback.removeAttribute('style');

            fetch('../../api/search_student.php?student_id=' + encodeURIComponent(studentId))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    searchBtn.classList.remove('loading');

                    if (data.success && data.student) {
                        var s = data.student;

                        // Auto-fill name fields
                        var fnameInput = document.getElementById('firstNameInput');
                        var mnameInput = document.getElementById('middleNameInput');
                        var lnameInput = document.getElementById('lastNameInput');

                        fnameInput.value = s.first_name || '';
                        mnameInput.value = s.middle_name || '';
                        lnameInput.value = s.last_name || '';

                        // Add auto-filled styling to names
                        if (s.first_name) fnameInput.classList.add('auto-filled');
                        if (s.middle_name) mnameInput.classList.add('auto-filled');
                        if (s.last_name) lnameInput.classList.add('auto-filled');

                        // Auto-fill department
                        if (s.department_id) {
                            var deptSelect = document.getElementById('deptSelect');
                            deptSelect.value = s.department_id;
                            deptSelect.classList.add('auto-filled');
                            // Trigger semester update based on department
                            updateSemesters();
                        }

                        // Auto-fill academic year
                        if (s.academic_year) {
                            var ayInput = document.getElementById('academicYearInput');
                            ayInput.value = s.academic_year;
                            ayInput.classList.add('auto-filled');
                        }

                        // Auto-fill batch (year of study) after semester update populates options
                        setTimeout(function () {
                            if (s.batch) {
                                var batchSel = document.getElementById('batchSelect');
                                batchSel.value = s.batch;
                                batchSel.classList.add('auto-filled');
                            }
                            if (s.current_semester) {
                                var semSel = document.getElementById('semesterSelect');
                                semSel.value = s.current_semester;
                                semSel.classList.add('auto-filled');
                            }
                        }, 150);

                        // Show success feedback
                        var statusText = s.status || 'Active';
                        var hasAccount = s.user_id ? ' (âš  Already has account!)' : '';
                        feedback.className = 'search-feedback found';
                        feedback.innerHTML = '<i class="fas fa-check-circle"></i> <span data-en="Student found: ' +
                            s.first_name + ' ' + (s.middle_name || '') + ' ' + (s.last_name || '') +
                            ' | Dept: ' + (s.department_name || 'N/A') + ' | Status: ' + statusText + hasAccount + '"' +
                            ' data-am="á‰°áˆ›áˆª á‰°áŒˆáŠá‰·áˆ: ' +
                            s.first_name + ' ' + (s.middle_name || '') + ' ' + (s.last_name || '') +
                            ' | áŠ­ááˆ: ' + (s.department_name || 'á‹¨áˆˆáˆ') + ' | áˆáŠ”á‰³: ' + statusText + hasAccount + '">' +
                            'Student found: ' + s.first_name + ' ' + (s.middle_name || '') + ' ' + (s.last_name || '') +
                            ' | Dept: ' + (s.department_name || 'N/A') + ' | Status: ' + statusText + hasAccount + '</span>';

                        if (typeof updateLanguage === 'function') updateLanguage();
                    } else {
                        // Not found
                        feedback.className = 'search-feedback not-found';
                        feedback.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <span data-en="Student not found in registrar records. Please check the Student ID." data-am="á‰°áˆ›áˆª á‰ áˆ¬áŒ…áˆµá‰µáˆ«áˆ­ áˆ˜á‹›áŒá‰¥á‰µ á‹áˆµáŒ¥ áŠ áˆá‰°áŒˆáŠ˜áˆá¢ áŠ¥á‰£áŠ­á‹Ž á‹¨á‰°áˆ›áˆª áˆ˜á‰³á‹ˆá‰‚á‹«á‹áŠ• á‹«áˆ¨áŒ‹áŒáŒ¡á¢">Student not found in registrar records. Please check the Student ID.</span>';
                        if (typeof updateLanguage === 'function') updateLanguage();
                    }
                })
                .catch(function (err) {
                    searchBtn.classList.remove('loading');
                    feedback.className = 'search-feedback error';
                    feedback.innerHTML = '<i class="fas fa-times-circle"></i> <span data-en="Error searching. Please try again." data-am="ááˆˆáŒ‹ áˆµáˆ…á‰°á‰µá¢ áŠ¥á‰£áŠ­á‹Ž áŠ¥áŠ•á‹°áŒˆáŠ“ á‹­áˆžáŠ­áˆ©á¢">Error searching. Please try again.</span>';
                    if (typeof updateLanguage === 'function') updateLanguage();
                });
        }
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>