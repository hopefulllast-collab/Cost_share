<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['cost_sharing_pro']);
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
                <div class="top-bar">
                    <h2 data-en="Cost Sharing professional Dashboard" data-am="የወጪ ክፍፍል ባለሙያ ዳሽቦርድ">Cost Sharing professional Dashboard</h2>
                    <div class="user-info">
                        <span
                            data-en="Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Cost Sharing Professional'); ?>"
                            data-am="እንኳን ደህና መጡ፣ <?php echo htmlspecialchars($_SESSION['name'] ?? 'Cost Sharing Professional'); ?>">Welcome,
                            <?php echo htmlspecialchars($_SESSION['name'] ?? 'Cost Sharing Professional'); ?>
                        </span>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <h3 data-en="Update Cost Share" data-am="የወጪ ክፍፍል ማሻሻያ">Update Cost Share</h3>
                        <a href="../../modules/cost_sharing/update_cost_share.php" class="btn-small" data-en="View"
                            data-am="ይመልከቱ">View</a>
                    </div>
                    <div class="stat-card">
                        <h3 data-en="Manage Tuition Rates" data-am="የክፍያ ተመን አያያዝ">Manage Tuition Rates</h3>
                        <a href="../../modules/cost_sharing/manage_tuition_rates.php" class="btn-small" data-en="View"
                            data-am="ይመልከቱ">View</a>
                    </div>
                </div>
            </div>  
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>