<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['transcript_pro']);

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

// Handle Accept & Record (direct save from modal)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accept_transfer'])) {
    $order_id = $_POST['order_id'];
    $student_id_str = trim($_POST['student_id_str']);

    if (empty($student_id_str)) {
        $error = "<span data-en='Student ID is required.' data-am='የተማሪ መታወቂያ ያስፈልጋል።'>Student ID is required.</span>";
    } else {
        try {
            // Find the student by student_id string
            $stmt = $pdo->prepare("SELECT s.user_id, s.student_id FROM students s WHERE s.student_id = ? LIMIT 1");
            $stmt->execute([$student_id_str]);
            $found_student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$found_student || !$found_student['user_id']) {
                $error = "<span data-en='Student with ID ' data-am='በመታወቂያ '>Student with ID </span>'" . htmlspecialchars($student_id_str) . "'<span data-en=' not found in the system.' data-am=' ተማሪ በስርዓቱ አልተገኘም።'> not found in the system.</span>";
            } else {
                // Get the order details
                $order_stmt = $pdo->prepare("SELECT * FROM official_transcript WHERE id = ?");
                $order_stmt->execute([$order_id]);
                $order = $order_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$order) {
                    $error = "<span data-en='Transfer order not found.' data-am='የዝውውር ትእዛዝ አልተገኘም።'>Transfer order not found.</span>";
                } else {
                    // Mark the order as Accepted
                    $upd = $pdo->prepare("UPDATE official_transcript SET status = 'Accepted' WHERE id = ?");
                    $upd->execute([$order_id]);

                    // Insert uploaded courses
                    $student_dept_id = $order['department_id'] ?? 1;
                    $rc_name = $_POST['rc_name'] ?? [];
                    $rc_cr = $_POST['rc_cr'] ?? [];
                    $rc_batch = $_POST['rc_batch'] ?? [];
                    $rc_sem = $_POST['rc_sem'] ?? [];
                    // Per batch+semester total billing credit hours
                    $bs_keys = $_POST['bs_key'] ?? [];
                    $bs_total_cr = $_POST['bs_total_cr'] ?? [];
                    $batchSemCrMap = [];
                    for ($k = 0; $k < count($bs_keys); $k++) {
                        $batchSemCrMap[$bs_keys[$k]] = (int) ($bs_total_cr[$k] ?? 0);
                    }

                    if (!empty($rc_name)) {
                        $stmt_course = $pdo->prepare("INSERT INTO courses (department_id, batch, semester, course_name, credit_hour, credit_hours, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        for ($i = 0; $i < count($rc_name); $i++) {
                            if (!empty($rc_name[$i])) {
                                $bKey = (int) $rc_batch[$i] . '_' . (int) $rc_sem[$i];
                                $totalCr = $batchSemCrMap[$bKey] ?? 0;
                                $stmt_course->execute([
                                    $student_dept_id,
                                    (int) $rc_batch[$i],
                                    (int) $rc_sem[$i],
                                    trim($rc_name[$i]),
                                    (int) $rc_cr[$i],
                                    $totalCr,
                                    $_SESSION['user_id']
                                ]);
                            }
                        }
                    }

                    $_SESSION["flash_success"] = "<span data-en='Successfully recorded the courses and cost share for student " . htmlspecialchars($student_id_str) . ".' data-am='ለተማሪ " . htmlspecialchars($student_id_str) . " 코ርሶች እና ኮስት ሼሪንግ በተሳካ ሁኔታ ተመዝግቧል።'>Successfully recorded the courses and cost share for student " . htmlspecialchars($student_id_str) . ".</span>";
                    header("Location: " . $_SERVER["PHP_SELF"]);
                    exit();
                }
            }
        } catch (PDOException $e) {
            $error = "<span data-en='Database Error: ' data-am='የውሂብ ጎታ ስህተት: '>Database Error: </span>" . $e->getMessage();
        }
    }
}

