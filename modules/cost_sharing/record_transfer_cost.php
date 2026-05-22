<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['cost_sharing_pro']);

$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

// Handle Record Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_cost'])) {
    $order_id = $_POST['order_id'];
    $student_id_str = trim($_POST['student_id_str']);

    // financial data
    $tuition_fee = floatval($_POST['tuition_fee'] ?? 0);
    $food_expense = floatval($_POST['food_expense'] ?? 0);
    $bed_expense = floatval($_POST['bed_expense'] ?? 0);
    $medication_expense = floatval($_POST['medication_expense'] ?? 0);
    $total_amount_semester = $tuition_fee + $food_expense + $bed_expense + $medication_expense;

    if (empty($student_id_str)) {
        $error = "<span data-en='Student ID is required.' data-am='የተማሪ መታወቂያ ያስፈልጋል።'>Student ID is required.</span>";
    } else {
        try {
            $pdo->beginTransaction();

            // Find the student user_id
            $stmt = $pdo->prepare("SELECT s.user_id, s.student_id FROM students s WHERE s.student_id = ? LIMIT 1");
            $stmt->execute([$student_id_str]);
            $found_student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$found_student || !$found_student['user_id']) {
                $error = "<span data-en='Student not found in system.' data-am='ተማሪው በስርዓቱ አልተገኘም።'>Student not found in system.</span>";
                $pdo->rollBack();
            } else {
                $student_user_id = $found_student['user_id'];

                $order_stmt = $pdo->prepare("SELECT * FROM official_transcript WHERE id = ?");
                $order_stmt->execute([$order_id]);
                $order = $order_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$order) {
                    $error = "<span data-en='Transfer order not found.' data-am='የዝውውር ትእዛዝ አልተገኘም።'>Transfer order not found.</span>";
                    $pdo->rollBack();
                } else {
                    $academic_year = $order['academic_year'] ?? '';
                    $semester = $order['semester'] ?? 1;

                    // 1. Insert into cost_sharing_agreements
                    $sql = "INSERT INTO cost_sharing_agreements 
                            (student_id, academic_year, semester, tuition_fee, food_expense, bed_expense, medication_expense, total_amount_semester, recorded_by, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ApprovedByCostPro')";
                    $ins = $pdo->prepare($sql);
                    $ins->execute([
                        $student_user_id,
                        $academic_year,
                        $semester,
                        $tuition_fee,
                        $food_expense,
                        $bed_expense,
                        $medication_expense,
                        $total_amount_semester,
                        $_SESSION['user_id']
                    ]);

                    // 2. Recalculate cumulative total_amount
                    $pdo->prepare("UPDATE cost_sharing_agreements SET total_amount = (SELECT t.total FROM (SELECT COALESCE(SUM(tuition_fee + food_expense + bed_expense + medication_expense), 0) as total FROM cost_sharing_agreements WHERE student_id = ?) as t) WHERE student_id = ?")->execute([$student_user_id, $student_user_id]);

                    // 3. Mark the order as Completed
                    $upd = $pdo->prepare("UPDATE official_transcript SET status = 'Completed' WHERE id = ?");
                    $upd->execute([$order_id]);

                    $pdo->commit();

                    $_SESSION["flash_success"] = "<span data-en='Successfully recorded cost share for student " . htmlspecialchars($student_id_str) . ".' data-am='ለተማሪ " . htmlspecialchars($student_id_str) . " ኮስት ሼሪንግ በተሳካ ሁኔታ ተመዝግቧል።'>Successfully recorded cost share for student " . htmlspecialchars($student_id_str) . ".</span>";
                    header("Location: " . $_SERVER["PHP_SELF"]);
                    exit();
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "<span data-en='Database Error: ' data-am='የውሂብ ጎታ ስህተት: '>Database Error: </span>" . $e->getMessage();
        }
    }
}

