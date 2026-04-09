<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['admin']);

$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
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
                <div class="top-bar">
                    <h2 data-en="System Administrator" data-am="ስርዓት አስተዳደር">System Administrator</h2>
                    <!-- Lang button moved to header, can remove or keep page specific actions here -->
                </div>

                <div class="card-grid">
                    <div class="card info-card">
                        <h3 data-en="Total Users" data-am="ጠቅላላ ተጠቃሚዎች">Total Users</h3>
                        <p class="big-number">
                            <?php echo $userCount; ?>
                        </p>
                    </div>
                </div>

                <div class="card">
                    <h3 data-en="System Management" data-am="ስርዓት አስተዳደር">System Management</h3>
                    <div class="action-buttons">
                        <a href="manage_users.php" class="btn-primary" data-en="Manage Users" data-am="ተጠቃሚዎችን ማስተዳደር"
                            style="color:#fff; background-color:#000000">Manage
                            Users</a>
                        <a href="feedback_list.php" class="btn-secondary" data-en="View Feedback" data-am="ግንዛቤ ማየት"
                            style="color:#fff; background-color:#000000">View Feedback</a>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>