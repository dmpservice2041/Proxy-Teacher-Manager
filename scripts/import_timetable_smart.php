<?php
/**
 * Smart Timetable PDF Import
 * Reads entries that appear BEFORE teacher names and maps them to grid positions
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Timetable.php';
require_once __DIR__ . '/../models/Classes.php';
require_once __DIR__ . '/../models/Subject.php';

use Smalot\PdfParser\Parser;

$pdfPath = $argv[1] ?? '/Users/darpanpatel/Downloads/Teachers Time Table R3.pdf';

if (!file_exists($pdfPath)) {
    die("Error: PDF file not found: {$pdfPath}\n");
}

echo "=== Smart Timetable Import from PDF ===\n\n";

// Initialize models
$teacherModel = new Teacher();
$timetableModel = new Timetable();
$classModel = new Classes();
$subjectModel = new Subject();

// Get all data
$allTeachers = $teacherModel->getAllWithDetails();
$allClasses = $classModel->getAll();
$allSubjects = $subjectModel->getAll();

// Create lookup maps
$teacherMap = [];
foreach ($allTeachers as $teacher) {
    $name = strtolower(trim($teacher['name']));
    // Store with and without title
    $teacherMap[$name] = $teacher;
    
    // Remove title and store
    $cleanName = preg_replace('/^(ms\.?|mr\.?|mrs\.?|miss|prof\.?|dr\.?|sr\.?)\s+/i', '', $name);
    if ($cleanName !== $name) {
        $teacherMap[$cleanName] = $teacher;
    }
    
    // Store first name + last name
    $nameParts = explode(' ', $cleanName);
    if (count($nameParts) >= 2) {
        $teacherMap[strtolower($nameParts[0] . ' ' . $nameParts[1])] = $teacher;
        $teacherMap[strtolower($nameParts[0])] = $teacher; // First name only
    }
}

$classMap = [];
foreach ($allClasses as $class) {
    $key = strtolower(trim($class['standard'] . '-' . $class['division']));
    $classMap[$key] = $class;
    // Also map by section name if available
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
    'games' => ['games', 'game']
];

foreach ($allSubjects as $subject) {
    $name = strtolower(trim($subject['name']));
    $subjectMap[$name] = $subject;
    
    // Map variations
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

// Parse PDF
$parser = new Parser();
$pdf = $parser->parseFile($pdfPath);
$pages = $pdf->getPages();

$allText = '';
foreach ($pages as $page) {
    $allText .= $page->getText() . "\n";
}

$lines = explode("\n", $allText);
$allEntries = [];

// Day mapping
$dayMap = ['mo' => 1, 'tu' => 2, 'we' => 3, 'th' => 4, 'fr' => 5, 'sa' => 6];
$totalPeriods = 8; // Default 8 periods

// Find all teacher sections
$teacherSections = [];
for ($i = 0; $i < count($lines); $i++) {
    $line = trim($lines[$i]);
    if (empty($line)) continue;
    
    // Look for "Teacher [Name]" pattern
    if (preg_match('/Teacher\s+(Ms\.?|Mr\.?|Mrs\.?|Miss|Prof\.?|Dr\.?|Sr\.?)?\s*(.+)/i', $line, $teacherMatch)) {
        $teacherName = trim($teacherMatch[2]);
        $cleanName = preg_replace('/^(ms\.?|mr\.?|mrs\.?|miss|prof\.?|dr\.?|sr\.?)\s+/i', '', strtolower($teacherName));
        
        // Find matching teacher in database
        $teacher = null;
        
        // Try exact match first
        if (isset($teacherMap[$cleanName])) {
            $teacher = $teacherMap[$cleanName];
        } else {
            // Try partial matching - check if cleanName is contained in any teacher name
            foreach ($teacherMap as $key => $t) {
                $keyLower = strtolower($key);
                $teacherNameLower = strtolower($t['name']);
                
                // Remove titles from teacher name for comparison
                $teacherNameClean = preg_replace('/^(ms\.?|mr\.?|mrs\.?|miss|prof\.?|dr\.?|sr\.?)\s+/i', '', $teacherNameLower);
                
                // Check various matching strategies
                if ($keyLower === $cleanName || 
                    $teacherNameClean === $cleanName ||
                    stripos($keyLower, $cleanName) !== false || 
                    stripos($cleanName, $keyLower) !== false ||
                    stripos($teacherNameClean, $cleanName) !== false ||
                    stripos($cleanName, $teacherNameClean) !== false) {
                    $teacher = $t;
                    break;
                }
                
                // Try matching first name + last initial
                $cleanNameParts = explode(' ', $cleanName);
                if (count($cleanNameParts) >= 2) {
                    $firstName = $cleanNameParts[0];
                    $lastInitial = substr($cleanNameParts[1], 0, 1);
                    $teacherNameParts = explode(' ', $teacherNameClean);
                    if (count($teacherNameParts) >= 2) {
                        if (strtolower($teacherNameParts[0]) === $firstName && 
                            strtolower(substr($teacherNameParts[1], 0, 1)) === $lastInitial) {
                            $teacher = $t;
                            break;
                        }
                    }
                }
            }
        }
        
        if ($teacher) {
            // Look backwards from teacher name to find entries
            $entries = [];
            $entryIndex = 0;
            
            // Look backwards up to 200 lines
            for ($j = $i - 1; $j >= max(0, $i - 200); $j--) {
                $prevLine = trim($lines[$j]);
                if (empty($prevLine)) continue;
                
                // Stop if we hit another teacher
                if (preg_match('/Teacher\s+/i', $prevLine)) break;
                
                // Stop if we hit day headers (we've gone too far back)
                if (preg_match('/\b(Mo|Tu|We|Th|Fr|Sa)\b/i', $prevLine)) {
                    // Check if this is the start of the grid
                    // Look ahead to see if there are period numbers
                    $hasPeriods = false;
                    for ($k = $j + 1; $k < min($j + 10, $i); $k++) {
                        if (preg_match('/^\s*\d+\s*$/', trim($lines[$k]))) {
                            $hasPeriods = true;
                            break;
                        }
                    }
                    if ($hasPeriods) {
                        // This is the grid header, entries are before this
                        continue;
                    }
                }
                
                // Look for class-subject pattern like "2 - Orchid"
                if (preg_match('/(\d+)\s*-\s*([A-Za-z]+)/i', $prevLine, $classMatch)) {
                    $standard = $classMatch[1];
                    $section = trim($classMatch[2]);
                    
                    // Get subject from following lines (up to 4 lines ahead, but before teacher name)
                    $subjectText = '';
                    for ($k = $j + 1; $k < min($j + 5, $i); $k++) {
                        $subLine = trim($lines[$k]);
                        if (empty($subLine)) continue;
                        
                        // Stop if we hit another class pattern
                        if (preg_match('/(\d+)\s*-\s*([A-Za-z]+)/i', $subLine)) break;
                        // Stop if we hit day headers
                        if (preg_match('/\b(Mo|Tu|We|Th|Fr|Sa)\b/i', $subLine)) break;
                        // Stop if we hit period number
                        if (preg_match('/^\s*\d+\s*$/', $subLine)) break;
                        // Stop if we hit time
                        if (preg_match('/\d+:\d+/', $subLine)) break;
                        // Stop if we hit teacher
                        if (preg_match('/Teacher\s+/i', $subLine)) break;
                        
                        $subjectText .= ' ' . $subLine;
                    }
                    
                    $subjectText = trim($subjectText);
                    
                    if (!empty($subjectText)) {
                        // Extract Group Name if present (e.g., "Maths (Group 1)" or "Maths - Group A")
                        $groupName = null;
                        if (preg_match('/(.+?)\s*[\(-]?\s*(Group\s*\w+)[\)]?/i', $subjectText, $groupMatch)) {
                            $subjectText = trim($groupMatch[1]);
                            $groupName = trim($groupMatch[2]);
                        }

                        // Find matching class
                        $classKey = strtolower($standard . '-' . $section);
                        $class = $classMap[$classKey] ?? $classMap[strtolower($section)] ?? null;
                        
                        // Find matching subject
                        $subject = null;
                        $subjectTextLower = strtolower($subjectText);
                        
                        // Try exact match first
                        if (isset($subjectMap[$subjectTextLower])) {
                            $subject = $subjectMap[$subjectTextLower];
                        } else {
                            // Try partial matching
                            foreach ($subjectMap as $subKey => $sub) {
                                if (stripos($subjectTextLower, $subKey) !== false || 
                                    stripos($subKey, $subjectTextLower) !== false) {
                                    $subject = $sub;
                                    break;
                                }
                            }
                            
                            // If still not found, try to create it (for common subjects like Games)
                            if (!$subject && in_array($subjectTextLower, ['games', 'game'])) {
                                try {
                                    $pdo = Database::getInstance()->getConnection();
                                    $stmt = $pdo->prepare("INSERT INTO subjects (name) VALUES (?)");
                                    $stmt->execute([ucfirst($subjectTextLower)]);
                                    $newSubjectId = $pdo->lastInsertId();
                                    $newSubject = $subjectModel->find($newSubjectId);
                                    if ($newSubject) {
                                        $subject = $newSubject;
                                        $subjectMap[$subjectTextLower] = $subject;
                                        echo "  [INFO] Created new subject: {$subjectText}\n";
                                    }
                                } catch (Exception $e) {
                                    // Ignore if creation fails
                                }
                            }
                        }
                        
                        if ($class && $subject) {
                            // Insert at beginning (since we're reading backwards)
                            array_unshift($entries, [
                                'teacher_id' => $teacher['id'],
                                'class_id' => $class['id'],
                                'subject_id' => $subject['id'],
                                'class_name' => $class['standard'] . '-' . $class['division'],
                                'subject_name' => $subject['name'],
                                'group_name' => $groupName,
                                'index' => $entryIndex++
                            ]);
                        } else {
                            // Debug: show what we couldn't match
                            if (!$class) {
                                echo "  [DEBUG] Could not find class: {$standard}-{$section}\n";
                            }
                            if (!$subject) {
                                echo "  [DEBUG] Could not find subject: {$subjectText}\n";
                            }
                        }
                    }
                }
            }
            
            if (!empty($entries)) {
                // Map entries to grid positions
                // Entries are in order: Mon P1, Mon P2, ..., Mon P8, Tue P1, ...
                $mappedEntries = mapEntriesToGrid($entries, $totalPeriods);
                $allEntries = array_merge($allEntries, $mappedEntries);
                
                echo "Found teacher: {$teacher['name']} (ID: {$teacher['id']}) - " . count($entries) . " entries\n";
            } else {
                echo "Found teacher: {$teacher['name']} (ID: {$teacher['id']}) - NO ENTRIES FOUND\n";
            }
        } else {
            echo "Teacher not found in database: {$teacherName}\n";
        }
    }
}

/**
 * Map entries to grid positions based on order
 * Assumes entries are listed in order: Mon P1, Mon P2, ..., Mon P8, Tue P1, ...
 */
