<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['transcript_pro']);

$pendingDocs = $pdo->query("SELECT COUNT(*) FROM official_transcript WHERE status IN ('Pending','Forwarded','Pending Transcript')")->fetchColumn();
$deliveredDocs = $pdo->query("SELECT COUNT(*) FROM official_transcript WHERE status = 'Delivered'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Transcript Office - DMU" data-am="ትራንስክሪፕት ቢሮ - DMU">Transcript Office - DMU</title>
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
                        <div class="dash-stat-icon amber"><i class="fas fa-file-alt"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Pending Documents" data-am="በመጠባበቅ ላይ ያሉ ሰነዶች">Pending Documents</h4>
                            <div class="dash-stat-value"><?php echo $pendingDocs; ?></div>
                        </div>
                    </div>
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon green"><i class="fas fa-check-double"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Delivered" data-am="የተሰጡ">Delivered</h4>
                            <div class="dash-stat-value"><?php echo $deliveredDocs; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dash-section">
                    <h3 class="dash-section-title"><i class="fas fa-bolt"></i> <span data-en="Quick Actions" data-am="ፈጣን ተግባራት">Quick Actions</span></h3>
                    <div class="dash-actions">
                        <a href="accept_cost_share.php" class="dash-action-link">
                            <i class="fas fa-exchange-alt"></i>
                            <span data-en="Record History (Transfer)" data-am="የታሪክ መዝገብ (ዝውውር)">Record History (Transfer)</span>
                        </a>
                        <a href="issue_document.php" class="dash-action-link">
                            <i class="fas fa-file-export"></i>
                            <span data-en="Issue Document" data-am="ሰነድ ይስጡ">Issue Document</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
        <script src="../../assets/js/bilingual.js"></script>
</body>

</html>