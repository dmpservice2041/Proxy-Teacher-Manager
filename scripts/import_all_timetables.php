<?php
/**
 * Import all timetable JSON files from data/timetables directory
 */

$timetableDir = __DIR__ . '/../data/timetables';

if (!is_dir($timetableDir)) {
    die("Error: Directory not found: {$timetableDir}\n");
}

$jsonFiles = glob($timetableDir . '/*.json');

if (empty($jsonFiles)) {
    die("No JSON files found in: {$timetableDir}\n");
}

echo "=== Import All Timetables ===\n";
echo "Found " . count($jsonFiles) . " JSON files\n\n";

$totalImported = 0;
$totalSkipped = 0;
$totalErrors = 0;
$processed = 0;

foreach ($jsonFiles as $jsonFile) {
    $processed++;
    echo "[{$processed}/" . count($jsonFiles) . "] Processing: " . basename($jsonFile) . "\n";
    
    $command = "php " . escapeshellarg(__DIR__ . '/import_timetable_from_json.php') . " " . escapeshellarg($jsonFile) . " 2>&1";
    $output = shell_exec($command);
    
    echo $output;
    echo "\n";
    
    // Extract stats from output
    if (preg_match('/Imported: (\d+)/', $output, $matches)) {
        $totalImported += (int)$matches[1];
    }
    if (preg_match('/Skipped.*?: (\d+)/', $output, $matches)) {
        $totalSkipped += (int)$matches[1];
    }
    if (preg_match('/Errors: (\d+)/', $output, $matches)) {
        $totalErrors += (int)$matches[1];
    }
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "=== Summary ===\n";
echo "Total Imported: {$totalImported}\n";
echo "Total Skipped: {$totalSkipped}\n";
echo "Total Errors: {$totalErrors}\n";
echo "\nDone!\n";

