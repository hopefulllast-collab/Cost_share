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

                <div class="card-grid">
                    <div class="card info-card">
                        <h3 data-en="Student Info" data-am="የተማሪ መረጃ">Student Info</h3>
                        <p><strong data-en="Name:" data-am="ስም:">Name:</strong>
                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']); ?>
                        </p>
                        <p><strong data-en="ID:" data-am="መለያ:">ID:</strong>
                            <?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></p>
                        <p><strong data-en="Dept:" data-am="ትምህርት ክፍል:">Dept:</strong>
                            <?php
                            $dept_en = $student['department_name'] ?? 'N/A';
                            $dept_am = $academic_translations[$dept_en] ?? $dept_en;
                            ?>
                            <span data-en="<?php echo htmlspecialchars($dept_en); ?>"
                                data-am="<?php echo htmlspecialchars($dept_am); ?>">
                                <?php echo htmlspecialchars($dept_en); ?>
                            </span>
                        </p>
                        <p><strong data-en="Year of Study:" data-am="የጥናት ዓመት:">Year of Study:</strong>
                            <?php echo htmlspecialchars($student['batch'] ?? 'N/A'); ?></p>
                        <p><strong data-en="Semester:" data-am="ሴሚስተር:">Semester:</strong>
                            <?php echo htmlspecialchars($student['current_semester'] ?? 'N/A'); ?></p>
                        <p><strong data-en="Academic Year:" data-am="የትምህርት ዘመን:">Academic Year:</strong>
                            <?php
                            $acYear = $student['academic_year'] ?? null;
                            if (empty($acYear)) {
                                // Compute current Ethiopian Calendar year
                                // Ethiopian New Year (Meskerem 1) falls around September 11-12 in Gregorian
                                $now = new DateTime();
                                $gcYear = (int) $now->format('Y');
                                $gcMonth = (int) $now->format('n');
                                $gcDay = (int) $now->format('j');
                                // Before ~Sep 11: Ethiopian year = GC year - 8
                                // On/After ~Sep 11: Ethiopian year = GC year - 7
                                if ($gcMonth < 9 || ($gcMonth == 9 && $gcDay < 11)) {
                                    $acYear = $gcYear - 8;
                                } else {
                                    $acYear = $gcYear - 7;
                                }
                            }
                            echo htmlspecialchars($acYear);
                            ?>
                        </p>
                        <p><strong data-en="Status:" data-am="ሁኔታ:">Status:</strong>
                            <?php
                            $st = $student['status'] ?? 'Active';
                            $stEn = $st;
                            $stAm = ($st === 'active' || $st === 'Active') ? 'ንቁ' : $st;
                            ?>
                            <span class="status-badge status-<?php echo strtolower($st); ?>"
                                data-en="<?php echo $stEn; ?>" data-am="<?php echo $stAm; ?>">
                                <?php echo $st; ?>
                            </span>
                        </p>
                    </div>

                    <div class="card action-card">
                        <h3 data-en="Quick Actions" data-am="ፈጣን ተግባራት">Quick Actions</h3>
                        <a href="agreement_form.php" class="btn-action" data-en="Sign Agreement" data-am="ውል ይፈርሙ">Sign
                            Agreement</a>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>