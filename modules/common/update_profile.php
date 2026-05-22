<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';

// Ensure user is logged in (auth_check usually handles this, but good to be safe)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

$signature_roles = ['cost_sharing_pro', 'registrar', 'department_head', 'transcript_pro', 'student'];
$show_signature = in_array($_SESSION['role'] ?? '', $signature_roles);

if ($show_signature) {
    $stmt = $pdo->prepare("SELECT digital_signature FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_signature = $stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_signature'])) {
    $sig_data = trim($_POST['signature_data'] ?? '');
    if (empty($sig_data)) {
        $error = "<span data-en='Please draw or upload a signature.' data-am='እባክዎ ፊርማ ይሳሉ ወይም ይጫኑ።'>Please draw or upload a signature.</span>";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET digital_signature = ? WHERE id = ?");
            if ($stmt->execute([$sig_data, $_SESSION['user_id']])) {
                $_SESSION["flash_success"] = "<span data-en='Signature saved successfully.' data-am='ፊርማው በተሳካ ሁኔታ ተቀምጧል።'>Signature saved successfully.</span>";
                header("Location: " . $_SERVER["PHP_SELF"]);
                exit();
            } else {
                $error = "<span data-en='Failed to save signature.' data-am='ፊርማውን ማስቀመጥ አልተቻለም።'>Failed to save signature.</span>";
            }
        } catch (Exception $e) {
            $error = "<span data-en='Error saving signature.' data-am='ፊርማውን በማስቀመጥ ላይ ስህተት።'>Error saving signature.</span>";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    $user_id = $_SESSION['user_id'];

    if (empty($new_pass) || empty($confirm_pass)) {
        $error = "<span data-en='Please fill all fields.' data-am='እባክዎ ሁሉንም ቦታዎች ይሙሉ።'>Please fill all fields.</span>";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "<span data-en='Passwords do not match.' data-am='የይለፍ ቃሎች አይዛመዱም።'>Passwords do not match.</span>";
    } elseif (strlen($new_pass) < 4) {
        $error = "<span data-en='Password must be at least 4 characters.' data-am='የይለፍ ቃል ቢያንስ 4 ቁምፊዎች መሆን አለበት።'>Password must be at least 4 characters.</span>";
    } else {
        try {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed, $user_id])) {
                $_SESSION["flash_success"] = "<span data-en='Successfully updated account.' data-am='መለያው በተሳካ ሁኔታ ተዘምኗል'>Successfully updated account.</span>";
                header("Location: " . $_SERVER["PHP_SELF"]);
                exit();
            } else {
                $error = "<span data-en='Failed to update password.' data-am='የይለፍ ቃል ማዘመን አልተቻለም።'>Failed to update password.</span>";
            }
        } catch (Exception $e) {
            $error = "<span data-en='Error updating password.' data-am='የይለፍ ቃል ማዘመን ላይ ስህተት።'>Error updating password.</span>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Update Account - DMU" data-am="መለያ አዘምን - DMU">Update Account - DMU</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .main-content .card {
            text-align: center;
        }
        .main-content .card .form-group {
            text-align: left;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <?php include '../../includes/main_header.php'; ?>
        <div class="layout-body">
            <?php include '../../includes/sidebar.php'; ?>
            <div class="main-content">
                <div class="content-centered">
                <div class="top-bar" style="text-align:center;">
                    <h2 data-en="Update Account" data-am="መለያ አዘምን">Update Account</h2>
                </div>

                <?php if ($msg): ?>
                    <div class="success-msg" style="color: green; border: 1px solid green; background: #e8f5e9;">
                        <?php echo $msg; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="error-msg"
                        style="color: red; border: 1px solid red; background: #ffebee; padding: 10px; border-radius: 6px; margin-bottom: 20px;">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <form method="POST">
                        <div class="form-group">
                            <label data-en="New Password" data-am="አዲስ የይለፍ ቃል">New Password</label>
                            <input type="password" name="new_password" required minlength="4"
                                placeholder="Enter new password" data-en="Enter new password"
                                data-en-placeholder="Enter new password" data-am-placeholder="አዲስ የይለፍ ቃል ያስገቡ">
                        </div>
                        <div class="form-group">
                            <label data-en="Confirm Password" data-am="የይለፍ ቃል ያረጋግጡ">Confirm Password</label>
                            <input type="password" name="confirm_password" required minlength="4"
                                placeholder="Confirm new password" data-en="Confirm new password"
                                data-en-placeholder="Confirm new password" data-am-placeholder="አዲሱን የይለፍ ቃል ያረጋግጡ">
                        </div>
                        <button type="submit" name="update_password" class="btn-primary" data-en="Update Account"
                            data-am="መለያ አዘምን">Update Account</button>
                    </form>
                </div>

                <?php if ($show_signature): ?>
                <div class="card" style="margin-top: 20px;">
                    <h3 data-en="Update Digital Signature" data-am="ዲጂታል ፊርማ አዘምን">Update Digital Signature</h3>
                    
                    <?php if (!empty($current_signature)): ?>
                        <div style="margin-bottom: 15px; padding: 10px; border: 1px dashed #ccc; background: #fafafa; text-align: center;">
                            <p data-en="Current Signature:" data-am="የአሁኑ ፊርማ:" style="margin-bottom:10px; color:#555;">Current Signature:</p>
                            <img src="<?php echo htmlspecialchars($current_signature); ?>" style="max-height: 100px; max-width: 100%;">
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="signatureForm">
                        <div class="signature-options" style="margin-bottom: 15px;">
                            <label><input type="radio" name="sig_type" value="draw" checked> <span data-en="Draw Signature" data-am="ፊርማ ይሳሉ">Draw</span></label>
                            <label style="margin-left: 15px;"><input type="radio" name="sig_type" value="upload"> <span data-en="Upload Image" data-am="ምስል ይጫኑ">Upload</span></label>
                        </div>

                        <div id="drawSection">
                            <div style="border: 1px solid #ccc; background: #fff; width: 100%; height: 150px; position: relative;">
                                <canvas id="sigCanvas" style="width: 100%; height: 100%; touch-action: none;"></canvas>
                            </div>
                            <button type="button" class="btn-secondary" id="clearBtn" style="margin-top: 5px; padding: 5px 10px; font-size: 12px;">
                                <i class="fas fa-eraser"></i> <span data-en="Clear" data-am="አጥፋ">Clear</span>
                            </button>
                        </div>

                        <div id="uploadSection" style="display: none;">
                            <input type="file" id="sigUpload" accept="image/*" class="inline-input">
                            <p style="font-size: 12px; color: #666; margin-top: 5px;" data-en="Upload a clear photo of your signature." data-am="ጥርት ያለ የፊርማዎን ፎቶ ይጫኑ።">Upload a clear photo of your signature.</p>
                        </div>

                        <input type="hidden" name="signature_data" id="signatureData">
                        <button type="submit" name="update_signature" id="saveSigBtn" class="btn-primary" style="margin-top: 15px;" data-en="Save Signature" data-am="ፊርማ አስቀምጥ">Save Signature</button>
                    </form>
                </div>
                <?php endif; ?>

                </div><!-- .content-centered -->
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script>
    <?php if ($show_signature): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const drawRadio = document.querySelector('input[value="draw"]');
        const uploadRadio = document.querySelector('input[value="upload"]');
        const drawSection = document.getElementById('drawSection');
        const uploadSection = document.getElementById('uploadSection');
        const uploadInput = document.getElementById('sigUpload');
        const sigForm = document.getElementById('signatureForm');
        const sigDataInput = document.getElementById('signatureData');
        const canvas = document.getElementById('sigCanvas');
        if(!canvas) return; // safety
        
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        let hasDrawn = false;
        let uploadBase64 = '';

        function resizeCanvas() {
            const rect = canvas.parentElement.getBoundingClientRect();
            canvas.width = rect.width;
            canvas.height = rect.height;
            ctx.fillStyle = "#fff";
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        function getPos(e) {
            const rect = canvas.getBoundingClientRect();
            if (e.touches && e.touches.length > 0) {
                return { x: e.touches[0].clientX - rect.left, y: e.touches[0].clientY - rect.top };
            }
            return { x: e.clientX - rect.left, y: e.clientY - rect.top };
        }

        function startPosition(e) {
            isDrawing = true;
            hasDrawn = true;
            const pos = getPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            e.preventDefault();
        }

        function endPosition() {
            isDrawing = false;
            ctx.beginPath();
        }

        function draw(e) {
            if (!isDrawing) return;
            const pos = getPos(e);
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#000';
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            e.preventDefault();
        }

        canvas.addEventListener('mousedown', startPosition);
        canvas.addEventListener('mouseup', endPosition);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('touchstart', startPosition, {passive: false});
        canvas.addEventListener('touchend', endPosition);
        canvas.addEventListener('touchmove', draw, {passive: false});

        document.getElementById('clearBtn').addEventListener('click', function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = "#fff";
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            hasDrawn = false;
        });

        drawRadio.addEventListener('change', () => { drawSection.style.display = 'block'; uploadSection.style.display = 'none'; });
        uploadRadio.addEventListener('change', () => { drawSection.style.display = 'none'; uploadSection.style.display = 'block'; });

        uploadInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    uploadBase64 = evt.target.result;
                }
                reader.readAsDataURL(file);
            }
        });

        sigForm.addEventListener('submit', function(e) {
            if (drawRadio.checked) {
                if (!hasDrawn) {
                    e.preventDefault();
                    alert(localStorage.getItem('dmu_lang') === 'am' ? 'እባክዎ ፊርማዎን ይሳሉ።' : 'Please draw your signature.');
                } else {
                    sigDataInput.value = canvas.toDataURL('image/png');
                }
            } else {
                if (!uploadBase64) {
                    e.preventDefault();
                    alert(localStorage.getItem('dmu_lang') === 'am' ? 'እባክዎ የፊርማዎን ምስል ይጫኑ።' : 'Please upload an image of your signature.');
                } else {
                    sigDataInput.value = uploadBase64;
                }
            }
        });
    });
    <?php endif; ?>
    </script>
    <script src="../../assets/js/bilingual.js"></script>
</body>
</html>
