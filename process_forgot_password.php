<?php
session_start();
require_once 'config/db_connect.php';

// Adjust the paths based on how PHPMailer is structured in the includes folder.
require_once 'includes/PHPMailer/src/Exception.php';
require_once 'includes/PHPMailer/src/PHPMailer.php';
require_once 'includes/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email']) && isset($_POST['username'])) {
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);

    // Check if user exists with BOTH username AND email matching
    $stmt = $pdo->prepare("SELECT id, first_name FROM users WHERE username = ? AND email = ?");
    $stmt->execute([$username, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $user_id = $user['id'];
        // Generate Token
        $token = bin2hex(random_bytes(32));
        $expires_at = date("Y-m-d H:i:s", strtotime('+10 minutes'));

        // Save token to database
        $stmt_token = $pdo->prepare("INSERT INTO password_resets (user_id, reset_token, expires_at) VALUES (?, ?, ?)");
        if ($stmt_token->execute([$user_id, $token, $expires_at])) {
            
            // Construct Reset Link
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $reset_link = $protocol . "://" . $host . "/Cost_share/reset_password.php?token=" . $token;

            require_once 'includes/mailer.php';
            
            $subject = 'Password Reset Request';
            $htmlBody = "Hi " . htmlspecialchars($user['first_name']) . ",<br><br>
                              You recently requested to reset your password for your DMU Cost Sharing account. 
                              Click the link below to reset it. This link is valid for 10 minutes.<br><br>
                              <a href='" . $reset_link . "'>Reset Password</a><br><br>
                              If you did not request a password reset, please ignore this email.<br><br>
                              Thanks,<br>DMU System Admin";

            $mail_sent = sendSystemEmail($email, $user['first_name'], $subject, $htmlBody);

            if ($mail_sent) {
                $_SESSION['reset_success'] = "<span data-en='A reset link has been sent to your email.' data-am='የይለፍ ቃል መቀየሪያ ሊንክ ወደ ኢሜልዎ ተልኳል።'>A reset link has been sent to your email.</span>";
            } else {
                // For demo/development purpose, if sending fails,
                // we'll output the link directly so you can test it without configuring SMTP.
                $_SESSION['reset_error'] = "Message could not be sent. Please check SMTP configuration. <br><br><b>Local Test Link:</b> <a href='$reset_link' style='color:#000;'>Click here to reset (Dev mode)</a>";
            }
        } else {
            $_SESSION['reset_error'] = "<span data-en='Failed to generate token. Please try again.' data-am='ቶከን ማመንጨት አልተሳካም። እባክዎ እንደገና ይሞክሩ።'>Failed to generate token. Please try again.</span>";
        }
    } else {
        // Security: Don't reveal whether username or email was wrong
        $_SESSION['reset_error'] = "<span data-en='The username and email you entered do not match any account in our system.' data-am='ያስገቡት የተጠቃሚ ስም እና ኢሜል ከስርዓታችን ውስጥ ካሉ መረጃዎች ጋር አይዛመዱም።'>The username and email you entered do not match any account in our system.</span>";
    }

    header("Location: forgot_password.php");
    exit();
} else {
    header("Location: index.php");
    exit();
}
?>
