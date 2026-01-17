<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Create daily_overrides table
    $sql = "CREATE TABLE IF NOT EXISTS daily_overrides (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        type ENUM('TEACHER_DUTY', 'CLASS_ABSENT') NOT NULL,
        target_id INT NOT NULL COMMENT 'Teacher ID or Class ID depending on type',
        period_no INT NULL COMMENT 'NULL means entire day',
        reason VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_date (date),
        INDEX idx_type_target (type, target_id)
    )";
    
    $pdo->exec($sql);
    echo "Table 'daily_overrides' created successfully.\n";
    
} catch (PDOException $e) {
    die("Error creating table: " . $e->getMessage() . "\n");
}
