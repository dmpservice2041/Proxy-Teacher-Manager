<?php
/**
 * Batch import multiple timetable JSON files
 * 
 * Usage: php batch_import_timetables.php <directory_with_json_files>
 */

require_once __DIR__ . '/import_timetable_from_json.php';

if ($argc < 2) {
    die("Usage: php batch_import_timetables.php <directory_with_json_files>\n");
}

$dir = $argv[1];

if (!is_dir($dir)) {
    die("Error: Directory not found: {$dir}\n");
}

$jsonFiles = glob($dir . '/*.json');

if (empty($jsonFiles)) {
    die("No JSON files found in: {$dir}\n");
}

echo "=== Batch Import Timetables ===\n";
echo "Found " . count($jsonFiles) . " JSON files\n\n";

$totalImported = 0;
$totalSkipped = 0;
$totalErrors = 0;

foreach ($jsonFiles as $jsonFile) {
    echo "Processing: " . basename($jsonFile) . "\n";
    echo str_repeat('-', 50) . "\n";
    
    // Call the import script logic
    $_SERVER['argv'] = ['import_timetable_from_json.php', $jsonFile];
    $GLOBALS['argc'] = 2;
    
    ob_start();
    include __DIR__ . '/import_timetable_from_json.php';
    $output = ob_get_clean();
    
    echo $output;
    echo "\n";
}

echo "\n=== Batch Import Complete ===\n";

