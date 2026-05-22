<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['cost_sharing_pro']);
require_once '../../includes/academic_translations.php';

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

// Handle Bulk Approval
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_approve'])) {
    $dept_id = $_POST['dept_id'];
    $batch = $_POST['batch'];

    $stmt = $pdo->prepare("SELECT digital_signature FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $signature = $stmt->fetchColumn();

    if (empty($signature)) {
        $error = "<span data-en='You have not set up your digital signature. Please update your profile.' data-am='የዲጂታል ፊርማዎን አላዘጋጁም። እባክዎ ፕሮፋይልዎን ያዘምኑ።'>You have not set up your digital signature. Please <a href=\"../common/update_profile.php\">update your profile</a>.</span>";
    } else {
        $sql = "UPDATE cost_sharing_agreements csa
                JOIN students s ON csa.student_id = s.user_id
                SET csa.status = 'ApprovedByCostPro', 
                    csa.signature_cost_pro = ?
                WHERE s.department_id = ? 
                  AND s.batch = ? 
                  AND csa.status = 'VerifiedByDept'";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$signature, $dept_id, $batch]);

        $count = $stmt->rowCount();
        $_SESSION["flash_success"] = "<span data-en='Successfully approved $count agreements for this batch.' data-am='ለዚህ ባች $count ስምምነቶች በተሳካ ሁኔታ ጸድቀዋል።'>Successfully approved $count agreements for this batch.</span>";
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    }
}

// Fetch Grouped Agreements (VerifiedByDept)
// Group by Department and Batch
$sql = "SELECT d.id as dept_id, d.name as dept_name, s.batch, COUNT(csa.id) as count
        FROM cost_sharing_agreements csa
        JOIN students s ON csa.student_id = s.user_id
        JOIN departments d ON s.department_id = d.id
        WHERE csa.status = 'VerifiedByDept'
        GROUP BY d.id, d.name, s.batch
        ORDER BY d.name, s.batch";

$stmt = $pdo->query($sql);
$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Verify Agreements - Cost Sharing" data-am="ስምምነቶችን ያረጋግጡ - የወጪ መጋራት">Verify Agreements</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .batch-card {
            background: #fff;
            border-left: 5px solid #007bff;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .batch-info h3 {
            margin: 0 0 5px 0;
            color: #333;
        }

        .batch-info p {
            margin: 0;
            color: #666;
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
                    <h2 data-en="Verify Cost Sharing Agreements" data-am="የወጪ መጋራት ስምምነቶችን ያረጋግጡ">Verify Agreements</h2>
                </div>

                <?php if ($msg)
                    echo "<div class='success-banner'>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>

                <div class="card">
                    <h3 style="margin-bottom: 20px;" data-en="Pending Batches (Verified by Dept)"
                        data-am="በመጠባበቅ ላይ ያሉ ባችዎች (በዲፓርትመንት የተረጋገጠ)">Pending Batches (Verified by Dept)</h3>

                    <?php if (empty($groups)): ?>
                        <p data-en="No pending agreements to verify." data-am="የሚረጋገጡ ስምምነቶች የሉም።">No pending agreements to
                            verify.</p>
                    <?php else: ?>
                        <?php foreach ($groups as $group): ?>
                            <div class="batch-card">
                                <div class="batch-info">
                                    <h3>
                                        <?php $dept_am = $academic_translations[$group['dept_name']] ?? $group['dept_name']; ?>
                                        <span data-en="<?php echo htmlspecialchars($group['dept_name']); ?>"
                                            data-am="<?php echo htmlspecialchars($dept_am); ?>"><?php echo htmlspecialchars($group['dept_name']); ?></span>
                                    </h3>
                                    <p><span data-en="Year of Study" data-am="የጥናት ዓመት">Year of Study</span>:
                                        <strong><?php echo $group['batch']; ?></strong>
                                    </p>
                                    <p><span data-en="Pending Agreements" data-am="በመጠባበቅ ላይ ያሉ ስምምነቶች">Pending
                                            Agreements</span>: <strong><?php echo $group['count']; ?></strong></p>
                                </div>
                                <div class="batch-actions">
                                    <!-- View List Button -->
                                    <a href="approve_cost_share_list.php?department_id=<?php echo $group['dept_id']; ?>&batch=<?php echo $group['batch']; ?>"
                                        class="btn-secondary" style="margin-right: 10px;">
                                        <i class="fas fa-list"></i> <span data-en="View List" data-am="ዝርዝር ይመልከቱ">View
                                            List</span>
                                    </a>

                                    <!-- Bulk Approve Form -->
                                    <button class="btn-primary"
                                        onclick="openModal('<?php echo $group['dept_id']; ?>', '<?php echo $group['batch']; ?>', '<?php echo htmlspecialchars($group['dept_name']); ?>')">
                                        <i class="fas fa-check-double"></i> <span data-en="Verify Batch"
                                            data-am="ባች አረጋግጥ">Verify Batch</span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- Modal -->
    <div id="verifyModal" class="modal"
        style="display:none; position:fixed; z-index:100; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
        <div class="modal-content"
            style="background:#fff; margin:15% auto; padding:20px; border:1px solid #888; width:40%; border-radius:8px;">
            <span class="close" onclick="closeModal()"
                style="float:right; font-size:28px; cursor:pointer;">&times;</span>
            <h3 id="modalTitle"><span data-en="Verify Batch" data-am="ባች አረጋግጥ">Verify Batch</span> <span
                    id="modalDynamicInfo"></span></h3>
            <form method="POST">
                <input type="hidden" name="dept_id" id="modalDeptId">
                <input type="hidden" name="batch" id="modalBatch">

                <div class="form-group" style="margin: 20px 0; background: #f9f9f9; padding: 15px; border-left: 4px solid #007bff;">
                    <p style="margin: 0;" data-en="This action will use your saved digital signature to approve all students in this batch." data-am="ይህ እርምጃ የተቀመጠውን የዲጂታል ፊርማዎን በመጠቀም በዚህ ባች ውስጥ ያሉትን ሁሉንም ተማሪዎች ያፀድቃል።">This action will use your saved digital signature to approve all students in this batch.</p>
                </div>

                <div style="text-align:right;">
                    <button type="button" class="btn-secondary" onclick="closeModal()" data-en="Cancel"
                        data-am="ሰርዝ">Cancel</button>
                    <button type="submit" name="bulk_approve" class="btn-primary" data-en="Approve & Sign"
                        data-am="አጽድቅ እና ፈርም">Approve & Sign</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(deptId, batch, deptName) {
            document.getElementById('verifyModal').style.display = "block";
            document.getElementById('modalDeptId').value = deptId;
            document.getElementById('modalBatch').value = batch;
            document.getElementById('modalDynamicInfo').innerText = ": " + deptName + " - Batch " + batch;
        }

        function closeModal() {
            document.getElementById('verifyModal').style.display = "none";
        }

        // Close if clicked outside
        window.onclick = function (event) {
            if (event.target == document.getElementById('verifyModal')) {
                closeModal();
            }
        }
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>