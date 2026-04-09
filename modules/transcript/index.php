<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['transcript_pro']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Official Transcript</title>
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
                    <h2 data-en="Official Transcript Dashboard" data-am="ኦፊሴላዊ ትራንስክሪፕት ዳሽቦርድ">Official Transcript
                        Dashboard</h2>
                    <div class="user-info">
                        <span data-en="Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>"
                            data-am="እንኳን ደህና መጡ፣ <?php echo htmlspecialchars($_SESSION['username']); ?>">Welcome,
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </span>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <h3 data-en="Transferred In Students" data-am="የተመዘገቡ ተማሪዎች">Transferred In Students</h3>
                        <p data-en="Check Registration Orders" data-am="የምዝገባ ትእዛዞችን ይመልከቱ">Check Registration Orders
                        </p>
                        <a href="view_orders.php" class="btn-small" data-en="View" data-am="ይመልከቱ">View</a>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>