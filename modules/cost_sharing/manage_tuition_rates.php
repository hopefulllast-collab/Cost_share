<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['cost_sharing_pro']);
require_once '../../includes/academic_translations.php';

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

// Fetch departments
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get filter values
$sel_dept = isset($_GET['department_id']) ? (int) $_GET['department_id'] : 0;
$sel_batch = isset($_GET['batch']) ? (int) $_GET['batch'] : 0;
$sel_semester = isset($_GET['semester']) ? (int) $_GET['semester'] : 0;

// Handle Save Rate
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_rate'])) {
    $dept_id = (int) $_POST['department_id'];
    $batch = (int) $_POST['batch'];
    $semester = (int) $_POST['semester'];
    $cost_per_credit = (float) $_POST['cost_per_credit_hour'];
    $academic_year = trim($_POST['academic_year']);

    $sel_dept = $dept_id;
    $sel_batch = $batch;
    $sel_semester = $semester;

    // Get credit hours from courses (set by Department Head)
    $rate_check = $pdo->prepare("SELECT MAX(id) as id, SUM(credit_hour) as credit_hours FROM courses WHERE department_id = ? AND batch = ? AND semester = ? AND rate_status = 'Submitted'");
    $rate_check->execute([$dept_id, $batch, $semester]);
    $existing = $rate_check->fetch(PDO::FETCH_ASSOC);

    if ($existing && $existing['id']) {
        // Update only the cost_per_credit_hour and academic_year
        $upd = $pdo->prepare("UPDATE courses SET cost_per_credit_hour = ?, academic_year = ? WHERE department_id = ? AND batch = ? AND semester = ? AND rate_status = 'Submitted'");
        $upd->execute([$cost_per_credit, $academic_year, $dept_id, $batch, $semester]);
        $_SESSION["flash_success"] = "<span data-en='Rate updated successfully.' data-am='የክፍያ መጠን በተሳካ ሁኔታ ተዘምኗል።'>Rate updated successfully.</span> Tuition = " . $existing['credit_hours'] . " × " . number_format($cost_per_credit, 2) . " = " . number_format($existing['credit_hours'] * $cost_per_credit, 2) . " Birr";
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    } else {
        $error = "<span data-en=\"No submitted credit hour record found. The Department Head must submit credit hours first.\" data-am=\"የተላከ የክሬዲት ሰዓት መረጃ የለም። የትምህርት ክፍል ኃላፊው መጀመሪያ የክሬዲት ሰዓቶችን መላክ አለበት።\">No submitted credit hour record found. The Department Head must submit credit hours first.</span>";
    }
}

// Handle Delete Rate
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_rate'])) {
    $rate_id = (int) $_POST['rate_id'];
    $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$rate_id]);
    $_SESSION["flash_success"] = "<span data-en='Rate deleted successfully.' data-am='የክፍያ መጠን በተሳካ ሁኔታ ተሰርዟል።'>Rate deleted successfully.</span>";
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit();
}


// Handle Edit Rate (inline edit from table)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_rate'])) {
    $rate_id = (int) $_POST['rate_id'];
    $new_cost = (float) $_POST['cost_per_credit_hour'];
    $new_year = trim($_POST['academic_year']);
    
    $upd = $pdo->prepare("UPDATE courses SET cost_per_credit_hour = ?, academic_year = ? WHERE id = ?");
    $upd->execute([$new_cost, $new_year, $rate_id]);
    $_SESSION["flash_success"] = "<span data-en='Rate updated successfully.' data-am='የክፍያ መጠን በተሳካ ሁኔታ ተዘምኗል።'>Rate updated successfully.</span>";
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit();
}

// Handle Update Expense Settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_expenses'])) {
    $food = (float) $_POST['food_expense'];
    $bed = (float) $_POST['bed_expense'];
    $med = (float) $_POST['medication_expense'];
    $food_label = trim($_POST['food_label']);
    $bed_label = trim($_POST['bed_label']);
    $med_label = trim($_POST['med_label']);
    
    $upd = $pdo->prepare("UPDATE expense_settings SET setting_value = ?, calculation_label = ? WHERE setting_key = ?");
    $upd->execute([$food, $food_label, 'food_expense']);
    $upd->execute([$bed, $bed_label, 'bed_expense']);
    $upd->execute([$med, $med_label, 'medication_expense']);
    
    $_SESSION["flash_success"] = "<span data-en='Expense settings updated successfully.' data-am='የወጪ ቅንብሮች በተሳካ ሁኔታ ተዘምነዋል።'>Expense settings updated successfully.</span>";
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit();
}

// Fetch Expense Settings
$exp_settings = [];
$exp_rows = $pdo->query("SELECT * FROM expense_settings")->fetchAll(PDO::FETCH_ASSOC);
foreach ($exp_rows as $row) {
    $exp_settings[$row['setting_key']] = $row;
}

