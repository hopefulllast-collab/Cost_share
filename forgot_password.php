<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-en="Forgot Password - DMU" data-am="የይለፍ ቃል ረሳሁ - DMU">Forgot Password - DMU</title>
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
        .msg-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
    </style>
</head>

<body>
    <div class="reset-card">
        <h2 data-en="Forgot Password" data-am="የይለፍ ቃል ረሳሁ">Forgot Password</h2>
        <p data-en="Enter your email address to receive a password reset link." data-am="የይለፍ ቃል መቀየሪያ ሊንክ ለማግኘት የኢሜል አድራሻዎን ያስገቡ።">Enter your email address to receive a password reset link.</p>
        
        <?php if (isset($_SESSION['reset_error'])): ?>
            <div class="msg-box msg-error"><?php echo $_SESSION['reset_error']; unset($_SESSION['reset_error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['reset_success'])): ?>
            <div class="msg-box msg-success"><?php echo $_SESSION['reset_success']; unset($_SESSION['reset_success']); ?></div>
        <?php endif; ?>

        <form action="process_forgot_password.php" method="POST" class="reset-form">
            <input type="email" name="email" required placeholder="example@email.com" data-en-placeholder="example@email.com" data-am-placeholder="ኢሜል ያስገቡ">
            <button type="submit" class="reset-btn" data-en="Send Reset Link" data-am="የመቀየሪያ ሊንክ ላክ">Send Reset Link</button>
        </form>
        <a href="index.php" class="back-link" data-en="Back to Login" data-am="ወደ መግቢያ ተመለስ">Back to Login</a>
    </div>
    <script src="assets/js/bilingual.js"></script>
</body>
</html>
