<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db_connect.php';
checkAuth(['admin']);

// PRG: Read flash messages from session
$msg = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);
$error = "";

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
                $_SESSION["flash_success"] = "<span data-en='Password updated successfully.' data-am='የይለፍ ቃል በተሳካ ሁኔታ ተዘምኗል።'>Password updated successfully.</span>";
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
    <title data-en="Update My Password - Admin" data-am="የይለፍ ቃል ማደሻ - አስተዳዳሪ">Update My Password - Admin</title>
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
                    <h2 data-en="Update My Password" data-am="የእኔን የይለፍ ቃል አዘምን">Update My Password</h2>
                </div>

                <?php if ($msg): ?>
                    <div class="success-msg"><?php echo $msg; ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="error-msg"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="card" style="max-width: 500px;">
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
                        <button type="submit" name="update_password" class="btn-primary" data-en="Update Password"
                            data-am="የይለፍ ቃል ያዘምኑ" style="background-color: #000000; color: #ffffff;
                            text-decoration: none; padding: 10px 20px; border-radius: 5px;">
                            <span data-en="Update Password" data-am="የይለፍ ቃል ያዘምኑ">Update Password</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
    <script src="../../assets/js/bilingual.js"></script>
</body>

</html>