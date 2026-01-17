<?php
require_once __DIR__ . '/../config/app.php';

try {
    $pdo = Database::getInstance()->getConnection();
    
    echo "Checking 'users' table for 'reset_token' column...\n";
    
    // Check if column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'reset_token'");
    $stmt->execute();
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "Adding 'reset_token' and 'reset_expires_at' columns to 'users' table...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL AFTER role, ADD COLUMN reset_expires_at DATETIME NULL AFTER reset_token");
        echo "Columns added successfully.\n";
    } else {
        echo "Columns already exist.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
