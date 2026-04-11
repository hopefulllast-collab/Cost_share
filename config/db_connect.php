<?php
// ============================================
// DATABASE CONNECTION - Environment Based
// Works with both local XAMPP and Cloud Deployments
// ============================================

// Load .env file for local development
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// Database configuration from environment variables
$host     = getenv('DB_HOST') ?: 'localhost';
$port     = getenv('DB_PORT') ?: '3306';
$db_name  = getenv('DB_NAME') ?: 'dmu_cost_sharing';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

try {
    // If you plan to use TiDB Cloud, the standard Docker PHP has built-in SSL certs
    // so we don't need any complex SSL configuration here!
    $dsn = "mysql:host=$host;port=$port;dbname=$db_name;charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    // Enable strict SSL verification for TiDB Cloud Serverless
    // In Docker (Debian), the system CA path is /etc/ssl/certs/ca-certificates.crt
    if ($host !== 'localhost' && $host !== '127.0.0.1') {
        $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    
    // Disable strict ONLY_FULL_GROUP_BY mode session-wide to ensure existing queries work on TiDB
    $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))";
    
    $pdo = new PDO($dsn, $username, $password, $options);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
