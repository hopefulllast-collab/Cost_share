<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['department_head']);

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

$user_id = $_SESSION['user_id'];

// Get Dept ID
$stmt = $pdo->prepare("SELECT id as department_id FROM departments WHERE head_user_id = ?");
$stmt->execute([$user_id]);
$dept_head = $stmt->fetch(PDO::FETCH_ASSOC);
$dept_id = $dept_head['department_id'];

// Get department name
$dept_name_stmt = $pdo->prepare("SELECT name, study_years FROM departments WHERE id = ?");
$dept_name_stmt->execute([$dept_id]);
$dept_info = $dept_name_stmt->fetch(PDO::FETCH_ASSOC);
$dept_name = $dept_info['name'];
$study_years = $dept_info['study_years'] ?? 4;

// Get selected batch/semester from GET
$sel_batch = isset($_GET['batch']) ? (int) $_GET['batch'] : 0;
$sel_semester = isset($_GET['semester']) ? (int) $_GET['semester'] : 0;

// --- Handle Add Course ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_course'])) {
    $course_name = trim($_POST['course_name']);
    $credit_hour = (int) $_POST['credit_hour'];
    $sel_batch = (int) $_POST['batch'];
    $sel_semester = (int) $_POST['semester'];

    // Fetch registrar min/max for validation
    $reg_check = $pdo->prepare("SELECT min_credit_hours, max_credit_hours, credit_hours, semester_min_credits, semester_max_credits FROM courses WHERE department_id = ? AND batch = ? AND semester = ?");
    $reg_check->execute([$dept_id, $sel_batch, $sel_semester]);
    $reg_limits = $reg_check->fetch(PDO::FETCH_ASSOC);

    $min_ch = $reg_limits ? (int) $reg_limits['min_credit_hours'] : 1;
    $max_ch = $reg_limits ? (int) $reg_limits['max_credit_hours'] : 10;

    // Check if adding this course exceeds semester max (optional hard block? User said "control", so maybe warning is enough, or hard block. I'll stick to per-course limit hard block, and total limit warning as before, or upgrade to hard block if implied.
    // User: "systemu yedepartment headun input endikotater" -> Control.
    // I will stick to course min/max hard block. (already done).
    // For semester total, usually it's a sum. It's hard to block "adding a course" if the sum isn't reached yet.
    // But if sum exceeds max? I'll probably just show the warning for now, as stopping them from adding might be annoying if they need to juggle.
    // Actually, "control" might mean "don't allow submitting" if there was a submit button. But here it's live add/delete.
    // I will keep course-level hard block. And semester-level warning/status.

    if (empty($course_name) || $credit_hour < 1) {
        $error = "<span data-en='Please enter a valid course name and credit hour.' data-am='???? ????? ???? ?? ?? ???? ??? ?????'>Please enter a valid course name and credit hour.</span>";
    } elseif ($credit_hour < $min_ch || $credit_hour > $max_ch) {
        $error = "<span data-en='Credit hour per course must be between $min_ch and $max_ch (set by Registrar).' data-am='???? ???? ??? ? $min_ch ?? $max_ch ???? ??? ???? (??????? ?????)?'>Credit hour per course must be between $min_ch and $max_ch (set by Registrar).</span>";
    } else {
        // Check duplicate
        $dup = $pdo->prepare("SELECT id FROM courses WHERE department_id = ? AND batch = ? AND semester = ? AND course_name = ?");
        $dup->execute([$dept_id, $sel_batch, $sel_semester, $course_name]);
        if ($dup->fetch()) {
            $error = "<span data-en='This course already exists for this batch/semester.' data-am='?? ??? ??? ??/????? ????? ???'>This course already exists for this batch/semester.</span>";
        } else {
            $ins = $pdo->prepare("INSERT INTO courses (department_id, batch, semester, course_name, credit_hour) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$dept_id, $sel_batch, $sel_semester, $course_name, $credit_hour]);
            $_SESSION["flash_success"] = "<span data-en='Course ' data-am='??? '>Course </span>'$course_name'<span data-en=' added successfully.' data-am=' ???? ??? ??????'> added successfully.</span>";
            header("Location: " . $_SERVER["PHP_SELF"] . "?batch=" . $sel_batch . "&semester=" . $sel_semester);
            exit();
        }
    }
}

// --- Handle Delete Course ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_course'])) {
    $course_id = (int) $_POST['course_id'];
    $sel_batch = (int) $_POST['batch'];
    $sel_semester = (int) $_POST['semester'];
    $pdo->prepare("DELETE FROM courses WHERE id = ? AND department_id = ?")->execute([$course_id, $dept_id]);
    $_SESSION["flash_success"] = "<span data-en='Course deleted successfully.' data-am='??? ???? ??? ??????'>Course deleted successfully.</span>";
    header("Location: " . $_SERVER["PHP_SELF"] . "?batch=" . $sel_batch . "&semester=" . $sel_semester);
    exit();
}

// --- Handle Submit to Cost Sharing ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_to_cost_sharing'])) {
    $sel_batch = (int) $_POST['batch'];
    $sel_semester = (int) $_POST['semester'];

    // Calculate total credit hours
    $ch_stmt = $pdo->prepare("SELECT SUM(credit_hour) as total FROM courses WHERE department_id = ? AND batch = ? AND semester = ?");
    $ch_stmt->execute([$dept_id, $sel_batch, $sel_semester]);
    $total = (int) $ch_stmt->fetch(PDO::FETCH_ASSOC)['total'];

    if ($total > 0) {
        $upd = $pdo->prepare("UPDATE courses SET credit_hours = ?, rate_status = 'Submitted' WHERE department_id = ? AND batch = ? AND semester = ?");
        $upd->execute([$total, $dept_id, $sel_batch, $sel_semester]);
        $_SESSION["flash_success"] = "<span data-en='Successfully submitted to Cost Sharing Professional.' data-am='??? ???? ???? ???? ??? ?????'>Successfully submitted to Cost Sharing Professional.</span>";
        header("Location: " . $_SERVER["PHP_SELF"] . "?batch=" . $sel_batch . "&semester=" . $sel_semester);
        exit();
    } else {
        $error = "<span data-en='No courses found to submit.' data-am='????? ??? ???????'>No courses found to submit.</span>";
    }
}

