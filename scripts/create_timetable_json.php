<?php
/**
 * Helper script to create JSON files for timetable import
 * This creates a template JSON file for a teacher
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Teacher.php';

$teacherModel = new Teacher();
$allTeachers = $teacherModel->getAllWithDetails();

echo "=== Create Timetable JSON Template ===\n\n";
echo "Available teachers:\n";
foreach ($allTeachers as $i => $teacher) {
    echo "  " . ($i + 1) . ". {$teacher['name']} (ID: {$teacher['id']})\n";
}

echo "\nEnter teacher name or number: ";
$handle = fopen("php://stdin", "r");
$input = trim(fgets($handle));
fclose($handle);

$teacher = null;
if (is_numeric($input)) {
    $index = (int)$input - 1;
    if (isset($allTeachers[$index])) {
        $teacher = $allTeachers[$index];
    }
} else {
    foreach ($allTeachers as $t) {
        if (stripos($t['name'], $input) !== false || stripos($input, $t['name']) !== false) {
            $teacher = $t;
            break;
        }
    }
}

if (!$teacher) {
    die("Teacher not found.\n");
}

echo "\nCreating template for: {$teacher['name']}\n";
echo "Enter output filename (e.g., bhavna_timetable.json): ";
$handle = fopen("php://stdin", "r");
$filename = trim(fgets($handle));
fclose($handle);

if (empty($filename)) {
    $filename = strtolower(str_replace([' ', '.'], '_', $teacher['name'])) . '_timetable.json';
}

$template = [
    'teacher_name' => $teacher['name'],
    'entries' => []
];

// Add example entries
$template['entries'][] = [
    'day' => 1,  // 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday
    'period' => 2,
    'subject' => 'Sanskrit',
    'class' => '7-Orchid',  // Format: "standard-division"
    'group_name' => null  // Optional: for group classes
];

$json = json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents($filename, $json);

echo "\nTemplate created: {$filename}\n";
echo "Edit this file and add all timetable entries, then run:\n";
echo "  php scripts/import_timetable_from_json.php {$filename}\n";

