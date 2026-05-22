<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['student']);

$today = date('Y-m-d');
// Only fetch non-expired notices and include the author's digital signature
$notices = $pdo->query("SELECT n.*, u.digital_signature as pro_signature FROM notices n LEFT JOIN users u ON n.posted_by = u.id WHERE n.expiry_date >= '$today' ORDER BY n.posted_date DESC")->fetchAll();
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
        /* Official Paper Layout Elements */
        .official-paper {
            background: #fff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #ddd;
            padding: 40px;
            margin-bottom: 30px;
            width: 100%;
            border-radius: 4px;
            position: relative;
        }

        .paper-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }

        .paper-header-text h1 {
            margin: 0;
            font-size: 24px;
            color: #1a1a2e;
            font-family: 'Times New Roman', serif;
        }

        .paper-header-text h2 {
            margin: 5px 0 0 0;
            font-size: 18px;
            color: #555;
            font-family: 'Times New Roman', serif;
        }

        .paper-content {
            font-size: 16px;
            line-height: 1.6;
            color: #333;
            font-family: Arial, sans-serif;
        }

        .paper-title {
            text-align: center;
            text-transform: uppercase;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 25px;
            text-decoration: underline;
        }

        .seal-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 40px;
        }

        .seal-container img {
            max-width: 200px;
            max-height: 120px;
            object-fit: contain;
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

        .notice-action-bar {
            background: #f8f9fa;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px dashed #ccc;
            margin-top: 20px;
            margin-left: -40px;
            margin-right: -40px;
            margin-bottom: -40px;
            border-bottom-left-radius: 4px;
            border-bottom-right-radius: 4px;
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

                <div class="mt-20">
                    <?php if (count($notices) > 0): ?>
                        <?php foreach ($notices as $n): ?>
                            <div class="official-paper" data-expiry="<?php echo htmlspecialchars($n['expiry_date']); ?>">
                                <!-- Header -->
                                <div class="paper-header">
                                    <div class="paper-header-text">
                                        <h1>Debre Markos University</h1>
                                        <h2>ደብረ ማርቆስ ዩኒቨርሲቲ</h2>
                                    </div>
                                </div>

                                <div
                                    style="display: flex; justify-content: space-between; margin-bottom: 20px; color:#555; font-size:14px;">
                                    <div><strong>Ref No:</strong> DMU-CS/<?php echo $n['id']; ?>/<?php echo date('Y'); ?></div>
                                    <div><strong>Date:</strong> <?php echo substr($n['posted_date'], 0, 10); ?></div>
                                </div>

                                <div class="paper-title"><?php echo htmlspecialchars($n['title']); ?></div>

                                <div class="paper-content">
                                    <?php echo nl2br(htmlspecialchars($n['content'])); ?>
                                </div>

                                <div class="seal-container"
                                    style="display: flex; align-items: center; justify-content: flex-end; margin-top: 40px; gap: 30px;">
                                    <?php if (!empty($n['signature_seal'])): ?>
                                        <img src="../../uploads/seals/<?php echo htmlspecialchars($n['signature_seal']); ?>"
                                            alt="Digital Seal / Signature"
                                            style="max-width: 250px; max-height: 120px; object-fit: contain;">
                                    <?php else: ?>
                                        <!-- Embedded Graphic SVG Signature -->
                                        <div class="signature-box" style="text-align: center; color: #1a1a2e; margin-right: 20px;">
                                            <?php if (!empty($n['pro_signature'])): ?>
                                                <img src="<?php echo htmlspecialchars($n['pro_signature']); ?>"
                                                    style="max-height: 80px; max-width: 150px; object-fit: contain; margin-bottom: 5px;">
                                            <?php else: ?>
                                                <svg width="150" height="60" viewBox="0 0 200 80" xmlns="http://www.w3.org/2000/svg"
                                                    style="transform: rotate(-5deg); opacity: 0.8;">
                                                    <path
                                                        d="M 20 50 C 30 20, 40 10, 50 30 C 60 50, 55 70, 70 40 C 85 10, 80 50, 95 40 C 110 30, 105 15, 120 25 C 135 35, 125 60, 145 45 C 165 30, 155 45, 175 40"
                                                        fill="none" stroke="#001845" stroke-width="3.5" stroke-linecap="round"
                                                        stroke-linejoin="round" />
                                                    <path d="M 120 15 L 125 45 M 115 30 L 135 25" fill="none" stroke="#001845"
                                                        stroke-width="2" stroke-linecap="round" />
                                                </svg>
                                            <?php endif; ?>
                                            <div
                                                style="border-top: 1px solid #333; width: 160px; margin: 0 auto; padding-top: 5px; font-size: 13px; font-weight: bold;">
                                                <span data-en="Cost Sharing Professional" data-am="የወጪ መጋራት ባለሙያ">Cost Sharing
                                                    Professional</span>
                                            </div>
                                        </div>

                                        <!-- Embedded Official Circular SVG Seal -->
                                        <div class="svg-seal" style="width: 140px; height: 140px;">
                                            <svg viewBox="0 0 200 200" width="140" height="140"
                                                style="opacity:0.85; transform: rotate(-10deg);">
                                                <circle cx="100" cy="100" r="95" fill="none" stroke="#1c3b70" stroke-width="4" />
                                                <circle cx="100" cy="100" r="88" fill="none" stroke="#1c3b70" stroke-width="1.5" />
                                                <circle cx="100" cy="100" r="50" fill="none" stroke="#1c3b70" stroke-width="1.5" />
                                                <defs>
                                                    <path id="top-path" d="M 25, 100 A 75 75 0 0 1 175, 100" />
                                                    <path id="bottom-path" d="M 25, 100 A 75 75 0 0 0 175, 100" />
                                                </defs>
                                                <text fill="#1c3b70" font-family="Arial, sans-serif" font-size="14"
                                                    font-weight="bold" letter-spacing="1">
                                                    <textPath href="#top-path" startOffset="50%" text-anchor="middle">DEBRE MARKOS
                                                        UNIVERSITY</textPath>
                                                </text>
                                                <text fill="#1c3b70" font-family="Arial, sans-serif" font-size="13"
                                                    font-weight="bold" letter-spacing="1.5">
                                                    <textPath href="#bottom-path" startOffset="50%" text-anchor="middle">COST
                                                        SHARING OFFICE</textPath>
                                                </text>
                                                <text x="100" y="110" font-family="Times New Roman, serif" font-size="34"
                                                    font-weight="bold" text-anchor="middle" fill="#1c3b70">DMU</text>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="notice-action-bar">
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <span class="notice-expiry-tag"></span>
                                        <?php if (!empty($n['has_form_link'])): ?>
                                            <a href="agreement_form.php" class="btn-primary"
                                                style="display: inline-block; text-decoration: none; color: white; padding: 6px 15px; font-size: 0.9em;">
                                                <i class="fas fa-file-signature"></i>
                                                <span data-en="Fill Cost Share Agreement" data-am="የወጪ መጋራት ውል ይሙሉ">Fill Cost Share
                                                    Agreement</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: right; color:#888; font-size:0.85em;">
                                        <strong>Expires:</strong> <?php echo substr($n['expiry_date'], 0, 10); ?>
                                        <div class="notice-countdown" style="margin-top:2px; font-style:italic;"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="card">
                            <p data-en="No active notices at this time." data-am="በዚህ ጊዜ ምንም ንቁ ማስታወቂያዎች የሉም።">No active
                                notices at
                                this time.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
    <script>
        // Render expiry tag + countdown on each notice card
        document.querySelectorAll('.official-paper[data-expiry]').forEach(function (card) {
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