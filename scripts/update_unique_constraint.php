<?php
require_once 'config/app.php';

$pdo = Database::getInstance()->getConnection();

echo "Updating unique constraint on proxy_assignments...\n";

try {
    // 1. Drop existing constraint
    echo "Dropping index 'unique_proxy'...\n";
    $pdo->exec("ALTER TABLE proxy_assignments DROP INDEX unique_proxy");
    
    // 2. Add new constraint
    // We want to allow same proxy for same period IF it's for the same class (group merge).
    // But strict uniqueness on (date, proxy, period) forbids this.
    // So we expand the uniqueness to include class_id and subject_id.
    // Actually, subject_id is nullable. UNIQUE indexes allow multiple NULLs.
    // If subject_id is NULL (legacy), we might still have issues.
    // Ideally, we populate subject_id for all rows, but we can't guarantee that now.
    // A broader unique key: (date, proxy_teacher_id, period_no, class_id, subject_id)
    
    echo "Adding new unique index...\n";
    $pdo->exec("ALTER TABLE proxy_assignments ADD UNIQUE KEY unique_proxy_extended (date, proxy_teacher_id, period_no, class_id, subject_id)");
    
    echo "Success: Constraint updated.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    // Check if drop failed because it didn't exist (ignore)
    if (strpos($e->getMessage(), "check that column/key exists") !== false) {
        // Retry add just in case
        try {
            $pdo->exec("ALTER TABLE proxy_assignments ADD UNIQUE KEY unique_proxy_extended (date, proxy_teacher_id, period_no, class_id, subject_id)");
            echo "Success: Constraint added (drop skipped).\n";
        } catch (Exception $ex) {
             echo "Retry Error: " . $ex->getMessage() . "\n";
        }
    }
}
