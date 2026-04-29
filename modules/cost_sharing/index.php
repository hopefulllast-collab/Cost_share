<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['cost_sharing_pro']);

$pendingCount = $pdo->query("SELECT COUNT(*) FROM cost_sharing_agreements WHERE status = 'VerifiedByDept'")->fetchColumn();
$approvedCount = $pdo->query("SELECT COUNT(*) FROM cost_sharing_agreements WHERE signature_cost_pro IS NOT NULL AND signature_cost_pro != ''")->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Cost Sharing Pro - Dashboard" data-am="የወጪ መጋራት ባለሙያ - ዳሽቦርድ">Cost Sharing Pro - Dashboard</title>
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
                            <h4 data-en="Total Students" data-am="ጠቅላላ ተማሪዎች">Total Students</h4>
                            <div class="dash-stat-value"><?php echo $totalStudents; ?></div>
                        </div>
                    </div>
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon amber"><i class="fas fa-clock"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Pending Review" data-am="በመጠባበቅ ላይ">Pending Review</h4>
                            <div class="dash-stat-value"><?php echo $pendingCount; ?></div>
                        </div>
                    </div>
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
                    <h3 class="dash-section-title"><i class="fas fa-bolt"></i> <span data-en="Quick Actions" data-am="ፈጣን ተግባራት">Quick Actions</span></h3>
                    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px;">
                        <a href="approve_cost_share.php" class="dash-action-link" style="flex-direction:column; text-align:center; padding:20px 14px; gap:10px;">
                            <i class="fas fa-check-double" style="width:42px; height:42px; font-size:1rem; border-radius:12px;"></i>
                            <span data-en="Approve Agreements" data-am="ውሎችን አጽድቅ" style="font-size:0.82rem;">Approve Agreements</span>
                            <?php if ($pendingCount > 0): ?>
                                <span class="dash-action-badge"><?php echo $pendingCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="update_cost_share.php" class="dash-action-link" style="flex-direction:column; text-align:center; padding:20px 14px; gap:10px;">
                            <i class="fas fa-edit" style="width:42px; height:42px; font-size:1rem; border-radius:12px;"></i>
                            <span data-en="Update Cost Share" data-am="የወጪ ክፍፍል ማሻሻያ" style="font-size:0.82rem;">Update Cost Share</span>
                        </a>
                        <a href="manage_tuition_rates.php" class="dash-action-link" style="flex-direction:column; text-align:center; padding:20px 14px; gap:10px;">
                            <i class="fas fa-money-bill-wave" style="width:42px; height:42px; font-size:1rem; border-radius:12px;"></i>
                            <span data-en="Manage Tuition Rates" data-am="የክፍያ ተመን አያያዝ" style="font-size:0.82rem;">Manage Tuition Rates</span>
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