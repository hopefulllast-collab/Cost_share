<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['cost_sharing_pro']);

// Fetch Orders specific to Cost Sharing
// Types: withdrawal, dropout, ethics, death
$stmt = $pdo->query("SELECT * FROM official_transcript WHERE request_type IN ('withdrawal', 'dropout', 'ethics', 'death') ORDER BY created_at DESC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="View Orders - Cost Sharing" data-am="ትዕዛዞችን ይመልከቱ - ወጪ መጋራት">View Orders - Cost Sharing</title>
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
                    <h2 data-en="Subtrahend Cost Share Orders" data-am="የወጪ መጋራት ትዕዛዞች">Subtrahend Cost Share Orders
                    </h2>
                </div>

                <div class="card">
                    <?php if (empty($orders)): ?>
                        <p data-en="No orders found." data-am="ምንም ትዕዛዞች አልተገኙም።">No orders found.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th data-en="Date" data-am="ቀን">Date</th>
                                    <th data-en="Student ID" data-am="የተማሪ መለያ">Student ID</th>
                                    <th data-en="Name" data-am="ስም">Name</th>
                                    <th data-en="Type/Reason" data-am="ዓይነት/ምክንያት">Type/Reason</th>
                                    <th data-en="Description" data-am="መግለጫ">Description</th>
                                    <th data-en="Status" data-am="ሁኔታ">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <?php echo date('Y-m-d', strtotime($order['created_at'])); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($order['student_id']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['middle_name'] . ' ' . $order['last_name']); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $type_map = [
                                                'withdrawal' => 'ማቋረጥ',
                                                'dropout' => 'ማቋረጥ',
                                                'ethics' => 'ስነምግባር',
                                                'death' => 'ሞት'
                                            ];
                                            $type_key = strtolower($order['request_type']);
                                            $type_am = $type_map[$type_key] ?? $order['request_type'];
                                            ?>
                                            <span data-en="<?php echo ucfirst($order['request_type']); ?>"
                                                data-am="<?php echo $type_am; ?>">
                                                <?php echo ucfirst($order['request_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($order['description']); ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php
                                                $status_map = [
                                                    'pending' => 'በመጠባበቅ ላይ',
                                                    'completed' => 'ተጠናቀቀ'
                                                ];
                                                $status_key = strtolower($order['status']);
                                                $status_am = $status_map[$status_key] ?? $order['status'];
                                                ?>
                                                <span data-en="<?php echo ucfirst($order['status']); ?>"
                                                    data-am="<?php echo $status_am; ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </span>
                                            <!-- Future: Add Approve/Seen Action -->
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>