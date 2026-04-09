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
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_student'])) {

    $student_id = trim($_POST['student_id']);

    // Check if Student ID exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    if ($stmt->fetchColumn() > 0) {
        $error = "<span data-en='Student with this ID already registered.' data-am='በዚህ መለያ ቁጥር የተመዘገበ ተማሪ አለ።'>Student with this ID already registered.</span>";
    } else {
        $fname = trim($_POST['first_name']);
        $mname = trim($_POST['middle_name']);
        $lname = trim($_POST['last_name']);
        $sex = $_POST['sex'];
        $dept_id = $_POST['department_id'];
        $year = $_POST['year'];
        $semester = $_POST['semester'];
        $academic_year = $_POST['academic_year'] ?? null;

        // Compute current Ethiopian Calendar year for admission_year and fallback academic_year
        $now = new DateTime();
        $gcYear = (int) $now->format('Y');
        $gcMonth = (int) $now->format('n');
        $gcDay = (int) $now->format('j');
        $ethYear = ($gcMonth < 9 || ($gcMonth == 9 && $gcDay < 11)) ? $gcYear - 8 : $gcYear - 7;

        if (empty($academic_year)) {
            $academic_year = $ethYear;
        }
        $admission_year = $ethYear;

        // Allow Remedial to have year/sem set by JS defaults (1/1) even if hidden 
        // Logic handled by POST values which should be present if logic is right.

        try {
            // Insert directly into students table (is_sent_to_others = 1 so admin can download by default)
            $stmt = $pdo->prepare("INSERT INTO students (student_id, department_id, batch, current_semester, first_name, middle_name, last_name, sex, user_id, academic_year, admission_year, is_sent_to_others) 
                                   VALUES (:sid, :did, :batch, :sem, :f, :m, :l, :sex, NULL, :ayear, :admyear, 1)");
            $stmt->execute([
                ':sid' => $student_id,
                ':did' => $dept_id,
                ':batch' => $year,
                ':sem' => $semester,
                ':f' => $fname,
                ':m' => $mname,
                ':l' => $lname,
                ':sex' => $sex,
                ':ayear' => $academic_year,
                ':admyear' => $admission_year
            ]);

            $msg = "<span data-en='Registered the Student successfully' data-am='ተማሪው በተሳካ ሁኔታ ተመዝግቧል'>ተማሪው በተሳካ ሁኔታ ተመዝግቧል</span>";
                $_SESSION['flash_success'] = $msg;
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
        } catch (Exception $e) {
            $error = "<span data-en='Error: " . $e->getMessage() . "' data-am='ስህተት: " . $e->getMessage() . "'>Error: " . $e->getMessage() . "</span>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Add Student - Registrar" data-am="ተማሪ ይመዝግቡ - ሬጅስትራር">Add Student - Registrar</title>
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
                    <h2 data-en="Add New Student" data-am="አዲስ ተማሪ ይመዝግቡ">Add New Student</h2>
                </div>

                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>

                <div class="card">
                    <form method="POST" id="addStudentForm">
                        <!-- Student Info -->
                        <div class="form-group three-col"
                            style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                            <div>
                                <label data-en="First Name" data-am="የመጀመሪያ ስም">First Name</label>
                                <input type="text" name="first_name" required pattern="[A-Za-z]+"
                                    placeholder="First Name" data-en-placeholder="First Name"
                                    data-am-placeholder="የመጀመሪያ ስም">
                            </div>
                            <div>
                                <label data-en="Middle Name" data-am="የአባት ስም">Middle Name</label>
                                <input type="text" name="middle_name" required pattern="[A-Za-z]+"
                                    placeholder="Middle Name" data-en-placeholder="Middle Name"
                                    data-am-placeholder="የአባት ስም">
                            </div>
                            <div>
                                <label data-en="Last Name" data-am="የአያት ስም">Last Name</label>
                                <input type="text" name="last_name" required pattern="[A-Za-z]+" placeholder="Last Name"
                                    data-en-placeholder="Last Name" data-am-placeholder="የአያት ስም">
                            </div>
                        </div>

                        <div class="form-group two-col"
                            style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <label data-en="Sex" data-am="ፆታ">Sex</label>
                                <select name="sex" required>
                                    <option value="M" data-en="Male" data-am="ወንድ">Male</option>
                                    <option value="F" data-en="Female" data-am="ሴት">Female</option>
                                </select>
                            </div>
                            <div>
                                <label data-en="Student ID" data-am="የተማሪ መለያ ቁጥር">Student ID</label>
                                <input type="text" name="student_id" required placeholder="Student ID"
                                    data-en-placeholder="Student ID" data-am-placeholder="የተማሪ መለያ ቁጥር">
                            </div>
                        </div>

                        <!-- Organization Hierarchy -->
                        <div class="form-group two-col"
                            style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <label data-en="Pre-Program (Freshman/Remedial)"
                                    data-am="ቅድመ-ፕሮግራም (ፍሬሽማን/ሪሚዲያል)">Pre-Program
                                    (Freshman/Remedial)</label>
                                <select id="preProgramSelect" onchange="handlePreProgram()">
                                    <option value="" data-en="Select Pre-Program" data-am="ቅድመ-ፕሮግራም ይምረጡ">Select
                                        Pre-Program</option>
                                    <option value="Freshman Program" data-en="Freshman Program" data-am="ፍሬሽማን ፕሮግራም">
                                        Freshman Program</option>
                                    <option value="Remedial Program" data-en="Remedial Program" data-am="ሪሚዲያል ፕሮግራም">
                                        Remedial Program</option>
                                </select>
                            </div>
                            <div>
                                <label data-en="College/School/Institute"
                                    data-am="ኮሌጅ/ትምህርት ቤት/ተቋም">College/School/Institute</label>
                                <select id="collegeSelect" onchange="handleCollege()">
                                    <option value="" data-en="Select College/Program" data-am="ኮሌጅ/ፕሮግራም ይምረጡ">Select
                                        College/Program</option>
                                    <!-- Populated by JS -->
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label data-en="Department/Stream" data-am="ክፍል/ስትሪም">Department/Stream</label>
                            <select name="department_id" id="deptSelect" onchange="updateYears()" required disabled>
                                <option value="" data-en="Select Department First" data-am="መጀመሪያ ክፍል ይምረጡ">Select
                                    Department First</option>
                            </select>
                            <input type="hidden" id="selectedDeptYears" value="4">
                        </div>

                        <!-- Academic Info -->
                        <div class="form-group two-col" id="academicInfo"
                            style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <label data-en="Study Year (Batch)" data-am="ዓመት (ባች)">Study Year (Batch)</label>
                                <select name="year" id="yearSelect" required>
                                    <!-- Populated by JS -->
                                </select>
                            </div>
                            <div>
                                <label data-en="Semester" data-am="ሴሚስተር">Semester</label>
                                <select name="semester" id="semesterSelect" required>
</select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label data-en="Academic Year" data-am="የትምህርት ዘመን">Academic Year</label>
                            <select name="academic_year" required>
                                <?php
                                // Compute current Ethiopian year for default selection
                                $now2 = new DateTime();
                                $gcY = (int) $now2->format('Y');
                                $gcM = (int) $now2->format('n');
                                $gcD = (int) $now2->format('j');
                                $currentEthYear = ($gcM < 9 || ($gcM == 9 && $gcD < 11)) ? $gcY - 8 : $gcY - 7;

                                $startYear = $currentEthYear - 5;
                                $endYear = $currentEthYear + 5;
                                for ($y = $startYear; $y <= $endYear; $y++) {
                                    $selected = ($y == $currentEthYear) ? 'selected' : '';
                                    echo "<option value='$y' $selected>$y</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <button data-en="Register the Student" data-am="ተማሪውን ይመዝግቡ" type="submit"
                            name="register_student" class="btn-primary"
                            style="background-color: #000000; color: #ffffff;">Register the
                            Student</button>
                    </form>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>

    <script>
        const academicTranslations = <?php echo json_encode($academic_translations); ?>;

        document.addEventListener('DOMContentLoaded', function () {
            fetchColleges();
        });

        function fetchColleges() {
            fetch('../../api/get_dropdown_options.php?action=get_colleges')
                .then(response => response.json())
                .then(data => {
                    const select = document.getElementById('collegeSelect');
                    data.forEach(college => {
                        let opt = document.createElement('option');
                        opt.value = college;

                        const colNameEn = college;
                        const colNameAm = academicTranslations[colNameEn] || colNameEn;

                        opt.textContent = colNameEn;
                        opt.setAttribute('data-en', colNameEn);
                        opt.setAttribute('data-am', colNameAm);

                        if (localStorage.getItem('dmu_lang') === 'am') {
                            opt.textContent = colNameAm;
                        }

                        select.appendChild(opt);
                    });
                });
        }

        function handlePreProgram() {
            const pre = document.getElementById('preProgramSelect');
            const college = document.getElementById('collegeSelect');

            if (pre.value) {
                college.value = "";
                college.disabled = true; // Optional: disable to enforce exclusion
                loadDepartments(pre.value);
            } else {
                college.disabled = false;
                // Reset
                document.getElementById('deptSelect').innerHTML = '<option value="" data-en="Select Department First" data-am="መጀመሪያ ክፍል ይምረጡ">Select Department First</option>';
                document.getElementById('deptSelect').disabled = true;
                document.getElementById('yearSelect').innerHTML = '';
                document.getElementById('semesterSelect').value = '1';
                document.getElementById('academicInfo').style.display = 'grid';
            }
        }

        function handleCollege() {
            const pre = document.getElementById('preProgramSelect');
            const college = document.getElementById('collegeSelect');

            if (college.value) {
                pre.value = "";
                pre.disabled = true; // Optional
                loadDepartments(college.value);
            } else {
                pre.disabled = false;
                // Reset
                document.getElementById('deptSelect').innerHTML = '<option value="" data-en="Select Department First" data-am="መጀመሪያ ክፍል ይምረጡ">Select Department First</option>';
                document.getElementById('deptSelect').disabled = true;
                document.getElementById('yearSelect').innerHTML = '';
                document.getElementById('semesterSelect').value = '1';
                document.getElementById('academicInfo').style.display = 'grid';
            }
        }

        function loadDepartments(collegeName) {
            const deptSelect = document.getElementById('deptSelect');

            const currentLang = localStorage.getItem('dmu_lang') || 'en';
            deptSelect.innerHTML = `<option value="" data-en="Select Department/Stream" data-am="ክፍል/ሙያ ይምረጡ">${currentLang === 'am' ? 'ክፍል/ሙያ ይምረጡ' : 'Select Department/Stream'}</option>`;
            deptSelect.disabled = true;

            if (collegeName) {
                fetch(`../../api/get_dropdown_options.php?action=get_departments&college=${encodeURIComponent(collegeName)}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(dept => {
                            let opt = document.createElement('option');
                            opt.value = dept.id;

                            const deptNameEn = dept.display_name || dept.name;
                            const deptNameAm = academicTranslations[deptNameEn] || deptNameEn;

                            opt.textContent = deptNameEn;
                            opt.setAttribute('data-en', deptNameEn);
                            opt.setAttribute('data-am', deptNameAm);
                            opt.setAttribute('data-years', dept.study_years);

                            if (localStorage.getItem('dmu_lang') === 'am') {
                                opt.textContent = deptNameAm;
                            }

                            deptSelect.appendChild(opt);
                        });
                        deptSelect.disabled = false;
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

        function updateYears() {
            const deptSelect = document.getElementById('deptSelect');
            const selectedOpt = deptSelect.options[deptSelect.selectedIndex];
            if (!selectedOpt.value) return;

            const years = parseInt(selectedOpt.getAttribute('data-years')) || 4;
            // Determine Context from PreProgram or College
            const preVal = document.getElementById('preProgramSelect').value;
            const isFreshman = (preVal === 'Freshman Program');
            const isRemedial = (preVal === 'Remedial Program');

            const yearSelect = document.getElementById('yearSelect');
            const semSelect = document.getElementById('semesterSelect');
            const academicInfo = document.getElementById('academicInfo');

            yearSelect.innerHTML = '';
            semSelect.innerHTML = '';
            yearSelect.disabled = false;
            semSelect.disabled = false;

            // Default Visibility
            academicInfo.style.display = 'grid';

            if (isFreshman) {
                // Fixed Year 1
                let opt = document.createElement('option');
                opt.value = 1;
                opt.setAttribute('data-en', '1 (Freshman)');
                opt.setAttribute('data-am', '1 (ፍሬሽማን)');
                opt.textContent = "1 (Freshman)";
                yearSelect.appendChild(opt);

                // Sem 1, 2
                semSelect.innerHTML = '<option value="1" data-en="Semester 1" data-am="1ኛ ሴሚስተር">1</option><option value="2" data-en="Semester 2" data-am="2ኛ ሴሚስተር">2</option>';

                const currentLang = localStorage.getItem('dmu_lang') || 'en';
                setLanguage(currentLang);
            }
            else if (isRemedial) {
                academicInfo.style.display = 'none';

                // Add Year 1 and Sem 1 as selected hidden values
                let opt = document.createElement('option');
                opt.value = 1;
                opt.selected = true;
                yearSelect.appendChild(opt);
                
                semSelect.innerHTML = '<option value="1" selected>1</option>';
            }
            else {
                // Regular Department: 2 to Years
                for (let i = 2; i <= years; i++) {
                    let opt = document.createElement('option');
                    opt.value = i;
                    opt.textContent = i;
                    yearSelect.appendChild(opt);
                }
                
                // Sem 1, 2
                semSelect.innerHTML = '<option value="1" data-en="Semester 1" data-am="1ኛ ሴሚስተር">1</option><option value="2" data-en="Semester 2" data-am="2ኛ ሴሚስተር">2</option>';
            }
        }

    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>
