<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['registrar']);

// Total Cost Share Amount
$totalCostShare = $pdo->query("SELECT COALESCE(SUM(tuition_fee + food_expense + bed_expense + medication_expense), 0) FROM cost_sharing_agreements WHERE status != 'Suspended'")->fetchColumn();

// Stats
$studentCount = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$deptCount = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$pendingCount = $pdo->query("SELECT COUNT(*) FROM official_transcript WHERE request_type = 'CostSharePaper' AND status = 'Pending'")->fetchColumn();
$approvedCount = $pdo->query("SELECT COUNT(*) FROM official_transcript WHERE request_type = 'CostSharePaper' AND status = 'Approved'")->fetchColumn();
$deliveredCount = $pdo->query("SELECT COUNT(*) FROM official_transcript WHERE status = 'Delivered'")->fetchColumn();
$activeAgreements = $pdo->query("SELECT COUNT(*) FROM cost_sharing_agreements WHERE status NOT IN ('Suspended','Draft')")->fetchColumn();

require_once '../../includes/academic_translations.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Registrar Dashboard - DMU" data-am="ሬጅስትራር ዳሽቦርድ - DMU">Registrar Dashboard - DMU</title>
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

                <!-- Stats Row -->
                <div class="dash-stats">
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon blue"><i class="fas fa-user-graduate"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Total Students" data-am="ጠቅላላ ተማሪዎች">Total Students</h4>
                            <div class="dash-stat-value"><?php echo $studentCount; ?></div>
                        </div>
                    </div>
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon amber"><i class="fas fa-building"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Departments" data-am="ትምህርት ክፍሎች">Departments</h4>
                            <div class="dash-stat-value"><?php echo $deptCount; ?></div>
                        </div>
                    </div>
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon green"><i class="fas fa-check-circle"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Approved" data-am="የጸደቁ">Approved</h4>
                            <div class="dash-stat-value"><?php echo $approvedCount; ?></div>
                        </div>
                    </div>
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon rose"><i class="fas fa-hourglass-half"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Pending" data-am="በመጠባበቅ">Pending</h4>
                            <div class="dash-stat-value"><?php echo $pendingCount; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Cost Share Summary — Full Width Premium -->
                <div class="dash-section" style="margin-bottom:22px;">
                    <h3 class="dash-section-title">
                        <i class="fas fa-coins"></i>
                        <span data-en="Cost Share Summary" data-am="የወጪ መጋራት ማጠቃለያ">Cost Share Summary</span>
                    </h3>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; align-items:stretch;">
                        <!-- Main Amount -->
                        <div class="dash-amount-display" style="grid-column:1/2;">
                            <div class="amount-label" data-en="Total Cost Share" data-am="ጠቅላላ የወጪ መጋራት">Total Cost Share</div>
                            <div class="amount-value"><?php echo number_format($totalCostShare, 2); ?> <span class="amount-currency" data-en="ETB" data-am="ብር">ETB</span></div>
                        </div>
                        <!-- Active Agreements -->
                        <div style="text-align:center; padding:20px 16px; background:linear-gradient(135deg, #eef2ff, #e0e7ff); border-radius:14px; display:flex; flex-direction:column; justify-content:center;">
                            <div style="font-size:0.7rem; color:#64748b; text-transform:uppercase; font-weight:600; letter-spacing:0.06em;" data-en="Active Agreements" data-am="ንቁ ውሎች">Active Agreements</div>
                            <div style="font-size:1.8rem; font-weight:800; color:#4338ca; margin-top:4px;"><?php echo $activeAgreements; ?></div>
                        </div>
                        <!-- Delivered / Pending Split -->
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <div style="flex:1; text-align:center; padding:14px; background:linear-gradient(135deg, #f0fdf4, #dcfce7); border-radius:12px; display:flex; flex-direction:column; justify-content:center;">
                                <div style="font-size:0.68rem; color:#64748b; text-transform:uppercase; font-weight:600; letter-spacing:0.05em;" data-en="Delivered" data-am="የተሰጡ">Delivered</div>
                                <div style="font-size:1.3rem; font-weight:800; color:#16a34a; margin-top:2px;"><?php echo $deliveredCount; ?></div>
                            </div>
                            <div style="flex:1; text-align:center; padding:14px; background:linear-gradient(135deg, #fffbeb, #fef3c7); border-radius:12px; display:flex; flex-direction:column; justify-content:center;">
                                <div style="font-size:0.68rem; color:#64748b; text-transform:uppercase; font-weight:600; letter-spacing:0.05em;" data-en="Pending" data-am="በመጠባበቅ">Pending</div>
                                <div style="font-size:1.3rem; font-weight:800; color:#d97706; margin-top:2px;"><?php echo $pendingCount; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions — 3-Column Grid -->
                <div class="dash-section">
                    <h3 class="dash-section-title">
                        <i class="fas fa-bolt"></i>
                        <span data-en="Quick Actions" data-am="ፈጣን ተግባራት">Quick Actions</span>
                    </h3>
                    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px;">
                        <a href="approve_cost_share.php" class="dash-action-link" style="flex-direction:column; text-align:center; padding:20px 14px; gap:10px;">
                            <i class="fas fa-check-double" style="width:42px; height:42px; font-size:1rem; border-radius:12px;"></i>
                            <span data-en="Approve Agreements" data-am="ውሎችን አጽድቅ" style="font-size:0.82rem;">Approve Agreements</span>
                            <?php if ($pendingCount > 0): ?>
                                <span class="dash-action-badge"><?php echo $pendingCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="report_cost_share.php" class="dash-action-link" style="flex-direction:column; text-align:center; padding:20px 14px; gap:10px;">
                            <i class="fas fa-chart-bar" style="width:42px; height:42px; font-size:1rem; border-radius:12px;"></i>
                            <span data-en="Generate Reports" data-am="ሪፖርቶችን አውጣ" style="font-size:0.82rem;">Generate Reports</span>
                        </a>
                        <a href="order.php" class="dash-action-link" style="flex-direction:column; text-align:center; padding:20px 14px; gap:10px;">
                            <i class="fas fa-clipboard-list" style="width:42px; height:42px; font-size:1rem; border-radius:12px;"></i>
                            <span data-en="Manage Orders" data-am="ትዕዛዞችን አስተዳድር" style="font-size:0.82rem;">Manage Orders</span>
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