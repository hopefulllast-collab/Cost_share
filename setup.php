<?php
$message = "";
$error = "";

if (isset($_POST['run_setup'])) {
    $host = 'localhost';
    $username = 'root';
    $password = ''; // Default XAMPP

    try {
        // 1. Connect without DB to create it
        $pdo = new PDO("mysql:host=$host", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec("USE dmu_cost_sharing");

        // 2. Run Full DB Dump (which includes Schema and Data)
        $sqlFilePath = 'config/dmu_cost_sharing_full.sql';
        if (file_exists($sqlFilePath)) {
            $sql = file_get_contents($sqlFilePath);
            $pdo->exec($sql);
            $message = "Database restored successfully from dmu_cost_sharing_full.sql!";
        } else {
            $error = "Error: config/dmu_cost_sharing_full.sql not found. Please place the exported database there.";
            return;
        }

        // Wait... the exported SQL already contains all seeded data!
        // Running seeder logic below is actually redundant and might cause duplicate constraint errors 
        // if the dump already has these users. However, we'll keep the admin creation just in case 
        // to ensure access is never fully lost if the dump is empty.

        // 3. Fallback Seeder Logic: Ensure core Admin access if the SQL dump didn't have it
        $passwordHash = password_hash('password', PASSWORD_DEFAULT);

        // Minimal Fallback User (Admin)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin1'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, first_name, last_name) VALUES ('admin1', ?, 'admin', 'System', 'Admin')");
            $stmt->execute([$passwordHash]);
            $message .= "<br>Fallback 'admin1' created (password: 'password').";
        }

    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>System Setup - DMU</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body style="height:100vh; display:flex; justify-content:center; align-items:center;">
    <div class="login-card" style="width:400px;">
        <h2>System Setup</h2>
        <p>Initialize database and seed test users.</p>

        <?php if ($message): ?>
            <div class="success-msg"
                style="background:#d4edda; color:#155724; padding:10px; border-radius:8px; margin:20px 0;">
                <?php echo $message; ?>
            </div>
            <a href="index.php" class="btn-login" style="display:block; text-decoration:none; margin-top:10px;">Go to
                Login</a>
        <?php elseif ($error): ?>
            <div class="error-msg" style="margin:20px 0;">
                <?php echo $error; ?>
            </div>
            <form method="POST">
                <button type="submit" name="run_setup" class="btn-login">Retry Setup</button>
            </form>
        <?php else: ?>
            <form method="POST">
                <button type="submit" name="run_setup" class="btn-login" style="margin-top:20px;">Run Setup</button>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>