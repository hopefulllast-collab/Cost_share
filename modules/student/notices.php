<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['student']);

$today = date('Y-m-d');
// Only fetch non-expired notices
$notices = $pdo->query("SELECT * FROM notices WHERE expiry_date >= '$today' ORDER BY posted_date DESC")->fetchAll();
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
        .notice-card {
            border-left: 4px solid #007bff;
            margin-bottom: 16px;
        }

        .notice-card.expiring-soon {
            border-left-color: #fd7e14;
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
            font-size: 0.82em;
            color: #888;
            margin-top: 4px;
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

                <?php if (count($notices) > 0): ?>
                    <?php foreach ($notices as $n): ?>
                        <div class="card notice-card" data-expiry="<?php echo htmlspecialchars($n['expiry_date']); ?>">
                            <div style="display:flex; align-items:center; flex-wrap:wrap; gap:6px;">
                                <h3 style="margin:0;"><?php echo htmlspecialchars($n['title']); ?></h3>
                                <span class="notice-expiry-tag"></span>
                            </div>
                            <div class="notice-countdown"></div>
                            <small style="color:#888;">
                                <span data-en="Posted on" data-am="የተለጠፈበት ቀን">Posted on</span>:
                                <?php echo substr($n['posted_date'], 0, 10); ?>
                                &nbsp;|&nbsp;
                                <span data-en="Expires" data-am="ይጠናቀቃል">Expires</span>:
                                <?php echo substr($n['expiry_date'], 0, 10); ?>
                            </small>
                            <p class="mt-10"><?php echo nl2br(htmlspecialchars($n['content'])); ?></p>
                            <?php if (!empty($n['has_form_link'])): ?>
                                <div style="margin-top: 15px;">
                                    <a href="agreement_form.php" class="btn-primary"
                                        style="display: inline-block; text-decoration: none; color: white;">
                                        <i class="fas fa-file-signature"></i>
                                        <span data-en="Fill Cost Share Agreement" data-am="የወጪ መጋራት ውል ይሙሉ">Fill Cost Share
                                            Agreement</span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p data-en="No active notices at this time." data-am="በዚህ ጊዜ ምንም ንቁ ማስታወቂያዎች የሉም።">No active notices at
                        this time.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
    <script>
        // Render expiry tag + countdown on each notice card
        document.querySelectorAll('.notice-card[data-expiry]').forEach(function (card) {
            const expiryStr = card.dataset.expiry;
            const tag = card.querySelector('.notice-expiry-tag');
            const countdown = card.querySelector('.notice-countdown');
            if (!expiryStr) return;

            // DB may store datetime '2026-02-20 00:00:00' — must take date part only
            const dateOnly = expiryStr.substring(0, 10);
            const expiryDate = new Date(dateOnly + 'T23:59:59');
            const diffMs = expiryDate - new Date();
            const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

            if (diffDays <= 3) {
                card.classList.add('expiring-soon');
                tag.textContent = '⚠ Expiring Soon';
                tag.className = 'notice-expiry-tag tag-expiring';
                countdown.textContent = diffDays === 0
                    ? 'Expires today — act now!'
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