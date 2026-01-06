<?php
/**
 * Import Timetable from JSON (Structured Text)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Timetable.php';
require_once __DIR__ . '/../models/Classes.php';
require_once __DIR__ . '/../models/Subject.php';

$jsonPath = __DIR__ . '/../temp_timetable.json';

if (!file_exists($jsonPath)) {
    die("Error: JSON file not found: {$jsonPath}\n");
}

$data = json_decode(file_get_contents($jsonPath), true);
if (!$data) {
    die("Error: Invalid JSON data.\n");
}

echo "=== Import Timetable from JSON ===\n";

// Initialize models
$teacherModel = new Teacher();
$timetableModel = new Timetable();
$classModel = new Classes();
$subjectModel = new Subject();

// Get all data
$allTeachers = $teacherModel->getAllWithDetails();
$allClasses = $classModel->getAll();
$allSubjects = $subjectModel->getAll();

// Create subject variation map
$subjectVariations = [
    'mathematics' => ['maths', 'math', 'mathematic', 'mathematics'],
    'english grammar' => ['english grammar', 'eng grammar', 'grammar'],
    'english' => ['english', 'eng', 'english fl'],
    'english reading' => ['english reading', 'eng reading', 'reading'],
    'environment' => ['evs', 'environment', 'environmental', 'environmen', 'environmental studies'],
    'general knowledge' => ['gk', 'general knowledge'],
    'value education' => ['v.ed', 'value education', 'value'],
    'conversation' => ['conv', 'conversation', 'conversatio'],
    'cursive writing' => ['cw', 'cursive writing', 'cursive'],
    'mpt' => ['mpt', 'pt'],
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
    'computer' => ['computer', 'comp', 'ict']
];

// Create flattened subject map
$flatSubjectMap = [];
foreach ($allSubjects as $s) {
    $sName = strtolower($s['name']);
    $flatSubjectMap[$sName] = $s['id'];
    
    // Add variations
    foreach ($subjectVariations as $target => $vars) {
        if ($sName === $target || in_array($sName, $vars)) {
            foreach ($vars as $v) {
                $flatSubjectMap[$v] = $s['id'];
            }
        }
    }
}

// Day mapping
$dayMap = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6];

foreach ($data as $teacherData) {
    $teacherName = $teacherData['name'];
    echo "Processing Teacher: {$teacherName}\n";
    
    // Find teacher (Try exact matches first)
    $cleanSearch = preg_replace('/^(ms\.?|mr\.?|mrs\.?|miss|prof\.?|dr\.?|sr\.?)\s+/i', '', strtolower($teacherName));
    
    $teacherId = null;
    foreach ($allTeachers as $t) {
        $dbName = strtolower(trim($t['name']));
        $dbNameClean = preg_replace('/^(ms\.?|mr\.?|mrs\.?|miss|prof\.?|dr\.?|sr\.?)\s+/i', '', $dbName);
        
        if ($dbName === strtolower($teacherName) || $dbNameClean === $cleanSearch) {
            $teacherId = $t['id'];
            echo "  Found Teacher ID: {$teacherId} ({$t['name']}) [Exact/Clean Match]\n";
            break;
        }
    }

    // Try partial matches if not found
    if (!$teacherId) {
        foreach ($allTeachers as $t) {
            $dbNameClean = preg_replace('/^(ms\.?|mr\.?|mrs\.?|miss|prof\.?|dr\.?|sr\.?)\s+/i', '', strtolower(trim($t['name'])));
            if (stripos($dbNameClean, $cleanSearch) !== false || stripos($cleanSearch, $dbNameClean) !== false) {
                $teacherId = $t['id'];
                echo "  Found Teacher ID: {$teacherId} ({$t['name']}) [Partial Match]\n";
                break;
            }
        }
    }
    
    if (!$teacherId) {
        echo "  [ERROR] Teacher not found: {$teacherName}. Skipping.\n";
        continue;
    }
    
    foreach ($teacherData['schedule'] as $dayName => $periods) {
        $dayNum = $dayMap[$dayName];
        
        foreach ($periods as $index => $entry) {
            if (empty(trim($entry))) continue;
            
            $periodNo = $index + 1;
            
            // Parse entry: "2-Orchid English Grammar" or "1-Tulip MPT"
            // Pattern: Standard-Division Subject
            if (preg_match('/^(\d+)[- ]([A-Za-z]+)\s+(.+)$/i', $entry, $matches)) {
                $std = $matches[1];
                $div = strtolower($matches[2]);
                $subName = strtolower(trim($matches[3]));
                
                // Find class
                $classId = null;
                $divLower = strtolower($div);
                foreach ($allClasses as $c) {
                    if ($c['standard'] == $std && (strtolower($c['division']) == $divLower || stripos($c['division'], $div) !== false)) {
                        $classId = $c['id'];
                        break;
                    }
                }
                
                if (!$classId) {
                    foreach ($allClasses as $c) {
                        if (strtolower($c['division']) == $divLower) {
                            $classId = $c['id'];
                            break;
                        }
                    }
                }
                
                if (!$classId) {
                    echo "    [ERROR] Class not found: {$std}-{$div} for entry '{$entry}'.\n";
                    continue;
                }
                
                // Find subject
                $subjectId = $flatSubjectMap[$subName] ?? null;
                
                if (!$subjectId) {
                    // Try one more search in variations
                    foreach ($subjectVariations as $target => $vars) {
                        if ($subName === $target || in_array($subName, $vars) || stripos($subName, $target) !== false) {
                            $subjectId = $flatSubjectMap[$target] ?? null;
                            if ($subjectId) break;
                        }
                    }
                }
                
                if (!$subjectId) {
                    // Auto-create subject if missing
                    try {
                        $pdo = Database::getInstance()->getConnection();
                        $stmt = $pdo->prepare("INSERT INTO subjects (name) VALUES (?)");
                        $stmt->execute([ucwords($subName)]);
                        $subjectId = $pdo->lastInsertId();
                        $newSub = $subjectModel->find($subjectId);
                        $flatSubjectMap[$subName] = $subjectId;
                        echo "    [INFO] Created new subject: " . ucwords($subName) . "\n";
                    } catch (Exception $e) {
                        echo "    [ERROR] Subject auto-create failed: " . $e->getMessage() . "\n";
                        continue;
                    }
                }
                
                // Add to timetable
                try {
                    $timetableModel->add($teacherId, $classId, $subjectId, $dayNum, $periodNo);
                    echo "    Added: Day {$dayNum} P{$periodNo} -> {$entry}\n";
                } catch (Exception $e) {
                    echo "    [ERROR] Failed to add: " . $e->getMessage() . "\n";
                }
            } else {
                echo "    [ERROR] Invalid entry format: '{$entry}'\n";
            }
        }
    }
}

echo "\nDone!\n";
unlink($jsonPath);
