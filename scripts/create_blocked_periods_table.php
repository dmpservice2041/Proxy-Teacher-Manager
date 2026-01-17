<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = Database::getInstance()->getConnection();
    
    echo "Creating blocked_periods table...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS blocked_periods (
        day VARCHAR(20) NOT NULL,
        period_no INT NOT NULL,
        description VARCHAR(255) NULL,
        PRIMARY KEY (day, period_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    echo "Table 'blocked_periods' created (or already exists).\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
