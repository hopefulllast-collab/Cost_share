<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>TiDB Connection Test</h2>";

$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$dbname = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

echo "<p>Host: $host</p>";
echo "<p>Port: $port</p>";
echo "<p>User length: " . strlen($user) . "</p>";
echo "<p>CA Cert exists: " . (file_exists(__DIR__ . '/config/ca-cert.pem') ? 'Yes' : 'No') . "</p>";

// Method 1: PDO
echo "<h3>Method 1: PDO Connection</h3>";
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname";
    $options = [
        PDO::MYSQL_ATTR_SSL_CA => __DIR__ . '/config/ca-cert.pem',
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "<p style='color:green'>PDO Connection Success!</p>";
} catch (PDOException $e) {
    echo "<p style='color:red'>PDO Error: " . $e->getMessage() . "</p>";
}

// Method 2: MySQLi
echo "<h3>Method 2: MySQLi Connection</h3>";
try {
    $mysqli = mysqli_init();
    mysqli_ssl_set($mysqli, NULL, NULL, __DIR__ . '/config/ca-cert.pem', NULL, NULL);
    mysqli_real_connect($mysqli, $host, $user, $pass, $dbname, $port, NULL, MYSQLI_CLIENT_SSL);
    if (mysqli_connect_errno()) {
        echo "<p style='color:red'>MySQLi Error: " . mysqli_connect_error() . "</p>";
    } else {
        echo "<p style='color:green'>MySQLi Connection Success!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>MySQLi Exception: " . $e->getMessage() . "</p>";
}

echo "<h3>PHP Info</h3>";
echo "<pre>PHP Version: " . phpversion() . "\n";
$ext = get_loaded_extensions();
echo "Loaded extensions: " . implode(', ', $ext) . "</pre>";
?>
