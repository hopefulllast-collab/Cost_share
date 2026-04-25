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
    <title data-en="Transcript Office - Cost Sharing Pro" data-am="ትራንስክሪፕት ቢሮ - የወጪ መጋራት ባለሙያ">Transcript Office - Cost
        Sharing Pro</title>
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

                <div class="card">
                    <h3 data-en="Actions" data-am="ተግባራት">Actions</h3>
                    <div class="action-buttons">
                        <a href="accept_cost_share.php" class="btn-primary" data-en="Record History (Transfer)"
                            data-am="የታሪክ መዝገብ (ዝውውር)">Record History (Transfer)</a>
                        <a href="issue_document.php" class="btn-secondary" data-en="Issue Document"
                            data-am="ሰነድ ይስጡ">Issue Document</a>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
        <script src="../../assets/js/bilingual.js"></script>
</body>

</html>