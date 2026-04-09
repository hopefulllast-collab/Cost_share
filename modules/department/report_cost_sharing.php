<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['department_head']);

// Helper to get Dept ID and Name
$stmt = $pdo->prepare("SELECT id as department_id, name as dept_name 
                       FROM departments 
                       WHERE head_user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$deptInfo = $stmt->fetch();
$myDeptId = $deptInfo['department_id'] ?? 0;
$myDeptName = $deptInfo['dept_name'] ?? 'Unknown';

// Filter Logic
$where_clauses = ["s.department_id = :dept_id"];
$params = [':dept_id' => $myDeptId];

if (!empty($_GET['year'])) {
    $year_val = $_GET['year'];
    if ($year_val == 'Custom' && !empty($_GET['custom_year'])) {
        $year_val = $_GET['custom_year'];
    }
    if ($year_val != 'Custom') {
        $where_clauses[] = "st.academic_year = :year";
        $params[':year'] = $year_val;
    }
}
if (!empty($_GET['batch'])) {
    $where_clauses[] = "s.batch = :batch";
    $params[':batch'] = $_GET['batch'];
}
if (!empty($_GET['semester'])) {
    $where_clauses[] = "st.semester = :sem";
    $params[':sem'] = $_GET['semester'];
}
if (!empty($_GET['sex'])) {
    $where_clauses[] = "s.sex = :sex";
    $params[':sex'] = $_GET['sex'];
}
if (!empty($_GET['status'])) {
    $where_clauses[] = "s.status = :status";
    $params[':status'] = $_GET['status'];
}
if (!empty($_GET['student_id'])) {
    $where_clauses[] = "s.student_id LIKE :sid";
    $params[':sid'] = "%" . trim($_GET['student_id']) . "%";
}

// Special filter: For Withdrawal and Dismissal with Readmission, only show cost share for the semester they have the status
$where_clauses[] = "(
    (s.status NOT IN ('Withdrawal', 'Dismissal with Readmission') AND st.status != 'Suspended')
    OR
    (s.status IN ('Withdrawal', 'Dismissal with Readmission') AND st.semester = s.current_semester AND st.academic_year = s.batch)
)";

$where_sql = implode(" AND ", $where_clauses);

