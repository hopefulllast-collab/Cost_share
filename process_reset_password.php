<?php
session_start();
require_once 'config/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['token']) && isset($_POST['new_password'])) {
    $token = $_POST['token'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $_SESSION['reset_error'] = "<span data-en='Passwords do not match.' data-am='የገቡት የይለፍ ቃላት አይመሳሰሉም።'>Passwords do not match.</span>";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit();
    }

    // Verify token one more time
    $stmt = $pdo->prepare("SELECT user_id, expires_at FROM password_resets WHERE reset_token = ?");
    $stmt->execute([$token]);
    $reset_record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($reset_record) {
        $expires_at = new DateTime($reset_record['expires_at']);
        $now = new DateTime();

        if ($now < $expires_at) {
            $user_id = $reset_record['user_id'];
            
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            try {
                $pdo->beginTransaction();

                // Update password
                $stmt_update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_update->execute([$hashed_password, $user_id]);

                // Delete all tokens for this user
                $stmt_delete = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $stmt_delete->execute([$user_id]);

                // Log audit (optional but good practice)
                require_once 'includes/audit_logger.php';
                logAudit($pdo, 'PASSWORD_RESET', 'User ID ' . $user_id . ' completed password reset process.');

                $pdo->commit();

                // Setting flash_success matches index.php session variables if any, otherwise just pass it.
                // Or simply redirect with success param.
                $_SESSION['forgot_pw_success'] = "<span data-en='Password updated successfully. You can now log in.' data-am='የይለፍ ቃልዎ በተሳካ ሁኔታ ተቀይሯል። አሁን መግባት ይችላሉ።'>Password updated successfully. You can now log in.</span>";
                header("Location: index.php?reset=success");
                exit();

            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['reset_error'] = "<span data-en='System error. Please try again later.' data-am='የስርዓት ስህተት። እባክዎ ትንሽ ቆይተው እንደገና ይሞክሩ።'>System error. Please try again later.</span>";
                header("Location: reset_password.php?token=" . urlencode($token));
                exit();
            }
        } else {
            $_SESSION['reset_error'] = "<span data-en='Token has expired.' data-am='የሊንኩ ጊዜ አልፎበታል።'>Token has expired.</span>";
            header("Location: reset_password.php?token=" . urlencode($token));
            exit();
        }
    } else {
         $_SESSION['reset_error'] = "<span data-en='Invalid token.' data-am='የተሳሳተ ቶከን።'>Invalid token.</span>";
         header("Location: reset_password.php?token=" . urlencode($token));
         exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>
