<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['department_head']);

$agreement_id = $_GET['id'] ?? null;
if (!$agreement_id) {
    header("Location: approve_agreement.php");
    exit();
}



// Fetch Agreement Details
$sql = "SELECT csa.*, s.student_id, s.first_name, s.middle_name, s.last_name, 
               d.name as dept_name, s.batch, s.current_semester, s.program_type
        FROM cost_sharing_agreements csa
        JOIN students s ON csa.student_id = s.user_id
        JOIN users u ON s.user_id = u.id
        JOIN departments d ON s.department_id = d.id
        WHERE csa.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$agreement_id]);
$agreement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agreement) {
    die("Agreement not found.");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Agreement Details" data-am="የስምምነት ዝርዝሮች">Agreement Details</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
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
                    <a href="javascript:history.back()" class="btn-secondary" data-en="Back to List"
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
                                <?php echo htmlspecialchars($agreement['dept_name']); ?>
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
                                <?php echo $agreement['status']; ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="label" data-en="Signed Date" data-am="የተፈረመበት ቀን">Signed Date</span>
                            <span class="value">
                                <?php echo $agreement['agreement_date']; ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="label" data-en="Student Signature" data-am="የተማሪ ፊርማ">Student Signature</span>
                            <span class="value">
                                <?php echo htmlspecialchars($agreement['signature_student']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="actions" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                        <div
                            style="padding: 12px 16px; background: #e8f4fd; border: 1px solid #b8daff; border-radius: 5px; color: #004085;">
                            <i class="fas fa-info-circle"></i>
                            <span data-en="Current Status:" data-am="ወቅታዊ ሁኔታ:">Current Status:</span>
                            <strong><?php echo htmlspecialchars($agreement['status']); ?></strong>
                            <?php if ($agreement['status'] == 'SignedByStudent'): ?>
                                <br><small data-en="To approve, go back and use the bulk approval with your digital signature."
                                    data-am="ለማጽደቅ ተመልሰው ዲጂታል ፊርማዎን በመጠቀም በጅምላ ያጽድቁ።">To approve, go back and use the bulk approval
                                    with your digital signature.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>