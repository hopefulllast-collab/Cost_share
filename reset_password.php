<?php
session_start();
require_once 'config/db_connect.php';

$valid_token = false;
$user_id = null;

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Verify token
    $stmt = $pdo->prepare("SELECT user_id, expires_at FROM password_resets WHERE reset_token = ?");
    $stmt->execute([$token]);
    $reset_record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($reset_record) {
        $expires_at = new DateTime($reset_record['expires_at']);
        $now = new DateTime();

        if ($now < $expires_at) {
            $valid_token = true;
            $user_id = $reset_record['user_id'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Reset Password - DMU" data-am="የይለፍ ቃል ቀይር - DMU">Reset Password - DMU</title>
    <link rel="stylesheet" href="assets/css/index.css?v=11">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .reset-card { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .reset-card h2 { margin-top: 0; color: #333; }
        .reset-card p { color: #666; font-size: 14px; margin-bottom: 20px; }
        .reset-form input { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .reset-btn { width: 100%; padding: 12px; background: #2e7d32; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; transition: background 0.3s; }
        .reset-btn:hover { background: #1b5e20; }
        .back-link { display: block; margin-top: 15px; color: #2e7d32; text-decoration: none; font-size: 14px; }
        .back-link:hover { text-decoration: underline; }
        .msg-box { padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 14px; }
        .msg-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
    </style>
</head>

<body>
    <div class="reset-card">
        <?php if ($valid_token): ?>
            <h2 data-en="Setup New Password" data-am="አዲስ የይለፍ ቃል ያዘጋጁ">Setup New Password</h2>
            <p data-en="Please enter your new password below." data-am="እባክዎትን አዲሱን የይለፍ ቃልዎን ከታች ያስገቡ።">Please enter your new password below.</p>
            
            <?php if (isset($_SESSION['reset_error'])): ?>
                <div class="msg-box msg-error"><?php echo $_SESSION['reset_error']; unset($_SESSION['reset_error']); ?></div>
            <?php endif; ?>

            <form action="process_reset_password.php" method="POST" class="reset-form">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="password" name="new_password" required placeholder="New Password" data-en-placeholder="New Password" data-am-placeholder="አዲስ የይለፍ ቃል">
                <input type="password" name="confirm_password" required placeholder="Confirm Password" data-en-placeholder="Confirm Password" data-am-placeholder="የይለፍ ቃል አረጋግጥ">
                <button type="submit" class="reset-btn" data-en="Reset Password" data-am="የይለፍ ቃል አዘምን">Reset Password</button>
            </form>
        <?php else: ?>
            <h2 style="color: #c62828;"><i class="fas fa-exclamation-circle"></i> Invalid Link</h2>
            <p data-en="The password reset link is invalid or has expired." data-am="የይለፍ ቃል መቀየሪያ ሊንኩ ጊዜው አልፎበታል ወይም የተሳሳተ ነው።">The password reset link is invalid or has expired.</p>
            <a href="forgot_password.php" class="reset-btn" style="text-decoration: none; display: inline-block; box-sizing: border-box;" data-en="Request New Link" data-am="አዲስ ሊንክ ይጠይቁ">Request New Link</a>
        <?php endif; ?>
        
        <br>
        <a href="index.php" class="back-link" data-en="Back to Login" data-am="ወደ መግቢያ ተመለስ">Back to Login</a>
    </div>
    <script src="assets/js/bilingual.js"></script>
</body>
</html>
