<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['department_head']);

// Fetch Dept Head's Department
$stmt = $pdo->prepare("SELECT id as department_id, name as dept_name FROM departments WHERE head_user_id = :uid");
$stmt->execute([':uid' => $_SESSION['user_id']]);
$deptHead = $stmt->fetch();
if (!$deptHead) {
    die("<div style='padding:40px; text-align:center; font-family:sans-serif;'>
        <h2 style='color:#e74c3c;'>⚠️ Department Not Assigned</h2>
        <p>Your account (User ID: " . $_SESSION['user_id'] . ") is not assigned as head of any department.</p>
    </div>");
}
$deptId = $deptHead['department_id'];

// Stats
$studentCount = $pdo->prepare("SELECT COUNT(*) FROM students WHERE department_id = ?");
$studentCount->execute([$deptId]);
$studentCount = $studentCount->fetchColumn();

// Fetch Pending Agreements (SignedByStudent) for this Department
$stmt = $pdo->prepare("SELECT csa.*, s.student_id as student_code, u.first_name, u.last_name 
                       FROM cost_sharing_agreements csa
                       JOIN students s ON csa.student_id = s.user_id
                       JOIN users u ON s.user_id = u.id
                       WHERE s.department_id = :did AND csa.status = 'SignedByStudent'");
$stmt->execute([':did' => $deptId]);
$pendingAgreements = $stmt->fetchAll();
$pendingCount = count($pendingAgreements);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Dept Head Dashboard - DMU" data-am="የዲፓርትመንት ኃላፊ ዳሽቦርድ - DMU">Dept Head Dashboard - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>

            <div class="main-content">
                <?php include '../../includes/welcome_banner.php'; ?>

                <!-- Stats -->
                <div class="dash-stats">
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon blue"><i class="fas fa-user-graduate"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Dept Students" data-am="የክፍል ተማሪዎች">Dept Students</h4>
                            <div class="dash-stat-value"><?php echo $studentCount; ?></div>
                        </div>
                    </div>
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon amber"><i class="fas fa-clock"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Pending Approvals" data-am="በመጠባበቅ ላይ">Pending Approvals</h4>
                            <div class="dash-stat-value"><?php echo $pendingCount; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Pending Agreements Table -->
                <div class="dash-section">
                    <h3 class="dash-section-title"><i class="fas fa-file-contract"></i> <span data-en="Pending Agreements" data-am="በመጠባበቅ ላይ ያሉ ውሎች">Pending Agreements</span></h3>
                    <?php if ($pendingCount > 0): ?>
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
                                        <td><?php echo htmlspecialchars($pa['first_name'] . ' ' . $pa['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($pa['student_code']); ?></td>
                                        <td><?php echo htmlspecialchars($pa['agreement_date']); ?></td>
                                        <td>
                                            <a href="approve_agreement.php?id=<?php echo $pa['id']; ?>" class="btn-sm btn-primary"
                                                data-en="View & Approve" data-am="ይመልከቱ እና ያረጋግጡ" style="font-size:0.8rem;">View & Approve</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align:center; padding:30px; color:#94a3b8;">
                            <i class="fas fa-check-circle" style="font-size:2rem; margin-bottom:10px; display:block; color:#10b981;"></i>
                            <p data-en="All agreements are up to date!" data-am="ሁሉም ውሎች ወቅታዊ ናቸው!">All agreements are up to date!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>