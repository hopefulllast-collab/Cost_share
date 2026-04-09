<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['admin']);

// Fetch New Students (Sent by Registrar, Not yet downloaded)
$stmt = $pdo->query("SELECT s.*, d.name as dept_name 
                     FROM students s 
                     JOIN departments d ON s.department_id = d.id 
                     WHERE s.is_sent_to_others = 1 AND s.is_downloaded = 0 
                     ORDER BY d.name, s.first_name");
$new_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Downloaded Students (For reference)
$stmtDefaults = $pdo->query("SELECT COUNT(*) FROM students WHERE is_sent_to_others = 1 AND is_downloaded = 1");
$downloaded_count = $stmtDefaults->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="View Students - Admin" data-am="ተማሪዎችን ይመልከቱ - አስተዳዳሪ">View Students - Admin</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>
            <div class="main-content">
                <div class="top-bar">
                    <h2 data-en="Student List (from Registrar)" data-am="የተማሪ ዝርዝር (ከሬጂስትራር)">Student List</h2>
                </div>

                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h3 data-en="New Students to Download" data-am="አዲስ የሚወርዱ ተማሪዎች">New Students</h3>
                        <?php if (count($new_students) > 0): ?>
                            <a href="download_students_csv.php" class="btn-primary"
                                style="background-color: #000000; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 5px;">
                                <i class="fas fa-download" style="color: #ffffff;"></i> <span data-en="Download CSV"
                                    data-am="CSV አውርድ">Download CSV</span>
                            </a>
                        <?php else: ?>
                            <button class="btn-secondary" disabled style="background-color: #000000; color: #ffffff;
                            text-decoration: none; padding: 10px 20px; border-radius: 5px;" data-en="No New Students"
                                data-am="አዲስ የሚወርዱ ተማሪዎች የለም">No New Students</button>
                        <?php endif; ?>
                    </div>
                    <p data-en="Total already downloaded" data-am="የተማሪ ዝርዝር ከዚህ በፊት የወረዱ">Total already downloaded:
                        <strong>
                            <?php echo $downloaded_count; ?>
                        </strong>
                    </p>

                    <table class="table-styled mt-20">
                        <thead>
                            <tr>
                                <th data-en="Student ID" data-am="የተማሪ መለያ ቁጥር">Student ID</th>
                                <th data-en="Name" data-am="ስም">Name</th>
                                <th data-en="Sex" data-am="ጾታ">Sex</th>
                                <th data-en="Department" data-am="ትምህርት ክፍል">Department</th>
                                <th data-en="Year of Study" data-am="የጥናት ዓመት">Year of Study</th>
                                <th data-en="Semester" data-am="ሴሚስተር">Semester</th>
                                <th data-en="Academic Year" data-am="የትምህርት ዘመን">Academic Year</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($new_students) > 0): ?>
                                <?php foreach ($new_students as $s): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($s['student_id']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['middle_name']); ?>
                                        </td>
                                        <td>
                                            <span data-en="<?php echo htmlspecialchars($s['sex']); ?>"
                                                data-am="<?php echo ($s['sex'] == 'Male') ? 'ወንድ' : 'ሴት'; ?>"><?php echo htmlspecialchars($s['sex']); ?></span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($s['dept_name']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($s['batch']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($s['current_semester']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($s['academic_year'] ?? '-'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" data-en="No new students sent from Registrar."
                                        data-am="ከሬጂስትራር የተላከ አዲስ ተማሪ የለም።">No new students sent from Registrar.</td>
                                </tr>
                            <?php endif; ?>
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