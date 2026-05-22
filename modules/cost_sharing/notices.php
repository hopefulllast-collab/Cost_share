<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['cost_sharing_pro']);

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

// Post new notice
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_notice'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $expiry = $_POST['expiry_date'];
    $has_link = isset($_POST['has_link']) ? 1 : 0;

    // Process file upload if any
    $signature_seal = null;
    if (isset($_FILES['signature_seal']) && $_FILES['signature_seal']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/seals/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileinfo = pathinfo($_FILES['signature_seal']['name']);
        $ext = strtolower($fileinfo['extension']);
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed)) {
            $filename = uniqid('seal_') . '.' . $ext;
            $destination = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['signature_seal']['tmp_name'], $destination)) {
                $signature_seal = $filename;
            }
        }
    }

    // Server-side: expiry must be today or future
    if ($expiry < date('Y-m-d')) {
        $error = "<span data-en='Expiry date must be today or a future date.' data-am='የሚያበቃበት ቀን ዛሬ ወይም የወደፊት ቀን መሆን አለበት።'>Expiry date must be today or a future date.</span>";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO notices (title, content, posted_by, expiry_date, has_form_link, signature_seal)
                                   VALUES (:t, :c, :uid, :e, :l, :s)");
            $stmt->execute([':t' => $title, ':c' => $content, ':uid' => $_SESSION['user_id'], ':e' => $expiry, ':l' => $has_link, ':s' => $signature_seal]);
            $_SESSION["flash_success"] = "<span data-en='Notice posted successfully!' data-am='ማስታወቂያው በተሳካ ሁኔታ ተለጥፏል!'>Notice posted successfully!</span>";
            header("Location: " . $_SERVER["PHP_SELF"]);
            exit();
        } catch (PDOException $e) {
            $error = "<span data-en='Error posting notice: " . $e->getMessage() . "' data-am='ማስታወቂያውን መለጠፍ አልተቻለም: " . $e->getMessage() . "'>Error posting notice: " . $e->getMessage() . "</span>";
        }
    }
}

// Delete notice
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_notice'])) {
    $del_id = (int) $_POST['notice_id'];
    try {
        $pdo->prepare("DELETE FROM notices WHERE id = ?")->execute([$del_id]);
        $_SESSION["flash_success"] = "<span data-en='Notice deleted.' data-am='ማስታወቂያው ተሰርዟል።'>Notice deleted.</span>";
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    } catch (PDOException $e) {
        $error = "<span data-en='Error deleting notice.' data-am='ማስታወቂያውን መሰረዝ አልተቻለም።'>Error deleting notice.</span>";
    }
}

