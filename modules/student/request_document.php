<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['student']);

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT s.student_id, s.status, u.first_name, u.middle_name, u.last_name, d.name as dept_name FROM students s JOIN users u ON s.user_id = u.id LEFT JOIN departments d ON s.department_id = d.id WHERE s.user_id = ?");
$stmt->execute([$user_id]);
$info = $stmt->fetch(PDO::FETCH_ASSOC);

require_once '../../includes/academic_translations.php';

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

$departments = [
    "Accounting and Finance",
    "Computer Science",
    "Economics",
    "Medicine",
    "Geography and Environmental Studies",
    "Animal Science",
    "Veterinary Science",
    "Statistics / Biostatistics",
    "Special Needs and Inclusive Education",
    "English Language and Literature",
    "Mathematics",
    "Sport Science"
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_doc'])) {
    $doc_type = $_POST['document_type'];
    $dept = $_POST['department']; // Just storing it, though we have dept_id in DB, form requires input

    $clearance_path = null;

    // 1. Check for ANY Pending/Active Request (prevent duplicates entirely)
    $check_any = $pdo->prepare("SELECT count(*) FROM official_transcript WHERE student_id = ? AND status NOT IN ('Rejected', 'Delivered')");
    $check_any->execute([$user_id]);
    if ($check_any->fetchColumn() > 0) {
        $error = "<span data-en='You already have an active document request. Please wait until it is processed before submitting a new one.' data-am='ቀድሞ ያቀረቡት ሰነድ ጥያቄ ያልተጠናቀቀ አለ። አዲስ ከማቅረብዎ በፊት እባክዎ ይጠብቁ።'>You already have an active document request. Please wait until it is processed.</span>";
    }

    // 2. Check Eligibility (Graduated only for Original/Graduation)
    if (!$error && ($doc_type == 'Original' || $doc_type == 'Graduation')) {
        if (strtolower($info['status']) != 'graduated') {
            $error = "<span data-en='You are not eligible, you must wait upto graduate.' data-am='እርስዎ ብቁ አይደሉም፣ እስኪመረቁ ድረስ መጠበቅ አለብዎት።'>You are not eligible, you must wait upto graduate.</span>";
        }
    }

    // Validation
    if ($doc_type == 'Original' || $doc_type == 'Transfer-Out') {
        if (!isset($_FILES['clearance_file']) || $_FILES['clearance_file']['error'] != 0) {
            $error = "<span data-en='Legal clearance file is required for this document type.' data-am='ለዚህ የሰነድ አይነት ህጋዊ ክሊራንስ ፋይል ያስፈልጋል።'>Legal clearance file is required for this document type.</span>";
        } else {
            // Upload Logic
            $target_dir = "../../uploads/clearances/";
            if (!file_exists($target_dir))
                mkdir($target_dir, 0777, true);

            $file_ext = strtolower(pathinfo($_FILES["clearance_file"]["name"], PATHINFO_EXTENSION));
            $new_name = "clearance_" . $user_id . "_" . time() . "." . $file_ext;
            $target_file = $target_dir . $new_name;

            if (move_uploaded_file($_FILES["clearance_file"]["tmp_name"], $target_file)) {
                $clearance_path = $new_name;
            } else {
                $error = "<span data-en='Failed to upload file.' data-am='ፋይል መጫን አልተቻለም።'>Failed to upload file.</span>";
            }
        }
    }

    if (!$error) {
        $stmt = $pdo->prepare("INSERT INTO official_transcript (student_id, request_type, clearance_file, status) VALUES (?, ?, ?, 'Pending')");
        if ($stmt->execute([$user_id, $doc_type, $clearance_path])) {
            $_SESSION["flash_success"] = ($doc_type == 'Transfer-Out')
                ? "<span data-en='Request submitted successfully to Academic Vice President.' data-am='ጥያቄው ለአካዳሚክ ም/ፕሬዝዳንት በተሳካ ሁኔታ ቀርቧል።'>Request submitted successfully to Academic Vice President.</span>"
                : "<span data-en='Request submitted successfully to Registrar.' data-am='ጥያቄው ለሬጂስትራር በተሳካ ሁኔታ ቀርቧል።'>Request submitted successfully to Registrar.</span>";
            header("Location: " . $_SERVER["PHP_SELF"]);
            exit();
        } else {
            $error = "<span data-en='Database error.' data-am='የውሂብ ጎታ ስህተት።'>Database error.</span>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Request Document - DMU" data-am="ሰነድ ይጠይቁ - DMU">Request Document - DMU</title>
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
                    <h2 data-en="Request Official Document" data-am="ኦፊሺያል ሰነድ ይጠይቁ">Request Official Document</h2>
                </div>

                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>

                <div id="js-error" class="error-msg" style="display:none;"></div>

                <div class="card">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group two-col">
                            <div>
                                <label data-en="Full Name" data-am="ሙሉ ስም">Full Name</label>
                                <input type="text"
                                    value="<?php echo $info['first_name'] . ' ' . $info['middle_name'] . ' ' . $info['last_name']; ?>"
                                    readonly>
                            </div>
                            <div>
                                <label data-en="Student ID" data-am="የተማሪ መለያ ቁጥር">Student ID</label>
                                <input type="text" value="<?php echo $info['student_id']; ?>" readonly>
                            </div>
                        </div>

                        <div class="form-group">
                            <label data-en="Department" data-am="ትምህርት ክፍል">Department</label>
                            <?php
                            $dept_en = $info['dept_name'] ?? 'N/A';
                            $dept_am = $academic_translations[$dept_en] ?? $dept_en;
                            ?>
                            <span class="form-control"
                                style="background-color: #e9ecef; display: block; padding: 10px; border: 1px solid #ccc;"
                                data-en="<?php echo htmlspecialchars($dept_en); ?>"
                                data-am="<?php echo htmlspecialchars($dept_am); ?>">
                                <?php echo htmlspecialchars($dept_en); ?>
                            </span>
                            <input type="hidden" name="department" value="<?php echo htmlspecialchars($dept_en); ?>">
                        </div>

                        <div class="form-group">
                            <label data-en="Document Type" data-am="የሰነድ አይነት">Document Type</label>
                            <select name="document_type" id="docType" onchange="toggleUpload()" required>
                                <option value="" data-en="Select Type" data-am="የሰነድ አይነት ይምረጡ">Select Type</option>
                                <option value="Graduation" data-en="Graduation Certificate (Temporary)"
                                    data-am="የምረቃ የምስክር ወረቀት (ጊዜያዊ)">Graduation Certificate (Temporary)</option>
                                <option value="Original" data-en="Original Document (Diploma/Transcript)"
                                    data-am="ኦሪጅናል ሰነድ (ዲፕሎማ/ግልባጭ)">Original Document (Diploma/Transcript)</option>
                                <option value="Transfer-Out" data-en="Transfer-Out Cost Share Debt"
                                    data-am="ግቢ ለመቀየር ወጪ ዕዳ ይጠይቁ">Transfer-Out Cost Share Debt</option>
                            </select>
                        </div>

                        <div id="uploadSection" class="form-group hidden">
                            <label
                                data-en="Upload Legal Acceptance Letter (From the university academic department, academic vice president office or inland revenue only)"
                                data-am="የህግ ተቀባይነት ደብዳቤ ስቀል
                                (ከዩኒቨርሲቲው የአካዳሚክ ክፍል፣ የአካዳሚክ ምክትል ፕሬዘዳንት ቢሮ ወይም የሀገር ውስጥ ገቢ ብቻ)">Upload Legal Acceptance Letter
                                <br>(From the university academic department, academic vice president office or inland revenue only)</label>
                            <input type="file" name="clearance_file" accept=".pdf,.jpg,.png">
                            <small data-en="Required for all document requests." data-am="ለሁሉም የሰነድ ጥያቄዎች ያስፈልጋል።">Required
                                for all document requests.</small>
                        </div>

                        <button type="submit" name="request_doc" class="btn-primary" data-en="Submit Request"
                            data-am="ጥያቄ አቅርብ">Submit Request</button>
                    </form>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>

    <script>
        const studentStatus = "<?php echo $info['status']; ?>";

        function toggleUpload() {
            const type = document.getElementById('docType').value;
            const section = document.getElementById('uploadSection');
            const errorDiv = document.getElementById('js-error');

            // Reset Error
            errorDiv.style.display = 'none';
            errorDiv.innerText = '';

            // Toggle Upload Section
            if (type === 'Original' || type === 'Transfer-Out') {
                section.style.display = 'block';
                section.classList.remove('hidden');
            } else {
                section.style.display = 'none';
                section.classList.add('hidden');
            }

            // Client-side Eligibility Check
            if (type === 'Original' || type === 'Graduation') {
                if (studentStatus.toLowerCase() !== 'graduated') {
                    // Show inline error instead of alert
                    const msg = localStorage.getItem('dmu_lang') === 'am' ? 'እርስዎ ብቁ አይደሉም፣ እስኪመረቁ ድረስ መጠበቅ አለብዎት።' : 'You are not eligible, you must wait upto graduate.';
                    errorDiv.innerText = msg;
                    errorDiv.style.display = 'block';

                    document.getElementById('docType').value = ""; // Reset selection
                    section.style.display = 'none';
                }
            }
        }
        // Init state
        document.addEventListener('DOMContentLoaded', toggleUpload);
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>