function mapEntriesToGrid($entries, $totalPeriods = 8) {
    $mapped = [];
    $days = 6; // Monday to Saturday
    
    foreach ($entries as $index => $entry) {
        // Calculate day and period from index
        // Index 0 = Monday Period 1, Index 1 = Monday Period 2, etc.
        $day = (int)($index / $totalPeriods) + 1;
        $period = ($index % $totalPeriods) + 1;
        
        // Skip if day exceeds 6 (Saturday)
        if ($day > 6) {
            continue;
        }
        
        $mapped[] = [
            'teacher_id' => $entry['teacher_id'],
            'class_id' => $entry['class_id'],
            'subject_id' => $entry['subject_id'],
            'day' => $day,
            'period' => $period,
            'class_name' => $entry['class_name'],
            'subject_name' => $entry['subject_name'],
            'group_name' => $entry['group_name'] ?? null
        ];
    }
    
    return $mapped;
}

echo "\n=== Parsing Complete ===\n";
echo "Total entries found: " . count($allEntries) . "\n\n";

if (empty($allEntries)) {
    echo "No entries could be parsed. Check the debug messages above.\n";
    exit(1);
}

// Count entries per teacher
$entriesPerTeacher = [];
foreach ($allEntries as $entry) {
    $teacherId = $entry['teacher_id'];
    if (!isset($entriesPerTeacher[$teacherId])) {
        $entriesPerTeacher[$teacherId] = 0;
    }
    $entriesPerTeacher[$teacherId]++;
}

