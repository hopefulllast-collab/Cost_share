<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['cost_sharing_pro']);

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

// AJAX Preview Handler
if (isset($_GET['preview_req_id'])) {
    $req_id = $_GET['preview_req_id'];
    $stmt = $pdo->prepare("SELECT dr.*, u.first_name, u.middle_name, u.last_name, s.student_id as real_student_id, d.name as dept_name, s.academic_year, s.batch, s.current_semester 
                           FROM official_transcript dr 
                           JOIN students s ON dr.student_id = s.user_id 
                           JOIN users u ON s.user_id = u.id 
                           LEFT JOIN departments d ON s.department_id = d.id 
                           WHERE dr.id = ?");
    $stmt->execute([$req_id]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt_xtra = $pdo->prepare("SELECT s.sex, d.college FROM students s LEFT JOIN departments d ON s.department_id = d.id WHERE s.user_id = ?");
    $stmt_xtra->execute([$info['student_id']]);
    $xtra = $stmt_xtra->fetch(PDO::FETCH_ASSOC);
    $sex = $xtra['sex'] ?? '';
    $college = $xtra['college'] ?? '';

    $cstmt = $pdo->prepare("SELECT SUM(tuition_fee) as t_fee, SUM(food_expense) as f_fee, SUM(bed_expense) as b_fee, SUM(medication_expense) as m_fee FROM cost_sharing_agreements WHERE student_id = ?");
    $cstmt->execute([$info['student_id']]);
    $costs = $cstmt->fetch(PDO::FETCH_ASSOC);

    $t_fee = $costs['t_fee'] ?? 0;
    $f_fee = $costs['f_fee'] ?? 0;
    $b_fee = $costs['b_fee'] ?? 0;
    $m_fee = $costs['m_fee'] ?? 0;
    $total_fee = $t_fee + $f_fee + $b_fee + $m_fee;

    $sex_am = ($sex == 'Male' || $sex == 'M') ? 'ወንድ' : (($sex == 'Female' || $sex == 'F') ? 'ሴት' : $sex);

    $docContent = "";

    if ($info['request_type'] == 'Original') {
        $filepath = "../../uploads/clearances/" . $info['clearance_file'];
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        $docContent .= "<div style='font-family: Arial, sans-serif; padding:20px; border:1px solid #ccc; background:#fff;'>";
        $docContent .= "<h3 style='margin-top:0; text-align:center; color:#0056b3;'>Uploaded Stamped Clearance File</h3>";
        $docContent .= "<div style='height:450px; display:flex; justify-content:center; align-items:center; background:#fafafa; border:1px dashed #aaa; overflow:hidden;'>";
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
            $docContent .= "<img src='{$filepath}' style='max-width:100%; max-height:100%; object-fit:contain;' />";
        } elseif ($ext === 'html') {
            $docContent .= "<iframe src='{$filepath}' style='width:100%; height:100%; border:none;'></iframe>";
        } else {
            $docContent .= "<embed src='{$filepath}' type='application/pdf' style='width:100%; height:100%;' />";
        }
        $docContent .= "</div></div>";
    } else {
        $docContent .= "<div class='generated-doc' style='font-family: Arial, sans-serif; padding:40px; border:1px dashed #ccc; background:#fff;'>";
        $docContent .= "<div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;'>";
        $docContent .= "    <h3 style='margin:0; flex:1;'>ደብረ ማርቆስ ዩኒቨርሲቲ</h3>";
        $docContent .= "    <img src='../../assets/images/dmulogo.png' style='height:40px;'>";
        $docContent .= "    <h3 style='margin:0; flex:1; text-align:right;'>Debre Markos University</h3>";
        $docContent .= "</div>";
        $docContent .= "<div style='border-bottom:2px solid #000; margin-bottom:10px;'></div>";

        $docContent .= "<h3 style='margin:0; text-align:center; text-decoration:underline; margin-bottom:10px;'>የወጪ መጋራት ተጠቃሚ መረጃ</h3>";

        $docContent .= "<div style='display:flex; justify-content:space-between; margin-bottom:10px;'>";
        $docContent .= "    <div style='flex-shrink:0;'><div style='border:1px solid #777; width:100px; min-width:100px; height:100px; text-align:center; padding-top:35px; font-weight:bold; color:#777; background:#fafafa;'>Photo</div></div>";
        $docContent .= "    <div style='width:45%; line-height: 1.8; font-size:1em;'>";
        $docContent .= "        <h4 style='margin:0 0 5px 0;text-align:center;'>የወጪ መጠን</h4>";
        $docContent .= "        <div style='display:flex; justify-content:space-between;'><span>ለምግብ</span> <span>" . number_format($f_fee, 0) . "</span></div>";
        $docContent .= "        <div style='display:flex; justify-content:space-between;'><span>ለዶርም</span> <span>" . number_format($b_fee, 0) . "</span></div>";
        $docContent .= "        <div style='display:flex; justify-content:space-between;'><span>ለትምህርት</span> <span>" . number_format($t_fee, 0) . "</span></div>";
        $docContent .= "        <div style='display:flex; justify-content:space-between;'><span>ለክሊኒክ</span> <span>" . number_format($m_fee, 0) . "</span></div>";
        $docContent .= "        <hr style='border:1px dashed #000; margin:5px 0;'>";
        $docContent .= "        <div style='display:flex; justify-content:space-between; font-weight:bold;'><span>ጠቅላላ ወጪ</span> <span>" . number_format($total_fee, 0) . "</span></div>";
        $docContent .= "    </div>";
        $docContent .= "</div>";

        $docContent .= "<div style='line-height: 1.8; font-size:1em;'>";
        $docContent .= "    1. የተማሪው የመታወቂያ ቁጥር: <strong>{$info['real_student_id']}</strong><br>";
        $docContent .= "    2. የተጠቃሚው ስም ከነአያት: <strong>{$info['first_name']} {$info['middle_name']} {$info['last_name']}</strong><br>";
        $docContent .= "    3. ተቋም/ኢንስቲትዩት/ኮሌጅ/ት/ቤት: <strong>{$college}</strong><br>";
        $docContent .= "    4. የትምህርት ክፍል: <strong>{$info['dept_name']}</strong><br>";
        $docContent .= "    5. ጾታ: <strong>{$sex_am}</strong><br>";
        $docContent .= "    6. የወጪ መጋራት የሚከፈልበት ሁኔታ: [&#10003;] ከምረቃ በኋላ ከገቢው ተቀናሽ ሆኖ የሚከፈል [ &nbsp; ] በሙያው አገልግሎት በመስጠት<br>";
        $docContent .= "    7. ተጠቃሚው በዩኒቨርሲቲው የቆየበት /ችበት/ ዓመት: <strong>{$info['batch']}</strong><br>";
        $docContent .= "    8. ከተጠቃሚው ተቀናሽ ሆኖ የሚከፈል የወጪ መጋራት ክፍያ ብር: <strong>" . number_format($total_fee, 0) . "</strong> /ብቻ<br>";
        $docContent .= "    9. በተጠቃሚው በቅድሚያ የተከፈለ በአካዝ ብር የለም በፊደል የለም<br>";
        $docContent .= "    10. በቅድሚያ በተጠቃሚው የተከፈለበት የደረሰኝ ቁጥር የለም<br>";
        $docContent .= "</div>";
        $docContent .= "</div>";
    }

    echo $docContent;
    exit();
}

// Handle Document Processing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_doc'])) {
    $req_id = $_POST['req_id'];
    $cost_share_amount = $_POST['cost_share_amount'];


    $stmt = $pdo->prepare("SELECT digital_signature FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $signature = $stmt->fetchColumn();

    if (empty($signature)) {
        $error = "<span data-en='You have not set up your digital signature. Please update your profile.' data-am='የዲጂታል ፊርማዎን አላዘጋጁም። እባክዎ ፕሮፋይልዎን ያዘምኑ።'>You have not set up your digital signature. Please <a href=\"../common/update_profile.php\">update your profile</a>.</span>";
    } else {
        // Generate Document Content (HTML)
        // Retrieve Student Info for the Doc
        $stmt = $pdo->prepare("SELECT dr.*, u.first_name, u.middle_name, u.last_name, s.student_id as real_student_id, d.name as dept_name, s.academic_year, s.batch, s.current_semester 
                               FROM official_transcript dr 
                               JOIN students s ON dr.student_id = s.user_id 
                               JOIN users u ON s.user_id = u.id 
                               LEFT JOIN departments d ON s.department_id = d.id 
                               WHERE dr.id = ?");
        $stmt->execute([$req_id]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch sex and college mapping
        $stmt_xtra = $pdo->prepare("SELECT s.sex, d.college FROM students s LEFT JOIN departments d ON s.department_id = d.id WHERE s.user_id = ?");
        $stmt_xtra->execute([$info['student_id']]);
        $xtra = $stmt_xtra->fetch(PDO::FETCH_ASSOC);
        $sex = $xtra['sex'] ?? '';
        $college = $xtra['college'] ?? '';

        $docContent = "";

        if ($info['request_type'] == 'Graduation') {
            // Fetch individual costs for graduation certificate
            $cstmt = $pdo->prepare("SELECT SUM(tuition_fee) as t_fee, SUM(food_expense) as f_fee, SUM(bed_expense) as b_fee, SUM(medication_expense) as m_fee FROM cost_sharing_agreements WHERE student_id = ?");
            $cstmt->execute([$info['student_id']]);
            $costs = $cstmt->fetch(PDO::FETCH_ASSOC);

            $t_fee = $costs['t_fee'] ?? 0;
            $f_fee = $costs['f_fee'] ?? 0;
            $b_fee = $costs['b_fee'] ?? 0;
            $m_fee = $costs['m_fee'] ?? 0;
            $total_fee = $t_fee + $f_fee + $b_fee + $m_fee;

            $sex_am = ($sex == 'Male' || $sex == 'M') ? 'ወንድ' : (($sex == 'Female' || $sex == 'F') ? 'ሴት' : $sex);

            $docContent = "<div class='generated-doc' style='font-family: Arial, sans-serif; padding:20px; border:1px solid #ccc; background:#fff;'>";
            $docContent .= "<div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;'>";
            $docContent .= "    <h3 style='margin:0; flex:1;'>ደብረ ማርቆስ ዩኒቨርሲቲ</h3>";
            $docContent .= "    <img src='../../assets/images/dmulogo.png' style='height:60px;'>";
            $docContent .= "    <h3 style='margin:0; flex:1; text-align:right;'>Debre Markos University</h3>";
            $docContent .= "</div>";
            $docContent .= "<div style='border-bottom:2px solid #000; margin-bottom:10px;'></div>";

            $docContent .= "<h3 style='margin:0; text-align:center; text-decoration:underline; margin-bottom:10px;'>የወጪ መጋራት ተጠቃሚ መረጃ</h3>";

            $docContent .= "<div style='display:flex; justify-content:space-between; margin-bottom:10px;'>";
            $docContent .= "    <div style='flex-shrink:0;'><div style='border:1px solid #777; width:100px; min-width:100px; height:100px; text-align:center; padding-top:35px; font-weight:bold; color:#777; background:#fafafa;'>Photo</div></div>";
            $docContent .= "    <div style='width:45%; line-height: 1.8; font-size:1em;'>";
            $docContent .= "        <h4 style='margin:0 0 5px 0;text-align:center;'>የወጪ መጠን</h4>";
            $docContent .= "        <div style='display:flex; justify-content:space-between;'><span>ለምግብ</span> <span>" . number_format($f_fee, 0) . "</span></div>";
            $docContent .= "        <div style='display:flex; justify-content:space-between;'><span>ለዶርም</span> <span>" . number_format($b_fee, 0) . "</span></div>";
            $docContent .= "        <div style='display:flex; justify-content:space-between;'><span>ለትምህርት</span> <span>" . number_format($t_fee, 0) . "</span></div>";
            $docContent .= "        <div style='display:flex; justify-content:space-between;'><span>ለክሊኒክ</span> <span>" . number_format($m_fee, 0) . "</span></div>";
            $docContent .= "        <hr style='border:1px dashed #000; margin:5px 0;'>";
            $docContent .= "        <div style='display:flex; justify-content:space-between; font-weight:bold;'><span>ጠቅላላ ወጪ</span> <span>" . number_format($total_fee, 0) . "</span></div>";
            $docContent .= "    </div>";
            $docContent .= "</div>";

            $docContent .= "<div style='line-height: 1.8; font-size:1em;'>";
            $docContent .= "    1. የተማሪው የመታወቂያ ቁጥር: <strong>{$info['real_student_id']}</strong><br>";
            $docContent .= "    2. የተጠቃሚው ስም ከነአያት: <strong>{$info['first_name']} {$info['middle_name']} {$info['last_name']}</strong><br>";
            $docContent .= "    3. ተቋም/ኢንስቲትዩት/ኮሌጅ/ት/ቤት: <strong>{$college}</strong><br>";
            $docContent .= "    4. የትምህርት ክፍል: <strong>{$info['dept_name']}</strong><br>";
            $docContent .= "    5. ጾታ: <strong>{$sex_am}</strong><br>";
            $docContent .= "    6. የወጪ መጋራት የሚከፈልበት ሁኔታ: [&#10003;] ከምረቃ በኋላ ከገቢው ተቀናሽ ሆኖ የሚከፈል [ &nbsp; ] በሙያው አገልግሎት በመስጠት<br>";
            $docContent .= "    7. ተጠቃሚው በዩኒቨርሲቲው የቆየበት /ችበት/ ዓመት: <strong>{$info['batch']}</strong><br>";
            $docContent .= "    8. ከተጠቃሚው ተቀናሽ ሆኖ የሚከፈል የወጪ መጋራት ክፍያ ብር: <strong>" . number_format($total_fee, 0) . "</strong> /ብቻ<br>";
            $docContent .= "    9. በተጠቃሚው በቅድሚያ የተከፈለ በአካዝ ብር የለም በፊደል የለም<br>";
            $docContent .= "    10. በቅድሚያ በተጠቃሚው የተከፈለበት የደረሰኝ ቁጥር የለም<br>";
            $docContent .= "</div>";

            $docContent .= "<div style='display:flex; justify-content:space-between; margin-top:60px; font-size:1.1em;'>";
            $docContent .= "    <div style='text-align:center;'>";
            $docContent .= "        <strong>የወጪ መጋራት ባለሙያ</strong><br><br>";
            $docContent .= "        ስም፡- ______________<br><br>";
            $docContent .= "        ፊርማ፡- <img src='{$signature}' style='height:45px; vertical-align:middle;'><br><br>";
            $docContent .= "        ቀን፡- " . date('d/m/Y') . "<br>";
            $docContent .= "    </div>";
            $docContent .= "    <div style='text-align:center;'>";
            $docContent .= "        <strong>ያረጋገጠው ፊርማ (Registrar)</strong><br><br>";
            $docContent .= "        ስም፡- ______________<br><br>";
            $docContent .= "        ፊርማ፡- <span style='font-style:italic; color:#777;'>[Pending Registrar Signature]</span><br>";
            $docContent .= "    </div>";
            $docContent .= "</div>";
            $docContent .= "</div>";
        } elseif ($info['request_type'] == 'Transfer-Out') {
            $t_fee = number_format((float) ($costs['t_fee'] ?? 0), 2);
            $f_fee = number_format((float) ($costs['f_fee'] ?? 0), 2);
            $b_fee = number_format((float) ($costs['b_fee'] ?? 0), 2);
            $m_fee = number_format((float) ($costs['m_fee'] ?? 0), 2);
            $tot_fee = number_format((float) $cost_share_amount, 2);

            $docContent = "<div class='generated-doc' style='padding:40px; font-family:Arial, sans-serif; background:#fff; border: 1px solid #ccc; max-width:800px; margin: 0 auto;'>";
            $docContent .= "<div class='header' style='text-align:center; margin-bottom: 30px;'><img src='../../assets/images/dmulogo.png' alt='Logo' style='height:80px;'><h2>ደብረ ማርቆስ ዩኒቨርሲቲ</h2></div>";
            $docContent .= "<p style='text-align:right;'>ቀን: " . date('d/m/Y') . "</p>";
            $docContent .= "<p style='font-weight:bold; font-size:18px; text-decoration:underline;'>ለሚመለከተው ዩኒቨርሲቲ</p>";
            $docContent .= "<p style='margin-top:20px; line-height: 1.6;'>ይህ ደብዳቤ ተማሪ <strong>{$info['first_name']} {$info['middle_name']} {$info['last_name']}</strong> ";
            $docContent .= "(መታወቂያ ቁጥር: <strong>{$info['real_student_id']}</strong>)፣ በ <strong>{$info['dept_name']}</strong> የትምህርት ክፍል ትምህርታቸውን ሲከታተሉ የነበሩ ሲሆን፣ ";
            $docContent .= "ወደ ሌላ ተቋም ለሚያደርጉት የዝውውር ሂደት፣ በዩኒቨርሲቲያችን መረጃ መሰረት ከዚህ በታች የተዘረዘሩት የወጪ መጋራት ዕዳዎች እንዳሉባቸው እናሳውቃለን።</p>";

            $docContent .= "<table style='width:100%; border-collapse:collapse; margin-top:20px; margin-bottom:30px; text-align:center;' border='1' cellpadding='8'>";
            $docContent .= "<tr style='background-color:#f4f4f4;'>
                                <th>የትምህርት ወጪ</th>
                                <th>የምግብ ወጪ</th>
                                <th>የአልጋ ወጪ</th>
                                <th>የህክምና ወጪ</th>
                                <th>ጠቅላላ የዕዳ ድምር</th>
                            </tr>";
            $docContent .= "<tr>
                                <td>{$t_fee} ብር</td>
                                <td>{$f_fee} ብር</td>
                                <td>{$b_fee} ብር</td>
                                <td>{$m_fee} ብር</td>
                                <td><strong>{$tot_fee} ብር</strong></td>
                            </tr>";
            $docContent .= "</table>";

            $docContent .= "<p style='line-height:1.6;'>እባክዎ እነዚህን የዕዳ መጠኖች በስርዓትዎ ላይ በመመዝገብ ዝውውሩን እንዲያጠናቅቁ እንጠይቃለን። ለትብብርዎ እናመሰግናለን።</p>";

            $docContent .= "<div class='signatures' style='margin-top:50px; display:flex; justify-content:space-between; align-items:flex-end;'>";
            $docContent .= "<div class='sig-block' style='text-align:center;'><img src='{$signature}' style='height:50px;'><br><hr style='width:150px;'>የወጪ መጋራት ባለሙያ</div>";
            $docContent .= "<div class='sig-block' style='text-align:center;'><span style='font-style:italic; color:#777;'>[የሬጅስትራር ፊርማ በመጠባበቅ ላይ]</span><br><hr style='width:150px;'>የሬጅስትራር ኃላፊ</div>";
            $docContent .= "</div>";
            $docContent .= "</div>";

        }

        $next_status = ($info['request_type'] == 'Transfer-Out') ? 'Pending Transcript' : 'Pending Registrar Signature';
        $success_en = ($info['request_type'] == 'Transfer-Out') ? 'Document processed and forwarded to Transcript Pro.' : 'Document processed, signed, and forwarded to Registrar.';
        $success_am = ($info['request_type'] == 'Transfer-Out') ? 'ሰነዱ ተሰርቷል እና ወደ ትራንስክሪፕት ባለሙያ ተላልፏል።' : 'ሰነዱ ተሰርቷል፣ ተፈርሟል እና ወደ ሬጂስትራር ተላልፏል።';

        $stmt = $pdo->prepare("UPDATE official_transcript SET status = ?, transcript_signature = ?, cost_share_amount = ?, generated_doc_content = ? WHERE id = ?");
        $res = $stmt->execute([$next_status, $signature, $cost_share_amount, $docContent, $req_id]);

        $_SESSION["flash_success"] = "<span data-en='{$success_en}' data-am='{$success_am}'>{$success_en}</span>";
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    }
}



// Fetch Pending Requests (New Workflow)
$pending_requests = $pdo->query("SELECT dr.*, u.first_name, u.last_name, s.student_id as real_student_id, d.name as dept_name,
                                 (SELECT SUM(tuition_fee + food_expense + bed_expense + medication_expense) 
                                  FROM cost_sharing_agreements ct 
                                  WHERE ct.student_id = s.user_id) as total_debt
                                 FROM official_transcript dr 
                                 JOIN students s ON dr.student_id = s.user_id 
                                 JOIN users u ON s.user_id = u.id 
                                 LEFT JOIN departments d ON s.department_id = d.id 
                                 WHERE dr.request_type IN ('Graduation', 'Original', 'Transfer-Out') AND dr.status = 'Pending Cost Share Pro'")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Issue Document - Cost Sharing Professional" data-am="ሰነድ አዘጋጅ - ወጪ መጋራት ባለሙያ">Issue Document - Cost
        Sharing Professional
    </title>
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
                    <h2 data-en="Issue Graduation Certificates" data-am="የምረቃ ክሊራንስ አዘጋጅ">Issue Graduation Certificates
                    </h2>
                </div>

                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>

                <!-- Pending Document Requests -->
                <div class="card">
                    <h3 data-en="Pending Document Requests" data-am="በመጠባበቅ ላይ ያሉ የሰነድ ጥያቄዎች">Pending Document
                        Requests</h3>
                    <?php if (empty($pending_requests)): ?>
                        <p data-en="No pending requests forwarded from Registrar."
                            data-am="ከሬጅስትራር የተላኩ በመጠባበቅ ላይ ያሉ ጥያቄዎች የሉም።">No pending requests forwarded from Registrar.
                        </p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th data-en="Student ID" data-am="የተማሪ መለያ">Student ID</th>
                                    <th data-en="Name" data-am="ስም">Name</th>
                                    <th data-en="Type" data-am="አይነት">Type</th>
                                    <th data-en="Action" data-am="ተግባር">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_requests as $req): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($req['real_student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($req['request_type']); ?></td>
                                        <td>
                                            <button class="btn-primary"
                                                onclick="openProcessModal(<?php echo htmlspecialchars(json_encode($req)); ?>)">
                                                <span data-en="Give (Process)" data-am="ስጥ (ሂደት)">Give (Process)</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- Process Modal -->
    <div id="processModal" class="modal"
        style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.6);">
        <div class="modal-content"
            style="background-color:#fefefe; margin:5% auto; padding:30px; border:1px solid #888; width:90%; max-width:1100px; border-radius:8px;">
            <span class="close" onclick="closeModal()"
                style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
            <h3 data-en="Process Document Request" data-am="የሰነድ ጥያቄን ማስተናገድ">Process Document Request</h3>

            <p><strong data-en="Student:" data-am="ተማሪ:">Student:</strong> <span id="modalStudentName"></span></p>

            <h4 data-en="Document Preview" data-am="የሰነድ ቅድመ እይታ">Document Preview</h4>
            <div id="modalContent"
                style="border:1px dashed #777; padding:20px; background:#f9f9f9; min-height:450px; overflow:auto;">
            </div>

            <form method="POST" style="margin-top:20px;">
                <input type="hidden" name="req_id" id="modalReqId">
                <input type="hidden" name="process_doc" value="1">
                <div class="form-group">
                    <label data-en="Total Cost Share Amount (Birr)" data-am="ጠቅላላ ወጪ መጋራት መጠን (ብር)">Total Cost Share
                        Amount (Birr)</label>
                    <input type="number" step="0.01" name="cost_share_amount" id="modalAmount" required>
                </div>

                <div class="form-group">
                    <p style="font-size:0.9em; color:#666;"
                        data-en="* This will generate the document with the University Logo, Student Info, and your signature. It will then be forwarded to the Registrar for final signature."
                        data-am="* ይህ በዩኒቨርሲቲው አርማ፣ በተማሪ መረጃ እና በእርስዎ ፊርማ ሰነዱን ያመነጫል። ከዚያም ለመጨረሻ ፊርማ ወደ ሬጅስትራር ይተላለፋል።">
                        * This will generate the document with the University Logo, Student Info, and your signature.
                        It will then be forwarded to the Registrar for final signature.
                    </p>
                </div>

                <button type="submit" class="btn-success" data-en="Sign & Forward" data-am="ፈርመህ አስተላልፍ">Sign &
                    Forward</button>
            </form>
        </div>
    </div>

    <script>
        function openProcessModal(data) {
            document.getElementById('processModal').style.display = 'block';
            document.getElementById('modalReqId').value = data.id;
            document.getElementById('modalStudentName').textContent = data.first_name + ' ' + data.last_name + ' (' + data.real_student_id + ')';

            // Auto-fill calculated amount from DB
            let debt = parseFloat(data.total_debt || 0);
            document.getElementById('modalAmount').value = debt.toFixed(2);

            // Fetch Preview
            document.getElementById('modalContent').innerHTML = '<div style="text-align:center; padding:30px;">Loading Preview...</div>';
            fetch('issue_document.php?preview_req_id=' + data.id)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('modalContent').innerHTML = html;
                });
        }

        function closeModal() {
            document.getElementById('processModal').style.display = 'none';
        }

        window.onclick = function (event) {
            if (event.target == document.getElementById('processModal')) {
                closeModal();
            }
        }
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>