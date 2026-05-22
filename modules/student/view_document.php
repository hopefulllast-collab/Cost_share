<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['student']);

$user_id = $_SESSION['user_id'];

// Fetch Student Info
$stmt = $pdo->prepare("SELECT s.student_id, s.batch, s.department_id, s.current_semester, u.first_name, u.middle_name, u.last_name, d.name as dept_name 
                       FROM students s 
                       JOIN users u ON s.user_id = u.id 
                       LEFT JOIN departments d ON s.department_id = d.id 
                       WHERE s.user_id = ?");
$stmt->execute([$user_id]);
$std_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Latest Request (any type)
$reqStmt = $pdo->prepare("SELECT * FROM official_transcript WHERE student_id = ? ORDER BY id DESC LIMIT 1");
$reqStmt->execute([$user_id]);
$request = $reqStmt->fetch(PDO::FETCH_ASSOC);

// Fetch Transcript Professional name (role = transcript_pro)
$transcript_pro_name = '';
$stmt_tp = $pdo->prepare("SELECT first_name, middle_name FROM users WHERE role = 'transcript_pro' LIMIT 1");
$stmt_tp->execute();
$tp = $stmt_tp->fetch(PDO::FETCH_ASSOC);
if ($tp) {
    $transcript_pro_name = $tp['first_name'] . ' ' . $tp['middle_name'];
}

// Fetch Cost Sharing Professional name (role = cost_sharing_pro)
$cost_sharing_pro_name = '';
$stmt_csp = $pdo->prepare("SELECT first_name, middle_name FROM users WHERE role = 'cost_sharing_pro' LIMIT 1");
$stmt_csp->execute();
$csp = $stmt_csp->fetch(PDO::FETCH_ASSOC);
if ($csp) {
    $cost_sharing_pro_name = $csp['first_name'] . ' ' . $csp['middle_name'];
}

// Fetch Registrar name (role = registrar)
$registrar_name = '';
$stmt_reg = $pdo->prepare("SELECT first_name, middle_name FROM users WHERE role = 'registrar' LIMIT 1");
$stmt_reg->execute();
$reg = $stmt_reg->fetch(PDO::FETCH_ASSOC);
if ($reg) {
    $registrar_name = $reg['first_name'] . ' ' . $reg['middle_name'];
}

// Fetch Academic VP name
$academic_vp_name = '';
$stmt_vp = $pdo->prepare("SELECT first_name, middle_name FROM users WHERE role = 'academic_vp' LIMIT 1");
$stmt_vp->execute();
$vp = $stmt_vp->fetch(PDO::FETCH_ASSOC);
if ($vp) {
    $academic_vp_name = $vp['first_name'] . ' ' . $vp['middle_name'];
}

// Determine if this is a Transfer-Out request
$is_transfer_out = ($request && $request['request_type'] == 'Transfer-Out');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="View Document - DMU" data-am="ሰነድ ይመልከቱ - DMU">View Document - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .paper-doc {
            background: #fff;
            padding: 50px;
            max-width: 800px;
            margin: 20px auto;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            font-family: 'Times New Roman', serif;
            color: #000;
        }

        .doc-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .doc-header h2 {
            margin: 5px 0;
            text-transform: uppercase;
        }

        .doc-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .doc-subject {
            font-weight: bold;
            font-size: 1.2em;
            margin-bottom: 20px;
            text-decoration: underline;
        }

        .doc-body {
            line-height: 1.6;
            font-size: 1.1em;
            min-height: 200px;
        }

        .doc-footer {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }

        .stamp-box {
            border: 2px dashed #ccc;
            width: 150px;
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ccc;
            font-weight: bold;
            transform: rotate(-5deg);
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }

        .sig-block {
            text-align: center;
            width: 40%;
        }

        /* Unified Print Styles */
        @media print {
            .no-print {
                display: none !important;
            }

            body * {
                visibility: hidden;
            }

            .paper-doc,
            .paper-doc * {
                visibility: visible;
            }

            body,
            .layout-body,
            .main-content {
                max-width: 100% !important;
                width: 100% !important;
                margin: 0;
                padding: 0;
            }

            .paper-doc {
                position: absolute;
                left: 0;
                top: 0;
                box-shadow: none;
                border: none;
                width: 100%;
            }

            .paper-doc * {
                font-size: 10pt !important;
                line-height: 1.4 !important;
            }

            .paper-doc h3 {
                font-size: 12pt !important;
            }

            .paper-doc img {
                height: 40px !important;
            }

            .paper-doc div[style*="height:120px"] {
                height: 80px !important;
                padding-top: 20px !important;
            }

            .doc-body {
                border: none !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box;
                margin: 0;
            }

            @page {
                size: landscape;
                margin: 5mm;
            }
        }

        /* Generated Doc Styles */
        .generated-doc .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
        }

        .generated-doc h2 {
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>

            <div class="main-content">
                <div class="top-bar no-print">
                    <h2 data-en="View Requested Document" data-am="የተጠየቀ ሰነድ ይመልከቱ">View Requested Document</h2>
                    <?php if ($request && ($request['status'] == 'Delivered' || $request['status'] == 'Approved')): ?>
                        <button style="color:#ffffff; background-color:#000000;" class="btn-primary"
                            onclick="window.print()" data-en="Print" data-am="አትም"><i class="fas fa-print"></i> <span
                                data-en="Print Document" data-am="ሰነድ አትም">Print
                                Document</span></button>
                    <?php endif; ?>
                </div>

                <?php if ($request): ?>
                    <div class="paper-doc">

                        <!-- If Delivered (Official Document View) -->
                        <!-- If Delivered (Official Document View) -->
                        <?php if ($request['status'] == 'Delivered'): ?>

                            <?php if ($request['request_type'] == 'Graduation'):
                                // Fetch detailed costs
                                $cstmt = $pdo->prepare("SELECT SUM(tuition_fee) as t_fee, SUM(food_expense) as f_fee, SUM(bed_expense) as b_fee, SUM(medication_expense) as m_fee FROM cost_sharing_agreements WHERE student_id = ?");
                                $cstmt->execute([$request['student_id']]);
                                $costs = $cstmt->fetch(PDO::FETCH_ASSOC);
                                $t_fee = $costs['t_fee'] ?? 0;
                                $f_fee = $costs['f_fee'] ?? 0;
                                $b_fee = $costs['b_fee'] ?? 0;
                                $m_fee = $costs['m_fee'] ?? 0;
                                $total_fee = $t_fee + $f_fee + $b_fee + $m_fee;

                                // Fetch sex and college
                                $sstmt = $pdo->prepare("SELECT s.sex, d.college FROM students s LEFT JOIN departments d ON s.department_id = d.id WHERE s.user_id = ?");
                                $sstmt->execute([$request['student_id']]);
                                $xtra = $sstmt->fetch(PDO::FETCH_ASSOC);
                                $sex = $xtra['sex'] ?? '';
                                $college = $xtra['college'] ?? '';
                                $sex_am = ($sex == 'Male' || $sex == 'M') ? 'ወንድ' : (($sex == 'Female' || $sex == 'F') ? 'ሴት' : $sex);
                                ?>

                                <div class="alert alert-info no-print"
                                    style="margin-bottom: 20px; background-color: #e3f2fd; color: #0d47a1; border: left 4px solid #1976d2; padding: 15px; border-radius: 4px; font-weight: bold; border-left: 4px solid #1976d2;">
                                    <i class="fas fa-info-circle"></i> <span
                                        data-en="Notice: Please come in person during working hours to collect your graduation document."
                                        data-am="ማሳሰቢያ፡ ይህንን ሰነድ በስራ ሰአት በአካል በመቅረብ ይውሰዱ።">ማሳሰቢያ፡ ይህንን ሰነድ በስራ ሰአት በአካል በመቅረብ
                                        ይውሰዱ።</span>
                                </div>

                                <div class="doc-body"
                                    style="font-family: Arial, sans-serif; padding:20px; border:1px solid #ccc; background:#fff;">
                                    <div
                                        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                        <h3 style="margin:0; flex:1;">ደብረ ማርቆስ ዩኒቨርሲቲ</h3>
                                        <img src="../../assets/images/dmulogo.png" style="height:60px;">
                                        <h3 style="margin:0; flex:1; text-align:right;">Debre Markos University</h3>
                                    </div>
                                    <div style="border-bottom:2px solid #000; margin-bottom:15px;"></div>

                                    <h3 style="margin:0; text-align:center; text-decoration:underline; margin-bottom:10px;">የወጪ መጋራት
                                        ተጠቃሚ መረጃ</h3>

                                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                                        <div style="flex-shrink:0;">
                                            <div
                                                style="border:1px solid #777; width:100px; min-width:100px; height:100px; text-align:center; padding-top:35px; font-weight:bold; color:#777; background:#fafafa;">
                                                Photo</div>
                                        </div>
                                        <div style="width:45%; line-height: 1.7; font-size:1em;">
                                            <h4 style="margin:0 0 5px 0;text-align:center;">የወጪ መጠን</h4>
                                            <div style="display:flex; justify-content:space-between;"><span>ለምግብ</span>
                                                <span><?php echo number_format($f_fee, 0); ?></span>
                                            </div>
                                            <div style="display:flex; justify-content:space-between;"><span>ለዶርም</span>
                                                <span><?php echo number_format($b_fee, 0); ?></span>
                                            </div>
                                            <div style="display:flex; justify-content:space-between;"><span>ለትምህርት</span>
                                                <span><?php echo number_format($t_fee, 0); ?></span>
                                            </div>
                                            <div style="display:flex; justify-content:space-between;"><span>ለክሊኒክ</span>
                                                <span><?php echo number_format($m_fee, 0); ?></span>
                                            </div>
                                            <hr style="border:1px dashed #000; margin:8px 0;">
                                            <div style="display:flex; justify-content:space-between; font-weight:bold;"><span>ጠቅላላ
                                                    ወጪ</span>
                                                <span><?php echo number_format($total_fee, 0); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="line-height: 1.7; font-size:1em; text-align: left;">
                                        1. የተማሪው የመታወቂያ ቁጥር: <strong><?php echo $std_info['student_id']; ?></strong><br>
                                        2. የተጠቃሚው ስም ከነአያት:
                                        <strong><?php echo $std_info['first_name'] . ' ' . $std_info['middle_name'] . ' ' . $std_info['last_name']; ?></strong><br>
                                        3. ተቋም/ኢንስቲትዩት/ኮሌጅ/ት/ቤት: <strong><?php echo $college; ?></strong><br>
                                        4. የትምህርት ክፍል: <strong><?php echo $std_info['dept_name']; ?></strong><br>
                                        5. ጾታ: <strong><?php echo $sex_am; ?></strong><br>
                                        6. የወጪ መጋራት የሚከፈልበት ሁኔታ: [&#10003;] ከምረቃ በኋላ ከገቢው ተቀናሽ ሆኖ የሚከፈል [ &nbsp; ] በሙያው አገልግሎት
                                        በመስጠት<br>
                                        7. ተጠቃሚው በዩኒቨርሲቲው የቆየበት /ችበት/ ዓመት:
                                        <strong><?php echo $std_info['batch'] ?? ''; ?></strong><br>
                                        8. ከተጠቃሚው ተቀናሽ ሆኖ የሚከፈል የወጪ መጋራት ክፍያ ብር:
                                        <strong><?php echo number_format($total_fee, 0); ?></strong> /ብቻ<br>
                                        9. በተጠቃሚው በቅድሚያ የተከፈለ በአካዝ ብር የለም በፊደል የለም<br>
                                        10. በቅድሚያ በተጠቃሚው የተከፈለበት የደረሰኝ ቁጥር የለም<br>
                                    </div>

                                    <div style="display:flex; justify-content:space-between; margin-top:20px; font-size:1em;">
                                        <div style="text-align:center;">
                                            <strong>የወጪ መጋራት ባለሙያ</strong><br><br>
                                            ስም፡- <strong><?php echo htmlspecialchars($cost_sharing_pro_name); ?></strong><br><br>
                                            ፊርማ፡-
                                            <?php if (!empty($request['transcript_signature'])): ?>
                                                <?php $t_src = (strpos($request['transcript_signature'], 'data:image/') === 0) ? $request['transcript_signature'] : "../../uploads/signatures/" . $request['transcript_signature']; ?>
                                                <img src="<?php echo $t_src; ?>" style="height:45px; vertical-align:middle;">
                                            <?php else: ?>
                                                __________________________
                                            <?php endif; ?>
                                            <br><br>
                                            ቀን፡- <?php echo date('d/m/Y'); ?>
                                        </div>
                                        <div style="text-align:center;">
                                            <strong>ያረጋገጠው ፊርማ (Registrar)</strong><br><br>
                                            ስም፡- <strong><?php echo htmlspecialchars($registrar_name); ?></strong><br><br>
                                            ፊርማ፡-
                                            <?php if (!empty($request['registrar_signature'])): ?>
                                                <?php $r_src = (strpos($request['registrar_signature'], 'data:image/') === 0) ? $request['registrar_signature'] : "../../uploads/signatures/" . $request['registrar_signature']; ?>
                                                <img src="<?php echo $r_src; ?>" style="height:45px; vertical-align:middle;">
                                            <?php else: ?>
                                                __________________________
                                            <?php endif; ?>
                                            <br><br>
                                            ቀን፡- <?php echo date('d/m/Y'); ?>
                                        </div>
                                    </div>
                                </div>

                            <?php elseif ($request['request_type'] == 'Original'): ?>
                                <div class="alert alert-info no-print"
                                    style="margin-bottom: 20px; background-color: #e3f2fd; color: #0d47a1; border: left 4px solid #1976d2; padding: 15px; border-radius: 4px; font-weight: bold; border-left: 4px solid #1976d2;">
                                    <i class="fas fa-info-circle"></i> <span
                                        data-en="Notice: Please come in person during working hours to collect your document."
                                        data-am="ማሳሰቢያ፡ ይህንን ሰነድ በስራ ሰአት በአካል በመቅረብ ይውሰዱ።">ማሳሰቢያ፡ ይህንን ሰነድ በስራ ሰአት በአካል በመቅረብ
                                        ይውሰዱ።</span>
                                </div>
                                <div class="doc-body"
                                    style="text-align:center; padding:40px; border:2px solid #000; background:#fff; font-family:Arial;">
                                    <img src="../../assets/img/logo.png" alt="Logo" style="height:90px;">
                                    <h2>Debre Markos University / ደብረ ማርቆስ ዩኒቨርሲቲ</h2>
                                    <hr>
                                    <h3 style="margin-top:30px; color:#2c3e50;">Original Document Request Processed</h3>
                                    <h3 style="color:#2c3e50;">የኦሪጅናል ሰነድ ጥያቄዎ ተስተናግዷል</h3>
                                    <p style="font-size:1.2em; line-height:1.6; margin-top:30px;">
                                        የተከበሩ
                                        <strong><?php echo $std_info['first_name'] . ' ' . $std_info['middle_name'] . ' ' . $std_info['last_name']; ?></strong>፣
                                        ጥያቄዎ ተቀባይነት አግኝቶ ተዘጋጅቷል!<br>
                                        እባክዎ <strong>በአካል በመቅረብ</strong> ኦሪጅናል ሰነድዎን ይውሰዱ።<br><br>
                                        Dear
                                        <strong><?php echo $std_info['first_name'] . ' ' . $std_info['middle_name'] . ' ' . $std_info['last_name']; ?></strong>,
                                        your request has been fully processed.<br>
                                        Please <strong>appear in person</strong> to collect your original document.
                                    </p>

                                    <div class="signatures" style="margin-top:60px;">
                                        <div class="sig-block">
                                            <p><strong>Transcript Professional:</strong></p>
                                            <?php if (!empty($request['transcript_signature'])): ?>
                                                <?php $t_src = (strpos($request['transcript_signature'], 'data:image/') === 0) ? $request['transcript_signature'] : "../../uploads/signatures/" . $request['transcript_signature']; ?>
                                                <img src="<?php echo $t_src; ?>"
                                                    style="max-height: 80px; display: block; margin: 0 auto;">
                                            <?php else: ?>
                                                <p>__________________________</p>
                                            <?php endif; ?>
                                            <p><strong><?php echo htmlspecialchars($transcript_pro_name); ?></strong></p>
                                        </div>
                                        <div class="sig-block">
                                            <p><strong>Registrar Head:</strong></p>
                                            <?php if (!empty($request['registrar_signature'])): ?>
                                                <?php $r_src = (strpos($request['registrar_signature'], 'data:image/') === 0) ? $request['registrar_signature'] : "../../uploads/signatures/" . $request['registrar_signature']; ?>
                                                <img src="<?php echo $r_src; ?>"
                                                    style="max-height: 80px; display: block; margin: 0 auto;">
                                            <?php else: ?>
                                                <p>__________________________</p>
                                            <?php endif; ?>
                                            <p><strong><?php echo htmlspecialchars($registrar_name); ?></strong></p>
                                        </div>
                                    </div>
                                </div>

                            <?php elseif ($request['request_type'] == 'Transfer-Out'):
                                $cstmt = $pdo->prepare("SELECT SUM(tuition_fee) as t_fee, SUM(food_expense) as f_fee, SUM(bed_expense) as b_fee, SUM(medication_expense) as m_fee FROM cost_sharing_agreements WHERE student_id = ?");
                                $cstmt->execute([$request['student_id']]);
                                $costs = $cstmt->fetch(PDO::FETCH_ASSOC);
                                $t_fee = number_format((float) $costs['t_fee'], 2);
                                $f_fee = number_format((float) $costs['f_fee'], 2);
                                $b_fee = number_format((float) $costs['b_fee'], 2);
                                $m_fee = number_format((float) $costs['m_fee'], 2);
                                $tot_fee = number_format((float) $request['cost_share_amount'], 2);

                                // Fetch courses up to current batch/semester
                                $stmt_courses = $pdo->prepare("SELECT batch, semester, course_name, credit_hour FROM courses WHERE department_id = ? AND (batch < ? OR (batch = ? AND semester <= ?)) ORDER BY batch ASC, semester ASC");
                                $stmt_courses->execute([$std_info['department_id'], $std_info['batch'], $std_info['batch'], $std_info['current_semester']]);
                                $courses = $stmt_courses->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <div class="alert alert-info no-print"
                                    style="margin-bottom: 20px; background-color: #e3f2fd; color: #0d47a1; border: left 4px solid #1976d2; padding: 15px; border-radius: 4px; font-weight: bold; border-left: 4px solid #1976d2;">
                                    <i class="fas fa-info-circle"></i> <span
                                        data-en="Notice: Please wait until all processing is finalized to print or view the complete official forms."
                                        data-am="ማሳሰቢያ፡ የተጠናቀቁ ይፋዊ ቅጾችን ለማየት ወይም ለማተም እባክዎ ሁሉም ሂደት እስኪጠናቀቅ ይጠብቁ።">ማሳሰቢያ፡ የተጠናቀቁ ይፋዊ
                                        ቅጾችን ለማየት ወይም ለማተም እባክዎ ሁሉም ሂደት እስኪጠናቀቅ ይጠብቁ።</span>
                                </div>

                                <!-- Part 1: Cost Share Letter -->
                                <div class="doc-body"
                                    style="font-family: Arial, sans-serif; padding:20px; text-align: left; border:1px solid #ccc; background:#fff;">
                                    <div
                                        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                        <h3 style="margin:0; flex:1;">ደብረ ማርቆስ ዩኒቨርሲቲ</h3>
                                        <img src="../../assets/images/dmulogo.png" style="height:60px;">
                                        <h3 style="margin:0; flex:1; text-align:right;">Debre Markos University</h3>
                                    </div>
                                    <div style="border-bottom:2px solid #000; margin-bottom:15px;"></div>
                                    <p style="text-align:right;">ቀን: <?php echo date('d/m/Y'); ?></p>
                                    <p style="font-weight:bold; font-size:16px; text-decoration:underline;text-align:center;">
                                        ለሚመለከተው ዩኒቨርሲቲ</p>
                                    <p style="margin-top:20px; line-height: 1.6;">
                                        ይህ ደብዳቤ ተማሪ
                                        <strong><?php echo htmlspecialchars($std_info['first_name'] . ' ' . $std_info['middle_name'] . ' ' . $std_info['last_name']); ?></strong>
                                        (መታወቂያ ቁጥር: <strong><?php echo htmlspecialchars($std_info['student_id']); ?></strong>)፣ በ
                                        <strong><?php echo htmlspecialchars($std_info['dept_name']); ?></strong> የትምህርት ክፍል ትምህርታቸውን
                                        ሲከታተሉ የነበሩ ሲሆን፣
                                        ወደ ሌላ ተቋም ለሚያደርጉት የዝውውር ሂደት፣ በዩኒቨርሲቲያችን መረጃ መሰረት ከዚህ በታች የተዘረዘሩት የወጪ መጋራት ዕዳዎች እንዳሉባቸው
                                        እናሳውቃለን።
                                    </p>
                                    <table
                                        style="width:100%; border-collapse:collapse; margin-top:20px; margin-bottom:30px; text-align:center;"
                                        border="1" cellpadding="8">
                                        <tr style="background-color:#f4f4f4;">
                                            <th>የትምህርት ወጪ</th>
                                            <th>የምግብ ወጪ</th>
                                            <th>የአልጋ ወጪ</th>
                                            <th>የህክምና ወጪ</th>
                                            <th>ጠቅላላ የዕዳ ድምር</th>
                                        </tr>
                                        <tr>
                                            <td><?php echo $t_fee; ?> ብር</td>
                                            <td><?php echo $f_fee; ?> ብር</td>
                                            <td><?php echo $b_fee; ?> ብር</td>
                                            <td><?php echo $m_fee; ?> ብር</td>
                                            <td><strong><?php echo $tot_fee; ?> ብር</strong></td>
                                        </tr>
                                    </table>
                                    <p style="line-height:1.6;">እባክዎ እነዚህን የዕዳ መጠኖች በስርዓትዎ ላይ በመመዝገብ ዝውውሩን እንዲያጠናቅቁ እንጠይቃለን። ለትብብርዎ
                                        እናመሰግናለን።</p>

                                    <div style="display:flex; justify-content:space-between; margin-top:40px; font-size:1em;">
                                        <div style="text-align:center;">
                                            <strong>የትራንስክሪፕት ባለሙያ</strong><br><br>
                                            ስም፡- <strong><?php echo htmlspecialchars($transcript_pro_name); ?></strong><br><br>
                                            ፊርማ፡-
                                            <?php if (!empty($request['transcript_signature'])): ?>
                                                <?php $t_src = (strpos($request['transcript_signature'], 'data:image/') === 0) ? $request['transcript_signature'] : "../../uploads/signatures/" . $request['transcript_signature']; ?>
                                                <img src="<?php echo $t_src; ?>" style="height:45px; vertical-align:middle;">
                                            <?php else: ?>
                                                __________________________
                                            <?php endif; ?>
                                            <br><br>
                                            ቀን፡- <?php echo date('d/m/Y'); ?>
                                        </div>
                                        <div style="text-align:center;">
                                            <strong>ያረጋገጠው ፊርማ (Registrar)</strong><br><br>
                                            ስም፡- <strong><?php echo htmlspecialchars($registrar_name); ?></strong><br><br>
                                            ፊርማ፡-
                                            <?php if (!empty($request['registrar_signature'])): ?>
                                                <?php $r_src = (strpos($request['registrar_signature'], 'data:image/') === 0) ? $request['registrar_signature'] : "../../uploads/signatures/" . $request['registrar_signature']; ?>
                                                <img src="<?php echo $r_src; ?>" style="height:45px; vertical-align:middle;">
                                            <?php else: ?>
                                                __________________________
                                            <?php endif; ?>
                                            <br><br>
                                            ቀን፡- <?php echo date('d/m/Y'); ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Part 2: Course Transcript -->
                                <div class="doc-body"
                                    style="font-family: Arial, sans-serif; padding:20px; text-align: left; border:1px solid #ccc; background:#fff; margin-top: 30px;">
                                    <div style="text-align:center; margin-bottom: 20px;">
                                        <img src="../../assets/images/dmulogo.png" alt="Logo" style="height:60px;"><br>
                                        <h3 style="margin-top:10px;text-decoration: underline;">የተማሪ የትምህርት ኮርሶች ማረጋገጫ / Course
                                            Transcript</h3>
                                    </div>

                                    <p><strong>Student Name:</strong>
                                        <?php echo htmlspecialchars($std_info['first_name'] . ' ' . $std_info['middle_name'] . ' ' . $std_info['last_name']); ?>
                                    </p>
                                    <p><strong>ID Number:</strong> <?php echo htmlspecialchars($std_info['student_id']); ?></p>
                                    <p><strong>Department:</strong> <?php echo htmlspecialchars($std_info['dept_name']); ?></p>
                                    <br>

                                    <?php if (empty($courses)): ?>
                                        <p style="color:red;">No courses found for this department.</p>
                                    <?php else: ?>
                                        <?php
                                        $courses_by_term = [];
                                        foreach ($courses as $c) {
                                            $term = 'Year: ' . $c['batch'] . ' Semester: ' . $c['semester'];
                                            $courses_by_term[$term][] = $c;
                                        }

                                        $cumulative_credits = 0;
                                        foreach ($courses_by_term as $term => $term_courses):
                                            $term_credits = 0;
                                            ?>
                                            <h4 style="margin:20px 0 5px 0; text-align:center;"><?php echo $term; ?></h4>
                                            <table
                                                style="width:100%; border-collapse:collapse; margin-bottom:15px; text-align:left; font-size:14px;"
                                                border="1" cellpadding="8">
                                                <tr style="background-color:#f4f4f4;">
                                                    <th>Course Title</th>
                                                    <th>Course Code</th>
                                                    <th>Credit</th>
                                                    <th>Number Grade</th>
                                                    <th>Letter Grade</th>
                                                    <th>Grade Point</th>
                                                </tr>
                                                <?php
                                                foreach ($term_courses as $c):
                                                    $term_credits += $c['credit_hour'];
                                                    $cumulative_credits += $c['credit_hour'];
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($c['course_name']); ?></td>
                                                        <td></td>
                                                        <td><?php echo $c['credit_hour']; ?></td>
                                                        <td></td>
                                                        <td></td>
                                                        <td></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </table>

                                            <table
                                                style="width:50%; border-collapse:collapse; margin-bottom:30px; text-align:right; font-size:14px; margin-left:auto;"
                                                border="1" cellpadding="8">
                                                <tr>
                                                    <th style="background-color:#f4f4f4; text-align:left;">Summary</th>
                                                    <th style="background-color:#f4f4f4;">Credit</th>
                                                    <th style="background-color:#f4f4f4;">GP</th>
                                                    <th style="background-color:#f4f4f4;">ANG</th>
                                                </tr>
                                                <tr>
                                                    <td style="text-align:left;">Previous Total</td>
                                                    <td></td>
                                                    <td></td>
                                                    <td></td>
                                                </tr>
                                                <tr>
                                                    <td style="text-align:left;">Semester Total</td>
                                                    <td><?php echo $term_credits; ?></td>
                                                    <td></td>
                                                    <td></td>
                                                </tr>
                                                <tr>
                                                    <td style="text-align:left;">Cumulative</td>
                                                    <td><?php echo $cumulative_credits; ?></td>
                                                    <td></td>
                                                    <td></td>
                                                </tr>
                                            </table>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <div style="display:flex; justify-content:space-between; margin-top:40px; font-size:1em;">
                                        <div style="text-align:center;">
                                            <strong>የትራንስክሪፕት ባለሙያ</strong><br><br>
                                            ስም፡- <strong><?php echo htmlspecialchars($transcript_pro_name); ?></strong><br><br>
                                            ፊርማ፡-
                                            <?php if (!empty($request['transcript_signature'])): ?>
                                                <?php $t_src = (strpos($request['transcript_signature'], 'data:image/') === 0) ? $request['transcript_signature'] : "../../uploads/signatures/" . $request['transcript_signature']; ?>
                                                <img src="<?php echo $t_src; ?>" style="height:45px; vertical-align:middle;">
                                            <?php else: ?>
                                                __________________________
                                            <?php endif; ?>
                                            <br><br>
                                            ቀን፡- <?php echo date('d/m/Y'); ?>
                                        </div>
                                        <div style="text-align:center;">
                                            <strong>ያረጋገጠው ፊርማ (Registrar)</strong><br><br>
                                            ስም፡- <strong><?php echo htmlspecialchars($registrar_name); ?></strong><br><br>
                                            ፊርማ፡-
                                            <?php if (!empty($request['registrar_signature'])): ?>
                                                <?php $r_src = (strpos($request['registrar_signature'], 'data:image/') === 0) ? $request['registrar_signature'] : "../../uploads/signatures/" . $request['registrar_signature']; ?>
                                                <img src="<?php echo $r_src; ?>" style="height:45px; vertical-align:middle;">
                                            <?php else: ?>
                                                __________________________
                                            <?php endif; ?>
                                            <br><br>
                                            ቀን፡- <?php echo date('d/m/Y'); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info no-print"
                                    style="margin-bottom: 20px; background-color: #e3f2fd; color: #0d47a1; border: left 4px solid #1976d2; padding: 15px; border-radius: 4px; font-weight: bold; border-left: 4px solid #1976d2;">
                                    <i class="fas fa-info-circle"></i> <span
                                        data-en="Notice: Please come in person during working hours to collect your document."
                                        data-am="ማሳሰቢያ፡ ይህንን ሰነድ በስራ ሰአት በአካል በመቅረብ ይውሰዱ።">ማሳሰቢያ፡ ይህንን ሰነድ በስራ ሰአት በአካል በመቅረብ
                                        ይውሰዱ።</span>
                                </div>
                                <!-- Fallback Generic English Format -->
                                <div class="doc-header">
                                    <h2 data-en="DEBRE MARKOS UNIVERSITY" data-am="ደብረ ማርቆስ ዩኒቨርሲቲ">DEBRE MARKOS UNIVERSITY</h2>
                                    <h3 data-en="OFFICE OF THE REGISTRAR" data-am="ሬጅስትራር ጽህፈት ቤት">OFFICE OF THE REGISTRAR</h3>
                                    <h4 data-en="Official Document" data-am="ኦፊሴላዊ ሰነድ">Official Document</h4>
                                </div>

                                <div class="doc-body" style="text-align: justify;">
                                    <p><strong data-en="Date:" data-am="ቀን:">Date:</strong> <?php echo date('d/m/Y'); ?></p>

                                    <p><strong data-en="To: Whom It May Concern" data-am="ለ: ጉዳዩ ለሚመለከተው ሁሉ">To: Whom It May
                                            Concern</strong></p>

                                    <p data-en="This is to certify that" data-am="ይህ የምስክር ወረቀት የሚያረጋግጠው">This is to certify that
                                        <strong><?php echo strtoupper($std_info['first_name'] . ' ' . $std_info['middle_name'] . ' ' . $std_info['last_name']); ?></strong>
                                        (ID: <strong><?php echo $std_info['student_id']; ?></strong>) <span
                                            data-en="was a bona fide student of Debre Markos University in the Department of"
                                            data-am="በደብረ ማርቆስ ዩኒቨርሲቲ በትምህርት ክፍል ተማሪ ነበሩ">was a bona fide student of
                                            Debre Markos University
                                            in the Department of</span> <strong><?php echo $std_info['dept_name']; ?></strong>.
                                    </p>

                                    <p><span data-en="The student has requested:" data-am="ተማሪው የጠየቀው:">The student has
                                            requested:</span> <strong><?php echo $request['request_type']; ?></strong>.</p>

                                    <p data-en="We confirm that the student has cleared all cost sharing obligations / or has an agreement on file as per the university regulations."
                                        data-am="ተማሪው ሁሉንም የወጪ መጋራት ግዴታዎች ማጠናቀቁን / ወይም በዩኒቨርሲቲው ደንቦች መሠረት በፋይል ላይ ስምምነት እንዳለው እናረጋግጣለን።">
                                        We confirm that the student has cleared all cost sharing obligations / or has an agreement
                                        on
                                        file as per the university regulations.</p>

                                    <p data-en="This document is issued upon the request of the student for legal purposes."
                                        data-am="ይህ ሰነድ በተማሪው ጥያቄ መሰረት ለህጋዊ ዓላማዎች ተሰጥቷል።">
                                        This document is issued upon the request of the student for legal purposes.</p>
                                </div>

                                <div class="signatures">
                                    <!-- Transcript Professional Signature -->
                                    <div class="sig-block">
                                        <p><strong data-en="Transcript Professional:" data-am="ትራንስክሪፕት ባለሙያ:">Transcript
                                                Professional:</strong></p>
                                        <?php if (!empty($request['transcript_signature'])): ?>
                                            <?php $t_src = (strpos($request['transcript_signature'], 'data:image/') === 0) ? $request['transcript_signature'] : "../../uploads/signatures/" . $request['transcript_signature']; ?>
                                            <img src="<?php echo $t_src; ?>" style="max-height: 80px; display: block; margin: 0 auto;"
                                                alt="Transcript Signature">
                                        <?php else: ?>
                                            <p>__________________________</p>
                                        <?php endif; ?>
                                        <p><strong><?php echo htmlspecialchars($transcript_pro_name); ?></strong></p>
                                        <p data-en="Transcript Professional" data-am="ትራንስክሪፕት ባለሙያ">Transcript Professional</p>
                                    </div>

                                    <!-- Registrar Head Signature -->
                                    <div class="sig-block">
                                        <p><strong data-en="Registrar Head:" data-am="ሬጅስትራር ኃላፊ:">Registrar Head:</strong></p>
                                        <?php if (!empty($request['registrar_signature'])): ?>
                                            <?php $r_src = (strpos($request['registrar_signature'], 'data:image/') === 0) ? $request['registrar_signature'] : "../../uploads/signatures/" . $request['registrar_signature']; ?>
                                            <img src="<?php echo $r_src; ?>" style="max-height: 80px; display: block; margin: 0 auto;"
                                                alt="Registrar Signature">
                                        <?php else: ?>
                                            <p>__________________________</p>
                                        <?php endif; ?>
                                        <p><strong><?php echo htmlspecialchars($registrar_name); ?></strong></p>
                                        <p data-en="Official Seal & Signature" data-am="የቢሮ ማህተም እና ፊርማ">Official Seal & Signature
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <!-- Fallback / Request Status View -->
                            <div class="doc-header">
                                <h3 data-en="Debre Markos University" data-am="ደብረ ማርቆስ ዩኒቨርሲቲ">Debre Markos University</h3>
                                <?php if ($is_transfer_out): ?>
                                    <h4 data-en="Office of the Academic Vice President" data-am="የአካዳሚክ ም/ፕሬዝዳንት ጽህፈት ቤት">Office of
                                        the Academic Vice President</h4>
                                <?php else: ?>
                                    <h4 data-en="Office of the Registrar" data-am="ሬጅስትራር ጽህፈት ቤት">Office of the Registrar</h4>
                                <?php endif; ?>
                                <h5 data-en="Student Document Request Form" data-am="የተማሪ ሰነድ መጠየቂያ ቅጽ">Student Document Request
                                    Form</h5>
                            </div>

                            <div class="doc-meta">
                                <div>
                                    <strong data-en="Date:" data-am="ቀን:">Date:</strong>
                                    <?php echo date('F j, Y', strtotime($request['created_at'] ?? 'now')); ?><br>
                                    <strong data-en="Ref No:" data-am="ማጣቀሻ ቁጥር:">Ref No:</strong> DMU/REQ/
                                    <?php echo $request['id']; ?>
                                </div>
                                <div style="text-align: right;">
                                    <strong data-en="Status:" data-am="ሁኔታ:">Status:</strong>
                                    <span
                                        style="color: <?php echo $request['status'] == 'Delivered' ? 'green' : 'orange'; ?>; font-weight:bold;">
                                        <?php echo $request['status']; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="doc-info">
                                <p><strong data-en="Student Name:" data-am="የተማሪ ስም:">Student Name:</strong>
                                    <?php echo $std_info['first_name'] . ' ' . $std_info['middle_name'] . ' ' . $std_info['last_name']; ?>
                                </p>
                                <p><strong data-en="Student ID:" data-am="የተማሪ መለያ:">Student ID:</strong>
                                    <?php echo $std_info['student_id']; ?>
                                </p>
                                <p><strong data-en="Department:" data-am="ትምህርት ክፍል:">Department:</strong>
                                    <?php echo $std_info['dept_name']; ?>
                                </p>
                            </div>

                            <div class="doc-subject">
                                <span data-en="Subject: Request for" data-am="ርዕሰ ጉዳይ: የጠየቁት ሰነድ">Subject: Request for</span>
                                <?php echo $request['request_type']; ?>
                            </div>

                            <div class="doc-body">
                                <?php if ($is_transfer_out): ?>
                                    <p data-en="To: Academic Vice President," data-am="ለ: አካዳሚክ ም/ፕሬዝዳንት,">To: Academic Vice
                                        President,</p>
                                <?php else: ?>
                                    <p data-en="To: Distro/Registrar Head," data-am="ለ: ስርጭት/ሬጅስትራር ኃላፊ,">To: Distro/Registrar Head,
                                    </p>
                                <?php endif; ?>
                                <p data-en="I, the undersigned student, have requested the issuance of my"
                                    data-am="እኔ, ከዚህ በታች የፈረምኩት ተማሪ, የሚከተለው ሰነድ እንዲሰጠኝ ጠይቄአለሁ">I, the undersigned student, have
                                    requested the issuance of my</p> <strong>
                                    <?php echo $request['request_type']; ?>
                                </strong>.</p>

                                <p><em><span data-en="Current Status:" data-am="የአሁኑ ሁኔታ:">Current Status:</span>
                                        <?php echo $request['status']; ?></em></p>
                                <?php if ($is_transfer_out && $request['status'] == 'Pending'): ?>
                                    <p data-en="Your request is being reviewed by the Academic Vice President."
                                        data-am="ጥያቄዎ በአካዳሚክ ም/ፕሬዝዳንት እየታየ ነው።">Your request is being reviewed by the Academic Vice
                                        President.</p>
                                <?php elseif ($is_transfer_out && $request['status'] == 'Forwarded'): ?>
                                    <p data-en="Your request has been forwarded to the Registrar by the Academic Vice President."
                                        data-am="ጥያቄዎ በአካዳሚክ ም/ፕሬዝዳንት ወደ ሬጅስትራር ተላልፏል።">Your request has been forwarded to the
                                        Registrar by the Academic Vice President.</p>
                                <?php elseif ($request['status'] == 'Pending Transcript'): ?>
                                    <p data-en="Your request is being processed by the Official Transcript Professional."
                                        data-am="ጥያቄዎ በኦፊሴላዊው ትራንስክሪፕት ባለሙያ እየተስተናገደ ነው።">Your request is being processed by the
                                        Official Transcript Professional.</p>
                                <?php elseif ($request['status'] == 'Pending Registrar Signature'): ?>
                                    <p data-en="Your document has been generated and is awaiting final signature from the Registrar."
                                        data-am="ሰነድዎ ተዘጋጅቷል እና ከሬጅስትራር የመጨረሻ ፊርማ እየጠበቀ ነው።">Your document has been generated and is
                                        awaiting final signature from the Registrar.</p>
                                <?php elseif ($request['status'] == 'Rejected'): ?>
                                    <p data-en="Your request has been Rejected." data-am="ጥያቄዎ ውድቅ ተደርጓል።"
                                        style="color:#dc3545; font-weight:bold; font-size:1.2em;">Your request has been Rejected.
                                    </p>
                                    <div
                                        style="background:#f8d7da; border:2px solid #dc3545; padding:20px; border-radius:8px; margin-top:20px;">
                                        <h4 data-en="Rejection Reason:" data-am="ውድቅ የተደረገበት ምክንያት፡"
                                            style="color:#721c24; margin-top:0; font-size:1.2em;">Rejection Reason:</h4>
                                        <p style="color:red; font-size:1.4em; font-weight:bold; margin-bottom:0;">
                                            <?php echo htmlspecialchars($request['rejection_reason'] ?: ($academic_translations['No reason provided'] ?? 'No reason provided')); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="doc-footer">
                                <div>
                                    <p data-en="Sincerely," data-am="ከሰላምታ ጋር,">Sincerely,</p>
                                    <br>
                                    <strong>
                                        <?php echo $std_info['first_name']; ?>
                                    </strong><br>
                                    <em data-en="(Digital Signature)" data-am="(ዲጂታል ፊርማ)">(Digital Signature)</em>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>
                <?php else: ?>
                    <div class="card" style="text-align: center; padding: 50px;">
                        <h3 data-en="No Document Requests Found" data-am="ምንም የሰነድ ጥያቄዎች አልተገኙም">No Document Requests Found
                        </h3>
                        <p data-en="You haven't submitted any requests yet." data-am="እስካሁን ምንም ጥያቄ አላቀረቡም።">You haven't
                            submitted any requests yet.</p>
                        <a href="request_document.php" class="btn-primary" style="color:#ffffff; background-color:#000000;"
                            data-en="Request Document" data-am="ሰነድ ይጠይቁ">Request Document</a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>