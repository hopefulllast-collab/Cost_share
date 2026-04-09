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
    <title data-en="Approve Clearance - Transcript Pro" data-am="ክሊራንስ ማጽደቅ - ትራንስክሪፕት ባለሙያ">Approve Clearance -
        Transcript Pro</title>
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
                    <h2 data-en="Approve Clearance" data-am="ክሊራንስ ማጽደቅ">Approve Clearance</h2>
                </div>
                <div class="card">
                    <p data-en="Approve Clearance functionality coming soon." data-am="ክሊራንስ ማጽደቅ ተግባር በቅርቡ ይመጣል።">
                        Approve Clearance functionality coming soon.</p>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>