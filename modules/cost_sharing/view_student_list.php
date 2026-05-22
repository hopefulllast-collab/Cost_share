<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['cost_sharing_pro']);
require_once '../../includes/academic_translations.php';

// Fetch Departments
$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Count Active Students 
$stmt = $pdo->query("SELECT COUNT(*) FROM students");
$total_active = $stmt->fetchColumn();

// Filter Logic
$where_clauses = ["1=1"]; // Default true
$params = [];

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
if (!empty($_GET['student_id'])) {
    $where_clauses[] = "s.student_id LIKE :sid";
    $params[':sid'] = "%" . trim($_GET['student_id']) . "%";
}
// Add Status Filter
if (!empty($_GET['status'])) {
    // We need to join cost_sharing_agreements if checking status
    // Note: This logic assumes 1 agreement per semester/year, might need distinct if multiple
    $where_clauses[] = "csa.status = :status";
    $params[':status'] = $_GET['status'];
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch from students table directly
$sql = "SELECT s.first_name, s.middle_name, s.last_name, s.sex, s.student_id, d.name as dept_name, s.batch, s.current_semester, s.academic_year, csa.id as agreement_id 
        FROM students s 
        JOIN departments d ON s.department_id = d.id 
        LEFT JOIN cost_sharing_agreements csa ON s.user_id = csa.student_id
        $where_sql 
        GROUP BY s.user_id 
        ORDER BY d.name, s.batch, s.current_semester, s.first_name"; // GROUP BY to avoid duplicates if multiple agreements

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="View Student List - Cost Sharing Pro" data-am="የተማሪ ዝርዝር ይመልከቱ - የወጪ መጋራት ባለሙያ">View Student List -
        Cost Sharing Pro</title>
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
                    <h2 data-en="View Student List" data-am="የተማሪ ዝርዝር ይመልከቱ">View Student List</h2>
                    <div style="background:#007bff; color:white; padding:5px 15px; border-radius:5px;">
                        <span data-en="Total Active Student" data-am="ጠቅላላ ንቁ ተማሪዎች">Total Active Student</span> =
                        <?php echo $total_active; ?>
                    </div>
                </div>

                <div class="card">
                    <form method="GET"
                        style="display:grid; grid-template-columns: repeat(5, 1fr); gap:10px; align-items:end;">
                        <div>
                            <label data-en="Department" data-am="ትምህርት ክፍል">Department</label>
                            <select name="department_id" style="width:100%">
                                <option value="" data-en="All" data-am="ሁሉም">All</option>
                                <?php foreach ($departments as $d):
                                    $dept_am = $academic_translations[$d['name']] ?? $d['name'];
                                    ?>
                                    <option value="<?php echo $d['id']; ?>" <?php echo (isset($_GET['department_id']) && $_GET['department_id'] == $d['id']) ? 'selected' : ''; ?>
                                        data-en="<?php echo htmlspecialchars($d['name']); ?>"
                                        data-am="<?php echo htmlspecialchars($dept_am); ?>">
                                        <?php echo htmlspecialchars($d['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label data-en="Year of Study" data-am="የጥናት ዓመት">Year of Study</label>
                            <select name="batch" id="batchSelect" style="width:100%">
                                <option value="" data-en="All Batches" data-am="ሁሉም ባች">All Batches</option>
                                <!-- Populated via JS -->
                            </select>
                        </div>
                        <div>
                            <label data-en="Semester" data-am="ሴሚስተር">Semester</label>
                            <select name="semester" id="semesterSelect" style="width:100%">
                                    <option value="" data-en="All Sem" data-am="ሁሉም ሴሚስተር">All Sem</option>
                                </select>
                        </div>
                        <div>
                            <label data-en="Student ID" data-am="የተማሪ መለያ">Student ID</label>
                            <input type="text" name="student_id" placeholder="ID..." data-en="ID..."
                                data-en-placeholder="ID..." data-am-placeholder="መለያ..."
                                value="<?php echo $_GET['student_id'] ?? ''; ?>" style="width:100%">
                        </div>
                        <div>
                            <button type="submit" class="btn-primary" style="width:50%;" data-en="Search" data-am="ፈልግ">
                                <i class="fas fa-search"></i> <span data-en="Search" data-am="ፈልግ">Search</span>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="card mt-20">
                    <table class="table-list">
                        <thead>
                            <tr>
                                <th data-en="Student ID" data-am="የተማሪ መለያ">Student ID</th>
                                <th data-en="Name" data-am="ስም">Name</th>
                                <th data-en="Sex" data-am="ጾታ">Sex</th>
                                <th data-en="Department" data-am="ትምህርት ክፍል">Department</th>
                                <th data-en="Year of Study" data-am="የጥናት ዓመት">Year of Study</th>
                                <th data-en="Sem" data-am="ሴሚስተር">Sem</th>
                                <th data-en="Academic Year" data-am="የትምህርት ዘመን">Academic Year</th>
                                <th data-en="Action" data-am="ድርጊት">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="8" data-en="No students found." data-am="ምንም ተማሪዎች አልተገኙም።">No students
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
                                            <span data-en="<?php echo htmlspecialchars($stu['sex']); ?>"
                                                data-am="<?php echo ($stu['sex'] == 'Male') ? 'ወንድ' : 'ሴት'; ?>"><?php echo htmlspecialchars($stu['sex']); ?></span>
                                        </td>
                                        <td>
                                            <?php $dept_am = $academic_translations[$stu['dept_name']] ?? $stu['dept_name']; ?>
                                            <span data-en="<?php echo htmlspecialchars($stu['dept_name']); ?>"
                                                data-am="<?php echo htmlspecialchars($dept_am); ?>"><?php echo htmlspecialchars($stu['dept_name']); ?></span>
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
                                            <?php if (!empty($stu['agreement_id'])): ?>
                                                <a href="view_agreement_detail.php?id=<?php echo $stu['agreement_id']; ?>" class="btn-secondary btn-sm"><i class="fas fa-eye"></i> <span data-en="View Details" data-am="ዝርዝር ይመልከቱ">View Details</span></a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
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
        const selectedDept = "<?php echo $_GET['department_id'] ?? ''; ?>";
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
        });

        if (selectedDept) loadBatches();

        // Attach event listener to department select
        document.getElementsByName('department_id')[0].addEventListener('change', loadBatches);
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>
