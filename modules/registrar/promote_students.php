<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['registrar']);

require_once '../../includes/academic_translations.php';

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['promote'])) {
    $dept_id = $_POST['department_id'];
    $current_batch = $_POST['current_batch'];

    $new_batch = $_POST['new_batch'];
    $new_semester = $_POST['new_semester'];
    $new_academic_year = $_POST['new_academic_year']; // Calendar Year e.g. 2017

    if (empty($dept_id) || empty($current_batch) || empty($new_batch) || empty($new_semester)) {
        $error = "<span data-en='All fields are required.' data-am='ሁሉም ቦታዎች ያስፈልጋሉ።'>All fields are required.</span>";
    } else {
        try {
            $pdo->beginTransaction();

            if ($new_batch === 'Graduated') {
                // When graduating: keep batch and semester as-is, only set status to 'graduated'
                // Note: users.status stays 'active' (account-level) so students can still log in
                $sql = "UPDATE students 
                        SET status = 'graduated', academic_year = ? 
                        WHERE department_id = ? AND batch = ? AND status = 'active'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$new_academic_year, $dept_id, $current_batch]);
                $count = $stmt->rowCount();
            } else {
                // Normal promotion: just update batch, semester, and academic year
                $sql = "UPDATE students 
                        SET batch = ?, current_semester = ?, academic_year = ? 
                        WHERE department_id = ? AND batch = ? AND status = 'active'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$new_batch, $new_semester, $new_academic_year, $dept_id, $current_batch]);
                $count = $stmt->rowCount();
            }

            $pdo->commit();

            if ($count > 0) {
                $action_label = ($new_batch === 'Graduated') 
                    ? "<span data-en='Successfully graduated' data-am='በተሳካ ሁኔታ ተመርቀዋል'>Successfully graduated</span>" 
                    : "<span data-en='Successfully promoted' data-am='በተሳካ ሁኔታ አድገዋል'>Successfully promoted</span>";
                $_SESSION["flash_success"] = "$action_label $count <span data-en='students' data-am='ተማሪዎች'>students</span>.";
                header("Location: " . $_SERVER["PHP_SELF"]);
                exit();
            } else {
                $error = "<span data-en='No students found matching the criteria or no changes made.' data-am='ከመስፈርቱ ጋር የሚስማሙ ተማሪዎች አልተገኙም ወይም ምንም ለውጥ አልተደረገም።'>No students found matching the criteria or no changes made.</span>";
            }

        } catch (PDOException $e) {
            $error = "<span data-en='Database Error: " . $e->getMessage() . "' data-am='የውሂብ ጎታ ስህተት: " . $e->getMessage() . "'>Database Error: " . $e->getMessage() . "</span>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Promote Students - Registrar" data-am="ተማሪዎችን ደረጃ ያሳድጉ - ሬጅስትራር">Promote Students - Registrar
    </title>
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
                    <h2 data-en="Promote Students" data-am="ተማሪዎችን ደረጃ ያሳድጉ">Promote Students</h2>
                </div>

                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>

                <div class="card">
                    <div class="card">
                        <h3 data-en="Bulk Promotion Form" data-am="የጅምላ ማስተካከያ ቅጽ">Bulk Promotion Form</h3>
                        <p style="margin-bottom: 20px; color: #666;"
                            data-en="Select a group of students (by Department and current Batch) and move them to a new Batch/Semester."
                            data-am="የተማሪዎችን ቡድን (በትምህርት ክፍል እና የአሁኑ ባች) ይምረጡ እና ወደ አዲስ ባች/ሴሚስተር ያንቀሳቅሷቸው።">
                            Select a group of students (by Department and current Batch) and move them to a new
                            Batch/Semester.
                        </p>

                        <form method="POST">
                            <div
                                style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                                <div style="flex: 1; min-width: 250px;">
                                    <h4 style="margin-bottom: 15px; color: var(--primary-color);"
                                        data-en="1. Select Target Cohort" data-am="1. የታለመውን ቡድን ይምረጡ">1. Select Target
                                        Cohort
                                    </h4>
                                    <div class="form-group">
                                        <label data-en="Department" data-am="ትምህርት ክፍል">Department</label>
                                        <select name="department_id" required>
                                            <option value="" data-en="Select Department" data-am="ትምህርት ክፍል ይምረጡ">Select
                                                Department</option>
                                            <?php foreach ($departments as $d):
                                                $d_name_en = $d['name'];
                                                $d_name_am = $academic_translations[$d_name_en] ?? $d_name_en;
                                                ?>
                                                <option value="<?php echo $d['id']; ?>"
                                                    data-en="<?php echo htmlspecialchars($d_name_en); ?>"
                                                    data-am="<?php echo htmlspecialchars($d_name_am); ?>">
                                                    <?php echo htmlspecialchars($d_name_en); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label data-en="Current Batch (Year)" data-am="የአሁኑ ባች (ዓመት)">Current Batch
                                            (Year)</label>
                                        <select name="current_batch" required>
                                            <option value="" data-en="Select Current Batch" data-am="የአሁኑን ባች ይምረጡ">
                                                Select Current Batch</option>
                                            <option value="1" data-en="1 (Freshman)" data-am="1 (ፍሬሽማን)">1 (Freshman)
                                            </option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                            <option value="6">6</option>
                                        </select>
                                    </div>
                                </div>

                                <div style="flex: 1; min-width: 250px;">
                                    <h4 style="margin-bottom: 15px; color: var(--success-color);"
                                        data-en="2. Promotion Details (New Status)"
                                        data-am="2. የማስተዋወቅ ዝርዝሮች (አዲስ ሁኔታ)">2. Promotion Details (New
                                        Status)</h4>
                                    <div class="form-group">
                                        <label data-en="New Batch (Year)" data-am="አዲስ ባች (ዓመት)">New Batch
                                            (Year)</label>
                                        <select name="new_batch" required>
                                            <option value="" data-en="Select New Batch" data-am="አዲስ ባች ይምረጡ">Select New
                                                Batch</option>
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                            <option value="6">6</option>
                                            <option value="Graduated" data-en="Graduated" data-am="ተመራቂ">Graduated
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label data-en="New Semester" data-am="አዲስ ሴሚስተር">New Semester</label>
                                        <select name="new_semester" required>
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label data-en="New Academic Year (Calendar)" data-am="አዲስ የትምህርት ዘመን">New
                                            Academic Year (Calendar)</label>
                                        <input type="text" name="academic_year" class="form-control"
                                            placeholder="e.g., 2017" data-en="e.g., 2017"
                                            data-en-placeholder="e.g., 2017" data-am-placeholder="ለምሳሌ፡ 2017">
                                    </div>
                                </div>
                            </div>

                            <div style="text-align: right;">
                                <button type="button" id="promoteBtn" class="btn-primary"
                                    onclick="document.getElementById('promoteConfirm').style.display='block'; this.style.display='none';">
                                    <i class="fas fa-level-up-alt"></i> <span data-en="Promote Students"
                                        data-am="ተማሪዎችን ደረጃ ያሳድጉ">Promote Students</span>
                                </button>
                                <div id="promoteConfirm"
                                    style="display:none; margin-top:10px; padding:15px; background:#fff3cd; border:1px solid #ffc107; border-radius:5px;">
                                    <p style="margin:0 0 10px; font-weight:bold; color:#856404;"
                                        data-en="Are you sure you want to promote these students? This action affects multiple records."
                                        data-am="እነዚህን ተማሪዎች ደረጃ ማሳደግ ይፈልጋሉ? ይህ ድርጊት ብዙ መዝገቦችን ይነካል።">Are you sure you
                                        want to promote these students? This action affects multiple records.</p>
                                    <button type="submit" name="promote" class="btn-primary" style="margin-right:10px;"
                                        data-en="Yes, Promote" data-am="አዎ፣ ደረጃ ስጥ">Yes, Promote</button>
                                    <button type="button" class="btn-secondary"
                                        onclick="document.getElementById('promoteConfirm').style.display='none'; document.getElementById('promoteBtn').style.display='inline-block';"
                                        data-en="Cancel" data-am="ሰርዝ">Cancel</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
        <script src="../../assets/js/bilingual.js"></script>
        <script>
            // Trigger bilingual update on load to handle dropdowns
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof updateLanguage === 'function') {
                    updateLanguage();
                }
            });
        </script>
</body>

</html>