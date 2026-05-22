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
                      WHERE dr.id = ? AND dr.request_type = 'Transfer-Out'");
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

    if ($action_type == 'upload_stamp') {
        if (isset($_FILES['stamped_file']) && $_FILES['stamped_file']['error'] == 0) {
            $target_dir = "../../uploads/clearances/";
            if (!file_exists($target_dir))
                mkdir($target_dir, 0777, true);

            $file_ext = strtolower(pathinfo($_FILES["stamped_file"]["name"], PATHINFO_EXTENSION));
            $new_name = "signed_" . $id . "_" . time() . "." . $file_ext;
            $target_file = $target_dir . $new_name;

            if (move_uploaded_file($_FILES["stamped_file"]["tmp_name"], $target_file)) {
                $update = $pdo->prepare("UPDATE official_transcript SET clearance_file = ? WHERE id = ?");
                $update->execute([$new_name, $id]);
                $msg = "<span data-en='Manually stamped file uploaded successfully.' data-am='እራስዎ ማህተም ያደረጉበት ፋይል በተሳካ ሁኔታ ተጭኗል።'>Manually stamped file uploaded successfully.</span>";
                $doc['clearance_file'] = $new_name;
            } else {
                $error = "Failed to upload file.";
            }
        } else {
            $error = "Please choose a file to upload.";
        }
    } elseif ($action_type == 'forward') {
        // Forwarding from Registrar sends it to Cost Sharing Pro
        if (strpos($doc['clearance_file'], 'signed_') !== 0) {
            $error = "<span data-en='You must upload a stamped file before forwarding.' data-am='ከማስተላለፍዎ በፊት ማህተም ያለበትን ፋይል መጫን አለቦት።'>You must upload a stamped file before forwarding.</span>";
        } else {
            $stmt = $pdo->prepare("UPDATE official_transcript SET status = 'Pending Cost Share Pro' WHERE id = ?");
            if ($stmt->execute([$id])) {
                $_SESSION['flash_success'] = "<span data-en='Transfer-Out request forwarded to Cost Sharing Professional.' data-am='የዝውውር ጥያቄ ወደ ወጪ መጋራት ባለሙያ ተላልፏል።'>Transfer-Out request forwarded to Cost Sharing Professional.</span>";
                header("Location: order.php");
                exit();
            } else {
                $error = "Failed to forward request.";
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
    <title data-en="Process Transfer-Out Document" data-am="የግቢ ዝውውር ሰነድ ማዘጋጀት">Process Transfer-Out Document</title>
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
                    <h2 data-en="Transfer-Out Document Processing" data-am="የግቢ ዝውውር ሰነድ ማዘጋጀት">Transfer-Out Document
                        Processing</h2>
                    <a href="order.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> <span data-en="Back"
                            data-am="ተመለስ">Back</span></a>
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
                            $filepath = "../../uploads/clearances/" . $doc['clearance_file'];
                            $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

                            if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
                                echo "<img src='{$filepath}' style='max-width:100%; max-height:100%; object-fit:contain;' />";
                            } else if ($ext === 'html') {
                                echo "<iframe src='{$filepath}' style='width:100%; height:100%; border:none;'></iframe>";
                            } else {
                                echo "<embed src='{$filepath}' type='application/pdf' style='width:100%; height:100%;' />";
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Right: Actions -->
                    <div class="action-panel">
                        <!-- Option 1: Download Original -->
                        <div class="action-box">
                            <h4 data-en="1. Download File" data-am="1. ፋይሉን ያውርዱ">1. Download File</h4>
                            <p style="font-size:13px; color:#666;"
                                data-en="Download the document if you wish to sign it externally."
                                data-am="ውጭ ባለ መንገድ ማህተም ለማድረግ ከፈለጉ ፋይሉን ያውርዱ።">Download the document if you wish to
                                sign it externally.</p>
                            <a href="<?php echo $filepath; ?>" download class="btn-secondary"
                                style="display:block; text-align:center; text-decoration:none;">
                                <i class="fas fa-download"></i> <span data-en="Download" data-am="ያውርዱ">Download</span>
                            </a>
                        </div>

                        <!-- Option 2: Upload Stamped -->
                        <div class="action-box">
                            <h4 data-en="2. Upload Stamped File" data-am="2. ማህተም ያለበትን ፋይል ይጫኑ">2. Upload Stamped File
                            </h4>
                            <p style="font-size:13px; color:#666;" data-en="Upload the manually stamped file here."
                                data-am="እራስዎ ያህተሙበትን ፋይል እዚህ ይጫኑ።">Upload the manually stamped file here.</p>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action_type" value="upload_stamp">
                                <input type="file" name="stamped_file" required
                                    style="width:100%; margin-bottom:10px; border:1px solid #ccc; padding:5px; border-radius:4px;">
                                <button type="submit" class="btn-primary"
                                    style="width:100%; background-color:#28a745; border:none;">
                                    <i class="fas fa-upload"></i> <span data-en="Upload" data-am="ጫን">Upload</span>
                                </button>
                            </form>
                        </div>

                        <!-- Final Step: Forward -->
                        <div class="action-box" style="background:#f8f9fa; border-color:#0056b3;">
                            <h4 style="color:#0056b3; border-bottom-color:#b8daff;" data-en="Final Step: Forward"
                                data-am="የመጨረሻ ደረጃ፡ ማስተላለፍ">Final Step: Forward</h4>
                            <p style="font-size:13px; color:#333;"
                                data-en="Forward the finalized document to the Cost Sharing Professional."
                                data-am="የተጠናቀቀውን ሰነድ ወደ ወጪ መጋራት ባለሙያ ያስተላልፉ።">Forward the finalized document to the
                                Cost Sharing Professional.</p>
                            <form method="POST">
                                <input type="hidden" name="action_type" value="forward">
                                <?php if (strpos($doc['clearance_file'], 'signed_') !== 0): ?>
                                    <button type="button" class="btn-primary"
                                        style="width:100%; margin-top:5px; background:#999; border-color:#999; cursor:not-allowed;"
                                        title="Please upload a stamped file first">
                                        <i class="fas fa-lock"></i> <span data-en="Upload Required to Forward"
                                            data-am="ለማለፍ መጫን ግዴታ ነው">Upload Required to Forward</span>
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="btn-primary" style="width:100%; margin-top:5px;">
                                        <i class="fas fa-share"></i> <span data-en="Forward to Cost Sharing Pro"
                                            data-am="ወደ ወጪ መጋራት ክፍል ላክ">Forward to Cost Sharing Pro</span>
                                    </button>
                                <?php endif; ?>
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