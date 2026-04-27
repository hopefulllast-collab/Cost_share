<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['cost_sharing_pro']);

$pendingCount = $pdo->query("SELECT COUNT(*) FROM cost_sharing_agreements WHERE status = 'VerifiedByDept'")->fetchColumn();
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
                </div>

                <!-- Quick Actions -->
                <div class="dash-section">
                    <h3 class="dash-section-title"><i class="fas fa-bolt"></i> <span data-en="Quick Actions" data-am="ፈጣን ተግባራት">Quick Actions</span></h3>
                    <div class="dash-actions">
                        <a href="update_cost_share.php" class="dash-action-link">
                            <i class="fas fa-edit"></i>
                            <span data-en="Update Cost Share" data-am="የወጪ ክፍፍል ማሻሻያ">Update Cost Share</span>
                        </a>
                        <a href="manage_tuition_rates.php" class="dash-action-link">
                            <i class="fas fa-money-bill-wave"></i>
                            <span data-en="Manage Tuition Rates" data-am="የክፍያ ተመን አያያዝ">Manage Tuition Rates</span>
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