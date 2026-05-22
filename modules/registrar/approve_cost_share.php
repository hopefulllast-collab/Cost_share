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

        $stmt = $pdo->prepare("SELECT digital_signature FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $signature = $stmt->fetchColumn();

        if (empty($signature)) {
            $error = "<span data-en='You have not set up your digital signature. Please update your profile.' data-am='የዲጂታል ፊርማዎን አላዘጋጁም። እባክዎ ፕሮፋይልዎን ያዘምኑ።'>You have not set up your digital signature. Please <a href=\"../common/update_profile.php\">update your profile</a>.</span>";
        } else {
            $stmt = $pdo->prepare("UPDATE official_transcript SET status = 'Delivered', registrar_signature = ? WHERE id = ?");
            if ($stmt->execute([$signature, $req_id])) {
                $_SESSION['flash_success'] = "<span data-en='Document signed and delivered to student successfully.' data-am='ሰነዱ በተሳካ ሁኔታ ተፈርሞ ለተማሪው ተሰጥቷል።'>Document signed and delivered to student successfully.</span>";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $error = "<span data-en='Failed to sign document.' data-am='ሰነዱን መፈረም አልተቻለም።'>Failed to sign document.</span>";
            }
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
                <div class="card mt-20" style="text-align:center;">
                    <h3 data-en="Ready for Final Signature (From Transcript Pro)"
                        data-am="ለመጨረሻ ፊርማ ዝግጁ (ከትራንስክሪፕት ባለሙያ)">Ready for Final Signature (From Transcript Pro)</h3>
                    <?php if (empty($signing_docs)): ?>
                        <p style="color:#888; padding:20px 0;" data-en="No documents waiting for final signature."
                            data-am="ለመጨረሻ ፊርማ የሚጠብቁ ሰነዶች የሉም።">No
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
                                            <a href="preview_and_sign.php?id=<?php echo $doc['id']; ?>" class="btn-success"
                                                style="color: #ffffff; padding: 6px 12px; background-color: #000000; font-size: 1.1em; text-decoration:none; display:inline-block;"
                                                data-en="Review & Sign" data-am="ገምግመህ ፈርም">
                                                <i class="fas fa-search-plus"></i> <span data-en="Review & Sign"
                                                    data-am="ገምግመህ ፈርም">Review & Sign</span>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <!-- Approved/Delivered Documents (Ready for Print) -->
                <div class="card mt-20" style="text-align:center;">
                    <h3 data-en="Finalized Documents (Signed & Ready to Print)"
                        data-am="የተጠናቀቁ ሰነዶች (የተፈረሙ እና ለማተም ዝግጁ)">Finalized Documents (Signed & Ready to Print)</h3>
                    <?php if (empty($delivered_docs)): ?>
                        <p style="color:#888; padding:20px 0;" data-en="No finalized documents available."
                            data-am="ያልተጠናቀቁ ሰነዶች የሉም።">No finalized documents
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