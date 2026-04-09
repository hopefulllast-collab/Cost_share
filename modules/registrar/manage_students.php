<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['registrar']);

$message = '';

// Add Student Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_student'])) {
    $username = $_POST['username'];
    $password = password_hash('password', PASSWORD_DEFAULT); // Default pwd
    $fname = $_POST['first_name'];
    $lname = $_POST['last_name'];
    $dept_id = $_POST['department_id'];
    $student_id = $_POST['student_id'];

    try {
        $pdo->beginTransaction();

        // Create User
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, first_name, middle_name, last_name) 
                               VALUES (:u, :p, 'student', :fn, '', :ln)");
        $stmt->execute([':u' => $username, ':p' => $password, ':fn' => $fname, ':ln' => $lname]);
        $uid = $pdo->lastInsertId();

        // Compute current Ethiopian Calendar year
        $now = new DateTime();
        $gcYear = (int) $now->format('Y');
        $gcMonth = (int) $now->format('n');
        $gcDay = (int) $now->format('j');
        $ethYear = ($gcMonth < 9 || ($gcMonth == 9 && $gcDay < 11)) ? $gcYear - 8 : $gcYear - 7;

        // Create Student Profile
        $stmt = $pdo->prepare("INSERT INTO students (user_id, student_id, department_id, batch, academic_year, admission_year) 
                               VALUES (:uid, :sid, :did, :batch, :ayear, :admyear)");
        $stmt->execute([':uid' => $uid, ':sid' => $student_id, ':did' => $dept_id, ':batch' => $batch, ':ayear' => $ethYear, ':admyear' => $ethYear]);

        $pdo->commit();
        $message = "Student added successfully. Default password is 'password'.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

$depts = $pdo->query("SELECT * FROM departments")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>

            <div class="main-content">
                <div class="top-bar">
                    <h2 data-en="Manage Students" data-am="ተማሪዎችን ያቀናብሩ">Manage Students</h2>
                    <a href="dashboard.php" class="btn-sm" data-en="Back" data-am="ተመለስ">Back</a>
                </div>

                <?php if ($message)
                    echo "<div class='success-msg'>$message</div>"; ?>

                <div class="card">
                    <h3 data-en="Add New Student" data-am="አዲስ ተማሪ ጨምር">Add New Student</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label data-en="First Name" data-am="የመጀመሪያ ስም">First Name</label>
                            <input type="text" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label data-en="Last Name" data-am="የአባት ስም">Last Name</label>
                            <input type="text" name="last_name" required>
                        </div>
                        <div class="form-group">
                            <label data-en="Username" data-am="የተጠቃሚ ስም">Username</label>
                            <input type="text" name="username" required>
                        </div>
                        <div class="form-group">
                            <label data-en="Student ID (e.g., DMU/1111/14)"
                                data-am="የተማሪ መለያ (ለምሳሌ DMU/1111/14)">Student ID (e.g., DMU/1111/14)</label>
                            <input type="text" name="student_id" required>
                        </div>
                        <div class="form-group">
                            <label data-en="Department" data-am="ትምህርት ክፍል">Department</label>
                            <select name="department_id" required>
                                <?php foreach ($depts as $d): ?>
                                    <option value="<?php echo $d['id']; ?>">
                                        <?php echo $d['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="add_student" class="btn-primary" data-en="Add Student"
                            data-am="ተማሪ ጨምር">Add Student</button>
                    </form>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>