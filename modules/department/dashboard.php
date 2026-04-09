<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['department_head']);

// Fetch Dept Head's Department
$stmt = $pdo->prepare("SELECT id as department_id FROM departments WHERE head_user_id = :uid");
$stmt->execute([':uid' => $_SESSION['user_id']]);
$deptHead = $stmt->fetch();
if (!$deptHead) {
    die("<div style='padding:40px; text-align:center; font-family:sans-serif;'>
        <h2 style='color:#e74c3c;'>⚠️ Department Not Assigned</h2>
        <p>Your account (User ID: " . $_SESSION['user_id'] . ") is not assigned as head of any department.</p>
        <p>Please ask the Registrar or Admin to assign you via:<br><code>UPDATE departments SET head_user_id = " . $_SESSION['user_id'] . " WHERE id = [DEPT_ID];</code></p>
    </div>");
}
$deptId = $deptHead['department_id'];

// Fetch Pending Agreements (SignedByStudent) for this Department
$stmt = $pdo->prepare("SELECT csa.*, s.student_id as student_code, u.first_name, u.last_name 
                       FROM cost_sharing_agreements csa
                       JOIN students s ON csa.student_id = s.user_id
                       JOIN users u ON s.user_id = u.id
                       WHERE s.department_id = :did AND csa.status = 'SignedByStudent'");
$stmt->execute([':did' => $deptId]);
$pendingAgreements = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Dept Head Dashboard - Cost Sharing Pro" data-am="የዲፓርትመንት ኃላፊ ዳሽቦርድ - የወጪ መጋራት ባለሙያ">Dept Head
        Dashboard - Cost Sharing Pro</title>
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
                    <h2 data-en="Department Head Dashboard" data-am="የዲፓርትመንት ተጠሪ ዳሽቦርድ">Department Head Dashboard</h2>
                </div>

                <div class="card">
                    <h3 data-en="Pending Agreements" data-am="በመጠባበቅ ላይ ያሉ ውሎች">Pending Agreements</h3>
                    <?php if (count($pendingAgreements) > 0): ?>
                        <table class="table-list">
                            <thead>
                                <tr>
                                    <th data-en="Student Name" data-am="የተማሪ ስም">Student Name</th>
                                    <th data-en="ID" data-am="መለያ">ID</th>
                                    <th data-en="Date" data-am="ቀን">Date</th>
                                    <th data-en="Action" data-am="ተግባር">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingAgreements as $pa): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($pa['first_name'] . ' ' . $pa['last_name']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($pa['student_code']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($pa['agreement_date']); ?>
                                        </td>
                                        <td>
                                            <a href="approve_agreement.php?id=<?php echo $pa['id']; ?>" class="btn-sm"
                                                data-en="View & Approve" data-am="ይመልከቱ እና ያረጋግጡ">View & Approve</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p data-en="No pending agreements." data-am="ምንም በመጠባበቅ ላይ ያሉ ውሎች የሉም።">No pending agreements.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>