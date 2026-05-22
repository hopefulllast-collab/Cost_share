<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['registrar']);

require_once '../../includes/academic_translations.php';

// PRG: Read flash messages from session
$msg = $_SESSION['flash_success'] ?? "";
unset($_SESSION['flash_success']);
$error = "";

// Fetch Departments
$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Handle Cost Sharing Order Submit (Manual Entry)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_cs_order'])) {
    try {
        $fname = $_POST['first_name'];
        $mname = $_POST['middle_name'];
        $lname = $_POST['last_name'];
        $sex = $_POST['sex'];
        $sid = $_POST['student_id'];
        $dept_id = $_POST['department_id'];
        $batch = $_POST['batch'];
        $sem = $_POST['semester'];
        $ac_year = $_POST['academic_year'];
        $reason = $_POST['reason'];

        // Check for duplicate pending order
        $dup_check = $pdo->prepare("SELECT id FROM official_transcript WHERE student_id_str = ? AND recipient_role = 'cost_sharing_pro' AND status = 'Pending'");
        $dup_check->execute([$sid]);
        if ($dup_check->fetch()) {
            $error = "<span data-en='Already ordered! A pending order for this student already exists.' data-am='ቀደም ሲል ታዝዟል! ለዚህ ተማሪ ተንጠልጣይ ትእዛዝ አለ።'>Already ordered! A pending order for this student already exists.</span> (ID: " . htmlspecialchars($sid) . ")";
        } else {
            $stmt = $pdo->prepare("INSERT INTO official_transcript 
                (sender_id, recipient_role, request_type, first_name, middle_name, last_name, sex, student_id_str, department_id, batch, semester, academic_year, description) 
                VALUES (?, 'cost_sharing_pro', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $typeMap = [
                'active' => 'active',
                'withdrawal' => 'withdrawal',
                'dropout' => 'dropout',
                'complete dismissal' => 'complete dismissal',
                'dismissal with readmission' => 'dismissal with readmission',
                'death' => 'death',
                'graduate' => 'Graduate',
                'ethics' => 'ethics'
            ];
            $dbType = $typeMap[$reason] ?? 'other';

            $stmt->execute([
                $_SESSION['user_id'],
                $dbType,
                $fname,
                $mname,
                $lname,
                $sex,
                $sid,
                $dept_id,
                $batch,
                $sem,
                $ac_year,
                $reason
            ]);

            $msg = "<span data-en='Successfully sent order to Cost Sharing Professional.' data-am='ለኮስት ሼሪንግ ባለሙያ ትእዛዝ በተሳካ ሁኔታ ተልኳል።'>Successfully sent order to Cost Sharing Professional.</span>";
            $_SESSION['flash_success'] = $msg;
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } catch (PDOException $e) {
        $error = "<span data-en='Error sending order: " . $e->getMessage() . "' data-am='ትእዛዝ በሚላክበት ጊዜ ስህተት: " . $e->getMessage() . "'>Error sending order: " . $e->getMessage() . "</span>";
    }
}

// Handle Transfer Submit (Transcript Pro)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_transfer'])) {
    try {
        $fname = $_POST['first_name'];
        $mname = $_POST['middle_name'];
        $lname = $_POST['last_name'];
        $sex = $_POST['sex'];
        $sid = $_POST['student_id'];
        $dept_id = $_POST['department_id'];
        $year = $_POST['year'];
        $sem = $_POST['semester'];

        // Handle file upload
        $transcript_file = '';
        if (isset($_FILES['transcript_file']) && $_FILES['transcript_file']['error'] == 0) {
            $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
            $filename = $_FILES['transcript_file']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                // Ensure upload directory exists
                $upload_dir = '../../uploads/clearances/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);

                $new_filename = uniqid('transcript_') . '.' . $ext;
                $destination = $upload_dir . $new_filename;
                if (move_uploaded_file($_FILES['transcript_file']['tmp_name'], $destination)) {
                    $transcript_file = $new_filename;
                } else {
                    throw new Exception("Failed to upload transcript file.");
                }
            } else {
                throw new Exception("Invalid file type. Only PDF and images are allowed.");
            }
        } else {
            throw new Exception("Transcript file is required.");
        }

        $stmt = $pdo->prepare("INSERT INTO official_transcript 
            (sender_id, recipient_role, request_type, first_name, middle_name, last_name, sex, student_id_str, department_id, batch, semester, cost_share_amount, clearance_file, description) 
            VALUES (?, 'transcript_pro', 'transfer', ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, 'Transfer In Document')");
        $stmt->execute([$_SESSION['user_id'], $fname, $mname, $lname, $sex, $sid, $dept_id, $year, $sem, $transcript_file]);

        $_SESSION['flash_success'] = "<span data-en='Successfully sent transferred student order with file attached.' data-am='የተዘዋወረ ተማሪ ትእዛዝ ከነፋይሉ በተሳካ ሁኔታ ተልኳል።'>Successfully sent transferred student order with file attached.</span>";
        header("Location: " . $_SERVER['PHP_SELF'] . "?target=transcript");
        exit();
    } catch (Exception $e) {
        $error = "<span data-en='Error: " . $e->getMessage() . "' data-am='ስህተት: " . $e->getMessage() . "'>Error: " . $e->getMessage() . "</span>";
    }
}

// Handle Document Request Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['doc_action'])) {
    $action = $_POST['doc_action'];
    $req_id = $_POST['request_id'];

    if ($action == 'approve_doc') {
        // Determine request type
        $check = $pdo->prepare("SELECT request_type FROM official_transcript WHERE id = ?");
        $check->execute([$req_id]);
        $r_type = $check->fetchColumn();

        $new_status = ($r_type == 'Graduation') ? 'Pending Cost Share Pro' : 'Pending Transcript';
        $r_msg_en = ($r_type == 'Graduation') ? 'Document request forwarded to Cost Sharing Professional.' : 'Document request forwarded to Official Transcript Professional.';
        $r_msg_am = ($r_type == 'Graduation') ? 'የሰነድ ጥያቄ ወደ ወጪ መጋራት ባለሙያ ተላልፏል።' : 'የሰነድ ጥያቄ ወደ ኦፊሴላዊ ትራንስክሪፕት ባለሙያ ተላልፏል።';

        $stmt = $pdo->prepare("UPDATE official_transcript SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $req_id])) {
            $_SESSION['flash_success'] = "<span data-en='{$r_msg_en}' data-am='{$r_msg_am}'>{$r_msg_en}</span>";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error = "<span data-en='Failed to update status.' data-am='ሁኔታውን ማዘመን አልተቻለም።'>Failed to update status.</span>";
        }
    } elseif ($action == 'reject_doc') {
        $reason = $_POST['rejection_reason'] ?? 'No reason provided';
        $stmt = $pdo->prepare("UPDATE official_transcript SET status = 'Rejected', rejection_reason = ? WHERE id = ?");
        if ($stmt->execute([$reason, $req_id])) {
            $_SESSION['flash_success'] = "<span data-en='Document request rejected successfully.' data-am='የሰነድ ጥያቄ በተሳካ ሁኔታ ውድቅ ተደርጓል።'>Document request rejected successfully.</span>";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error = "<span data-en='Failed to reject request.' data-am='ጥያቄውን ውድቅ ማድረግ አልተቻለም።'>Failed to reject request.</span>";
        }
    } elseif ($action == 'forward_transfer_to_transcript') {
        $stmt = $pdo->prepare("UPDATE official_transcript SET status = 'Pending Transcript' WHERE id = ? AND request_type = 'Transfer-Out'");
        if ($stmt->execute([$req_id])) {
            $_SESSION['flash_success'] = "<span data-en='Transfer-Out request forwarded to Official Transcript Professional.' data-am='የዝውውር ጥያቄ ወደ ኦፊሴላዊ ትራንስክሪፕት ባለሙያ ተላልፏል።'>Transfer-Out request forwarded to Official Transcript Professional.</span>";
            header("Location: " . $_SERVER['PHP_SELF'] . "?target=transcript");
            exit();
        } else {
            $error = "<span data-en='Failed to forward request.' data-am='ጥያቄውን ማስተላለፍ አልተቻለም።'>Failed to forward request.</span>";
        }
    }
}

