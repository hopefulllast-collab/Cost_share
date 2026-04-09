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

    // Server-side: expiry must be today or future
    if ($expiry < date('Y-m-d')) {
        $error = "<span data-en='Expiry date must be today or a future date.' data-am='የሚያበቃበት ቀን ዛሬ ወይም የወደፊት ቀን መሆን አለበት።'>Expiry date must be today or a future date.</span>";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO notices (title, content, posted_by, expiry_date, has_form_link)
                                   VALUES (:t, :c, :uid, :e, :l)");
            $stmt->execute([':t' => $title, ':c' => $content, ':uid' => $_SESSION['user_id'], ':e' => $expiry, ':l' => $has_link]);
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

$notices = $pdo->query("SELECT * FROM notices ORDER BY posted_date DESC")->fetchAll();
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
                    <form method="POST" id="noticeForm">
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
                        <div class="form-group" style="flex-direction: row; align-items: center; gap: 10px;">
                            <input type="checkbox" name="has_link" id="hasLink" style="width: auto; margin: 0;">
                            <label for="hasLink" style="margin: 0;" data-en="Include Link to Fill Cost Share Form"
                                data-am="የወጪ መጋራት ቅጽን ለማካተት የሳጥን ምልክቷን ይንኩ">Include Link to Fill Cost Share Form</label>
                        </div>
                        <button type="submit" name="post_notice" class="btn-primary" data-en="Post Notice"
                            data-am="ማስታወቂያ ይለጥፉ">Post Notice</button>
                    </form>
                </div>

                <!-- Notice List -->
                <div class="card mt-20">
                    <h3 data-en="All Notices" data-am="ሁሉም ማስታወቂያዎች">All Notices</h3>
                    <table class="table-list">
                        <thead>
                            <tr>
                                <th data-en="Title" data-am="ርዕስ">Title</th>
                                <th data-en="Posted Date" data-am="የተለጠፈበት ቀን">Posted Date</th>
                                <th data-en="Expiry Date" data-am="የሚያበቃበት ቀን">Expiry Date</th>
                                <th data-en="Status" data-am="ሁኔታ">Status</th>
                                <th data-en="Action" data-am="እርምጃ">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notices as $n): ?>
                                <?php
                                $isExpired = (substr($n['expiry_date'], 0, 10) < $today);
                                $rowClass = $isExpired ? 'expired-row' : '';
                                ?>
                                <tr class="<?php echo $rowClass; ?>"
                                    data-expiry="<?php echo htmlspecialchars($n['expiry_date']); ?>">
                                    <td><?php echo htmlspecialchars($n['title']); ?></td>
                                    <td><?php echo substr($n['posted_date'], 0, 10); ?></td>
                                    <td><?php echo substr($n['expiry_date'], 0, 10); ?></td>
                                    <td>
                                        <!-- Status badge rendered by JS based on expiry_date -->
                                        <span class="notice-status-badge"></span>
                                        <div class="countdown-text notice-countdown"></div>
                                    </td>
                                    <td>
                                        <form method="POST" id="deleteForm_<?php echo $n['id']; ?>"
                                            class="delete-notice-form">
                                            <input type="hidden" name="notice_id" value="<?php echo $n['id']; ?>">
                                            <button type="button" class="btn-delete"
                                                onclick="confirmDelete(<?php echo $n['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                                <span data-en="Delete" data-am="ሰርዝ">Delete</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($notices)): ?>
                        <p data-en="No notices posted yet." data-am="እስካሁን ምንም ማስታወቂያ አልተለጠፈም።">No notices posted yet.</p>
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
        document.querySelectorAll('tr[data-expiry]').forEach(function (row) {
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