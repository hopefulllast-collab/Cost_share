<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['transcript_pro']);

// Handle AJAX Preview Request
if (isset($_GET['ajax_preview']) && isset($_GET['req_id'])) {
    $req_id = $_GET['req_id'];
    $stmt = $pdo->prepare("SELECT dr.*, u.first_name, u.middle_name, u.last_name, s.student_id as real_student_id, d.name as dept_name, s.academic_year, s.batch, s.current_semester, s.department_id as student_dept_id 
                           FROM official_transcript dr 
                           JOIN students s ON dr.student_id = s.user_id 
                           JOIN users u ON s.user_id = u.id 
                           LEFT JOIN departments d ON s.department_id = d.id 
                           WHERE dr.id = ?");
    $stmt->execute([$req_id]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        echo "<p style='color:red;'>Request not found.</p>";
        exit();
    }

    if ($info['request_type'] == 'original' || $info['request_type'] == 'Original') {
        $docContent = "<div class='generated-doc' style='text-align:center; padding:40px; border:2px solid #000; background:#fff; font-family:Arial;'>";
        $docContent .= "<img src='../../assets/img/logo.png' alt='Logo' style='height:90px;'>";
        $docContent .= "<h2>Debre Markos University / ደብረ ማርቆስ ዩኒቨርሲቲ</h2><hr>";
        $docContent .= "<h3 style='margin-top:30px; color:#2c3e50;'>Original Document Request Processed</h3>";
        $docContent .= "<h3 style='color:#2c3e50;'>የኦሪጅናል ሰነድ ጥያቄዎ ተስተናግዷል</h3>";
        $docContent .= "<p style='font-size:1.2em; line-height:1.6; margin-top:30px;'>";
        $docContent .= "የተከበሩ <strong>{$info['first_name']} {$info['middle_name']} {$info['last_name']}</strong>፣ ጥያቄዎ ተቀባይነት አግኝቶ ተዘጋጅቷል!<br>";
        $docContent .= "እባክዎ <strong>በአካል በመቅረብ</strong> ኦሪጅናል ሰነድዎን ይውሰዱ።<br><br>";
        $docContent .= "Dear <strong>{$info['first_name']} {$info['middle_name']} {$info['last_name']}</strong>, your request has been fully processed.<br>";
        $docContent .= "Please <strong>appear in person</strong> to collect your original document.";
        $docContent .= "</p>";
        $docContent .= "</div>";
        echo $docContent;
        exit();
    } elseif ($info['request_type'] == 'Transfer-Out') {
        $docContent = $info['generated_doc_content'] ?? '';

        $stmt_courses = $pdo->prepare("SELECT batch, semester, course_name, credit_hour FROM courses WHERE department_id = ? AND (batch < ? OR (batch = ? AND semester <= ?)) ORDER BY batch ASC, semester ASC");
        $stmt_courses->execute([$info['student_dept_id'], $info['batch'], $info['batch'], $info['current_semester']]);
        $courses = $stmt_courses->fetchAll(PDO::FETCH_ASSOC);

        $docContent .= "<div style='page-break-before: always; padding: 40px; font-family: Arial, sans-serif; background:#fff; border: 1px solid #ccc; max-width:800px; margin: 20px auto;'>";
        $docContent .= "<div class='header' style='text-align:center; margin-bottom: 20px;'>";
        $docContent .= "<img src='../../assets/images/dmulogo.png' alt='Logo' style='height:80px;'><br>";
        $docContent .= "<h3 style='margin-top:10px;text-decoration: underline;'>የተማሪ የትምህርት ኮርሶች ማረጋገጫ / Course Transcript</h3>";
        $docContent .= "</div>";

        $docContent .= "<p><strong>Student Name:</strong> {$info['first_name']} {$info['middle_name']} {$info['last_name']}</p>";
        $docContent .= "<p><strong>ID Number:</strong> {$info['real_student_id']}</p>";
        $docContent .= "<p><strong>Department:</strong> {$info['dept_name']}</p>";
        $docContent .= "<br>";

        if (empty($courses)) {
            $docContent .= "<p style='color:red;'>No courses found for this department.</p>";
        } else {
            $courses_by_term = [];
            foreach ($courses as $c) {
                $term = 'Year: ' . $c['batch'] . ' Semester: ' . $c['semester'];
                $courses_by_term[$term][] = $c;
            }

            $cumulative_credits = 0;
            foreach ($courses_by_term as $term => $term_courses) {
                $docContent .= "<h4 style='margin:20px 0 5px 0; text-align:center;'>{$term}</h4>";
                $docContent .= "<table style='width:100%; border-collapse:collapse; margin-bottom:15px; text-align:left; font-size:14px;' border='1' cellpadding='8'>";
                $docContent .= "<tr style='background-color:#f4f4f4;'>
                                    <th>Course Title</th>
                                    <th>Course Code</th>
                                    <th>Credit</th>
                                    <th>Number Grade</th>
                                    <th>Letter Grade</th>
                                    <th>Grade Point</th>
                                </tr>";
                $term_credits = 0;
                foreach ($term_courses as $c) {
                    $term_credits += $c['credit_hour'];
                    $cumulative_credits += $c['credit_hour'];
                    $docContent .= "<tr>
                                        <td>" . htmlspecialchars($c['course_name']) . "</td>
                                        <td></td>
                                        <td>{$c['credit_hour']}</td>
                                        <td></td>
                                        <td></td>
                                        <td></td>
                                    </tr>";
                }
                $docContent .= "</table>";

                $docContent .= "<table style='width:50%; border-collapse:collapse; margin-bottom:30px; text-align:right; font-size:14px; margin-left:auto;' border='1' cellpadding='8'>";
                $docContent .= "<tr>
                                    <th style='background-color:#f4f4f4; text-align:left;'>Summary</th>
                                    <th style='background-color:#f4f4f4;'>Credit</th>
                                    <th style='background-color:#f4f4f4;'>GP</th>
                                    <th style='background-color:#f4f4f4;'>ANG</th>
                                </tr>";
                $docContent .= "<tr>
                                    <td style='text-align:left;'>Previous Total</td>
                                    <td></td><td></td><td></td>
                                </tr>";
                $docContent .= "<tr>
                                    <td style='text-align:left;'>Semester Total</td>
                                    <td>{$term_credits}</td><td></td><td></td>
                                </tr>";
                $docContent .= "<tr>
                                    <td style='text-align:left;'>Cumulative</td>
                                    <td>{$cumulative_credits}</td><td></td><td></td>
                                </tr>";
                $docContent .= "</table>";
            }
        }
        $docContent .= "</div>";
        echo $docContent;
        exit();
    } else {
        echo "<p style='text-align:center; font-family:Arial;'>Preview ready for signing.</p>";
        exit();
    }
}

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

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
        $stmt = $pdo->prepare("SELECT dr.*, u.first_name, u.middle_name, u.last_name, s.student_id as real_student_id, d.name as dept_name, s.academic_year, s.batch, s.current_semester, s.department_id as student_dept_id 
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

        if ($info['request_type'] == 'original' || $info['request_type'] == 'Original') {
            $docContent = "<div class='generated-doc' style='text-align:center; padding:40px; border:2px solid #000; background:#fff; font-family:Arial;'>";
            $docContent .= "<img src='../../assets/img/logo.png' alt='Logo' style='height:90px;'>";
            $docContent .= "<h2>Debre Markos University / ደብረ ማርቆስ ዩኒቨርሲቲ</h2><hr>";
            $docContent .= "<h3 style='margin-top:30px; color:#2c3e50;'>Original Document Request Processed</h3>";
            $docContent .= "<h3 style='color:#2c3e50;'>የኦሪጅናል ሰነድ ጥያቄዎ ተስተናግዷል</h3>";
            $docContent .= "<p style='font-size:1.2em; line-height:1.6; margin-top:30px;'>";
            $docContent .= "የተከበሩ <strong>{$info['first_name']} {$info['middle_name']} {$info['last_name']}</strong>፣ ጥያቄዎ ተቀባይነት አግኝቶ ተዘጋጅቷል!<br>";
            $docContent .= "እባክዎ <strong>በአካል በመቅረብ</strong> ኦሪጅናል ሰነድዎን ይውሰዱ።<br><br>";
            $docContent .= "Dear <strong>{$info['first_name']} {$info['middle_name']} {$info['last_name']}</strong>, your request has been fully processed.<br>";
            $docContent .= "Please <strong>appear in person</strong> to collect your original document.";
            $docContent .= "</p>";

            $docContent .= "<div class='signatures' style='margin-top:60px;'>";
            $docContent .= "<div class='sig-block'><img src='{$signature}' style='height:50px;'><br>Transcript Professional</div>";
            $docContent .= "<div class='sig-block'><span style='font-style:italic; color:#777;'>[Pending Registrar Signature]</span><br>Registrar Head</div>";
            $docContent .= "</div>";
            $docContent .= "</div>";

        } elseif ($info['request_type'] == 'Transfer-Out') {
            // Fetch courses up to current batch/semester
            $stmt_courses = $pdo->prepare("SELECT batch, semester, course_name, credit_hour FROM courses WHERE department_id = ? AND (batch < ? OR (batch = ? AND semester <= ?)) ORDER BY batch ASC, semester ASC");
            $stmt_courses->execute([$info['student_dept_id'], $info['batch'], $info['batch'], $info['current_semester']]);
            $courses = $stmt_courses->fetchAll(PDO::FETCH_ASSOC);

            // Build the course transcript
            $docContent = $info['generated_doc_content'] ?? ''; // Appending to Cost Share Pro's letter

            $docContent .= "<div style='page-break-before: always; padding: 40px; font-family: Arial, sans-serif; background:#fff; border: 1px solid #ccc; max-width:800px; margin: 20px auto;'>";
            $docContent .= "<div class='header' style='text-align:center; margin-bottom: 20px;'>";
            $docContent .= "<img src='../../assets/images/dmulogo.png' alt='Logo' style='height:80px;'><br>";
            $docContent .= "<h3 style='margin-top:10px;text-decoration: underline;'>የተማሪ የትምህርት ኮርሶች ማረጋገጫ / Course Transcript</h3>";
            $docContent .= "</div>";

            $docContent .= "<p><strong>Student Name:</strong> {$info['first_name']} {$info['middle_name']} {$info['last_name']}</p>";
            $docContent .= "<p><strong>ID Number:</strong> {$info['real_student_id']}</p>";
            $docContent .= "<p><strong>Department:</strong> {$info['dept_name']}</p>";
            $docContent .= "<br>";

            if (empty($courses)) {
                $docContent .= "<p style='color:red;'>No courses found for this department.</p>";
            } else {
                $courses_by_term = [];
                foreach ($courses as $c) {
                    $term = 'Year: ' . $c['batch'] . ' Semester: ' . $c['semester'];
                    $courses_by_term[$term][] = $c;
                }

                $cumulative_credits = 0;
                foreach ($courses_by_term as $term => $term_courses) {
                    $docContent .= "<h4 style='margin:20px 0 5px 0; text-align:center;'>{$term}</h4>";
                    $docContent .= "<table style='width:100%; border-collapse:collapse; margin-bottom:15px; text-align:left; font-size:14px;' border='1' cellpadding='8'>";
                    $docContent .= "<tr style='background-color:#f4f4f4;'>
                                        <th>Course Title</th>
                                        <th>Course Code</th>
                                        <th>Credit</th>
                                        <th>Number Grade</th>
                                        <th>Letter Grade</th>
                                        <th>Grade Point</th>
                                    </tr>";
                    $term_credits = 0;
                    foreach ($term_courses as $c) {
                        $term_credits += $c['credit_hour'];
                        $cumulative_credits += $c['credit_hour'];
                        $docContent .= "<tr>
                                            <td>" . htmlspecialchars($c['course_name']) . "</td>
                                            <td></td>
                                            <td>{$c['credit_hour']}</td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                        </tr>";
                    }
                    $docContent .= "</table>";

                    $docContent .= "<table style='width:50%; border-collapse:collapse; margin-bottom:30px; text-align:right; font-size:14px; margin-left:auto;' border='1' cellpadding='8'>";
                    $docContent .= "<tr>
                                        <th style='background-color:#f4f4f4; text-align:left;'>Summary</th>
                                        <th style='background-color:#f4f4f4;'>Credit</th>
                                        <th style='background-color:#f4f4f4;'>GP</th>
                                        <th style='background-color:#f4f4f4;'>ANG</th>
                                    </tr>";
                    $docContent .= "<tr>
                                        <td style='text-align:left;'>Previous Total</td>
                                        <td></td><td></td><td></td>
                                    </tr>";
                    $docContent .= "<tr>
                                        <td style='text-align:left;'>Semester Total</td>
                                        <td>{$term_credits}</td><td></td><td></td>
                                    </tr>";
                    $docContent .= "<tr>
                                        <td style='text-align:left;'>Cumulative</td>
                                        <td>{$cumulative_credits}</td><td></td><td></td>
                                    </tr>";
                    $docContent .= "</table>";
                }
            }

            $docContent .= "<div class='signatures' style='margin-top:50px; display:flex; justify-content:space-between; align-items:flex-end;'>";
            $docContent .= "<div class='sig-block' style='text-align:center;'><img src='{$signature}' style='height:50px;'><br><hr style='width:200px;'>የትራንስክሪፕት ባለሙያ / Transcript Pro</div>";
            $docContent .= "<div class='sig-block' style='text-align:center;'><span style='font-style:italic; color:#777;'>[የሬጅስትራር ፊርማ በመጠባበቅ ላይ]</span><br><hr style='width:200px;'>የሬጅስትራር ኃላፊ / Registrar Head</div>";
            $docContent .= "</div>";

            $docContent .= "</div>";

        } else {
            $docContent = "<div class='generated-doc'>";
            $docContent .= "<div class='header'><img src='../../assets/img/logo.png' alt='Logo' style='height:80px;'><h2>Debre Markos University</h2></div>";
            $docContent .= "<p>Date: " . date('d/m/Y') . "</p>";
            $docContent .= "<p>To: Whom It May Concern</p>";
            $docContent .= "<p>This is to certify that <strong>{$info['first_name']} {$info['middle_name']} {$info['last_name']}</strong> (ID: {$info['real_student_id']}) ";
            $docContent .= "graduated from the Department of <strong>{$info['dept_name']}</strong>.</p>";
            $docContent .= "<p>Total Cost Share to be paid: <strong>" . number_format((float) $cost_share_amount, 2) . " Birr</strong></p>";
            $docContent .= "<div class='signatures'>";
            $docContent .= "<div class='sig-block'><img src='{$signature}' style='height:50px;'><br>Transcript Professional</div>";
            $docContent .= "<div class='sig-block'><span style='font-style:italic; color:#777;'>[Pending Registrar Signature]</span><br>Registrar Head</div>";
            $docContent .= "</div>";
            $docContent .= "</div>";
        }

        $stmt = $pdo->prepare("UPDATE official_transcript SET status = 'Pending Registrar Signature', transcript_signature = ?, cost_share_amount = ?, generated_doc_content = ? WHERE id = ?");
        $res = $stmt->execute([$signature, $cost_share_amount, $docContent, $req_id]);

        $_SESSION["flash_success"] = "<span data-en='Document processed, signed, and forwarded to Registrar.' data-am='ሰነዱ ተሰርቷል፣ ተፈርሟል እና ወደ ሬጂስትራር ተላልፏል።'>Document processed, signed, and forwarded to Registrar.</span>";
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit();
    }
}