// Determine active tab
$target = $_GET['target'] ?? 'cost_sharing';

// Fetch Pending Document Requests (exclude Transfer-Out, those go through Academic VP first)
if ($target == 'cost_sharing') {
    $pending_docs = $pdo->query("SELECT dr.*, u.first_name, u.last_name, s.student_id as real_student_id, d.name as dept_name 
                                 FROM official_transcript dr 
                                 JOIN students s ON dr.student_id = s.user_id 
                                 JOIN users u ON s.user_id = u.id 
                                 LEFT JOIN departments d ON s.department_id = d.id 
                                 WHERE dr.request_type = 'Graduation' AND dr.status = 'Pending'")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $pending_docs = $pdo->query("SELECT dr.*, u.first_name, u.last_name, s.student_id as real_student_id, d.name as dept_name 
                                 FROM official_transcript dr 
                                 JOIN students s ON dr.student_id = s.user_id 
                                 JOIN users u ON s.user_id = u.id 
                                 LEFT JOIN departments d ON s.department_id = d.id 
                                 WHERE dr.request_type NOT IN ('CostSharePaper', 'Transfer-Out', 'Graduation') AND dr.status = 'Pending'")->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch VP-Forwarded Transfer-Out Requests
$vp_forwarded_docs = $pdo->query("SELECT dr.*, u.first_name, u.middle_name, u.last_name, s.student_id as real_student_id, d.name as dept_name 
                             FROM official_transcript dr 
                             JOIN students s ON dr.student_id = s.user_id 
                             JOIN users u ON s.user_id = u.id 
                             LEFT JOIN departments d ON s.department_id = d.id 
                             WHERE dr.request_type = 'Transfer-Out' AND dr.status = 'Forwarded'
                             ORDER BY dr.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Order - Registrar" data-am="ትእዛዝ - ሬጅስትራር">Order - Registrar</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .accordion {
            background-color: #eee;
            color: #444;
            cursor: pointer;
            padding: 18px;
            width: 100%;
            border: none;
            text-align: left;
            outline: none;
            font-size: 15px;
            transition: 0.4s;
            border-radius: 5px;
            margin-bottom: 5px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .active,
        .accordion:hover {
            background-color: #ccc;
        }

        .panel {
            padding: 0 18px;
            display: none;
            background-color: white;
            overflow: hidden;
            border: 1px solid #ccc;
            border-top: none;
            margin-bottom: 20px;
            border-radius: 0 0 5px 5px;
        }

        .sub-section {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }

        /* Student ID Search Styles */
        .search-box {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            margin-bottom: 20px;
            padding: 15px 20px;
            background: linear-gradient(135deg, #f0f4ff 0%, #e8f0fe 100%);
            border-radius: 10px;
            border: 1px solid #c8d8f0;
        }

        .search-box .search-input-group {
            flex: 1;
            max-width: 350px;
        }

        .search-box label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #1a237e;
            font-size: 14px;
        }

        .search-box input[type="text"] {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #b0bec5;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s, box-shadow 0.3s;
            outline: none;
        }

        .search-box input[type="text"]:focus {
            border-color: #1565c0;
            box-shadow: 0 0 0 3px rgba(21, 101, 192, 0.15);
        }

        .search-btn {
            padding: 10px 22px;
            background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            height: 42px;
        }

        .search-btn:hover {
            background: linear-gradient(135deg, #0d47a1 0%, #0a3780 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(13, 71, 161, 0.3);
        }

        .search-btn:active {
            transform: translateY(0);
        }

        .search-btn .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        .search-btn.loading .spinner {
            display: inline-block;
        }

        .search-btn.loading .btn-text {
            display: none;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Search result feedback */
        .search-feedback {
            margin-top: 10px;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            display: none;
            align-items: center;
            gap: 8px;
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

        /* Auto-filled fields styling */
        .auto-filled {
            background-color: #f5f5f5 !important;
            border-color: #4caf50 !important;
            color: #333 !important;
        }

        .form-fields-wrapper {
            transition: opacity 0.3s ease;
        }

        .form-fields-wrapper.loading {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Divider */
        .form-divider {
            border: none;
            border-top: 2px dashed #ddd;
            margin: 20px 0;
        }

        .form-divider-label {
            text-align: center;
            color: #888;
            font-size: 12px;
            margin: -10px 0 15px;
            background: white;
            display: inline-block;
            padding: 0 12px;
            position: relative;
            left: 50%;
            transform: translateX(-50%);
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>
            <div class="main-content">
                <div class="top-bar">
                    <h2 data-en="Order Recommendation" data-am="የትእዛዝ ምክር">Order Recommendation</h2>
                </div>

                <?php if ($msg): ?>
                    <div class="success-msg"><?php echo $msg; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="error-msg"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Accordion 1: Cost Sharing -->
                <button class="accordion <?php echo ($target == 'cost_sharing') ? 'active' : ''; ?>">
                    <span data-en="To Cost Sharing Professional" data-am="ለወጪ መጋራት ባለሙያ">To Cost Sharing
                        Professional</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="panel" style="display: <?php echo ($target == 'cost_sharing') ? 'block' : 'none'; ?>">
                    <div class="sub-section" style="border-bottom: none;">
                        <h3 data-en="Report Student Status (Withdrawal, Death, etc.)"
                            data-am="የተማሪ ሁኔታ ሪፖርት (ማቋረጥ፣ ሞት፣ ወዘተ)">Report Student Status (Withdrawal, Death, etc.)</h3>

                        <!-- Student ID Search Box -->
                        <div class="search-box">
                            <div class="search-input-group">
                                <label data-en="🔍 Search by Student ID" data-am="🔍 በተማሪ መታወቂያ ፈልግ">🔍 Search by
                                    Student ID</label>
                                <input type="text" id="cs_search_id" placeholder="Enter Student ID..."
                                    data-en-placeholder="Enter Student ID..." data-am-placeholder="የተማሪ መታወቂያ ያስገቡ...">
                            </div>
                            <button type="button" class="search-btn" id="cs_search_btn" onclick="searchStudent('cs')">
                                <span class="spinner"></span>
                                <i class="fas fa-search btn-text"></i>
                                <span class="btn-text" data-en="Search" data-am="ፈልግ">Search</span>
                            </button>
                        </div>
                        <div class="search-feedback" id="cs_feedback"></div>

                        <form method="POST">
                            <div class="form-fields-wrapper" id="cs_fields">
                                <div class="form-group two-col"
                                    style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                    <div>
                                        <label data-en="Student ID" data-am="የተማሪ መታወቂያ">Student ID</label>
                                        <input type="text" name="student_id" id="cs_student_id" required
                                            placeholder="Student ID" readonly
                                            style="background:#f0f0f0; cursor:not-allowed;"
                                            data-en-placeholder="Student ID" data-am-placeholder="የተማሪ መታወቂያ">
                                    </div>
                                    <div>
                                        <label data-en="Sex" data-am="ጾታ">Sex</label>
                                        <select id="cs_sex" disabled style="background:#f0f0f0; cursor:not-allowed;">
                                            <option value="M" data-en="Male" data-am="ወንድ">Male</option>
                                            <option value="F" data-en="Female" data-am="ሴት">Female</option>
                                        </select>
                                        <input type="hidden" name="sex" id="cs_sex_hidden" value="M">
                                    </div>
                                </div>
                                <div class="form-group three-col"
                                    style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                                    <div><label data-en="First Name" data-am="የመጀመሪያ ስም">First Name</label><input
                                            type="text" name="first_name" id="cs_first_name" required
                                            placeholder="First Name" readonly style="background:#f0f0f0;"
                                            data-en-placeholder="First Name" data-am-placeholder="የመጀመሪያ ስም"></div>
                                    <div><label data-en="Middle Name" data-am="የአባት ስም">Middle Name</label><input
                                            type="text" name="middle_name" id="cs_middle_name" required
                                            placeholder="Middle Name" readonly style="background:#f0f0f0;"
                                            data-en-placeholder="Middle Name" data-am-placeholder="የአባት ስም"></div>
                                    <div><label data-en="Last Name" data-am="የአያት ስም">Last Name</label><input
                                            type="text" name="last_name" id="cs_last_name" required
                                            placeholder="Last Name" readonly style="background:#f0f0f0;"
                                            data-en-placeholder="Last Name" data-am-placeholder="የአያት ስም"></div>
                                </div>
                                <div class="form-group">
                                    <label data-en="Department" data-am="ትምህርት ክፍል">Department</label>
                                    <select id="cs_department_id" disabled
                                        style="background:#f0f0f0; cursor:not-allowed;">
                                        <option value="" data-en="Select Department" data-am="ትምህርት ክፍል ይምረጡ">Select
                                            Department</option>
                                        <?php foreach ($departments as $d):
                                            $d_name_en = $d['name'];
                                            $d_name_am = $academic_translations[$d_name_en] ?? $d_name_en;
                                            ?>
                                            <option value="<?php echo $d['id']; ?>"
                                                data-en="<?php echo htmlspecialchars($d_name_en); ?>"
                                                data-am="<?php echo htmlspecialchars($d_name_am); ?>">
                                                <?php echo htmlspecialchars($d_name_en); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="department_id" id="cs_department_id_hidden" value="">
                                </div>
                                <div class="form-group three-col"
                                    style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                                    <div><label data-en="Year of Study" data-am="የጥናት ዓመት">Year of Study</label><input
                                            type="number" name="batch" id="cs_batch" min="1" max="8" required readonly
                                            style="background:#f0f0f0; cursor:not-allowed;">
                                    </div>
                                    <div><label data-en="Semester" data-am="ሴሚስተር">Semester</label><input type="number"
                                            name="semester" id="cs_semester" min="1" max="3" required readonly
                                            style="background:#f0f0f0; cursor:not-allowed;"></div>
                                    <div><label data-en="Academic Year" data-am="የትምህርት ዘመን">Academic Year</label><input
                                            type="text" name="academic_year" id="cs_academic_year"
                                            placeholder="e.g. 2016" required readonly
                                            style="background:#f0f0f0; cursor:not-allowed;" data-en="e.g. 2016"
                                            data-en-placeholder="e.g. 2016" data-am-placeholder="ለምሳሌ 2016"></div>
                                </div>

                                <hr class="form-divider">

                                <div class="form-group">
                                    <label data-en="Cause / Reason" data-am="ምክንያት">Cause / Reason</label>
                                    <select name="reason" required>
                                        <option value="" data-en="Select Cause" data-am="ምክንያት ይምረጡ">Select Cause
                                        </option>
                                        <option value="active" data-en="Active" data-am="ንቁ">Active</option>
                                        <option value="withdrawal" data-en="Withdrawal" data-am="ያቋረጠ (Withdrawal)">
                                            Withdrawal</option>
                                        <option value="dropout" data-en="Dropout" data-am="ያቋረጠ (Dropout)">Dropout
                                        </option>
                                        <option value="complete dismissal" data-en="Complete Dismissal"
                                            data-am="ሙሉ ለሙሉ የተሰናበተ">Complete Dismissal</option>
                                        <option value="dismissal with readmission" data-en="Dismissal with Readmission"
                                            data-am="መመለስ የሚቻል">Dismissal with Readmission</option>
                                        <option value="death" data-en="Death" data-am="ሞት">Death</option>
                                        <option value="graduate" <?php echo (isset($_GET['status']) && $_GET['status'] == 'graduate') ? 'selected' : ''; ?> data-en="Graduate"
                                            data-am="????">Graduate</option>
                                        <option value="other" data-en="Other" data-am="ሌላ">Other</option>
                                    </select>
                                </div>
                                <button type="submit" name="send_cs_order" class="btn-primary"
                                    data-en="Send Student List" data-am="የተማሪ ዝርዝር ላክ">Send Student List</button>
                            </div>
                        </form>
                    </div>

                    <!-- VP-Forwarded Transfer-Out Requests -->
                    <?php if (!empty($vp_forwarded_docs)): ?>
                        <div class="sub-section" style="border-top: 2px solid #eee; margin-top: 10px;">
                            <h3 data-en="Transfer-Out Requests (Forwarded by Academic VP)"
                                data-am="የዝውውር ጥያቄዎች (በአካዳሚክ ም/ፕሬዝዳንት የተላለፉ)">Transfer-Out Requests (Forwarded by Academic
                                VP)</h3>
                            <table class="table" style="width:100%; border-collapse:collapse; margin-top:10px;">
                                <thead>
                                    <tr style="background:#f9f9f9; text-align:left;">
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Student ID"
                                            data-am="የተማሪ መታወቂያ">Student ID</th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Name" data-am="ስም">Name
                                        </th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Department"
                                            data-am="ትምህርት ክፍል">Department</th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Clearance"
                                            data-am="ክሊራንስ">Clearance</th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Action" data-am="እርምጃ">
                                            Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vp_forwarded_docs as $vdoc): ?>
                                        <tr>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <?php echo htmlspecialchars($vdoc['real_student_id']); ?>
                                            </td>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <?php echo htmlspecialchars($vdoc['first_name'] . ' ' . trim(($vdoc['middle_name'] ?? '') . ' ' . $vdoc['last_name'])); ?>
                                            </td>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <?php
                                                $vd_en = $vdoc['dept_name'] ?: 'N/A';
                                                $vd_am = $academic_translations[$vd_en] ?? $vd_en;
                                                ?>
                                                <span data-en="<?php echo htmlspecialchars($vd_en); ?>"
                                                    data-am="<?php echo htmlspecialchars($vd_am); ?>">
                                                    <?php echo htmlspecialchars($vd_en); ?>
                                                </span>
                                            </td>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <?php if ($vdoc['clearance_file']): ?>
                                                    <a href="../../uploads/clearances/<?php echo $vdoc['clearance_file']; ?>"
                                                        target="_blank" style="color:blue; text-decoration:underline;"
                                                        data-en="View File" data-am="ፋይል ይመልከቱ">View File</a>
                                                <?php else: ?>
                                                    <span data-en="N/A" data-am="የለም">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <!-- Process Document Link -->
                                                <a href="process_transfer.php?id=<?php echo $vdoc['id']; ?>" class="btn-primary"
                                                    style="padding:5px 10px; font-size:12px; margin-right:5px; text-decoration:none; display:inline-block;"
                                                    data-en="Process Document" data-am="ሰነድ አዘጋጅ">
                                                    <i class="fas fa-file-signature"></i> Process Document</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Accordion 3: Student Document Requests -->
                <button class="accordion <?php echo ($target == 'requests') ? 'active' : ''; ?>">
                    <span data-en="Student Document Requests" data-am="የተማሪ ሰነድ ጥያቄዎች">Student Document Requests</span>
                    (<?php echo count($pending_docs); ?>)
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="panel"
                    style="display: <?php echo ($target == 'requests' || !empty($pending_docs)) ? 'block' : 'none'; ?>">
                    <div class="sub-section" style="border-bottom: none;">
                        <h3 data-en="Pending Requests" data-am="በመጠባበቅ ላይ ያሉ ጥያቄዎች">Pending Requests</h3>
                        <?php if (empty($pending_docs)): ?>
                            <p data-en="No pending document requests." data-am="በመጠባበቅ ላይ ያሉ የሰነድ ጥያቄዎች የሉም።">No pending
                                document requests.</p>
                        <?php else: ?>
                            <table class="table" style="width:100%; border-collapse:collapse; margin-top:10px;">
                                <thead>
                                    <tr style="background:#f9f9f9; text-align:left;">
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Student ID"
                                            data-am="የተማሪ መታወቂያ">Student ID</th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Name" data-am="ስም">Name
                                        </th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Document Type"
                                            data-am="የሰነድ ዓይነት">Document Type</th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Clearance"
                                            data-am="ክሊራንስ">Clearance</th>
                                        <th style="padding:10px; border:1px solid #ddd;" data-en="Action" data-am="እርምጃ">
                                            Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_docs as $doc): ?>
                                        <tr>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <?php echo htmlspecialchars($doc['real_student_id']); ?>
                                            </td>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?>
                                            </td>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <?php echo htmlspecialchars($doc['request_type']); ?>
                                            </td>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <?php if ($doc['clearance_file']): ?>
                                                    <a href="../../uploads/clearances/<?php echo $doc['clearance_file']; ?>"
                                                        target="_blank" style="color:blue; text-decoration:underline;"
                                                        data-en="View File" data-am="ፋይል ይመልከቱ">View File</a>
                                                <?php else: ?>
                                                    <span data-en="N/A" data-am="የለም">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding:10px; border:1px solid #ddd;">
                                                <!-- Process Document Link -->
                                                <a href="process_request.php?id=<?php echo $doc['id']; ?>" class="btn-primary"
                                                    style="padding:5px 10px; font-size:12px; margin-right:5px; text-decoration:none; display:inline-block;"
                                                    data-en="Process Document" data-am="ሰነድ መርምር">
                                                    <i class="fas fa-file-signature"></i> <span data-en="Process Request"
                                                        data-am="ጥያቄ መርምር">Process Request</span></a>
                                                <form method="POST" style="display:inline;"
                                                    id="rejectForm_<?php echo $doc['id']; ?>">
                                                    <input type="hidden" name="request_id" value="<?php echo $doc['id']; ?>">
                                                    <input type="hidden" name="doc_action" value="reject_doc">
                                                    <input type="hidden" name="rejection_reason"
                                                        id="rej_reason_<?php echo $doc['id']; ?>" value="">
                                                    <button type="button" class="btn-danger"
                                                        style="padding:5px 10px; font-size:12px; background: #dc3545; color: white; border: none;"
                                                        onclick="openRejectModal('<?php echo $doc['id']; ?>')" data-en="Reject"
                                                        data-am="ውድቅ">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Accordion 2: Transcript -->
                <button class="accordion <?php echo ($target == 'transcript') ? 'active' : ''; ?>">
                    <span data-en="To Official Transcript Professional" data-am="ለኦፊሴላዊ ትራንስክሪፕት ባለሙያ">To Official
                        Transcript Professional</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="panel" style="display: <?php echo ($target == 'transcript') ? 'block' : 'none'; ?>">
                    <div class="sub-section" style="border-bottom: none;">
                        <h3 data-en="Transfer In Request" data-am="የዝውውር ጥያቄ">Transfer In Request</h3>

                        <!-- Student ID Search Box -->
                        <div class="search-box">
                            <div class="search-input-group">
                                <label data-en="🔍 Search by Student ID" data-am="🔍 በተማሪ መታወቂያ ፈልግ">🔍 Search by
                                    Student ID</label>
                                <input type="text" id="tr_search_id" placeholder="Enter Student ID..."
                                    data-en-placeholder="Enter Student ID..." data-am-placeholder="የተማሪ መታወቂያ ያስገቡ...">
                            </div>
                            <button type="button" class="search-btn" id="tr_search_btn" onclick="searchStudent('tr')">
                                <span class="spinner"></span>
                                <i class="fas fa-search btn-text"></i>
                                <span class="btn-text" data-en="Search" data-am="ፈልግ">Search</span>
                            </button>
                        </div>
                        <div class="search-feedback" id="tr_feedback"></div>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-fields-wrapper" id="tr_fields">
                                <div class="form-group two-col"
                                    style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                    <div>
                                        <label data-en="Student ID" data-am="የተማሪ መታወቂያ">Student ID</label>
                                        <input type="text" name="student_id" id="tr_student_id" required
                                            placeholder="Student ID" readonly
                                            style="background:#f0f0f0; cursor:not-allowed;"
                                            data-en-placeholder="Student ID" data-am-placeholder="የተማሪ መታወቂያ">
                                    </div>
                                    <div>
                                        <label data-en="Sex" data-am="ጾታ">Sex</label>
                                        <select id="tr_sex" disabled style="background:#f0f0f0; cursor:not-allowed;">
                                            <option value="M" data-en="Male" data-am="ወንድ">Male</option>
                                            <option value="F" data-en="Female" data-am="ሴት">Female</option>
                                        </select>
                                        <input type="hidden" name="sex" id="tr_sex_hidden" value="M">
                                    </div>
                                </div>
                                <div class="form-group three-col"
                                    style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                                    <div><label data-en="First Name" data-am="የመጀመሪያ ስም">First Name</label><input
                                            type="text" name="first_name" id="tr_first_name" required
                                            placeholder="First Name" readonly
                                            style="background:#f0f0f0; cursor:not-allowed;"
                                            data-en-placeholder="First Name" data-am-placeholder="የመጀመሪያ ስም"></div>
                                    <div><label data-en="Middle Name" data-am="የአባት ስም">Middle Name</label><input
                                            type="text" name="middle_name" id="tr_middle_name" required
                                            placeholder="Middle Name" readonly
                                            style="background:#f0f0f0; cursor:not-allowed;"
                                            data-en-placeholder="Middle Name" data-am-placeholder="የአባት ስም"></div>
                                    <div><label data-en="Last Name" data-am="የአያት ስም">Last Name</label><input
                                            type="text" name="last_name" id="tr_last_name" required
                                            placeholder="Last Name" readonly
                                            style="background:#f0f0f0; cursor:not-allowed;"
                                            data-en-placeholder="Last Name" data-am-placeholder="የአያት ስም"></div>
                                </div>
                                <div class="form-group">
                                    <label data-en="Department" data-am="ትምህርት ክፍል">Department</label>
                                    <select id="tr_department_id" disabled
                                        style="background:#f0f0f0; cursor:not-allowed;">
                                        <option value="" data-en="Select Department" data-am="ትምህርት ክፍል ይምረጡ">Select
                                            Department</option>
                                        <?php foreach ($departments as $d):
                                            $d_name_en = $d['name'];
                                            $d_name_am = $academic_translations[$d_name_en] ?? $d_name_en;
                                            ?>
                                            <option value="<?php echo $d['id']; ?>"
                                                data-en="<?php echo htmlspecialchars($d_name_en); ?>"
                                                data-am="<?php echo htmlspecialchars($d_name_am); ?>">
                                                <?php echo htmlspecialchars($d_name_en); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="department_id" id="tr_department_id_hidden" value="">
                                </div>
                                <div class="form-group three-col"
                                    style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
                                    <div><label data-en="Year of Study" data-am="የጥናት ዓመት">Year of Study</label><input
                                            type="number" name="year" id="tr_year" min="1" max="8" required readonly
                                            style="background:#f0f0f0; cursor:not-allowed;">
                                    </div>
                                    <div><label data-en="Semester" data-am="ሴሚስተር">Semester</label><input type="number"
                                            name="semester" id="tr_semester" min="1" max="3" required readonly
                                            style="background:#f0f0f0; cursor:not-allowed;"></div>
                                    <div><label data-en="Transcript File" data-am="የትራንስክሪፕት ፋይል">Transcript File
                                        </label><input type="file" name="transcript_file" required
                                            accept=".pdf,.jpg,.jpeg,.png">
                                    </div>
                                </div>
                                <button type="submit" name="send_transfer" class="btn-primary"
                                    data-en="Send Transfer Order" data-am="የዝውውር ትእዛዝ ላክ">Send Transfer Order</button>
                            </div>
                        </form>
                    </div>
                </div>


            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <!-- Reject Modal -->
    <div id="rejectModal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:#fff; padding:20px; border-radius:8px; width:400px; max-width:90%;">
            <h3 data-en="Rejection Reason" data-am="የውድቅ ማድረጊያ ምክንያት" style="margin-top:0; color:#dc3545;">Rejection
                Reason</h3>
            <p data-en="Please enter the reason for rejection (required):"
                data-am="እባክዎ የውድቅ ማድረጊያ ምክን ያትዎን ይፃፉ (ግዴታ):">Please enter the reason for rejection (required):</p>
            <textarea id="modalRejectionReason" rows="4"
                style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; margin-bottom:15px;"></textarea>
            <div style="text-align:right;">
                <button type="button" class="btn-secondary" onclick="closeRejectModal()" data-en="Cancel"
                    data-am="ሰርዝ">Cancel</button>
                <button type="button" class="btn-danger"
                    style="background:#dc3545; color:white; border:none; margin-left:10px;"
                    onclick="submitRejectModal()" data-en="Confirm Reject" data-am="ማረጋገጫ አረጋግጥ">Confirm Reject</button>
            </div>
        </div>
    </div>

    <script>
        let currentRejectId = null;

        function openRejectModal(id) {
            currentRejectId = id;
            document.getElementById('modalRejectionReason').value = '';
            document.getElementById('rejectModal').style.display = 'flex';
        }

        function closeRejectModal() {
            currentRejectId = null;
            document.getElementById('rejectModal').style.display = 'none';
        }

        function submitRejectModal() {
            if (!currentRejectId) return;
            const reason = document.getElementById('modalRejectionReason').value.trim();
            if (reason === '') {
                alert(localStorage.getItem('dmu_lang') === 'am' ? 'ምክንያት መጻፍ ግዴታ ነው!' : 'Rejection reason is required!');
                return;
            }

            document.getElementById('rej_reason_' + currentRejectId).value = reason;
            document.getElementById('rejectForm_' + currentRejectId).submit();
        }
    </script>

    <script>
        // Accordion Logic
        var acc = document.getElementsByClassName("accordion");
        for (var i = 0; i < acc.length; i++) {
            acc[i].addEventListener("click", function () {
                this.classList.toggle("active");
                var panel = this.nextElementSibling;
                if (panel.style.display === "block") {
                    panel.style.display = "none";
                } else {
                    panel.style.display = "block";
                }
            });
        }

        // Student ID Search - Enter key support
        document.getElementById('cs_search_id').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); searchStudent('cs'); }
        });
        document.getElementById('tr_search_id').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); searchStudent('tr'); }
        });

        function clearFormFields(prefix) {
            // Clear all form input fields
            document.getElementById(prefix + '_student_id').value = '';
            document.getElementById(prefix + '_first_name').value = '';
            document.getElementById(prefix + '_middle_name').value = '';
            document.getElementById(prefix + '_last_name').value = '';
            document.getElementById(prefix + '_sex').value = 'M';
            document.getElementById(prefix + '_department_id').value = '';

            if (prefix === 'cs') {
                document.getElementById('cs_batch').value = '';
                document.getElementById('cs_semester').value = '';
                document.getElementById('cs_academic_year').value = '';
                // Clear hidden inputs for disabled selects
                document.getElementById('cs_sex_hidden').value = 'M';
                document.getElementById('cs_department_id_hidden').value = '';
            } else {
                document.getElementById('tr_year').value = '';
                document.getElementById('tr_semester').value = '';
                // Clear hidden inputs for disabled selects
                document.getElementById('tr_sex_hidden').value = 'M';
                document.getElementById('tr_department_id_hidden').value = '';
            }

            // Remove auto-filled styling
            var fields = document.getElementById(prefix + '_fields');
            fields.querySelectorAll('input, select').forEach(function (el) {
                el.classList.remove('auto-filled');
            });
        }

        function searchStudent(prefix) {
            var searchInput = document.getElementById(prefix + '_search_id');
            var studentId = searchInput.value.trim();
            var feedback = document.getElementById(prefix + '_feedback');
            var searchBtn = document.getElementById(prefix + '_search_btn');

            if (!studentId) {
                feedback.className = 'search-feedback error';
                feedback.innerHTML = '<i class="fas fa-exclamation-circle"></i> <span data-en="Please enter a Student ID" data-am="እባክዎ የተማሪ መታወቂያ ያስገቡ">Please enter a Student ID</span>';
                searchInput.focus();
                if (typeof updateLanguage === 'function') updateLanguage();
                return;
            }

            // Show loading - reset feedback
            searchBtn.classList.add('loading');
            feedback.className = 'search-feedback';
            feedback.removeAttribute('style');

            fetch('../../api/search_student.php?student_id=' + encodeURIComponent(studentId))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    searchBtn.classList.remove('loading');

                    if (data.success && data.student) {
                        var s = data.student;

                        // Fill the hidden student_id field
                        document.getElementById(prefix + '_student_id').value = s.student_id;

                        // Fill names
                        document.getElementById(prefix + '_first_name').value = s.first_name || '';
                        document.getElementById(prefix + '_middle_name').value = s.middle_name || '';
                        document.getElementById(prefix + '_last_name').value = s.last_name || '';

                        // Fill sex
                        document.getElementById(prefix + '_sex').value = s.sex || 'M';

                        // Fill department
                        if (s.department_id) {
                            document.getElementById(prefix + '_department_id').value = s.department_id;
                        }

                        // Sync hidden inputs for disabled selects
                        if (prefix === 'cs') {
                            document.getElementById('cs_sex_hidden').value = s.sex || 'M';
                            document.getElementById('cs_department_id_hidden').value = s.department_id || '';
                        } else {
                            document.getElementById('tr_sex_hidden').value = s.sex || 'M';
                            document.getElementById('tr_department_id_hidden').value = s.department_id || '';
                        }

                        // Fill year/batch
                        if (prefix === 'cs') {
                            document.getElementById('cs_batch').value = s.batch || '';
                            document.getElementById('cs_semester').value = s.current_semester || '';
                            document.getElementById('cs_academic_year').value = s.academic_year || '';
                        } else {
                            document.getElementById('tr_year').value = s.batch || '';
                            document.getElementById('tr_semester').value = s.current_semester || '';
                        }

                        // Add auto-filled visual class
                        var fields = document.getElementById(prefix + '_fields');
                        var inputs = fields.querySelectorAll('input, select');
                        inputs.forEach(function (el) {
                            if (el.value) el.classList.add('auto-filled');
                        });

                        // Show success
                        var statusText = s.status || 'Active';
                        feedback.className = 'search-feedback found';
                        feedback.innerHTML = '<i class="fas fa-check-circle"></i> <span data-en="Student found: ' +
                            s.first_name + ' ' + (s.middle_name || '') + ' ' + (s.last_name || '') +
                            ' | Dept: ' + (s.department_name || 'N/A') + ' | Status: ' + statusText + '"' +
                            ' data-am="ተማሪ ተገኝቷል: ' +
                            s.first_name + ' ' + (s.middle_name || '') + ' ' + (s.last_name || '') +
                            ' | ክፍል: ' + (s.department_name || 'የለም') + ' | ሁኔታ: ' + statusText + '">' +
                            'Student found: ' + s.first_name + ' ' + (s.middle_name || '') + ' ' + (s.last_name || '') +
                            ' | Dept: ' + (s.department_name || 'N/A') + ' | Status: ' + statusText + '</span>';

                        if (typeof updateLanguage === 'function') updateLanguage();
                    } else {
                        // Clear all form fields when student not found
                        clearFormFields(prefix);

                        // Not found - for Transfer In, allow manual entry; for CS, show warning
                        if (prefix === 'tr') {
                            // Transfer In: Student may not exist yet, allow manual entry
                            document.getElementById(prefix + '_student_id').value = studentId;
                            // Make input fields editable for manual entry
                            var trFields = document.getElementById(prefix + '_fields');
                            trFields.querySelectorAll('input:not([type="hidden"])').forEach(function (el) {
                                el.removeAttribute('readonly');
                                el.style.background = '';
                                el.style.cursor = '';
                            });
                            // Enable select dropdowns for manual entry
                            var trSex = document.getElementById('tr_sex');
                            trSex.removeAttribute('disabled');
                            trSex.style.background = '';
                            trSex.style.cursor = '';
                            trSex.setAttribute('name', 'sex');
                            document.getElementById('tr_sex_hidden').remove();

                            var trDept = document.getElementById('tr_department_id');
                            trDept.removeAttribute('disabled');
                            trDept.style.background = '';
                            trDept.style.cursor = '';
                            trDept.setAttribute('name', 'department_id');
                            document.getElementById('tr_department_id_hidden').remove();

                            feedback.className = 'search-feedback not-found';
                            feedback.innerHTML = '<i class="fas fa-info-circle"></i> <span data-en="Student not found in system. You can enter details manually for Transfer In." data-am="ተማሪ በስርዓቱ አልተገኘም። ለዝውውር መረጃውን በእጅ ማስገባት ይችላሉ።">Student not found in system. You can enter details manually for Transfer In.</span>';
                        } else {
                            feedback.className = 'search-feedback not-found';
                            feedback.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <span data-en="Student not found! Please check the Student ID and try again." data-am="ተማሪ አልተገኘም! እባክዎ የተማሪ መታወቂያውን ያረጋግጡና እንደገና ይሞክሩ።">Student not found! Please check the Student ID and try again.</span>';
                        }
                        if (typeof updateLanguage === 'function') updateLanguage();
                    }
                })
                .catch(function (err) {
                    searchBtn.classList.remove('loading');
                    feedback.className = 'search-feedback error';
                    feedback.innerHTML = '<i class="fas fa-times-circle"></i> <span data-en="Error searching. Please try again." data-am="ፍለጋ ስህተት። እባክዎ እንደገና ይሞክሩ።">Error searching. Please try again.</span>';
                    if (typeof updateLanguage === 'function') updateLanguage();
                });
        }
    </script>
    <script src="../../assets/js/bilingual.js"></script>
    <script>
        // Trigger bilingual update on load to handle dropdowns
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof updateLanguage === 'function') {
                updateLanguage();
            }
        });
    </script>
</body>

</html>