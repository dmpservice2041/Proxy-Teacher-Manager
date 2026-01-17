<?php
require_once __DIR__ . '/../config/app.php';

try {
    $pdo = Database::getInstance()->getConnection();
    
    echo "Checking 'users' table for 'email' column...\n";
    
    // Check if column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'email'");
    $stmt->execute();
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "Adding 'email' column to 'users' table...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(150) NULL AFTER username");
        echo "Column 'email' added successfully.\n";
    } else {
        echo "Column 'email' already exists.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
