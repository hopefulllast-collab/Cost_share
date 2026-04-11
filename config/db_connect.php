<?php
// ============================================
// DATABASE CONNECTION - Environment Based
// Works with both local XAMPP and TiDB Cloud
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
$use_ssl  = getenv('DB_SSL') ?: 'false';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db_name;charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    // Auto-detect SSL: if not localhost, enable SSL (TiDB Cloud requires it)
    $need_ssl = ($use_ssl === 'true') || ($host !== 'localhost' && $host !== '127.0.0.1');
    
    if ($need_ssl) {
        // Try system CA certificate paths (Linux/Vercel)
        $ca_paths = [
            getenv('DB_SSL_CA') ?: '',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/ca-bundle.pem',
        ];
        
        $ca_found = false;
        foreach ($ca_paths as $ca) {
            if ($ca && file_exists($ca)) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
                $ca_found = true;
                break;
            }
        }
        
        if (!$ca_found) {
            // Last resort: set empty SSL_CA to still force SSL mode
            $options[PDO::MYSQL_ATTR_SSL_CA] = '';
        }
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    
    $pdo = new PDO($dsn, $username, $password, $options);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
