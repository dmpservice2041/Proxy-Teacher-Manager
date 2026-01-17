<?php
require_once 'config/app.php';

echo "Adding group_name column to timetable...\n";

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Check if exists first
    $stmt = $pdo->query("SHOW COLUMNS FROM timetable LIKE 'group_name'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE timetable ADD COLUMN group_name VARCHAR(50) DEFAULT NULL AFTER subject_id");
        echo "Success: Column 'group_name' added.\n";
    } else {
        echo "Info: Column 'group_name' already exists.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
