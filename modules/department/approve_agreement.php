<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['department_head']);

// Helper to get Dept ID
$stmt = $pdo->prepare("SELECT id as department_id FROM departments WHERE head_user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$deptInfo = $stmt->fetch();
$myDeptId = $deptInfo['department_id'] ?? 0;

// Fetch Pending Agreements Grouped
$stmt = $pdo->prepare("SELECT csa.academic_year, csa.semester, count(*) as count 
                       FROM cost_sharing_agreements csa
                       JOIN students s ON csa.student_id = s.user_id
                       WHERE s.department_id = :did AND csa.status = 'SignedByStudent'
                       GROUP BY csa.academic_year, csa.semester");
$stmt->execute([':did' => $myDeptId]);
$agreements = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Approve Agreements - Cost Sharing Pro" data-am="ስምምነቶችን አጽድቅ - የወጪ መጋራት ባለሙያ">Approve Agreements -
        Cost Sharing Pro</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>

            <div class="main-content">
                <div class="card">
                    <h3 data-en="Approve Cost Share Agreements (Grouped by Batch)"
                        data-am="የወጪ መጋራት ስምምነቶችን ያጽድቁ (በቡድን)">Approve Cost Share Agreements
                        (Grouped by Batch)</h3>
                    <?php if (count($agreements) > 0): ?>
                        <table class="table-list">
                            <thead>
                                <tr>
                                    <th data-en="Academic Year" data-am="የትምህርት ዘመን">Academic Year</th>
                                    <th data-en="Semester" data-am="ሴሚስተር">Semester</th>
                                    <th data-en="Pending Students" data-am="በመጠባበቅ ላይ ያሉ ተማሪዎች">Pending Students</th>
                                    <th data-en="Action" data-am="ተግባር">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agreements as $ag): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($ag['academic_year']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($ag['semester']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($ag['count']); ?>
                                        </td>
                                        <td>
                                            <a href="approve_agreement_list.php?year=<?php echo urlencode($ag['academic_year']); ?>&sem=<?php echo urlencode($ag['semester']); ?>"
                                                class="btn-primary" style="padding: 5px 10px; font-size: 14px;">
                                                <span data-en="View & Approve" data-am="ይመልከቱ እና ያጽድቁ">View & Approve</span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p data-en="No pending agreements found." data-am="ምንም በመጠባበቅ ላይ ያሉ ስምምነቶች አልተገኙም።">No pending
                            agreements found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>