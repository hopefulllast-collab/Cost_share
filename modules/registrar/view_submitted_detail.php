<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['registrar']);

if (!isset($_GET['rate_id'])) {
    header("Location: dashboard.php");
    exit();
}

$rate_id = (int) $_GET['rate_id'];

// Fetch Rate Info
$stmt = $pdo->prepare("SELECT r.*, d.name as dept_name 
                       FROM courses r 
                       JOIN departments d ON r.department_id = d.id 
                       WHERE r.id = ?");
$stmt->execute([$rate_id]);
$rate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rate) {
    die("Invalid Rate ID");
}

$dept_id = $rate['department_id'];
$batch = $rate['batch'];
$semester = $rate['semester'];
$submitted_total = $rate['credit_hours'];

// Fetch Students
$std_stmt = $pdo->prepare("SELECT s.user_id, s.student_id, u.first_name, u.middle_name, u.last_name 
                           FROM students s 
                           JOIN users u ON s.user_id = u.id 
                           WHERE s.department_id = ? AND s.batch = ? AND s.current_semester = ? 
                           ORDER BY u.first_name");
$std_stmt->execute([$dept_id, $batch, $semester]);
$students = $std_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Special Cases
$sc_stmt = $pdo->prepare("SELECT user_id as student_id, adjusted_credit_hours FROM students WHERE department_id = ? AND batch = ? AND current_semester = ? AND adjusted_credit_hours IS NOT NULL");
$sc_stmt->execute([$dept_id, $batch, $semester]);
$special_cases = $sc_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Calculate Standard Total from Courses (for verification)
$calc_stmt = $pdo->prepare("SELECT SUM(credit_hour) FROM courses WHERE department_id = ? AND batch = ? AND semester = ?");
$calc_stmt->execute([$dept_id, $batch, $semester]);
$standard_total = $calc_stmt->fetchColumn() ?: 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="View Credit Hour Detail - Registrar" data-am="የክሬዲት ሰዓት ዝርዝር ይመልከቱ - ሬጅስትራር">View Credit Hour Detail
        - Registrar</title>
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
                    <h2 data-en="Credit Hour Details" data-am="የክሬዲት ሰዓት ዝርዝሮች">Credit Hour Details</h2>
                </div>

                <div style="margin-bottom:20px;">
                    <a href="dashboard.php" class="btn-secondary"
                        style="background:#6c757d; color:white; text-decoration:none; padding:8px 15px; border-radius:4px;">
                        <i class="fas fa-arrow-left"></i> <span data-en="Back" data-am="ተመለስ">Back</span>
                    </a>
                </div>

                <div class="card mb-20">
                    <h3>
                        <?php echo htmlspecialchars($rate['dept_name']); ?>
                    </h3>
                    <p>
                        <strong data-en="Batch:" data-am="ባች:">Batch:</strong>
                        <?php echo $batch; ?> |
                        <strong data-en="Semester:" data-am="ሴሚስተር:">Semester:</strong>
                        <?php echo $semester; ?>
                    </p>
                    <p>
                        <strong data-en="Submitted Standard Total:" data-am="የገባው መደበኛ ጠቅላላ:">Submitted Standard
                            Total:</strong>
                        <?php echo $submitted_total; ?>
                        <?php if ($submitted_total != $standard_total): ?>
                            <span style="color:red;">(<span data-en="Mismatch with current courses:"
                                    data-am="ከኮርሶች ጋር አለመመጣጠን:">Mismatch with current courses:</span>
                                <?php echo $standard_total; ?>)
                            </span>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="card">
                    <h3 data-en="Student List" data-am="የተማሪ ዝርዝር">Student List</h3>
                    <?php if (count($students) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th data-en="Student Name" data-am="የተማሪ ስም">Student Name</th>
                                    <th data-en="ID" data-am="መታወቂያ">ID</th>
                                    <th data-en="Effective Credits" data-am="ውጤታማ ክሬዲቶች">Effective Credit Hour</th>
                                    <th data-en="Status" data-am="ሁኔታ">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $i => $s): ?>
                                    <?php
                                    $ch = $standard_total; // Default to standard
                                    $note = "<span data-en='Standard' data-am='መደበኛ'>Standard</span>";
                                    $style = "";

                                    if (isset($special_cases[$s['user_id']])) {
                                        $ch = $special_cases[$s['user_id']];
                                        $note = "<strong data-en='Special Case' data-am='ልዩ ሁኔታ'>Special Case</strong>";
                                        $style = "background:#fff3cd;";
                                    }
                                    ?>
                                    <tr style="<?php echo $style; ?>">
                                        <td>
                                            <?php echo $i + 1; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['middle_name'] . ' ' . $s['last_name']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($s['student_id']); ?>
                                        </td>
                                        <td><strong>
                                                <?php echo $ch; ?>
                                            </strong></td>
                                        <td>
                                            <?php echo $note; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p data-en="No students found." data-am="ምንም ተማሪዎች አልተገኙም።">No students found.</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</body>

</html>