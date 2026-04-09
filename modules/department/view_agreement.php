<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['department_head']);

$user_id = $_SESSION['user_id'];
// Get Dept ID
$stmt = $pdo->prepare("SELECT id as department_id FROM departments WHERE head_user_id = ?");
$stmt->execute([$user_id]);
$dept_id = $stmt->fetchColumn();

// Handle Approval with Signature
if (isset($_POST['approve_agreement'])) {
    $agreement_id = $_POST['agreement_id'];

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
                $update = $pdo->prepare("UPDATE cost_sharing_agreements SET status = 'VerifiedByDept', signature_dept_head = ? WHERE id = ?");
                $update->execute([$new_name, $agreement_id]);
                $_SESSION["flash_success"] = "<span data-en='Successfully approved and forwarded to Cost Share Pro.' data-am='በተሳካ ሁኔታ ጸድቆ ወደ ኮስት ሼሪንግ ባለሙያ ተላልፏል።'>Successfully approved and forwarded to Cost Share Pro.</span>";
                header("Location: " . $_SERVER["PHP_SELF"]);
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

// Fetch Agreements for this Dept
// Only SignedByStudent
$sql = "SELECT c.id, s.first_name, s.last_name, s.student_id, c.academic_year, c.semester, c.agreement_date
FROM cost_sharing_agreements c
JOIN students s ON c.student_id = s.user_id
JOIN users u ON s.user_id = u.id
WHERE s.department_id = ? AND c.status = 'SignedByStudent'
ORDER BY c.agreement_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$dept_id]);
$agreements = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Approve Agreements - Dept Head" data-am="ስምምነቶችን አጽድቅ - የክፍል ኃላፊ">Approve Agreements - Dept Head
    </title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>
            <div class="main-content">
                <div class="top-bar">
                    <h2 data-en="Approve Cost Share Agreements" data-am="የወጪ መጋራት ስምምነቶችን አጽድቅ">Approve Cost Share
                        Agreements</h2>
                </div>

                <?php if (isset($msg))
                    echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if (isset($error))
                    echo "<div class='error-msg'>$error</div>"; ?>

                <div class="card">
                    <p style="margin-bottom:15px; color:#666;"
                        data-en="Review and approve student cost sharing agreements. Upload your signature to approve each agreement."
                        data-am="የተማሪዎች የወጪ መጋራት ስምምነቶችን ይገምግሙና ያጽድቁ። እያንዳንዱን ስምምነት ለማጽደቅ ፊርማዎን ይስቀሉ።">
                        Review and approve student cost sharing agreements. Upload your signature to approve each
                        agreement.
                    </p>
                    <table class="table-list">
                        <thead>
                            <tr>
                                <th data-en="Student ID" data-am="የተማሪ መለያ">Student ID</th>
                                <th data-en="Name" data-am="ስም">Name</th>
                                <th data-en="Year/Sem" data-am="ዓመት/ሴሚስተር">Year/Sem</th>
                                <th data-en="Date Signed" data-am="የተፈረመበት ቀን">Date Signed</th>
                                <th data-en="Action" data-am="ተግባር">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($agreements)): ?>
                                <tr>
                                    <td colspan="5" data-en="No pending agreements." data-am="ምንም በመጠባበቅ ላይ ያሉ ውሎች የሉም።">No
                                        pending agreements.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($agreements as $a): ?>
                                    <tr>
                                        <td><?php echo $a['student_id']; ?></td>
                                        <td><?php echo $a['first_name'] . ' ' . $a['last_name']; ?></td>
                                        <td><?php echo "Year " . ($a['academic_year'] ?? '?') . " / Sem " . $a['semester']; ?>
                                        </td>
                                        <td><?php echo $a['agreement_date']; ?></td>
                                        <td>
                                            <a href="view_agreement_detail.php?id=<?php echo $a['id']; ?>" class="btn-secondary"
                                                style="padding: 5px 10px; font-size: 14px; margin-right: 5px;" data-en="View"
                                                data-am="ይመልከቱ">View</a>
                                            <button type="button" class="btn-primary"
                                                style="padding: 5px 10px; font-size: 14px;"
                                                onclick="openSignatureModal(<?php echo $a['id']; ?>)" data-en="Approve"
                                                data-am="አጽድቅ">Approve</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- Signature Modal -->
    <div id="signatureModal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
        <div
            style="background:white; padding:30px; border-radius:10px; max-width:450px; width:90%; box-shadow: 0 5px 20px rgba(0,0,0,0.3);">
            <h3 style="margin-bottom:15px;" data-en="Upload Signature to Approve" data-am="ለማጽደቅ ፊርማ ይስቀሉ">Upload
                Signature to Approve</h3>
            <form method="POST" enctype="multipart/form-data" id="approveForm">
                <input type="hidden" name="agreement_id" id="modalAgreementId" value="">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label data-en="Department Head Signature (Image):" data-am="የክፍል ሃላፊ ፊርማ (ምስል):">Department Head
                        Signature (Image):</label>
                    <input type="file" name="signature" required accept=".jpg,.jpeg,.png" style="margin-top:8px;">
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" class="btn-secondary" style="padding:8px 16px;"
                        onclick="closeSignatureModal()" data-en="Cancel" data-am="ሰርዝ">Cancel</button>
                    <button type="submit" name="approve_agreement" class="btn-primary" style="padding:8px 16px;"
                        data-en="Sign & Approve" data-am="ፈርም እና አፅድቅ">Sign & Approve</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openSignatureModal(agreementId) {
            document.getElementById('modalAgreementId').value = agreementId;
            document.getElementById('signatureModal').style.display = 'flex';
        }
        function closeSignatureModal() {
            document.getElementById('signatureModal').style.display = 'none';
        }
        // Close modal on outside click
        document.getElementById('signatureModal').addEventListener('click', function (e) {
            if (e.target === this) closeSignatureModal();
        });
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>