<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['registrar']);

require_once '../../includes/academic_translations.php';

// PRG: Read flash messages from session
$msg = $_SESSION['flash_success'] ?? "";
unset($_SESSION['flash_success']);
$error = "";

// Fetch Departments with study_years
$departments = $pdo->query("SELECT id, name, study_years FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Handle Submit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_credit_hour'])) {
    $dept_id = $_POST['department_id'];
    $ac_year = $_POST['academic_year'];
    $batch = $_POST['batch'];
    $semester = $_POST['semester'];
    $credit_hour = $_POST['credit_hour'];
    $min_credit = (int) $_POST['min_credit_hours'];
    $max_credit = (int) $_POST['max_credit_hours'];
    $sem_min_credit = (int) $_POST['semester_min_credits'];
    $sem_max_credit = (int) $_POST['semester_max_credits'];


    // Validation
    if (empty($dept_id) || empty($ac_year) || empty($batch) || empty($semester) || empty($credit_hour)) {
        $error = "<span data-en='All fields are required.' data-am='ሁሉንም የግድ መሙላት አለብዎት'>All fields are required.</span>";
    } elseif ($min_credit < 1 || $max_credit < 1) {
        $error = "<span data-en='Course Min and Max credit hour must be at least 1.' data-am='የኮርሱ ዝቅተኛ እና ከፍተኛ ክሬዲት ሰዓት ቢያንስ 1 መሆን አለበት'>Course Min and Max credit hour must be at least 1.</span>";
    } elseif ($min_credit > $max_credit) {
        $error = "<span data-en='Course Minimum credit hour cannot be greater than Maximum.' data-am='የኮርሱ ዝቅተኛ ክሬዲት ሰዓት ከከፍተኛው በላይ ሊሆን አይችልም'>Course Minimum credit hour cannot be greater than Maximum.</span>";
    } elseif ($sem_min_credit > $sem_max_credit) {
        $error = "<span data-en='Semester Minimum credit hour cannot be greater than Maximum.' data-am='የሴሚስተር ዝቅተኛ ክሬዲት ሰዓት ከከፍተኛው በላይ ሊሆን አይችልም'>Semester Minimum credit hour cannot be greater than Maximum.</span>";
    } elseif ($credit_hour < $sem_min_credit || $credit_hour > $sem_max_credit) {
        $error = "<span data-en='Total Billing Credit Hour ($credit_hour) must be between Semester Min ($sem_min_credit) and Max ($sem_max_credit).' data-am='ጠቅላላ የክፍያ ክሬዲት ሰዓት ($credit_hour) በሴሚስተር ዝቅተኛ ($sem_min_credit) እና ከፍተኛ ($sem_max_credit) መካከል መሆን አለበት'>Total Billing Credit Hour ($credit_hour) must be between Semester Min ($sem_min_credit) and Max ($sem_max_credit).</span>";
    } else {
        // Check Duplicate (ignore academic_year so one combination of dept+batch+sem exists)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses 
                               WHERE department_id = ? AND batch = ? AND semester = ?");
        $stmt->execute([$dept_id, $batch, $semester]);
        if ($stmt->fetchColumn() > 0) {
            $error = "<span data-en='Credit hour already exists for this combination. Please edit the existing record.' data-am='ለዚህ ጥምረት የክሬዲት ሰዓት ቀድሞውኑ አለ። እባክዎ ያለውን መዝገብ ያርትዑ'>Credit hour already exists for this combination.</span>";
        } else {
            // Insert
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO courses 
                    (department_id, academic_year, batch, semester, credit_hours, min_credit_hours, max_credit_hours, semester_min_credits, semester_max_credits, rate_status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending_Dept')");
                $stmt->execute([$dept_id, $ac_year, $batch, $semester, $credit_hour, $min_credit, $max_credit, $sem_min_credit, $sem_max_credit]);

                // Auto-create cost_sharing_agreements for students in this dept/batch/semester
                $students = $pdo->prepare("SELECT user_id FROM students WHERE department_id = ? AND batch = ?");
                $students->execute([$dept_id, $batch]);

                $tuition = 0;
                $food = 0;
                $bed = 0;
                $med = 0;

                $stmt_check = $pdo->prepare("SELECT id FROM cost_sharing_agreements 
                                             WHERE student_id = ? AND academic_year = ? AND semester = ?");

                $stmt_insert = $pdo->prepare("INSERT INTO cost_sharing_agreements (student_id, academic_year, semester, tuition_fee, food_expense, bed_expense, medication_expense, recorded_by, status) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?, NULL, 'Pending')");

                foreach ($students->fetchAll(PDO::FETCH_ASSOC) as $stu) {
                    $stmt_check->execute([$stu['user_id'], $ac_year, $semester]);
                    if ($stmt_check->fetchColumn() == 0) {
                        $stmt_insert->execute([$stu['user_id'], $ac_year, $semester, $tuition, $food, $bed, $med]);
                        // Recalculate cumulative total_amount for this student
                        $pdo->prepare("UPDATE cost_sharing_agreements SET total_amount = (SELECT t.total FROM (SELECT COALESCE(SUM(tuition_fee + food_expense + bed_expense + medication_expense), 0) as total FROM cost_sharing_agreements WHERE student_id = ?) as t) WHERE student_id = ?")->execute([$stu['user_id'], $stu['user_id']]);
                    }
                }

                $pdo->commit();
                $_SESSION['flash_success'] = "<span data-en='Credit hour sent successfully to Department Head.' data-am='የክሬዲት ሰዓት ለዲፓርትመንት ኃላፊ በተሳካ ሁኔታ ተልኳል።'>Credit hour sent successfully to Department Head.</span>";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "<span data-en='Database Error: " . $e->getMessage() . "' data-am='የውሂብ ጎታ ስህተት: " . $e->getMessage() . "'>Database Error: " . $e->getMessage() . "</span>";
            }
        }
    }
}

// Handle Edit Submit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_credit_hour'])) {
    $id = $_POST['course_id'];
    $ac_year = $_POST['academic_year'];
    $credit_hour = $_POST['credit_hour'];
    $min_credit = (int) $_POST['min_credit_hours'];
    $max_credit = (int) $_POST['max_credit_hours'];
    $sem_min_credit = (int) $_POST['semester_min_credits'];
    $sem_max_credit = (int) $_POST['semester_max_credits'];

    if (empty($ac_year) || empty($credit_hour)) {
        $error = "<span data-en='All fields are required.' data-am='ሁሉንም የግድ መሙላት አለብዎት'>All fields are required.</span>";
    } elseif ($min_credit < 1 || $max_credit < 1) {
        $error = "<span data-en='Course Min and Max credit hour must be at least 1.' data-am='የኮርስ ዝቅተኛ እና ከፍተኛ ክሬዲት ሰዓት ቢያንስ 1 መሆን አለበት'>Course Min and Max credit hour must be at least 1.</span>";
    } elseif ($min_credit > $max_credit) {
        $error = "<span data-en='Course Minimum credit hour cannot be greater than Maximum.' data-am='የኮርስ ዝቅተኛ ክሬዲት ሰዓት ከከፍተኛው በላይ ሊሆን አይችልም'>Course Minimum credit hour cannot be greater than Maximum.</span>";
    } elseif ($sem_min_credit > $sem_max_credit) {
        $error = "<span data-en='Semester Minimum credit hour cannot be greater than Maximum.' data-am='የሴሚስተር ዝቅተኛ ክሬዲት ሰዓት ከከፍተኛው በላይ ሊሆን አይችልም'>Semester Minimum credit hour cannot be greater than Maximum.</span>";
    } elseif ($credit_hour < $sem_min_credit || $credit_hour > $sem_max_credit) {
        $error = "<span data-en='Total Billing Credit Hour ($credit_hour) must be between Semester Min ($sem_min_credit) and Max ($sem_max_credit).' data-am='ጠቅላላ የክፍያ ክሬዲት ሰዓት ($credit_hour) በሴሚስተር ዝቅተኛ ($sem_min_credit) እና ከፍተኛ ($sem_max_credit) መካከል መሆን አለበት'>Total Billing Credit Hour ($credit_hour) must be between Semester Min ($sem_min_credit) and Max ($sem_max_credit).</span>";
    } else {
        try {
            $pdo->prepare("UPDATE courses 
                           SET academic_year = ?, credit_hours = ?, min_credit_hours = ?, max_credit_hours = ?, semester_min_credits = ?, semester_max_credits = ?, rate_status = 'Pending_Dept' 
                           WHERE id = ?")->execute([$ac_year, $credit_hour, $min_credit, $max_credit, $sem_min_credit, $sem_max_credit, $id]);
            $_SESSION['flash_success'] = "<span data-en='Credit hour updated and sent to Department Head.' data-am='የክሬዲት ሰዓት ለዲፓርትመንት ኃላፊ በተሳካ ሁኔታ ተልኳል።'>Credit hour updated and sent to Department Head.</span>";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } catch (PDOException $e) {
            $error = "<span data-en='Database Error: " . $e->getMessage() . "' data-am='የውሂብ ጎታ ስህተት: " . $e->getMessage() . "'>Database Error: " . $e->getMessage() . "</span>";
        }
    }
}

// Handle Delete Submit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_credit_hour'])) {
    $id = $_POST['course_id'];
    try {
        $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);
        $_SESSION['flash_success'] = "<span data-en='Credit hour record deleted successfully.' data-am='የክሬዲት ሰዓት መዝገብ በተሳካ ሁኔታ ተሰርዟል።'>Credit hour record deleted successfully.</span>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        $error = "<span data-en='Database Error: " . $e->getMessage() . "' data-am='የውሂብ ጎታ ስህተት: " . $e->getMessage() . "'>Database Error: " . $e->getMessage() . "</span>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Send Credit Hour - Registrar" data-am="የክሬዲት ሰዓት ይላኩ - ሬጅስትራር">Send Credit Hour - Registrar</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>
            <div class="main-content">
                <div class="top-bar">
                    <h2 data-en="Send Credit Hour to Dept Head" data-am="ለዲፓርትመንት ኃላፊ የክሬዲት ሰዓት ይላኩ">Send Credit Hour
                        to Dept Head</h2>
                </div>

                <?php if ($msg): ?>
                    <div class="success-msg">
                        <?php echo $msg; ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="error-msg">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="card" style="max-width: 800px; margin: 0 auto;">
                    <form method="POST">
                        <div class="form-group">
                            <label data-en="Department" data-am="ዲፓርትመንት">Department</label>
                            <select name="department_id" id="deptSelect" onchange="loadBatches()" required>
                                <option value="" data-en="-- Select --" data-am="-- ይምረጡ --">-- Select --</option>
                                <?php foreach ($departments as $d):
                                    $d_name_en = $d['name'];
                                    $d_name_am = $academic_translations[$d['name']] ?? $d['name'];
                                    ?>
                                    <option value="<?php echo $d['id']; ?>"
                                        data-study-years="<?php echo $d['study_years']; ?>"
                                        data-en="<?php echo htmlspecialchars($d_name_en); ?>"
                                        data-am="<?php echo htmlspecialchars($d_name_am); ?>">
                                        <?php echo htmlspecialchars($d_name_en); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group three-col"
                            style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                            <div>
                                <label data-en="Academic Year" data-am="የትምህርት ዘመን">Academic Year</label>
                                <input type="text" name="academic_year" placeholder="e.g. 2017"
                                    data-en-placeholder="e.g. 2017" data-am-placeholder="የትምህርት ዘመን 2017" required>
                            </div>
                            <div>
                                <label data-en="Year of Study" data-am="የጥናት ዓመት">Year of Study</label>
                                <select name="batch" id="batchSelect" onchange="onBatchChange()" required disabled
                                    style="opacity:0.5; cursor:not-allowed;">
                                    <option value="" data-en="-- Select Department First --"
                                        data-am="-- መጀመሪያ ዲፓርትመንት ይምረጡ --">-- Select Department First --</option>
                                </select>
                            </div>
                            <div>
                                <label data-en="Semester" data-am="ሴሚስተር">Semester</label>
                                <select name="semester" id="semesterSelect" required disabled
                                    style="opacity:0.5; cursor:not-allowed;">
                                </select>
                            </div>
                        </div>

                        <div class="form-group"
                            style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top:15px;">
                            <div>
                                <label data-en="Min Credit Hour (Per Course)" data-am="ዝቅተኛ የክሬዲት ሰዓት (ለእያንዳንዱ ኮርስ)">Min
                                    Credit Hour (Per Course)</label>
                                <input type="number" name="min_credit_hours" min="1" max="20" value="2" required>
                            </div>
                            <div>
                                <label data-en="Max Credit Hour (Per Course)" data-am="ከፍተኛ የክሬዲት ሰዓት (ለእያንዳንዱ ኮርስ)">Max
                                    Credit Hour (Per Course)</label>
                                <input type="number" name="max_credit_hours" min="1" max="20" value="5" required>
                            </div>
                        </div>

                        <div class="form-group"
                            style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-top:15px;">
                            <div>
                                <label data-en="Semester Min Credits (Total)" data-am="ሴሚስተር ዝቅተኛ የክሬዲት ሰዓት (ጠቅላላ)">Semester
                                    Min</label>
                                <input type="number" name="semester_min_credits" min="0" max="50" value="15" required>
                            </div>
                            <div>
                                <label data-en="Semester Max Credits (Total)" data-am="ሴሚስተር ከፍተኛ የክሬዲት ሰዓት (ጠቅላላ)">Semester
                                    Max</label>
                                <input type="number" name="semester_max_credits" min="0" max="60" value="21" required>
                            </div>
                            <div>
                                <label data-en="Total Billing Credit Hour" data-am="ጠቅላላ ክፍያ የክሬዲት ሰዓት">Billing
                                    Cr.Hr</label>
                                <input type="number" name="credit_hour" min="1" max="60" value="18" required>
                            </div>
                        </div>


                        <button type="submit" name="send_credit_hour" class="btn-primary" style="width: 25%;"
                            data-en="Send Credit Hour" data-am="ክሬዲት ሰዓት ይላኩ">
                            <i class="fas fa-paper-plane"></i> Send Credit Hour
                        </button>
                    </form>
                </div>

                <!-- Existing Records -->
                <div class="card" style="margin-top: 20px;">
                    <h3><i class="fas fa-list"></i>
                        <span data-en="Existing Credit Hour Records" data-am="ከዚህ በፊት የነበሩ ክሬዲት ሰአት መዝገብ">Existing
                            Records</span>
                    </h3>
                    <?php
                    $existing = $pdo->query("SELECT r.*, d.name as dept_name 
                                             FROM courses r 
                                             JOIN departments d ON r.department_id = d.id 
                                             WHERE r.credit_hours IS NOT NULL AND r.credit_hours > 0
                                             ORDER BY d.name, r.batch, r.semester")->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <?php if (count($existing) > 0): ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th data-en="Department" data-am="ትምህርት ክፍል">Department</th>
                                    <th data-en="Year" data-am="ዓመት">Year</th>
                                    <th data-en="Batch" data-am="የጥናት አመት">Batch</th>
                                    <th data-en="Sem" data-am="ሴሚስተር">Semester</th>
                                    <th data-en="Cr.Hrs" data-am="ክ.ሰዓት">Credit Hour</th>
                                    <th data-en="Min" data-am="ዝቅተኛ ክ.ሰዓት">Minimum Credit Hour</th>
                                    <th data-en="Max" data-am="ከፍተኛ ክ.ሰዓት">Maximum Credit Hour</th>
                                    <th data-en="Sem Min" data-am="ሴሚስተር ዝ.ዝ.ሰዓት">Semester Minimum Credit Hour</th>
                                    <th data-en="Sem Max" data-am="ሴሚስተር ከ.ከ.ሰዓት">Semester Maximum Credit Hour</th>
                                    <th data-en="Status" data-am="ሁኔታ">Status</th>
                                    <th data-en="Action" data-am="ድርጊት">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($existing as $r): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $dept_am = $academic_translations[$r['dept_name']] ?? $r['dept_name'];
                                            ?>
                                            <span data-en="<?php echo htmlspecialchars($r['dept_name']); ?>"
                                                data-am="<?php echo htmlspecialchars($dept_am); ?>">
                                                <?php echo htmlspecialchars($r['dept_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $r['academic_year'] ?: '-'; ?>
                                        </td>
                                        <td>
                                            <?php echo $r['batch']; ?>
                                        </td>
                                        <td>
                                            <?php echo $r['semester']; ?>
                                        </td>
                                        <td><strong>
                                                <?php echo $r['credit_hours']; ?>
                                            </strong></td>
                                        <td>
                                            <?php echo $r['min_credit_hours']; ?>
                                        </td>
                                        <td>
                                            <?php echo $r['max_credit_hours']; ?>
                                        </td>
                                        <td>
                                            <?php echo $r['semester_min_credits']; ?>
                                        </td>
                                        <td>
                                            <?php echo $r['semester_max_credits']; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $st = $r['rate_status'] ?? 'Pending_Dept';
                                            $bg = ($st == 'Submitted') ? '#28a745' : '#6c757d';
                                            ?>
                                            <span
                                                style="background:<?php echo $bg; ?>; color:#fff; padding:3px 8px; border-radius:10px; font-size:12px;">
                                                <?php echo $st; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button onclick='openEditModal(<?php echo htmlspecialchars(json_encode($r), JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class='btn-secondary' style='padding: 3px 8px; font-size: 11px; margin-bottom:4px;' data-en='Edit' data-am='አስተካክል'>Edit</button>
                                            <form style="display:inline-block;" method="POST" onsubmit="event.preventDefault(); var form = this; Swal.fire({title:'Are you sure?', data-am='ይህን መዝገብ መሰረዝ ይፈልጋሉ?', text: data-en='Do you want to delete this record?', data-am='ይህን መዝገብ መሰረዝ ይፈልጋሉ?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3085d6', confirmButtonText: 'Yes, delete it!'}).then((result) => { if (result.isConfirmed) { form.submit(); } });">
                                                <input type="hidden" name="course_id" value="<?php echo $r['id']; ?>">
                                                <button type="submit" name="delete_credit_hour" class='btn-danger' style='padding: 3px 8px; font-size: 11px;' data-en='Delete' data-am='ሰርዝ'>Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align:center; padding:20px; color:#999;" data-en="No records yet."
                            data-am="እስካሁን ምንም የተመዘገበ የለም።">No records yet.</p>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 data-en="Edit Credit Hour" data-am="የክሬዲት ሰዓት ማስተካከያ">Edit Credit Hour</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="course_id" id="edit_course_id">
                    
                    <div class="form-group three-col" style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                        <div>
                            <label data-en="Department" data-am="ትምህርት ክፍል">Department</label>
                            <input type="text" id="edit_dept_name" readonly style="background:#f0f0f0;" disabled>
                        </div>
                        <div>
                            <label data-en="Year of Study" data-am="የጥናት ዓመት">Year of Study</label>
                            <input type="text" id="edit_batch" readonly style="background:#f0f0f0;" disabled>
                        </div>
                        <div>
                            <label data-en="Semester" data-am="ሴሚስተር">Semester</label>
                            <input type="text" id="edit_semester" readonly style="background:#f0f0f0;" disabled>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:15px;">
                        <label data-en="Academic Year" data-am="የትምህርት ዘመን">Academic Year</label>
                        <input type="text" name="academic_year" id="edit_academic_year" required>
                    </div>

                    <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top:15px;">
                        <div>
                            <label data-en="Min Credit Hour (Per Course)" data-am="ዝቅተኛ የክሬዲት ሰዓት (ለእያንዳንዱ ትምህርት)">Min Credit Hour (Per Course)</label>
                            <input type="number" name="min_credit_hours" id="edit_min_credit_hours" min="1" max="20" required>
                        </div>
                        <div>
                            <label data-en="Max Credit Hour (Per Course)" data-am="ከፍተኛ የክሬዲት ሰዓት (ለእያንዳንዱ ትምህርት)">Max Credit Hour (Per Course)</label>
                            <input type="number" name="max_credit_hours" id="edit_max_credit_hours" min="1" max="20" required>
                        </div>
                    </div>

                    <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-top:15px;">
                        <div>
                            <label data-en="Semester Min Credits (Total)" data-am="ዝቅተኛ የሴሚስተር ክሬዲት (ጠቅላላ)">Semester Min</label>
                            <input type="number" name="semester_min_credits" id="edit_sem_min" min="0" max="50" required>
                        </div>
                        <div>
                            <label data-en="Semester Max Credits (Total)" data-am="ከፍተኛ የሴሚስተር ክሬዲት (ጠቅላላ)">Semester Max</label>
                            <input type="number" name="semester_max_credits" id="edit_sem_max" min="0" max="60" required>
                        </div>
                        <div>
                            <label data-en="Total Billing Credit Hour" data-am="ጠቅላላ የክፍያ ክሬዲት ሰዓት">Billing Cr.Hr</label>
                            <input type="number" name="credit_hour" id="edit_credit_hour" min="1" max="60" required>
                        </div>
                    </div>

                    <button type="submit" name="edit_credit_hour" class="btn-primary" style="width: 100%; margin-top:15px;" data-en="Update and Send to Dept Head" data-am="አድስ እና ለትምህርት ክፍሉ ኃላፊ ላክ">
                        <i class="fas fa-save"></i> Update and Send to Dept Head
                    </button>
                </form>
            </div>
        </div>
    </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script>
        function openEditModal(record) {
            document.getElementById('edit_course_id').value = record.id;
            document.getElementById('edit_dept_name').value = record.dept_name;
            document.getElementById('edit_batch').value = 'Batch ' + record.batch;
            document.getElementById('edit_semester').value = 'Sem ' + record.semester;
            
            document.getElementById('edit_academic_year').value = record.academic_year || '';
            document.getElementById('edit_min_credit_hours').value = record.min_credit_hours;
            document.getElementById('edit_max_credit_hours').value = record.max_credit_hours;
            document.getElementById('edit_sem_min').value = record.semester_min_credits;
            document.getElementById('edit_sem_max').value = record.semester_max_credits;
            document.getElementById('edit_credit_hour').value = record.credit_hours;
            
            document.getElementById('editModal').style.display = 'block';
            if(typeof updateLanguage === 'function') updateLanguage();
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        window.onclick = function(event) {
            let modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }

        let currentSemesters = {};
        const selectedSemester = "<?php echo $_GET['semester'] ?? ''; ?>";

        function loadBatches() {
            const deptSelect = document.getElementById('deptSelect');
            const deptId = deptSelect ? deptSelect.value : null;
            const batchSelect = document.getElementById('batchSelect');
            const semesterSelect = document.getElementById('semesterSelect');

            const currentLang = localStorage.getItem('dmu_lang') || 'en';
            if (batchSelect) {
                batchSelect.innerHTML = `<option value="" data-en="All Batches" data-am="ሁሉንም ዓመታት">${currentLang === 'am' ? 'ሁሉንም ዓመታት' : 'All Batches'}</option>`;
            }
            if (semesterSelect) {
                semesterSelect.innerHTML = `<option value="" data-en="All Sem" data-am="ሁሉንም ሴሚስተር">${currentLang === 'am' ? 'ሁሉንም ሴሚስተር' : 'All Sem'}</option>`;
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
                        batchSelect.disabled = false;
                        batchSelect.style.opacity = '1';
                        batchSelect.style.cursor = 'pointer';
                        
                        batches.forEach(batch => {
                            const option = document.createElement('option');
                            option.value = batch;
                            option.textContent = 'Batch ' + batch;
                            option.setAttribute('data-en', 'Batch ' + batch);
                            option.setAttribute('data-am', 'ዓመት ' + batch);
                            if (currentLang === 'am') {
                                option.textContent = 'ዓመት ' + batch;
                            }
                            if (typeof selectedBatch !== 'undefined' && batch == selectedBatch) option.selected = true;
                            // check if form has year attribute instead of selectedBatch
                            const yearInput = document.getElementById('yearSelect');
                            if (yearInput && typeof selectedYear !== 'undefined' && batch == selectedYear) option.selected = true;
                            
                            batchSelect.appendChild(option);
                        });
                        
                        // Automatically load semesters if batch is already selected
                        if (typeof selectedBatch !== 'undefined' && selectedBatch) {
                            loadSemesters();
                        }
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
                semesterSelect.innerHTML = `<option value="" data-en="All Sem" data-am="ሁሉንም ሴሚስተር">${currentLang === 'am' ? 'ሁሉንም ሴሚስተር' : 'All Sem'}</option>`;
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

        function onBatchChange() {
            const batchSelect = document.getElementById('batchSelect');
            const semesterSelect = document.getElementById('semesterSelect');

            if (batchSelect.value) {
                semesterSelect.disabled = false;
                semesterSelect.style.opacity = '1';
                semesterSelect.style.cursor = 'pointer';
                loadSemesters();
            } else {
                semesterSelect.value = '';
                semesterSelect.disabled = true;
                semesterSelect.style.opacity = '0.5';
                semesterSelect.style.cursor = 'not-allowed';
            }
        }
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>
