<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['registrar']);

require_once '../../includes/academic_translations.php';

$id = $_GET['id'] ?? 0;

// Fetch Document info
$stmt = $pdo->prepare("SELECT dr.*, u.first_name, u.middle_name, u.last_name, s.student_id as real_student_id, d.name as dept_name 
                      FROM official_transcript dr 
                      JOIN students s ON dr.student_id = s.user_id 
                      JOIN users u ON s.user_id = u.id 
                      LEFT JOIN departments d ON s.department_id = d.id 
                      WHERE dr.id = ? AND dr.request_type != 'CostSharePaper' AND dr.status = 'Pending Registrar Signature'");
$stmt->execute([$id]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    die("Invalid request.");
}

$registrar_name = $_SESSION['name'] ?? 'Registrar';
$error = '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type'])) {
    $action_type = $_POST['action_type'];

    if ($action_type == 'finalize_doc') {
        $stmt = $pdo->prepare("SELECT digital_signature FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $signature = $stmt->fetchColumn();

        if (empty($signature)) {
            $error = "<span data-en='You have not set up your digital signature. Please update your profile.' data-am='የዲጂታል ፊርማዎን አላዘጋጁም። እባክዎ ፕሮፋይልዎን ያዘምኑ።'>You have not set up your digital signature.</span>";
        } else {
            $stmt = $pdo->prepare("UPDATE official_transcript SET status = 'Delivered', registrar_signature = ? WHERE id = ?");
            if ($stmt->execute([$signature, $id])) {
                $_SESSION['flash_success'] = "<span data-en='Document signed and delivered to student successfully.' data-am='ሰነዱ በተሳካ ሁኔታ ተፈርሞ ለተማሪው ተሰጥቷል።'>Document signed and delivered to student successfully.</span>";
                header("Location: approve_cost_share.php");
                exit();
            } else {
                $error = "<span data-en='Failed to sign document.' data-am='ሰነዱን መፈረም አልተቻለም።'>Failed to sign document.</span>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Review Final Document" data-am="የመጨረሻ ሰነድ ይገምግሙ">Review Final Document</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .process-layout {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }

        .file-preview {
            flex: 2;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            min-height: 500px;
        }

        .action-panel {
            flex: 1;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .action-box {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .action-box h4 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
            margin-bottom: 15px;
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
                    <h2 data-en="Preview & Sign Final Document" data-am="የመጨረሻ ሰነድ ይገምግሙና ይፈርሙ">Preview & Sign Final
                        Document</h2>
                    <a href="approve_cost_share.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> <span
                            data-en="Back" data-am="ተመለስ">Back</span></a>
                </div>

                <?php if ($msg)
                    echo "<div class='success-msg'>$msg</div>"; ?>
                <?php if ($error)
                    echo "<div class='error-msg'>$error</div>"; ?>

                <div class="card">
                    <h3 style="margin-top:0;">
                        <span data-en="Student:" data-am="ተማሪ፡">Student:</span>
                        <?php echo htmlspecialchars($doc['first_name'] . ' ' . trim($doc['middle_name'] . ' ' . $doc['last_name'])); ?>
                        (
                        <?php echo htmlspecialchars($doc['real_student_id']); ?>)
                    </h3>
                    <p style="margin-bottom: 0; color: #555;">
                        <span data-en="Department:" data-am="ትምህርት ክፍል፡">Department:</span>
                        <?php echo htmlspecialchars($doc['dept_name']); ?>
                    </p>
                </div>

                <div class="process-layout">
                    <!-- Left: Preview -->
                    <div class="file-preview">
                        <h4 data-en="Current Document Preview" data-am="የሰነዱ እይታ">Current Document Preview</h4>
                        <div
                            style="border:1px solid #eee; height: 600px; overflow: hidden; background: #fafafa; display: flex; align-items: center; justify-content: center;">
                            <?php
                            $preview_url = "print_document.php?id=" . $id;
                            echo "<iframe src='{$preview_url}' style='width:100%; height:100%; border:none;'></iframe>";
                            ?>
                        </div>
                    </div>

                    <!-- Right: Actions -->
                    <div class="action-panel">
                        <!-- Final Step: Forward -->
                        <div class="action-box" style="background:#f8f9fa; border-color:#0056b3;">
                            <h4 style="color:#0056b3; border-bottom-color:#b8daff;" data-en="Final Signature"
                                data-am="የመጨረሻ ፊርማ">Final Signature</h4>
                            <p style="font-size:13px; color:#333;"
                                data-en="Review the generated document. If everything is correct, click the button below to append your digital signature and deliver it to the student."
                                data-am="የተዘጋጀውን ሰነድ ይገምግሙ። ሁሉም ነገር ትክክል ከሆነ፣ የዲጂታል ፊርማዎን ለማስቀመጥ እና ለተማሪው ለማድረስ ከዚህ በታች ያለውን ቁልፍ ይጫኑ።">
                                Review the generated document. If everything is correct, click the button below to
                                append your digital signature and deliver it to the student.
                            </p>
                            <form method="POST">
                                <input type="hidden" name="action_type" value="finalize_doc">
                                <button type="submit" class="btn-primary"
                                    style="width:100%; margin-top:5px; background-color:#28a745; border:none; padding:10px; font-size:16px;">
                                    <i class="fas fa-file-signature"></i> <span data-en="Sign & Deliver Document"
                                        data-am="ሰነዱን ፈርመህ አድርስ">Sign & Deliver Document</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>