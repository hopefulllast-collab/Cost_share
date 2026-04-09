<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['registrar']);

require_once '../../includes/academic_translations.php';

// PRG: Read flash messages from session
$msg = $_SESSION['flash_success'] ?? "";
unset($_SESSION['flash_success']);
$error = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_department'])) {
    $name = trim($_POST['department_name']);
    $college = trim($_POST['college']);
    $study_years = (int) $_POST['study_years'];
    $total_credits = (int) $_POST['total_credits'];

    if (empty($name)) {
        $error = "<span data-en='Department Name is required.' data-am='የዲፓርትመንት ስም ያስፈልጋል።'>Department Name is required.</span>";
    } else {
        // Check Duplicate
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() > 0) {
            $error = "<span data-en='Department \"$name\" already exists.' data-am='ዲፓርትመንት \"$name\" አስቀድሞ አለ።'>Department \"$name\" already exists.</span>";
        } else {
            // Insert
            try {
                $stmt = $pdo->prepare("INSERT INTO departments (name, college, study_years, total_credits) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $college, $study_years, $total_credits]);
                $_SESSION['flash_success'] = "<span data-en='Department added successfully!' data-am='ዲፓርትመንት በተሳካ ሁኔታ ተጨምሯል!'>Department added successfully!</span>";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } catch (PDOException $e) {
                $error = "<span data-en='Database Error: " . $e->getMessage() . "' data-am='የውሂብ ጎታ ስህተት: " . $e->getMessage() . "'>Database Error: " . $e->getMessage() . "</span>";
            }
        }
    }
}

// Fetch Existing Departments for Display (Optional List)
$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Add Department - Registrar" data-am="ክፍል ይጨምሩ - ሬጅስትራር">Add Department - Registrar</title>
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
                    <h2 data-en="Add Department" data-am="ክፍል ይጨምሩ">Add Department</h2>
                    <a href="dashboard.php" class="btn-sm" data-en="Back" data-am="ተመለስ">Back</a>
                </div>

                <?php if ($msg): ?>
                    <div class="success-msg"><?php echo $msg; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="error-msg"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="card" style="max-width: 800px; margin: 0 auto;">
                    <form method="POST">
                        <div class="form-group">
                            <label data-en="Department Name" data-am="የዲፓርትመንት ስም">Department Name</label>
                            <input type="text" name="department_name" required placeholder="e.g. Software Engineering"
                                data-en="e.g. Software Engineering" data-en-placeholder="e.g. Software Engineering"
                                data-am-placeholder="ለምሳሌ ሶፍትዌር ኢንጂነሪንግ">
                        </div>

                        <div class="form-group">
                            <label data-en="College / School / Faculty" data-am="ኮሌጅ / ትምህርት ቤት / ፋኩልቲ">College / School
                                / Faculty</label>
                            <input type="text" name="college" required placeholder="e.g. College of Computing"
                                data-en="e.g. College of Computing" data-en-placeholder="e.g. College of Computing"
                                data-am-placeholder="ለምሳሌ የኮምፒውቲንግ ኮሌጅ">
                        </div>

                        <div class="form-group two-col" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                            <div>
                                <label data-en="Total Study Years" data-am="ጠቅላላ የትምህርት ዓመታት">Total Study Years</label>
                                <input type="number" name="study_years" min="1" max="7" value="4" required>
                            </div>
                            <div>
                                <label data-en="Total Credit Hours to Graduation"
                                    data-am="ለማስመረቅ የሚያስፈልጉ የክሬዲት ሰዓታት">Total Credit Hours to Graduation</label>
                                <input type="number" name="total_credits" min="1" required placeholder="e.g. 148"
                                    data-en="e.g. 148" data-en-placeholder="e.g. 148" data-am-placeholder="ለምሳሌ 148">
                            </div>
                        </div>

                        <button type="submit" name="add_department" class="btn-primary" style="width: 100%;"
                            data-en="Add Department" data-am="ዲፓርትመንት ጨምር">
                            <i class="fas fa-plus-circle"></i> <span data-en="Add Department" data-am="ዲፓርትመንት ጨምር">Add
                                Department</span>
                        </button>
                    </form>
                </div>

                <div class="card mt-20">
                    <h3 data-en="Existing Departments" data-am="ነባር ክፍሎች">Existing Departments</h3>
                    <table class="table-list">
                        <thead>
                            <tr>
                                <th data-en="College" data-am="ኮሌጅ">College</th>
                                <th data-en="Department Name" data-am="የዲፓርትመንት ስም">Department Name</th>
                                <th data-en="Years" data-am="ዓመታት">Years</th>
                                <th data-en="Credits" data-am="ክሬዲቶች">Credits</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $col_en = $dept['college'] ?? '-';
                                        $col_am = $academic_translations[$col_en] ?? $col_en;
                                        ?>
                                        <span data-en="<?php echo htmlspecialchars($col_en); ?>"
                                            data-am="<?php echo htmlspecialchars($col_am); ?>">
                                            <?php echo htmlspecialchars($col_en); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $dept_en = $dept['name'];
                                        $dept_am = $academic_translations[$dept_en] ?? $dept_en;
                                        ?>
                                        <span data-en="<?php echo htmlspecialchars($dept_en); ?>"
                                            data-am="<?php echo htmlspecialchars($dept_am); ?>">
                                            <?php echo htmlspecialchars($dept_en); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($dept['study_years']); ?></td>
                                    <td><?php echo htmlspecialchars($dept['total_credits']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>