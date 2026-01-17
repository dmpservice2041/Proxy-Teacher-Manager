<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::getInstance()->getConnection();
    
    echo "Updating blocked_periods table schema...\n";
    
    $sql = "DROP TABLE IF EXISTS blocked_periods";
    $pdo->exec($sql);
    
    $sql = "CREATE TABLE blocked_periods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        day VARCHAR(20) NOT NULL,
        period_no INT NOT NULL,
        class_id INT NULL DEFAULT NULL,
        description VARCHAR(255) NULL,
        UNIQUE KEY unique_block (day, period_no, class_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "Table 'blocked_periods' re-created with class_id support.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
