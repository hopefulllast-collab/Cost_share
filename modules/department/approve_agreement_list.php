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
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['signature']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $new_name = "sig_dept_" . $dept_id . "_" . time() . "." . $ext;
            $upload_dir = "../../uploads/signatures/";
            if (!file_exists($upload_dir))
                mkdir($upload_dir, 0777, true);

            if (move_uploaded_file($_FILES['signature']['tmp_name'], $upload_dir . $new_name)) {
                // Bulk Update
                $sql = "UPDATE cost_sharing_agreements csa
                        JOIN students s ON csa.student_id = s.user_id
                        SET csa.status = 'VerifiedByDept', csa.signature_dept_head = ?
                        WHERE s.department_id = ? AND csa.academic_year = ? AND csa.semester = ? AND csa.status = 'SignedByStudent'";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$new_name, $dept_id, $year, $sem]);

                $count = $stmt->rowCount();
                $_SESSION["flash_success"] = "<span data-en='Successfully approved' data-am='በተሳካ ሁኔታ ጸድቋል'>Successfully approved</span> $count <span data-en='agreements for Batch' data-am='ስምምነቶች ለባች'>agreements for Batch</span> $year, <span data-en='Semester' data-am='ሴሚስተር'>Semester</span> $sem.";
                header("Location: " . $_SERVER["PHP_SELF"] . "?year=" . urlencode($year) . "&sem=" . urlencode($sem));
                exit();
            } else {
                $error = "<span data-en='Failed to upload signature.' data-am='ፊርማ መስቀል አልተቻለም።'>Failed to upload signature.</span>";
            }
        } else {
            $error = "<span data-en='Invalid file type. Only JPG, JPEG, PNG allowed.' data-am='የማይሰራ የፋይል ዓይነት። JPG፣ JPEG፣ PNG ብቻ ይፈቀዳሉ።'>Invalid file type. Only JPG, JPEG, PNG allowed.</span>";
        }
    } else {
        $error = "<span data-en='Department Head Signature is required.' data-am='የዲፓርትመንት ኃላፊ ፊርማ ያስፈልጋል።'>Department Head Signature is required.</span>";
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
                        <p data-en="Upload your signature to approve and forward ALL listed agreements to the Cost Sharing Professional."
                            data-am="ፊርማዎን ይስቀሉ ሁሉንም ስምምነቶች ለወጪ ክፍፍል ባለሙያ ለማፅደቅና ለማስተላለፍ።">Upload your signature to approve
                            and forward ALL listed agreements to the Cost Sharing
                            Professional.</p>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="bulk_approve" value="1">
                            <div class="form-group">
                                <label data-en="Department Head Signature (Image):" data-am="የክፍል ሃላፊ ፊርማ (ምስል):">Department
                                    Head Signature (Image):</label>
                                <input type="file" name="signature" required accept=".jpg,.jpeg,.png">
                            </div>
                            <button type="button" id="bulkApproveBtn" class="btn-success"
                                onclick="var sigFile = document.querySelector('input[name=signature]'); if(!sigFile.value){document.getElementById('sigError').style.display='block'; return;} document.getElementById('sigError').style.display='none'; document.getElementById('bulkApproveConfirm').style.display='block'; this.style.display='none';"
                                style="color:white; background:black; padding:10px 20px; font-size:1.1em;"
                                data-en="Sign & Approve All" data-am="ፈርም እና ሁሉንም አፅድቅ">
                                Sign & Approve All
                            </button>
                            <div id="sigError"
                                style="display:none; margin-top:10px; padding:10px 15px; background:#f8d7da; border:1px solid #f5c6cb; border-radius:5px; color:#721c24;">
                                <i class="fas fa-exclamation-circle"></i> <span
                                    data-en="Please select a signature image file before proceeding."
                                    data-am="እባክዎ ከመቀጠልዎ በፊት የፊርማ ምስል ፋይል ይምረጡ።">Please select a signature image file before
                                    proceeding.</span>
                            </div>
                            <div id="bulkApproveConfirm"
                                style="display:none; margin-top:10px; padding:15px; background:#fff3cd; border:1px solid #ffc107; border-radius:5px;">
                                <p style="margin:0 0 10px; font-weight:bold; color:#856404;"
                                    data-en="Approve all <?php echo count($students); ?> agreements?"
                                    data-am="ሁሉንም <?php echo count($students); ?> ስምምነቶች ያፀድቃሉ?">Approve all
                                    <?php echo count($students); ?> agreements?
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