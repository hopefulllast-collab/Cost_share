<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['registrar']);

// Fetch Colleges
$colleges = $pdo->query("SELECT DISTINCT college FROM departments WHERE college IS NOT NULL ORDER BY college ASC")->fetchAll(PDO::FETCH_COLUMN);

// Count Active Students 
$stmt = $pdo->query("SELECT COUNT(*) FROM students s JOIN users u ON s.user_id = u.id WHERE u.status = 'active' AND s.status = 'active'");
$total_active = $stmt->fetchColumn();

// Count All Students (Total in Database)
$stmt = $pdo->query("SELECT COUNT(*) FROM students");
$total_all = $stmt->fetchColumn();

// Filter Logic
$where_clauses = ["1=1"]; // Default true
$params = [];

// College Filter
if (!empty($_GET['college']) && empty($_GET['department_id'])) {
    $where_clauses[] = "d.college = :college";
    $params[':college'] = $_GET['college'];
}

if (!empty($_GET['department_id'])) {
    $where_clauses[] = "s.department_id = :dept";
    $params[':dept'] = $_GET['department_id'];
}
if (!empty($_GET['batch'])) {
    $where_clauses[] = "s.batch = :batch";
    $params[':batch'] = $_GET['batch'];
}
if (!empty($_GET['semester'])) {
    $where_clauses[] = "s.current_semester = :sem";
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

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch from students table directly
$sql = "SELECT s.first_name, s.middle_name, s.last_name, s.sex, s.student_id, d.name as dept_name, s.batch, s.current_semester, s.academic_year, s.status 
        FROM students s 
        JOIN departments d ON s.department_id = d.id 
        $where_sql 
        ORDER BY d.name, s.batch, s.current_semester, s.first_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$status_map = [
    'active' => 'ንቁ',
    'withdrawal' => 'ያቋረጠ (Withdrawal)',
    'dropout' => 'ያቋረጠ (Dropout)',
    'complete dismissal' => 'ሙሉ ለሙሉ የተሰናበተ',
    'dismissal with readmission' => 'መመለስ የሚቻል',
    'death' => 'ሞት',
    'suspended' => 'ታግዷል'
];

require_once '../../includes/academic_translations.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student List - Registrar</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            align-items: end;
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
                    <h2 data-en="View Student List" data-am="የተማሪ ዝርዝር ይመልከቱ">View Student List</h2>
                    <div style="text-align: right;">
                        <div style="background:#007bff; color:white; padding:5px 15px; border-radius:5px; margin-bottom: 5px;"
                            data-en="Total Active Student = <?php echo $total_active; ?>"
                            data-am="ጠቅላላ ንቁ ተማሪ = <?php echo $total_active; ?>">
                            Total Active Student = <?php echo $total_active; ?>
                        </div>
                        <div style="background:#6c757d; color:white; padding:5px 15px; border-radius:5px;"
                            data-en="Total Student = <?php echo $total_all; ?>"
                            data-am="ጠቅላላ ተማሪ = <?php echo $total_all; ?>">
                            Total Student = <?php echo $total_all; ?>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <form method="GET">
                        <div class="filter-grid">
                            <!-- College -->
                            <div>
                                <label data-en="College" data-am="ኮሌጅ">College</label>
                                <select name="college" id="collegeSelect" onchange="loadDepartments()">
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
                            </div>

                            <!-- Department -->
                            <div>
                                <label data-en="Department" data-am="ክፍል">Department</label>
                                <select name="department_id" id="deptSelect" onchange="loadBatches()">
                                    <option value="" data-en="All Departments" data-am="ሁሉም ክፍሎች">All Departments
                                    </option>
                                    <!-- Populated via JS -->
                                </select>
                            </div>

                            <!-- Batch -->
                            <div>
                                <label data-en="Year of Study" data-am="የጥናት ዓመት">Year of Study</label>
                                <select name="batch" id="batchSelect">
                                    <option value="" data-en="All Batches" data-am="ሁሉም ባቾች">All Batches</option>
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
                                <label data-en="Sex" data-am="ፆታ">Sex</label>
                                <select name="sex">
                                    <option value="" data-en="All" data-am="ሁሉም">All</option>
                                    <option value="Male" <?php echo (isset($_GET['sex']) && $_GET['sex'] == 'Male') ? 'selected' : ''; ?> data-en="Male" data-am="ወንድ">Male</option>
                                    <option value="Female" <?php echo (isset($_GET['sex']) && $_GET['sex'] == 'Female') ? 'selected' : ''; ?> data-en="Female" data-am="ሴት">Female</option>
                                </select>
                            </div>

                            <!-- Status -->
                            <div>
                                <label data-en="Status" data-am="ሁኔታ">Status</label>
                                <select name="status">
                                    <option value="" data-en="All Status" data-am="ሁሉም ሁኔታ">All Status</option>
                                    <option value="active" <?php echo (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : ''; ?> data-en="Active" data-am="ንቁ">Active</option>
                                    <option value="withdrawal" <?php echo (isset($_GET['status']) && $_GET['status'] == 'withdrawal') ? 'selected' : ''; ?> data-en="Withdrawal" data-am="ያቋረጠ (Withdrawal)">Withdrawal</option>
                                    <option value="dropout" <?php echo (isset($_GET['status']) && $_GET['status'] == 'dropout') ? 'selected' : ''; ?> data-en="Dropout" data-am="ያቋረጠ (Dropout)">Dropout</option>
                                    <option value="complete dismissal" <?php echo (isset($_GET['status']) && $_GET['status'] == 'complete dismissal') ? 'selected' : ''; ?> data-en="Complete Dismissal" data-am="ሙሉ ለሙሉ የተሰናበተ">Complete Dismissal</option>
                                    <option value="dismissal with readmission" <?php echo (isset($_GET['status']) && $_GET['status'] == 'dismissal with readmission') ? 'selected' : ''; ?> data-en="Dismissal with Readmission" data-am="መመለስ የሚቻል">Dismissal with Readmission</option>
                                    <option value="death" <?php echo (isset($_GET['status']) && $_GET['status'] == 'death') ? 'selected' : ''; ?> data-en="Death" data-am="ሞት">Death</option>
                                    <option value="graduate" <?php echo (isset($_GET['status']) && $_GET['status'] == 'graduate') ? 'selected' : ''; ?> data-en="Graduate" data-am="????">Graduate</option>
                                    <option value="suspended" <?php echo (isset($_GET['status']) && $_GET['status'] == 'suspended') ? 'selected' : ''; ?> data-en="Suspended" data-am="ታግዷል">Suspended</option>
                                </select>
                            </div>

                            <!-- Student ID -->
                            <div style="grid-column: span 2;">
                                <label data-en="Student ID" data-am="የተማሪ መለያ">Student ID</label>
                                <input type="text" name="student_id" placeholder="Search by ID..." data-en
                                    data-en-placeholder="Search by ID..." data-am-placeholder="በመለያ ይፈልጉ..."
                                    value="<?php echo $_GET['student_id'] ?? ''; ?>">
                            </div>

                            <!-- Search Button -->
                            <div style="grid-column: span 2;">
                                <button type="submit" class="btn-primary" style="width:30%;" data-en="Search"
                                    data-am="ፈልግ">
                                    <i class="fas fa-search"></i> <span data-en="Search" data-am="ፈልግ">Search</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Client-Side Search -->
                <div style="margin-top: 20px; text-align: right;">
                    <input type="text" id="tableSearch" placeholder="Search in table..." data-en="Search in table..."
                        data-en-placeholder="Search in table..." data-am-placeholder="በሰንጠረዥ ውስጥ ይፈልጉ..."
                        style="padding: 10px; width: 300px; border: 1px solid #ddd; border-radius: 6px;">
                </div>

                <div class="card mt-20">
                    <table class="table-list">
                        <thead>
                            <tr>
                                <th data-en="Student ID" data-am="የተማሪ መለያ">Student ID</th>
                                <th data-en="Name" data-am="ስም">Name</th>
                                <th data-en="Sex" data-am="ፆታ">Sex</th>
                                <th data-en="Department" data-am="ክፍል">Department</th>
                                <th data-en="Year of Study" data-am="የጥናት ዓመት">Year of Study</th>
                                <th data-en="Sem" data-am="ሴሚስተር">Sem</th>
                                <th data-en="Academic Year" data-am="የትምህርት ዘመን">Academic Year</th>
                                <th data-en="Status" data-am="ሁኔታ">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="8" data-en="No students found." data-am="ምንም ተማሪ አልተገኘም።">No students
                                        found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $stu): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($stu['student_id']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($stu['first_name'] . ' ' . $stu['middle_name'] . ' ' . $stu['last_name']); ?>
                                        </td>
                                        <td>
                                            <span
                                                data-en="<?php echo $stu['sex'] == 'M' ? 'Male' : ($stu['sex'] == 'F' ? 'Female' : $stu['sex']); ?>"
                                                data-am="<?php echo $stu['sex'] == 'M' ? 'ወንድ' : ($stu['sex'] == 'F' ? 'ሴት' : $stu['sex']); ?>">
                                                <?php echo htmlspecialchars($stu['sex']); ?>
                                            </span>
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
                                            <?php echo htmlspecialchars($stu['batch']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($stu['current_semester']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($stu['academic_year'] ?? '-'); ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($stu['status']); ?>"
                                                data-en="<?php echo ucfirst($stu['status']); ?>"
                                                data-am="<?php echo $status_map[strtolower($stu['status'])] ?? ucfirst($stu['status']); ?>">
                                                <?php echo ucfirst($stu['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>

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

                            option.textContent = deptNameEn; // Default text
                            option.setAttribute('data-en', deptNameEn);
                            option.setAttribute('data-am', deptNameAm);

                            // Initial language check
                            if (localStorage.getItem('dmu_lang') === 'am') {
                                option.textContent = deptNameAm;
                            }

                            if (dept.id == selectedDept) option.selected = true;
                            deptSelect.appendChild(option);
                        });
                        if (selectedDept) loadBatches();
                    });
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

/*
            const deptId = document.getElementById('deptSelect').value;
            const batchSelect = document.getElementById('batchSelect');

            const currentLang = localStorage.getItem('dmu_lang') || 'en';
            batchSelect.innerHTML = `<option value="" data-en="All Batches" data-am="ሁሉም ባች">${currentLang === 'am' ? 'ሁሉም ባች' : 'All Batches'}</option>`;

            if (deptId) {
                fetch(`../../api/get_dropdown_options.php?action=get_batches&department_id=${deptId}`)
                    .then(response => response.json())
                    .then(result => {
                        const batches = result.batches || result;
                        batches.forEach(batch => {
                            const option = document.createElement('option');
                            option.value = batch;
                            option.textContent = 'Batch ' + batch;
                            option.setAttribute('data-en', 'Batch ' + batch);
                            option.setAttribute('data-am', 'ባች ' + batch);
                            if (localStorage.getItem('dmu_lang') === 'am') {
                                option.textContent = 'ባች ' + batch;
                            }
                            if (batch == selectedBatch) option.selected = true;
                            batchSelect.appendChild(option);
                        });
                    });
            }
*/


        if (selectedCollege) loadDepartments();

        // Client-side Table Search
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
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>