// Fetch Pending Transfer Orders
$transfer_orders = $pdo->query("SELECT t.*, t.student_id_str, d.name as dept_name 
                                FROM official_transcript t 
                                LEFT JOIN departments d ON t.department_id = d.id 
                                WHERE t.recipient_role = 'transcript_pro' 
                                AND t.request_type = 'transfer' 
                                AND (t.status = 'Pending' OR t.status IS NULL)
                                ORDER BY t.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Accept Cost Share - Transcript Pro" data-am="የወጪ መጋራትን ተቀበል - ትራንስክሪፕት ባለሙያ">Accept Cost Share -
        Transcript Pro</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgb(0, 0, 0);
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 95%;
            height: 90vh;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .detail-row {
            display: flex;
            border-bottom: 1px solid #eee;
            padding: 8px 0;
        }

        .detail-label {
            font-weight: bold;
            width: 40%;
            color: #555;
        }

        .detail-value {
            width: 60%;
            color: #333;
        }

        /* Confirmation Modal */
        .confirm-overlay {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(3px);
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .confirm-box {
            background: white;
            width: 440px;
            max-width: 90%;
            margin: 12% auto;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            animation: slideDown 0.3s ease;
        }

        .confirm-header {
            padding: 20px 24px 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .confirm-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .confirm-icon.warning {
            background: #fff3cd;
            color: #856404;
        }

        .confirm-icon.error {
            background: #f8d7da;
            color: #721c24;
        }

        .confirm-title {
            font-size: 17px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0;
        }

        .confirm-body {
            padding: 0 24px 16px;
            color: #555;
            font-size: 14px;
            line-height: 1.6;
        }

        .confirm-body .student-info {
            background: #f8f9fa;
            border-left: 3px solid var(--primary-color, #1565c0);
            padding: 10px 14px;
            border-radius: 0 6px 6px 0;
            margin-top: 10px;
            font-size: 13px;
        }

        .confirm-body .student-info strong {
            color: #333;
        }

        .confirm-footer {
            padding: 12px 24px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .confirm-footer button {
            padding: 9px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .confirm-cancel {
            background: #e9ecef;
            color: #495057;
        }

        .confirm-cancel:hover {
            background: #dee2e6;
        }

        .confirm-ok {
            background: #28a745;
            color: white;
        }

        .confirm-ok:hover {
            background: #218838;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .confirm-ok-alert {
            background: var(--primary-color, #1565c0);
            color: white;
        }

        .confirm-ok-alert:hover {
            opacity: 0.9;
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
                    <h2 data-en="Accept Cost Share (Record Data)" data-am="የወጪ መጋራትን ተቀበል (መረጃ መዝግብ)">Accept Cost Share
                        (Record Data)</h2>
                </div>

                <?php if ($msg): ?>
                    <div class="success-msg">
                        <?php echo $msg; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="error-msg">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Transfer In Requests Section -->
                <?php if (!empty($transfer_orders)): ?>
                    <div class="card mt-20">
                        <h3 data-en="Transfer In Requests (Pending)" data-am="የዝውውር ጠያቄዎች">Transfer In Requests (Pending)
                        </h3>
                        <table class="table" style="width:100%; border-collapse:collapse; margin-top:10px;">
                            <thead>
                                <tr style="background:#f9f9f9; text-align:left;">
                                    <th style="padding:10px; border:1px solid #ddd;">Student ID</th>
                                    <th style="padding:10px; border:1px solid #ddd;">Name</th>
                                    <th style="padding:10px; border:1px solid #ddd;">Dept</th>
                                    <th style="padding:10px; border:1px solid #ddd;">Amount</th>
                                    <th style="padding:10px; border:1px solid #ddd;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transfer_orders as $order): ?>
                                    <tr>
                                        <td style="padding:10px; border:1px solid #ddd;">
                                            <?php echo htmlspecialchars($order['student_id_str'] ?? 'N/A'); ?>
                                        </td>
                                        <td style="padding:10px; border:1px solid #ddd;">
                                            <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                                        </td>
                                        <td style="padding:10px; border:1px solid #ddd;">
                                            <?php echo htmlspecialchars($order['dept_name']); ?>
                                        </td>
                                        <td style="padding:10px; border:1px solid #ddd;">
                                            <?php echo number_format($order['cost_share_amount'], 2); ?> ETB
                                        </td>
                                        <td style="padding:10px; border:1px solid #ddd;">
                                            <button type="button" class="btn-primary"
                                                onclick="openModal(<?php echo htmlspecialchars(json_encode($order)); ?>)"
                                                style="padding:5px 10px; font-size:12px;">
                                                <i class="fas fa-edit"></i> <span data-en="View and Record"
                                                    data-am="እይ እና መዝግብ">View and Record</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (empty($transfer_orders) && empty($msg) && empty($error)): ?>
                    <div class="card mt-20">
                        <p data-en="No pending transfer requests." data-am="በመጠባበቅ ላይ ያሉ የዝውውር ጥያቄዎች የሉም።">No pending
                            transfer requests.</p>
                    </div>
                <?php endif; ?>

                <!-- View Detail Modal -->
                <div id="detailModal" class="modal">
                    <div class="modal-content"
                        style="width: 95% !important; max-width: 95% !important; height: 90vh !important;">
                        <span class="close" onclick="closeModal()">&times;</span>
                        <h3 style="margin-top:0; border-bottom:2px solid var(--primary-color); padding-bottom:10px;">
                            Request Details</h3>

                        <div id="modalBody" style="flex:1; overflow:hidden;">
                            <!-- Content populated by JS -->
                        </div>

                        <div style="margin-top: 20px; text-align: right; border-top: 1px solid #ccc; padding-top:10px;">
                            <button class="btn-secondary" onclick="closeModal()" data-en="Close"
                                data-am="ዝጋ">Close</button>
                            <button type="button" id="acceptBtn" class="btn-success"><i class="fas fa-save"></i> <span
                                    data-en="Record Student Information" data-am="የተማሪ መረጃ መዝግብ">Record Student
                                    Information</span></button>
                        </div>
                    </div>
                </div>

                <!-- Hidden form for Accept & Record POST -->
                <form id="acceptForm" method="POST" style="display:none;">
                    <input type="hidden" name="accept_transfer" value="1">
                    <input type="hidden" name="order_id" id="acceptOrderId">
                    <input type="hidden" name="student_id_str" id="acceptStudentIdStr">
                    <div id="hiddenCoursesContainer"></div>
                </form>

                <!-- Custom Confirmation Modal -->
                <div id="confirmOverlay" class="confirm-overlay">
                    <div class="confirm-box">
                        <div class="confirm-header">
                            <div class="confirm-icon" id="confirmIcon"><i class="fas fa-question-circle"></i></div>
                            <h4 class="confirm-title" id="confirmTitle">Confirm</h4>
                        </div>
                        <div class="confirm-body" id="confirmBody"></div>
                        <div class="confirm-footer" id="confirmFooter"></div>
                    </div>
                </div>

                <script>
                    var currentOrderData = null;

                    function openModal(data) {
                        currentOrderData = data;
                        var modal = document.getElementById("detailModal");
                        var body = document.getElementById("modalBody");
                        var acceptBtn = document.getElementById("acceptBtn");

                        var fileUrl = data.clearance_file ? '../../uploads/clearances/' + data.clearance_file : '';
                        var iframeHtml = fileUrl ? `<iframe src="${fileUrl}" style="width:100%; height:100%; border:none;"></iframe>` : `<p style="color:red; text-align:center; padding:50px;">No transcript file uploaded.</p>`;

                        var sidDisplay = (data.student_id_str && data.student_id_str !== 'null' && data.student_id_str !== '')
                            ? `<input type="text" id="modalStudentIdInput" value="${data.student_id_str}" style="padding:5px 10px; border:1px solid #ccc; width:150px; background:#f4f4f4; cursor:not-allowed;" readonly />`
                            : `<input type="text" id="modalStudentIdInput" placeholder="Enter Student ID" style="padding:5px 10px; border:1px solid #ccc; border-radius:4px; width:150px;" required />`;

                        var html = `
                            <div style="display:flex; height:100%;">
                                <!-- Left side: Iframe Preview -->
                                <div style="flex: 1.5; border-right: 1px solid #ccc; padding-right:15px; height: 100%;">
                                    <h4 data-en="Uploaded Transcript Document" data-am="የተጫነው ትራንስክሪፕት ፋይል" style="margin-top:0;">Uploaded Transcript Document</h4>
                                    <div style="height: calc(100% - 30px); border: 1px solid #ddd;">
                                        ${iframeHtml}
                                    </div>
                                </div>
                                <!-- Right side: Form -->
                                <div style="flex: 1; padding-left:15px; overflow-y:auto; height: 100%;">
                                    <h4 data-en="Record Student Information" data-am="የተማሪውን መረጃ መዝግብ" style="margin-top:0;">Record Student Information</h4>
                                    
                                    <div style="background:#f9f9f9; padding:10px; border-radius:5px; margin-bottom:15px;">
                                        <p style="margin:5px 0;"><strong>Name:</strong> ${data.first_name} ${data.middle_name || ''} ${data.last_name}</p>
                                        <p style="margin:5px 0;"><strong>Department:</strong> ${data.dept_name || 'N/A'}</p>
                                        <p style="margin:5px 0; display:flex; align-items:center;"><strong>Student ID:</strong> &nbsp;${sidDisplay}</p>
                                    </div>

                                    <h5 style="border-bottom:1px solid #eee; padding-bottom:5px;">Transferred Courses</h5>
                                    
                                    <table style="width:100%; border-collapse:collapse; margin-bottom:10px;">
                                        <thead>
                                            <tr style="background:#f4f4f4; text-align:left;">
                                                <th style="padding:5px; border:1px solid #ddd;">Crs Name</th>
                                                <th style="padding:5px; border:1px solid #ddd;">Batch</th>
                                                <th style="padding:5px; border:1px solid #ddd;">Sem</th>
                                                <th style="padding:5px; border:1px solid #ddd;">Cr Hr</th>
                                                <th style="padding:5px; border:1px solid #ddd;">Act</th>
                                            </tr>
                                        </thead>
                                        <tbody id="courseRowsBody">
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="5" style="padding:5px;">
                                                    <button type="button" class="btn-secondary" onclick="addCourseRow()" style="padding:4px 8px; font-size:12px;">+ Add Course</button>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>

                                    <h5 style="border-bottom:1px solid #eee; padding-bottom:5px; margin-top:15px;" data-en="Credit Hours (Total Billings) per Batch/Semester" data-am="የክሬዲት ሰዓት (ጠቅላላ ክፍያ) በባች/ሴሚስተር">Credit Hours (Total Billings) per Batch/Semester</h5>
                                    <div id="batchSemSummary" style="margin-bottom:10px;">
                                        <p style="color:#888; font-size:12px;" data-en="Add courses above to see batch/semester groups here." data-am="ከላይ ኮርሶችን ያስገቡ፣ የባች/ሴሚስተር ቡድኖች እዚህ ይታያሉ።">Add courses above to see batch/semester groups here.</p>
                                    </div>
                                    
                                </div>
                            </div>
                        `;

                        body.innerHTML = html;
                        addCourseRow(); // Add one row by default

                        // Set Accept & Record Action
                        acceptBtn.onclick = function () {
                            var sid = document.getElementById('modalStudentIdInput') ? document.getElementById('modalStudentIdInput').value.trim() : data.student_id_str;

                            if (!sid) {
                                showAlert(
                                    '<i class="fas fa-exclamation-circle"></i>',
                                    'error',
                                    'Missing Information',
                                    '<span data-en="Student ID is required!" data-am="የተማሪ መታወቂያ ያስፈልጋል!">Student ID is required!</span>'
                                );
                                return;
                            }

                            // Validate Course Rows
                            let courseElements = document.getElementsByClassName('course-name-in');
                            let crhrElements = document.getElementsByClassName('cr-hr-in');
                            let batchElements = document.getElementsByClassName('batch-in');
                            let semElements = document.getElementsByClassName('sem-in');

                            if (courseElements.length === 0) {
                                showAlert(
                                    '<i class="fas fa-exclamation-circle"></i>',
                                    'error',
                                    'Missing Courses',
                                    '<span data-en="Please add at least one course record." data-am="እባክዎ ቢያንስ አንድ ኮርስ ያስገቡ።">Please add at least one course record.</span>'
                                );
                                return;
                            }

                            let totalCredits = 0;
                            let courseDataHtml = '';

                            for (let i = 0; i < courseElements.length; i++) {
                                let cname = courseElements[i].value.trim();
                                let credits = parseInt(crhrElements[i].value);
                                let b = parseInt(batchElements[i].value);
                                let s = parseInt(semElements[i].value);

                                if (!cname || isNaN(credits) || isNaN(b) || isNaN(s)) {
                                    showAlert(
                                        '<i class="fas fa-exclamation-circle"></i>',
                                        'error',
                                        'Incomplete Rows',
                                        '<span data-en="Please fill all fields strictly in all course rows." data-am="እባክዎትን ሁሉንም የኮርስ መረጃዎች በትክክል ይሙሉ::">Please fill all fields strictly in all course rows.</span>'
                                    );
                                    return;
                                }
                                totalCredits += credits;
                                courseDataHtml += `<input type='hidden' name='rc_name[]' value='${cname}'>`;
                                courseDataHtml += `<input type='hidden' name='rc_cr[]' value='${credits}'>`;
                                courseDataHtml += `<input type='hidden' name='rc_batch[]' value='${b}'>`;
                                courseDataHtml += `<input type='hidden' name='rc_sem[]' value='${s}'>`;
                            }

                            // Validate batch/semester total billing credit hours
                            let bsTotalInputs = document.getElementsByClassName('bs-total-cr-in');
                            let bsKeyInputs = document.getElementsByClassName('bs-key-in');
                            for (let j = 0; j < bsTotalInputs.length; j++) {
                                let val = parseInt(bsTotalInputs[j].value);
                                if (isNaN(val) || val < 1) {
                                    showAlert(
                                        '<i class="fas fa-exclamation-circle"></i>',
                                        'error',
                                        'Missing Total Billings',
                                        '<span data-en="Please fill Credit Hours (Total Billings) for all batch/semester groups." data-am="እባክዎ ለሁሉም ባች/ሴሚስተር ቡድኖች ክሬዲት ሰዓት (ጠቅላላ ክፍያ) ያስገቡ።">Please fill Credit Hours (Total Billings) for all batch/semester groups.</span>'
                                    );
                                    return;
                                }
                                courseDataHtml += `<input type='hidden' name='bs_key[]' value='${bsKeyInputs[j].value}'>`;
                                courseDataHtml += `<input type='hidden' name='bs_total_cr[]' value='${val}'>`;
                            }

                            // Show custom confirmation
                            var studentName = (data.first_name || '') + ' ' + (data.middle_name || '') + ' ' + (data.last_name || '');
                            showConfirm(
                                '<span data-en="Confirm Accept & Record" data-am="ማረጋገጫ ተቀበል እና መዝግብ">Confirm Accept & Record</span>',
                                '<span data-en="Are you sure you want to accept and record this transfer information?" data-am="ይህን ዝውውር ተቀብለው ለመመዝገብ እርግጠኛ ነዎት?">Are you sure you want to accept and record this transfer information?</span>' +
                                '<div class="student-info">' +
                                '<div><strong data-en="Student:" data-am="ተማሪ:">Student:</strong> ' + studentName.trim() + '</div>' +
                                '<div><strong data-en="ID:" data-am="መታወቂያ:">ID:</strong> ' + sid + '</div>' +
                                '<div><strong data-en="Total Recorded Courses:" data-am="የተመዘገቡ ኮርሶች ብዛት:">Total Recorded Courses:</strong> ' + courseElements.length + '</div>' +
                                '<div><strong data-en="Total Credit Hours:" data-am="ጠቅላላ ክሬዲት አወር:">Total Credit Hours:</strong> ' + totalCredits + '</div>' +
                                '</div>',
                                function () {
                                    document.getElementById('hiddenCoursesContainer').innerHTML = courseDataHtml;
                                    document.getElementById('acceptOrderId').value = data.id;
                                    document.getElementById('acceptStudentIdStr').value = sid;
                                    document.getElementById('acceptForm').submit();
                                }
                            );
                        };

                        modal.style.display = "block";

                        if (typeof updateLanguage === 'function') updateLanguage();
                    }

                    function closeModal() {
                        document.getElementById("detailModal").style.display = "none";
                    }

                    function addCourseRow() {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td style="padding:2px;"><input type="text" class="course-name-in" style="width:100%; padding:4px;" required placeholder="Crs"></td>
                            <td style="padding:2px;"><input type="number" class="batch-in" style="width:100%; padding:4px;" required min="1" max="8" placeholder="Btc" onchange="updateBatchSemSummary()" oninput="updateBatchSemSummary()"></td>
                            <td style="padding:2px;"><input type="number" class="sem-in" style="width:100%; padding:4px;" required min="1" max="4" placeholder="Sem" onchange="updateBatchSemSummary()" oninput="updateBatchSemSummary()"></td>
                            <td style="padding:2px;"><input type="number" class="cr-hr-in" style="width:100%; padding:4px;" required min="1" max="10" placeholder="Cr"></td>
                            <td style="padding:2px; text-align:center;"><button type="button" onclick="this.parentElement.parentElement.remove(); updateBatchSemSummary();" style="background:#dc3545; color:#fff; border:none; padding:4px 6px; cursor:pointer;"><i class="fas fa-trash"></i></button></td>
                        `;
                        document.getElementById('courseRowsBody').appendChild(tr);
                    }

                    function updateBatchSemSummary() {
                        let batchEls = document.getElementsByClassName('batch-in');
                        let semEls = document.getElementsByClassName('sem-in');
                        let groups = {};
                        for (let i = 0; i < batchEls.length; i++) {
                            let b = parseInt(batchEls[i].value);
                            let s = parseInt(semEls[i].value);
                            if (!isNaN(b) && !isNaN(s)) {
                                let key = b + '_' + s;
                                if (!groups[key]) groups[key] = { batch: b, sem: s };
                            }
                        }
                        let container = document.getElementById('batchSemSummary');
                        let keys = Object.keys(groups);
                        if (keys.length === 0) {
                            container.innerHTML = '<p style="color:#888; font-size:12px;" data-en="Add courses above to see batch/semester groups here." data-am="ከላይ ኮርሶችን ያስገቡ፣ የባች/ሴሚስተር ቡድኖች እዚህ ይታያሉ።">Add courses above to see batch/semester groups here.</p>';
                        } else {
                            let html = '<table style="width:100%; border-collapse:collapse;">';
                            html += '<thead><tr style="background:#e8f5e9; text-align:left;">';
                            html += '<th style="padding:6px; border:1px solid #ddd;">Batch</th>';
                            html += '<th style="padding:6px; border:1px solid #ddd;">Semester</th>';
                            html += '<th style="padding:6px; border:1px solid #ddd;">Cr Hr (Total Billings)</th>';
                            html += '</tr></thead><tbody>';
                            // Preserve existing values
                            let oldInputs = document.getElementsByClassName('bs-total-cr-in');
                            let oldKeys = document.getElementsByClassName('bs-key-in');
                            let oldValues = {};
                            for (let x = 0; x < oldKeys.length; x++) {
                                oldValues[oldKeys[x].value] = oldInputs[x].value;
                            }
                            for (let k of keys) {
                                let g = groups[k];
                                let prevVal = oldValues[k] || '';
                                html += '<tr>';
                                html += '<td style="padding:6px; border:1px solid #ddd;">Batch ' + g.batch + '</td>';
                                html += '<td style="padding:6px; border:1px solid #ddd;">Semester ' + g.sem + '</td>';
                                html += '<td style="padding:6px; border:1px solid #ddd;"><input type="hidden" class="bs-key-in" value="' + k + '"><input type="number" class="bs-total-cr-in" style="width:100%; padding:4px;" min="1" max="60" placeholder="Total Cr Hr" value="' + prevVal + '" required></td>';
                                html += '</tr>';
                            }
                            html += '</tbody></table>';
                            container.innerHTML = html;
                        }
                        if (typeof updateLanguage === 'function') updateLanguage();
                    }

                    // Close modal when clicking outside
                    window.onclick = function (event) {
                        var modal = document.getElementById("detailModal");
                        if (event.target == modal) {
                            modal.style.display = "none";
                        }
                    }

                    function showConfirm(title, bodyHtml, onConfirm) {
                        var overlay = document.getElementById('confirmOverlay');
                        document.getElementById('confirmIcon').innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                        document.getElementById('confirmIcon').className = 'confirm-icon warning';
                        document.getElementById('confirmTitle').innerHTML = title;
                        document.getElementById('confirmBody').innerHTML = bodyHtml;
                        document.getElementById('confirmFooter').innerHTML =
                            '<button class="confirm-cancel" id="confirmCancelBtn" data-en="Cancel" data-am="ይቅር">Cancel</button>' +
                            '<button class="confirm-ok" id="confirmOkBtn" data-en="Yes, Accept & Record" data-am="አዎ፣ ተቀበል እና መዝግብ"><i class="fas fa-check"></i> Yes, Accept & Record</button>';
                        overlay.style.display = 'block';
                        document.getElementById('confirmCancelBtn').onclick = function () { overlay.style.display = 'none'; };
                        document.getElementById('confirmOkBtn').onclick = function () { overlay.style.display = 'none'; onConfirm(); };
                        if (typeof updateLanguage === 'function') updateLanguage();
                    }

                    function showAlert(icon, type, title, bodyHtml) {
                        var overlay = document.getElementById('confirmOverlay');
                        document.getElementById('confirmIcon').innerHTML = icon;
                        document.getElementById('confirmIcon').className = 'confirm-icon ' + type;
                        document.getElementById('confirmTitle').innerHTML = title;
                        document.getElementById('confirmBody').innerHTML = bodyHtml;
                        document.getElementById('confirmFooter').innerHTML =
                            '<button class="confirm-ok-alert" id="confirmAlertOk" data-en="OK" data-am="እሺ">OK</button>';
                        overlay.style.display = 'block';
                        document.getElementById('confirmAlertOk').onclick = function () { overlay.style.display = 'none'; };
                        if (typeof updateLanguage === 'function') updateLanguage();
                    }
                </script>

            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>