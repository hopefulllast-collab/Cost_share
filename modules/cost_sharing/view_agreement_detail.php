<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['cost_sharing_pro']);
require_once '../../includes/academic_translations.php';

$agreement_id = $_GET['id'] ?? null;
if (!$agreement_id) {
    header("Location: approve_cost_share.php");
    exit();
}

// Fetch Agreement Details with Student Info
$sql = "SELECT csa.*, s.student_id, s.first_name, s.middle_name, s.last_name, 
               d.name as dept_name, s.batch, s.current_semester, s.program_type
        FROM cost_sharing_agreements csa
        JOIN students s ON csa.student_id = s.user_id
        JOIN departments d ON s.department_id = d.id
        WHERE csa.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$agreement_id]);
$agreement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agreement) {
    die("Agreement not found.");
}

// Data is now in separate columns - no need to decode content_json
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Agreement Details" data-am="የስምምነት ዝርዝሮች">Agreement Details</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .detail-item {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
        }

        .label {
            font-weight: bold;
            color: #555;
            display: block;
            margin-bottom: 5px;
        }

        .value {
            font-size: 1.1em;
        }

        .agreement-text {
            border: 1px solid #ddd;
            padding: 20px;
            background: #fff;
            margin: 20px 0;
            max-height: 300px;
            overflow-y: auto;
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
                    <h2 data-en="Review Agreement" data-am="ስምምነትን ይገምግሙ">Review Agreement</h2>
                    <a href="approve_cost_share.php" class="btn-secondary" data-en="Back to List"
                        data-am="ተመለስ">Back</a>
                </div>

                <div class="card">
                    <h3 data-en="Student Information" data-am="የተማሪ መረጃ">Student Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="label" data-en="Full Name" data-am="ሙሉ ስም">Full Name</span>
                            <span class="value">
                                <?php echo htmlspecialchars($agreement['first_name'] . ' ' . $agreement['middle_name'] . ' ' . $agreement['last_name']); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="label" data-en="Student ID" data-am="የተማሪ መለያ">Student ID</span>
                            <span class="value">
                                <?php echo htmlspecialchars($agreement['student_id']); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="label" data-en="Department" data-am="ትምህርት ክፍል">Department</span>
                            <span class="value">
                                <?php $dept_am = $academic_translations[$agreement['dept_name']] ?? $agreement['dept_name']; ?>
                                <span data-en="<?php echo htmlspecialchars($agreement['dept_name']); ?>"
                                    data-am="<?php echo htmlspecialchars($dept_am); ?>"><?php echo htmlspecialchars($agreement['dept_name']); ?></span>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="label" data-en="Batch / Semester" data-am="ባች / ሴሚስተር">Batch / Semester</span>
                            <span class="value">
                                <?php echo $agreement['batch'] . ' / ' . $agreement['current_semester']; ?>
                            </span>
                        </div>
                    </div>

                    <h3 data-en="Agreement Status" data-am="የስምምነት ሁኔታ">Agreement Status</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="label" data-en="Current Status" data-am="ወቅታዊ ሁኔታ">Current Status</span>
                            <span class="value badge-<?php echo strtolower($agreement['status']); ?>">
                                <?php
                                $status_map = [
                                    'verifiedbydept' => 'በዲፓርትመንት የተረጋገጠ',
                                    'approvedbycostpro' => 'በወጪ መጋራት የተረጋገጠ',
                                    'pending' => 'በመጠባበቅ ላይ'
                                ];
                                $status_key = strtolower($agreement['status']);
                                $status_am = $status_map[$status_key] ?? $agreement['status'];
                                ?>
                                <span data-en="<?php echo $agreement['status']; ?>" data-am="<?php echo $status_am; ?>">
                                    <?php echo $agreement['status']; ?>
                                </span>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="label" data-en="Signed Date" data-am="የተፈረመበት ቀን">Signed Date</span>
                            <span class="value">
                                <?php echo $agreement['agreement_date']; ?>
                            </span>
                        </div>
                    </div>

                    <div class="actions" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                        <?php if ($agreement['status'] == 'VerifiedByDept'): ?>
                            <form action="../../api/verify_agreement_pro.php" method="POST">
                                <input type="hidden" name="agreement_id" value="<?php echo $agreement['id']; ?>">
                                <button type="button" id="verifyAgrBtn" class="btn-primary"
                                    onclick="document.getElementById('verifyConfirm').style.display='block'; this.style.display='none';">
                                    <i class="fas fa-check-double"></i>
                                    <span data-en="Verify & Approve" data-am="ያረጋግጡ እና ያጽድቁ">Verify & Approve</span>
                                </button>
                                <div id="verifyConfirm"
                                    style="display:none; margin-top:10px; padding:15px; background:#fff3cd; border:1px solid #ffc107; border-radius:5px;">
                                    <p style="margin:0 0 10px; font-weight:bold; color:#856404;"
                                        data-en="Are you sure you want to verify this agreement?"
                                        data-am="ይህን ስምምነት ማረጋገጥ ይፈልጋሉ?">Are you sure you want to verify this agreement?</p>
                                    <button type="submit" name="verify" class="btn-primary" style="margin-right:10px;"
                                        data-en="Yes, Verify" data-am="አዎ፣ ያረጋግጡ">Yes, Verify</button>
                                    <button type="button" class="btn-secondary"
                                        onclick="document.getElementById('verifyConfirm').style.display='none'; document.getElementById('verifyAgrBtn').style.display='inline-block';"
                                        data-en="Cancel" data-am="ሰርዝ">Cancel</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert-info" data-en="This agreement has already been processed."
                                data-am="ይህ ስምምነት ቀድሞውኑ ተካሂዷል።">
                                This agreement has already been processed.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>