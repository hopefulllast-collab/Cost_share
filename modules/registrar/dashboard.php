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

                <!-- Stats -->
                <div class="dash-stats">
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon blue"><i class="fas fa-user-graduate"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Total Students" data-am="ጠቅላላ ተማሪዎች">Total Students</h4>
                            <div class="dash-stat-value"><?php echo $studentCount; ?></div>
                        </div>
                    </div>
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon amber"><i class="fas fa-building"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Departments" data-am="የትምህርት ክፍሎች">Departments</h4>
                            <div class="dash-stat-value"><?php echo $deptCount; ?></div>
                        </div>
                    </div>
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon green"><i class="fas fa-check-circle"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Approved" data-am="የጸደቁ">Approved</h4>
                            <div class="dash-stat-value"><?php echo $approvedCount; ?></div>
                        </div>
                    </div>
                    <div class="dash-stat-card">
                        <div class="dash-stat-icon rose"><i class="fas fa-clock"></i></div>
                        <div class="dash-stat-info">
                            <h4 data-en="Pending" data-am="በመጠባበቅ">Pending</h4>
                            <div class="dash-stat-value"><?php echo $pendingCount; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="dash-content-grid">
                    <!-- Left: Recent Registrations -->
                    <div class="dash-section">
                        <h3 class="dash-section-title"><i class="fas fa-list-alt"></i> <span data-en="Recent Registrations" data-am="በቅርቡ የተመዘገቡ">Recent Registrations</span></h3>
                        <?php if (empty($recentStudents)): ?>
                            <p data-en="No students found." data-am="ተማሪዎች አልተገኙም">No students found.</p>
                        <?php else: ?>
                            <table class="table-list">
                                <thead>
                                    <tr>
                                        <th data-en="ID" data-am="መለያ">ID</th>
                                        <th data-en="Name" data-am="ስም">Name</th>
                                        <th data-en="Dept" data-am="ክፍል">Dept</th>
                                        <th data-en="Year" data-am="ዓመት">Year</th>
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
                            <a href="view_student_list.php" class="dash-view-all" data-en="View All Students" data-am="ሁሉንም ተማሪዎች ይመልከቱ">
                                <i class="fas fa-arrow-right"></i> View All Students
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Right Column -->
                    <div style="display:flex; flex-direction:column; gap:20px;">
                        <!-- Cost Share Summary -->
                        <div class="dash-section">
                            <h3 class="dash-section-title"><i class="fas fa-coins"></i> <span data-en="Cost Share Summary" data-am="የወጪ መጋራት ማጠቃለያ">Cost Share Summary</span></h3>
                            <div class="dash-amount-display">
                                <div class="amount-label" data-en="Total Amount" data-am="ጠቅላላ መጠን">Total Amount</div>
                                <div class="amount-value"><?php echo number_format($totalCostShare, 2); ?> <span class="amount-currency" data-en="ETB" data-am="ብር">ETB</span></div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="dash-section">
                            <h3 class="dash-section-title"><i class="fas fa-bolt"></i> <span data-en="Quick Actions" data-am="ፈጣን ተግባራት">Quick Actions</span></h3>
                            <div class="dash-actions">
                                <a href="add_student.php" class="dash-action-link">
                                    <i class="fas fa-user-plus"></i>
                                    <span data-en="Add New Student" data-am="አዲስ ተማሪ ጨምር">Add New Student</span>
                                </a>
                                <a href="approve_cost_share.php" class="dash-action-link">
                                    <i class="fas fa-check-double"></i>
                                    <span data-en="Approve Agreements" data-am="ውሎችን አጽድቅ">Approve Agreements</span>
                                </a>
                                <a href="report_cost_share.php" class="dash-action-link">
                                    <i class="fas fa-chart-bar"></i>
                                    <span data-en="Generate Reports" data-am="ሪፖርቶችን አውጣ">Generate Reports</span>
                                </a>
                                <a href="order.php" class="dash-action-link">
                                    <i class="fas fa-clipboard-list"></i>
                                    <span data-en="Manage Orders" data-am="ትዕዛዞችን አስተዳድር">Manage Orders</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>