echo "Summary by teacher:\n";
$dayNames = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
foreach ($entriesPerTeacher as $teacherId => $count) {
    $teacher = array_filter($allTeachers, fn($t) => $t['id'] == $teacherId);
    $teacher = reset($teacher);
    $teacherName = $teacher ? $teacher['name'] : 'Unknown';
    echo "  {$teacherName}: {$count} entries\n";
}
echo "\n";

// Show preview
echo "Preview (first 30 entries):\n";
foreach (array_slice($allEntries, 0, 30) as $entry) {
    $teacher = array_filter($allTeachers, fn($t) => $t['id'] == $entry['teacher_id']);
    $teacher = reset($teacher);
    $teacherName = $teacher ? $teacher['name'] : 'Unknown';
    echo "  {$teacherName} | {$entry['class_name']} | {$entry['subject_name']} | {$dayNames[$entry['day']]} P{$entry['period']}\n";
}

if (count($allEntries) > 30) {
    echo "  ... and " . (count($allEntries) - 30) . " more\n";
}

// Ask for confirmation
echo "\nDo you want to import these entries? (yes/no): ";
$handle = fopen("php://stdin", "r");
$response = trim(fgets($handle));
fclose($handle);

if (strtolower($response) !== 'yes') {
    echo "Import cancelled.\n";
    exit(0);
}

