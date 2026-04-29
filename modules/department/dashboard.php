<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['department_head']);

// Fetch Dept Head's Department
$stmt = $pdo->prepare("SELECT id as department_id, name as dept_name FROM departments WHERE head_user_id = :uid");
$stmt->execute([':uid' => $_SESSION['user_id']]);
$deptHead = $stmt->fetch();
if (!$deptHead) {
    die("<div style='padding:40px; text-align:center; font-family:sans-serif;'>
        <h2 style='color:#e74c3c;'>⚠️ Department Not Assigned</h2>
        <p>Your account (User ID: " . $_SESSION['user_id'] . ") is not assigned as head of any department.</p>
    </div>");
}
$deptId = $deptHead['department_id'];

// Stats
$studentCount = $pdo->prepare("SELECT COUNT(*) FROM students WHERE department_id = ?");
$studentCount->execute([$deptId]);
$studentCount = $studentCount->fetchColumn();

// Fetch Pending Agreements (SignedByStudent) for this Department
$stmt = $pdo->prepare("SELECT csa.*, s.student_id as student_code, u.first_name, u.last_name 
                       FROM cost_sharing_agreements csa
                       JOIN students s ON csa.student_id = s.user_id
                       JOIN users u ON s.user_id = u.id
                       WHERE s.department_id = :did AND csa.status = 'SignedByStudent'");
$stmt->execute([':did' => $deptId]);
$pendingAgreements = $stmt->fetchAll();
$pendingCount = count($pendingAgreements);

// Fetch Approved Agreements (Verified by this Dept Head)
$stmtApproved = $pdo->prepare("SELECT COUNT(*) FROM cost_sharing_agreements csa 
                               JOIN students s ON csa.student_id = s.user_id 
                               WHERE s.department_id = :did AND csa.signature_dept_head IS NOT NULL AND csa.signature_dept_head != ''");
$stmtApproved->execute([':did' => $deptId]);
$approvedCount = $stmtApproved->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Dept Head Dashboard - DMU" data-am="የዲፓርትመንት ኃላፊ ዳሽቦርድ - DMU">Dept Head Dashboard - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>

            <div class="main-content">
                <?php include '../../includes/welcome_banner.php'; ?>

                <!-- Stats -->
                <div class="dash-stats">
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon blue"><i class="fas fa-user-graduate"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Dept Students" data-am="የክፍል ተማሪዎች">Dept Students</h4>
                            <div class="dash-stat-value"><?php echo $studentCount; ?></div>
                        </div>
                    </div>
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon amber"><i class="fas fa-clock"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Pending Approvals" data-am="በመጠባበቅ ላይ">Pending Approvals</h4>
                            <div class="dash-stat-value"><?php echo $pendingCount; ?></div>
                        </div>
                    </div>
                    <!-- New Approved Card -->
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon green"><i class="fas fa-check-circle"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Approved" data-am="የጸደቁ">Approved</h4>
                            <div class="dash-stat-value"><?php echo $approvedCount; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dash-section">
                    <h3 class="dash-section-title">
                        <i class="fas fa-bolt"></i>
                        <span data-en="Quick Actions" data-am="ፈጣን ተግባራት">Quick Actions</span>
                    </h3>
                    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px;">
                        <a href="approve_agreement.php" class="dash-action-link" style="flex-direction:column; text-align:center; padding:20px 14px; gap:10px;">
                            <i class="fas fa-check-double" style="width:42px; height:42px; font-size:1rem; border-radius:12px;"></i>
                            <span data-en="Approve Agreements" data-am="ውሎችን አጽድቅ" style="font-size:0.82rem;">Approve Agreements</span>
                            <?php if ($pendingCount > 0): ?>
                                <span class="dash-action-badge"><?php echo $pendingCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <!-- Future actions can go here -->
                        <a href="report_cost_sharing.php" class="dash-action-link" style="flex-direction:column; text-align:center; padding:20px 14px; gap:10px;">
                            <i class="fas fa-chart-line" style="width:42px; height:42px; font-size:1rem; border-radius:12px;"></i>
                            <span data-en="Department Reports" data-am="የክፍል ሪፖርቶች" style="font-size:0.82rem;">Department Reports</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>