// Handle Update Expense Settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_expenses'])) {
    $food = (float) $_POST['food_expense'];
    $bed = (float) $_POST['bed_expense'];
    $med = (float) $_POST['medication_expense'];
    $food_label = trim($_POST['food_label']);
    $bed_label = trim($_POST['bed_label']);
    $med_label = trim($_POST['med_label']);
    
    $upd = $pdo->prepare("UPDATE expense_settings SET setting_value = ?, calculation_label = ? WHERE setting_key = ?");
    $upd->execute([$food, $food_label, 'food_expense']);
    $upd->execute([$bed, $bed_label, 'bed_expense']);
    $upd->execute([$med, $med_label, 'medication_expense']);
    
    $_SESSION["flash_success"] = "<span data-en='Expense settings updated successfully.' data-am='የወጪ ቅንብሮች በተሳካ ሁኔታ ተዘምነዋል።'>Expense settings updated successfully.</span>";
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit();
}

// Fetch Expense Settings
$exp_settings = [];
$exp_rows = $pdo->query("SELECT * FROM expense_settings")->fetchAll(PDO::FETCH_ASSOC);
foreach ($exp_rows as $row) {
    $exp_settings[$row['setting_key']] = $row;
}
// Fetch all existing rates (grouped to avoid duplicate rows per course)
$rates = $pdo->query("SELECT chr.department_id, chr.batch, chr.semester, 
                       MAX(chr.id) as id,
                       SUM(chr.credit_hour) as credit_hours, 
                       MAX(chr.cost_per_credit_hour) as cost_per_credit_hour, 
                       MAX(chr.academic_year) as academic_year,
                       d.name as dept_name 
                       FROM courses chr 
                       JOIN departments d ON chr.department_id = d.id 
                       WHERE chr.rate_status = 'Submitted'
                       GROUP BY chr.department_id, chr.batch, chr.semester, d.name
                       ORDER BY d.name, chr.batch, chr.semester")->fetchAll(PDO::FETCH_ASSOC);

// Fetch credit data for selected filter
$fetched_credit = null;
$special_cases = [];
$courses = [];
if ($sel_dept > 0 && $sel_batch > 0 && $sel_semester > 0) {
    // Credit hour rate
    $stmt = $pdo->prepare("SELECT SUM(chr.credit_hour) as credit_hours, MAX(chr.cost_per_credit_hour) as cost_per_credit_hour, MAX(chr.academic_year) as academic_year FROM courses chr WHERE chr.department_id = ? AND chr.batch = ? AND chr.semester = ? AND chr.rate_status = 'Submitted'");
    $stmt->execute([$sel_dept, $sel_batch, $sel_semester]);
    $fetched_credit = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetched_credit && !$fetched_credit['credit_hours']) $fetched_credit = null;

    // Courses
    $course_stmt = $pdo->prepare("SELECT * FROM courses WHERE department_id = ? AND batch = ? AND semester = ? ORDER BY course_name");
    $course_stmt->execute([$sel_dept, $sel_batch, $sel_semester]);
    $courses = $course_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Special cases (student overrides)
    $sc_stmt = $pdo->prepare("SELECT s.user_id as student_id, s.department_id, s.batch, s.current_semester as semester, 
                               s.adjusted_credit_hours, s.dropped_courses, s.added_courses, s.special_credit_reason as reason, 
                               u.first_name, u.middle_name, u.last_name, s.student_id as student_code 
                               FROM students s 
                               JOIN users u ON s.user_id = u.id 
                               WHERE s.department_id = ? AND s.batch = ? AND s.current_semester = ? AND s.adjusted_credit_hours IS NOT NULL
                               ORDER BY u.first_name");
    $sc_stmt->execute([$sel_dept, $sel_batch, $sel_semester]);
    $special_cases = $sc_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Manage Tuition Rates - Cost Sharing Pro" data-am="የትምህርት ክፍያ መጠን አስተዳደር - ወጪ መጋራት">Manage
        Tuition Rates - Cost Sharing Pro</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .fetched-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .fetched-info .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .fetched-info .info-item {
            text-align: center;
        }

        .fetched-info .info-item .value {
            font-size: 24px;
            font-weight: bold;
        }

        .fetched-info .info-item .label {
            font-size: 12px;
            opacity: 0.85;
        }

        .rate-form {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border: 2px dashed #dee2e6;
        }

        .rates-table {
            width: 100%;
            border-collapse: collapse;
        }

        .rates-table th,
        .rates-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .rates-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .rates-table tr:hover {
            background: #f0f4ff;
        }

        .special-tag {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            border: 1px solid #ffc107;
        }

        .course-list-compact {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .course-chip {
            background: #e9ecef;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
        }

        .course-chip .ch {
            font-weight: bold;
            color: #007bff;
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
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>
            <div class="main-content">
                <div class="top-bar">
                    <h2 data-en="Manage Tuition Rates" data-am="የትምህርት ክፍያ መጠን አስተዳደር">Manage Tuition Rates</h2>
                </div>

                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>

                <!-- Filter / Fetch Bar -->
                <div class="card mb-20">
                    <h3><i class="fas fa-search"></i>
                        <span data-en="Fetch Credit Hours" data-am="የክሬዲት ሰዓታትን አምጣ">Fetch Credit Hours</span>
                    </h3>
                    <p style="color:#666; margin-bottom:15px;"
                        data-en="Select department, batch, and semester to auto-fetch credit hour data."
                        data-am="የክሬዲት ሰዓት መረጃ ለማምጣት ትምህርት ክፍል፣ ባች እና ሴሚስተር ይምረጡ።">
                        Select department, batch, and semester to auto-fetch credit hour data.</p>
                    <form method="GET" class="filter-bar">
                        <div class="form-group" style="flex:2;">
                            <label data-en="Department" data-am="ትምህርት ክፍል">Department</label>
                            <select name="department_id" id="deptSelect" onchange="loadBatches()" required>
                                <option value="" data-en="-- Select --" data-am="-- ይምረጡ --">-- Select --</option>
                                <?php foreach ($departments as $d): 
                                    $dept_am = $academic_translations[$d['name']] ?? $d['name'];
                                ?>
                                    <option value="<?php echo $d['id']; ?>" <?php echo ($sel_dept == $d['id']) ? 'selected' : ''; ?>
                                        data-en="<?php echo htmlspecialchars($d['name']); ?>"
                                        data-am="<?php echo htmlspecialchars($dept_am); ?>">
                                        <?php echo htmlspecialchars($d['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label data-en="Year of Study" data-am="የተማሪ ባች">Year of Study</label>
                            <select name="batch" id="batchSelect" required>
                                <option value="" data-en="Select Batch" data-am="ባች ይምረጡ">--</option>
                                <!-- Populated via JS based on department -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label data-en="Semester" data-am="ሴሚስተር">Semester</label>
                            <select name="semester" id="semesterSelect" required>
</select>
                        </div>
                        <button type="submit" class="btn-primary" style="margin-top:10px;">
                            <i class="fas fa-search"></i>
                            <span data-en="Fetch" data-am="አምጣ">Fetch</span>
                        </button>
                    </form>
                </div>

                <!-- Fetched Credit Hour Info -->
                <?php if ($sel_dept > 0 && $sel_batch > 0 && $sel_semester > 0): ?>
                    <?php if ($fetched_credit): ?>
                        <div class="fetched-info">
                            <h3 style="margin:0;"><i class="fas fa-check-circle"></i>
                                <span data-en="Credit Hour Data Found" data-am="የክሬዲት ሰዓት መረጃ ተገኝቷል">Credit Hour Data
                                    Found</span>
                            </h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="value">
                                        <?php echo $fetched_credit['credit_hours']; ?>
                                    </div>
                                    <div class="label" data-en="Credit Hours" data-am="ክሬዲት ሰዓታት">Credit Hours</div>
                                </div>
                                <div class="info-item">
                                    <div class="value">
                                        <?php echo number_format($fetched_credit['cost_per_credit_hour'], 2); ?>
                                    </div>
                                    <div class="label" data-en="Cost/Cr.Hr (Birr)" data-am="ዋጋ/ለክ.ሰ (ብር)">Cost/Cr.Hr (Birr)
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="value">
                                        <?php echo number_format($fetched_credit['credit_hours'] * $fetched_credit['cost_per_credit_hour'], 2); ?>
                                    </div>
                                    <div class="label" data-en="Tuition Fee (Birr)" data-am="የትምህርት ክፍያ (ብር)">Tuition Fee (Birr)
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="value">
                                        <?php echo $fetched_credit['academic_year'] ?: 'N/A'; ?>
                                    </div>
                                    <div class="label" data-en="Academic Year" data-am="የትምህርት ዘመን">Academic Year</div>
                                </div>
                            </div>
                        </div>

                        <!-- Courses from Dept Head -->
                        <?php if (count($courses) > 0): ?>
                            <div class="card mb-20">
                                <h4><i class="fas fa-book"></i>
                                    <span data-en="Courses (from Dept Head)" data-am="ኮርሶች (ከትምህርት ክፍል ሀላፊ)">Courses (from Dept
                                        Head)</span>
                                </h4>
                                <div class="course-list-compact">
                                    <?php foreach ($courses as $c): ?>
                                        <span class="course-chip">
                                            <?php echo htmlspecialchars($c['course_name']); ?>
                                            <span class="ch">(
                                                <?php echo $c['credit_hour']; ?>)
                                            </span>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Update Rate Form -->
                        <div class="card mb-20">
                            <h3><i class="fas fa-edit"></i>
                                <span data-en="Update Rate" data-am="መጠን አዘምን">Update Rate</span>
                            </h3>
                            <div class="rate-form">
                                <form method="POST">
                                    <input type="hidden" name="department_id" value="<?php echo $sel_dept; ?>">
                                    <input type="hidden" name="batch" value="<?php echo $sel_batch; ?>">
                                    <input type="hidden" name="semester" value="<?php echo $sel_semester; ?>">

                                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                                        <div class="form-group">
                                            <label data-en="Credit Hours (auto-fetched)" data-am="ክሬዲት ሰዓታት (በራሱ የመጣ)">Credit Hours
                                                (auto)</label>
                                            <input type="number" value="<?php echo $fetched_credit['credit_hours']; ?>" readonly
                                                style="background:#e9ecef; font-weight:bold;">
                                        </div>
                                        <div class="form-group">
                                            <label data-en="Cost Per Credit Hour (Birr)" data-am="ዋጋ ለ1 ክ.ሰ (ብር)">Cost Per
                                                Cr.Hr (Birr)</label>
                                            <input type="number" name="cost_per_credit_hour" step="0.01" min="1"
                                                value="<?php echo $fetched_credit['cost_per_credit_hour']; ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label data-en="Academic Year" data-am="የትምህርት ዘመን">Academic Year</label>
                                            <input type="text" name="academic_year"
                                                value="<?php echo $fetched_credit['academic_year'] ?: '2017'; ?>" required>
                                        </div>
                                    </div>
                                    <button type="submit" name="save_rate" class="btn-primary"
                                        style="width:100%; margin-top:10px;">
                                        <i class="fas fa-save"></i>
                                        <span data-en="Save Rate" data-am="መጠን አስቀምጥ">Save Rate</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Special Cases -->
                        <?php if (count($special_cases) > 0): ?>
                            <div class="card mb-20">
                                <h3><i class="fas fa-exclamation-triangle" style="color:#e67e22;"></i>
                                    <span data-en="Student Special Cases" data-am="የተማሪ ልዩ ሁኔታዎች">Student Special Cases</span>
                                    <span class="special-tag">
                                        <?php echo count($special_cases); ?> students
                                    </span>
                                </h3>
                                <p style="color:#666; margin-bottom:15px;"
                                    data-en="These students have adjusted credit hours (due to course drops). Their tuition will be calculated using the adjusted value."
                                    data-am="እነዚህ ተማሪዎች የተቀነሰ ክሬዲት ሰዓት አላቸው። የትምህርት ክፍያቸውም በተስተካከለው መጠን ይሰላል።">
                                    These students have adjusted credit hours. Their tuition will use the adjusted value.</p>
                                <table class="rates-table">
                                    <thead>
                                        <tr>
                                            <th data-en="Student" data-am="ተማሪ">Student</th>
                                            <th data-en="ID" data-am="መታወቂያ">ID</th>
                                            <th data-en="Standard Cr.Hr" data-am="መደበኛ ክ.ሰ">Standard</th>
                                            <th data-en="Adjusted Cr.Hr" data-am="የተስተካከለ ክ.ሰ">Adjusted</th>
                                            <th data-en="Dropped" data-am="????">Dropped Courses</th>
                                            <th data-en="Adjusted Tuition" data-am="የተስተካከለ ክፍያ">Tuition</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($special_cases as $sc): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($sc['first_name'] . ' ' . ($sc['middle_name'] ?? '') . ' ' . $sc['last_name']); ?>
                                                </td>
                                                <td>
                                                    <?php echo $sc['student_code']; ?>
                                                </td>
                                                <td>
                                                    <?php echo $fetched_credit['credit_hours']; ?>
                                                </td>
                                                <td style="color:#e67e22; font-weight:bold;">
                                                    <?php echo $sc['adjusted_credit_hours']; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $dropped = json_decode($sc['dropped_courses'], true);
                                                    echo !empty($dropped) ? implode(', ', $dropped) : '-';
                                                    ?>
                                                </td>
                                                <td style="font-weight:bold;">
                                                    <?php echo number_format($sc['adjusted_credit_hours'] * $fetched_credit['cost_per_credit_hour'], 2); ?>
                                                    Birr
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="card mb-20" style="text-align:center; padding:40px;">
                            <i class="fas fa-exclamation-circle" style="font-size:50px; color:#ffc107; margin-bottom:15px;"></i>
                            <h3 data-en="No Credit Hour Data" data-am="የክሬዲት ሰዓት መረጃ የለም">No Credit Hour Data</h3>
                            <p data-en="The Department Head has not yet submitted credit hours for this department/batch/semester combination."
                                data-am="የተመረጠው ትምህርት ክፍል፣ ባች እና ሴሚስተር የክሬዲት ሰዓት በተምህርት ክፍል ኃላፊ ገና አልተላከም።">
                                The Department Head has not yet submitted credit hours for this combination.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>


                <!-- Expense Settings (Universal) -->
                <div class="card mb-20">
                    <h3><i class="fas fa-cogs" style="color:#8e44ad;"></i>
                        <span data-en="Expense Settings (Food, Bed, Medication)" data-am="የወጪ ቅንብሮች (ምግብ፣ መኝታ፣ ህክምና)">Expense Settings (Food, Bed, Medication)</span>
                    </h3>
                    <p style="color:#666; margin-bottom:15px;"
                        data-en="These expenses apply universally to all students. Update when rates change per Ministry directive."
                        data-am="እነዚህ ወጪዎች ለሁሉም ተማሪዎች ተመሳሳይ ናቸው። በሚኒስቴር ትዕዛዝ ሲቀየሩ ያዘምኑ።">
                        These expenses apply universally to all students. Update when rates change per Ministry directive.</p>
                    <form method="POST">
                        <input type="hidden" name="update_expenses" value="1">
                        <table class="rates-table">
                            <thead>
                                <tr>
                                    <th data-en="Expense Type" data-am="የወጪ ዓይነት">Expense Type</th>
                                    <th data-en="Calculation" data-am="ስሌት">Calculation</th>
                                    <th data-en="Amount (Birr)" data-am="መጠን (ብር)">Amount (Birr)</th>
                                    <th data-en="Last Updated" data-am="መጨረሻ የተዘመነ">Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong data-en="Food Expense" data-am="የምግብ ወጪ">Food Expense</strong></td>
                                    <td><input type="text" name="food_label" value="<?php echo htmlspecialchars($exp_settings['food_expense']['calculation_label'] ?? '100 * 30 * 5'); ?>" style="border:1px solid #ddd; padding:6px 10px; border-radius:4px; width:150px;"></td>
                                    <td><input type="number" name="food_expense" step="0.01" min="0" value="<?php echo $exp_settings['food_expense']['setting_value'] ?? 15000; ?>" style="border:2px solid #8e44ad; padding:6px 10px; border-radius:4px; width:120px; font-weight:bold;" required></td>
                                    <td style="color:#888; font-size:13px;"><?php echo isset($exp_settings['food_expense']) ? date('d M Y', strtotime($exp_settings['food_expense']['updated_at'])) : '-'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong data-en="Bed Expense" data-am="የመኝታ ወጪ">Bed Expense</strong></td>
                                    <td><input type="text" name="bed_label" value="<?php echo htmlspecialchars($exp_settings['bed_expense']['calculation_label'] ?? '60 * 5'); ?>" style="border:1px solid #ddd; padding:6px 10px; border-radius:4px; width:150px;"></td>
                                    <td><input type="number" name="bed_expense" step="0.01" min="0" value="<?php echo $exp_settings['bed_expense']['setting_value'] ?? 300; ?>" style="border:2px solid #8e44ad; padding:6px 10px; border-radius:4px; width:120px; font-weight:bold;" required></td>
                                    <td style="color:#888; font-size:13px;"><?php echo isset($exp_settings['bed_expense']) ? date('d M Y', strtotime($exp_settings['bed_expense']['updated_at'])) : '-'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong data-en="Medication Expense" data-am="የህክምና ወጪ">Medication Expense</strong></td>
                                    <td><input type="text" name="med_label" value="<?php echo htmlspecialchars($exp_settings['medication_expense']['calculation_label'] ?? 'Fixed'); ?>" style="border:1px solid #ddd; padding:6px 10px; border-radius:4px; width:150px;"></td>
                                    <td><input type="number" name="medication_expense" step="0.01" min="0" value="<?php echo $exp_settings['medication_expense']['setting_value'] ?? 25; ?>" style="border:2px solid #8e44ad; padding:6px 10px; border-radius:4px; width:120px; font-weight:bold;" required></td>
                                    <td style="color:#888; font-size:13px;"><?php echo isset($exp_settings['medication_expense']) ? date('d M Y', strtotime($exp_settings['medication_expense']['updated_at'])) : '-'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="submit" class="btn-primary" style="margin-top:15px; background:#8e44ad;">
                            <i class="fas fa-save"></i>
                            <span data-en="Save Expense Settings" data-am="የወጪ ቅንብሮችን ያስቀምጡ">Save Expense Settings</span>
                        </button>
                    </form>
                </div>

                <!-- Prepare Cost Share Form Button -->
                <div style="text-align:center; margin: 20px 0;">
                    <button onclick="openFormPreview()" class="btn-primary" style="display:inline-flex; align-items:center; gap:10px; padding:14px 32px; font-size:1.05em; background:linear-gradient(135deg, #0a0044, #3730a3); border-radius:12px; border:none; cursor:pointer; color:#fff; font-weight:700; box-shadow:0 4px 14px rgba(10,0,68,0.25); transition:all 0.3s ease;">
                        <i class="fas fa-file-invoice"></i>
                        <span data-en="Prepare Cost Share Form" data-am="የወጪ መጋራት ቅጽ አዘጋጅ">Prepare Cost Share Form</span>
                    </button>
                </div>

                <!-- All Existing Rates Table -->
                <div class="card">
                    <h3><i class="fas fa-list"></i>
                        <span data-en="All Existing Rates" data-am="ሁሉም የገቡ መጠኖች">All Existing Rates</span>
                    </h3>
                    <?php if (count($rates) > 0): ?>
                        <table class="rates-table">
                            <thead>
                                <tr>
                                    <th data-en="Department" data-am="ትምህርት ክፍል">Department</th>
                                    <th data-en="Year of Study" data-am="የተማሪ ባች">Year of Study</th>
                                    <th data-en="Sem" data-am="ሴሚ.">Sem</th>
                                    <th data-en="Cr.Hrs" data-am="ክ.ሰ.">Cr.Hrs</th>
                                    <th data-en="Cost/Cr.Hr" data-am="ዋጋ/ክ.ሰ">Cost/Cr.Hr</th>
                                    <th data-en="Tuition" data-am="የትምህርት ክፍያ">Tuition</th>
                                    <th data-en="Year" data-am="ዓ.ም">Year</th>
                                    <th data-en="Action" data-am="ድርጊት">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rates as $r): ?>
                                    <tr id="view-row-<?php echo $r['id']; ?>">
                                        <td>
                                            <?php $dept_am = $academic_translations[$r['dept_name']] ?? $r['dept_name']; ?>
                                            <span data-en="<?php echo htmlspecialchars($r['dept_name']); ?>" data-am="<?php echo htmlspecialchars($dept_am); ?>"><?php echo htmlspecialchars($r['dept_name']); ?></span>
                                        </td>
                                        <td>
                                            <?php echo $r['batch'] ?: '-'; ?>
                                        </td>
                                        <td>
                                            <?php echo $r['semester']; ?>
                                        </td>
                                        <td><strong>
                                                <?php echo $r['credit_hours']; ?>
                                            </strong></td>
                                        <td>
                                            <?php echo number_format($r['cost_per_credit_hour'], 2); ?>
                                        </td>
                                        <td style="font-weight:bold;">
                                            <?php echo number_format($r['credit_hours'] * $r['cost_per_credit_hour'], 2); ?>
                                        </td>
                                        <td>
                                            <?php echo $r['academic_year'] ?: '-'; ?>
                                        </td>
                                        <td>
                                            <button type="button" title="Edit"
                                                onclick="document.getElementById('view-row-<?php echo $r['id']; ?>').style.display='none'; document.getElementById('edit-row-<?php echo $r['id']; ?>').style.display='table-row';"
                                                style="background:#3498db; color:#fff; border:none; padding:5px 10px; border-radius:4px; cursor:pointer; font-size:13px; margin-right:4px;">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display:inline;" id="del-rate-<?php echo $r['id']; ?>">
                                                <input type="hidden" name="rate_id" value="<?php echo $r['id']; ?>">
                                                <input type="hidden" name="delete_rate" value="1">
                                                <span id="dri-<?php echo $r['id']; ?>">
                                                    <button type="button" class="btn-danger-sm"
                                                        onclick="document.getElementById('dri-<?php echo $r['id']; ?>').style.display='none'; document.getElementById('drc-<?php echo $r['id']; ?>').style.display='inline';">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </span>
                                                <span id="drc-<?php echo $r['id']; ?>"
                                                    style="display:none; background:#fff3cd; padding:4px 8px; border-radius:5px; border:1px solid #ffc107;">
                                                    <strong style="color:#856404; font-size:12px;" data-en="Delete?"
                                                        data-am="ይሰረዝ?">Delete?</strong>
                                                    <button type="submit"
                                                        style="background:#e74c3c; color:#fff; border:none; padding:3px 8px; border-radius:3px; cursor:pointer; font-size:12px; margin-left:4px;"
                                                        data-en="Yes" data-am="አዎ">Yes</button>
                                                    <button type="button"
                                                        onclick="document.getElementById('drc-<?php echo $r['id']; ?>').style.display='none'; document.getElementById('dri-<?php echo $r['id']; ?>').style.display='inline';"
                                                        style="background:#6c757d; color:#fff; border:none; padding:3px 8px; border-radius:3px; cursor:pointer; font-size:12px; margin-left:3px;"
                                                        data-en="No" data-am="አይ">No</button>
                                                </span>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr id="edit-row-<?php echo $r['id']; ?>" style="display:none; background:#eaf6ff;">
                                        <form method="POST">
                                            <input type="hidden" name="rate_id" value="<?php echo $r['id']; ?>">
                                            <input type="hidden" name="edit_rate" value="1">
                                            <td colspan="4" style="text-align:right; font-weight:bold; color:#555; padding:10px;">
                                                <i class="fas fa-edit" style="color:#3498db;"></i>
                                                <span data-en="Editing rate for" data-am="መጠን ማስተካከል ለ">Editing rate for</span>
                                                <?php echo htmlspecialchars($r['dept_name']); ?> - Yr <?php echo $r['batch']; ?>, Sem <?php echo $r['semester']; ?>
                                            </td>
                                            <td style="padding:10px;">
                                                <input type="number" name="cost_per_credit_hour" step="0.01" min="0"
                                                    value="<?php echo $r['cost_per_credit_hour']; ?>"
                                                    style="width:100px; padding:5px 8px; border:2px solid #3498db; border-radius:4px; font-size:13px;">
                                            </td>
                                            <td style="font-weight:bold; color:#888; padding:10px;">
                                                <?php echo $r['credit_hours']; ?> x <?php echo number_format($r['cost_per_credit_hour'], 2); ?>
                                            </td>
                                            <td style="padding:10px;">
                                                <input type="text" name="academic_year" value="<?php echo htmlspecialchars($r['academic_year']); ?>"
                                                    style="width:80px; padding:5px 8px; border:2px solid #3498db; border-radius:4px; font-size:13px;">
                                            </td>
                                            <td style="white-space:nowrap; padding:10px;">
                                                <button type="submit"
                                                    style="background:#27ae60; color:#fff; border:none; padding:5px 12px; border-radius:4px; cursor:pointer; font-size:13px; margin-right:4px;">
                                                    <i class="fas fa-check"></i> <span data-en="Save" data-am="አስቀምጥ">Save</span>
                                                </button>
                                                <button type="button"
                                                    onclick="document.getElementById('edit-row-<?php echo $r['id']; ?>').style.display='none'; document.getElementById('view-row-<?php echo $r['id']; ?>').style.display='table-row';"
                                                    style="background:#95a5a6; color:#fff; border:none; padding:5px 12px; border-radius:4px; cursor:pointer; font-size:13px;">
                                                    <i class="fas fa-times"></i> <span data-en="Cancel" data-am="ሰርዝ">Cancel</span>
                                                </button>
                                            </td>
                                        </form>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align:center; padding:20px; color:#999;" data-en="No rates have been set yet."
                            data-am="እስካሁን የገባ የክፍያ መጠን የለም።">No rates have been set yet.</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script>
        const selectedBatch = "<?php echo $sel_batch; ?>";

                let currentSemesters = {};
        const selectedSemester = "<?php echo $_GET['semester'] ?? ''; ?>";

        function loadBatches() {
            const deptSelect = document.getElementById('deptSelect');
            const deptId = deptSelect ? deptSelect.value : null;
            const batchSelect = document.getElementById('batchSelect');
            const semesterSelect = document.getElementById('semesterSelect');

            const currentLang = localStorage.getItem('dmu_lang') || 'en';
            if (batchSelect) {
                batchSelect.innerHTML = `<option value="" data-en="All Batches" data-am="ሁሉም ባች">${currentLang === 'am' ? 'ሁሉም ባች' : 'All Batches'}</option>`;
            }
            if (semesterSelect) {
                semesterSelect.innerHTML = `<option value="" data-en="All Sem" data-am="ሁሉም ሴሚስተር">${currentLang === 'am' ? 'ሁሉም ሴሚስተር' : 'All Sem'}</option>`;
            }

            let fetchUrl = `../../api/get_dropdown_options.php?action=get_all_batches`;
            if (deptId) {
                fetchUrl = `../../api/get_dropdown_options.php?action=get_batches&department_id=${deptId}`;
            }

            fetch(fetchUrl)
                .then(response => response.json())
                .then(result => {
                    const batches = result.batches || result;
                    currentSemesters = result.semesters || {};
                    
                    if (batchSelect) {
                        batches.forEach(batch => {
                            const option = document.createElement('option');
                            option.value = batch;
                            option.textContent = 'Batch ' + batch;
                            option.setAttribute('data-en', 'Batch ' + batch);
                            option.setAttribute('data-am', 'ባች ' + batch);
                            if (currentLang === 'am') {
                                option.textContent = 'ባች ' + batch;
                            }
                            if (typeof selectedBatch !== 'undefined' && batch == selectedBatch) option.selected = true;
                            // check if form has year attribute instead of selectedBatch
                            const yearInput = document.getElementById('yearSelect');
                            if (yearInput && typeof selectedYear !== 'undefined' && batch == selectedYear) option.selected = true;
                            
                            batchSelect.appendChild(option);
                        });
                        
                        loadSemesters();
                    } else {
                        // For add_student and manage_credit_hours which use yearSelect
                        const yearSelect = document.getElementById('yearSelect');
                        if (yearSelect) {
                            batches.forEach(batch => {
                                const option = document.createElement('option');
                                option.value = batch;
                                option.textContent = result.dept_type === 'freshman' ? "1 (Freshman)" : batch;
                                option.setAttribute('data-en', result.dept_type === 'freshman' ? "1 (Freshman)" : batch);
                                option.setAttribute('data-am', result.dept_type === 'freshman' ? "1 (ፍሬሽማን)" : batch);
                                if (currentLang === 'am' && result.dept_type === 'freshman') {
                                    option.textContent = '1 (ፍሬሽማን)';
                                }
                                yearSelect.appendChild(option);
                            });
                            loadSemesters();
                        }
                    }
                });
        }

        function loadSemesters() {
            const batchSelect = document.getElementById('batchSelect') || document.getElementById('yearSelect');
            const semesterSelect = document.getElementById('semesterSelect');
            if (!semesterSelect) return;
            
            const batch = batchSelect ? batchSelect.value : null;
            const currentLang = localStorage.getItem('dmu_lang') || 'en';
            
            // clear it only if it's a filter, if it's required (form), maybe don't put 'All Sem'
            const isRequired = semesterSelect.hasAttribute('required');
            if (!isRequired) {
                semesterSelect.innerHTML = `<option value="" data-en="All Sem" data-am="ሁሉም ሴሚስተር">${currentLang === 'am' ? 'ሁሉም ሴሚስተር' : 'All Sem'}</option>`;
            } else {
                semesterSelect.innerHTML = '';
            }

            if (batch && currentSemesters[batch]) {
                currentSemesters[batch].forEach(sem => {
                    const option = document.createElement('option');
                    option.value = sem;
                    option.textContent = sem;
                    if (typeof selectedSemester !== 'undefined' && sem == selectedSemester) option.selected = true;
                    semesterSelect.appendChild(option);
                });
            } else if (!batch || !currentSemesters[batch]) {
                // Default if no batch selected but dept is selected
                ['1', '2'].forEach(sem => {
                    const option = document.createElement('option');
                    option.value = sem;
                    option.textContent = sem;
                    if (typeof selectedSemester !== 'undefined' && sem == selectedSemester) option.selected = true;
                    semesterSelect.appendChild(option);
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const batchSelect = document.getElementById('batchSelect');
            if (batchSelect) {
                batchSelect.addEventListener('change', loadSemesters);
            }
            const yearSelect = document.getElementById('yearSelect');
            if (yearSelect) {
                yearSelect.addEventListener('change', loadSemesters);
            }
        });

        // Load batches on page load if department pre-selected
        if (document.getElementById('deptSelect').value) loadBatches();
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>

    <!-- Form Preview Modal -->
    <div id="formPreviewModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px);">
        <div style="position:relative; width:92%; max-width:1100px; height:90vh; margin:5vh auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.3); animation:modalIn 0.3s ease;">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 22px; background:linear-gradient(135deg,#0a0044,#3730a3); color:#fff;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-file-invoice" style="font-size:1.2rem;"></i>
                    <strong data-en="Cost Share Form Preview" data-am="የወጪ መጋራት ቅጽ ቅድመ-እይታ">Cost Share Form Preview</strong>
                </div>
                <button onclick="closeFormPreview()" style="background:rgba(255,255,255,0.15); border:none; color:#fff; width:36px; height:36px; border-radius:50%; cursor:pointer; font-size:1.1rem; display:flex; align-items:center; justify-content:center; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <iframe id="formPreviewIframe" src="" style="width:100%; height:calc(100% - 56px); border:none;"></iframe>
        </div>
    </div>
    <style>
        @keyframes modalIn { from { opacity:0; transform:scale(0.95) translateY(20px); } to { opacity:1; transform:scale(1) translateY(0); } }
    </style>
    <script>
        function openFormPreview() {
            document.getElementById('formPreviewIframe').src = 'prepare_form.php?embed=1';
            document.getElementById('formPreviewModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        function closeFormPreview() {
            document.getElementById('formPreviewModal').style.display = 'none';
            document.getElementById('formPreviewIframe').src = '';
            document.body.style.overflow = '';
        }
        document.getElementById('formPreviewModal').addEventListener('click', function(e) {
            if (e.target === this) closeFormPreview();
        });
    </script>

</html>
