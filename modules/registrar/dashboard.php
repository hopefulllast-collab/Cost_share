<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['registrar']);

// Total Cost Share Amount
$totalCostShare = $pdo->query("SELECT COALESCE(SUM(tuition_fee + food_expense + bed_expense + medication_expense), 0) FROM cost_sharing_agreements WHERE status != 'Suspended'")->fetchColumn();

// Stats Queries
$studentCount = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$deptCount = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();

// Pending Cost Share
$pendingCount = $pdo->query("SELECT COUNT(*) FROM official_transcript WHERE request_type = 'CostSharePaper' AND status = 'Pending'")->fetchColumn();

// Approved Cost Share
$approvedCount = $pdo->query("SELECT COUNT(*) FROM official_transcript WHERE request_type = 'CostSharePaper' AND status = 'Approved'")->fetchColumn();

// Recent Students (Last 5)
$recentStudents = $pdo->query("SELECT s.student_id, s.first_name, s.last_name, d.name as dept_name, s.batch 
                               FROM students s 
                               JOIN departments d ON s.department_id = d.id 
                               ORDER BY s.id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/academic_translations.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Registrar Dashboard - DMU" data-am="ሬጅስትራር ዳሽቦርድ - DMU">Registrar Dashboard - DMU</title>
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

                <!-- Stats Grid -->
                <div class="card-grid">
                    <div class="card info-card">
                        <h3><i class="fas fa-users" style="color:var(--secondary-color);"></i> <span
                                data-en="Total Students" data-am="ጠቅላላ ተማሪዎች">Total Students</span></h3>
                        <p class="big-number"><?php echo $studentCount; ?></p>
                    </div>
                    <div class="card info-card">
                        <h3><i class="fas fa-university" style="color:var(--secondary-color);"></i> <span
                                data-en="Departments" data-am="የትምህርት ክፍሎች">Departments</span></h3>
                        <p class="big-number"><?php echo $deptCount; ?></p>
                    </div>
                </div>
                <!-- Bottom Content Grid -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
                    
                    <!-- Left Column: Recent Registrations -->
                    <div class="card">
                        <h3 data-en="Recent Registrations" data-am="በቅርቡ የተመዘገቡ">Recent Registrations</h3>
                        <?php if (empty($recentStudents)): ?>
                            <p data-en="No students found." data-am="ተማሪዎች አልተገኙም">No students found.</p>
                        <?php else: ?>
                            <table class="table-list">
                                <thead>
                                    <tr>
                                        <th data-en="ID" data-am="መለያ ቁጥር">ID</th>
                                        <th data-en="Name" data-am="ስም">Name</th>
                                        <th data-en="Dept" data-am="ክፍል">Dept</th>
                                        <th data-en="Year of Study" data-am="የጥናት ዓመት">Year of Study</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentStudents as $stu): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($stu['student_id']); ?></td>
                                            <td><?php echo htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']); ?></td>
                                            <td>
                                                <?php
                                                $dept_en = $stu['dept_name'];
                                                $dept_am = $academic_translations[$dept_en] ?? $dept_en;
                                                ?>
                                                <span data-en="<?php echo htmlspecialchars($dept_en); ?>"
                                                    data-am="<?php echo htmlspecialchars($dept_am); ?>">
                                                    <?php echo htmlspecialchars($dept_en); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($stu['batch']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div style="margin-top: 15px; text-align: right;">
                                <a href="view_student_list.php" class="btn-sm" data-en="View All" data-am="ሁሉንም ይመልከቱ"
                                    style="color: #ffffff; padding: 15px; background-color: #000000; font-size: 1.2em;">View
                                    All</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right Column: Cost Share Summary & Quick Actions -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        
                        <!-- Cost Share Summary -->
                        <div class="card">
                            <h3 data-en="Cost Share Summary" data-am="የወጪ መጋራት ማጠቃለያ">Cost Share Summary</h3>
                            <p style="font-size: 1.1em; margin: 10px 0;">
                                <strong data-en="Total Cost Share Amount:" data-am="ጠቅላላ የወጪ መጋራት መጠን:">Total Cost Share Amount:</strong>
                                <span style="font-size: 1.3em; color: var(--primary-color); font-weight: bold;">
                                    <?php echo number_format($totalCostShare, 2); ?> <span data-en="ETB" data-am="ብር">ETB</span>
                                </span>
                            </p>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="card" style="flex: 1;">
                            <h3 data-en="Quick Actions" data-am="ፈጣን ተግባራት">Quick Actions</h3>
                            <div class="action-buttons" style="display: flex; flex-direction: column; gap: 10px;">
                                <a href="add_student.php" class="btn-primary"
                                    style="text-align: center; width:80%; height: 30%;">
                                    <i class="fas fa-plus"></i> <span data-en="Add New Student" data-am="አዲስ ተማሪ ጨምር">Add
                                        New Student</span>
                                </a>
                                <a href="approve_cost_share.php" class="btn-secondary"
                                    style="text-align: center; width: 80%; height: 30%;">
                                    <i class="fas fa-check-circle"></i> <span data-en="Approve Agreements"
                                        data-am="ውሎችን አጽድቅ">Approve Agreements</span>
                                </a>
                                <a href="report_cost_share.php" class="btn-secondary"
                                    style="text-align: center; width: 80%; height: 30%;">
                                    <i class="fas fa-chart-line"></i> <span data-en="Generate Reports"
                                        data-am="ሪፖርቶችን አውጣ">Generate Reports</span>
                                </a>
                                <a href="order.php" class="btn-secondary"
                                    style="text-align: center; width: 80%; height: 30%;">
                                    <i class="fas fa-user-shield"></i> <span data-en="Manage Orders"
                                        data-am="ትዕዛዞችን አስተዳድር">Manage Orders</span>
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

            </div> <!-- Closes main-content -->
        </div> <!-- Closes layout-body -->
        
        <?php include '../../includes/footer.php'; ?>
    </div> <!-- Closes dashboard-container -->
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>