// Fetch Pending Transfer Orders accepted by Transcript Pro
$transfer_orders = $pdo->query("SELECT t.*, t.student_id_str, d.name as dept_name 
                                FROM official_transcript t 
                                LEFT JOIN departments d ON t.department_id = d.id 
                                WHERE t.recipient_role = 'transcript_pro' 
                                AND t.request_type = 'transfer' 
                                AND t.status = 'Accepted'
                                ORDER BY t.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Record Cost Share - Cost Sharing Pro" data-am="የወጪ መጋራትን ይመዝግቡ - ኮስት ሼሪንግ ባለሙያ">Record Cost Share
    </title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
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

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .total-input {
            background-color: #e9ecef;
            cursor: not-allowed;
            font-weight: bold;
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
                    <h2 data-en="Record Cost Share (Transfer-In)" data-am="የወጪ መጋራት መዝግብ (ዝውውር)">Record Cost Share
                        (Transfer-In)</h2>
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

                <?php if (!empty($transfer_orders)): ?>
                    <div class="card mt-20">
                        <h3 data-en="Pending Transfer Agreements" data-am="በመጠባበቅ ላይ ያሉ የዝውውር ስምምነቶች">Pending Transfer
                            Agreements</h3>
                        <table class="table" style="width:100%; border-collapse:collapse; margin-top:10px;">
                            <thead>
                                <tr style="background:#f9f9f9; text-align:left;">
                                    <th style="padding:10px; border:1px solid #ddd;">Student ID</th>
                                    <th style="padding:10px; border:1px solid #ddd;">Name</th>
                                    <th style="padding:10px; border:1px solid #ddd;">Dept</th>
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
                                            <button type="button" class="btn-primary"
                                                onclick="openModal(<?php echo htmlspecialchars(json_encode($order)); ?>)"
                                                style="padding:5px 10px; font-size:12px;">
                                                <i class="fas fa-edit"></i> <span data-en="Record Cost"
                                                    data-am="ወጪ ይመዝግቡ">Record Cost</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="card mt-20">
                        <p data-en="No pending transfer cost sharing requests."
                            data-am="በመጠባበቅ ላይ ያሉ የዝውውር ወጪ መጋራት ጥያቄዎች የሉም።">No pending transfer cost sharing requests.</p>
                    </div>
                <?php endif; ?>

                <!-- Modal -->
                <div id="costModal" class="modal">
                    <div class="modal-content">
                        <span class="close" onclick="closeModal()">&times;</span>
                        <h3 style="margin-top:0; border-bottom:2px solid var(--primary-color); padding-bottom:10px;"
                            data-en="Fill Cost Sharing Information" data-am="የወጪ መጋራት መረጃ ይሙሉ">Fill Cost Sharing
                            Information</h3>

                        <form id="recordForm" method="POST">
                            <input type="hidden" name="record_cost" value="1">
                            <input type="hidden" name="order_id" id="modalOrderId">
                            <input type="hidden" name="student_id_str" id="modalStudentId">

                            <div style="background:#f9f9f9; padding:15px; border-radius:5px; margin-bottom:20px;">
                                <p style="margin:5px 0;"><strong data-en="Name:" data-am="ስም:">Name:</strong> <span
                                        id="dispName"></span></p>
                                <p style="margin:5px 0;"><strong data-en="Student ID:" data-am="መታወቂያ:">Student
                                        ID:</strong> <span id="dispId"></span></p>
                            </div>

                            <div class="form-group">
                                <label data-en="Tuition Fee" data-am="የትምህርት ክፍያ">Tuition Fee</label>
                                <input type="number" name="tuition_fee" id="tuitionFee" step="0.01" min="0" value="0"
                                    required oninput="calculateTotal()">
                            </div>

                            <div class="form-group">
                                <label data-en="Food Expense" data-am="የምግብ ወጪ">Food Expense</label>
                                <input type="number" name="food_expense" id="foodExpense" step="0.01" min="0" value="0"
                                    required oninput="calculateTotal()">
                            </div>

                            <div class="form-group">
                                <label data-en="Bed Expense" data-am="የአልጋ ወጪ">Bed Expense</label>
                                <input type="number" name="bed_expense" id="bedExpense" step="0.01" min="0" value="0"
                                    required oninput="calculateTotal()">
                            </div>

                            <div class="form-group">
                                <label data-en="Medication Expense" data-am="የህክምና ወጪ">Medication Expense</label>
                                <input type="number" name="medication_expense" id="medExpense" step="0.01" min="0"
                                    value="0" required oninput="calculateTotal()">
                            </div>

                            <div class="form-group">
                                <label data-en="Total Amount (Calculated)" data-am="ጠቅላላ መጠን (የተሰላ)">Total Amount
                                    (Calculated)</label>
                                <input type="number" id="totalAmount" step="0.01" class="total-input" readonly
                                    value="0">
                            </div>

                            <div style="margin-top: 20px; text-align: right;">
                                <button type="button" class="btn-secondary" onclick="closeModal()" data-en="Cancel"
                                    data-am="ሰርዝ">Cancel</button>
                                <button type="submit" class="btn-success"><i class="fas fa-save"></i> <span
                                        data-en="Record Information" data-am="መረጃ ይመዝግቡ">Record
                                        Information</span></button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    function openModal(data) {
                        document.getElementById('modalOrderId').value = data.id;
                        document.getElementById('modalStudentId').value = data.student_id_str;

                        document.getElementById('dispName').textContent = data.first_name + ' ' + (data.middle_name || '') + ' ' + data.last_name;
                        document.getElementById('dispId').textContent = data.student_id_str;

                        document.getElementById('tuitionFee').value = "0";
                        document.getElementById('foodExpense').value = "0";
                        document.getElementById('bedExpense').value = "0";
                        document.getElementById('medExpense').value = "0";
                        calculateTotal();

                        document.getElementById('costModal').style.display = 'block';
                        if (typeof updateLanguage === 'function') updateLanguage();
                    }

                    function closeModal() {
                        document.getElementById('costModal').style.display = 'none';
                    }

                    function calculateTotal() {
                        let f1 = parseFloat(document.getElementById('tuitionFee').value) || 0;
                        let f2 = parseFloat(document.getElementById('foodExpense').value) || 0;
                        let f3 = parseFloat(document.getElementById('bedExpense').value) || 0;
                        let f4 = parseFloat(document.getElementById('medExpense').value) || 0;
                        let tot = f1 + f2 + f3 + f4;
                        document.getElementById('totalAmount').value = tot.toFixed(2);
                    }

                    window.onclick = function (event) {
                        if (event.target == document.getElementById('costModal')) {
                            closeModal();
                        }
                    }
                </script>

            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>