// --- Handle Student Special Case (Drop Courses) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_special_case'])) {
    $student_user_id = (int) $_POST['student_user_id'];
    $sel_batch = (int) $_POST['batch'];
    $sel_semester = (int) $_POST['semester'];
    $dropped_ids = isset($_POST['dropped_courses']) ? $_POST['dropped_courses'] : [];

    $added_ids = isset($_POST['added_courses']) ? $_POST['added_courses'] : [];
    $reason = trim($_POST['reason'] ?? '');

    // Get all courses for this batch/semester
    $all_courses = $pdo->prepare("SELECT * FROM courses WHERE department_id = ? AND batch = ? AND semester = ?");
    $all_courses->execute([$dept_id, $sel_batch, $sel_semester]);
    $courses_list = $all_courses->fetchAll(PDO::FETCH_ASSOC);

    // Calculate adjusted credit hours  
    $total_ch = 0;
    $dropped_names = [];
    $added_names = []; // Log additions

    // 1. Start with standard courses (minus dropped)
    foreach ($courses_list as $c) {
        if (!in_array($c['id'], $dropped_ids)) {
            $total_ch += $c['credit_hour'];
        } else {
            $dropped_names[] = $c['course_name'] . ' (' . $c['credit_hour'] . ' Cr.Hr)';
        }
    }

    // 2. Add extra courses
    if (!empty($added_ids)) {
        // Fetch details for added courses
        $in_clause = implode(',', array_fill(0, count($added_ids), '?'));
        $add_stmt = $pdo->prepare("SELECT id, course_name, credit_hour FROM courses WHERE id IN ($in_clause) AND department_id = ?");
        // Combine added_ids and dept_id for execution
        $params = $added_ids;
        $params[] = $dept_id;
        $add_stmt->execute($params);
        $added_courses_data = $add_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($added_courses_data as $ac) {
            $total_ch += $ac['credit_hour'];
            $added_names[] = $ac['course_name'] . ' (' . $ac['credit_hour'] . ' Cr.Hr)';
        }
    }

    $dropped_json = json_encode($dropped_names);
    $added_json = json_encode($added_names);

    // Upsert into students table directly
    // Validate adjusted credit hours against Semester Min/Max
    // Fetch limits
    $limits_stmt = $pdo->prepare("SELECT semester_min_credits, semester_max_credits FROM courses WHERE department_id = ? AND batch = ? AND semester = ?");
    $limits_stmt->execute([$dept_id, $sel_batch, $sel_semester]);
    $limits = $limits_stmt->fetch(PDO::FETCH_ASSOC);
    $sem_min = $limits ? $limits['semester_min_credits'] : 0;
    $sem_max = $limits ? $limits['semester_max_credits'] : 999;

    if ($total_ch < $sem_min || $total_ch > $sem_max) {
        $error = "<span data-en='Adjusted credit hour ($total_ch) must be between Semester Min ($sem_min) and Max ($sem_max) for special cases.' data-am='??????? ???? ??? ($total_ch) ??? ???? ?????? ??? ($sem_min) ?? ???? ($sem_max) ???? ??? ?????'>Adjusted credit hour ($total_ch) must be between Semester Min ($sem_min) and Max ($sem_max) for special cases.</span>";
    } else {
        $upd = $pdo->prepare("UPDATE students SET adjusted_credit_hours = ?, dropped_courses = ?, added_courses = ?, special_credit_reason = ? WHERE user_id = ?");
        $upd->execute([$total_ch, $dropped_json, $added_json, $reason, $student_user_id]);
        $_SESSION["flash_success"] = "<span data-en='Special case saved. Adjusted credit hours: ' data-am='?? ??? ?????? ??????? ???? ???: '>Special case saved. Adjusted credit hours: </span>$total_ch";
        header("Location: " . $_SERVER["PHP_SELF"] . "?batch=" . $sel_batch . "&semester=" . $sel_semester);
        exit();
    }
}


// --- Handle Remove Special Case ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_special_case'])) {
    $sc_user_id = (int) $_POST['sc_id'];
    $sel_batch = (int) $_POST['batch'];
    $sel_semester = (int) $_POST['semester'];
    $pdo->prepare("UPDATE students SET adjusted_credit_hours = NULL, dropped_courses = NULL, added_courses = NULL, special_credit_reason = NULL WHERE user_id = ? AND department_id = ?")->execute([$sc_user_id, $dept_id]);
    $_SESSION["flash_success"] = "<span data-en='Special case removed. Student will use standard credit hours.' data-am='?? ??? ?????? ???? ?????? ???? ??? ??????'>Special case removed. Student will use standard credit hours.</span>";
    header("Location: " . $_SERVER["PHP_SELF"] . "?batch=" . $sel_batch . "&semester=" . $sel_semester);
    exit();
}

