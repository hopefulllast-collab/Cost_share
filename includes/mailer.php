<?php
/**
 * includes/mailer.php
 * Helper script for sending emails centrally through PHPMailer
 */

$base_dir = dirname(__DIR__);
require_once $base_dir . '/includes/PHPMailer/src/Exception.php';
require_once $base_dir . '/includes/PHPMailer/src/PHPMailer.php';
require_once $base_dir . '/includes/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Sends an email using configured SMTP.
 *
 * @param string $toEmail The recipient's email address
 * @param string $toName The recipient's name
 * @param string $subject The email subject
 * @param string $htmlBody The HTML email body
 * @return bool True on success, false on failure (logs error)
 */
function sendSystemEmail($toEmail, $toName, $subject, $htmlBody) {
    if (empty(trim($toEmail))) {
        return false; // Silently fail if email is empty
    }

    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();                                            
        $mail->Host       = 'smtp.gmail.com';  // Specify main and backup SMTP servers
        $mail->SMTPAuth   = true;              // Enable SMTP authentication
        $mail->Username   = 'hopefulllast@gmail.com'; // System Email
        $mail->Password   = 'tgyqwabaeyrqgxrh';        // App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('no-reply@dmu.edu.et', 'DMU Cost Sharing System');
        $mail->addAddress(trim($toEmail), $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        // Strip HTML tags for non-HTML email clients
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error to PHP error log to avoid breaking scripts
        error_log("Email sending failed to $toEmail. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