// Fetch Pending Requests (New Workflow)
$pending_requests = $pdo->query("SELECT dr.*, u.first_name, u.middle_name, u.last_name, s.student_id as real_student_id, d.name as dept_name,
                                 (SELECT SUM(tuition_fee) FROM cost_sharing_agreements ct WHERE ct.student_id = s.user_id) as sum_tuition,
                                 (SELECT SUM(food_expense) FROM cost_sharing_agreements ct WHERE ct.student_id = s.user_id) as sum_food,
                                 (SELECT SUM(bed_expense) FROM cost_sharing_agreements ct WHERE ct.student_id = s.user_id) as sum_bed,
                                 (SELECT SUM(medication_expense) FROM cost_sharing_agreements ct WHERE ct.student_id = s.user_id) as sum_med,
                                 (SELECT SUM(tuition_fee + food_expense + bed_expense + medication_expense) 
                                  FROM cost_sharing_agreements ct 
                                  WHERE ct.student_id = s.user_id) as total_debt
                                 FROM official_transcript dr 
                                 JOIN students s ON dr.student_id = s.user_id 
                                 JOIN users u ON s.user_id = u.id 
                                 LEFT JOIN departments d ON s.department_id = d.id 
                                 WHERE dr.request_type != 'CostSharePaper' AND dr.status = 'Pending Transcript'")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Issue Document - Transcript Pro" data-am="ሰነድ ይስጡ - ትራንስክሪፕት ባለሙያ">Issue Document - Transcript Pro
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
                    <h2 data-en="Issue Original Document / Clearance" data-am="ኦሪጅናል ሰነድ / ክሊራንስ ይስጡ">Issue Original
                        Document / Clearance</h2>
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
        style="display:none; position:fixed; z-index:100; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5);">
        <div class="modal-content"
            style="background-color:#fefefe; margin:3% auto; padding:25px; border:1px solid #888; width:75%; max-width:850px; border-radius:8px;">
            <span class="close" onclick="closeModal()"
                style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
            <h3 data-en="Process Document Request" data-am="የሰነድ ጥያቄን ማስተናገድ">Process Document Request</h3>

            <!-- Document Preview -->
            <div id="docPreview"
                style="border:1px solid #ddd; padding:30px; margin:15px 0; background:#fff; border-radius:6px;"></div>

            <form method="POST" style="margin-top:15px;">
                <input type="hidden" name="req_id" id="modalReqId">
                <input type="hidden" name="process_doc" value="1">
                <input type="hidden" name="cost_share_amount" id="modalAmount">

                <button type="submit" class="btn-success" style="width:100%; padding:12px; font-size:16px;"
                    data-en="Sign & Forward" data-am="ፍረመህ አስተላልፍ">Sign &
                    Forward</button>
            </form>
        </div>
    </div>

    <script>
        function formatNum(n) {
            return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        async function openProcessModal(data) {
            document.getElementById('processModal').style.display = 'block';
            document.getElementById('modalReqId').value = data.id;

            let debt = parseFloat(data.total_debt || 0);
            document.getElementById('modalAmount').value = debt.toFixed(2);
            document.getElementById('docPreview').innerHTML = '<div style="text-align:center; padding:40px; font-weight:bold; color:#1565c0;">Generating Preview...</div>';

            try {
                let response = await fetch(`issue_document.php?ajax_preview=1&req_id=${data.id}`);
                if (response.ok) {
                    document.getElementById('docPreview').innerHTML = await response.text();
                } else {
                    document.getElementById('docPreview').innerHTML = '<p style="color:red;">Failed to preview document.</p>';
                }
            } catch (e) {
                document.getElementById('docPreview').innerHTML = '<p style="color:red;">Error fetching preview.</p>';
            }
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