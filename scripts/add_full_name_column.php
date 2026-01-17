<?php
require_once __DIR__ . '/../config/app.php';

try {
    $pdo = Database::getInstance()->getConnection();
    
    echo "Checking 'users' table for 'full_name' column...\n";
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'full_name'");
    $stmt->execute();
    $exists = $stmt->fetch();
    
    if (!$exists) {
        echo "Adding 'full_name' column to 'users' table...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN full_name VARCHAR(100) NULL AFTER username");
        echo "Column added successfully.\n";
        
        // Initialize full_name with username for existing users
        $pdo->exec("UPDATE users SET full_name = username WHERE full_name IS NULL");
        echo "Initialized full_name with existing usernames.\n";
    } else {
        echo "Column already exists.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
