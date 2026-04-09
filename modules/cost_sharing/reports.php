<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['cost_sharing_pro', 'registrar', 'department_head']); // Shared report
require_once '../../includes/academic_translations.php';

$agreements = [];
if (isset($_GET['report_type'])) {
    if ($_GET['report_type'] == 'agreements') {
        $stmt = $pdo->prepare("SELECT csa.*, u.first_name, u.last_name, s.student_id as sid, d.name as dname 
                               FROM cost_sharing_agreements csa
                               JOIN students s ON csa.student_id = s.user_id
                               JOIN users u ON s.user_id = u.id
                               JOIN departments d ON s.department_id = d.id
                               WHERE csa.status = 'ApprovedByCostPro'");
        $stmt->execute();
        $agreements = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Reports - DMU" data-am="ሪፖርቶች - ዲ.ማ.ዩ">Reports - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>

            <div class="main-content">
                <div class="top-bar">
                    <h2 data-en="System Reports" data-am="የስርዓት ሪፖርቶች">System Reports</h2>
                    <a href="dashboard.php" class="btn-sm" data-en="Back" data-am="ተመለስ">Back</a>
                </div>

                <div class="card">
                    <form method="GET" style="display:flex; gap:10px;">
                        <select name="report_type">
                            <option value="agreements" data-en="Signed Agreements" data-am="የተፈረሙ ስምምነቶች">Signed
                                Agreements</option>
                            <option value="active_students" data-en="Active Students" data-am="ንቁ ተማሪዎች">Active Students
                            </option>
                        </select>
                        <button type="submit" class="btn-primary" data-en="Generate" data-am="አመንጭ">Generate</button>
                    </form>
                </div>

                <?php if (!empty($agreements)): ?>
                    <div class="card mt-20">
                        <h3 data-en="Signed Cost Sharing Agreements" data-am="የተፈረሙ የወጪ ክፍፍል ስምምነቶች">Signed Cost Sharing
                            Agreements</h3>
                        <table class="table-list">
                            <thead>
                                <tr>
                                    <th data-en="Student" data-am="ተማሪ">Student</th>
                                    <th data-en="ID" data-am="መለያ">ID</th>
                                    <th data-en="Dept" data-am="ክፍል">Dept</th>
                                    <th data-en="Year" data-am="ዓመት">Year</th>
                                    <th data-en="Sem" data-am="ሴሚስተር">Sem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agreements as $a): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($a['sid']); ?>
                                        </td>
                                        <td>
                                            <?php $dept_am = $academic_translations[$a['dname']] ?? $a['dname']; ?>
                                            <span data-en="<?php echo htmlspecialchars($a['dname']); ?>"
                                                data-am="<?php echo htmlspecialchars($dept_am); ?>"><?php echo htmlspecialchars($a['dname']); ?></span>
                                        </td>
                                        <td>
                                            <?php echo $a['academic_year']; ?>
                                        </td>
                                        <td>
                                            <?php echo $a['semester']; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <script src="../../assets/js/bilingual.js"></script>
</body>

</html>