$notices = $pdo->query("SELECT n.*, u.digital_signature as pro_signature FROM notices n LEFT JOIN users u ON n.posted_by = u.id ORDER BY n.posted_date DESC")->fetchAll();
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Manage Notices - DMU" data-am="ማስታወቂያዎችን ያቀናብሩ - DMU">Manage Notices - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .badge-active {
            background: #28a745;
            color: #fff;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.78em;
            font-weight: 600;
        }

        .badge-expired {
            background: #dc3545;
            color: #fff;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.78em;
            font-weight: 600;
        }

        .badge-expiring {
            background: #fd7e14;
            color: #fff;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.78em;
            font-weight: 600;
        }

        .countdown-text {
            font-size: 0.82em;
            color: #555;
            margin-top: 2px;
        }

        .expired-row td {
            opacity: 0.55;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.82em;
        }

        .btn-delete:hover {
            background: #b02a37;
        }

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
                    <h2 data-en="Manage Notices" data-am="ማስታወቂያዎችን ያቀናብሩ">Manage Notices</h2>
                    <a href="index.php" class="btn-sm" data-en="Back" data-am="ተመለስ">Back</a>
                </div>

                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>

                <!-- Post Form -->
                <div class="card">
                    <h3 data-en="Post New Notice" data-am="አዲስ ማስታወቂያ ይለጥፉ">Post New Notice</h3>
                    <form method="POST" id="noticeForm" enctype="multipart/form-data">
                        <div class="form-group">
                            <label data-en="Title" data-am="ርዕስ">Title</label>
                            <input type="text" name="title" required placeholder="Notice title" data-en="Notice title"
                                data-am="የማስታወቂያ ርዕስ" data-en-placeholder="Notice title"
                                data-am-placeholder="የማስታወቂያ ርዕስ">
                        </div>
                        <div class="form-group">
                            <label data-en="Content" data-am="ይዘት">Content</label>
                            <textarea name="content" required placeholder="Enter notice content..."
                                data-en="Enter notice content..." data-am="የማስታወቂያ ይዘት ያስገቡ"
                                data-en-placeholder="Enter notice content..."
                                data-am-placeholder="የማስታወቂያውን ይዘት ያስገቡ..."
                                style="width:100%; height:100px; padding:10px;"></textarea>
                        </div>
                        <div class="form-group">
                            <label data-en="Expiry Date" data-am="የሚያበቃበት ቀን">Expiry Date</label>
                            <input type="date" name="expiry_date" id="expiryInput" required>
                            <small id="expiryHint" style="color:#888; font-size:0.83em;"></small>
                        </div>
                        <div class="form-group"
                            style="display: flex; flex-direction: row; align-items: center; gap: 10px;">
                            <input type="checkbox" name="has_link" id="hasLink" style="width: auto; margin: 0;">
                            <label for="hasLink" style="margin: 0;" data-en="Include Link to Fill Cost Share Form"
                                data-am="የወጪ መጋራት ቅጽን ለማካተት የሳጥን ምልክቷን ይንኩ">Include Link to Fill Cost Share Form</label>
                        </div>
                        <div class="form-group">
                            <label data-en="Digital Seal & Signature (Optional)"
                                data-am="ዲጂታል ማህተም እና ፊርማ (አማራጭ)">Digital Seal & Signature (Optional)</label>
                            <input type="file" name="signature_seal" accept="image/*"
                                style="border: none; background: transparent; padding: 0; box-shadow: none;">
                        </div>
                        <button type="submit" name="post_notice" class="btn-primary" data-en="Post Notice"
                            data-am="ማስታወቂያ ይለጥፉ">Post Notice</button>
                    </form>
                </div>

                <!-- Notice List -->
                <div class="mt-20">
                    <h3 style="margin-bottom:20px;" data-en="All Published Notices" data-am="ሁሉም የታተሙ ማስታወቂያዎች">All
                        Published Notices</h3>

                    <?php if (empty($notices)): ?>
                        <div class="card">
                            <p data-en="No notices posted yet." data-am="እስካሁን ምንም ማስታወቂያ አልተለጠፈም።">No notices posted yet.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notices as $n): ?>
                            <?php
                            $isExpired = (substr($n['expiry_date'], 0, 10) < $today);
                            $opacity = $isExpired ? '0.6' : '1';
                            ?>
                            <div class="official-paper" data-expiry="<?php echo htmlspecialchars($n['expiry_date']); ?>"
                                style="opacity: <?php echo $opacity; ?>;">
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
                                                <img src="<?php echo htmlspecialchars($n['pro_signature']); ?>" style="max-height: 80px; max-width: 150px; object-fit: contain; margin-bottom: 5px;">
                                            <?php else: ?>
                                                <svg width="150" height="60" viewBox="0 0 200 80" xmlns="http://www.w3.org/2000/svg" style="transform: rotate(-5deg); opacity: 0.8;">
                                                    <path d="M 20 50 C 30 20, 40 10, 50 30 C 60 50, 55 70, 70 40 C 85 10, 80 50, 95 40 C 110 30, 105 15, 120 25 C 135 35, 125 60, 145 45 C 165 30, 155 45, 175 40" fill="none" stroke="#001845" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    <path d="M 120 15 L 125 45 M 115 30 L 135 25" fill="none" stroke="#001845" stroke-width="2" stroke-linecap="round"/>
                                                </svg>
                                            <?php endif; ?>
                                            <div style="border-top: 1px solid #333; width: 160px; margin: 0 auto; padding-top: 5px; font-size: 13px; font-weight: bold;">
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

                                <!-- Action bar at the bottom -->
                                <div class="notice-action-bar">
                                    <div>
                                        <span class="notice-status-badge"></span>
                                        <span class="countdown-text notice-countdown" style="margin-left: 10px;"></span>
                                        <div style="font-size: 13px; color:#777; margin-top:5px;">
                                            <span><strong>Expires:</strong>
                                                <?php echo substr($n['expiry_date'], 0, 10); ?></span>
                                        </div>
                                    </div>
                                    <div>
                                        <form method="POST" id="deleteForm_<?php echo $n['id']; ?>" class="delete-notice-form">
                                            <input type="hidden" name="notice_id" value="<?php echo $n['id']; ?>">
                                            <button type="button" class="btn-delete"
                                                onclick="confirmDelete(<?php echo $n['id']; ?>)" style="padding: 6px 15px;">
                                                <i class="fas fa-trash"></i>
                                                <span data-en="Delete Notice" data-am="ማስታወቂያውን ሰርዝ">Delete Notice</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>

    <script src="../../assets/js/bilingual.js"></script>
    <script>
        // ── Expiry Date: enforce min = today on the form input ─────────────────
        const expiryInput = document.getElementById('expiryInput');
        const expiryHint = document.getElementById('expiryHint');
        const today = new Date().toISOString().split('T')[0];
        expiryInput.setAttribute('min', today);

        expiryInput.addEventListener('change', function () {
            const selected = new Date(this.value);
            const now = new Date(today);
            const diffDays = Math.round((selected - now) / (1000 * 60 * 60 * 24));

            if (diffDays === 0) {
                expiryHint.textContent = '⚠ Expires today';
                expiryHint.style.color = '#fd7e14';
            } else if (diffDays > 0) {
                expiryHint.textContent = `✔ Expires in ${diffDays} day${diffDays !== 1 ? 's' : ''}`;
                expiryHint.style.color = '#28a745';
            }
        });

        // ── Notice table: render status badge + countdown for each row ─────────
        document.querySelectorAll('.official-paper[data-expiry]').forEach(function (row) {
            const expiryStr = row.dataset.expiry;
            const badge = row.querySelector('.notice-status-badge');
            const countdown = row.querySelector('.notice-countdown');

            if (!expiryStr) return;

            // DB may store datetime '2026-02-20 00:00:00' — take date part only
            const dateOnly = expiryStr.substring(0, 10);
            const expiryDate = new Date(dateOnly + 'T23:59:59');
            const nowDate = new Date();
            const diffMs = expiryDate - nowDate;
            const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

            if (diffMs < 0) {
                // Expired
                badge.textContent = '✖ Expired';
                badge.className = 'notice-status-badge badge-expired';
                countdown.textContent = 'Expired ' + Math.abs(diffDays) + ' day' + (Math.abs(diffDays) !== 1 ? 's' : '') + ' ago';
            } else if (diffDays <= 3) {
                // Expiring soon (≤3 days)
                badge.textContent = '⚠ Expiring Soon';
                badge.className = 'notice-status-badge badge-expiring';
                countdown.textContent = diffDays === 0
                    ? 'Expires today'
                    : 'Expires in ' + diffDays + ' day' + (diffDays !== 1 ? 's' : '');
            } else {
                // Active
                badge.textContent = '✔ Active';
                badge.className = 'notice-status-badge badge-active';
                countdown.textContent = 'Expires in ' + diffDays + ' days';
            }
        });

        function confirmDelete(noticeId) {
            const msgEn = "Are you sure you want to delete this notice?";
            const msgAm = "ይህን ማስታወቂያ ለመሰረዝ እርግጠኛ ነዎት?";
            showBilingualConfirm(msgEn, msgAm, function () {
                // To submit securely with the specific name attribute, we create a hidden input
                const form = document.getElementById('deleteForm_' + noticeId);
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'delete_notice';
                hiddenInput.value = '1';
                form.appendChild(hiddenInput);
                form.submit();
            });
        }
    </script>
</body>

</html>