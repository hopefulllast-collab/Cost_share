<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['admin']);

$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$studentCount = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$deptCount = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$activeCount = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Admin Dashboard - DMU" data-am="የአስተዳዳሪ ዳሽቦርድ - DMU">Admin Dashboard - DMU</title>
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
                        <div class="dash-stat-icon purple"><i class="fas fa-users"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Total Users" data-am="ጠቅላላ ተጠቃሚዎች">Total Users</h4>
                            <div class="dash-stat-value"><?php echo $userCount; ?></div>
                        </div>
                    </div>
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon blue"><i class="fas fa-user-graduate"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Students" data-am="ተማሪዎች">Students</h4>
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
                        <div class="dash-stat-icon green"><i class="fas fa-user-check"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Active Users" data-am="ንቁ ተጠቃሚዎች">Active Users</h4>
                            <div class="dash-stat-value"><?php echo $activeCount; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dash-section">
                    <h3 class="dash-section-title"><i class="fas fa-cogs"></i> <span data-en="System Management" data-am="ስርዓት አስተዳደር">System Management</span></h3>
                    <div class="dash-actions">
                        <a href="manage_users.php" class="dash-action-link">
                            <i class="fas fa-users-cog"></i>
                            <span data-en="Manage Accounts" data-am="መለያዎችን ያስተዳድሩ">Manage Accounts</span>
                        </a>
                        <a href="create_account.php" class="dash-action-link">
                            <i class="fas fa-user-plus"></i>
                            <span data-en="Create Account" data-am="መለያ ይፍጠሩ">Create Account</span>
                        </a>
                        <a href="feedback_list.php" class="dash-action-link">
                            <i class="fas fa-comment-dots"></i>
                            <span data-en="View Feedback" data-am="ግብረመልስ ይመልከቱ">View Feedback</span>
                        </a>
                        <a href="audit_logs.php" class="dash-action-link">
                            <i class="fas fa-history"></i>
                            <span data-en="Audit Logs" data-am="የኦዲት ምዝግብ">Audit Logs</span>
                        </a>
                        <a href="report_cost_share.php" class="dash-action-link">
                            <i class="fas fa-chart-line"></i>
                            <span data-en="Cost Share Reports" data-am="የወጪ መጋራት ሪፖርት">Cost Share Reports</span>
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