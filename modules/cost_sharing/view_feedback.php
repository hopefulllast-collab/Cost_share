<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
require_once '../../includes/encryption.php';
checkAuth(['cost_sharing_pro']);

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

// Handle Resolve
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $feedback_id = $_POST['feedback_id'];
    $stmt = $pdo->prepare("UPDATE feedback SET status = 'Resolved' WHERE id = ?");
    $stmt->execute([$feedback_id]);
    $_SESSION["flash_success"] = "<span data-en='Feedback marked as resolved.' data-am='መልዕክቱ ተፈቷል ተብሎ ተመዝግቧል።'>Feedback marked as resolved.</span>";
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit();
}

// Fetch only Tuition Rate feedback for Cost Sharing Pro
$feedbacks = $pdo->query("SELECT f.*, u.first_name, u.last_name, s.student_id as real_student_id 
                          FROM feedback f 
                          JOIN users u ON f.student_id = u.id 
                          LEFT JOIN students s ON u.id = s.user_id 
                          WHERE f.subject = 'Tuition Rate'
                          ORDER BY f.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="View Feedback - Cost Sharing Pro" data-am="ግብረመልስ ይመልከቱ - የወጪ መጋራት ባለሙያ">View Feedback - Cost Sharing Pro</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .feedback-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .badge-resolved {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
        }

        .badge-pending {
            background: #fff3e0;
            color: #ef6c00;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
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
                    <h2 data-en="Student Feedback (Tuition Rate)" data-am="ተማሪዎች ግንዛቤ (የትምህርት ተመን)">Student Feedback (Tuition Rate)</h2>
                </div>

                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>

                <div class="card">
                    <?php if (empty($feedbacks)): ?>
                        <p data-en="No tuition rate feedback received." data-am="ምንም የትምህርት ተመን ግንዛቤ አልተቀበለም።">No tuition rate feedback received.</p>
                    <?php else: ?>
                        <?php foreach ($feedbacks as $fb): ?>
                            <div class="feedback-item">
                                <div class="feedback-header">
                                    <strong>
                                        <?php echo htmlspecialchars($fb['first_name'] . ' ' . $fb['last_name']); ?> (
                                        <?php echo htmlspecialchars($fb['real_student_id']); ?>)
                                    </strong>
                                    <span class="text-sm text-gray">
                                        <?php echo $fb['created_at']; ?>
                                    </span>
                                </div>
                                <div style="margin-bottom: 5px;">
                                    <span style="font-weight:bold;">
                                        <?php echo htmlspecialchars($fb['subject']); ?>
                                    </span>
                                    <span
                                        class="<?php echo $fb['status'] == 'Resolved' ? 'badge-resolved' : 'badge-pending'; ?>"
                                        data-en="<?php echo $fb['status'] ?? 'Pending'; ?>"
                                        data-am="<?php echo ($fb['status'] == 'Resolved') ? 'ተፈቷል' : 'በመጠባበቅ ላይ'; ?>">
                                        <?php echo $fb['status'] ?? 'Pending'; ?>
                                    </span>
                                </div>
                                <p>
                                    <?php echo nl2br(htmlspecialchars(decryptData($fb['message']))); ?>
                                </p>



                                <?php if ($fb['status'] != 'Resolved'): ?>
                                    <div style="margin-top: 10px;">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="feedback_id" value="<?php echo $fb['id']; ?>">
                                            <button type="submit" class="btn-secondary btn-sm" data-en="Mark Resolved"
                                                data-am="ተፈቷል">Mark Resolved</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>
