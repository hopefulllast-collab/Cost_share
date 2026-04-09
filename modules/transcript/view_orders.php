<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['transcript_pro']);

// Fetch Orders specific to Transcript
// Type: transfer
$stmt = $pdo->query("SELECT * FROM official_transcript WHERE request_type = 'transfer' ORDER BY created_at DESC");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Department Names for display (optional, but good for ID mapping)
$depts = $pdo->query("SELECT id, name FROM departments")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="View Orders - Official Transcript" data-am="ትዕዛዞችን ይመልከቱ - ኦፊሴላዊ ትራንስክሪፕት">View Orders - Official
        Transcript</title>
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
                    <h2 data-en="Transferred In Student Orders" data-am="የተዛወሩ ተማሪዎች ትዕዛዞች">Transferred In Student
                        Orders</h2>
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
                                    <th data-en="Sex" data-am="ጾታ">Sex</th>
                                    <th data-en="Department" data-am="ትምህርት ክፍል">Department</th>
                                    <th data-en="Year/Sem" data-am="ዓመት/ሴሚስተር">Year/Sem</th>
                                    <th data-en="Cost Share" data-am="ወጪ መጋራት">Cost Share</th>
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
                                            <span data-en="<?php echo htmlspecialchars($order['sex']); ?>"
                                                data-am="<?php echo ($order['sex'] == 'Male') ? 'ወንድ' : 'ሴት'; ?>"><?php echo htmlspecialchars($order['sex']); ?></span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($depts[$order['department_id']] ?? $order['department_id']); ?>
                                        </td>
                                        <td>
                                            <?php echo "Batch " . ($order['batch_year'] ?? $order['batch'] ?? '?') . " / Sem " . $order['semester']; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($order['cost_share_amount']); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_map = [
                                                'completed' => 'ተጠናቀቀ',
                                                'pending' => 'በመጠባበቅ ላይ',
                                                'rejected' => 'ተቀባይነት አላገኘም'
                                            ];
                                            $st_lower = strtolower($order['status']);
                                            $st_am = $status_map[$st_lower] ?? $order['status'];
                                            ?>
                                            <span class="status-badge status-<?php echo $st_lower; ?>"
                                                data-en="<?php echo ucfirst($order['status']); ?>"
                                                data-am="<?php echo $st_am; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
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