// Fetch Transactions
$sql = "SELECT s.student_id, s.first_name, s.last_name, s.sex, s.batch, s.status,
               SUM(st.tuition_fee + st.food_expense + st.bed_expense + st.medication_expense) as total,
               COUNT(st.id) as agreement_count
        FROM cost_sharing_agreements st
        JOIN students s ON st.student_id = s.user_id
        WHERE $where_sql
        GROUP BY s.user_id, s.student_id, s.first_name, s.last_name, s.sex, s.batch, s.status
        ORDER BY s.batch, s.first_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Report Cost Sharing - Department Head" data-am="የወጪ መጋራት ሪፖርት - የዲፓርትመንት ኃላፊ">Report Cost Sharing -
        Department Head</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            align-items: end;
        }

        @media print {
            body {
                background: white;
            }

            .sidebar,
            .top-bar,
            .filter-grid,
            form,
            button,
            nav,
            .main-header {
                display: none !important;
            }

            .main-content {
                margin: 0 !important;
                width: 100% !important;
                padding: 0 !important;
            }

            .card {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .table-list {
                width: 100% !important;
                border-collapse: collapse;
            }

            .table-list th,
            .table-list td {
                border: 1px solid #000 !important;
                padding: 8px !important;
                text-align: left !important;
                color: #000 !important;
            }

            .layout-body {
                display: block !important;
            }

            .dashboard-container {
                display: block !important;
            }

            .status-badge {
                border: none !important;
                padding: 0 !important;
                background: transparent !important;
                color: #000 !important;
                font-weight: normal !important;
            }
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
                    <h2 data-en="Generate Report Cost Share" data-am="የወጪ መጋራት ሪፖርት ያመንጩ">
                        Generate Report Cost Share</h2>
                </div>

                <div class="card">
                    <form method="GET">
                        <div class="filter-grid">
                            <!-- Department (Read Only) -->
                            <div>
                                <label data-en="Department" data-am="ትምህርት ክፍል">Department</label>
                                <input type="text" value="<?php echo htmlspecialchars($myDeptName); ?>" readonly
                                    style="background-color: #f0f0f0; color: #555; cursor: not-allowed;">
                            </div>

                            <!-- Academic Year -->
                            <div>
                                <label data-en="Academic Year" data-am="የትምህርት ዘመን">Academic Year</label>
                                <select name="year" id="yearSelect" onchange="toggleCustomYear()">
                                    <option value="" data-en="All Years" data-am="ሁሉም ዓመታት">All Years</option>
                                    <?php
                                    $startYear = 2018;
                                    $endYear = 2050;
                                    for ($y = $startYear; $y <= $endYear; $y++) {
                                        $val = $y;
                                        $display = $y . '/' . ($y + 8);
                                        $selected = (isset($_GET['year']) && $_GET['year'] == $val) ? 'selected' : '';
                                        echo "<option value='$val' $selected>$display</option>";
                                    }
                                    ?>
                                    <option value="Custom" <?php echo (isset($_GET['year']) && $_GET['year'] == 'Custom') ? 'selected' : ''; ?> data-en="Custom query..." data-am="ብጁ ጥያቄ...">Custom...
                                    </option>
                                </select>
                                <input type="number" name="custom_year" id="customYearInput" class="form-control"
                                    placeholder="Enter Year" data-en="Enter Year" data-en-placeholder="Enter Year"
                                    data-am-placeholder="ዓመት ያስገቡ" style="display:none; margin-top:5px;"
                                    value="<?php echo $_GET['custom_year'] ?? ''; ?>">
                            </div>

                            <!-- Batch -->
                            <div>
                                <label data-en="Year of Study" data-am="የጥናት ዓመት">Year of Study</label>
                                <select name="batch" id="batchSelect">
                                    <option value="" data-en="All Batches" data-am="ሁሉም ባች">All Batches</option>
                                    <!-- Populated via JS -->
                                </select>
                            </div>

                            <!-- Semester -->
                            <div>
                                <label data-en="Semester" data-am="ሴሚስተር">Semester</label>
                                <select name="semester" id="semesterSelect">
                                    <option value="" data-en="All Sem" data-am="ሁሉም ሴሚስተር">All Sem</option>
                                </select>
                            </div>

                            <!-- Sex -->
                            <div>
                                <label data-en="Sex" data-am="ጾታ">Sex</label>
                                <select name="sex">
                                    <option value="" data-en="All" data-am="ሁሉም">All</option>
                                    <option value="Male" <?php echo (isset($_GET['sex']) && $_GET['sex'] == 'Male') ? 'selected' : ''; ?> data-en="Male" data-am="ወንድ">Male</option>
                                    <option value="Female" <?php echo (isset($_GET['sex']) && $_GET['sex'] == 'Female') ? 'selected' : ''; ?> data-en="Female" data-am="ሴት">
                                        Female</option>
                                </select>
                            </div>

                            <!-- Status -->
                            <div>
                                <label data-en="Status" data-am="ሁኔታ">Status</label>
                                <select name="status">
                                    <option value="" data-en="All Status" data-am="ሁሉም ሁኔታዎች">All Status</option>
                                    <option value="active" <?php echo (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : ''; ?> data-en="Active" data-am="ንቁ">Active</option>
                                    <option value="withdrawal" <?php echo (isset($_GET['status']) && $_GET['status'] == 'withdrawal') ? 'selected' : ''; ?> data-en="Withdrawal" data-am="ያቋረጠ (Withdrawal)">Withdrawal</option>
                                    <option value="dropout" <?php echo (isset($_GET['status']) && $_GET['status'] == 'dropout') ? 'selected' : ''; ?> data-en="Dropout" data-am="ያቋረጠ (Dropout)">Dropout</option>
                                    <option value="complete dismissal" <?php echo (isset($_GET['status']) && $_GET['status'] == 'complete dismissal') ? 'selected' : ''; ?> data-en="Complete Dismissal" data-am="ሙሉ ለሙሉ የተሰናበተ">Complete Dismissal</option>
                                    <option value="dismissal with readmission" <?php echo (isset($_GET['status']) && $_GET['status'] == 'dismissal with readmission') ? 'selected' : ''; ?> data-en="Dismissal with Readmission" data-am="መመለስ የሚቻል">Dismissal with Readmission</option>
                                    <option value="death" <?php echo (isset($_GET['status']) && $_GET['status'] == 'death') ? 'selected' : ''; ?> data-en="Death" data-am="ሞት">Death</option>
                                    <option value="graduate" <?php echo (isset($_GET['status']) && $_GET['status'] == 'graduate') ? 'selected' : ''; ?> data-en="Graduate" data-am="????">Graduate</option>
                                </select>
                            </div>

                            <!-- Student ID -->
                            <div style="grid-column: span 2;">
                                <label data-en="Student ID (Optional)" data-am="የተማሪ መለያ (አማራጭ)">Student ID
                                    (Optional)</label>
                                <input type="text" name="student_id" placeholder="Search by ID..."
                                    data-en="Search by ID..." data-en-placeholder="Search by ID..."
                                    data-am-placeholder="በመታወቂያ ቁጥር ይፈልጉ..."
                                    value="<?php echo $_GET['student_id'] ?? ''; ?>">
                            </div>

                            <!-- Action Buttons -->
                            <div style="grid-column: span 2; display: flex; gap: 10px;">
                                <button type="submit" class="btn-primary" style="flex: 1;">
                                    <i class="fas fa-search"></i> <span data-en="Fetch Report" data-am="ሪፖርት አምጣ">Fetch
                                        Report</span>
                                </button>
                                <button type="button" class="btn-secondary" onclick="printReport()"
                                    style="flex: 1; background: #6c757d; color: white;">
                                    <i class="fas fa-print"></i> <span data-en="Print Document" data-am="ሰነድ አትም">Print
                                        Document</span>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Client-Side Search -->
                    <div style="margin-top: 20px; text-align: right;">
                        <input type="text" id="tableSearch" placeholder="Search in table..."
                            data-en="Search in table..." data-en-placeholder="Search in table..."
                            data-am-placeholder="በሰንጠረዥ ውስጥ ይፈልጉ..."
                            style="padding: 10px; width: 300px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>

                    <div class="card mt-20">
                        <table class="table-list">
                            <thead>
                                <tr>
                                    <th data-en="Student ID" data-am="የተማሪ መለያ">Student ID</th>
                                    <th data-en="Name" data-am="ስም">Name</th>
                                    <th data-en="Sex" data-am="ጾታ">Sex</th>
                                    <th data-en="Year of Study" data-am="የጥናት ዓመት">Year of Study</th>
                                    <th data-en="Status" data-am="ሁኔታ">Status</th>
                                    <th data-en="Total Cost" data-am="ጠቅላላ ወጪ">Total Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reports)): ?>
                                    <tr>
                                        <td colspan="6" data-en="No records found." data-am="ምንም መዝገቦች አልተገኙም።">No
                                            records found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reports as $r): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($r['student_id']); ?></td>
                                            <td><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?>
                                            </td>
                                            <td><span data-en="<?php echo htmlspecialchars($r['sex']); ?>"
                                                    data-am="<?php echo ($r['sex'] == 'Male') ? 'ወንድ' : 'ሴት'; ?>"><?php echo htmlspecialchars($r['sex']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($r['batch']); ?></td>
                                            <td>
                                                <?php
                                                $status_map = [
    'active' => 'ንቁ',
    'withdrawal' => 'ያቋረጠ (Withdrawal)',
    'dropout' => 'ያቋረጠ (Dropout)',
    'complete dismissal' => 'ሙሉ ለሙሉ የተሰናበተ',
    'dismissal with readmission' => 'መመለስ የሚቻል',
    'death' => 'ሞት'
];
                                                $st_lower = strtolower($r['status']);
                                                $st_am = $status_map[$st_lower] ?? $r['status'];
                                                ?>
                                                <span class="status-badge status-<?php echo $st_lower; ?>"
                                                    data-en="<?php echo ucfirst($r['status']); ?>"
                                                    data-am="<?php echo $st_am; ?>">
                                                    <?php echo ucfirst($r['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($r['total'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <script>
        const myDeptId = "<?php echo $myDeptId; ?>";
        const selectedBatch = "<?php echo $_GET['batch'] ?? ''; ?>";

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

        function toggleCustomYear() {
            const val = document.getElementById('yearSelect').value;
            const customInput = document.getElementById('customYearInput');
            if (val === 'Custom') {
                customInput.style.display = 'block';
            } else {
                customInput.style.display = 'none';
            }
        }

        loadBatches();
        toggleCustomYear();

        document.getElementById('tableSearch').addEventListener('keyup', function () {
            let filter = this.value.toUpperCase();
            let table = document.querySelector('.table-list');
            let tr = table.getElementsByTagName('tr');
            for (let i = 1; i < tr.length; i++) {
                let tdArray = tr[i].getElementsByTagName('td');
                let found = false;
                for (let j = 0; j < tdArray.length; j++) {
                    let td = tdArray[j];
                    if (td) {
                        let txtValue = td.textContent || td.innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? "" : "none";
            }
        });
        // Handle print with filter validation
        function printReport() {
            const params = new URLSearchParams(window.location.search);
            let hasFilter = false;

            for (const [key, value] of params.entries()) {
                if (value && value.trim() !== '' && value !== '0' && key !== 'report_type') {
                    hasFilter = true;
                    break;
                }
            }

            if (!hasFilter) {
                const msgEn = "You will print all data, please first filter out. Do you want to proceed anyway?";
                const msgAm = "ያለ ማጣሪያ ሁሉንም መረጃዎች እያተሙ ነው። እባክዎ መጀመሪያ ያጣሩ። ለማንኛውም መቀጠል ይፈልጋሉ?";
                showBilingualConfirm(msgEn, msgAm, function () {
                    window.print();
                });
            } else {
                window.print();
            }
        }
    </script>
    <?php include '../../includes/footer.php'; ?>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>