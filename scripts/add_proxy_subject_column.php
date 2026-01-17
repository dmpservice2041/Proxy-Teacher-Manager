<?php
require_once 'config/app.php';

echo "Adding subject_id column to proxy_assignments...\n";

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Check if exists first
    $stmt = $pdo->query("SHOW COLUMNS FROM proxy_assignments LIKE 'subject_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE proxy_assignments ADD COLUMN subject_id INT NULL AFTER class_id");
        // Add Foreign Key too
        $pdo->exec("ALTER TABLE proxy_assignments ADD CONSTRAINT fk_proxy_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL");
        echo "Success: Column 'subject_id' added.\n";
    } else {
        echo "Info: Column 'subject_id' already exists.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
