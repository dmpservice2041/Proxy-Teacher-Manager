<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/etime_config.php';
require_once __DIR__ . '/../services/ETimeService.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Attendance.php';

// Initialize Service
$etimeService = new ETimeService();

echo "=== Debugging Teacher Import (90 Days Lookback) ===\n";

// Fetch teachers using the service method
$daysBack = 90;
$result = $etimeService->fetchAllTeachers($daysBack);

if ($result['success']) {
    $teachers = $result['teachers'];
    $count = count($teachers);
    echo "Total unique teachers found in API: $count\n";
    
    echo "\nList of Employees Found:\n";
    echo str_pad("EmpCode", 10) . " | " . "Name\n";
    echo str_repeat("-", 40) . "\n";
    
    $foundCodes = [];
    foreach ($teachers as $t) {
        echo str_pad($t['empcode'], 10) . " | " . $t['name'] . "\n";
        $foundCodes[] = $t['empcode'];
    }
    
    echo "\nDuplicate Check:\n";
    $codeCounts = array_count_values($foundCodes);
    $duplicates = array_filter($codeCounts, function($c) { return $c > 1; });
    
    if (empty($duplicates)) {
        echo "No duplicate EmpCodes found in the unique list.\n";
    } else {
        echo "Duplicates found: " . print_r($duplicates, true) . "\n";
    }

} else {
    echo "Error fetching teachers: " . $result['message'] . "\n";
}
