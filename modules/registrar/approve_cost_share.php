<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['registrar']);

// PRG: Read flash messages from session
$msg = $_SESSION['flash_success'] ?? "";
unset($_SESSION['flash_success']);
$error = "";

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'approve') {
        $paper_id = $_POST['paper_id'];
        $stmt = $pdo->prepare("UPDATE official_transcript SET status = 'Approved', registrar_signature = 'Signed' WHERE id = ?");
        $stmt->execute([$paper_id]);
        $_SESSION['flash_success'] = "<span data-en='Paper approved signed and stamped successfully.' data-am='ወረቀቱ ጸድቋል፣ ተፈርሟል እና ማህተም ተደርጎበታል።'>Paper approved signed and stamped successfully.</span>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } elseif ($action == 'give_paper') {
        $paper_id = $_POST['paper_id'];
        $stmt = $pdo->prepare("UPDATE official_transcript SET status = 'Given' WHERE id = ?");
        $stmt->execute([$paper_id]);
        $_SESSION['flash_success'] = "<span data-en='Cost Share Paper given to student successfully.' data-am='የኮስት ሼሪንግ ወረቀት ለተማሪው በተሳካ ሁኔታ ተሰጥቷል።'>Cost Share Paper given to student successfully.</span>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } elseif ($action == 'reject_paper') {
        $paper_id = $_POST['paper_id'];
        $stmt = $pdo->prepare("UPDATE official_transcript SET status = 'Rejected' WHERE id = ?");
        $stmt->execute([$paper_id]);
        $_SESSION['flash_success'] = "<span data-en='Cost Share Paper rejected successfully.' data-am='የኮስት ሼሪንግ ወረቀት በተሳካ ሁኔታ ውድቅ ተደርጓል።'>Cost Share Paper rejected successfully.</span>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } elseif ($action == 'finalize_doc') {
        $req_id = $_POST['request_id'];

        if (isset($_FILES['signature']) && $_FILES['signature']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $filename = $_FILES['signature']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $new_name = "sig_reg_" . $req_id . "_" . time() . "." . $ext;
                $upload_dir = "../../uploads/signatures/";
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                move_uploaded_file($_FILES['signature']['tmp_name'], $upload_dir . $new_name);

                $stmt = $pdo->prepare("UPDATE official_transcript SET status = 'Delivered', registrar_signature = ? WHERE id = ?");
                $stmt->execute([$new_name, $req_id]);
                $_SESSION['flash_success'] = "<span data-en='Document signed and delivered to student successfully.' data-am='ሰነዱ በተሳካ ሁኔታ ተፈርሞ ለተማሪው ተሰጥቷል።'>Document signed and delivered to student successfully.</span>";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error = "<span data-en='Invalid file type. Only JPG, JPEG, PNG allowed for signature.' data-am='ትክክል ያልሆነ የፋይል አይነት። ለፊርማ JPG፣ JPEG፣ PNG ብቻ ይፈቀዳሉ።'>Invalid file type. Only JPG, JPEG, PNG allowed for signature.</span>";
            }
        } else {
            $error = "<span data-en='Please upload your signature.' data-am='እባክዎ ፊርማዎን ይስቀሉ።'>Please upload your signature.</span>";
        }
    }
}

