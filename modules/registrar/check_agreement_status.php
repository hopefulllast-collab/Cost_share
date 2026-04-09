<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['registrar']);

require_once '../../includes/academic_translations.php';

// Defaults
// Calculate Current Academic Year (EC/GC)
$gc_year = date('Y');
$month = date('n');
// If month is Sep (9) or later, it's a new EC year (GC-7), otherwise it's the previous EC year (GC-8)
// Example: Feb 2026 -> 2018 EC. (2026-8).
// Oct 2025 -> 2018 EC. (2025-7).
$ec_year = ($month >= 9) ? $gc_year - 7 : $gc_year - 8;
$real_academic_year = "$ec_year/$gc_year";

// 0. Fetch Colleges
$colleges = $pdo->query("SELECT DISTINCT college FROM departments WHERE college IS NOT NULL ORDER BY college ASC")->fetchAll(PDO::FETCH_COLUMN);

$dept_id = $_GET['department_id'] ?? '';
$batch = $_GET['batch'] ?? '';
$semester = $_GET['semester'] ?? '';
$status_filter = $_GET['status_filter'] ?? 'all'; // all, signed, unsigned
$student_id = $_GET['student_id'] ?? '';

// Base Conditions
$conditions = "WHERE u.status = 'active' AND s.status = 'active'"; // Ensure active status match standard
$params = [];

// Hierarchical Filters
if (!empty($_GET['college']) && empty($dept_id)) {
    // If only college selected, filter departments by college OR join departments
    $conditions .= " AND d.college = ?";
    $params[] = $_GET['college'];
    // Note: We need to JOIN departments in the main queries below for this to work.
    // The queries below already JOIN or LEFT JOIN departments, so we just need to ensure aliases match.
    // Main queries use 'd' alias for departments.
}

if ($dept_id) {
    $conditions .= " AND s.department_id = ?";
    $params[] = $dept_id;
}
if ($student_id) {
    $conditions .= " AND s.student_id = ?";
    $params[] = $student_id;
}
if ($batch) {
    $conditions .= " AND s.batch = ?";
    $params[] = $batch;
}
if ($semester) {
    $conditions .= " AND s.current_semester = ?";
    $params[] = $semester;
}

// 1. Total Active Students
// Need to join departments for college filter if applied
$sql_total = "SELECT COUNT(*) FROM students s 
              JOIN users u ON s.user_id = u.id 
              JOIN departments d ON s.department_id = d.id " . $conditions;
$stmt = $pdo->prepare($sql_total);
$stmt->execute($params);
$total_students = $stmt->fetchColumn();

// 2. Total Signed Agreements (count unique students who have signed)
$sql_signed = "SELECT COUNT(DISTINCT csa.student_id) FROM cost_sharing_agreements csa 
               JOIN students s ON csa.student_id = s.user_id 
               JOIN users u ON s.user_id = u.id
               JOIN departments d ON s.department_id = d.id
               $conditions";

$params_signed = $params;

if ($semester) {
    $sql_signed .= " AND csa.semester = ?";
    $params_signed[] = $semester;
}

$stmt = $pdo->prepare($sql_signed);
$stmt->execute($params_signed);
$total_signed = $stmt->fetchColumn();

$total_unsigned = max(0, $total_students - $total_signed);

// 2.5 Total Fully Approved (Cost Pro Approved)
$sql_approved = "SELECT COUNT(*) FROM cost_sharing_agreements csa 
                 JOIN students s ON csa.student_id = s.user_id 
                 JOIN users u ON s.user_id = u.id 
                 JOIN departments d ON s.department_id = d.id 
                 $conditions AND csa.status = 'ApprovedByCostPro'";
$stmt = $pdo->prepare($sql_approved);
$stmt->execute($params);
$total_approved = $stmt->fetchColumn();

