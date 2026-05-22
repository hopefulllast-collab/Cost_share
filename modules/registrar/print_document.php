<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
// checkAuth(['registrar']); // Optional: Allow students to print too? User said Registrar verify.

if (!isset($_GET['id'])) {
    die("Invalid Request");
}

$req_id = $_GET['id'];

$stmt = $pdo->prepare("SELECT dr.*, u.first_name, u.middle_name, u.last_name, s.student_id as real_student_id, d.name as dept_name, d.college, s.batch, s.department_id as student_dept_id, s.current_semester 
                       FROM official_transcript dr 
                       JOIN students s ON dr.student_id = s.user_id 
                       JOIN users u ON s.user_id = u.id 
                       LEFT JOIN departments d ON s.department_id = d.id 
                       WHERE dr.id = ?");
$stmt->execute([$req_id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    die("Document not found.");
}

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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Document</title>
    <style>
        body {
            font-family: 'Times New Roman', serif;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 50px;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
        }

        .logo {
            max-width: 100px;
        }

        .content {
            font-size: 14pt;
            line-height: 1.6;
            text-align: justify;
        }

        .footer {
            margin-top: 100px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            text-align: center;
            width: 45%;
        }

        .signature-img {
            max-height: 80px;
            display: block;
            margin: 0 auto;
        }

        @media print {
            .no-print {
                display: none;
            }

            * {
                box-sizing: border-box;
            }

            body {
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 5px !important;
                font-size: 10pt !important;
            }

            .content {
                border: 1px solid #000 !important;
                padding: 15px !important;
                width: 100% !important;
                max-width: 100% !important;
                page-break-inside: avoid;
                line-height: 1.4 !important;
            }

            .content h3 {
                font-size: 12pt !important;
                margin: 0 !important;
            }

            .content h4 {
                font-size: 11pt !important;
                margin: 5px 0 !important;
            }

            .content img {
                height: 40px !important;
            }

            .content div[style*="height:120px"] {
                height: 80px !important;
                padding-top: 25px !important;
            }

            .footer {
                margin-top: 20px !important;
            }

            @page {
                size: A4 landscape;
                margin: 5mm;
            }
        }
    </style>
</head>

<body>
    <button onclick="window.print()" class="no-print"
        style="padding: 10px 20px; font-size: 16px; margin-bottom: 20px; cursor: pointer;" data-en="Print Page"
        data-am="ገጽ አትም">Print Page</button>

    <?php if ($doc['request_type'] == 'Graduation'):
        // Fetch detailed costs
        $cstmt = $pdo->prepare("SELECT SUM(tuition_fee) as t_fee, SUM(food_expense) as f_fee, SUM(bed_expense) as b_fee, SUM(medication_expense) as m_fee FROM cost_sharing_agreements WHERE student_id = ?");
        $cstmt->execute([$doc['student_id']]);
        $costs = $cstmt->fetch(PDO::FETCH_ASSOC);
        $t_fee = $costs['t_fee'] ?? 0;
        $f_fee = $costs['f_fee'] ?? 0;
        $b_fee = $costs['b_fee'] ?? 0;
        $m_fee = $costs['m_fee'] ?? 0;
        $total_fee = $t_fee + $f_fee + $b_fee + $m_fee;

        // Fetch sex and college since view only has dept
        $sstmt = $pdo->prepare("SELECT sex FROM students WHERE user_id = ?");
        $sstmt->execute([$doc['student_id']]);
        $sex = $sstmt->fetchColumn();
        $sex_am = ($sex == 'Male' || $sex == 'M') ? 'ወንድ' : (($sex == 'Female' || $sex == 'F') ? 'ሴት' : $sex);
        $college = $doc['college'] ?? '';
        ?>
        <div class="content" style="border: 1px solid #000; padding: 15px 20px; font-family: Arial, sans-serif;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                <h3 style="margin:0; flex:1;">ደብረ ማርቆስ ዩኒቨርሲቲ</h3>
                <img src="../../assets/images/dmulogo.png" style="height:40px;">
                <h3 style="margin:0; flex:1; text-align:right;">Debre Markos University</h3>
            </div>
            <div style="border-bottom:2px solid #000; margin-bottom:8px;"></div>

            <h3 style="margin:0; text-align:center; text-decoration:underline; margin-bottom:8px;">የወጪ መጋራት ተጠቃሚ መረጃ</h3>

            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <div style="flex-shrink:0;">
                    <div
                        style="border:1px solid #777; width:100px; min-width:100px; height:100px; text-align:center; padding-top:35px; font-weight:bold; color:#777; background:#fafafa;">
                        Photo</div>
                </div>
                <div style="width:45%; line-height: 1.4; font-size:0.9em;">
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
                    <hr style="border:1px dashed #000; margin:5px 0;">
                    <div style="display:flex; justify-content:space-between; font-weight:bold;"><span>ጠቅላላ ወጪ</span>
                        <span><?php echo number_format($total_fee, 0); ?></span>
                    </div>
                </div>
            </div>

            <div style="line-height: 1.4; font-size:0.9em;">
                1. የተማሪው የመታወቂያ ቁጥር: <strong><?php echo $doc['real_student_id']; ?></strong><br>
                2. የተጠቃሚው ስም ከነአያት:
                <strong><?php echo $doc['first_name'] . ' ' . $doc['middle_name'] . ' ' . $doc['last_name']; ?></strong><br>
                3. ተቋም/ኢንስቲትዩት/ኮሌጅ/ት/ቤት: <strong><?php echo $college; ?></strong><br>
                4. የትምህርት ክፍል: <strong><?php echo $doc['dept_name']; ?></strong><br>
                5. ጾታ: <strong><?php echo $sex_am; ?></strong><br>
                6. የወጪ መጋራት የሚከፈልበት ሁኔታ: [&#10003;] ከምረቃ በኋላ ከገቢው ተቀናሽ ሆኖ የሚከፈል [ &nbsp; ] በሙያው አገልግሎት በመስጠት<br>
                7. ተጠቃሚው በዩኒቨርሲቲው የቆየበት /ችበት/ ዓመት: <strong><?php echo $doc['batch']; ?></strong><br>
                8. ከተጠቃሚው ተቀናሽ ሆኖ የሚከፈል የወጪ መጋራት ክፍያ ብር: <strong><?php echo number_format($total_fee, 0); ?></strong>
                /ብቻ<br>
                9. በተጠቃሚው በቅድሚያ የተከፈለ በአካዝ ብር የለም በፊደል የለም<br>
                10. በቅድሚያ በተጠቃሚው የተከፈለበት የደረሰኝ ቁጥር የለም<br>
            </div>

            <div style="display:flex; justify-content:space-between; margin-top:10px; font-size:0.9em;">
                <div style="text-align:center;">
                    <strong>የወጪ መጋራት ባለሙያ</strong><br><br>
                    ስም፡- <strong><?php echo htmlspecialchars($cost_sharing_pro_name); ?></strong><br><br>
                    ፊርማ፡-
                    <?php if (!empty($doc['transcript_signature'])): ?>
                        <?php $t_src = (strpos($doc['transcript_signature'], 'data:image/') === 0) ? $doc['transcript_signature'] : "../../uploads/signatures/" . $doc['transcript_signature']; ?>
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
                    <?php if (!empty($doc['registrar_signature'])): ?>
                        <?php $r_src = (strpos($doc['registrar_signature'], 'data:image/') === 0) ? $doc['registrar_signature'] : "../../uploads/signatures/" . $doc['registrar_signature']; ?>
                        <img src="<?php echo $r_src; ?>" style="height:45px; vertical-align:middle;">
                    <?php else: ?>
                        __________________________
                    <?php endif; ?>
                    <br><br>
                    ቀን፡- <?php echo date('d/m/Y'); ?>
                </div>
            </div>
        </div>

    <?php elseif ($doc['request_type'] == 'Original'): ?>
        <div class="content" style="text-align:center; padding:40px; border:2px solid #000;">
            <img src="../../assets/img/logo.png" style="height:90px;">
            <h2>Debre Markos University / ደብረ ማርቆስ ዩኒቨርሲቲ</h2>
            <hr>
            <h3 style="margin-top:30px; color:#2c3e50;">Original Document Request Processed</h3>
            <h3 style="color:#2c3e50;">የኦሪጅናል ሰነድ ጥያቄዎ ተስተናግዷል</h3>
            <p style="font-size:1.2em; line-height:1.6; margin-top:30px;">
                የተከበሩ
                <strong><?php echo $doc['first_name'] . ' ' . $doc['middle_name'] . ' ' . $doc['last_name']; ?></strong>፣
                ጥያቄዎ ተቀባይነት አግኝቶ ተዘጋጅቷል!<br>
                እባክዎ <strong>በአካል በመቅረብ</strong> ኦሪጅናል ሰነድዎን ይውሰዱ።<br><br>
                Dear
                <strong><?php echo $doc['first_name'] . ' ' . $doc['middle_name'] . ' ' . $doc['last_name']; ?></strong>,
                your request has been fully processed.<br>
                Please <strong>appear in person</strong> to collect your original document.
            </p>

            <div class="footer" style="margin-top:60px;">
                <div class="signature-box">
                    <p><strong>Cost Sharing Professional:</strong></p>
                    <?php if (!empty($doc['transcript_signature'])): ?>
                        <?php $t_src = (strpos($doc['transcript_signature'], 'data:image/') === 0) ? $doc['transcript_signature'] : "../../uploads/signatures/" . $doc['transcript_signature']; ?>
                        <img src="<?php echo $t_src; ?>" class="signature-img">
                    <?php else: ?>
                        <p>__________________________</p>
                    <?php endif; ?>
                    <p><strong><?php echo htmlspecialchars($cost_sharing_pro_name); ?></strong></p>
                </div>
                <div class="signature-box">
                    <p><strong>Registrar Head:</strong></p>
                    <?php if (!empty($doc['registrar_signature'])): ?>
                        <?php $r_src = (strpos($doc['registrar_signature'], 'data:image/') === 0) ? $doc['registrar_signature'] : "../../uploads/signatures/" . $doc['registrar_signature']; ?>
                        <img src="<?php echo $r_src; ?>" class="signature-img">
                    <?php else: ?>
                        <p>__________________________</p>
                    <?php endif; ?>
                    <p><strong><?php echo htmlspecialchars($registrar_name); ?></strong></p>
                </div>
            </div>
        </div>

    <?php elseif ($doc['request_type'] == 'Transfer-Out'):
        $cstmt = $pdo->prepare("SELECT SUM(tuition_fee) as t_fee, SUM(food_expense) as f_fee, SUM(bed_expense) as b_fee, SUM(medication_expense) as m_fee FROM cost_sharing_agreements WHERE student_id = ?");
        $cstmt->execute([$doc['student_id']]);
        $costs = $cstmt->fetch(PDO::FETCH_ASSOC);
        $t_fee = number_format((float) ($costs['t_fee'] ?? 0), 2);
        $f_fee = number_format((float) ($costs['f_fee'] ?? 0), 2);
        $b_fee = number_format((float) ($costs['b_fee'] ?? 0), 2);
        $m_fee = number_format((float) ($costs['m_fee'] ?? 0), 2);
        $tot_fee = number_format((float) ($doc['cost_share_amount'] ?? 0), 2);
        ?>
        <div class="content" style="padding:40px; border:1px solid #ccc; max-width:800px; margin: 0 auto;">
            <div class="header" style="text-align:center; margin-bottom: 30px;">
                <img src="../../assets/images/dmulogo.png" alt="Logo" style="height:80px;">
                <h2>ደብረ ማርቆስ ዩኒቨርሲቲ</h2>
            </div>

            <p style="text-align:right;">ቀን: <?php echo date('d/m/Y'); ?></p>
            <p style="font-weight:bold; font-size:18px; text-decoration:underline;">ለሚመለከተው ዩኒቨርሲቲ
            </p>

            <p style="margin-top:20px; line-height: 1.6;">
                ይህ ደብዳቤ ተማሪ
                <strong><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['middle_name'] . ' ' . $doc['last_name']); ?></strong>
                (መታወቂያ ቁጥር: <strong><?php echo htmlspecialchars($doc['real_student_id']); ?></strong>)፣ በ
                <strong><?php echo htmlspecialchars($doc['dept_name']); ?></strong> የትምህርት ክፍል ትምህርታቸውን ሲከታተሉ የነበሩ ሲሆን፣
                ወደ ሌላ ተቋም ለሚያደርጉት የዝውውር ሂደት፣ በዩኒቨርሲቲያችን መረጃ መሰረት ከዚህ በታች የተዘረዘሩት የወጪ መጋራት ዕዳዎች
                እንዳሉባቸው እናሳውቃለን።
            </p>

            <table style="width:100%; border-collapse:collapse; margin-top:20px; margin-bottom:30px; text-align:center;"
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

            <p style="line-height:1.6;">
                እባክዎ እነዚህን የዕዳ መጠኖች በስርዓትዎ ላይ በመመዝገብ ዝውውሩን እንዲያጠናቅቁ እንጠይቃለን። ለትብብርዎ
                እናመሰግናለን።
            </p>

            <div class="footer" style="margin-top:60px; display:flex; justify-content:space-between; align-items:flex-end;">
                <div class="signature-box" style="text-align:center;">
                    <p><strong>የትራንስክሪፕት ባለሙያ:</strong></p>
                    <?php if (!empty($doc['transcript_signature'])): ?>
                        <?php $t_src = (strpos($doc['transcript_signature'], 'data:image/') === 0) ? $doc['transcript_signature'] : "../../uploads/signatures/" . $doc['transcript_signature']; ?>
                        <img src="<?php echo $t_src; ?>" class="signature-img" style="height:50px;">
                    <?php else: ?>
                        <p>__________________________</p>
                    <?php endif; ?>
                    <p><strong><?php echo htmlspecialchars($transcript_pro_name); ?></strong></p>
                </div>

                <div class="signature-box" style="text-align:center;">
                    <p><strong>የሬጅስትራር ኃላፊ:</strong></p>
                    <?php if (!empty($doc['registrar_signature'])): ?>
                        <?php $r_src = (strpos($doc['registrar_signature'], 'data:image/') === 0) ? $doc['registrar_signature'] : "../../uploads/signatures/" . $doc['registrar_signature']; ?>
                        <img src="<?php echo $r_src; ?>" class="signature-img" style="height:50px;">
                    <?php else: ?>
                        <p>__________________________</p>
                    <?php endif; ?>
                    <p><strong><?php echo htmlspecialchars($registrar_name); ?></strong></p>
                </div>
            </div>
        </div>

        <?php
        // Fetch courses for the course transcript up to the current batch and semester
        $stmt_courses = $pdo->prepare("SELECT batch, semester, course_name, credit_hour FROM courses WHERE department_id = ? AND (batch < ? OR (batch = ? AND semester <= ?)) ORDER BY batch ASC, semester ASC");
        $stmt_courses->execute([$doc['student_dept_id'], $doc['batch'], $doc['batch'], $doc['current_semester']]);
        $courses = $stmt_courses->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="content"
            style="page-break-before: always; border: 1px solid #ccc; padding: 40px; font-family: Arial, sans-serif; max-width:800px; margin: 20px auto;">
            <div class="header" style="text-align:center; margin-bottom: 20px;">
                <img src="../../assets/images/dmulogo.png" alt="Logo" style="height:80px;"><br>
                <h3 style="margin-top:10px;text-decoration: underline;">የተማሪ የትምህርት ኮርሶች ማረጋገጫ / Course Transcript</h3>
            </div>

            <p><strong>Student Name:</strong>
                <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['middle_name'] . ' ' . $doc['last_name']); ?>
            </p>
            <p><strong>ID Number:</strong> <?php echo htmlspecialchars($doc['real_student_id']); ?></p>
            <p><strong>Department:</strong> <?php echo htmlspecialchars($doc['dept_name']); ?></p>
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
                    <table style="width:100%; border-collapse:collapse; margin-bottom:15px; text-align:left; font-size:14px;"
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

            <div class="footer" style="margin-top:50px; display:flex; justify-content:space-between; align-items:flex-end;">
                <div class="signature-box" style="text-align:center;">
                    <p><strong>የትራንስክሪፕት ባለሙያ:</strong></p>
                    <?php if (!empty($doc['transcript_signature'])): ?>
                        <?php $t_src = (strpos($doc['transcript_signature'], 'data:image/') === 0) ? $doc['transcript_signature'] : "../../uploads/signatures/" . $doc['transcript_signature']; ?>
                        <img src="<?php echo $t_src; ?>" class="signature-img" style="height:50px;">
                    <?php else: ?>
                        <p>__________________________</p>
                    <?php endif; ?>
                    <p><strong><?php echo htmlspecialchars($transcript_pro_name); ?></strong></p>
                </div>
                <div class="signature-box" style="text-align:center;">
                    <p><strong>የሬጅስትራር ኃላፊ:</strong></p>
                    <?php if (!empty($doc['registrar_signature'])): ?>
                        <?php $r_src = (strpos($doc['registrar_signature'], 'data:image/') === 0) ? $doc['registrar_signature'] : "../../uploads/signatures/" . $doc['registrar_signature']; ?>
                        <img src="<?php echo $r_src; ?>" class="signature-img" style="height:50px;">
                    <?php else: ?>
                        <p>__________________________</p>
                    <?php endif; ?>
                    <p><strong><?php echo htmlspecialchars($registrar_name); ?></strong></p>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="header">
            <h2 data-en="DEBRE MARKOS UNIVERSITY" data-am="ደብረ ማርቆስ ዩኒቨርሲቲ">DEBRE MARKOS UNIVERSITY</h2>
            <h3 data-en="OFFICE OF THE REGISTRAR" data-am="ሬጅስትራር ጽህፈት ቤት">OFFICE OF THE REGISTRAR</h3>
            <h4 data-en="Official Document" data-am="ይፋዊ ሰነድ">Official Document</h4>
        </div>

        <div class="content">
            <p><strong data-en="Date:" data-am="ቀን:">Date:</strong> <?php echo date('d/m/Y'); ?></p>
            <p><strong data-en="To: Whom It May Concern" data-am="ለ: ለሚመለከተው ሁሉ">To: Whom It May Concern</strong></p>
            <p>
                <span data-en="This is to certify that" data-am="ይህ የሚያረጋግጠው">This is to certify that</span> <strong>
                    <?php echo strtoupper($doc['first_name'] . ' ' . $doc['middle_name'] . ' ' . $doc['last_name']); ?>
                </strong>
                (<span data-en="ID:" data-am="መታወቂያ:">ID:</span> <strong>
                    <?php echo $doc['real_student_id']; ?>
                </strong>) <span data-en="was a bona fide student of Debre Markos University in the Department of"
                    data-am="በደብረ ማርቆስ ዩኒቨርሲቲ ተማሪ የነበረ/ች በ">was a bona fide student of Debre Markos University in the
                    Department of</span> <strong>
                    <?php echo $doc['dept_name']; ?>
                </strong>, <span data-en="College of" data-am="ኮሌጅ">College of</span> <strong>
                    <?php echo $doc['college']; ?>
                </strong>.
            </p>

            <p><span data-en="The student has requested:" data-am="ተማሪው የሚከተለውን ጠይቋል:">The student has requested:</span>
                <strong><?php echo $doc['request_type']; ?></strong>.
            </p>

            <p><span data-en="We confirm that the student has cleared all cost sharing obligations / or has an agreement on file as per the university regulations."
                    data-am="ተማሪው በዩኒቨርሲቲው ደንብ መሰረት ሁሉንም የወጪ መጋራት ግዴታዎች እንዳጸዳ/ች ወይም ስምምነት እንዳለው/ላት እናረጋግጣለን።">We confirm
                    that the student has cleared all cost sharing obligations / or has an agreement on file as per the
                    university regulations.</span></p>

            <p><span data-en="This document is issued upon the request of the student for legal purposes."
                    data-am="ይህ ሰነድ ለህጋዊ ጉዳዮች በተማሪው ጥያቄ መሰረት የተሰጠ ነው።">This document is issued upon the request of the
                    student for legal purposes.</span></p>
        </div>

        <div class="footer">
            <div class="signature-box">
                <p><strong data-en="Transcript Professional:" data-am="ትራንስክሪፕት ባለሙያ:">Transcript Professional:</strong></p>
                <?php if (!empty($doc['transcript_signature'])): ?>
                    <?php $t_src = (strpos($doc['transcript_signature'], 'data:image/') === 0) ? $doc['transcript_signature'] : "../../uploads/signatures/" . $doc['transcript_signature']; ?>
                    <img src="<?php echo $t_src; ?>" class="signature-img" alt="Transcript Signature">
                <?php else: ?>
                    <p>__________________________</p>
                <?php endif; ?>
                <p><strong><?php echo htmlspecialchars($transcript_pro_name); ?></strong></p>
                <p data-en="Transcript Professional" data-am="ትራንስክሪፕት ባለሙያ">Transcript Professional</p>
            </div>

            <div class="signature-box">
                <p><strong data-en="Registrar Head:" data-am="ሬጅስትራር ኃላፊ:">Registrar Head:</strong></p>
                <?php if (!empty($doc['registrar_signature'])): ?>
                    <?php $r_src = (strpos($doc['registrar_signature'], 'data:image/') === 0) ? $doc['registrar_signature'] : "../../uploads/signatures/" . $doc['registrar_signature']; ?>
                    <img src="<?php echo $r_src; ?>" class="signature-img" alt="Registrar Signature">
                <?php else: ?>
                    <p>__________________________</p>
                <?php endif; ?>
                <p><strong><?php echo htmlspecialchars($registrar_name); ?></strong></p>
                <p data-en="Official Seal & Signature" data-am="ይፋዊ ማህተም እና ፊርማ">Official Seal & Signature</p>
            </div>
        </div>
    <?php endif; ?>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>