// Fetch Submissions
$pending_papers = $pdo->query("SELECT csp.*, u.first_name, u.last_name, s.student_id as real_student_id, d.name as dept_name 
                               FROM official_transcript csp 
                               JOIN students s ON csp.student_id = s.user_id 
                               JOIN users u ON s.user_id = u.id 
                               JOIN departments d ON csp.department_id = d.id 
                               WHERE csp.request_type = 'CostSharePaper' AND csp.status = 'Pending'")->fetchAll(PDO::FETCH_ASSOC);

$approved_papers = $pdo->query("SELECT csp.*, u.first_name, u.last_name, s.student_id as real_student_id, d.name as dept_name 
                                FROM official_transcript csp 
                                JOIN students s ON csp.student_id = s.user_id 
                                JOIN users u ON s.user_id = u.id 
                                JOIN departments d ON csp.department_id = d.id 
                                WHERE csp.request_type = 'CostSharePaper' AND csp.status = 'Approved'")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Document Requests (Pending Final Signature)
$signing_docs = $pdo->query("SELECT dr.*, u.first_name, u.last_name, s.student_id as real_student_id, d.name as dept_name 
                             FROM official_transcript dr 
                             JOIN students s ON dr.student_id = s.user_id 
                             JOIN users u ON s.user_id = u.id 
                             LEFT JOIN departments d ON s.department_id = d.id 
                             WHERE dr.request_type != 'CostSharePaper' AND dr.status = 'Pending Registrar Signature'")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Delivered/Finalized Documents (For Printing)
$delivered_docs = $pdo->query("SELECT dr.*, u.first_name, u.last_name, s.student_id as real_student_id, d.name as dept_name 
                             FROM official_transcript dr 
                             JOIN students s ON dr.student_id = s.user_id 
                             JOIN users u ON s.user_id = u.id 
                             LEFT JOIN departments d ON s.department_id = d.id 
                             WHERE dr.request_type != 'CostSharePaper' AND dr.status = 'Delivered'")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Approve Cost Share - Registrar" data-am="የወጪ መጋራትን አጽድቅ - ሬጅስትራር">Approve Cost Share - Registrar
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
                    <h2 data-en="Approve Cost Share Papers" data-am="የወጪ ክፍፍል ሰነዶችን ያፅድቁ">Approve Cost Share Papers</h2>
                </div>

                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>
                <!-- Document Requests (Final Signature) -->
                <div class="card mt-20">
                    <h3 data-en="Ready for Final Signature (From Transcript Pro)"
                        data-am="ለመጨረሻ ፊርማ ዝግጁ (ከትራንስክሪፕት ባለሙያ)">Ready for Final Signature (From Transcript Pro)</h3>
                    <?php if (empty($signing_docs)): ?>
                        <p data-en="No documents waiting for final signature." data-am="ለመጨረሻ ፊርማ የሚጠብቁ ሰነዶች የሉም።">No
                            documents waiting for final signature.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th data-en="Student ID" data-am="የተማሪ መለያ">Student ID</th>
                                    <th data-en="Name" data-am="ስም">Name</th>
                                    <th data-en="Document Type" data-am="የሰነድ ዓይነት">Document Type</th>
                                    <th data-en="Transcript Sig" data-am="የትራንስክሪፕት ፊርማ">Transcript Sig</th>
                                    <th data-en="Action" data-am="ድርጊት">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($signing_docs as $doc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($doc['real_student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($doc['request_type']); ?></td>
                                        <td data-en="Signed" data-am="ተፈርሟል">Signed</td>
                                        <td>
                                            <form method="POST" enctype="multipart/form-data"
                                                style="display:flex; gap:5px; align-items:center;">
                                                <input type="hidden" name="request_id" value="<?php echo $doc['id']; ?>">
                                                <input type="hidden" name="action" value="finalize_doc">
                                                <input type="file" name="signature" required style="width:150px;">
                                                <button type="submit" class="btn-success"
                                                    style="color: #ffffff; padding: 5px; background-color: #000000; font-size: 1.2em;"
                                                    data-en="Sign & Give" data-am="ፈርመህ ስጥ">Sign
                                                    & Give</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <!-- Approved/Delivered Documents (Ready for Print) -->
                <div class="card mt-20">
                    <h3 data-en="Finalized Documents (Signed & Ready to Print)"
                        data-am="የተጠናቀቁ ሰነዶች (የተፈረሙ እና ለማተም ዝግጁ)">Finalized Documents (Signed & Ready to Print)</h3>
                    <?php if (empty($delivered_docs)): ?>
                        <p data-en="No finalized documents available." data-am="ያልተጠናቀቁ ሰነዶች የሉም።">No finalized documents
                            available.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th data-en="Student ID" data-am="የተማሪ መለያ">Student ID</th>
                                    <th data-en="Name" data-am="ስም">Name</th>
                                    <th data-en="Document Type" data-am="የሰነድ ዓይነት">Document Type</th>
                                    <th data-en="Status" data-am="ሁኔታ">Status</th>
                                    <th data-en="Action" data-am="ድርጊት">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($delivered_docs as $doc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($doc['real_student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($doc['request_type']); ?></td>
                                        <td><span class="status-badge status-active" data-en="Delivered"
                                                data-am="ተሰጥቷል">Delivered</span></td>
                                        <td>
                                            <a href="print_document.php?id=<?php echo $doc['id']; ?>" target="_blank"
                                                class="btn-primary"
                                                style="background-color:#007bff; color:white; padding:10px 15px; text-decoration:none; display:inline-block;"
                                                data-en="Print Document" data-am="ሰነድ ያትሙ">
                                                <i class="fas fa-print"></i> Print Document
                                            </a>
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
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>