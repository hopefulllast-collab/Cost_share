<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['student']);

// Fetch Student Data
$stmt = $pdo->prepare("SELECT s.*, d.name as department_name, u.first_name, u.middle_name, u.last_name 
                       FROM students s 
                       JOIN departments d ON s.department_id = d.id 
                       JOIN users u ON s.user_id = u.id
                       WHERE s.user_id = :uid");
$stmt->execute([':uid' => $_SESSION['user_id']]);
$student = $stmt->fetch();

require_once '../../includes/academic_translations.php';

// Compute academic year
$acYear = $student['academic_year'] ?? null;
if (empty($acYear)) {
    $now = new DateTime();
    $gcYear = (int) $now->format('Y');
    $gcMonth = (int) $now->format('n');
    $gcDay = (int) $now->format('j');
    if ($gcMonth < 9 || ($gcMonth == 9 && $gcDay < 11)) {
        $acYear = $gcYear - 8;
    } else {
        $acYear = $gcYear - 7;
    }
}

$dept_en = $student['department_name'] ?? 'N/A';
$dept_am = $academic_translations[$dept_en] ?? $dept_en;
$st = $student['status'] ?? 'Active';
$stAm = ($st === 'active' || $st === 'Active') ? 'ንቁ' : $st;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Student Dashboard - DMU" data-am="የተማሪ መረጃ - DMU">Student Dashboard - DMU</title>
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

                <div class="dash-content-grid">
                    <!-- Student Info -->
                    <div class="dash-section">
                        <h3 class="dash-section-title"><i class="fas fa-id-card"></i> <span data-en="Student Information" data-am="የተማሪ መረጃ">Student Information</span></h3>
                        <div class="dash-info-list">
                            <div class="dash-info-item">
                                <span class="info-label" data-en="Full Name" data-am="ሙሉ ስም">Full Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']); ?></span>
                            </div>
                            <div class="dash-info-item">
                                <span class="info-label" data-en="Student ID" data-am="የተማሪ መለያ">Student ID</span>
                                <span class="info-value"><?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="dash-info-item">
                                <span class="info-label" data-en="Department" data-am="ትምህርት ክፍል">Department</span>
                                <span class="info-value" data-en="<?php echo htmlspecialchars($dept_en); ?>" data-am="<?php echo htmlspecialchars($dept_am); ?>"><?php echo htmlspecialchars($dept_en); ?></span>
                            </div>
                            <div class="dash-info-item">
                                <span class="info-label" data-en="Year of Study" data-am="የጥናት ዓመት">Year of Study</span>
                                <span class="info-value"><?php echo htmlspecialchars($student['batch'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="dash-info-item">
                                <span class="info-label" data-en="Semester" data-am="ሴሚስተር">Semester</span>
                                <span class="info-value"><?php echo htmlspecialchars($student['current_semester'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="dash-info-item">
                                <span class="info-label" data-en="Academic Year" data-am="ትምህርት ዘመን">Academic Year</span>
                                <span class="info-value"><?php echo htmlspecialchars($acYear); ?></span>
                            </div>
                            <div class="dash-info-item">
                                <span class="info-label" data-en="Status" data-am="ሁኔታ">Status</span>
                                <span class="info-value">
                                    <span class="status-badge status-<?php echo strtolower($st); ?>" data-en="<?php echo $st; ?>" data-am="<?php echo $stAm; ?>"><?php echo $st; ?></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="dash-section">
                        <h3 class="dash-section-title"><i class="fas fa-bolt"></i> <span data-en="Quick Actions" data-am="ፈጣን ተግባራት">Quick Actions</span></h3>
                        <div class="dash-actions">
                            <a href="agreement_form.php" class="dash-action-link">
                                <i class="fas fa-edit"></i>
                                <span data-en="Fill Cost Share" data-am="የወጪ መጋራት ይሙሉ">Fill Cost Share</span>
                            </a>
                            <a href="history.php" class="dash-action-link">
                                <i class="fas fa-eye"></i>
                                <span data-en="View Cost Share" data-am="የወጪ መጋራት ይመልከቱ">View Cost Share</span>
                            </a>
                            <a href="request_document.php" class="dash-action-link">
                                <i class="fas fa-file-alt"></i>
                                <span data-en="Request Document" data-am="ሰነድ ይጠይቁ">Request Document</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>
