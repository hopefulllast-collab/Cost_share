<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
require_once '../../includes/encryption.php';
checkAuth(['registrar']);

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

// Fetch Registrar's own feedback
$my_feedbacks = $pdo->query("SELECT f.*, u.first_name, u.last_name, s.student_id as real_student_id 
                          FROM feedback f 
                          JOIN users u ON f.student_id = u.id 
                          LEFT JOIN students s ON u.id = s.user_id 
                          WHERE f.subject = 'Cost Sharing Issue'
                          ORDER BY f.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch System Performance feedbacks (Admin)
$system_feedbacks = $pdo->query("SELECT f.*, u.first_name, u.last_name, s.student_id as real_student_id 
                          FROM feedback f 
                          JOIN users u ON f.student_id = u.id 
                          LEFT JOIN students s ON u.id = s.user_id 
                          WHERE f.subject = 'System Performance'
                          ORDER BY f.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Tuition Rate feedbacks (Cost Pro)
$tuition_feedbacks = $pdo->query("SELECT f.*, u.first_name, u.last_name, s.student_id as real_student_id 
                          FROM feedback f 
                          JOIN users u ON f.student_id = u.id 
                          LEFT JOIN students s ON u.id = s.user_id 
                          WHERE f.subject = 'Tuition Rate'
                          ORDER BY f.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Other feedbacks
$other_misc_feedbacks = $pdo->query("SELECT f.*, u.first_name, u.last_name, s.student_id as real_student_id 
                          FROM feedback f 
                          JOIN users u ON f.student_id = u.id 
                          LEFT JOIN students s ON u.id = s.user_id 
                          WHERE f.subject = 'Other'
                          ORDER BY f.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="View Feedback - Registrar" data-am="የተማሪ ግብረመልስ - ሬጅስትራር">View Feedback - Registrar</title>
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
                    <h2 data-en="Student Feedback" data-am="ተማሪዎች ግንዛቤ">Student Feedback</h2>
                </div>

                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>

                <div class="card">
                    <?php if (empty($my_feedbacks)): ?>
                        <p data-en="No feedback received." data-am="ምንም ግንዛቤ አልተቀበለም።">No feedback received.</p>
                    <?php else: ?>
                        <?php foreach ($my_feedbacks as $fb): ?>
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

                <div class="top-bar" style="margin-top: 30px;">
                    <h2 data-en="System Performance Feedback (Admin)" data-am="የስርዓት አፈጻጸም ግንዛቤ (አድሚን)">System
                        Performance Feedback (Admin)</h2>
                </div>
                <div class="card">
                    <?php if (empty($system_feedbacks)): ?>
                        <p data-en="No system performance feedback received." data-am="ምንም የስርዓት አፈጻጸም ግንዛቤ አልተቀበለም።">No
                            system performance feedback received.</p>
                    <?php else: ?>
                        <?php foreach ($system_feedbacks as $fb): ?>
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
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="top-bar" style="margin-top: 30px;">
                    <h2 data-en="Tuition Rate Feedback (Cost Sharing Pro)" data-am="የትምህርት ተመን ግንዛቤ (የወጪ መጋራት ባለሙያ)">
                        Tuition Rate Feedback (Cost Sharing Pro)</h2>
                </div>
                <div class="card">
                    <?php if (empty($tuition_feedbacks)): ?>
                        <p data-en="No tuition rate feedback received." data-am="ምንም የትምህርት ተመን ግንዛቤ አልተቀበለም።">No tuition
                            rate feedback received.</p>
                    <?php else: ?>
                        <?php foreach ($tuition_feedbacks as $fb): ?>
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
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="top-bar" style="margin-top: 30px;">
                    <h2 data-en="Other Feedback" data-am="ልዩ ልዩ ግንዛቤ">Other Feedback</h2>
                </div>
                <div class="card">
                    <?php if (empty($other_misc_feedbacks)): ?>
                        <p data-en="No other feedback received." data-am="ምንም ሌላ ልዩ ልዩ ግንዛቤ አልተቀበለም።">No other feedback
                            received.</p>
                    <?php else: ?>
                        <?php foreach ($other_misc_feedbacks as $fb): ?>
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