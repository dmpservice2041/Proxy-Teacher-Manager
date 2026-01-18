<?php
/**
 * Clear all timetable entries
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getInstance()->getConnection();

echo "=== Clearing All Timetable Data ===\n\n";

// Ask for confirmation
echo "WARNING: This will delete ALL timetable entries!\n";
echo "Do you want to continue? (yes/no): ";
$handle = fopen("php://stdin", "r");
$response = trim(fgets($handle));
fclose($handle);

if (strtolower($response) !== 'yes') {
    echo "Operation cancelled.\n";
    exit(0);
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("DELETE FROM timetable");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    
    $pdo->commit();
    
    echo "\n=== Success ===\n";
    echo "Deleted {$deleted} timetable entries.\n";
    echo "All timetable data has been cleared.\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n=== Error ===\n";
    echo "Failed to clear timetable: " . $e->getMessage() . "\n";
    exit(1);
}

