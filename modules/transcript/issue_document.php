<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['transcript_pro']);

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
        $stmt = $pdo->prepare("SELECT dr.*, u.first_name, u.middle_name, u.last_name, s.student_id as real_student_id, d.name as dept_name, s.academic_year, s.batch, s.current_semester 
                               FROM official_transcript dr 
                               JOIN students s ON dr.student_id = s.user_id 
                               JOIN users u ON s.user_id = u.id 
                               LEFT JOIN departments d ON s.department_id = d.id 
                               WHERE dr.id = ?");
        $stmt->execute([$req_id]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        $docContent = "<div class='generated-doc'>";
        $docContent .= "<div class='header'><img src='../../assets/img/logo.png' alt='Logo' style='height:80px;'><h2>Debre Markos University</h2></div>";
        $docContent .= "<p>Date: " . date('d/m/Y') . "</p>";
        $docContent .= "<p>To: Whom It May Concern</p>";
        $docContent .= "<p>This is to certify that <strong>{$info['first_name']} {$info['middle_name']} {$info['last_name']}</strong> (ID: {$info['real_student_id']}) ";
        $docContent .= "graduated from the Department of <strong>{$info['dept_name']}</strong>.</p>";

        if ($info['request_type'] == 'Graduation' || $info['request_type'] == 'original') {
            $docContent .= "<p>Cumulative GPA: <strong>[CGPA_PLACEHOLDER]</strong></p>"; // We don't have CGPA column yet, generic placeholder
            $docContent .= "<p>Total Cost Share to be paid: <strong>" . number_format((float)$cost_share_amount, 2) . " Birr</strong></p>";
        } else {
            $docContent .= "<p>This original degree is issued free of outstanding cost share debts.</p>";
        }

        $docContent .= "<div class='signatures'>";
        // Using the base64 signature from DB directly instead of file path
        $docContent .= "<div class='sig-block'><img src='{$signature}' style='height:50px;'><br>Transcript Professional</div>";
        $docContent .= "<div class='sig-block'>[Registrar Signature Pending]<br>Registrar Head</div>";
        $docContent .= "</div>";
        $docContent .= "</div>";

        $stmt = $pdo->prepare("UPDATE official_transcript SET status = 'Pending Registrar Signature', transcript_signature = ?, cost_share_amount = ?, generated_doc_content = ? WHERE id = ?");
        $res = $stmt->execute([$signature, $cost_share_amount, $docContent, $req_id]);

        $_SESSION["flash_success"] = "<span data-en='Document processed, signed, and forwarded to Registrar.' data-am='ሰነዱ ተሰርቷል፣ ተፈርሟል እና ወደ ሬጂስትራር ተላልፏል።'>Document processed, signed, and forwarded to Registrar.</span>";
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
        style="display:none; position:fixed; z-index:100; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgb(0,0,0); background-color:rgba(0,0,0,0.4);">
        <div class="modal-content"
            style="background-color:#fefefe; margin:15% auto; padding:20px; border:1px solid #888; width:50%;">
            <span class="close" onclick="closeModal()"
                style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
            <h3 data-en="Process Document Request" data-am="የሰነድ ጥያቄን ማስተናገድ">Process Document Request</h3>
            <div id="modalContent"></div>
            <form method="POST" style="margin-top:20px;">
                <input type="hidden" name="req_id" id="modalReqId">
                <input type="hidden" name="process_doc" value="1">

                <p><strong data-en="Student:" data-am="ተማሪ:">Student:</strong> <span id="modalStudentName"></span></p>
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