// 3. Unsigned List (Only fetch if filter allows)
$missing_students = [];
if ($status_filter == 'all' || $status_filter == 'unsigned') {
    $sql_missing = "SELECT s.student_id, u.first_name, u.middle_name, u.last_name, d.name as dept_name, s.batch 
                    FROM students s
                    JOIN users u ON s.user_id = u.id
                    LEFT JOIN departments d ON s.department_id = d.id
                    $conditions
                    AND s.user_id NOT IN (
                        SELECT student_id FROM cost_sharing_agreements csa
                        WHERE 1=1 ";
    $params_missing = $params;

    $sub_params = [];
    if ($semester) {
        $sql_missing .= " AND csa.semester = ?";
        $sub_params[] = $semester;
    }

    $sql_missing .= " )
                    ORDER BY d.name, s.batch, u.first_name";

    $stmt = $pdo->prepare($sql_missing);
    $stmt->execute(array_merge($params, $sub_params));
    $missing_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 4. Signed List (Only fetch if filter allows)
$signed_students = [];
if ($status_filter == 'all' || $status_filter == 'signed') {
    $sql_signed_list = "SELECT s.student_id, u.first_name, u.middle_name, u.last_name, d.name as dept_name, s.batch, csa.status 
                        FROM cost_sharing_agreements csa
                        JOIN students s ON csa.student_id = s.user_id
                        JOIN users u ON s.user_id = u.id
                        JOIN departments d ON s.department_id = d.id
                        $conditions";
    $signed_list_params = $params;

    if ($semester) {
        $sql_signed_list .= " AND csa.semester = ?";
        $signed_list_params[] = $semester;
    }

    $sql_signed_list .= " ORDER BY d.name, s.batch, u.first_name";

    $stmt = $pdo->prepare($sql_signed_list);
    $stmt->execute($signed_list_params);
    $signed_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Check Agreement Status" data-am="የስምምነት ሁኔታን ያረጋግጡ">Check Agreement Status</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-card h3 {
            margin: 0;
            font-size: 2em;
            color: #333;
        }

        .stat-card p {
            margin: 5px 0 0;
            color: #666;
        }

        .filter-bar {
            background: #fff;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            gap: 15px;
            align-items: center;
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
                    <h2 data-en="Check Cost Share Agreements" data-am="የወጪ መጋራት ስምምነቶችን ያረጋግጡ">Check Agreement Status
                    </h2>
                </div>

                <!-- Filter -->
                <form method="GET" class="filter-bar" style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <!-- College -->
                    <select name="college" id="collegeSelect" style="padding: 8px;" onchange="loadDepartments()">
                        <option value="" data-en="All Colleges" data-am="ሁሉም ኮሌጆች">All Colleges</option>
                        <?php foreach ($colleges as $col):
                            $col_am = $academic_translations[$col] ?? $col;
                            ?>
                            <option value="<?php echo htmlspecialchars($col); ?>" <?php echo (isset($_GET['college']) && $_GET['college'] == $col) ? 'selected' : ''; ?>
                                data-en="<?php echo htmlspecialchars($col); ?>"
                                data-am="<?php echo htmlspecialchars($col_am); ?>">
                                <?php echo htmlspecialchars($col); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Department -->
                    <select name="department_id" id="deptSelect" style="padding: 8px;" onchange="loadBatches()">
                        <option value="" data-en="All Departments" data-am="ሁሉም ክፍሎች">All Departments</option>
                        <!-- Populated via JS -->
                    </select>

                    <!-- Student ID -->
                    <input type="text" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>" 
                           placeholder="Student ID" data-en="Student ID" data-am="የተማሪ መለያ"
                           style="padding: 8px; width: 120px;">
                    <input type="text" value="<?php echo htmlspecialchars($real_academic_year); ?>"
                        style="padding: 8px; width: 100px; background-color: #e9ecef; border: 1px solid #ced4da; color: #495057;"
                        readonly title="Current Academic Year">

                    <!-- Batch -->
                    <select name="batch" id="batchSelect" style="padding: 8px; width: 100px;">
                        <option value="" data-en="All Batches" data-am="ሁሉም ባች">All Batches</option>
                        <!-- Populated via JS -->
                    </select>

                    <!-- Semester -->
                    <select name="semester" id="semesterSelect" style="padding: 8px;">
                                    <option value="" data-en="All Sem" data-am="ሁሉም ሴሚስተር">All Sem</option>
                                </select>

                    <!-- Status Filter -->
                    <select name="status_filter" style="padding: 8px;">
                        <option data-en="All Status" data-am="ሁሉንም ሁኔታ" value="all" <?php echo ($status_filter == 'all') ? 'selected' : ''; ?>>All Status</option>
                        <option data-en="Signed Only" data-am="የተፈረመ ብቻ" value="signed" <?php echo ($status_filter == 'signed') ? 'selected' : ''; ?>>Signed Only
                        </option>
                        <option data-en="Fully Approved" data-am="ሙሉ በሙሉ የጸደቀ" value="approved" <?php echo ($status_filter == 'approved') ? 'selected' : ''; ?>>Fully Approved Only
                        </option>
                        <option data-en="Not Signed Only" data-am="ያልተፈረመ ብቻ" value="unsigned" <?php echo ($status_filter == 'unsigned') ? 'selected' : ''; ?>>Not
                            Signed Only</option>
                    </select>

                    <button type="submit" class="btn-primary" data-en="Filter" data-am="አጣራ"
                        style="padding: 8px 15px; background-color: #000; color: #fff; border: none; cursor: pointer;">
                        Filter
                    </button>
                </form>

                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>
                            <?php echo $total_students; ?>
                        </h3>
                        <p data-en="Total Active Students" data-am="ጠቅላላ ንቁ ተማሪዎች">Total Active Students</p>
                    </div>
                    <div class="stat-card" style="border-bottom: 4px solid #28a745;">
                        <h3>
                            <?php echo $total_signed; ?>
                        </h3>
                        <p data-en="Signed Agreements" data-am="የተፈረሙ ስምምነቶች">Signed Agreements</p>
                    </div>
                    <div class="stat-card" style="border-bottom: 4px solid #007bff;">
                        <h3>
                            <?php echo $total_approved; ?>
                        </h3>
                        <p data-en="Fully Approved" data-am="ሙሉ በሙሉ የጸደቀ">Fully Approved</p>
                    </div>
                    <div class="stat-card" style="border-bottom: 4px solid #dc3545;">
                        <h3>
                            <?php echo $total_unsigned; ?>
                        </h3>
                        <p data-en="Not Signed Yet" data-am="እስካሁን ያልፈረሙ">Not Signed Yet</p>

                        <?php
                        // Breakdown by Department for Unsigned
                        $sql_uns_dept = "SELECT d.name, COUNT(*) as count
                                         FROM students s
                                         JOIN users u ON s.user_id = u.id
                                         LEFT JOIN departments d ON s.department_id = d.id
                                         $conditions
                                         AND s.user_id NOT IN (
                                            SELECT student_id FROM cost_sharing_agreements csa
                                            WHERE 1=1 ";
                        $uns_params = $params; // Base filters (Dept/Batch/Sem)

                        if ($semester) {
                            $sql_uns_dept .= " AND csa.semester = ? ";
                            $uns_params[] = $semester;
                        }

                        $sql_uns_dept .= " ) GROUP BY d.name";

                        $stmt = $pdo->prepare($sql_uns_dept);
                        $stmt->execute($uns_params);
                        $uns_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <?php if (!empty($uns_breakdown)): ?>
                            <div
                                style="margin-top: 15px; text-align: left; font-size: 0.9em; border-top: 1px solid #eee; padding-top: 10px;">
                                <?php foreach ($uns_breakdown as $row): ?>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                        <?php
                                        $dept_en = $row['name'] ?? 'Unknown';
                                        $dept_am = $academic_translations[$dept_en] ?? $dept_en;
                                        ?>
                                        <span>
                                            <span data-en="<?php echo htmlspecialchars($dept_en); ?>"
                                                data-am="<?php echo htmlspecialchars($dept_am); ?>">
                                                <?php echo htmlspecialchars($dept_en); ?>
                                            </span>:
                                        </span>
                                        <strong><?php echo $row['count']; ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($status_filter == 'all' || $status_filter == 'unsigned'): ?>
                    <!-- List of Unsigned Students -->
                    <div class="card">
                        <h3 data-en="Students Who Have Not Signed" data-am="ያልፈረሙ ተማሪዎች">Students Who Have Not Signed</h3>
                        <?php if (empty($missing_students)): ?>
                            <p class="success-msg" data-en="There are no unsigned students." data-am="ያልፈረሙ ተማሪዎች የሉም።">There are no unsigned students.</p>
                        <?php else: ?>
                            <table class="table-styled">
                                <thead>
                                    <tr>
                                        <th data-en="Student ID" data-am="የተማሪ መለያ">Student ID</th>
                                        <th data-en="Name" data-am="ስም">Name</th>
                                        <th data-en="Department" data-am="ትምህርት ክፍል">Department</th>
                                        <th data-en="Year of Study" data-am="የጥናት ዓመት">Year of Study</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($missing_students as $s): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($s['student_id']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['middle_name'] . ' ' . $s['last_name']); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $dept_en = $s['dept_name'];
                                                $dept_am = $academic_translations[$dept_en] ?? $dept_en;
                                                ?>
                                                <span data-en="<?php echo htmlspecialchars($dept_en); ?>"
                                                    data-am="<?php echo htmlspecialchars($dept_am); ?>">
                                                    <?php echo htmlspecialchars($dept_en); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($s['batch']); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($status_filter == 'all' || $status_filter == 'signed' || $status_filter == 'approved'): ?>
                    <!-- List of Signed Students -->
                    <div class="card mt-20">
                        <h3 data-en="Students Who Have Signed" data-am="የፈረሙ ተማሪዎች">Students Who Have Signed</h3>
                        <?php 
                        // If approved filter, show only approved; otherwise show all signed
                        $display_students = $signed_students;
                        if ($status_filter == 'approved') {
                            $display_students = array_filter($signed_students, function($s) {
                                return $s['status'] === 'ApprovedByCostPro';
                            });
                        }
                        ?>
                        <?php if (empty($display_students)): ?>
                            <p class="error-msg" data-en="No signed agreements found." data-am="ምንም የተፈረመ ስምምነት የለም።">No signed agreements found.</p>
                        <?php else: ?>
                            <table class="table-styled">
                                <thead>
                                    <tr>
                                        <th data-en="Student ID" data-am="የተማሪ መለያ">Student ID</th>
                                        <th data-en="Name" data-am="ስም">Name</th>
                                        <th data-en="Department" data-am="ትምህርት ክፍል">Department</th>
                                        <th data-en="Status" data-am="ሁኔታ">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($display_students as $s): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($s['student_id']); ?></td>
                                            <td><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['middle_name'] . ' ' . $s['last_name']); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $dept_en = $s['dept_name'];
                                                $dept_am = $academic_translations[$dept_en] ?? $dept_en;
                                                ?>
                                                <span data-en="<?php echo htmlspecialchars($dept_en); ?>"
                                                    data-am="<?php echo htmlspecialchars($dept_am); ?>">
                                                    <?php echo htmlspecialchars($dept_en); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $badgeClass = '';
                                                $statusLabel = $s['status'];
                                                if ($s['status'] == 'ApprovedByCostPro') {
                                                    $badgeClass = 'badge-success';
                                                    $statusLabel = "<span data-en='Fully Approved' data-am='ሙሉ በሙሉ ጸድቋል'>Fully Approved</span>";
                                                } elseif ($s['status'] == 'VerifiedByDept') {
                                                    $badgeClass = 'badge-warning';
                                                    $statusLabel = "<span data-en='Verified by Dept' data-am='በክፍል ተረጋግጧል'>Verified by Dept</span>";
                                                } elseif ($s['status'] == 'Pending') {
                                                    $badgeClass = 'badge-secondary';
                                                    $statusLabel = "<span data-en='Pending' data-am='በመጠባበቅ ላይ'>Pending</span>";
                                                } else {
                                                    $badgeClass = 'badge-secondary';
                                                    $statusLabel = "<span data-en='" . htmlspecialchars($s['status']) . "' data-am='" . htmlspecialchars($s['status']) . "'>" . htmlspecialchars($s['status']) . "</span>";
                                                }
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $statusLabel; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
    <script>
        const selectedCollege = "<?php echo $_GET['college'] ?? ''; ?>";
        const selectedDept = "<?php echo $_GET['department_id'] ?? ''; ?>";
        const selectedBatch = "<?php echo $_GET['batch'] ?? ''; ?>";
        const academicTranslations = <?php echo json_encode($academic_translations); ?>;

        function loadDepartments() {
            const college = document.getElementById('collegeSelect').value;
            const deptSelect = document.getElementById('deptSelect');

            const currentLang = localStorage.getItem('dmu_lang') || 'en';
            deptSelect.innerHTML = `<option value="" data-en="All Departments" data-am="ሁሉም ክፍሎች">${currentLang === 'am' ? 'ሁሉም ክፍሎች' : 'All Departments'}</option>`;
            document.getElementById('batchSelect').innerHTML = `<option value="" data-en="All Batches" data-am="ሁሉም ባች">${currentLang === 'am' ? 'ሁሉም ባች' : 'All Batches'}</option>`;

            if (college) {
                fetch(`../../api/get_dropdown_options.php?action=get_departments&college=${encodeURIComponent(college)}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(dept => {
                            const option = document.createElement('option');
                            option.value = dept.id;

                            const deptNameEn = dept.name;
                            const deptNameAm = academicTranslations[deptNameEn] || deptNameEn;

                            option.textContent = deptNameEn; // Default
                            option.setAttribute('data-en', deptNameEn);
                            option.setAttribute('data-am', deptNameAm);

                            // Initial language check
                            if (localStorage.getItem('dmu_lang') === 'am') {
                                option.textContent = deptNameAm;
                            }

                            if (dept.id == selectedDept) option.selected = true;
                            deptSelect.appendChild(option);
                        });
                        loadBatches();
                    });
            } else {
                loadBatches();
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
                        
                        // Automatically load semesters
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
            
            if (!selectedCollege) loadBatches();
        });


        if (selectedCollege) loadDepartments();
    </script>
</body>

</html>
