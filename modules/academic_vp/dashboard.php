<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['academic_vp']);

// Stats Queries
$studentCount = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$deptCount = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();

// Total Cost Share Amount
$totalCostShare = $pdo->query("SELECT COALESCE(SUM(tuition_fee + food_expense + bed_expense + medication_expense), 0) FROM cost_sharing_agreements WHERE status != 'Suspended'")->fetchColumn();

// Approved Agreements Count
$approvedCount = $pdo->query("SELECT COUNT(*) FROM cost_sharing_agreements WHERE status = 'ApprovedByCostPro'")->fetchColumn();

// Pending Agreements Count
$pendingCount = $pdo->query("SELECT COUNT(*) FROM cost_sharing_agreements WHERE status IN ('Draft','SignedByStudent','VerifiedByDept')")->fetchColumn();

require_once '../../includes/academic_translations.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Academic VP Dashboard - DMU" data-am="የአካዳሚክ ም/ፕሬዚዳንት ዳሽቦርድ - DMU">Academic VP Dashboard - DMU</title>
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
                        <div class="dash-stat-icon rose"><i class="fas fa-clock"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Pending" data-am="በመጠባበቅ">Pending</h4>
                            <div class="dash-stat-value"><?php echo $pendingCount; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="dash-content-grid">
                    <!-- Cost Share Summary -->
                    <div class="dash-section">
                        <h3 class="dash-section-title"><i class="fas fa-coins"></i> <span data-en="Cost Share Summary" data-am="የወጪ መጋራት ማጠቃለያ">Cost Share Summary</span></h3>
                        <div class="dash-amount-display">
                            <div class="amount-label" data-en="Total Cost Share Amount" data-am="ጠቅላላ የወጪ መጋራት መጠን">Total Cost Share Amount</div>
                            <div class="amount-value"><?php echo number_format($totalCostShare, 2); ?> <span class="amount-currency" data-en="ETB" data-am="ብር">ETB</span></div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="dash-section">
                        <h3 class="dash-section-title"><i class="fas fa-bolt"></i> <span data-en="Quick Actions" data-am="ፈጣን ተግባራት">Quick Actions</span></h3>
                        <div class="dash-actions">
                            <a href="report_cost_share.php" class="dash-action-link">
                                <i class="fas fa-chart-line"></i>
                                <span data-en="Cost Share Report" data-am="የወጪ መጋራት ሪፖርት">Cost Share Report</span>
                            </a>
                            <a href="view_requested_document.php" class="dash-action-link">
                                <i class="fas fa-file-alt"></i>
                                <span data-en="View Requested Documents" data-am="የተጠየቁ ሰነዶችን ይመልከቱ">View Requested Documents</span>
                            </a>
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
