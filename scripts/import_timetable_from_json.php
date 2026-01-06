<?php
/**
 * Import Timetable from JSON file
 * 
 * Usage: php import_timetable_from_json.php <json_file_path>
 * 
 * JSON Format:
 * {
 *   "teacher_name": "Ms. Bhavna",
 *   "entries": [
 *     {
 *       "day": 1,  // 1=Monday, 2=Tuesday, etc.
 *       "period": 2,
 *       "subject": "Sanskrit",
 *       "class": "7-Orchid",  // or "standard": 7, "division": "Orchid"
 *       "group_name": null  // optional for group classes
 *     }
 *   ]
 * }
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Timetable.php';
require_once __DIR__ . '/../models/Classes.php';
require_once __DIR__ . '/../models/Subject.php';

if ($argc < 2) {
    die("Usage: php import_timetable_from_json.php <json_file_path>\n");
}

$jsonPath = $argv[1];

if (!file_exists($jsonPath)) {
    die("Error: JSON file not found: {$jsonPath}\n");
}

$jsonData = json_decode(file_get_contents($jsonPath), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error: Invalid JSON file. " . json_last_error_msg() . "\n");
}

echo "=== Import Timetable from JSON ===\n\n";

// Initialize models
$teacherModel = new Teacher();
$timetableModel = new Timetable();
$classModel = new Classes();
$subjectModel = new Subject();

// Get all data
$allTeachers = $teacherModel->getAllWithDetails();
$allClasses = $classModel->getAll();
$allSubjects = $subjectModel->getAll();

// Find teacher
$teacherName = $jsonData['teacher_name'] ?? '';
$teacher = null;
$teacherNameLower = strtolower(trim($teacherName));
$cleanName = preg_replace('/^(ms\.?|mr\.?|mrs\.?|miss|prof\.?|dr\.?|sr\.?)\s+/i', '', $teacherNameLower);

foreach ($allTeachers as $t) {
    $name = strtolower(trim($t['name']));
    $nameClean = preg_replace('/^(ms\.?|mr\.?|mrs\.?|miss|prof\.?|dr\.?|sr\.?)\s+/i', '', $name);
    
    if ($name === $teacherNameLower || 
        $nameClean === $cleanName ||
        stripos($name, $cleanName) !== false ||
        stripos($cleanName, $name) !== false) {
        $teacher = $t;
        break;
    }
}

if (!$teacher) {
    die("Error: Teacher not found in database: {$teacherName}\n");
}

echo "Found teacher: {$teacher['name']} (ID: {$teacher['id']})\n\n";

// Create lookup maps
$classMap = [];
foreach ($allClasses as $class) {
    $key = strtolower(trim($class['standard'] . '-' . $class['division']));
    $classMap[$key] = $class;
    if (!empty($class['division'])) {
        $classMap[strtolower(trim($class['division']))] = $class;
    }
}

$subjectMap = [];
$subjectVariations = [
    'mathematics' => ['maths', 'math', 'mathematic', 'mathematics'],
    'english grammar' => ['english grammar', 'eng grammar', 'grammar'],
    'english' => ['english', 'eng'],
    'english reading' => ['english reading', 'eng reading', 'reading'],
    'environmental studies' => ['evs', 'environment', 'environmental', 'environmen'],
    'general knowledge' => ['gk', 'general knowledge'],
    'value education' => ['v.ed', 'value education', 'value'],
    'conversation' => ['conv', 'conversation', 'conversatio'],
    'cursive writing' => ['cw', 'cursive writing', 'cursive'],
    'mpt' => ['mpt'],
    'physical training' => ['pt', 'physical training'],
    'drawing' => ['drawing'],
    'gujarati' => ['guj', 'gujarati'],
    'hindi' => ['hindi'],
    'hindi reading' => ['hindi reading'],
    'sanskrit' => ['sanskrit'],
    'science' => ['science', 'sci'],
    'social studies' => ['s.s', 'social studies', 'social science'],
    'commerce' => ['com', 'commerce'],
    'library' => ['lib', 'library'],
    'games' => ['games', 'game'],
    'accounts' => ['accounts'],
    'statistics' => ['statistics'],
    'economics' => ['economics'],
    'o.c' => ['o.c', 'oc'],
    'spcc' => ['spcc']
];

foreach ($allSubjects as $subject) {
    $name = strtolower(trim($subject['name']));
    $subjectMap[$name] = $subject;
    
    foreach ($subjectVariations as $key => $variations) {
        foreach ($variations as $var) {
            if (stripos($name, $var) !== false || stripos($var, $name) !== false) {
                foreach ($variations as $v) {
                    $subjectMap[$v] = $subject;
                }
                break 2;
            }
        }
    }
}

// Process entries
$entries = $jsonData['entries'] ?? [];
$imported = 0;
$skipped = 0;
$errors = [];

$dayNames = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

echo "Processing " . count($entries) . " entries...\n\n";

foreach ($entries as $index => $entry) {
    $day = $entry['day'] ?? null;
    $period = $entry['period'] ?? null;
    $subjectName = $entry['subject'] ?? '';
    $classStr = $entry['class'] ?? '';
    $standard = $entry['standard'] ?? null;
    $division = $entry['division'] ?? null;
    $groupName = $entry['group_name'] ?? null;
    
    if (!$day || !$period || !$subjectName) {
        $errors[] = "Entry " . ($index + 1) . ": Missing required fields (day, period, or subject)";
        continue;
    }
    
    // Parse class
    $class = null;
    if ($standard && $division) {
        $classKey = strtolower($standard . '-' . $division);
        $class = $classMap[$classKey] ?? null;
    } elseif ($classStr) {
        // Parse "7-Orchid" or "7 - Orchid"
        if (preg_match('/(\d+)\s*[-]\s*([A-Za-z]+)/i', $classStr, $match)) {
            $std = $match[1];
            $div = trim($match[2]);
            $classKey = strtolower($std . '-' . $div);
            $class = $classMap[$classKey] ?? null;
        }
    }
    
    if (!$class) {
        $errors[] = "Entry " . ($index + 1) . " (Day {$day}, Period {$period}): Class not found: {$classStr}";
        continue;
    }
    
    // Find subject
    $subject = null;
    $subjectNameLower = strtolower(trim($subjectName));
    if (isset($subjectMap[$subjectNameLower])) {
        $subject = $subjectMap[$subjectNameLower];
    } else {
        foreach ($subjectMap as $subKey => $sub) {
            if (stripos($subjectNameLower, $subKey) !== false || 
                stripos($subKey, $subjectNameLower) !== false) {
                $subject = $sub;
                break;
            }
        }
    }
    
    if (!$subject) {
        $errors[] = "Entry " . ($index + 1) . " (Day {$day}, Period {$period}): Subject not found: {$subjectName}";
        continue;
    }
    
    // Check if entry already exists
    $existing = $timetableModel->getTeacherSchedule($teacher['id'], $day);
    $exists = false;
    foreach ($existing as $existingEntry) {
        if ($existingEntry['period_no'] == $period && 
            $existingEntry['class_id'] == $class['id'] &&
            $existingEntry['subject_id'] == $subject['id']) {
            $exists = true;
            break;
        }
    }
    
    if ($exists) {
        $skipped++;
        continue;
    }
    
    // Import entry
    try {
        $timetableModel->add(
            $teacher['id'],
            $class['id'],
            $subject['id'],
            $day,
            $period,
            $groupName
        );
        $imported++;
        echo "  ✓ {$dayNames[$day]} P{$period} - {$class['standard']}-{$class['division']} - {$subject['name']}\n";
    } catch (Exception $e) {
        $errors[] = "Entry " . ($index + 1) . ": " . $e->getMessage();
    }
}

echo "\n=== Import Complete ===\n";
echo "Imported: {$imported}\n";
echo "Skipped (already exists): {$skipped}\n";
echo "Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach (array_slice($errors, 0, 20) as $error) {
        echo "  - {$error}\n";
    }
    if (count($errors) > 20) {
        echo "  ... and " . (count($errors) - 20) . " more errors\n";
    }
}

echo "\nDone!\n";

