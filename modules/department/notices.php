<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['department_head']);

// Fetch active (non-expired) notices only
$stmt = $pdo->query("SELECT * FROM notices WHERE expiry_date >= CURDATE() ORDER BY posted_date DESC");
$notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Notices - DMU" data-am="ማስታወቂያዎች - DMU">Notices - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .notice-item {
            border-bottom: 1px solid #eee;
            padding: 15px;
            margin-bottom: 10px;
        }

        .notice-item.expiring-soon {
            border-left: 4px solid #fd7e14;
            padding-left: 12px;
        }

        .notice-expiry-tag {
            display: inline-block;
            font-size: 0.78em;
            padding: 2px 10px;
            border-radius: 20px;
            margin-left: 8px;
            font-weight: 600;
        }

        .tag-active {
            background: #e9f7ef;
            color: #28a745;
        }

        .tag-expiring {
            background: #fff3e0;
            color: #fd7e14;
        }

        .notice-countdown {
            font-size: 0.81em;
            color: #888;
            margin-top: 3px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>

            <div class="main-content">
                <div class="top-bar">
                    <h2 data-en="Notices" data-am="ማስታወቂያዎች">Notices</h2>
                </div>
                <div class="card">
                    <?php if (count($notices) > 0): ?>
                        <div class="notice-list">
                            <?php foreach ($notices as $notice): ?>
                                <div class="notice-item" data-expiry="<?php echo htmlspecialchars($notice['expiry_date']); ?>">
                                    <div style="display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
                                        <h4 style="margin:0;"><?php echo htmlspecialchars($notice['title']); ?></h4>
                                        <span class="notice-expiry-tag"></span>
                                    </div>
                                    <div class="notice-countdown"></div>
                                    <small class="text-muted">
                                        <span data-en="Posted" data-am="የተለጠፈበት">Posted</span>:
                                        <?php echo $notice['posted_date']; ?>
                                        &nbsp;|&nbsp;
                                        <span data-en="Expires" data-am="ይጠናቀቃል">Expires</span>:
                                        <?php echo $notice['expiry_date']; ?>
                                    </small>
                                    <p style="margin-top:10px;">
                                        <?php echo nl2br(htmlspecialchars($notice['content'])); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p data-en="No active notices." data-am="ምንም ንቁ ማስታወቂያ የለም።">No active notices.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
    <script>
        document.querySelectorAll('.notice-item[data-expiry]').forEach(function (item) {
            const expiryStr = item.dataset.expiry;
            const tag = item.querySelector('.notice-expiry-tag');
            const countdown = item.querySelector('.notice-countdown');
            if (!expiryStr) return;

            // DB may store datetime '2026-02-20 00:00:00' — take date part only
            const dateOnly = expiryStr.substring(0, 10);
            const expiryDate = new Date(dateOnly + 'T23:59:59');
            const diffMs = expiryDate - new Date();
            const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

            if (diffDays <= 3) {
                item.classList.add('expiring-soon');
                tag.textContent = '⚠ Expiring Soon';
                tag.className = 'notice-expiry-tag tag-expiring';
                countdown.textContent = diffDays === 0
                    ? 'Expires today'
                    : 'Expires in ' + diffDays + ' day' + (diffDays !== 1 ? 's' : '');
            } else {
                tag.textContent = '✔ Active';
                tag.className = 'notice-expiry-tag tag-active';
                countdown.textContent = 'Expires in ' + diffDays + ' days';
            }
        });
    </script>
</body>

</html>