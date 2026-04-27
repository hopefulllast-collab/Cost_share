<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['registrar']);

// Total Cost Share Amount
$totalCostShare = $pdo->query("SELECT COALESCE(SUM(tuition_fee + food_expense + bed_expense + medication_expense), 0) FROM cost_sharing_agreements WHERE status != 'Suspended'")->fetchColumn();

// Stats
$studentCount = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$deptCount = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$pendingCount = $pdo->query("SELECT COUNT(*) FROM official_transcript WHERE request_type = 'CostSharePaper' AND status = 'Pending'")->fetchColumn();
$approvedCount = $pdo->query("SELECT COUNT(*) FROM official_transcript WHERE request_type = 'CostSharePaper' AND status = 'Approved'")->fetchColumn();
$deliveredCount = $pdo->query("SELECT COUNT(*) FROM official_transcript WHERE status = 'Delivered'")->fetchColumn();

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

                <!-- Stats Row -->
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
                            <h4 data-en="Departments" data-am="ትምህርት ክፍሎች">Departments</h4>
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
                        <div class="dash-stat-icon rose"><i class="fas fa-hourglass-half"></i></div>
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
                        <h3 class="dash-section-title">
                            <i class="fas fa-clipboard-list"></i>
                            <span data-en="Recent Registrations" data-am="በቅርቡ የተመዘገቡ">Recent Registrations</span>
                        </h3>
                        <?php if (empty($recentStudents)): ?>
                            <div style="text-align:center; padding:40px; color:#94a3b8;">
                                <i class="fas fa-inbox" style="font-size:2.5rem; margin-bottom:12px; display:block; color:#cbd5e1;"></i>
                                <p data-en="No students registered yet." data-am="ገና ምንም ተማሪ አልተመዘገበም።">No students registered yet.</p>
                            </div>
                        <?php else: ?>
                            <table class="table-list">
                                <thead>
                                    <tr>
                                        <th data-en="ID" data-am="መለያ">ID</th>
                                        <th data-en="Name" data-am="ስም">Name</th>
                                        <th data-en="Department" data-am="ክፍል">Department</th>
                                        <th data-en="Year" data-am="ዓመት">Year</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentStudents as $stu): ?>
                                        <tr>
                                            <td style="font-weight:600; color:#6366f1;"><?php echo htmlspecialchars($stu['student_id']); ?></td>
                                            <td>
                                                <div style="display:flex; align-items:center; gap:8px;">
                                                    <div style="width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg,#e0e7ff,#c7d2fe); display:flex; align-items:center; justify-content:center; font-size:0.65rem; color:#4338ca; font-weight:700;">
                                                        <?php echo strtoupper(substr($stu['first_name'],0,1) . substr($stu['last_name'],0,1)); ?>
                                                    </div>
                                                    <?php echo htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']); ?>
                                                </div>
                                            </td>
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
                                            <td>
                                                <span style="background:#f0fdf4; color:#16a34a; padding:2px 10px; border-radius:8px; font-size:0.78rem; font-weight:700;">
                                                    <?php echo htmlspecialchars($stu['batch']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <a href="view_student_list.php" class="dash-view-all" data-en="View All Students" data-am="ሁሉንም ተማሪዎች ይመልከቱ">
                                View All Students <i class="fas fa-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Right Column -->
                    <div class="dash-right-col">
                        <!-- Cost Share Summary -->
                        <div class="dash-section">
                            <h3 class="dash-section-title">
                                <i class="fas fa-coins"></i>
                                <span data-en="Cost Share Summary" data-am="የወጪ መጋራት ማጠቃለያ">Cost Share Summary</span>
                            </h3>
                            <div class="dash-amount-display">
                                <div class="amount-label" data-en="Total Amount" data-am="ጠቅላላ መጠን">Total Amount</div>
                                <div class="amount-value"><?php echo number_format($totalCostShare, 2); ?> <span class="amount-currency" data-en="ETB" data-am="ብር">ETB</span></div>
                            </div>
                            <div style="display:flex; gap:12px; margin-top:14px;">
                                <div style="flex:1; text-align:center; padding:10px; background:#f0fdf4; border-radius:10px;">
                                    <div style="font-size:0.68rem; color:#64748b; text-transform:uppercase; font-weight:600; letter-spacing:0.05em;" data-en="Delivered" data-am="የተሰጡ">Delivered</div>
                                    <div style="font-size:1.1rem; font-weight:800; color:#16a34a; margin-top:2px;"><?php echo $deliveredCount; ?></div>
                                </div>
                                <div style="flex:1; text-align:center; padding:10px; background:#fffbeb; border-radius:10px;">
                                    <div style="font-size:0.68rem; color:#64748b; text-transform:uppercase; font-weight:600; letter-spacing:0.05em;" data-en="Pending" data-am="በመጠባበቅ">Pending</div>
                                    <div style="font-size:1.1rem; font-weight:800; color:#d97706; margin-top:2px;"><?php echo $pendingCount; ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="dash-section">
                            <h3 class="dash-section-title">
                                <i class="fas fa-bolt"></i>
                                <span data-en="Quick Actions" data-am="ፈጣን ተግባራት">Quick Actions</span>
                            </h3>
                            <div class="dash-actions">
                                <a href="add_student.php" class="dash-action-link">
                                    <i class="fas fa-user-plus"></i>
                                    <span data-en="Add New Student" data-am="አዲስ ተማሪ ጨምር">Add New Student</span>
                                </a>
                                <a href="approve_cost_share.php" class="dash-action-link">
                                    <i class="fas fa-check-double"></i>
                                    <span data-en="Approve Agreements" data-am="ውሎችን አጽድቅ">Approve Agreements</span>
                                    <?php if ($pendingCount > 0): ?>
                                        <span class="dash-action-badge"><?php echo $pendingCount; ?></span>
                                    <?php endif; ?>
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