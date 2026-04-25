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

                <!-- Stats Grid -->
                <div class="card-grid">
                    <div class="card info-card">
                        <h3><i class="fas fa-users" style="color:var(--secondary-color);"></i> <span
                                data-en="Total Students" data-am="ጠቅላላ ተማሪዎች">Total Students</span></h3>
                        <p class="big-number"><?php echo $studentCount; ?></p>
                    </div>
                    <div class="card info-card">
                        <h3><i class="fas fa-university" style="color:var(--secondary-color);"></i> <span
                                data-en="Departments" data-am="የትምህርት ክፍሎች">Departments</span></h3>
                        <p class="big-number"><?php echo $deptCount; ?></p>
                    </div>
                    <div class="card info-card">
                        <h3><i class="fas fa-check-circle" style="color:var(--secondary-color);"></i> <span
                                data-en="Approved Agreements" data-am="የጸደቁ ስምምነቶች">Approved Agreements</span></h3>
                        <p class="big-number"><?php echo $approvedCount; ?></p>
                    </div>
                    <div class="card info-card">
                        <h3><i class="fas fa-clock" style="color:var(--secondary-color);"></i> <span
                                data-en="Pending Agreements" data-am="በመጠባበቅ ላይ ያሉ ስምምነቶች">Pending Agreements</span></h3>
                        <p class="big-number"><?php echo $pendingCount; ?></p>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <div class="card">
                        <h3 data-en="Cost Share Summary" data-am="የወጪ መጋራት ማጠቃለያ">Cost Share Summary</h3>
                        <p style="font-size: 1.1em; margin: 10px 0;">
                            <strong data-en="Total Cost Share Amount:" data-am="ጠቅላላ የወጪ መጋራት መጠን:">Total Cost Share Amount:</strong>
                            <span style="font-size: 1.3em; color: var(--primary-color); font-weight: bold;">
                                <?php echo number_format($totalCostShare, 2); ?> <span data-en="ETB" data-am="ብር">ETB</span>
                            </span>
                        </p>
                    </div>

                    <div class="card">
                        <h3 data-en="Quick Actions" data-am="ፈጣን ተግባራት">Quick Actions</h3>
                        <div class="action-buttons" style="display: flex; flex-direction: column; gap: 10px;">
                            <a href="report_cost_share.php" class="btn-primary"
                                style="text-align: center; width:80%; height: 30%;">
                                <i class="fas fa-chart-line"></i> <span data-en="Report Cost Share"
                                    data-am="የወጪ መጋራት ሪፖርት">Report Cost Share</span>
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
