<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['student']);

$user_id = $_SESSION['user_id'];
$student = $pdo->prepare("SELECT s.*, u.first_name, u.middle_name, u.last_name, d.name as dept_name, d.college, s.status_updated_at 
                          FROM students s 
                          JOIN users u ON s.user_id = u.id 
                          LEFT JOIN departments d ON s.department_id = d.id 
                          WHERE s.user_id = ?");
$student->execute([$user_id]);
$std_info = $student->fetch(PDO::FETCH_ASSOC);

if (!$std_info) {
    die("Student profile not found. Please contact registrar.");
}

// Check for Active Notice that allows filing
$today = date('Y-m-d');
$check_notice = $pdo->query("SELECT count(*) FROM notices WHERE has_form_link = 1 AND expiry_date >= '$today'");
$is_open = $check_notice->fetchColumn() > 0;

// Check if already signed for current semester
$current_year = $std_info['batch'];
$current_sem = $std_info['current_semester'];

$check = $pdo->prepare("SELECT * FROM cost_sharing_agreements WHERE student_id = ? AND academic_year = ? AND semester = ?");
$check->execute([$user_id, $current_year, $current_sem]);
$already_signed = $check->fetch();

// Fetch Credit Hour Rates for JS
$rates = $pdo->query("SELECT * FROM courses")->fetchAll(PDO::FETCH_ASSOC);
$rates_json = json_encode($rates);

// Check if tuition rate exists for this student's dept/batch/semester
$rate_check = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE department_id = ? AND batch = ? AND semester = ? AND cost_per_credit_hour > 0");
$rate_check->execute([$std_info['department_id'], $current_year, $current_sem]);
$has_rate = $rate_check->fetchColumn() > 0;

// Fetch Expense Settings from DB
$exp_settings = [];
$exp_rows = $pdo->query("SELECT * FROM expense_settings")->fetchAll(PDO::FETCH_ASSOC);
foreach ($exp_rows as $row) {
    $exp_settings[$row['setting_key']] = $row;
}
$food_val = $exp_settings['food_expense']['setting_value'] ?? 15000.00;
$bed_val = $exp_settings['bed_expense']['setting_value'] ?? 300.00;
$med_val = $exp_settings['medication_expense']['setting_value'] ?? 25.00;
$food_label = $exp_settings['food_expense']['calculation_label'] ?? '100 * 30 * 5';
$bed_label = $exp_settings['bed_expense']['calculation_label'] ?? '60 * 5';
$med_label = $exp_settings['medication_expense']['calculation_label'] ?? 'Fixed';

// Fetch Special Credit Override for this student (if dropped courses)
$special_credits = [];
if ($std_info['adjusted_credit_hours']) {
    $special_credits[] = [
        'batch' => $current_year,
        'semester' => $current_sem,
        'adjusted_credit_hours' => $std_info['adjusted_credit_hours'],
        'dropped_courses' => $std_info['dropped_courses'],
        'added_courses' => $std_info['added_courses'],
        'reason' => $std_info['special_credit_reason']
    ];
}
$special_json = json_encode($special_credits);

// Fetch most recent previous agreement for auto-fill (Birth, Mother, School)
$prev_stmt = $pdo->prepare("SELECT date_of_birth, pob_region, pob_zone, pob_wereda, pob_town, pob_kebele, pob_house, pob_pobox,
                                   mother_firstname, mother_middlename, mother_lastname, mom_region, mom_zone, mom_wereda, mom_town,
                                   prep_school, prep_completed_date
                            FROM cost_sharing_agreements 
                            WHERE student_id = ? 
                            ORDER BY id DESC LIMIT 1");
$prev_stmt->execute([$user_id]);
$prev = $prev_stmt->fetch(PDO::FETCH_ASSOC);
if (!$prev) $prev = [];

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_agreement'])) {
    if (!$has_rate) {
        $error = "<span data-en='Tuition rates have not been set yet. Please wait until the Cost Sharing Professional configures the rates.' data-am='የትምህርት ተመኖች ገና አልተዘጋጁም። እባክዎ የወጪ መጋራት ባለሙያው ተመኖችን እስኪያዘጋጁ ይጠብቁ።'>Tuition rates have not been set yet.</span>";
    } elseif ($already_signed) {
        $error = "<span data-en='You have already signed the agreement for this semester.' data-am='ለዚህ ሴሚስተር ስምምነትን አስቀድመው ፈርመዋል።'>You have already signed the agreement for this semester.</span>";
    } else {
        // Collect form data into variables
        $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
        $pob_region = $_POST['pob_region'] ?? null;
        $pob_zone = $_POST['pob_zone'] ?? null;
        $pob_wereda = $_POST['pob_wereda'] ?? null;
        $pob_town = $_POST['pob_town'] ?? null;
        $pob_kebele = $_POST['pob_kebele'] ?? null;
        $pob_house = $_POST['pob_house'] ?? null;
        $pob_phone = $_POST['pob_phone'] ?? null;
        $pob_pobox = $_POST['pob_pobox'] ?? null;
        $mother_firstname = $_POST['mother_firstname'] ?? null;
        $mother_middlename = $_POST['mother_middlename'] ?? null;
        $mother_lastname = $_POST['mother_lastname'] ?? null;
        $mom_region = $_POST['mom_region'] ?? null;
        $mom_zone = $_POST['mom_zone'] ?? null;
        $mom_wereda = $_POST['mom_wereda'] ?? null;
        $mom_town = $_POST['mom_town'] ?? null;
        $prep_school = $_POST['prep_school'] ?? null;
        $prep_completed_date = !empty($_POST['prep_completed_date']) ? $_POST['prep_completed_date'] : null;
        $transfer_uni = $_POST['transfer_uni'] ?? null;
        $transfer_cost_val = !empty($_POST['transfer_cost']) ? (float)$_POST['transfer_cost'] : null;
        $service_inkind = isset($_POST['service_inkind']) ? implode(',', $_POST['service_inkind']) : null;
        $service_cash = isset($_POST['service_cash']) ? implode(',', $_POST['service_cash']) : null;
        $remedial_year = $_POST['remedial_year'] ?? null;
        $payment_mode = $_POST['payment_mode'] ?? null;

        // Server-side validation: Both Food and Boarding must be assigned
        $inkind_arr = isset($_POST['service_inkind']) ? $_POST['service_inkind'] : [];
        $cash_arr = isset($_POST['service_cash']) ? $_POST['service_cash'] : [];
        $food_ok = in_array('Food', $inkind_arr) || in_array('Food', $cash_arr);
        $boarding_ok = in_array('Boarding', $inkind_arr) || in_array('Boarding', $cash_arr);
        if (!$food_ok || !$boarding_ok) {
            $error = "<span data-en='You must assign both Food and Boarding services (each to either In-kind or In Cash).' data-am='ምግብ እና መኝታ ሁለቱንም አገልግሎቶች መመደብ አለብዎት (እያንዳንዱን በዓይነት ወይም በገንዘብ)።'>You must assign both Food and Boarding services.</span>";
        }

        if (empty($error)) {
        try {
            $pdo->beginTransaction();

            $recheck = $pdo->prepare("SELECT id FROM cost_sharing_agreements WHERE student_id = ? AND academic_year = ? AND semester = ?");
            $recheck->execute([$user_id, $current_year, $current_sem]);
            if ($recheck->fetch()) {
                $pdo->rollBack();
                $error = "<span data-en='You have already signed the agreement for this semester.' data-am='ለዚህ ሴሚስተር ስምምነትን አስቀድመው ፈርመዋል።'>You have already signed the agreement for this semester.</span>";
            } else {
                $tuition = (float) $_POST['tuition_fee'];
                $food = (float)$food_val;
                $bed = (float)$bed_val;
                $med = (float)$med_val;
                $total_cost_calc = $tuition + $food + $bed + $med;

                $stmt = $pdo->prepare("INSERT INTO cost_sharing_agreements 
                    (student_id, academic_year, semester, agreement_date, status, signature_student, 
                     tuition_fee, food_expense, bed_expense, medication_expense,
                     date_of_birth, pob_region, pob_zone, pob_wereda, pob_town, pob_kebele, pob_house, pob_phone, pob_pobox,
                     mother_firstname, mother_middlename, mother_lastname, mom_region, mom_zone, mom_wereda, mom_town,
                     prep_school, prep_completed_date, transfer_uni, transfer_cost, service_inkind, service_cash, remedial_year, payment_mode) 
                    VALUES (?, ?, ?, NOW(), 'SignedByStudent', ?, 
                            ?, ?, ?, ?,
                            ?, ?, ?, ?, ?, ?, ?, ?, ?,
                            ?, ?, ?, ?, ?, ?, ?,
                            ?, ?, ?, ?, ?, ?, ?, ?)");
                $sig_text = "Digitally Signed by: " . $std_info['first_name'] . " " . $std_info['last_name'] . " (ID: " . $std_info['student_id'] . ")";
                $stmt->execute([
                    $user_id, $current_year, $current_sem, $sig_text,
                    $tuition, $food, $bed, $med,
                    $date_of_birth, $pob_region, $pob_zone, $pob_wereda, $pob_town, $pob_kebele, $pob_house, $pob_phone, $pob_pobox,
                    $mother_firstname, $mother_middlename, $mother_lastname, $mom_region, $mom_zone, $mom_wereda, $mom_town,
                    $prep_school, $prep_completed_date, $transfer_uni, $transfer_cost_val, $service_inkind, $service_cash, $remedial_year, $payment_mode
                ]);

                $pdo->prepare("UPDATE cost_sharing_agreements SET total_amount = (SELECT t.total FROM (SELECT COALESCE(SUM(tuition_fee + food_expense + bed_expense + medication_expense), 0) as total FROM cost_sharing_agreements WHERE student_id = ? AND status != 'Suspended') as t) WHERE student_id = ?")->execute([$user_id, $user_id]);

                $pdo->commit();

                $_SESSION['agreement_success'] = "Agreement signed successfully! Total Semester Cost: " . number_format($total_cost_calc, 2) . " Birr";
                header("Location: agreement_form.php");
                exit;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
        } // end if(empty($error))
    }
}

// Check for redirected success message (Post/Redirect/Get pattern)
if (isset($_SESSION['agreement_success'])) {
    $msg = $_SESSION['agreement_success'];
    unset($_SESSION['agreement_success']);
    $check->execute([$user_id, $current_year, $current_sem]);
    $already_signed = $check->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Cost Sharing Agreement - DMU" data-am="የወጪ መጋራት ስምምነት - DMU">Cost Sharing Agreement - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .paper-form {
            background: #fff;
            padding: 50px;
            /* Increased padding */
            border: 1px solid #ccc;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            max-width: 1000px;
            /* Increased width to prevent elongation issues */
            margin: 0 auto;
            color: #000;
            font-family: 'Times New Roman', serif;
            /* More paper-like font */
        }

        .header-section {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #000;
            padding-bottom: 20px;
        }

        .header-section h2,
        .header-section h3,
        .header-section h4 {
            margin: 5px 0;
            text-transform: uppercase;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            /* Allow wrapping */
            gap: 20px;
            margin-bottom: 20px;
            align-items: center;
            line-height: 1.6;
        }

        .form-row label {
            font-weight: bold;
            white-space: nowrap;
        }

        .w-full {
            width: 100%;
        }

        .inline-input {
            border: none;
            border-bottom: 1px solid #000;
            padding: 5px;
            background: transparent;
            outline: none;
            min-width: 100px;
            /* Ensure minimum width */
        }

        .inline-input:focus {
            border-bottom: 2px solid #007bff;
        }

        select.inline-input {
            background: #fff;
            /* Reset for selects */
            cursor: pointer;
        }

        .radio-group {
            display: flex;
            gap: 20px;
        }

        .cost-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .cost-table td,
        .cost-table th {
            border: 1px solid #ddd;
            padding: 12px;
        }

        .cost-table td input {
            width: 100%;
            border: none;
            background: transparent;
            font-weight: bold;
        }

        .success-banner {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
        }

        .error-banner {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>

            <div class="main-content">
                <?php if ($msg)
                    echo "<div class='success-banner'><h4><i class='fas fa-check-circle'></i> Success</h4>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-banner'>$error</div>"; ?>

                <?php if (strtolower($std_info['status']) !== 'active'): ?>
                    <div class="card" style="text-align: center; padding: 60px;">
                        <i class="fas fa-user-clock fa-5x" style="color: #e74c3c; margin-bottom: 20px;"></i>
                        <h2 data-en="Account Status Issue" data-am="የመለያ ሁኔታ ችግር">Account Status Issue</h2>
                        <p data-en="You cannot sign the Cost Sharing Agreement because your student status is currently:<?php echo htmlspecialchars($std_info['status']); ?>. Please contact the Registrar to resolve this."
                            data-am="የተማሪነት ሁኔታዎ በአሁኑ ጊዜ <?php echo htmlspecialchars($std_info['status']); ?> ስለሆነ የወጪ መጋራት ውል መፈረም አይችሉም። እባክዎን ይህንን ለመፍታት ሬጅስትራርን ያነጋግሩ።"
                            class="mb-20">
                            You cannot sign the Cost Sharing Agreement because your student status is currently:
                            <strong><?php echo htmlspecialchars($std_info['status']); ?></strong>.
                        </p>
                        <a href="dashboard.php" class="btn-primary mt-20" data-en="Back to Dashboard"
                            data-am="ወደ ዳሽቦርድ ይመለሱ">Back to Dashboard</a>
                    </div>
                <?php elseif (!$is_open): ?>
                    <div class="card" style="text-align: center; padding: 60px;">
                        <i class="fas fa-lock fa-5x" style="color: #6c757d; margin-bottom: 20px;"></i>
                        <h2 data-en="Registration Closed" data-am="ምዝገባ ተዘግቷል">Registration Closed</h2>
                        <p data-en="The Cost Sharing Agreement form is currently closed. Please wait for an official notice."
                            data-am="የወጪ መጋራት ስምምነት ቅጽ በአሁኑ ጊዜ ተዘግቷል። እባክዎን ኦፊሴላዊ ማስታወቂያ ይጠብቁ።" class="mb-20">
                            The Cost Sharing Agreement form is currently closed.</p>
                        <a href="dashboard.php" class="btn-primary mt-20" data-en="Back to Dashboard"
                            data-am="ወደ ዳሽቦርድ ይመለሱ">Back to Dashboard</a>
                    </div>
                <?php elseif ($already_signed): ?>
                    <div class="card" style="text-align: center; padding: 60px;">
                        <i class="fas fa-file-contract fa-5x" style="color: #28a745; margin-bottom: 20px;"></i>
                        <h2 data-en="Agreement Submitted" data-am="ስምምነት ተጠናቋል">Agreement Submitted</h2>

                        <!-- Status Messages -->
                        <?php if ($already_signed['status'] == 'ApprovedByCostPro'): ?>
                            <div class="success-banner"
                                style="font-size: 1.1em; background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                                <i class="fas fa-check-double"></i>
                                <strong data-en="Your agreement is approved by Department Head and Cost Sharing Professional."
                                    data-am="ስምምነትዎ በዲፓርትመንት ኃላፊ እና በወጪ መጋራት ባለሙያ ተረጋግጧል።">
                                    Your agreement is approved by Department Head and Cost Sharing Professional.
                                </strong>
                            </div>
                        <?php else: ?>
                            <p data-en="You have successfully signed the Cost Sharing Agreement for this semester."
                                data-am="ለዚህ ሴሚስተር የወጪ መጋራት ስምምነትን በተሳካ ሁኔታ ተፈራርመዋል።" class="mb-20">You have successfully signed
                                the Cost Sharing Agreement for this semester.</p>
                        <?php endif; ?>

                        <?php if ($msg)
                            echo "<p style='font-size: 1.2em; font-weight: bold;'>$msg</p>"; ?>
                        <a href="history.php" class="btn-primary mt-20" data-en="View My Cost History"
                            data-am="የወጪ ታሪክ ማየት">View My Cost History</a>
                    </div>
                <?php elseif (!$has_rate): ?>
                    <div class="card" style="text-align: center; padding: 60px;">
                        <i class="fas fa-hourglass-half fa-5x" style="color: #f39c12; margin-bottom: 20px;"></i>
                        <h2 data-en="Rates Not Yet Available" data-am="ተመኖች እስካሁን አልተዘጋጁም">Rates Not Yet Available</h2>
                        <p data-en="Please wait until the Cost Sharing Professional fills the tuition rates for your department, year, and semester."
                            data-am="እባክዎ የወጪ መጋራት ባለሙያው ለዲፓርትመንትዎ ፣ ለዓመት እና ለሴሚስተር የትምህርት ተመኖችን እስኪሞሉ ድረስ ይጠብቁ።" class="mb-20">
                            Please wait until the Cost Sharing Professional fills the tuition rates for your department, year, and semester.
                        </p>
                        <a href="dashboard.php" class="btn-primary mt-20" data-en="Back to Dashboard"
                            data-am="ወደ ዳሽቦርድ ይመለሱ">Back to Dashboard</a>
                    </div>
                <?php else: ?>
                    <form method="POST" id="agreementForm">
                        <div class="paper-form">
                            <div class="header-section">
                                <h3 data-en="FEDERAL DEMOCRATIC REPUBLIC OF ETHIOPIA" data-am="የኢትዮጵያ ፌዴራላዊ ዲሞክራሲያዊ ሪፐብሊክ">
                                    FEDERAL DEMOCRATIC REPUBLIC OF ETHIOPIA</h3>
                                <h4 data-en="MINISTRY OF EDUCATION" data-am="ትምህርት ሚኒስቴር">MINISTRY OF EDUCATION</h4>
                                <h3 data-en="HIGHER EDUCATION COST SHARING REGULATION" data-am="የከፍተኛ ትምህርት የወጪ መጋራት ደንብ">
                                    HIGHER EDUCATION COST SHARING REGULATION</h3>
                                <h4 data-en="Council of Ministers Regulation No. 154/2008"
                                    data-am="የሚኒስትሮች ምክር ቤት ደንብ ቁጥር 154/2008">Council of Ministers Regulation No. 154/2008
                                </h4>
                                <h2 data-en="BENEFICIARIES AGREEMENT FORM" data-am="የተጠቃሚዎች የውል ፎርም">BENEFICIARIES AGREEMENT
                                    FORM</h2>
                            </div>

                            <!-- 1. Identity -->
                            <div class="form-row">
                                <label data-en="1. First Name:" data-am="1. ስም:">1. First Name:</label>
                                <input type="text" name="first_name" value="<?php echo $std_info['first_name']; ?>"
                                    class="inline-input" style="flex:1;" readonly>
                                <label data-en="Middle Name:" data-am="የአባት ስም:">Middle Name:</label>
                                <input type="text" name="middle_name" value="<?php echo $std_info['middle_name']; ?>"
                                    class="inline-input" style="flex:1;" readonly>
                                <label data-en="Last Name:" data-am="የአያት ስም:">Last Name:</label>
                                <input type="text" name="last_name" value="<?php echo $std_info['last_name']; ?>"
                                    class="inline-input" style="flex:1;" readonly>
                            </div>
                            <div class="form-row">
                                <label data-en="Identity No:" data-am="የመታወቂያ ቁጥር:">Identity No:</label>
                                <input type="text" name="identity_no" value="<?php echo $std_info['student_id']; ?>"
                                    class="inline-input" style="flex:2;" readonly>
                            </div>

                            <!-- 2. Sex/Nationality -->
                            <div class="form-row">
                                <label data-en="2. Sex:" data-am="2. ጾታ:">2. Sex:</label>
                                <?php
                                $sex_en = $std_info['sex'];
                                $sex_am = (strtoupper($sex_en) == 'MALE' || strtoupper($sex_en) == 'M') ? 'ወንድ' : 'ሴት';

                                // Translation Mapping - Now using shared file
                                require_once '../../includes/academic_translations.php';

                                $college_en = $std_info['college'];
                                $college_am = $academic_translations[$college_en] ?? $college_en;
                                ?>
                                <span class="inline-input" style="flex:1; display:inline-block;"
                                    data-en="<?php echo $sex_en; ?>"
                                    data-am="<?php echo $sex_am; ?>"><?php echo $sex_en; ?></span>
                                <input type="hidden" name="sex" value="<?php echo $sex_en; ?>">

                                <label style="margin-left: 20px;" data-en="College/School:"
                                    data-am="ኮሌጅ/ትምህርት ቤት:">College/School:</label>
                                <span class="inline-input" style="flex:2; display:inline-block;"
                                    data-en="<?php echo $college_en; ?>"
                                    data-am="<?php echo $college_am; ?>"><?php echo $college_en; ?></span>
                                <input type="hidden" name="college" value="<?php echo $college_en; ?>">

                                <label style="margin-left: 20px;" data-en="Nationality:"
                                    data-am="ዜግነት:">Nationality:</label>
                                <span class="inline-input" style="flex:1; display:inline-block;" data-en="Ethiopian"
                                    data-am="ኢትዮጵያዊ">Ethiopian</span>
                                <input type="hidden" name="nationality" value="Ethiopian">

                                <label style="margin-left: 20px;" data-en="Status:" data-am="ሁኔታ:">Status:</label>
                                <?php
                                $status_map = [
                                    'active' => 'ንቁ',
                                    'suspended' => 'ታግዷል',
                                    'withdrawn' => 'ያቋረጠ',
                                    'graduated' => 'የተመረቀ'
                                ];
                                $st_key = strtolower($std_info['status']);
                                $st_am = $status_map[$st_key] ?? $std_info['status'];
                                ?>
                                <span class="inline-input" style="flex:1; display:inline-block;"
                                    data-en="<?php echo $std_info['status']; ?>"
                                    data-am="<?php echo $st_am; ?>"><?php echo $std_info['status']; ?></span>
                                <input type="hidden" name="status" value="<?php echo $std_info['status']; ?>">
                            </div>

                            <!-- 3. Birth -->
                            <div class="form-row">
                                <label data-en="3. Date of Birth:" data-am="3. የትውልድ ዘመን:">3. Date of Birth:</label>
                                <input type="date" name="withdrawal_date" class="inline-input" value="<?php echo htmlspecialchars($prev['date_of_birth'] ?? ''); ?>">
                            </div>

                            <div class="form-row">
                                <label data-en="Place of Birth:" data-am="የትውልድ ቦታ:">Place of Birth:</label>

                                <span data-en="Region:" data-am="ክልል:">Region:</span>
                                <?php $pr = $prev['pob_region'] ?? ''; ?>
                                <select name="pob_region" class="inline-input">
                                    <option data-en="Amhara" data-am="አማራ" <?php if($pr=='Amhara') echo 'selected'; ?>>Amhara</option>
                                    <option data-en="Oromia" data-am="ኦሮሚያ" <?php if($pr=='Oromia') echo 'selected'; ?>>Oromia</option>
                                    <option data-en="Addis Ababa" data-am="አዲስ አበባ" <?php if($pr=='Addis Ababa') echo 'selected'; ?>>Addis Ababa</option>
                                    <option data-en="Tigray" data-am="ትግራይ" <?php if($pr=='Tigray') echo 'selected'; ?>>Tigray</option>
                                    <option data-en="SNNPR" data-am="ደቡብ ክልል" <?php if($pr=='SNNPR') echo 'selected'; ?>>SNNPR</option>
                                    <option data-en="Sidama" data-am="ሲዳማ" <?php if($pr=='Sidama') echo 'selected'; ?>>Sidama</option>
                                    <option data-en="Somali" data-am="ሶማሌ" <?php if($pr=='Somali') echo 'selected'; ?>>Somali</option>
                                    <option data-en="Afar" data-am="አፋር" <?php if($pr=='Afar') echo 'selected'; ?>>Afar</option>
                                    <option data-en="Gambela" data-am="ጋምቤላ" <?php if($pr=='Gambela') echo 'selected'; ?>>Gambela</option>
                                    <option data-en="Benishangul-Gumuz" data-am="ቤኒሻንጉል-ጉሙዝ" <?php if($pr=='Benishangul-Gumuz') echo 'selected'; ?>>Benishangul-Gumuz</option>
                                    <option data-en="Harari" data-am="ሐረሪ" <?php if($pr=='Harari') echo 'selected'; ?>>Harari</option>
                                    <option data-en="Dire Dawa" data-am="ድሬዳዋ" <?php if($pr=='Dire Dawa') echo 'selected'; ?>>Dire Dawa</option>
                                </select>

                                </select>

                                <span data-en="Zone:" data-am="ዞን:">Zone:</span>
                                <?php $pz = $prev['pob_zone'] ?? ''; ?>
                                <select name="pob_zone" class="inline-input" style="min-width: 150px;">
                                    <option data-en="Select Zone" data-am="ዞን ይምረጡ" <?php if(!$pz) echo 'selected'; ?>>Select Zone</option>
                                    <option data-en="East Gojjam" data-am="ምስራቅ ጎጃም" <?php if($pz=='East Gojjam') echo 'selected'; ?>>East Gojjam</option>
                                    <option data-en="West Gojjam" data-am="ምዕራብ ጎጃም" <?php if($pz=='West Gojjam') echo 'selected'; ?>>West Gojjam</option>
                                    <option data-en="Awi" data-am="አዊ" <?php if($pz=='Awi') echo 'selected'; ?>>Awi</option>
                                    <option data-en="Bahir Dar" data-am="ባህር ዳር" <?php if($pz=='Bahir Dar') echo 'selected'; ?>>Bahir Dar</option>
                                    <option data-en="North Gondar" data-am="ሰሜን ጎንደር" <?php if($pz=='North Gondar') echo 'selected'; ?>>North Gondar</option>
                                    <option data-en="South Gondar" data-am="ደቡብ ጎንደር" <?php if($pz=='South Gondar') echo 'selected'; ?>>South Gondar</option>
                                    <option data-en="North Wollo" data-am="ሰሜን ወሎ" <?php if($pz=='North Wollo') echo 'selected'; ?>>North Wollo</option>
                                    <option data-en="South Wollo" data-am="ደቡብ ወሎ" <?php if($pz=='South Wollo') echo 'selected'; ?>>South Wollo</option>
                                    <option data-en="Wag Hemra" data-am="ዋግ ጀምራ" <?php if($pz=='Wag Hemra') echo 'selected'; ?>>Wag Hemra</option>
                                    <option data-en="North Shewa" data-am="ሰሜን ሸዋ" <?php if($pz=='North Shewa') echo 'selected'; ?>>North Shewa</option>
                                    <option data-en="Oromia Zone" data-am="ኦሮሚያ ዞን" <?php if($pz=='Oromia Zone') echo 'selected'; ?>>Oromia Zone</option>
                                    <option data-en="Other" data-am="ሌላ" <?php if($pz=='Other') echo 'selected'; ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-row">
                                <span data-en="Woreda:" data-am="ወረዳ:">Woreda:</span>
                                <?php $pw = $prev['pob_wereda'] ?? ''; ?>
                                <select name="pob_wereda" class="inline-input" style="min-width: 150px;">
                                    <option data-en="Select Woreda" data-am="ወረዳ ይምረጡ" <?php if(!$pw) echo 'selected'; ?>>Select Woreda</option>
                                    <option data-en="Debre Markos" data-am="ደብረ ማርቆስ" <?php if($pw=='Debre Markos') echo 'selected'; ?>>Debre Markos</option>
                                    <option data-en="Gozamin" data-am="ጎዛምን" <?php if($pw=='Gozamin') echo 'selected'; ?>>Gozamin</option>
                                    <option data-en="Machakel" data-am="ማቻከል" <?php if($pw=='Machakel') echo 'selected'; ?>>Machakel</option>
                                    <option data-en="Sinan" data-am="ሲናን" <?php if($pw=='Sinan') echo 'selected'; ?>>Sinan</option>
                                    <option data-en="Baso Liben" data-am="ባሶ ሊበን" <?php if($pw=='Baso Liben') echo 'selected'; ?>>Baso Liben</option>
                                    <option data-en="Other" data-am="ሌላ" <?php if($pw=='Other') echo 'selected'; ?>>Other</option>
                                </select>

                                </select>

                                <span data-en="Town:" data-am="ከተማ:">Town:</span>
                                <?php $pt = $prev['pob_town'] ?? ''; ?>
                                <select name="pob_town" class="inline-input" style="min-width: 150px;">
                                    <option data-en="Select Town" data-am="ከተማ ይምረጡ" <?php if(!$pt) echo 'selected'; ?>>Select Town</option>
                                    <option data-en="Debre Markos" data-am="ደብረ ማርቆስ" <?php if($pt=='Debre Markos') echo 'selected'; ?>>Debre Markos</option>
                                    <option data-en="Bahir Dar" data-am="ባህር ዳር" <?php if($pt=='Bahir Dar') echo 'selected'; ?>>Bahir Dar</option>
                                    <option data-en="Gondar" data-am="ጎንደር" <?php if($pt=='Gondar') echo 'selected'; ?>>Gondar</option>
                                    <option data-en="Addis Ababa" data-am="አዲስ አበባ" <?php if($pt=='Addis Ababa') echo 'selected'; ?>>Addis Ababa</option>
                                    <option data-en="Other" data-am="ሌላ" <?php if($pt=='Other') echo 'selected'; ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-row">
                                <span data-en="Kebele" data-am="ቀበሌ">Kebele</span> <input type="text" name="pob_kebele"
                                    class="inline-input" size="5" value="<?php echo htmlspecialchars($prev['pob_kebele'] ?? ''); ?>">
                                <span data-en="House No" data-am="የቤት ቁጥር">House No</span> <input type="text"
                                    name="pob_house" class="inline-input" size="5" value="<?php echo htmlspecialchars($prev['pob_house'] ?? ''); ?>">
                                <span data-en="Phone" data-am="ስልክ ቁጥር">Phone</span> <input type="text" name="pob_phone"
                                    class="inline-input" size="12">
                                <span data-en="P.O.Box" data-am="ፖ.ሳ.ቁ">P.O.Box</span> <input type="text" name="pob_pobox"
                                    class="inline-input" size="6" value="<?php echo htmlspecialchars($prev['pob_pobox'] ?? ''); ?>">
                            </div>

                            <!-- 4. Family (Mother Split) -->
                            <div class="form-row">
                                <div class="form-row">
                                    <label data-en="4. Mother's/Adopter's Name" data-am="4. የወላጅ/አሳዳጊ እናት ሙሉ ስም">4.
                                        Mother's/Adopter's Name</label>
                                </div>
                            </div>
                            <div class="form-row" style="padding-left: 20px;">
                                <span data-en="First Name:" data-am="ስም:">First Name:</span> <input type="text"
                                    name="mother_firstname" class="inline-input" style="flex:1;" value="<?php echo htmlspecialchars($prev['mother_firstname'] ?? ''); ?>">
                                <span data-en="Father Name:" data-am="የአባት ስም:">Father Name:</span> <input type="text"
                                    name="mother_middlename" class="inline-input" style="flex:1;" value="<?php echo htmlspecialchars($prev['mother_middlename'] ?? ''); ?>">
                                <span data-en="G.Father Name:" data-am="የአያት ስም:">G.Father Name:</span> <input type="text"
                                    name="mother_lastname" class="inline-input" style="flex:1;" value="<?php echo htmlspecialchars($prev['mother_lastname'] ?? ''); ?>">
                            </div>

                            <div class="form-row">
                                <label data-en="Mother's Address:" data-am="የእናት አድራሻ:">Mother's Address:</label>
                                <?php $mr = $prev['mom_region'] ?? ''; ?>
                                <span data-en="Region" data-am="ክልል">Region</span> <select name="mom_region"
                                    class="inline-input">
                                    <option data-en="Amhara" data-am="አማራ" <?php if($mr=='Amhara'||!$mr) echo 'selected'; ?>>Amhara</option>
                                    <option data-en="Oromia" data-am="ኦሮሚያ" <?php if($mr=='Oromia') echo 'selected'; ?>>Oromia</option>
                                    <option data-en="Other" data-am="ሌላ" <?php if($mr=='Other') echo 'selected'; ?>>Other</option>
                                </select>
                                <?php $mz = $prev['mom_zone'] ?? ''; ?>
                                <span data-en="Zone" data-am="ዞን">Zone</span> <select name="mom_zone" class="inline-input">
                                    <option data-en="Select Zone" data-am="ዞን ይምረጡ" <?php if(!$mz) echo 'selected'; ?>>Select Zone</option>
                                    <option <?php if($mz=='East Gojjam') echo 'selected'; ?>>East Gojjam</option>
                                    <option <?php if($mz=='Other') echo 'selected'; ?>>Other</option>
                                </select>
                                <?php $mw = $prev['mom_wereda'] ?? ''; ?>
                                <span data-en="Woreda" data-am="ወረዳ">Woreda</span> <select name="mom_wereda"
                                    class="inline-input">
                                    <option value="" data-en="Select Wereda" data-am="ወረዳ ይምረጡ" <?php if(!$mw) echo 'selected'; ?>>Select Wereda</option>
                                    <option <?php if($mw=='Debre Markos') echo 'selected'; ?>>Debre Markos</option>
                                    <option <?php if($mw=='Other') echo 'selected'; ?>>Other</option>
                                </select>
                                <?php $mt = $prev['mom_town'] ?? ''; ?>
                                <span data-en="Town" data-am="ከተማ">Town</span> <select name="mom_town" class="inline-input">
                                    <option value="" data-en="Select Town" data-am="ከተማ ይምረጡ" <?php if(!$mt) echo 'selected'; ?>>Select Town</option>
                                    <option <?php if($mt=='Debre Markos') echo 'selected'; ?>>Debre Markos</option>
                                    <option <?php if($mt=='Other') echo 'selected'; ?>>Other</option>
                                </select>
                            </div>

                            <!-- 5. School -->
                            <div class="form-row">
                                <label data-en="5. School Name (Preparatory):" data-am="5. የመሰናዶ ትምህርት ቤት ስም:">5. School
                                    Name (Preparatory):</label>
                                <input type="text" name="prep_school" class="inline-input" style="flex:1;" value="<?php echo htmlspecialchars($prev['prep_school'] ?? ''); ?>">
                                <span data-en="Date Completed:" data-am="የተጠናቀቀበት ቀን:">Date Completed:</span> <input
                                    type="date" name="prep_completed_date" class="inline-input" value="<?php echo htmlspecialchars($prev['prep_completed_date'] ?? ''); ?>">
                            </div>

                            <!-- 6. University -->
                            <div class="form-row">
                                <label data-en="6. University/ College/ Institute:" data-am="6. ዩኒቨርሲቲ/ ኮሌጅ/ ኢንስቲትዩት:">6.
                                    University/ College/ Institute:</label>
                                <span class="inline-input" style="flex:1; display:inline-block; font-weight:bold;"
                                    data-en="Debre Markos University" data-am="ደብረ ማርቆስ ዩኒቨርሲቲ">Debre Markos
                                    University</span>
                            </div>
                            <div class="form-row">
                                <label data-en="Department:" data-am="የትምህርት ክፍል:">Department:</label>
                                <?php
                                $dept_en = $std_info['dept_name'];
                                $dept_am = $academic_translations[$dept_en] ?? $dept_en;
                                ?>
                                <span class="inline-input" style="flex:1; display:inline-block; background:#eee;"
                                    data-en="<?php echo $dept_en; ?>"
                                    data-am="<?php echo $dept_am; ?>"><?php echo $dept_en; ?></span>

                                <input type="hidden" id="dept_id" value="<?php echo $std_info['department_id']; ?>">
                                <input type="hidden" id="dept_name_hidden" value="<?php echo $dept_en; ?>">

                                <label data-en="Year:" data-am="ዓመት:">Year:</label>
                                <input type="text" name="academic_year_i_v" value="<?php echo $std_info['batch']; ?>"
                                    id="yearInput" class="inline-input" style="width:50px; text-align:center;" readonly>

                                <label data-en="Semester:" data-am="ሴሚስተር:">Semester:</label>
                                <input type="text" name="semester" value="<?php echo $std_info['current_semester']; ?>"
                                    id="semesterInput" class="inline-input" style="width:50px; text-align:center;" readonly>
                            </div>

                            <!-- 7. Withdrawal -->
                            <div class="form-row">
                                <label data-en="7. If Withdrawal (Indicate Date):" data-am="7. አቋርጦ ከሆነ (ቀን ይግለጹ):">7. If
                                    Withdrawal (Indicate Date):</label>
                                <?php
                                $w_date = '';
                                if (($std_info['status'] ?? '') === 'Withdrawal' && !empty($std_info['status_updated_at'])) {
                                    $w_date = date('Y-m-d', strtotime($std_info['status_updated_at']));
                                }
                                ?>
                                <input type="date" name="withdrawal_date" value="<?php echo $w_date; ?>"
                                    class="inline-input" <?php echo ($w_date ? 'readonly' : ''); ?>>
                            </div>

                            <!-- 8. Transfer -->
                            <div class="form-row">
                                <label data-en="8. If Transferred (From):" data-am="8. ዝውውር ከሆነ (ከየት):">8. If Transferred
                                    (From):</label>
                                <input type="text" name="transfer_uni" class="inline-input" style="flex:1;">
                                <span data-en="Cost Used:" data-am="ጥቅም ላይ የዋለ ወጪ:">Cost Used:</span> <input type="text"
                                    name="transfer_cost" class="inline-input" size="10">
                            </div>

                            <!-- 9. Services -->
                            <div class="form-row">
                                <label data-en="9. Services Demanded:" data-am="9. የሚጠየቁ አገልግሎቶች:">9. Services
                                    Demanded:</label>
                            </div>
                            <div id="serviceValidationMsg"
                                style="display:none; background:#f8d7da; color:#721c24; padding:10px 15px; border:1px solid #f5c6cb; border-radius:5px; margin-bottom:10px; font-size:0.95em;">
                            </div>
                            <div class="form-row" style="justify-content: space-around;">
                                <div>
                                    <span data-en="A. In-kind:" data-am="ሀ. በዓይነት:">A. In-kind:</span>
                                    <label><input type="checkbox" name="service_inkind[]" value="Food" class="service-check"
                                            data-type="kind" data-service="food"> <span data-en="Food"
                                            data-am="ምግብ">Food</span></label>
                                    <label><input type="checkbox" name="service_inkind[]" value="Boarding"
                                            class="service-check" data-type="kind" data-service="boarding"> <span
                                            data-en="Boarding" data-am="መኝታ">Boarding</span></label>
                                </div>
                                <div>
                                    <span data-en="B. In Cash:" data-am="ለ. በገንዘብ:">B. In Cash:</span>
                                    <label><input type="checkbox" name="service_cash[]" value="Food" class="service-check"
                                            data-type="cash" data-service="food"> <span data-en="Food"
                                            data-am="ምግብ">Food</span></label>
                                    <label><input type="checkbox" name="service_cash[]" value="Boarding"
                                            class="service-check" data-type="cash" data-service="boarding"> <span
                                            data-en="Boarding" data-am="መኝታ">Boarding</span></label>
                                </div>
                            </div>

                            <!-- 10. Remedial -->
                            <div class="form-row">
                                <label data-en="10. Remedial Year (if applicable):"
                                    data-am="10.  የማካካሻ ዓመት (የሚመለከተው ከሆነ):">10. Remedial Year (if applicable):</label>
                                <input type="text" name="remedial_year" class="inline-input">
                            </div>

                            <!-- 11. Cost Estimate -->
                            <hr style="border-top: 2px solid #000; margin: 30px 0;">
                            <h4 data-en="11. Estimate Cost (Current Academic Year)"
                                data-am="11. የተገመተ ወጪ (የአሁኑ የትምህርት ዘመን)">11. Estimate Cost (Current Academic Year)</h4>
                            <table class="cost-table">
                                <tr style="background: #f0f0f0;">
                                    <th data-en="Service Type" data-am="የአገልግሎት ዓይነት">Service Type</th>
                                    <th data-en="Calculation" data-am="ስሌት">Calculation</th>
                                    <th data-en="Amount (Birr)" data-am="መጠን (ብር)">Amount (Birr)</th>
                                </tr>
                                <tr>
                                    <td data-en="15% Tuition Fee" data-am="15% የትምህርት ክፍያ">15% Tuition Fee</td>
                                    <td id="tuition_calc" data-en="Based on Credit Hours" data-am="በክሬዲት ሰዓት ላይ የተመሰረተ">
                                        Based on Credit Hours</td>
                                    <td><input type="text" name="tuition_fee" id="tuition_fee" readonly></td>
                                </tr>
                                <tr>
                                    <td data-en="Food Expense" data-am="የምግብ ወጪ">Food Expense</td>
                                    <td><?php echo htmlspecialchars($food_label); ?></td>
                                    <td><input type="text" name="food_expense" value="<?php echo number_format($food_val, 2); ?>" readonly></td>
                                </tr>
                                <tr>
                                    <td data-en="Bed Expense" data-am="የመኝታ ወጪ">Bed Expense</td>
                                    <td><?php echo htmlspecialchars($bed_label); ?></td>
                                    <td><input type="text" name="bed_expense" value="<?php echo number_format($bed_val, 2); ?>" readonly></td>
                                </tr>
                                <tr>
                                    <td data-en="Medication Expense" data-am="የህክምና ወጪ">Medication Expense</td>
                                    <td><?php echo htmlspecialchars($med_label); ?></td>
                                    <td><input type="text" name="med_expense" value="<?php echo number_format($med_val, 2); ?>" readonly></td>
                                </tr>
                                <tr style="background: #e9ecef;">
                                    <td colspan="2" style="text-align: right;"><strong data-en="TOTAL ESTIMATED COST"
                                            data-am="ጠቅላላ የተገመተ ወጪ">TOTAL ESTIMATED COST</strong></td>
                                    <td><input type="text" name="total_cost" id="total_cost" readonly
                                            style="font-weight:bold; font-size: 1.1em;"></td>
                                </tr>
                            </table>

                            <!-- 12. Agreement -->
                            <div style="margin-top: 30px; background: #fafafa; padding: 20px; border: 1px solid #ddd;">
                                <p data-en="I, <?php echo $std_info['first_name'] . ' ' . $std_info['last_name']; ?>, in accordance with this contractual agreement and the higher education Proclamation No 351/1995 and the higher education cost sharing Regulation 154/2008 of the council of ministers, agree to pay the above cost after graduation:"
                                    data-am="እኔ, <?php echo $std_info['first_name'] . ' ' . $std_info['last_name']; ?>, በዚህ የውል ስምምነት እና በከፍተኛ ትምህርት አዋጅ ቁጥር 351/1995 እንዲሁም በሚኒስትሮች ምክር ቤት የወጪ መጋራት ደንብ ቁጥር 154/2008 መሰረት ከምረቃ በኋላ ከላይ የተጠቀሰውን ወጪ ለመክፈል እስማማለሁ:">
                                    I,
                                    <strong><?php echo $std_info['first_name'] . ' ' . $std_info['last_name']; ?></strong>,
                                    in accordance with this contractual agreement and the higher education Proclamation No
                                    351/1995 and the higher education cost sharing Regulation 154/2008 of the council of
                                    ministers, agree to pay the above cost <strong>after graduation</strong>:
                                </p>
                                <div class="form-row">
                                    <label style="cursor:pointer;">
                                        <input type="radio" name="payment_mode" value="Income" required>
                                        <span data-en="A. To be paid from my income(Deduction)"
                                            data-am="ሀ. ከገቢዬ ተቀናሽ ሆኖ እንዲከፈል">A. To be paid from my income(Deduction)</span>
                                    </label>
                                </div>
                                <div class="form-row" style="margin-top: 20px;">
                                    <label style="font-size: 1.1em; font-weight: bold; color: #d9534f;">
                                        <input type="checkbox" name="beneficiary_signature_check" value="1" required
                                            style="transform: scale(1.5); margin-right: 10px;">
                                        <span data-en="Beneficiary's Signature (I have read and agreed to the above terms)"
                                            data-am="የተጠቃሚው ፊርማ (ከላይ የተገለጹትን ሁኔታዎች አንብቤ ተስማምቻለሁ)">Beneficiary's Signature (I
                                            have read and agreed to the above terms)</span>
                                    </label>

                                    <div style="margin-left: auto;">
                                        <label data-en="Date:" data-am="ቀን:">Date:</label>
                                        <input type="text" value="<?php echo date('d/m/Y'); ?>" readonly
                                            class="inline-input">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" name="submit_agreement" class="btn-primary w-full"
                                style="margin-top: 30px; color: #ffffff; padding: 15px; background-color: #000000; font-size: 1.2em;"
                                data-en="Sign Agreement" data-am="ስምምነት ይፈርሙ"
                                onclick="return validateServicesOnSubmit()">Sign Agreement</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>

    <script>
        const rates = <?php echo $rates_json; ?>;
        const specialCredits = <?php echo $special_json; ?>;

        function calculateTuition() {
            const deptId = document.getElementById('dept_id').value;
            const year = document.getElementById('yearInput').value;
            const sem = document.getElementById('semesterInput').value;

            // Find rate matching department, batch, and semester
            let rateObj = rates.find(r => r.department_id == deptId && r.batch == year && r.semester == sem);

            // Check for special credit override (student dropped courses)
            let specialObj = specialCredits.find(s => s.batch == year && s.semester == sem);

            let tuition = 0;
            const calcEl = document.getElementById('tuition_calc');
            if (rateObj) {
                let creditHours = parseFloat(rateObj.credit_hours);
                const costPerCredit = parseFloat(rateObj.cost_per_credit_hour);

                // Use adjusted credit hours if student has a special case
                if (specialObj) {
                    creditHours = parseFloat(specialObj.adjusted_credit_hours);
                    const enText = creditHours + ' Cr.Hrs (adjusted) × ' + costPerCredit.toFixed(2) + ' Birr';
                    const amText = creditHours + ' የክሬዲት ሰዓት (ተስተካክሏል) × ' + costPerCredit.toFixed(2) + ' ብር';

                    calcEl.textContent = localStorage.getItem('dmu_lang') === 'am' ? amText : enText;
                    calcEl.setAttribute('data-en', enText);
                    calcEl.setAttribute('data-am', amText);
                } else {
                    const enText = creditHours + ' Cr.Hrs × ' + costPerCredit.toFixed(2) + ' Birr';
                    const amText = creditHours + ' የክሬዲት ሰዓት × ' + costPerCredit.toFixed(2) + ' ብር';

                    calcEl.textContent = localStorage.getItem('dmu_lang') === 'am' ? amText : enText;
                    calcEl.setAttribute('data-en', enText);
                    calcEl.setAttribute('data-am', amText);
                }

                tuition = creditHours * costPerCredit;
            } else {
                calcEl.textContent = 'No rate found for your Dept/Batch/Semester';
                calcEl.setAttribute('data-en', 'No rate found for your Dept/Batch/Semester');
                calcEl.setAttribute('data-am', 'ቅናሽ/ባች/ሴሚስተር አልተገኘም');
            }

            document.getElementById('tuition_fee').value = tuition.toFixed(2);

            const food = <?php echo $food_val; ?>;
            const bed = <?php echo $bed_val; ?>;
            const med = <?php echo $med_val; ?>;

            const total = tuition + food + bed + med;
            document.getElementById('total_cost').value = total.toFixed(2);
        }

        // --- VALIDATION LOGIC ---
        document.addEventListener('DOMContentLoaded', function () {
            const checkboxes = document.querySelectorAll('.service-check');

            checkboxes.forEach(cb => {
                cb.addEventListener('change', function () {
                    validateServices(this);
                });
            });
        });

        function showValidationMsg(msg) {
            const el = document.getElementById('serviceValidationMsg');
            el.textContent = msg;
            el.style.display = 'block';
            // Scroll into view
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // Auto-hide after 5 seconds
            setTimeout(() => { el.style.display = 'none'; }, 5000);
        }

        function hideValidationMsg() {
            document.getElementById('serviceValidationMsg').style.display = 'none';
        }

        function validateServices(changedCb) {
            const type = changedCb.getAttribute('data-type'); // 'kind' or 'cash'
            const service = changedCb.getAttribute('data-service'); // 'food' or 'boarding'

            hideValidationMsg();

            const allChecks = document.querySelectorAll('.service-check');

            // 1. Mutually Exclusive (Cannot pick same service in Kind AND Cash)
            // If I checked Food In-Kind, uncheck Food In-Cash (and vice versa)
            if (changedCb.checked) {
                const otherType = (type === 'kind') ? 'cash' : 'kind';
                const conflictCb = document.querySelector(`.service-check[data-type="${otherType}"][data-service="${service}"]`);
                if (conflictCb && conflictCb.checked) {
                    const msg = localStorage.getItem('dmu_lang') === 'am' ? `ይህን አገልግሎት (${service}) በዓይነት እና በገንዘብ በአንድ ጊዜ መምረጥ አይችሉም።` : `You cannot select ${service} in both Kind and Cash.`;
                    showValidationMsg(msg);
                    changedCb.checked = false;
                    return;
                }
            }

            // 2. Category B (Cash) Restriction: Cannot select BOTH Food and Boarding in Cash
            // "From B (Cash), both cannot be selected"
            if (type === 'cash' && changedCb.checked) {
                // Check if other cash item is checked
                const otherService = (service === 'food') ? 'boarding' : 'food';
                const otherCashCb = document.querySelector(`.service-check[data-type="cash"][data-service="${otherService}"]`);

                if (otherCashCb && otherCashCb.checked) {
                    const msg = localStorage.getItem('dmu_lang') === 'am' ? 'በገንዘብ አንድ አገልግሎት ብቻ መምረጥ ይችላሉ (ምግብ ወይም መኝታ)።' : 'You can only select one service In Cash (Food OR Boarding), not both.';
                    showValidationMsg(msg);
                    changedCb.checked = false;
                    return;
                }
            }
        }

        // Submit validation: Both Food and Boarding must be assigned
        function validateServicesOnSubmit() {
            const kindFood = document.querySelector('.service-check[data-type="kind"][data-service="food"]');
            const kindBoarding = document.querySelector('.service-check[data-type="kind"][data-service="boarding"]');
            const cashFood = document.querySelector('.service-check[data-type="cash"][data-service="food"]');
            const cashBoarding = document.querySelector('.service-check[data-type="cash"][data-service="boarding"]');

            const foodAssigned = (kindFood && kindFood.checked) || (cashFood && cashFood.checked);
            const boardingAssigned = (kindBoarding && kindBoarding.checked) || (cashBoarding && cashBoarding.checked);

            if (!foodAssigned && !boardingAssigned) {
                const msg = localStorage.getItem('dmu_lang') === 'am'
                    ? 'ምግብ እና መኝታ ሁለቱንም አገልግሎቶች መመደብ አለብዎት (እያንዳንዱን በዓይነት ወይም በገንዘብ)።'
                    : 'You must assign both Food and Boarding services (each to In-kind or In Cash).';
                showValidationMsg(msg);
                return false;
            }
            if (!foodAssigned) {
                const msg = localStorage.getItem('dmu_lang') === 'am'
                    ? 'ምግብ አገልግሎትን በዓይነት ወይም በገንዘብ መመደብ አለብዎት።'
                    : 'You must assign Food service to either In-kind or In Cash.';
                showValidationMsg(msg);
                return false;
            }
            if (!boardingAssigned) {
                const msg = localStorage.getItem('dmu_lang') === 'am'
                    ? 'መኝታ አገልግሎትን በዓይነት ወይም በገንዘብ መመደብ አለብዎት።'
                    : 'You must assign Boarding service to either In-kind or In Cash.';
                showValidationMsg(msg);
                return false;
            }
            return true;
        }

        // Init calc
        window.onload = function () {
            calculateTuition();
        };
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>