<?php
/**
 * Batch Import Timetables from Screenshots
 * Scans data/screenshots, runs OCR, and imports data.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Timetable.php';
require_once __DIR__ . '/../models/Classes.php';
require_once __DIR__ . '/../models/Subject.php';

$screenshotDir = __DIR__ . '/../data/screenshots';
$ocrHelper = __DIR__ . '/ocr_helper.js';

if (!is_dir($screenshotDir)) {
    die("Error: Directory not found: {$screenshotDir}\n");
}

$images = glob($screenshotDir . '/*.{png,jpg,jpeg}', GLOB_BRACE);

if (empty($images)) {
    die("No images found in: {$screenshotDir}\n");
}

echo "=== Batch Import Timetables from Screenshots ===\n";
echo "Found " . count($images) . " images\n\n";

// Initialize models
$teacherModel = new Teacher();
$timetableModel = new Timetable();
$classModel = new Classes();
$subjectModel = new Subject();

// Get all data for mapping
$allTeachers = $teacherModel->getAllWithDetails();
$allClasses = $classModel->getAll();
$allSubjects = $subjectModel->getAll();

// Create lookup maps (case-insensitive)
$teacherMap = [];
foreach ($allTeachers as $t) {
    $name = strtolower(trim($t['name']));
    $teacherMap[$name] = $t;
    // Also map without title
    $cleanName = preg_replace('/^(ms\.?|mr\.?|mrs\.?|miss|prof\.?|dr\.?|sr\.?)\s+/i', '', $name);
    $teacherMap[$cleanName] = $t;
}

$classMap = [];
foreach ($allClasses as $c) {
    $key = strtolower(trim($c['standard'] . '-' . $c['division']));
    $classMap[$key] = $c;
    if (!empty($c['division'])) {
        $classMap[strtolower(trim($c['division']))] = $c;
    }
}

$subjectMap = [];
foreach ($allSubjects as $s) {
    $name = strtolower(trim($s['name']));
    $subjectMap[$name] = $s;
}

$dayMap = ['mo' => 1, 'monday' => 1, 'tu' => 2, 'tuesday' => 2, 'we' => 3, 'wednesday' => 3, 
           'th' => 4, 'thursday' => 4, 'fr' => 5, 'friday' => 5, 'sa' => 6, 'saturday' => 6];

foreach ($images as $index => $imagePath) {
    $filename = basename($imagePath);
    echo "[" . ($index + 1) . "/" . count($images) . "] Processing: {$filename}\n";
    
    // Run OCR via Node.js helper
    $command = "node " . escapeshellarg($ocrHelper) . " " . escapeshellarg($imagePath) . " 2>/dev/null";
    $text = shell_exec($command);
    
    if (empty($text)) {
        echo "  [ERROR] OCR failed or returned no text.\n";
        continue;
    }
    
    // Parse the text
    $lines = explode("\n", $text);
    $currentTeacher = null;
    $entriesFound = 0;
    
    // Try to find teacher name first (from text or filename)
    $foundTeacher = null;
    $possibleNames = [];
    
    foreach ($lines as $line) {
        if (preg_match('/Teacher\s*:?\s*(.+)/i', $line, $matches)) {
            $possibleNames[] = strtolower(trim($matches[1]));
        }
    }
    
    // Fallback: also try to find names in the filename
    $possibleNames[] = strtolower(preg_replace('/[^A-Za-z ]/', ' ', $filename));
    
    foreach ($possibleNames as $nameSearch) {
        $cleanSearch = preg_replace('/^(ms\.?|mr\.?|mrs\.?|miss|prof\.?|dr\.?|sr\.?)\s+/i', '', $nameSearch);
        $searchParts = explode(' ', $cleanSearch);
        
        foreach ($allTeachers as $t) {
            $dbName = strtolower(trim($t['name']));
            $dbNameClean = preg_replace('/^(ms\.?|mr\.?|mrs\.?|miss|prof\.?|dr\.?|sr\.?)\s+/i', '', $dbName);
            $dbParts = explode(' ', $dbNameClean);
            
            // Strategy 1: Exact or stripos match
            if ($dbName === $nameSearch || $dbNameClean === $cleanSearch || 
                stripos($dbNameClean, $cleanSearch) !== false || stripos($cleanSearch, $dbNameClean) !== false) {
                $foundTeacher = $t;
                break 2;
            }
            
            // Strategy 2: First name match (if search is just one name like "Avani")
            if (count($searchParts) === 1 && !empty($searchParts[0])) {
                foreach ($dbParts as $part) {
                    if ($part === $searchParts[0] && strlen($part) > 3) {
                        $foundTeacher = $t;
                        break 3;
                    }
                }
            }
            
            // Strategy 3: Partial name parts match (e.g. "Lisna Vincent" matches "Lisna ... Vincent")
            $matchCount = 0;
            foreach ($searchParts as $sp) {
                if (strlen($sp) < 3) continue;
                foreach ($dbParts as $dp) {
                    if ($sp === $dp) $matchCount++;
                }
            }
            if ($matchCount >= 2 || ($matchCount >= 1 && count($searchParts) === 1)) {
                $foundTeacher = $t;
                break 2;
            }
        }
    }
    
    $currentTeacher = $foundTeacher;
    
    if (!$currentTeacher) {
        echo "  [WARN] Teacher not identified in text. Skipping script-based mapping for this file.\n";
        // In a real scenario, we might want to manually assign or use a smart mapping like import_timetable_smart.php
        continue;
    }
    
    echo "  Found teacher: {$currentTeacher['name']}\n";
    
    // Use smart mapping logic
    $entries = [];
    $currentDay = null;
    $periodCount = 8;
    
    // Strategy: Identify days and their associated lines
    $dayLines = [];
    foreach ($lines as $idx => $line) {
        $line = trim($line);
        foreach ($dayMap as $dayName => $dayNum) {
            // Using slightly more flexible regex for Day headers
            if (preg_match('/^' . preg_quote($dayName, '/') . '\b|^\W*' . preg_quote($dayName, '/') . '\W*$/i', $line)) {
                $dayLines[$dayNum] = $idx;
                break;
            }
        }
    }
    
    // Sort days by line number
    asort($dayLines);
    
    $dayNums = array_keys($dayLines);
    for ($i = 0; $i < count($dayNums); $i++) {
        $dayNum = $dayNums[$i];
        $startLine = $dayLines[$dayNum];
        $endLine = ($i + 1 < count($dayNums)) ? $dayLines[$dayNums[$i+1]] : count($lines);
        
        // Inside a day, we look for class-subject pairs
        // The OCR might put class and subject on different lines or same line
        // Pattern 1: "9-Rose" then "Hindi" (multi-line)
        // Pattern 2: "9-Rose Hindi" (same line)
        
        $dayTextLines = array_slice($lines, $startLine, $endLine - $startLine);
        $dayEntries = [];
        
        // Temporary storage for text that looks like class-subject
        $potentialPairs = [];
        foreach ($dayTextLines as $dtl) {
            $dtl = trim($dtl);
            if (empty($dtl) || preg_match('/Teacher/i', $dtl) || preg_match('/^\d+$/', $dtl)) continue;
            
            // Normalize separators
            $dtl = str_replace(['_', '—-'], '-', $dtl);
            
            // Find class patterns like "9-Rose", "10.Rose", "10-Tulip"
            if (preg_match('/(\d+)[\-\. ]\s*([A-Za-z]+)/i', $dtl, $matches)) {
                $std = $matches[1];
                $div = $matches[2];
                
                // Content after the class might be the subject
                $subjectTag = trim(substr($dtl, strpos($dtl, $div) + strlen($div)));
                $subjectTag = preg_replace('/[^A-Za-z ]/', '', $subjectTag);
                
                $potentialPairs[] = [
                    'std' => $std,
                    'div' => $div,
                    'subject' => $subjectTag
                ];
            } else if (!empty($potentialPairs) && empty($potentialPairs[count($potentialPairs)-1]['subject'])) {
                // If previous line was a class without subject, this line might be the subject
                $potentialPairs[count($potentialPairs)-1]['subject'] = preg_replace('/[^A-Za-z ]/', '', $dtl);
            }
        }
        
        // Map these pairs to periods (sequential assumption based on timetable structure)
        foreach ($potentialPairs as $pIdx => $pair) {
            $period = $pIdx + 1;
            if ($period > $periodCount) break;
            
            $std = $pair['std'];
            $div = strtolower($pair['div']);
            $subTxt = strtolower(trim($pair['subject'] ?? ''));
            
            $class = null;
            // Fuzzy match class
            foreach ($classMap as $ck => $c) {
                if (stripos($ck, $std) !== false && stripos($ck, $div) !== false) {
                    $class = $c;
                    break;
                }
            }
            if (!$class && isset($classMap[$div])) $class = $classMap[$div];
            
            if ($class && !empty($subTxt)) {
                $subject = null;
                // Fuzzy match subject
                foreach ($subjectMap as $sk => $s) {
                    if ($sk === $subTxt || stripos($subTxt, $sk) !== false || stripos($sk, $subTxt) !== false) {
                        $subject = $s;
                        break;
                    }
                }
                
                if ($subject) {
                    $entries[] = [
                        'teacher_id' => $currentTeacher['id'],
                        'class_id' => $class['id'],
                        'subject_id' => $subject['id'],
                        'day' => $dayNum,
                        'period' => $period,
                        'class_name' => $class['standard'] . '-' . $class['division'],
                        'subject_name' => $subject['name']
                    ];
                    $entriesFound++;
                }
            }
        }
    }
    
    echo "  Parsed {$entriesFound} entries.\n";
    
    // If dry run, just show entries
    if (isset($argv[1]) && $argv[1] === '--dry-run') {
        foreach ($entries as $e) {
            echo "    - Day {$e['day']} P{$e['period']}: {$e['class_name']} - {$e['subject_name']}\n";
        }
    } else {
        // Perform actual import
        foreach ($entries as $e) {
            try {
                // Check if exists
                $existing = $timetableModel->getTeacherSchedule($e['teacher_id'], $e['day']);
                $exists = false;
                foreach ($existing as $ex) {
                    if ($ex['period_no'] == $e['period']) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    $timetableModel->add($e['teacher_id'], $e['class_id'], $e['subject_id'], $e['day'], $e['period']);
                }
            } catch (Exception $ex) {
                echo "    [ERROR] Failed to add entry: " . $ex->getMessage() . "\n";
            }
        }
    }
}

echo "\nDone!\n";