// Import entries
echo "\nImporting entries...\n";
$imported = 0;
$skipped = 0;
$errors = [];

foreach ($allEntries as $entry) {
    try {
        // Check if entry already exists
        $existing = $timetableModel->getTeacherSchedule($entry['teacher_id'], $entry['day']);
        $exists = false;
        foreach ($existing as $existingEntry) {
            if ($existingEntry['period_no'] == $entry['period'] && 
                $existingEntry['class_id'] == $entry['class_id'] &&
                $existingEntry['subject_id'] == $entry['subject_id']) {
                $exists = true;
                break;
            }
        }
        
        if (!$exists) {
            $timetableModel->add(
                $entry['teacher_id'],
                $entry['class_id'],
                $entry['subject_id'],
                $entry['day'],
                $entry['period'],
                $entry['group_name'] ?? null
            );
            $imported++;
        } else {
            $skipped++;
        }
    } catch (Exception $e) {
        $errors[] = "Error for {$entry['class_name']} {$entry['subject_name']} {$dayNames[$entry['day']]} P{$entry['period']}: " . $e->getMessage();
    }
}

echo "\n=== Import Complete ===\n";
echo "Imported: {$imported}\n";
echo "Skipped (already exists): {$skipped}\n";
echo "Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach (array_slice($errors, 0, 10) as $error) {
        echo "  - {$error}\n";
    }
}

echo "\nNote: The day/period mapping is based on entry order.\n";
echo "Please verify the imported data in the timetable page.\n";
