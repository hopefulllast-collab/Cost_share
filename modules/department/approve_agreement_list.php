<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['department_head']);

$dept_head_id = $_SESSION['user_id'];
// Get Dept ID
$stmt = $pdo->prepare("SELECT id as department_id FROM departments WHERE head_user_id = ?");
$stmt->execute([$dept_head_id]);
$dept_id = $stmt->fetchColumn();

$year = $_GET['year'] ?? '';
$sem = $_GET['sem'] ?? '';

if (!$year || !$sem) {
    die("Invalid Request");
}

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

// Handle Bulk Approval
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_approve'])) {
    $stmt = $pdo->prepare("SELECT digital_signature FROM users WHERE id = ?");
    $stmt->execute([$dept_head_id]);
    $signature = $stmt->fetchColumn();

    if (empty($signature)) {
        $error = "<span data-en='You have not set up your digital signature. Please update your profile.' data-am='የዲጂታል ፊርማዎን አላዘጋጁም። እባክዎ ፕሮፋይልዎን ያዘምኑ።'>You have not set up your digital signature. Please <a href=\"../common/update_profile.php\">update your profile</a>.</span>";
    } else {
        $sql = "UPDATE cost_sharing_agreements csa
                JOIN students s ON csa.student_id = s.user_id
                SET csa.status = 'VerifiedByDept', csa.signature_dept_head = ?
                WHERE s.department_id = ? AND csa.academic_year = ? AND csa.semester = ? AND csa.status = 'SignedByStudent'";

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$signature, $dept_id, $year, $sem])) {
            $count = $stmt->rowCount();
            $_SESSION["flash_success"] = "<span data-en='Successfully approved' data-am='በተሳካ ሁኔታ ጸድቋል'>Successfully approved</span> $count <span data-en='agreements for Batch' data-am='ስምምነቶች ለባች'>agreements for Batch</span> $year, <span data-en='Semester' data-am='ሴሚስተር'>Semester</span> $sem.";
            header("Location: " . $_SERVER["PHP_SELF"] . "?year=" . urlencode($year) . "&sem=" . urlencode($sem));
            exit();
        } else {
            $error = "<span data-en='Failed to approve agreements.' data-am='ስምምነቶችን ማፅደቅ አልተቻለም።'>Failed to approve agreements.</span>";
        }
    }
}

// Fetch Students in this Batch/Sem
$stmt = $pdo->prepare("SELECT csa.*, s.student_id as student_code, u.first_name, u.last_name 
                       FROM cost_sharing_agreements csa
                       JOIN students s ON csa.student_id = s.user_id
                       JOIN users u ON s.user_id = u.id
                       WHERE s.department_id = ? AND csa.academic_year = ? AND csa.semester = ? AND csa.status = 'SignedByStudent'");
$stmt->execute([$dept_id, $year, $sem]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Approve Batch Agreements - Cost Sharing Pro" data-am="ባች ስምምነቶችን አጽድቅ - የወጪ መጋራት ባለሙያ">Approve Batch
        Agreements - Cost Sharing Pro</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .details-panel {
            background: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            margin-top: 20px;
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
                    <a href="approve_agreement.php" class="btn-secondary" style="margin-right:20px;" data-en="← Back"
                        data-am="← ተመለስ">&larr; Back</a>
                    <h2 data-en="Approve Agreements: Batch <?php echo htmlspecialchars($year); ?> - Sem <?php echo htmlspecialchars($sem); ?>"
                        data-am="ስምምነቶችን ያፀድቁ: ባች <?php echo htmlspecialchars($year); ?> - ሴሚስተር <?php echo htmlspecialchars($sem); ?>">
                        Approve Agreements: Batch
                        <?php echo htmlspecialchars($year); ?> - Sem
                        <?php echo htmlspecialchars($sem); ?>
                    </h2>
                </div>

                <?php if ($msg)
                    echo "<div class='success-banner'>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>

                <?php if (empty($students) && !$msg): ?>
                    <p data-en="No pending agreements for this batch." data-am="ለዚህ ባች ያልተጠናቀቁ ስምምነቶች የሉም።">No pending
                        agreements for this batch.</p>
                <?php elseif (!empty($students)): ?>
                    <div class="card">
                        <h3 data-en="Student List (<?php echo count($students); ?>)"
                            data-am="የተማሪ ዝርዝር (<?php echo count($students); ?>)">Student List (
                            <?php echo count($students); ?>)
                        </h3>
                        <table class="table-list">
                            <thead>
                                <tr>
                                    <th data-en="ID" data-am="መለያ">ID</th>
                                    <th data-en="Name" data-am="ስም">Name</th>
                                    <th data-en="Date Signed" data-am="የተፈረመበት ቀን">Date Signed</th>
                                    <th data-en="Action" data-am="ድርጊት">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $st): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($st['student_code']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($st['agreement_date']); ?>
                                        </td>
                                        <td>
                                            <a href="view_agreement_detail.php?id=<?php echo $st['id']; ?>"
                                                class="btn-sm" data-en="View Details" data-am="ዝርዝር ይመልከቱ">View Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="details-panel card">
                        <h3 data-en="Bulk Approval" data-am="የጅምላ ማፅደቅ">Bulk Approval</h3>
                        <p data-en="Approve and forward ALL listed agreements to the Cost Sharing Professional."
                            data-am="ሁሉንም ስምምነቶች ለወጪ ክፍፍል ባለሙያ ማፅደቅና ማስተላለፍ።">Approve and forward ALL listed agreements to the Cost Sharing
                            Professional.</p>
                        <form method="POST">
                            <input type="hidden" name="bulk_approve" value="1">
                            
                            <button type="button" id="bulkApproveBtn" class="btn-success"
                                onclick="document.getElementById('bulkApproveConfirm').style.display='block'; this.style.display='none';"
                                style="color:white; background:black; padding:10px 20px; font-size:1.1em;"
                                data-en="Sign & Approve All" data-am="ፈርም እና ሁሉንም አፅድቅ">
                                Sign & Approve All
                            </button>
                            
                            <div id="bulkApproveConfirm"
                                style="display:none; margin-top:10px; padding:15px; background:#fff3cd; border:1px solid #ffc107; border-radius:5px;">
                                <p style="margin:0 0 10px; font-weight:bold; color:#856404;"
                                    data-en="Approve all <?php echo count($students); ?> agreements using your saved signature?"
                                    data-am="በተቀመጠው ፊርማዎ ሁሉንም <?php echo count($students); ?> ስምምነቶች ያፀድቃሉ?">Approve all
                                    <?php echo count($students); ?> agreements using your saved signature?
                                </p>
                                <button type="submit" class="btn-success"
                                    style="color:white; background:black; padding:8px 16px; margin-right:10px;"
                                    data-en="Yes, Approve All" data-am="አዎ፣ ሁሉንም አፅድቅ">Yes, Approve
                                    All</button>
                                <button type="button" class="btn-secondary" style="padding:8px 16px;"
                                    onclick="document.getElementById('bulkApproveConfirm').style.display='none'; document.getElementById('bulkApproveBtn').style.display='inline-block';"
                                    data-en="Cancel" data-am="ሰርዝ">Cancel</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>