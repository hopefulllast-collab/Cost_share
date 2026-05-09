<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['student']);
require_once '../../includes/academic_translations.php';

$user_id = $_SESSION['user_id'];

// Fetch student info
$student = $pdo->prepare("SELECT s.*, u.first_name, u.middle_name, u.last_name, d.name as dept_name, d.college
                          FROM students s 
                          JOIN users u ON s.user_id = u.id 
                          LEFT JOIN departments d ON s.department_id = d.id 
                          WHERE s.user_id = ?");
$student->execute([$user_id]);
$std_info = $student->fetch(PDO::FETCH_ASSOC);

if (!$std_info) {
    die("Student profile not found.");
}

// Fetch ALL agreements for this student (all batches/semesters)
$all_stmt = $pdo->prepare("SELECT csa.id, csa.academic_year, csa.semester, csa.status, csa.agreement_date
                        FROM cost_sharing_agreements csa
                        WHERE csa.student_id = ?
                        ORDER BY csa.academic_year DESC, csa.semester DESC");
$all_stmt->execute([$user_id]);
$all_agreements = $all_stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_id = isset($_GET['agreement_id']) ? (int)$_GET['agreement_id'] : 0;

$agreement = null;
$content = [];

if ($selected_id > 0) {
    $stmt = $pdo->prepare("SELECT csa.*, s.student_id, d.name as dept_name, d.college,
                            dh.first_name as dept_head_fname, dh.middle_name as dept_head_mname,
                            cp.first_name as cost_pro_fname, cp.middle_name as cost_pro_mname
                            FROM cost_sharing_agreements csa
                            JOIN students s ON csa.student_id = s.user_id
                            JOIN departments d ON s.department_id = d.id
                            LEFT JOIN users dh ON csa.head_user_id = dh.id
                            LEFT JOIN users cp ON cp.role = 'cost_sharing_pro' AND csa.signature_cost_pro IS NOT NULL AND csa.signature_cost_pro != ''
                            WHERE csa.id = ? AND csa.student_id = ?");
    $stmt->execute([$selected_id, $user_id]);
    $agreement = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (!empty($all_agreements)) {
    $default_id = $all_agreements[0]['id'];
    foreach ($all_agreements as $ag) {
        if ($ag['status'] === 'ApprovedByCostPro') { $default_id = $ag['id']; break; }
    }
    $stmt = $pdo->prepare("SELECT csa.*, s.student_id, d.name as dept_name, d.college,
                            dh.first_name as dept_head_fname, dh.middle_name as dept_head_mname,
                            cp.first_name as cost_pro_fname, cp.middle_name as cost_pro_mname
                            FROM cost_sharing_agreements csa
                            JOIN students s ON csa.student_id = s.user_id
                            JOIN departments d ON s.department_id = d.id
                            LEFT JOIN users dh ON csa.head_user_id = dh.id
                            LEFT JOIN users cp ON cp.role = 'cost_sharing_pro' AND csa.signature_cost_pro IS NOT NULL AND csa.signature_cost_pro != ''
                            WHERE csa.id = ? AND csa.student_id = ?");
    $stmt->execute([$default_id, $user_id]);
    $agreement = $stmt->fetch(PDO::FETCH_ASSOC);
}

// No need to decode content_json - data is now in separate columns
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="View Agreement - DMU" data-am="ስምምነት ይመልከቱ - DMU">View Agreement - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .paper-form {
            background: #fff;
            padding: 50px;
            border: 1px solid #ccc;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            max-width: 1000px;
            margin: 0 auto;
            color: #000;
            font-family: 'Times New Roman', serif;
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
            gap: 20px;
            margin-bottom: 20px;
            align-items: center;
            line-height: 1.6;
        }

        .form-row label {
            font-weight: bold;
            white-space: nowrap;
        }

        .inline-value {
            border: none;
            border-bottom: 1px solid #000;
            padding: 5px;
            background: transparent;
            min-width: 100px;
            display: inline-block;
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

        .signature-section {
            margin-top: 40px;
            border-top: 3px solid #000;
            padding-top: 30px;
        }

        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }

        .signature-box {
            text-align: center;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }

        .signature-box .sig-label {
            font-weight: bold;
            font-size: 0.9em;
            color: #555;
            margin-bottom: 10px;
            display: block;
        }

        .signature-box .sig-value {
            font-size: 1em;
            color: #000;
            padding: 10px;
            min-height: 40px;
            border-bottom: 2px solid #000;
            margin-bottom: 5px;
        }

        .signature-box .sig-status {
            font-size: 0.85em;
            margin-top: 8px;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
        }

        .sig-approved {
            background: #d4edda;
            color: #155724;
        }

        .print-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            margin-top: 20px;
        }

        .print-btn:hover {
            background: #5a6268;
        }

        @media print {
            body { background: white; }
            .sidebar, .top-bar, .main-header, .print-btn, nav { display: none !important; }
            .main-content { margin: 0 !important; width: 100% !important; padding: 0 !important; }
            .layout-body { display: block !important; }
            .dashboard-container { display: block !important; }
            .paper-form { box-shadow: none !important; border: none !important; }
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
                    <h2 data-en="View Agreement" data-am="ስምምነት ይመልከቱ">View Agreement</h2>
                </div>

                <?php if (!empty($all_agreements) && count($all_agreements) > 1): ?>
                <div class="card mb-20" style="padding:15px 20px;">
                    <form method="GET" style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
                        <label style="font-weight:bold; white-space:nowrap;">
                            <i class="fas fa-calendar-alt" style="color:#007bff;"></i>
                            <span data-en="Select Agreement:" data-am="ስምምነት ይምረጡ:">Select Agreement:</span>
                        </label>
                        <select name="agreement_id" onchange="this.form.submit()" style="padding:8px 15px; border:2px solid #007bff; border-radius:5px; font-size:14px; cursor:pointer;">
                            <?php foreach ($all_agreements as $ag): 
                                $sl = ['SignedByStudent' => 'Pending', 'VerifiedByDept' => 'Dept Approved', 'ApprovedByCostPro' => 'Approved'][$ag['status']] ?? $ag['status'];
                                $sel_attr = ($agreement && $agreement['id'] == $ag['id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $ag['id']; ?>" <?php echo $sel_attr; ?>>
                                    Year <?php echo $ag['academic_year']; ?> - Sem <?php echo $ag['semester']; ?> (<?php echo $sl; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <?php endif; ?>

                <?php if (!$agreement): ?>
                    <div class="card" style="text-align: center; padding: 60px;">
                        <i class="fas fa-file-contract fa-5x" style="color: #6c757d; margin-bottom: 20px;"></i>
                        <h2 data-en="No Approved Agreement" data-am="የጸደቀ ስምምነት የለም">No Approved Agreement</h2>
                        <p data-en="You don't have a fully approved agreement yet. Once your agreement is approved by both the Department Head and Cost Sharing Professional, it will appear here."
                            data-am="እስካሁን ሙሉ በሙሉ የጸደቀ ስምምነት የሎትም። ስምምነትዎ በዲፓርትመንት ኃላፊ እና በወጪ መጋራት ባለሙያ ከተጸደቀ በኋላ እዚህ ይታያል።" class="mb-20">
                            You don't have a fully approved agreement yet.
                        </p>
                        <a href="dashboard.php" class="btn-primary mt-20" data-en="Back to Dashboard"
                            data-am="ወደ ዳሽቦርድ ይመለሱ">Back to Dashboard</a>
                    </div>
                <?php else: ?>
                    <?php
                    $college_en = $agreement['college'] ?? '';
                    $college_am = $academic_translations[$college_en] ?? $college_en;
                    $dept_en = $agreement['dept_name'] ?? '';
                    $dept_am = $academic_translations[$dept_en] ?? $dept_en;
                    $sex_en = $std_info['sex'];
                    $sex_am = (strtoupper($sex_en) == 'MALE' || strtoupper($sex_en) == 'M') ? 'ወንድ' : 'ሴት';
                    ?>

                    <div style="text-align: right; margin-bottom: 15px;">
                        <button class="print-btn" onclick="window.print()">
                            <i class="fas fa-print"></i> <span data-en="Print Agreement" data-am="ስምምነት አትም">Print Agreement</span>
                        </button>
                    </div>

                    <div class="paper-form">
                        <div class="header-section">
                            <h3 data-en="FEDERAL DEMOCRATIC REPUBLIC OF ETHIOPIA" data-am="የኢትዮጵያ ፌዴራላዊ ዲሞክራሲያዊ ሪፐብሊክ">
                                FEDERAL DEMOCRATIC REPUBLIC OF ETHIOPIA</h3>
                            <h4 data-en="MINISTRY OF EDUCATION" data-am="ትምህርት ሚኒስቴር">MINISTRY OF EDUCATION</h4>
                            <h3 data-en="HIGHER EDUCATION COST SHARING REGULATION" data-am="የከፍተኛ ትምህርት የወጪ መጋራት ደንብ">
                                HIGHER EDUCATION COST SHARING REGULATION</h3>
                            <h4 data-en="Council of Ministers Regulation No. 154/2008"
                                data-am="የሚኒስትሮች ምክር ቤት ደንብ ቁጥር 154/2008">Council of Ministers Regulation No. 154/2008</h4>
                            <h2 data-en="BENEFICIARIES AGREEMENT FORM" data-am="የተጠቃሚዎች የውል ፎርም">BENEFICIARIES AGREEMENT FORM</h2>
                        </div>

                        <!-- 1. Identity -->
                        <div class="form-row">
                            <label data-en="1. First Name:" data-am="1. ስም:">1. First Name:</label>
                            <span class="inline-value" style="flex:1;"><?php echo htmlspecialchars($std_info['first_name']); ?></span>
                            <label data-en="Middle Name:" data-am="የአባት ስም:">Middle Name:</label>
                            <span class="inline-value" style="flex:1;"><?php echo htmlspecialchars($std_info['middle_name']); ?></span>
                            <label data-en="Last Name:" data-am="የአያት ስም:">Last Name:</label>
                            <span class="inline-value" style="flex:1;"><?php echo htmlspecialchars($std_info['last_name']); ?></span>
                        </div>
                        <div class="form-row">
                            <label data-en="Identity No:" data-am="የመታወቂያ ቁጥር:">Identity No:</label>
                            <span class="inline-value" style="flex:2;"><?php echo htmlspecialchars($std_info['student_id']); ?></span>
                        </div>

                        <!-- 2. Sex/Nationality -->
                        <div class="form-row">
                            <label data-en="2. Sex:" data-am="2. ጾታ:">2. Sex:</label>
                            <span class="inline-value" style="flex:1;" data-en="<?php echo $sex_en; ?>" data-am="<?php echo $sex_am; ?>"><?php echo htmlspecialchars($sex_en); ?></span>

                            <label style="margin-left: 20px;" data-en="College/School:" data-am="ኮሌጅ/ትምህርት ቤት:">College/School:</label>
                            <span class="inline-value" style="flex:2;" data-en="<?php echo htmlspecialchars($college_en); ?>" data-am="<?php echo htmlspecialchars($college_am); ?>"><?php echo htmlspecialchars($college_en); ?></span>

                            <label style="margin-left: 20px;" data-en="Nationality:" data-am="ዜግነት:">Nationality:</label>
                            <span class="inline-value" style="flex:1;" data-en="Ethiopian" data-am="ኢትዮጵያዊ">Ethiopian</span>

                            <label style="margin-left: 20px;" data-en="Status:" data-am="ሁኔታ:">Status:</label>
                            <span class="inline-value" style="flex:1;"><?php echo htmlspecialchars($std_info['status']); ?></span>
                        </div>

                        <!-- 3. Birth -->
                        <div class="form-row">
                            <label data-en="3. Date of Birth:" data-am="3. የትውልድ ዘመን:">3. Date of Birth:</label>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['date_of_birth'] ?? ''); ?></span>
                        </div>

                        <div class="form-row">
                            <label data-en="Place of Birth:" data-am="የትውልድ ቦታ:">Place of Birth:</label>
                            <span data-en="Region:" data-am="ክልል:">Region:</span>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['pob_region'] ?? ''); ?></span>
                            <span data-en="Zone:" data-am="ዞን:">Zone:</span>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['pob_zone'] ?? ''); ?></span>
                        </div>
                        <div class="form-row">
                            <span data-en="Woreda:" data-am="ወረዳ:">Woreda:</span>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['pob_wereda'] ?? ''); ?></span>
                            <span data-en="Town:" data-am="ከተማ:">Town:</span>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['pob_town'] ?? ''); ?></span>
                        </div>
                        <div class="form-row">
                            <span data-en="Kebele" data-am="ቀበሌ">Kebele</span>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['pob_kebele'] ?? ''); ?></span>
                            <span data-en="House No" data-am="የቤት ቁጥር">House No</span>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['pob_house'] ?? ''); ?></span>
                            <span data-en="Phone" data-am="ስልክ ቁጥር">Phone</span>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['pob_phone'] ?? ''); ?></span>
                            <span data-en="P.O.Box" data-am="ፖ.ሳ.ቁ">P.O.Box</span>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['pob_pobox'] ?? ''); ?></span>
                        </div>

                        <!-- 4. Family -->
                        <div class="form-row">
                            <label data-en="4. Mother's/Adopter's Name" data-am="4. የወላጅ/አሳዳጊ እናት ሙሉ ስም">4. Mother's/Adopter's Name</label>
                        </div>
                        <div class="form-row" style="padding-left: 20px;">
                            <span data-en="First Name:" data-am="ስም:">First Name:</span>
                            <span class="inline-value" style="flex:1;"><?php echo htmlspecialchars($agreement['mother_firstname'] ?? ''); ?></span>
                            <span data-en="Father Name:" data-am="የአባት ስም:">Father Name:</span>
                            <span class="inline-value" style="flex:1;"><?php echo htmlspecialchars($agreement['mother_middlename'] ?? ''); ?></span>
                            <span data-en="G.Father Name:" data-am="የአያት ስም:">G.Father Name:</span>
                            <span class="inline-value" style="flex:1;"><?php echo htmlspecialchars($agreement['mother_lastname'] ?? ''); ?></span>
                        </div>

                        <div class="form-row">
                            <label data-en="Mother's Address:" data-am="የእናት አድራሻ:">Mother's Address:</label>
                            <span data-en="Region" data-am="ክልል">Region</span>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['mom_region'] ?? ''); ?></span>
                            <span data-en="Zone" data-am="ዞን">Zone</span>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['mom_zone'] ?? ''); ?></span>
                            <span data-en="Woreda" data-am="ወረዳ">Woreda</span>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['mom_wereda'] ?? ''); ?></span>
                            <span data-en="Town" data-am="ከተማ">Town</span>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['mom_town'] ?? ''); ?></span>
                        </div>

                        <!-- 5. School -->
                        <div class="form-row">
                            <label data-en="5. School Name (Preparatory):" data-am="5. የመሰናዶ ትምህርት ቤት ስም:">5. School Name (Preparatory):</label>
                            <span class="inline-value" style="flex:1;"><?php echo htmlspecialchars($agreement['prep_school'] ?? ''); ?></span>
                            <span data-en="Date Completed:" data-am="የተጠናቀቀበት ቀን:">Date Completed:</span>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['prep_completed_date'] ?? ''); ?></span>
                        </div>

                        <!-- 6. University -->
                        <div class="form-row">
                            <label data-en="6. University/ College/ Institute:" data-am="6. ዩኒቨርሲቲ/ ኮሌጅ/ ኢንስቲትዩት:">6. University/ College/ Institute:</label>
                            <span class="inline-value" style="flex:1; font-weight:bold;" data-en="Debre Markos University" data-am="ደብረ ማርቆስ ዩኒቨርሲቲ">Debre Markos University</span>
                        </div>
                        <div class="form-row">
                            <label data-en="Department:" data-am="የትምህርት ክፍል:">Department:</label>
                            <span class="inline-value" style="flex:1; background:#eee;" data-en="<?php echo htmlspecialchars($dept_en); ?>" data-am="<?php echo htmlspecialchars($dept_am); ?>"><?php echo htmlspecialchars($dept_en); ?></span>

                            <label data-en="Year:" data-am="ዓመት:">Year:</label>
                            <span class="inline-value" style="width:50px; text-align:center;"><?php echo htmlspecialchars($agreement['academic_year']); ?></span>

                            <label data-en="Semester:" data-am="ሴሚስተር:">Semester:</label>
                            <span class="inline-value" style="width:50px; text-align:center;"><?php echo htmlspecialchars($agreement['semester']); ?></span>
                        </div>

                        <!-- 7. Withdrawal -->
                        <div class="form-row">
                            <label data-en="7. If Withdrawal (Indicate Date):" data-am="7. አቋርጦ ከሆነ (ቀን ይግለጹ):">7. If Withdrawal (Indicate Date):</label>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['date_of_birth'] ?? ''); ?></span>
                        </div>

                        <!-- 8. Transfer -->
                        <div class="form-row">
                            <label data-en="8. If Transferred (From):" data-am="8. ዝውውር ከሆነ (ከየት):">8. If Transferred (From):</label>
                            <span class="inline-value" style="flex:1;"><?php echo htmlspecialchars($agreement['transfer_uni'] ?? ''); ?></span>
                            <span data-en="Cost Used:" data-am="ጥቅም ላይ የዋለ ወጪ:">Cost Used:</span>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['transfer_cost'] ?? ''); ?></span>
                        </div>

                        <!-- 9. Services -->
                        <div class="form-row">
                            <label data-en="9. Services Demanded:" data-am="9. የሚጠየቁ አገልግሎቶች:">9. Services Demanded:</label>
                        </div>
                        <div class="form-row" style="justify-content: space-around;">
                            <div>
                                <span data-en="A. In-kind:" data-am="ሀ. በዓይነት:">A. In-kind:</span>
                                <?php
                                $inkind = !empty($agreement['service_inkind']) ? explode(',', $agreement['service_inkind']) : [];
                                
                                ?>
                                <label><input type="checkbox" disabled <?php echo in_array('Food', $inkind) ? 'checked' : ''; ?>> <span data-en="Food" data-am="ምግብ">Food</span></label>
                                <label><input type="checkbox" disabled <?php echo in_array('Boarding', $inkind) ? 'checked' : ''; ?>> <span data-en="Boarding" data-am="መኝታ">Boarding</span></label>
                            </div>
                            <div>
                                <span data-en="B. In Cash:" data-am="ለ. በገንዘብ:">B. In Cash:</span>
                                <?php
                                $cash = !empty($agreement['service_cash']) ? explode(',', $agreement['service_cash']) : [];
                                
                                ?>
                                <label><input type="checkbox" disabled <?php echo in_array('Food', $cash) ? 'checked' : ''; ?>> <span data-en="Food" data-am="ምግብ">Food</span></label>
                                <label><input type="checkbox" disabled <?php echo in_array('Boarding', $cash) ? 'checked' : ''; ?>> <span data-en="Boarding" data-am="መኝታ">Boarding</span></label>
                            </div>
                        </div>

                        <!-- 10. Remedial -->
                        <div class="form-row">
                            <label data-en="10. Remedial Year (if applicable):" data-am="10. የማካካሻ ዓመት (የሚመለከተው ከሆነ):">10. Remedial Year (if applicable):</label>
                            <span class="inline-value"><?php echo htmlspecialchars($agreement['remedial_year'] ?? ''); ?></span>
                        </div>

                        <!-- 11. Cost Estimate -->
                        <hr style="border-top: 2px solid #000; margin: 30px 0;">
                        <h4 data-en="11. Estimate Cost (Current Academic Year)" data-am="11. የተገመተ ወጪ (የአሁኑ የትምህርት ዘመን)">11. Estimate Cost (Current Academic Year)</h4>
                        <table class="cost-table">
                            <tr style="background: #f0f0f0;">
                                <th data-en="Service Type" data-am="የአገልግሎት ዓይነት">Service Type</th>
                                <th data-en="Amount (Birr)" data-am="መጠን (ብር)">Amount (Birr)</th>
                            </tr>
                            <tr>
                                <td data-en="15% Tuition Fee" data-am="15% የትምህርት ክፍያ">15% Tuition Fee</td>
                                <td><strong><?php echo number_format((float)$agreement['tuition_fee'], 2); ?></strong></td>
                            </tr>
                            <tr>
                                <td data-en="Food Expense" data-am="የምግብ ወጪ">Food Expense</td>
                                <td><strong><?php echo number_format((float)$agreement['food_expense'], 2); ?></strong></td>
                            </tr>
                            <tr>
                                <td data-en="Bed Expense" data-am="የመኝታ ወጪ">Bed Expense</td>
                                <td><strong><?php echo number_format((float)$agreement['bed_expense'], 2); ?></strong></td>
                            </tr>
                            <tr>
                                <td data-en="Medication Expense" data-am="የህክምና ወጪ">Medication Expense</td>
                                <td><strong><?php echo number_format((float)$agreement['medication_expense'], 2); ?></strong></td>
                            </tr>
                            <tr style="background: #e9ecef;">
                                <td style="text-align: right;"><strong data-en="TOTAL ESTIMATED COST" data-am="ጠቅላላ የተገመተ ወጪ">TOTAL ESTIMATED COST</strong></td>
                                <td><strong style="font-size: 1.2em;"><?php echo number_format((float)$agreement['tuition_fee'] + (float)$agreement['food_expense'] + (float)$agreement['bed_expense'] + (float)$agreement['medication_expense'], 2); ?> Birr</strong></td>
                            </tr>
                        </table>

                        <!-- 12. Agreement Text -->
                        <div style="margin-top: 30px; background: #fafafa; padding: 20px; border: 1px solid #ddd;">
                            <p data-en="I, <?php echo htmlspecialchars($std_info['first_name'] . ' ' . $std_info['last_name']); ?>, in accordance with this contractual agreement and the higher education Proclamation No 351/1995 and the higher education cost sharing Regulation 154/2008 of the council of ministers, agree to pay the above cost after graduation."
                                data-am="እኔ, <?php echo htmlspecialchars($std_info['first_name'] . ' ' . $std_info['last_name']); ?>, በዚህ የውል ስምምነት እና በከፍተኛ ትምህርት አዋጅ ቁጥር 351/1995 እንዲሁም በሚኒስትሮች ምክር ቤት የወጪ መጋራት ደንብ ቁጥር 154/2008 መሰረት ከምረቃ በኋላ ከላይ የተጠቀሰውን ወጪ ለመክፈል እስማማለሁ።">
                                I, <strong><?php echo htmlspecialchars($std_info['first_name'] . ' ' . $std_info['last_name']); ?></strong>,
                                in accordance with this contractual agreement and the higher education Proclamation No 351/1995
                                and the higher education cost sharing Regulation 154/2008 of the council of ministers,
                                agree to pay the above cost <strong>after graduation</strong>.
                            </p>
                            <?php if (!empty($agreement['payment_mode'])): ?>
                                <p style="margin-top:10px;">
                                    <strong data-en="Payment Mode:" data-am="የመክፈያ ዘዴ:">Payment Mode:</strong>
                                    <span data-en="<?php echo htmlspecialchars($agreement['payment_mode']); ?>"
                                        data-am="<?php echo ($agreement['payment_mode'] == 'Income') ? 'ከገቢ ተቀናሽ' : htmlspecialchars($agreement['payment_mode']); ?>">
                                        <?php echo htmlspecialchars($agreement['payment_mode']); ?>
                                    </span>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- SIGNATURES SECTION -->
                        <div class="signature-section">
                            <h3 style="text-align:center; margin-bottom:10px;" data-en="Signatures" data-am="ፊርማዎች">Signatures</h3>

                            <div class="signature-grid">
                                <!-- Student Signature -->
                                <div class="signature-box">
                                    <span class="sig-label" data-en="Beneficiary's Signature" data-am="የተጠቃሚው ፊርማ">Beneficiary's Signature</span>
                                    <div class="sig-value">
                                        <?php echo htmlspecialchars($agreement['signature_student'] ?? ''); ?>
                                    </div>
                                    <div class="sig-status sig-approved">
                                        <i class="fas fa-check"></i> <span data-en="Signed" data-am="ተፈርሟል">Signed</span>
                                    </div>
                                    <div style="margin-top:5px; font-size:0.85em; color:#555;">
                                        <span data-en="Date:" data-am="ቀን:">Date:</span> <?php echo htmlspecialchars($agreement['agreement_date'] ?? ''); ?>
                                    </div>
                                </div>

                                <!-- Department Head Signature -->
                                <div class="signature-box">
                                    <span class="sig-label" data-en="Department Head Signature" data-am="የዲፓርትመንት ኃላፊ ፊርማ">Department Head Signature</span>
                                    <div class="sig-value">
                                        <?php 
                                        $dept_sig = $agreement['signature_dept_head'] ?? '';
                                        if (!empty($dept_sig)) {
                                            // Show signer name
                                            $dh_name = trim(($agreement['dept_head_fname'] ?? '') . ' ' . ($agreement['dept_head_mname'] ?? ''));
                                            if (!empty($dh_name)) {
                                                echo '<p style="font-size:0.85em; color:#333; margin-bottom:5px; font-weight:600;">' . htmlspecialchars($dh_name) . '</p>';
                                            }
                                            if (strpos($dept_sig, 'data:image/') === 0) {
                                                echo '<img src="' . $dept_sig . '" alt="Department Head Signature" style="max-height:60px; max-width:100%;">';
                                            } else if (strpos($dept_sig, '/') === false && strpos($dept_sig, '\\') === false) {
                                                echo '<img src="../../uploads/signatures/' . htmlspecialchars($dept_sig) . '" alt="Department Head Signature" style="max-height:60px; max-width:100%;">';
                                            } else {
                                                echo '<img src="' . htmlspecialchars($dept_sig) . '" alt="Department Head Signature" style="max-height:60px; max-width:100%;">';
                                            }
                                        }
                                        ?>
                                    </div>
                                    <?php if (!empty($agreement['signature_dept_head'])): ?>
                                        <div class="sig-status sig-approved">
                                            <i class="fas fa-check-double"></i> <span data-en="Approved" data-am="ጸድቋል">Approved</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Cost Sharing Professional Signature -->
                                <div class="signature-box">
                                    <span class="sig-label" data-en="Cost Sharing Pro Signature" data-am="የወጪ መጋራት ባለሙያ ፊርማ">Cost Sharing Pro Signature</span>
                                    <div class="sig-value">
                                        <?php 
                                        $cost_sig = $agreement['signature_cost_pro'] ?? '';
                                        if (!empty($cost_sig)) {
                                            // Show signer name
                                            $cp_name = trim(($agreement['cost_pro_fname'] ?? '') . ' ' . ($agreement['cost_pro_mname'] ?? ''));
                                            if (!empty($cp_name)) {
                                                echo '<p style="font-size:0.85em; color:#333; margin-bottom:5px; font-weight:600;">' . htmlspecialchars($cp_name) . '</p>';
                                            }
                                            if (strpos($cost_sig, 'data:image/') === 0) {
                                                echo '<img src="' . $cost_sig . '" alt="Cost Sharing Pro Signature" style="max-height:60px; max-width:100%;">';
                                            } else if (preg_match('/\.(png|jpg|jpeg|gif)$/i', $cost_sig)) {
                                                if (strpos($cost_sig, '/') === false && strpos($cost_sig, '\\') === false) {
                                                    echo '<img src="../../uploads/signatures/' . htmlspecialchars($cost_sig) . '" alt="Cost Sharing Pro Signature" style="max-height:60px; max-width:100%;">';
                                                } else {
                                                    echo '<img src="' . htmlspecialchars($cost_sig) . '" alt="Cost Sharing Pro Signature" style="max-height:60px; max-width:100%;">';
                                                }
                                            } else {
                                                echo htmlspecialchars($cost_sig);
                                            }
                                        }
                                        ?>
                                    </div>
                                    <?php if (!empty($agreement['signature_cost_pro'])): ?>
                                        <div class="sig-status sig-approved">
                                            <i class="fas fa-check-double"></i> <span data-en="Approved" data-am="ጸድቋል">Approved</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>
