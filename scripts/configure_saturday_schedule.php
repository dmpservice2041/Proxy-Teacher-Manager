<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Classes.php';
require_once __DIR__ . '/../models/BlockedPeriod.php';
require_once __DIR__ . '/../models/Settings.php';

try {
    echo "Starting Saturday Schedule Configuration...\n";
    
    $classModel = new Classes();
    $blockedModel = new BlockedPeriod();
    $settingsModel = new Settings();
    
    $totalPeriods = $settingsModel->get('total_periods', 8);
    $allClasses = $classModel->getAll();
    $day = 'Saturday';
    
    $targetStandards = array_merge(range(1, 9), [11]); // [1, 2, ... 9, 11]
    
    $count = 0;
    
    foreach ($allClasses as $cls) {
        $std = $cls['standard'];
        
        // Normalize standard (handle "10-A" vs "10" if necessary, but assuming numeric or string numeric)
        // If standard is "I", "II" etc, this logic fails. Let's assume numeric based on user request "1 to 9".
        // Use loose comparison for string number matching ("1" == 1)
        
        if (in_array($std, $targetStandards)) {
            echo "Processing Class ID {$cls['id']} (Std: $std) -> Limit 3 Periods\n";
            // Allow Periods 1-3
            for ($p = 1; $p <= 3; $p++) $blockedModel->unblock($day, $p, $cls['id']);
            // Block Periods 4+
            for ($p = 4; $p <= $totalPeriods; $p++) $blockedModel->block($day, $p, $cls['id'], "Saturday Limit (Std $std)");
            $count++;
        }
        
        // Logic for Std 10 and 12 (Max 4 Periods)
        if (in_array($std, [10, 12])) {
            echo "Processing Class ID {$cls['id']} (Std: $std) -> Limit 4 Periods\n";
            // Allow Periods 1-4
            for ($p = 1; $p <= 4; $p++) $blockedModel->unblock($day, $p, $cls['id']);
            // Block Periods 5+
            for ($p = 5; $p <= $totalPeriods; $p++) $blockedModel->block($day, $p, $cls['id'], "Saturday Limit (Std $std)");
            $count++;
        }
    }
    
    echo "Configuration Reference:\n";
    echo "- Applied Saturday Limit (Max 3 Periods) to $count classes.\n";
    echo "- Standards: " . implode(', ', $targetStandards) . "\n";
    echo "Done.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