// --- Fetch Courses for selected batch/semester ---
$courses_stmt = $pdo->prepare("SELECT * FROM courses WHERE department_id = ? AND batch = ? AND semester = ? ORDER BY course_name");
$courses_stmt->execute([$dept_id, $sel_batch, $sel_semester]);
$courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch ALL courses for this department (for Add Course option) ---
// Exclude courses already in this semester (to avoid adding duplicates of standard courses)
// Ideally, fetch courses where batch != $sel_batch OR semester != $sel_semester
// But simpler to fetch all and filter in UI or let user decide?
// User said "yalwesedewn course" -> not taken.
// I will fetch ALL courses for the department, ordered by batch/sem.
$all_courses_stmt = $pdo->prepare("SELECT * FROM courses WHERE department_id = ? ORDER BY batch, semester, course_name");
$all_courses_stmt->execute([$dept_id]);
$all_dept_courses = $all_courses_stmt->fetchAll(PDO::FETCH_ASSOC); // Used in modal for additions

// Calculate total credit hours from courses
$total_credit_hours = array_sum(array_column($courses, 'credit_hour'));

// --- Fetch courses for this batch/semester (from Registrar) ---
$rate_stmt = $pdo->prepare("SELECT * FROM courses WHERE department_id = ? AND batch = ? AND semester = ?");
$rate_stmt->execute([$dept_id, $sel_batch, $sel_semester]);
$existing_rate = $rate_stmt->fetch(PDO::FETCH_ASSOC);

// Extract min/max for use in the form
$reg_min = $existing_rate ? (int) $existing_rate['min_credit_hours'] : 1;
$reg_max = $existing_rate ? (int) $existing_rate['max_credit_hours'] : 10;
$reg_sem_min = $existing_rate ? (int) $existing_rate['semester_min_credits'] : 0;
$reg_sem_max = $existing_rate ? (int) $existing_rate['semester_max_credits'] : 0;
$reg_sem_min = $existing_rate ? (int) $existing_rate['semester_min_credits'] : 0;
$reg_sem_max = $existing_rate ? (int) $existing_rate['semester_max_credits'] : 0;
$reg_billing_total = $existing_rate ? (int) $existing_rate['credit_hours'] : 0;
$status = $existing_rate['status'] ?? 'Pending_Dept';

// Status Logic
$status_label = '';
$status_class = '';
$can_edit = true;

if ($status == 'Pending_Dept') {
    $status_label = '<span data-en="Draft (Not Submitted)" data-am="??? (??????)">Draft (Not Submitted)</span>';
    $status_class = 'status-draft'; // Gray/Info
} elseif ($status == 'Submitted') {
    $status_label = '<span data-en="Submitted to Cost Sharing" data-am="??? ???? ????">Submitted to Cost Sharing</span>';
    $status_class = 'status-submitted'; // Blue/Green
    $can_edit = false; // Locked after submission
}

