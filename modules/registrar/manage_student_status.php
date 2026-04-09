<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['registrar']);

require_once '../../includes/academic_translations.php';

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$student = null;
// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

// Handle Search
if (isset($_GET['search_id'])) {
    $search_id = trim($_GET['search_id']);
    $stmt = $pdo->prepare("SELECT s.*, u.first_name, u.middle_name, u.last_name, d.name as dept_name 
                           FROM students s 
                           JOIN users u ON s.user_id = u.id 
                           JOIN departments d ON s.department_id = d.id 
                           WHERE s.student_id = ?");
    $stmt->execute([$search_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        $error = "<span data-en='Student not found with ID: ' data-am='በዚህ መታወቂያ ተማሪ አልተገኘም: '>Student not found with ID: </span>" . htmlspecialchars($search_id);
    }
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $student_id = $_POST['student_db_id'];
    $new_status = $_POST['status'];

    if ($new_status) {
        try {
            $stmt = $pdo->prepare("UPDATE students SET status = ?, status_updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $student_id]);
            $_SESSION["flash_success"] = "<span data-en='Student status updated to ' data-am='የተማሪ ሁኔታ ተዘምኗል ወደ '>Student status updated to </span>" . htmlspecialchars($new_status);
            header("Location: " . $_SERVER["PHP_SELF"]);
            exit();
        } catch (PDOException $e) {
            $error = "<span data-en='Error updating status: ' data-am='ሁኔታን በማዘመን ላይ ስህተት: '>Error updating status: </span>" . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Manage Student Status - Registrar" data-am="የተማሪ ሁኔታን ያስተዳድሩ - ሬጅስትራር">Manage Student Status - Registrar</title>
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
                    <h2 data-en="Manage Student Status" data-am="የተማሪ ሁኔታን ያስተዳድሩ">Manage Student Status</h2>
                </div>

                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>

                <div class="card">
                    <h3 data-en="Search Student" data-am="ተማሪ ይፈልጉ">Search Student</h3>
                    <form method="GET" style="display:flex; gap:10px;">
                        <input type="text" name="search_id"
                            value="<?php echo isset($_GET['search_id']) ? htmlspecialchars($_GET['search_id']) : ''; ?>"
                            placeholder="Enter Student ID (e.g. DMU/1234/14)"
                            data-en="Enter Student ID (e.g. DMU/1234/14)"
                            data-en-placeholder="Enter Student ID (e.g. DMU/1234/14)"
                            data-am-placeholder="የተማሪ መታወቂያ ያስገቡ (ለምሳሌ DMU/1234/14)" style="flex:1;">
                        <button type="submit" class="btn-primary" data-en="Search" data-am="ፈልግ">Search</button>
                    </form>
                </div>

                <?php if ($student): ?>
                    <div class="card mt-20">
                        <h3 data-en="Student Details" data-am="የተማሪ ዝርዝሮች">Student Details</h3>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                            <div>
                                <p><strong data-en="Name:" data-am="ስም:">Name:</strong>
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']); ?>
                                </p>
                                <p><strong data-en="ID:" data-am="መታወቂያ:">ID:</strong>
                                    <?php echo htmlspecialchars($student['student_id']); ?>
                                </p>
                                <p><strong data-en="Department:" data-am="የትምህርት ክፍል:">Department:</strong>
                                    <?php 
                                    $dept_en = $student['dept_name'];
                                    $dept_am = $academic_translations[$dept_en] ?? $dept_en;
                                    ?>
                                    <span data-en="<?php echo htmlspecialchars($dept_en); ?>" data-am="<?php echo htmlspecialchars($dept_am); ?>">
                                        <?php echo htmlspecialchars($dept_en); ?>
                                    </span>
                                </p>
                            </div>
                            <div>
                                <p><strong data-en="Batch:" data-am="ባች:">Batch:</strong>
                                    <?php echo htmlspecialchars($student['batch']); ?>
                                </p>
                                <p><strong data-en="Semester:" data-am="ሴሚስተር:">Semester:</strong>
                                    <?php echo htmlspecialchars($student['current_semester']); ?>
                                </p>
                                <p><strong data-en="Current Status:" data-am="ያለበት ሁኔታ:">Current Status:</strong>
                                    <span
                                        class="status-badge status-<?php echo strtolower($student['status'] ?? 'active'); ?>">
                                        <?php 
                                        $status_en = $student['status'] ?? 'Active';
                                        $status_map = [
                                            'Active' => 'ንቁ',
                                            'Withdrawal' => 'ያቋረጠ (Withdrawal)',
                                            'Dropout' => 'ያቋረጠ (Dropout)',
                                            'Complete Dismissal' => 'ሙሉ ለሙሉ የተሰናበተ',
                                            'Dismissal with Readmission' => 'መመለስ የሚቻል',
                                            'Death' => 'ሞት'
                                        ];
                                        $status_am = $status_map[$status_en] ?? $status_en;
                                        ?>
                                        <span data-en="<?php echo htmlspecialchars($status_en); ?>" data-am="<?php echo htmlspecialchars($status_am); ?>">
                                            <?php echo htmlspecialchars($status_en); ?>
                                        </span>
                                    </span>
                                </p>
                            </div>
                        </div>

                        <hr>

                        <h4 class="mt-20" data-en="Update Status" data-am="ሁኔታን ያዘምኑ">Update Status</h4>
                        <form method="POST">
                            <input type="hidden" name="student_db_id" value="<?php echo $student['id']; ?>">
                            <div class="form-group">
                                <label data-en="New Status" data-am="አዲስ ሁኔታ">New Status</label>
                                <select name="status" required>
                                    <option value="Active" <?php echo ($student['status'] == 'Active') ? 'selected' : ''; ?>
                                        data-en="Active" data-am="ንቁ">Active</option>
                                    <option value="Withdrawal" <?php echo ($student['status'] == 'Withdrawal') ? 'selected' : ''; ?>
                                        data-en="Withdrawal" data-am="ያቋረጠ (Withdrawal)">Withdrawal</option>
                                    <option value="Dropout" <?php echo ($student['status'] == 'Dropout') ? 'selected' : ''; ?>
                                        data-en="Dropout" data-am="ያቋረጠ (Dropout)">Dropout</option>
                                    <option value="Complete Dismissal" <?php echo ($student['status'] == 'Complete Dismissal') ? 'selected' : ''; ?>
                                        data-en="Complete Dismissal" data-am="ሙሉ ለሙሉ የተሰናበተ">Complete Dismissal</option>
                                    <option value="Dismissal with Readmission" <?php echo ($student['status'] == 'Dismissal with Readmission') ? 'selected' : ''; ?>
                                        data-en="Dismissal with Readmission" data-am="መመለስ የሚቻል">Dismissal with Readmission</option>
                                    <option value="Death" <?php echo ($student['status'] == 'Death') ? 'selected' : ''; ?>
                                        data-en="Death" data-am="ሞት">Death</option>
                                </select>
                            </div>
                            <button type="button" id="updateStatusBtn" class="btn-primary" data-en="Update Status"
                                data-am="ሁኔታን አዘምን"
                                onclick="document.getElementById('statusConfirm').style.display='block'; this.style.display='none';">Update
                                Status</button>
                            <div id="statusConfirm"
                                style="display:none; margin-top:10px; padding:15px; background:#fff3cd; border:1px solid #ffc107; border-radius:5px;">
                                <p style="margin:0 0 10px; font-weight:bold; color:#856404;"
                                    data-en="Are you sure you want to change the status?"
                                    data-am="ሁኔታውን መቀየር እንደሚፈልጉ እርግጠኛ ነዎት?">Are you sure you want to change the status?</p>
                                <button type="submit" name="update_status" class="btn-primary" style="margin-right:10px;"
                                    data-en="Update" data-am="አዘምን">Update</button>
                                <button type="button" class="btn-secondary"
                                    onclick="document.getElementById('statusConfirm').style.display='none'; document.getElementById('updateStatusBtn').style.display='inline-block';"
                                    data-en="Cancel" data-am="ሰርዝ">Cancel</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>