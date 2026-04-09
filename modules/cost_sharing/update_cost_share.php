<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['cost_sharing_pro']);

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

// Handle Suspend a Semester Cost
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['drop_semester'])) {
    $txn_id = $_POST['txn_id'];
    $order_id = $_POST['order_id'] ?? null;
    $order_id = $_POST['order_id'];
    try {
        $info = $pdo->prepare("SELECT student_id FROM cost_sharing_agreements WHERE id = ?");
        $info->execute([$txn_id]);
        $txn = $info->fetch(PDO::FETCH_ASSOC);

        if ($txn) {
            // Suspend the agreement instead of deleting
            $pdo->prepare("UPDATE cost_sharing_agreements SET status = 'Suspended' WHERE id = ?")->execute([$txn_id]);
            // Recalculate cumulative total_amount excluding suspended
            $sid = $txn['student_id'];
            $pdo->prepare("UPDATE cost_sharing_agreements SET total_amount = (SELECT t.total FROM (SELECT COALESCE(SUM(tuition_fee + food_expense + bed_expense + medication_expense), 0) as total FROM cost_sharing_agreements WHERE student_id = ? AND status != 'Suspended') as t) WHERE student_id = ?")->execute([$sid, $sid]);
        }

        // Mark the order as completed
        $pdo->prepare("UPDATE official_transcript SET status = 'Completed' WHERE id = ?")->execute([$order_id]);

        $_SESSION["flash_success"] = "<span data-en='Semester suspended successfully.' data-am='ሴሚስተር በተሳካ ሁኔታ ታግዷል።'>Semester suspended successfully.</span>";
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    } catch (PDOException $e) {
        $error = "<span data-en='Suspend failed: " . htmlspecialchars($e->getMessage()) . "' data-am='ማገድ አልተሳካም: " . htmlspecialchars($e->getMessage()) . "'>Suspend failed: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
}

// Handle Unsuspend a Semester Cost
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['unsuspend_semester'])) {
    $txn_id = $_POST['txn_id'];
    try {
        $info = $pdo->prepare("SELECT student_id FROM cost_sharing_agreements WHERE id = ?");
        $info->execute([$txn_id]);
        $txn = $info->fetch(PDO::FETCH_ASSOC);

        if ($txn) {
            // Revert to ApprovedByCostPro
            $pdo->prepare("UPDATE cost_sharing_agreements SET status = 'ApprovedByCostPro' WHERE id = ?")->execute([$txn_id]);
            // Recalculate cumulative total_amount
            $sid = $txn['student_id'];
            $pdo->prepare("UPDATE cost_sharing_agreements SET total_amount = (SELECT t.total FROM (SELECT COALESCE(SUM(tuition_fee + food_expense + bed_expense + medication_expense), 0) as total FROM cost_sharing_agreements WHERE student_id = ? AND status != 'Suspended') as t) WHERE student_id = ?")->execute([$sid, $sid]);
        }

        
        $order_id = $_POST['order_id'] ?? null;
        if ($order_id) {
            $pdo->prepare("UPDATE official_transcript SET status = 'Completed' WHERE id = ?")->execute([$order_id]);
        }

        $_SESSION["flash_success"] = "<span data-en='Semester unsuspended successfully.' data-am='ሴሚስተር እገዳ ተነስቷል።'>Semester unsuspended successfully.</span>";
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    } catch (PDOException $e) {
        $error = "<span data-en='Unsuspend failed: " . htmlspecialchars($e->getMessage()) . "' data-am='እገዳ ማንሳት አልተሳካም: " . htmlspecialchars($e->getMessage()) . "'>Unsuspend failed: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
}

// Fetch ONLY orders sent by Registrar (from official_transcript table)
$orders = $pdo->query("SELECT o.*, d.name as dept_name 
                        FROM official_transcript o 
                        LEFT JOIN departments d ON o.department_id = d.id 
                        WHERE o.recipient_role = 'cost_sharing_pro' 
                        AND o.request_type IN ('withdrawal', 'dropout', 'ethics', 'death', 'active', 'complete dismissal', 'dismissal with readmission')
                        AND o.status = 'Pending'
                        ORDER BY o.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// For each order, try to find matching cost_transactions and student info
foreach ($orders as &$order) {
    // Find student in students table
    $stmt = $pdo->prepare("SELECT s.*, u.first_name as db_fname, u.middle_name as db_mname, u.last_name as db_lname 
                           FROM students s 
                           JOIN users u ON s.user_id = u.id 
                           WHERE s.student_id = ?");
    $stmt->execute([$order['student_id_str'] ?? $order['student_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    $order['student_db'] = $student;

    // Fetch ALL semester transactions for this student
    if ($student) {
        $txn_stmt = $pdo->prepare("SELECT * FROM cost_sharing_agreements WHERE student_id = ? ORDER BY academic_year ASC, semester ASC");
        $txn_stmt->execute([$student['user_id']]);
        $order['semesters'] = $txn_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total debit
        $total_stmt = $pdo->prepare("SELECT COALESCE(SUM(tuition_fee + food_expense + bed_expense + medication_expense), 0) as total FROM cost_sharing_agreements WHERE student_id = ?");
        $total_stmt->execute([$student['user_id']]);
        $order['total_debit'] = $total_stmt->fetch()['total'];
    } else {
        $order['semesters'] = [];
        $order['total_debit'] = 0;
    }
}
unset($order);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Update Cost Share - Cost Sharing Pro" data-am="ወጪ መጋራትን አዘምን - የወጪ መጋራት ባለሙያ">Update Cost Share -
        Cost Sharing Pro</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .warning-card {
            border-left: 5px solid #ffc107;
            background-color: #fff3cd;
        }

        .expand-btn {
            cursor: pointer;
            background: none;
            border: none;
            font-size: 18px;
            color: var(--primary-color, #007bff);
            transition: transform 0.3s;
        }

        .expand-btn.open {
            transform: rotate(90deg);
        }

        .semester-detail {
            display: none;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 10px;
        }

        .semester-detail.show {
            display: block;
        }

        .sem-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .sem-card.dropped {
            opacity: 0.6;
            border-left: 4px solid #e74c3c;
        }

        .sem-card.target-sem {
            border-left: 4px solid #ffc107;
            background: #fffde7;
        }

        .sem-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .sem-costs {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .cost-item {
            text-align: center;
            padding: 8px;
            background: #f0f0f0;
            border-radius: 5px;
        }

        .cost-item .label {
            font-size: 11px;
            color: #666;
            margin-bottom: 3px;
        }

        .cost-item .value {
            font-weight: bold;
            font-size: 14px;
        }

        .btn-danger-sm {
            background: #e74c3c;
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-danger-sm:hover {
            background: #c0392b;
        }

        .grand-total {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            text-align: right;
            font-size: 18px;
            margin-top: 10px;
        }

        .student-row {
            cursor: pointer;
        }

        .student-row:hover {
            background: #f0f4ff;
        }

        .no-orders {
            text-align: center;
            padding: 60px 20px;
        }

        .no-orders i {
            font-size: 60px;
            color: #ccc;
            margin-bottom: 15px;
        }

        .order-badge {
            display: inline-block;
            background: #e74c3c;
            color: #fff;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            text-transform: uppercase;
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
                    <h2 data-en="Update Cost Share" data-am="ወጪ መጋራትን ያዘምኑ">Update Cost Share</h2>
                </div>

                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>

                <?php if (count($orders) == 0): ?>
                    <!-- No Orders Message -->
                    <div class="card no-orders">
                        <i class="fas fa-inbox"></i>
                        <h3 data-en="No Pending Orders" data-am="ምንም በመጠበቅ ላይ ያሉ ትዕዛዞች የሉም">No Pending Orders</h3>
                        <p data-en="There are no orders from the Registrar to update. Orders will appear here when the Registrar sends them."
                            data-am="ከሬጅስትራር ለማዘመን ምንም ትዕዛዝ የለም። ሬጅስትራር ሲልክ እዚህ ይታያል።">
                            There are no orders from the Registrar to update. Orders will appear here when the Registrar
                            sends them.</p>
                    </div>
                <?php else: ?>
                    <!-- Orders from Registrar -->
                    <div class="card warning-card mb-20">
                        <h3><i class="fas fa-exclamation-triangle"></i>
                            <span data-en="Orders from Registrar" data-am="ከሬጅስትራር የተላኩ ትዕዛዞች">Orders from
                                Registrar</span>
                            <span
                                style="margin-left:10px; background:#e74c3c; color:#fff; padding:2px 10px; border-radius:10px; font-size:13px;">
                                <?php echo count($orders); ?>
                            </span>
                        </h3>
                        <p data-en="The Registrar has sent the following orders. Click a student to expand, review their cost breakdown, and drop the withdrawal semester cost."
                            data-am="ሬጅስትራር የሚከተሉትን ትዕዛዞች ልኳል። ተማሪውን ጠቅ ያድርጉ፣ የወጪ ዝርዝሩን ይመርምሩ፣ እና የማቋረጥ ሴሚስተር ወጪውን ይሰርዙ።">
                            The Registrar has sent the following orders. Click a student to expand and drop the
                            withdrawal semester cost.</p>
                    </div>

                    <?php foreach ($orders as $idx => $order): ?>
                        <div class="card mt-20">
                            <!-- Student Header (clickable to expand) -->
                            <div class="student-row" onclick="toggleSemesterDetail(<?php echo $idx; ?>)"
                                style="display:flex; justify-content:space-between; align-items:center;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <button class="expand-btn" id="expand-btn-<?php echo $idx; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                    <div>
                                        <h4 style="margin:0;">
                                            <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['middle_name'] . ' ' . $order['last_name']); ?>
                                            (<?php echo htmlspecialchars($order['student_id_str'] ?? $order['student_id']); ?>)
                                        </h4>
                                        <p style="margin:3px 0; color:#666;">
                                            <span data-en="Dept" data-am="ትምህርት ክፍል">Dept</span>:
                                            <?php echo htmlspecialchars($order['dept_name'] ?? 'N/A'); ?> |
                                            <span data-en="Year of Study" data-am="የጥናት ዓመት">Year of Study</span>:
                                            <?php echo $order['batch'] ?? 'N/A'; ?> |
                                            <span data-en="Semester" data-am="ሴሚስተር">Sem</span>:
                                            <?php echo $order['semester'] ?? 'N/A'; ?> |
                                            <span
                                                class="order-badge"><?php echo ucfirst($order['request_type'] ?? ''); ?></span>
                                        </p>
                                        <p style="margin:0; font-size:12px; color:#999;">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="grand-total" id="grand-total-<?php echo $idx; ?>">
                                    <span data-en="Total Debit" data-am="ጠቅላላ ዕዳ">Total Debit</span>:
                                    <?php echo number_format($order['total_debit'], 2); ?> Birr
                                </div>
                            </div>

                            <!-- Expandable Semester Breakdown -->
                            <div class="semester-detail" id="semester-detail-<?php echo $idx; ?>">
                                <?php if (empty($order['semesters'])): ?>
                                    <p style="color:#999; text-align:center; padding:20px;">
                                        <i class="fas fa-info-circle"></i>
                                        <span data-en="No cost transactions found for this student."
                                            data-am="ለዚህ ተማሪ ምንም የወጪ ግብይት አልተገኘም።">No cost transactions
                                            found.</span>
                                    </p>
                                <?php else: ?>
                                    <?php foreach ($order['semesters'] as $sem): ?>
                                        <?php
                                        $sem_total = (float) $sem['tuition_fee'] + (float) $sem['food_expense'] + (float) $sem['bed_expense'] + (float) $sem['medication_expense'];
                                        $is_suspended = ($sem['status'] === 'Suspended');
                                        // Drop button ONLY on the semester matching the ORDER's batch+semester
                                        $is_target = ($sem['academic_year'] == ($order['batch'] ?? '') && $sem['semester'] == ($order['semester'] ?? ''));
                                        if (!$is_target) continue;
                                        ?>
                                        <div
                                            class="sem-card <?php echo $is_suspended ? 'dropped' : ''; ?> <?php echo ($is_target && !$is_suspended) ? 'target-sem' : ''; ?>">
                                            <div class="sem-header">
                                                <div>
                                                    <strong>
                                                        <i class="fas fa-calendar-alt"></i>
                                                        <span data-en="Year" data-am="ዓመት">Year</span>
                                                        <?php echo htmlspecialchars($sem['academic_year']); ?>
                                                        - <span data-en="Semester" data-am="ሴሚስተር">Semester</span>
                                                        <?php echo $sem['semester']; ?>
                                                    </strong>
                                                    <?php if ($is_suspended): ?>
                                                        <span style="color:#e74c3c; margin-left:10px; font-size:12px; font-weight:bold;">
                                                            <i class="fas fa-pause-circle"></i>
                                                            <span data-en="SUSPENDED" data-am="ታግዷል">SUSPENDED</span>
                                                        </span>
                                                    <?php elseif ($is_target): ?>
                                                        <span style="color:#e67e22; margin-left:10px; font-size:12px; font-weight:bold;">
                                                            <i class="fas fa-exclamation-circle"></i>
                                                            <span data-en="Target Semester" data-am="የታለመ ሴሚስተር">Target
                                                                Semester</span>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="display:flex; align-items:center; gap:10px;">
                                                    <span style="font-size:16px; font-weight:bold;">
                                                        <?php echo number_format($sem_total, 2); ?> Birr
                                                    </span>
                                                    <?php if ($is_suspended): ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="txn_id" value="<?php echo $sem['id']; ?>">
                                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
<input type="hidden" name="unsuspend_semester" value="1">
                                                            <span id="unsuspend-init-<?php echo $sem['id']; ?>">
                                                                <button type="button" style="background:#28a745; color:#fff; border:none; padding:6px 14px; border-radius:4px; cursor:pointer; font-size:12px;" onclick="showUnsuspendConfirm(<?php echo $sem['id']; ?>)">
                                                                    <i class="fas fa-undo"></i>
                                                                    <span data-en="Unsuspend" data-am="እገዳ አንሳ">Unsuspend</span>
                                                                </button>
                                                            </span>
                                                            <span id="unsuspend-confirm-<?php echo $sem['id']; ?>" style="display:none; background:#d4edda; padding:6px 12px; border-radius:5px; border:1px solid #28a745;">
                                                                <strong style="color:#155724; font-size:12px;" data-en="Restore this semester?" data-am="ይህን ሴሚስተር መመለስ?">Restore?</strong>
                                                                <button type="submit" style="background:#28a745; color:#fff; border:none; padding:4px 12px; border-radius:3px; cursor:pointer; margin-left:8px; font-size:12px;">
                                                                    <span data-en="Yes" data-am="አዎ">Yes</span>
                                                                </button>
                                                                <button type="button" onclick="hideUnsuspendConfirm(<?php echo $sem['id']; ?>)" style="background:#6c757d; color:#fff; border:none; padding:4px 12px; border-radius:3px; cursor:pointer; margin-left:4px; font-size:12px;">
                                                                    <span data-en="No" data-am="አይ">No</span>
                                                                </button>
                                                            </span>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" style="display:inline;"
                                                            id="drop-form-<?php echo $sem['id']; ?>">
                                                            <input type="hidden" name="txn_id" value="<?php echo $sem['id']; ?>">
                                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                            <input type="hidden" name="drop_semester" value="1">
                                                            <span id="drop-init-<?php echo $sem['id']; ?>">
                                                                <button type="button" class="btn-danger-sm"
                                                                    onclick="showDropConfirm(<?php echo $sem['id']; ?>)">
                                                                    <i class="fas fa-pause-circle"></i>
                                                                    <span data-en="Suspend" data-am="አግድ">Suspend</span>
                                                                </button>
                                                            </span>
                                                            <span id="drop-confirm-<?php echo $sem['id']; ?>"
                                                                style="display:none; background:#fff3cd; padding:6px 12px; border-radius:5px; border:1px solid #ffc107;">
                                                                <strong style="color:#856404; font-size:12px;" data-en="Suspend this semester?" data-am="ይህን ሴሚስተር ማገድ?">Suspend?</strong>
                                                                <button type="submit"
                                                                    style="background:#e74c3c; color:#fff; border:none; padding:4px 12px; border-radius:3px; cursor:pointer; margin-left:8px; font-size:12px;">
                                                                    <span data-en="Yes" data-am="አዎ">Yes</span>
                                                                </button>
                                                                <button type="button" onclick="hideDropConfirm(<?php echo $sem['id']; ?>)"
                                                                    style="background:#6c757d; color:#fff; border:none; padding:4px 12px; border-radius:3px; cursor:pointer; margin-left:4px; font-size:12px;">
                                                                    <span data-en="No" data-am="አይ">No</span>
                                                                </button>
                                                            </span>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Cost Components -->
                                            <div class="sem-costs">
                                                <div class="cost-item">
                                                    <div class="label" data-en="Tuition Fee" data-am="ክፍያ">
                                                        Tuition Fee</div>
                                                    <div class="value">
                                                        <?php echo number_format($sem['tuition_fee'], 2); ?>
                                                    </div>
                                                </div>
                                                <div class="cost-item">
                                                    <div class="label" data-en="Food" data-am="ምግብ">Food
                                                    </div>
                                                    <div class="value">
                                                        <?php echo number_format($sem['food_expense'], 2); ?>
                                                    </div>
                                                </div>
                                                <div class="cost-item">
                                                    <div class="label" data-en="Bed" data-am="አልጋ">Bed
                                                    </div>
                                                    <div class="value">
                                                        <?php echo number_format($sem['bed_expense'], 2); ?>
                                                    </div>
                                                </div>
                                                <div class="cost-item">
                                                    <div class="label" data-en="Medication" data-am="መድሃኒት">
                                                        Medication</div>
                                                    <div class="value">
                                                        <?php echo number_format($sem['medication_expense'], 2); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>

    <script>
        function toggleSemesterDetail(idx) {
            const detail = document.getElementById('semester-detail-' + idx);
            const btn = document.getElementById('expand-btn-' + idx);
            detail.classList.toggle('show');
            btn.classList.toggle('open');
        }

        function showDropConfirm(id) {
            document.getElementById('drop-init-' + id).style.display = 'none';
            document.getElementById('drop-confirm-' + id).style.display = 'inline-block';
        }

        function hideDropConfirm(id) {
            document.getElementById('drop-confirm-' + id).style.display = 'none';
            document.getElementById('drop-init-' + id).style.display = 'inline';
        }

        function showUnsuspendConfirm(id) {
            document.getElementById('unsuspend-init-' + id).style.display = 'none';
            document.getElementById('unsuspend-confirm-' + id).style.display = 'inline-block';
        }

        function hideUnsuspendConfirm(id) {
            document.getElementById('unsuspend-confirm-' + id).style.display = 'none';
            document.getElementById('unsuspend-init-' + id).style.display = 'inline';
        }
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>