// --- Fetch Students in this dept/batch/semester ---
$students_stmt = $pdo->prepare("SELECT s.user_id, s.student_id, u.first_name, u.middle_name, u.last_name, s.batch, s.current_semester 
                                FROM students s 
                                JOIN users u ON s.user_id = u.id 
                                WHERE s.department_id = ? AND s.batch = ? 
                                ORDER BY u.first_name");
$students_stmt->execute([$dept_id, $sel_batch]);
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch existing special cases ---
$special_stmt = $pdo->prepare("SELECT s.user_id as id, s.user_id as student_id, s.department_id, s.batch, s.current_semester as semester, 
                               s.adjusted_credit_hours, s.dropped_courses, s.added_courses, s.special_credit_reason as reason, 
                               u.first_name, u.middle_name, u.last_name, s.student_id as student_code 
                               FROM students s 
                               JOIN users u ON s.user_id = u.id 
                               WHERE s.department_id = ? AND s.batch = ? AND s.current_semester = ? AND s.adjusted_credit_hours IS NOT NULL
                               ORDER BY u.first_name");
$special_stmt->execute([$dept_id, $sel_batch, $sel_semester]);
$special_cases = $special_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Manage Credit Hours - Dept Head" data-am="????? ??? ?????? - ???????? ???">Manage Credit Hours -
        Dept Head</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .filter-bar {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter-bar .form-group {
            margin-bottom: 0;
        }

        .filter-bar select {
            min-width: 120px;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-card.green {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .stat-card.orange {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card .stat-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            font-size: 13px;
            opacity: 0.9;
        }

        .course-table {
            width: 100%;
            border-collapse: collapse;
        }

        .course-table th,
        .course-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .course-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .course-table tr:hover {
            background: #f0f4ff;
        }

        .add-course-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 15px;
        }

        .add-course-form input,
        .add-course-form button {
            padding: 8px 12px;
        }

        .btn-danger-sm {
            background: #e74c3c;
            color: #fff;
            border: none;
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-danger-sm:hover {
            background: #c0392b;
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .special-case-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-left: 4px solid #e67e22;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .special-case-card .student-name {
            font-weight: bold;
            font-size: 15px;
        }

        .special-case-card .details {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        .dropped-list {
            background: #fff3cd;
            padding: 8px 12px;
            border-radius: 5px;
            margin-top: 8px;
            font-size: 13px;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-box h3 {
            margin-bottom: 15px;
        }

        .course-check-list {
            list-style: none;
            padding: 0;
        }

        .course-check-list li {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .course-check-list li label {
            cursor: pointer;
            flex: 1;
        }

        .course-check-list li .ch-badge {
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
        }

        .total-footer {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            text-align: right;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>
            <div class="main-content">
                <div class="top-bar">
                    <h2 data-en="Manage Credit Hours" data-am="????? ??? ??????">Manage Credit Hours</h2>
                </div>

                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>

                <!-- Department Info -->
                <div class="card mb-20"
                    style="background:linear-gradient(135deg,#1a1a2e,#16213e); color:#fff; padding:20px;">
                    <h3 style="margin:0;"><i class="fas fa-university"></i>
                        <?php echo htmlspecialchars($dept_name); ?>
                    </h3>
                    <p style="margin:5px 0 0; opacity:0.8;"
                        data-en="Manage courses and credit hours for your department"
                        data-am="?????? ????? ???? ?? ???? ???? ??????">
                        Manage courses and credit hours for your department</p>
                </div>

                <!-- Filter Bar -->
                <div class="card mb-20">
                    <form method="GET" class="filter-bar">
                        <div class="form-group">
                            <label data-en="Year of Study" data-am="???? ???">Year of Study</label>
                            <select name="batch" id="batchSelect" required>
                                <option value="" data-en="Select Batch" data-am="?? ????">-- Select --</option>
                                <!-- Populated via JS loadBatches() -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label data-en="Semester" data-am="?????">Semester</label>
                            <select name="semester" id="semesterSelect" required>
</select>
                        </div>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-filter"></i>
                            <span data-en="Filter" data-am="???">Filter</span>
                        </button>
                    </form>
                </div>

                <?php if ($sel_batch > 0 && $sel_semester > 0): ?>
                    <!-- Stats Row -->
                    <div class="stats-row">
                        <div class="stat-card">
                            <div class="stat-value">
                                <?php echo count($courses); ?>
                            </div>
                            <div class="stat-label" data-en="Total Courses" data-am="???? ????">Total Courses</div>
                        </div>
                        <div class="stat-card green">
                            <div class="stat-value">
                                <?php echo $total_credit_hours; ?>
                            </div>
                            <div class="stat-label" data-en="Total Credit Hours" data-am="???? ???? ???">Total Credit Hours
                            </div>
                        </div>
                        <div class="stat-card orange">
                            <div class="stat-value">
                                <?php echo count($special_cases); ?>
                            </div>
                            <div class="stat-label" data-en="Special Cases" data-am="?? ????">Special Cases (Drops)</div>
                        </div>
                    </div>

                    <!-- Courses Section -->
                    <div class="card mb-20">
                        <div class="section-title">
                            <h3><i class="fas fa-book"></i>
                                <span data-en="Courses" data-am="????">Courses</span>
                                — Batch
                                <?php echo $sel_batch; ?>, Semester
                                <?php echo $sel_semester; ?>
                            </h3>
                        </div>

                        <?php if (count($courses) > 0): ?>
                            <table class="course-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th data-en="Course Name" data-am="???? ??">Course Name</th>
                                        <th data-en="Credit Hour" data-am="???? ???">Credit Hour</th>
                                        <th data-en="Action" data-am="????">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $i => $c): ?>
                                        <tr>
                                            <td>
                                                <?php echo $i + 1; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($c['course_name']); ?>
                                            </td>
                                            <td><strong>
                                                    <?php echo $c['credit_hour']; ?>
                                                </strong></td>
                                            <td>
                                                <?php if ($can_edit): ?>
                                                    <!-- Init -->
                                                    <span id="del-init-<?php echo $c['id']; ?>">
                                                        <button type="button" class="btn-danger-sm"
                                                            onclick="showDelConfirm(<?php echo $c['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </span>
                                                    <!-- Confirm -->
                                                    <span id="del-confirm-<?php echo $c['id']; ?>" style="display:none;">
                                                        <form method="POST" style="display:inline;"
                                                            id="del-course-<?php echo $c['id']; ?>">
                                                            <input type="hidden" name="course_id" value="<?php echo $c['id']; ?>">
                                                            <input type="hidden" name="batch" value="<?php echo $sel_batch; ?>">
                                                            <input type="hidden" name="semester" value="<?php echo $sel_semester; ?>">
                                                            <input type="hidden" name="delete_course" value="1">
                                                            <button type="submit" class="btn-danger-sm">Yes</button>
                                                        </form>
                                                        <button type="button" class="btn-secondary-sm"
                                                            onclick="hideDelConfirm(<?php echo $c['id']; ?>)">No</button>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color:#999; font-size:12px;"><i class="fas fa-lock"></i> Locked</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background:#e9ecef; font-weight:bold;">
                                        <td colspan="2" style="text-align:right;"><span data-en="Total Credit Hours"
                                                data-am="???? ???? ???">Total Credit Hours</span></td>
                                        <td>
                                            <?php echo $total_credit_hours; ?>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        <?php else: ?>
                            <p style="text-align:center; padding:30px; color:#999;">
                                <i class="fas fa-info-circle"></i>
                                <span data-en="No courses added yet for this batch/semester."
                                    data-am="??? ??/????? ??? ??? ????????">No courses added yet for this batch/semester.</span>
                            </p>
                        <?php endif; ?>

                        <?php if ($can_edit): ?>
                            <!-- Add Course Form -->
                            <form method="POST" class="add-course-form" onsubmit="return validateCourseAdd(event)">
                                <input type="hidden" name="batch" value="<?php echo $sel_batch; ?>">
                                <input type="hidden" name="semester" value="<?php echo $sel_semester; ?>">
                                <div class="form-group" style="flex:2;">
                                    <label data-en="Course Name" data-am="???? ??">Course Name</label>
                                    <input type="text" name="course_name" required placeholder="e.g. Data Structures"
                                        data-en="e.g. Data Structures" data-en-placeholder="e.g. Data Structures"
                                        data-am-placeholder="????? ?? ??????">
                                </div>
                                <div class="form-group" style="flex:1;">
                                    <label data-en="Credit Hour" data-am="???? ???">Credit Hour
                                        (<?php echo $reg_min; ?>-<?php echo $reg_max; ?>)</label>
                                    <input type="number" name="credit_hour" min="<?php echo $reg_min; ?>"
                                        max="<?php echo $reg_max; ?>" value="<?php echo $reg_min; ?>" required>
                                </div>
                                <button type="submit" name="add_course" class="btn-primary" style="margin-top:10px;"
                                    data-en="Add Course" data-am="??? ???">Add
                                    Course</button>
                            </form>
                        <?php endif; ?>

                        <!-- Action Bar: Submit to Cost Sharing -->
                        <div
                            style="margin-top: 20px; padding: 15px; background: #fff; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #eee;">
                            <div>
                                <span style="font-weight: bold; margin-right: 10px;">Status:</span>
                                <span class="badge <?php echo $status_class; ?>" style="padding: 5px 10px; border-radius: 4px; font-size: 13px; font-weight: bold;
                                <?php
                                if ($status == 'Pending_Dept')
                                    echo 'background:#e2e3e5; color:#383d41;';
                                elseif ($status == 'Submitted')
                                    echo 'background:#d4edda; color:#155724;';
                                ?>">
                                    <?php echo $status_label; ?>
                                </span>
                            </div>

                            <?php if (count($courses) > 0 && $can_edit): ?>
                                <form method="POST" style="display:inline;"
                                    onsubmit="event.preventDefault(); var form = this; Swal.fire({title: 'Are you sure?', text: 'Do you want to submit these credit hours to Cost Sharing Professional?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#28a745', cancelButtonColor: '#d33', confirmButtonText: 'Yes, submit it!'}).then((result) => { if (result.isConfirmed) { form.submit(); } });">
                                    <input type="hidden" name="batch" value="<?php echo $sel_batch; ?>">
                                    <input type="hidden" name="semester" value="<?php echo $sel_semester; ?>">
                                    <button type="submit" name="submit_to_cost_sharing" class="btn-primary"
                                        style="background:#28a745;">
                                        <i class="fas fa-paper-plane"></i>
                                        <span data-en="Submit to Cost Sharing" data-am="??? ???? ????">Submit to Cost
                                            Sharing</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Registrar Rate Info -->
                    <?php if ($existing_rate): ?>
                        <div class="card mb-20" style="border-left:4px solid #28a745;">
                            <h4><i class="fas fa-info-circle" style="color:#28a745;"></i>
                                <span data-en="Registrar Credit Hour Rate" data-am="??????? ???? ???? ???">Registrar Credit Hour
                                    Rate</span>
                            </h4>
                            <p>
                                <span data-en="Semester Range" data-am="?????? ???">Semester Range</span>:
                                <strong><?php echo $reg_sem_min; ?> - <?php echo $reg_sem_max; ?></strong> Cr.Hrs |
                                <span data-en="Billing Total" data-am="????? ????">Billing Total</span>:
                                <strong><?php echo $reg_billing_total; ?></strong> |
                                <span data-en="Range/Course" data-am="???/???">Range/Course</span>:
                                <strong><?php echo $reg_min; ?> - <?php echo $reg_max; ?></strong>
                            </p>
                        </div>

                        <!-- Constraint warning if total credit hours are outside range -->
                        <?php if ($total_credit_hours > 0 && ($total_credit_hours < $reg_sem_min || $total_credit_hours > $reg_sem_max)): ?>
                            <div class="card mb-20" style="border-left:4px solid #e74c3c; background:#fdf0f0;">
                                <p style="margin:0;">
                                    <i class="fas fa-exclamation-triangle" style="color:#e74c3c;"></i>
                                    <strong data-en="Credit Hour Mismatch" data-am="???? ??? ???????">Credit Hour Out of
                                        Range</strong>:
                                    <span data-en="Your courses total" data-am="????? ????">Your total</span>
                                    <strong><?php echo $total_credit_hours; ?></strong>
                                    <span data-en="must be between" data-am="??? ???? ?... ????">must be between</span>
                                    <strong><?php echo $reg_sem_min; ?></strong> <span data-en="and" data-am="??">and</span>
                                    <strong><?php echo $reg_sem_max; ?></strong>.
                                </p>
                            </div>
                        <?php elseif ($total_credit_hours > 0 && $reg_billing_total > 0 && $total_credit_hours != $reg_billing_total): ?>
                            <div class="card mb-20" style="border-left:4px solid #ffc107; background:#fff9e6;">
                                <p style="margin:0;">
                                    <i class="fas fa-info-circle" style="color:#ffc107;"></i>
                                    <strong data-en="Note" data-am="?????">Note</strong>:
                                    <span data-en="Your total" data-am="?????">Your total</span>
                                    <strong><?php echo $total_credit_hours; ?></strong>
                                    <span data-en="differs from Billing Total" data-am="????? ???? ????">differs from Billing
                                        Total</span>
                                    <strong>(<?php echo $reg_billing_total; ?>)</strong>,
                                    <span data-en="but is within valid range" data-am="??? ?? ???? ??? ??">but is within valid
                                        range</span>.
                                </p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Special Cases Section -->
                    <div class="card mb-20">
                        <div class="section-title">
                            <h3><i class="fas fa-user-edit"></i>
                                <span data-en="Student Special Cases (Drop)" data-am="???? ?? ???? (??? ???)">Student
                                    Special Cases (Drop)</span>
                            </h3>
                            <?php if (count($courses) > 0): ?>
                                <button class="btn-primary" onclick="openStudentModal()">
                                    <i class="fas fa-plus"></i>
                                    <span data-en="Add Special Case" data-am="?? ??? ???">Add Special Case</span>
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if (count($special_cases) > 0): ?>
                            <?php foreach ($special_cases as $sc): ?>
                                <div class="special-case-card">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <div>
                                            <div class="student-name">
                                                <?php echo htmlspecialchars($sc['first_name'] . ' ' . ($sc['middle_name'] ?? '') . ' ' . $sc['last_name']); ?>
                                                <span style="color:#999;">(
                                                    <?php echo $sc['student_code']; ?>)
                                                </span>
                                            </div>
                                            <div class="details">
                                                <span data-en="Adjusted Credit Hours" data-am="??????? ???? ???">Adjusted
                                                    Cr.Hrs</span>:
                                                <strong style="color:#e67e22;">
                                                    <?php echo $sc['adjusted_credit_hours']; ?>
                                                </strong>
                                                (Standard:
                                                <?php echo $total_credit_hours; ?>) |
                                                <span data-en="Reason" data-am="?????">Reason</span>:
                                                <?php echo htmlspecialchars($sc['reason'] ?: 'N/A'); ?>
                                            </div>
                                            <?php
                                            $dropped = json_decode($sc['dropped_courses'], true);
                                            if (!empty($dropped)):
                                                ?>
                                                <div class="dropped-list">
                                                    <i class="fas fa-exclamation-triangle" style="color:#856404;"></i>
                                                    <span data-en="Dropped Courses" data-am="???? ????">Dropped</span>:
                                                    <?php echo implode(', ', $dropped); ?>
                                                    <span data-en="Dropped Courses" data-am="???? ????">Dropped</span>:
                                                    <?php echo implode(', ', $dropped); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php
                                            $added = json_decode($sc['added_courses'] ?? '[]', true);
                                            if (!empty($added)):
                                                ?>
                                                <div class="dropped-list"
                                                    style="background:#d4edda; color:#155724; border:1px solid #c3e6cb;">
                                                    <i class="fas fa-plus-circle"></i>
                                                    <span data-en="Added Courses" data-am="?? added ????">Added</span>:
                                                    <?php echo implode(', ', $added); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <form method="POST" style="display:inline;" id="rm-sc-<?php echo $sc['id']; ?>">
                                            <input type="hidden" name="sc_id" value="<?php echo $sc['id']; ?>">
                                            <input type="hidden" name="batch" value="<?php echo $sel_batch; ?>">
                                            <input type="hidden" name="semester" value="<?php echo $sel_semester; ?>">
                                            <input type="hidden" name="remove_special_case" value="1">
                                            <span id="rm-init-<?php echo $sc['id']; ?>">
                                                <button type="button" class="btn-danger-sm"
                                                    onclick="showRmConfirm(<?php echo $sc['id']; ?>)" title="Remove special case">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </span>
                                            <span id="rm-confirm-<?php echo $sc['id']; ?>"
                                                style="display:none; background:#fff3cd; padding:4px 10px; border-radius:5px; border:1px solid #ffc107;">
                                                <strong style="color:#856404; font-size:12px;">Remove?</strong>
                                                <button type="submit"
                                                    style="background:#e74c3c; color:#fff; border:none; padding:3px 10px; border-radius:3px; cursor:pointer; margin-left:6px; font-size:12px;">Yes</button>
                                                <button type="button" onclick="hideRmConfirm(<?php echo $sc['id']; ?>)"
                                                    style="background:#6c757d; color:#fff; border:none; padding:3px 10px; border-radius:3px; cursor:pointer; margin-left:4px; font-size:12px;">No</button>
                                            </span>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align:center; padding:20px; color:#999;">
                                <i class="fas fa-check-circle" style="color:#28a745;"></i>
                                <span
                                    data-en="No special cases for this batch/semester. All students use standard credit hours."
                                    data-am="??? ??/????? ??? ?? ??? ????">No special cases. All students use standard credit
                                    hours.</span>
                            </p>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <div class="card" style="text-align: center; padding: 50px 20px; color: #666; margin-top: 20px;">
                        <i class="fas fa-info-circle"
                            style="font-size: 48px; margin-bottom: 15px; color: #007bff; opacity: 0.5;"></i>
                        <h3 data-en="Select Year of Study and Semester" data-am="???? ??? ?? ????? ????"
                            style="margin-bottom: 10px;">Select Year of Study and Semester</h3>
                        <p data-en="Please select the Year of Study and Semester from the filter above to view and manage courses."
                            data-am="????? ???? ?? ??????? ???? ??? ??? ???? ???? ??? ?? ????? ?????">Please select the Year
                            of Study and Semester from the filter above to view and manage courses.</p>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- Student Selection & Drop Course Modal -->
    <div class="modal-overlay" id="studentModal">
        <div class="modal-box">
            <h3><i class="fas fa-user-edit"></i>
                <span data-en="Handle Student Special Case" data-am="???? ?? ???">Handle Student Special Case</span>
            </h3>
            <form method="POST" id="specialCaseForm" onsubmit="return validateSpecialCase(event)">
                <input type="hidden" name="batch" value="<?php echo $sel_batch; ?>">
                <input type="hidden" name="semester" value="<?php echo $sel_semester; ?>">

                <!-- Step 1: Select Student -->
                <div class="form-group">
                    <label data-en="Select Student" data-am="??? ????">Select Student</label>
                    <select name="student_user_id" required id="studentSelect"
                        onchange="document.getElementById('courseDropSection').style.display = this.value ? 'block' : 'none';">
                        <option value="" data-en="-- Select Student --" data-am="-- ??? ???? --">-- Select Student --
                        </option>
                        <?php foreach ($students as $stu): ?>
                            <option value="<?php echo $stu['user_id']; ?>">
                                <?php echo htmlspecialchars($stu['first_name'] . ' ' . ($stu['middle_name'] ?? '') . ' ' . $stu['last_name']); ?>
                                (
                                <?php echo $stu['student_id']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Step 2: Select Dropped Courses -->
                <div id="courseDropSection" style="display:none;">
                    <p style="margin-bottom:10px; color:#666;" data-en="Check the courses the student has DROPPED:"
                        data-am="???? ??????? ???? ???? ????:">
                        Check the courses the student has <strong>DROPPED</strong>:</p>
                    <ul class="course-check-list">
                        <?php foreach ($courses as $c): ?>
                            <li>
                                <input type="checkbox" name="dropped_courses[]" value="<?php echo $c['id']; ?>"
                                    id="drop_<?php echo $c['id']; ?>" class="drop-check"
                                    data-ch="<?php echo $c['credit_hour']; ?>" onchange="updateAdjustedTotal()">
                                <label for="drop_<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['course_name']); ?>
                                </label>
                                <span class="ch-badge">
                                    <?php echo $c['credit_hour']; ?> <span data-en="Cr.Hr" data-am="????">Cr.Hr</span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Separator -->
                    <hr style="margin:15px 0; border:0; border-top:1px solid #eee;">

                    <!-- Step 3: Add Extra Courses -->
                    <p style="margin-bottom:10px; color:#28a745;" data-en="Select courses to ADD (e.g. Retake/F):"
                        data-am="??????? ???? ???? (????? ?? ???):">
                        Select courses to <strong>ADD</strong> (e.g. Retake/F):</p>

                    <div
                        style="max-height:150px; overflow-y:auto; border:1px solid #eee; padding:10px; border-radius:5px;">
                        <?php if (count($all_dept_courses) > 0): ?>
                            <ul class="course-check-list">
                                <?php foreach ($all_dept_courses as $adc): ?>
                                    <?php
                                    // Skip if course is already in the current semester list
                                    $is_current = false;
                                    foreach ($courses as $cc) {
                                        if ($cc['id'] == $adc['id']) {
                                            $is_current = true;
                                            break;
                                        }
                                    }
                                    if ($is_current)
                                        continue; // Don't show current sem courses in "Add" list
                                    ?>
                                    <li>
                                        <input type="checkbox" name="added_courses[]" value="<?php echo $adc['id']; ?>"
                                            id="add_<?php echo $adc['id']; ?>" class="add-check"
                                            data-ch="<?php echo $adc['credit_hour']; ?>" onchange="updateAdjustedTotal()">
                                        <label for="add_<?php echo $adc['id']; ?>">
                                            <?php echo htmlspecialchars($adc['course_name']); ?>
                                            <small style="color:#666;">(Batch <?php echo $adc['batch']; ?>, Sem
                                                <?php echo $adc['semester']; ?>)</small>
                                        </label>
                                        <span class="ch-badge" style="background:#d1e7dd; color:#0f5132;">
                                            +<?php echo $adc['credit_hour']; ?> <span data-en="Cr.Hr"
                                                data-am="????">Cr.Hr</span>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p style="color:#999; font-size:13px;">No other courses available.</p>
                        <?php endif; ?>
                    </div>

                    <div class="total-footer">
                        <span data-en="Adjusted Total Credit Hours" data-am="??????? ???? ???? ???">Adjusted
                            Total</span>:
                        <span id="adjustedTotal">
                            <?php echo $total_credit_hours; ?>
                        </span> /
                        <?php echo $total_credit_hours; ?>
                    </div>

                    <div class="form-group" style="margin-top:15px;">
                        <label data-en="Reason" data-am="?????">Reason (optional)</label>
                        <input type="text" name="reason" placeholder="e.g. Student dropped due to health issues"
                            data-en="e.g. Student dropped due to health issues"
                            data-en-placeholder="e.g. Student dropped due to health issues"
                            data-am-placeholder="????? ???? ??? ??? ????? ??? ??">
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:20px; justify-content:flex-end;">
                    <button type="button" class="btn-secondary" onclick="closeStudentModal()"
                        style="background:#6c757d; color:#fff; border:none; padding:10px 20px; border-radius:5px; cursor:pointer;">
                        <i class="fas fa-times"></i>
                        <span data-en="Cancel" data-am="???">Cancel</span>
                    </button>
                    <button type="submit" name="save_special_case" class="btn-primary">
                        <i class="fas fa-save"></i>
                        <span data-en="Save Special Case" data-am="?? ??? ?????">Save Special Case</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const totalCH = <?php echo $total_credit_hours; ?>;

        // Registrar constraints passed from PHP
        const regCourseMin = <?php echo $reg_min; ?>;
        const regCourseMax = <?php echo $reg_max; ?>;
        const regSemMax = <?php echo $reg_sem_max; ?>;
        const regSemMin = <?php echo $reg_sem_min; ?>;
        const regBillingTotal = <?php echo $reg_billing_total; ?>;
        const currentTotal = <?php echo $total_credit_hours; ?>;

        // Reverted delete validation: Standard flow is not constrained by Sem Min (only Special Case is).
        // User said: "Special case lelebachew students new... constrain yemiyadergew" (Constraints are for special case students).
        // Standard courses should add up to Billing Total. Deleting is allowed to adjust.

        function validateCourseAdd(e) {
            const input = document.querySelector('input[name="credit_hour"]');
            const val = parseInt(input.value);
            const name = document.querySelector('input[name="course_name"]').value.trim();

            if (!name) {
                e.preventDefault();
                const lang = localStorage.getItem('dmu_lang') || 'en';
                Swal.fire({
                    icon: 'error',
                    title: lang === 'en' ? 'Missing Input' : '??? ?????',
                    text: lang === 'en' ? 'Please enter a course name.' : '???? ???? ?? ?????',
                });
                return false;
            }

            // Course Min/Max Check
            if (val < regCourseMin || val > regCourseMax) {
                e.preventDefault();
                const lang = localStorage.getItem('dmu_lang') || 'en';
                Swal.fire({
                    icon: 'error',
                    title: lang === 'en' ? 'Invalid Credit Hour' : '????? ???? ???',
                    text: lang === 'en'
                        ? `Credit hour per course must be between ${regCourseMin} and ${regCourseMax}.`
                        : `???? ???? ??? ? ${regCourseMin} ?? ${regCourseMax} ???? ??? ?????`,
                });
                return false;
            }

            // Total Billing Check (Standard)
            // User said: "Dept head add course ... based on Total Billing Credit Hour"
            if ((currentTotal + val) > regBillingTotal) {
                e.preventDefault();
                const lang = localStorage.getItem('dmu_lang') || 'en';
                Swal.fire({
                    icon: 'error',
                    title: lang === 'en' ? 'Limit Exceeded' : '??? ????',
                    text: lang === 'en'
                        ? `Adding this course (${val} Cr.Hr) would exceed the Total Billing Credit Hour (${regBillingTotal}) set by the Registrar.`
                        : `???? ??? ???? (${val} ????) ??????? ??????? ???? ??? ???? (${regBillingTotal}) ?????`,
                    footer: lang === 'en' ? 'The standard course list must not exceed the billing total.' : '???? ???? ???? ???? ???? ???? ??????'
                });
                return false;
            }

            return true;
        }

        function validateSpecialCase(e) {
            // Calculate potential adjusted total
            let dropped = 0;
            document.querySelectorAll('.drop-check:checked').forEach(cb => {
                dropped += parseInt(cb.getAttribute('data-ch'));
            });

            let added = 0;
            document.querySelectorAll('.add-check:checked').forEach(cb => {
                added += parseInt(cb.getAttribute('data-ch'));
            });

            const adjusted = totalCH - dropped + added;

            if (adjusted < regSemMin || adjusted > regSemMax) {
                e.preventDefault();
                const lang = localStorage.getItem('dmu_lang') || 'en';
                Swal.fire({
                    icon: 'error',
                    title: lang === 'en' ? 'Special Case Limit Violation' : '??? ??? ??? ???',
                    text: lang === 'en'
                        ? `The adjusted credit hour (${adjusted}) must be between Semester Min (${regSemMin}) and Max (${regSemMax}).`
                        : `??????? ???? ??? (${adjusted}) ?????? ??? (${regSemMin}) ?? ???? (${regSemMax}) ???? ??? ?????`,
                });
                return false;
            }
            return true;
        }

        function openStudentModal() {
            document.getElementById('studentModal').classList.add('active');
        }

        function closeStudentModal() {
            document.getElementById('studentModal').classList.remove('active');
            document.getElementById('specialCaseForm').reset();
            document.getElementById('courseDropSection').style.display = 'none';
            document.getElementById('adjustedTotal').textContent = totalCH;
        }

        function updateAdjustedTotal() {
            let dropped = 0;
            document.querySelectorAll('.drop-check:checked').forEach(cb => {
                dropped += parseInt(cb.getAttribute('data-ch'));
            });

            let added = 0;
            document.querySelectorAll('.add-check:checked').forEach(cb => {
                added += parseInt(cb.getAttribute('data-ch'));
            });

            const adjusted = totalCH - dropped + added;
            document.getElementById('adjustedTotal').textContent = adjusted;
        }

        // Close modal on outside click
        document.getElementById('studentModal').addEventListener('click', function (e) {
            if (e.target === this) closeStudentModal();
        });

        // Delete course confirm
        function showDelConfirm(id) {
            document.getElementById('del-init-' + id).style.display = 'none';
            document.getElementById('del-confirm-' + id).style.display = 'inline';
        }
        function hideDelConfirm(id) {
            document.getElementById('del-confirm-' + id).style.display = 'none';
            document.getElementById('del-init-' + id).style.display = 'inline';
        }

        // Remove special case confirm
        function showRmConfirm(id) {
            document.getElementById('rm-init-' + id).style.display = 'none';
            document.getElementById('rm-confirm-' + id).style.display = 'inline';
        }
        function hideRmConfirm(id) {
            document.getElementById('rm-confirm-' + id).style.display = 'none';
            document.getElementById('rm-init-' + id).style.display = 'inline';
        }
    </script>
    <script src="../../assets/js/bilingual.js"></script>
<script>
    let currentSemesters = {};
    const selectedBatch = "<?php echo $sel_batch ?? ''; ?>";
    const selectedSemester = "<?php echo $sel_semester ?? ''; ?>";
    const deptId = "<?php echo $dept_id ?? ''; ?>";

    document.addEventListener('DOMContentLoaded', function() {
        const batchSelect = document.getElementById('batchSelect');
        const semesterSelect = document.getElementById('semesterSelect');
        
        if (batchSelect) {
            batchSelect.addEventListener('change', loadSemesters);
            loadBatches();
        }
    });

    function loadBatches() {
        const batchSelect = document.getElementById('batchSelect');
        const semesterSelect = document.getElementById('semesterSelect');

        fetch(`../../api/get_dropdown_options.php?action=get_batches&department_id=${deptId}`)
            .then(response => response.json())
            .then(result => {
                const batches = result.batches || result;
                currentSemesters = result.semesters || {};
                
                if (batchSelect) {
                    batches.forEach(batch => {
                        const option = document.createElement('option');
                        option.value = batch;
                        option.textContent = 'Batch ' + batch;
                        if (batch == selectedBatch) option.selected = true;
                        batchSelect.appendChild(option);
                    });
                    
                    if (selectedBatch) {
                        loadSemesters();
                    }
                }
            });
    }

    function loadSemesters() {
        const batchSelect = document.getElementById('batchSelect');
        const semesterSelect = document.getElementById('semesterSelect');
        if (!semesterSelect) return;
        
        const batch = batchSelect ? batchSelect.value : null;
        semesterSelect.innerHTML = '<option value="">-- Select --</option>';

        if (batch && currentSemesters[batch]) {
            currentSemesters[batch].forEach(sem => {
                const option = document.createElement('option');
                option.value = sem;
                option.textContent = sem;
                if (sem == selectedSemester) option.selected = true;
                semesterSelect.appendChild(option);
            });
        }
    }
</script>
</body>

</html>
