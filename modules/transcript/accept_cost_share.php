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
                    $student_user_id = $found_student['user_id'];
                    $academic_year = $order['academic_year'] ?? '';
                    $semester = $order['semester'] ?? 1;
                    $cost_amount = $order['cost_share_amount'] ?? 0;

                    // Insert into cost_sharing_agreements
                    $sql = "INSERT INTO cost_sharing_agreements 
                            (student_id, academic_year, semester, tuition_fee, food_expense, bed_expense, medication_expense, total_amount_semester, recorded_by, status) 
                            VALUES (?, ?, ?, ?, 0, 0, 0, ?, ?, 'ApprovedByCostPro')";
                    $ins = $pdo->prepare($sql);
                    $ins->execute([
                        $student_user_id,
                        $academic_year,
                        $semester,
                        $cost_amount,
                        $cost_amount,
                        $_SESSION['user_id']
                    ]);

                    // Recalculate cumulative total_amount for this student
                    $pdo->prepare("UPDATE cost_sharing_agreements SET total_amount = (SELECT t.total FROM (SELECT COALESCE(SUM(tuition_fee + food_expense + bed_expense + medication_expense), 0) as total FROM cost_sharing_agreements WHERE student_id = ?) as t) WHERE student_id = ?")->execute([$student_user_id, $student_user_id]);

                    // Mark the order as Accepted
                    $upd = $pdo->prepare("UPDATE official_transcript SET status = 'Accepted' WHERE id = ?");
                    $upd->execute([$order_id]);

                    $_SESSION["flash_success"] = "<span data-en='Successfully recorded the cost share for student " . htmlspecialchars($student_id_str) . ".' data-am='ለተማሪ " . htmlspecialchars($student_id_str) . " ኮስት ሼሪንግ በተሳካ ሁኔታ ተመዝግቧል።'>Successfully recorded the cost share for student " . htmlspecialchars($student_id_str) . ".</span>";
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
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 60%;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
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
                                                <i class="fas fa-eye"></i> View Detail
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
                        <p data-en="No pending transfer requests." data-am="በመጠባበቅ ላይ ያሉ የዝውውር ጥያቄዎች የሉም።">No pending transfer requests.</p>
                    </div>
                <?php endif; ?>

                <!-- View Detail Modal -->
                <div id="detailModal" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeModal()">&times;</span>
                        <h3 style="margin-top:0; border-bottom:2px solid var(--primary-color); padding-bottom:10px;">
                            Request Details</h3>

                        <div id="modalBody">
                            <!-- Content populated by JS -->
                        </div>

                        <div style="margin-top: 20px; text-align: right;">
                            <button class="btn-secondary" onclick="closeModal()" data-en="Close"
                                data-am="ዝጋ">Close</button>
                            <button id="acceptBtn" class="btn-success" data-en="Accept & Record"
                                data-am="ተቀበል እና መዝግብ">Accept & Record</button>
                        </div>
                    </div>
                </div>

                <!-- Hidden form for Accept & Record POST -->
                <form id="acceptForm" method="POST" style="display:none;">
                    <input type="hidden" name="accept_transfer" value="1">
                    <input type="hidden" name="order_id" id="acceptOrderId">
                    <input type="hidden" name="student_id_str" id="acceptStudentIdStr">
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

                        var sidDisplay = (data.student_id_str && data.student_id_str !== 'null' && data.student_id_str !== '')
                            ? data.student_id_str
                            : '<input type="text" id="modalStudentIdInput" placeholder="Enter Student ID" style="padding:5px 10px; border:1px solid #ccc; border-radius:4px; width:200px;" />';

                        var html = `
                            <div class="detail-row"><div class="detail-label"><span data-en="Full Name:" data-am="ሙሉ ስም:">Full Name:</span></div><div class="detail-value">${data.first_name} ${data.middle_name || ''} ${data.last_name}</div></div>
                            <div class="detail-row"><div class="detail-label"><span data-en="Sex:" data-am="ጾታ:">Sex:</span></div><div class="detail-value">${data.sex}</div></div>
                            <div class="detail-row"><div class="detail-label"><span data-en="Student ID:" data-am="የተማሪ መታወቂያ:">Student ID:</span></div><div class="detail-value">${sidDisplay}</div></div>
                            <div class="detail-row"><div class="detail-label"><span data-en="Department:" data-am="ትምህርት ክፍል:">Department:</span></div><div class="detail-value">${data.dept_name || 'N/A'}</div></div>
                            <div class="detail-row"><div class="detail-label"><span data-en="Batch (Year):" data-am="ባች (ዓመት):">Batch (Year):</span></div><div class="detail-value">${data.batch || 'N/A'}</div></div>
                            <div class="detail-row"><div class="detail-label"><span data-en="Semester:" data-am="ሴሚስተር:">Semester:</span></div><div class="detail-value">${data.semester}</div></div>
                            <div class="detail-row"><div class="detail-label"><span data-en="Cost Share Amount:" data-am="የወጪ መጋራት መጠን:">Cost Share Amount:</span></div><div class="detail-value"><strong>${Number(data.cost_share_amount).toLocaleString('en-US', {minimumFractionDigits: 2})} ETB</strong></div></div>
                            <div class="detail-row"><div class="detail-label"><span data-en="Description:" data-am="መግለጫ:">Description:</span></div><div class="detail-value">${data.description || 'N/A'}</div></div>
                            <div class="detail-row"><div class="detail-label"><span data-en="Date Requested:" data-am="የተጠየቀበት ቀን:">Date Requested:</span></div><div class="detail-value">${data.created_at}</div></div>
                        `;

                        body.innerHTML = html;

                        // Set Accept & Record Action
                        acceptBtn.onclick = function () {
                            // Determine the student ID to use
                            var sid = data.student_id_str;
                            if (!sid || sid === 'null' || sid === '') {
                                var input = document.getElementById('modalStudentIdInput');
                                if (input) {
                                    sid = input.value.trim();
                                }
                            }
                            if (!sid) {
                                showAlert(
                                    '<i class="fas fa-exclamation-circle"></i>',
                                    'error',
                                    'Student ID Required',
                                    '<span data-en="Please enter a Student ID before accepting." data-am="እባክዎ ከመቀበልዎ በፊት የተማሪ መታወቂያ ያስገቡ።">Please enter a Student ID before accepting.</span>'
                                );
                                return;
                            }

                            // Show custom confirmation
                            var studentName = (data.first_name || '') + ' ' + (data.middle_name || '') + ' ' + (data.last_name || '');
                            var amount = Number(data.cost_share_amount).toLocaleString('en-US', {minimumFractionDigits: 2});
                            showConfirm(
                                '<span data-en="Confirm Accept & Record" data-am="ማረጋገጫ ተቀበል እና መዝግብ">Confirm Accept & Record</span>',
                                '<span data-en="Are you sure you want to accept and record this transfer?" data-am="ይህን ዝውውር ተቀብለው ለመመዝገብ እርግጠኛ ነዎት?">Are you sure you want to accept and record this transfer?</span>' +
                                '<div class="student-info">' +
                                '<div><strong data-en="Student:" data-am="ተማሪ:">Student:</strong> ' + studentName.trim() + '</div>' +
                                '<div><strong data-en="ID:" data-am="መታወቂያ:">ID:</strong> ' + sid + '</div>' +
                                '<div><strong data-en="Amount:" data-am="መጠን:">Amount:</strong> ' + amount + ' ETB</div>' +
                                '</div>',
                                function () {
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