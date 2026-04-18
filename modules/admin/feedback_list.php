<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
require_once '../../includes/encryption.php';
// Allow Admin and Registrar to view feedback
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'registrar'])) {
    die("Access Denied");
}

// Mark as resolved if requested
if (isset($_GET['resolve'])) {
    $fid = $_GET['resolve'];
    $stmt = $pdo->prepare("UPDATE feedback SET status = 'Resolved' WHERE id = :id");
    $stmt->execute([':id' => $fid]);
}

$feedbacks = $pdo->query("SELECT f.*, u.first_name, u.last_name, u.username FROM feedback f 
                          JOIN users u ON f.student_id = u.id 
                          WHERE f.subject = 'System Performance'
                          ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="View Feedback - DMU" data-am="ግንዛቤ ማየት - DMU">View Feedback - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>

            <div class="main-content">
                <div class="top-bar">
                    <h2 data-en="Student Feedback" data-am="ተማሪዎች ግንዛቤ">Student Feedback</h2>
                    <a href="dashboard.php" class="btn-sm" data-en="Back" data-am="ተመለስ">Back</a>
                </div>

                <div class="card">
                    <table class="table-list">
                        <thead>
                            <tr>
                                <th data-en="Student" data-am="ተማሪ">Student</th>
                                <th data-en="Subject" data-am="የትምህርት አይነት">Subject</th>
                                <th data-en="Message" data-am="መልዕክት">Message</th>
                                <th data-en="Status" data-am="ሁኔታ">Status</th>
                                <th data-en="Action" data-am="ድርጊት">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedbacks as $f): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($f['first_name'] . ' ' . $f['last_name'] . ' (' . $f['username'] . ')'); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($f['subject']); ?>
                                    </td>
                                    <td>
                                        <?php $decrypted_msg = decryptData($f['message']); echo nl2br(htmlspecialchars(substr($decrypted_msg, 0, 100))) . (strlen($decrypted_msg) > 100 ? '...' : ''); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $f['status'];
                                        if ($status == 'Pending') {
                                            echo '<span data-en="Pending" data-am="በመጠባበቅ ላይ">Pending</span>';
                                        } elseif ($status == 'Resolved') {
                                            echo '<span data-en="Resolved" data-am="ተፈቷል">Resolved</span>';
                                        } else {
                                            echo $status;
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($f['status'] == 'Pending'): ?>
                                            <a href="?resolve=<?php echo $f['id']; ?>" class="btn-sm" data-en="Mark Resolved"
                                                data-am="ተፈቷል በሚል ምልክት ያድርጉ">Mark Resolved</a>
                                        <?php else: ?>
                                            <span class="text-muted" data-en="Closed" data-am="ተዘግቷል">Closed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>