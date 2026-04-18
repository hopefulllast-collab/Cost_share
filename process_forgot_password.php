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

        // --- Rate Limiting: Max 2 reset requests per 24 hours, with 12-hour gap ---
        $max_resets_per_day = 2;
        $min_gap_hours = 12;

        // Count resets in last 24 hours
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt_count->execute([$user_id]);
        $reset_count = (int) $stmt_count->fetchColumn();

        // Check last reset time for 12-hour gap
        $stmt_last = $pdo->prepare("SELECT created_at FROM password_resets WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt_last->execute([$user_id]);
        $last_reset = $stmt_last->fetch(PDO::FETCH_ASSOC);

        if ($reset_count >= $max_resets_per_day) {
            $_SESSION['reset_error'] = "<span data-en='You have reached the maximum of 2 password reset requests in 24 hours. Please try again later.' data-am='በ24 ሰዓት ውስጥ ከ2 ጊዜ በላይ የይለፍ ቃል መቀየሪያ ጥያቄ አቅርበዋል። እባክዎ ቆይተው እንደገና ይሞክሩ።'>You have reached the maximum of 2 password reset requests in 24 hours. Please try again later.</span>";
            header("Location: forgot_password.php");
            exit();
        }

        if ($last_reset) {
            $last_time = new DateTime($last_reset['created_at']);
            $now = new DateTime();
            $diff_hours = ($now->getTimestamp() - $last_time->getTimestamp()) / 3600;
            if ($diff_hours < $min_gap_hours) {
                $wait_hours = ceil($min_gap_hours - $diff_hours);
                $_SESSION['reset_error'] = "<span data-en='Please wait at least 12 hours between password reset requests. Try again in about {$wait_hours} hour(s).' data-am='በየ12 ሰዓት ልዩነት ብቻ የይለፍ ቃል መቀየሪያ መጠየቅ ይቻላል። ከ{$wait_hours} ሰዓት(ዎች) በኋላ እንደገና ይሞክሩ።'>Please wait at least 12 hours between password reset requests. Try again in about {$wait_hours} hour(s).</span>";
                header("Location: forgot_password.php");
                exit();
            }
        }

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

            $subject = 'Password Reset Request | የይለፍ ቃል መቀየሪያ ጥያቄ - DMU';
            $firstName = htmlspecialchars($user['first_name']);
            $htmlBody = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;'>
                    <!-- Header -->
                    <div style='background: linear-gradient(135deg, #2e7d32, #1b5e20); padding: 20px; text-align: center;'>
                        <h2 style='color: #ffffff; margin: 0; font-size: 20px;'>DMU Cost Sharing System</h2>
                        <p style='color: #c8e6c9; margin: 5px 0 0; font-size: 13px;'>ደብረ ማርቆስ ዩኒቨርሲቲ - የወጪ መጋራት ስርዓት</p>
                    </div>

                    <!-- Body -->
                    <div style='padding: 30px;'>
                        <!-- English -->
                        <p style='color: #333; font-size: 15px;'>Hi <strong>{$firstName}</strong>,</p>
                        <p style='color: #555; font-size: 14px; line-height: 1.6;'>
                            You recently requested to reset your password for your DMU Cost Sharing account. 
                            Click the button below to reset it. <strong>This link is valid for 10 minutes.</strong>
                        </p>

                        <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 20px 0;'>

                        <!-- Amharic -->
                        <p style='color: #333; font-size: 15px;'>ሰላም <strong>{$firstName}</strong>፣</p>
                        <p style='color: #555; font-size: 14px; line-height: 1.6;'>
                            ለ DMU የወጪ መጋራት አካውንትዎ የይለፍ ቃልዎን እንዲቀይሩ ጥያቄ አቅርበዋል። 
                            ከታች ያለውን ቁልፍ ጠቅ በማድረግ ይቀይሩ። <strong>ይህ ሊንክ ለ10 ደቂቃ ብቻ ይሰራል።</strong>
                        </p>

                        <!-- Reset Button -->
                        <div style='text-align: center; margin: 25px 0;'>
                            <a href='{$reset_link}' style='background: #2e7d32; color: #ffffff; padding: 14px 35px; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: bold; display: inline-block;'>
                                Reset Password | የይለፍ ቃል ቀይር
                            </a>
                        </div>

                        <p style='color: #888; font-size: 13px; line-height: 1.5;'>
                            If you did not request this, please ignore this email.<br>
                            ይህን ካልጠየቁ፣ እባክዎ ይህን ኢሜል ችላ ይበሉ።
                        </p>
                    </div>

                    <!-- Footer -->
                    <div style='background: #f5f5f5; padding: 15px; text-align: center; border-top: 1px solid #e0e0e0;'>
                        <p style='color: #999; font-size: 12px; margin: 0;'>
                            &copy; 2026 Debre Markos University | ደብረ ማርቆስ ዩኒቨርሲቲ
                        </p>
                    </div>
                </div>";

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