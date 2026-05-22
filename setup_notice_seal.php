<?php
require_once 'config/db_connect.php';

try {
    $pdo->exec("ALTER TABLE notices ADD COLUMN signature_seal VARCHAR(255) NULL");
    echo "Successfully added signature_seal column.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
