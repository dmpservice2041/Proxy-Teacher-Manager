<?php
/**
 * Import Timetable from JPG Image
 * Parses a JPG image of a teacher's timetable and imports the data
 * 
 * Usage: php import_timetable_from_jpg.php <teacher_name> <jpg_file_path>
 * Example: php import_timetable_from_jpg.php "Ms. Bhavna" /path/to/bhavna_timetable.jpg
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Timetable.php';
require_once __DIR__ . '/../models/Classes.php';
require_once __DIR__ . '/../models/Subject.php';

use Smalot\PdfParser\Parser;

// Check arguments
if ($argc < 3) {
    die("Usage: php import_timetable_from_jpg.php <teacher_name> <jpg_file_path>\n");
}

$teacherName = $argv[1];
$jpgPath = $argv[2];

if (!file_exists($jpgPath)) {
    die("Error: JPG file not found: {$jpgPath}\n");
}

echo "=== Import Timetable from JPG ===\n";
echo "Teacher: {$teacherName}\n";
echo "File: {$jpgPath}\n\n";

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
    'games' => ['games', 'game']
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

// For JPG, we'll need OCR or manual parsing
// For now, let's use a simple approach: convert JPG to text using OCR if available
// Otherwise, we'll need to parse manually

echo "Note: JPG parsing requires OCR. Checking for available OCR tools...\n\n";

// Try to use tesseract OCR if available
$ocrAvailable = false;
$textOutput = '';

// Check if tesseract is available
exec('which tesseract 2>/dev/null', $output, $return);
if ($return === 0) {
    $ocrAvailable = true;
    echo "Tesseract OCR found. Extracting text from image...\n";
    
    // Create temp file for OCR output
    $tempDir = sys_get_temp_dir();
    $tempFile = $tempDir . '/ocr_output_' . time();
    
    // Run tesseract with better settings for tables
    $command = "tesseract " . escapeshellarg($jpgPath) . " " . escapeshellarg($tempFile) . " -l eng 2>&1";
    exec($command, $ocrOutput, $ocrReturn);
    
    if ($ocrReturn === 0 && file_exists($tempFile . '.txt')) {
        $textOutput = file_get_contents($tempFile . '.txt');
        
        // Save OCR output for debugging
        $debugFile = __DIR__ . '/../temp/ocr_output_' . basename($jpgPath, '.jpg') . '_' . time() . '.txt';
        if (!is_dir(dirname($debugFile))) {
            mkdir(dirname($debugFile), 0755, true);
        }
        file_put_contents($debugFile, $textOutput);
        echo "Text extracted successfully.\n";
        echo "OCR output saved to: {$debugFile}\n\n";
        
        unlink($tempFile . '.txt');
    } else {
        echo "OCR extraction failed. Error: " . implode("\n", $ocrOutput) . "\n";
        echo "Please check the image file and try again.\n";
        exit(1);
    }
} else {
    echo "ERROR: Tesseract OCR not found!\n\n";
    echo "Please install tesseract-ocr first:\n";
    echo "  macOS:    brew install tesseract\n";
    echo "  Ubuntu:   sudo apt-get install tesseract-ocr\n";
    echo "  Windows:  Download from https://github.com/UB-Mannheim/tesseract/wiki\n\n";
    echo "After installation, run this script again.\n";
    exit(1);
}

// Parse the extracted text
$lines = explode("\n", $textOutput);
$entries = [];

// Day mapping
$dayMap = ['mo' => 1, 'monday' => 1, 'tu' => 2, 'tuesday' => 2, 'we' => 3, 'wednesday' => 3, 
           'th' => 4, 'thursday' => 4, 'fr' => 5, 'friday' => 5, 'sa' => 6, 'saturday' => 6];
$totalPeriods = 8;

// Parse the text to find timetable entries
// Strategy: Build a grid structure first, then parse entries within each cell
$grid = []; // [day][period] = array of text lines in that cell

// First pass: Identify day and period headers to build grid structure
$dayHeaders = [];
$periodHeaders = [];

for ($i = 0; $i < count($lines); $i++) {
    $line = trim($lines[$i]);
    if (empty($line)) continue;
    
    // Look for day headers
    foreach ($dayMap as $dayName => $dayNum) {
        if (preg_match('/\b' . preg_quote($dayName, '/') . '\b/i', $line)) {
            $dayHeaders[] = ['line' => $i, 'day' => $dayNum, 'text' => $line];
        }
    }
    
    // Look for period numbers (1-8) - usually standalone
    if (preg_match('/^\s*(\d+)\s*$/', $line, $periodMatch)) {
        $periodNum = (int)$periodMatch[1];
        if ($periodNum >= 1 && $periodNum <= 8) {
            $periodHeaders[] = ['line' => $i, 'period' => $periodNum, 'text' => $line];
        }
    }
}

echo "Found " . count($dayHeaders) . " day headers and " . count($periodHeaders) . " period headers\n";

// Second pass: Parse entries by looking for class-subject patterns
// We'll use a simpler approach: scan for patterns and try to associate them with nearby day/period markers
$entries = [];
$currentDay = null;
$currentPeriod = null;

// Track the last seen day/period to use as context
$lastDayLine = -1;
$lastPeriodLine = -1;

for ($i = 0; $i < count($lines); $i++) {
    $line = trim($lines[$i]);
    if (empty($line)) continue;
    
    // Update current day if we see a day header
    foreach ($dayHeaders as $dayHeader) {
        if ($dayHeader['line'] == $i) {
            $currentDay = $dayHeader['day'];
            $lastDayLine = $i;
            echo "Found day: Day {$currentDay} at line {$i}\n";
        }
    }
    
    // Update current period if we see a period header
    foreach ($periodHeaders as $periodHeader) {
        if ($periodHeader['line'] == $i) {
            $currentPeriod = $periodHeader['period'];
            $lastPeriodLine = $i;
            echo "Found period: Period {$currentPeriod} at line {$i}\n";
        }
    }
    
    // Look for class pattern: "7 - Orchid" or "5 - Rose"
    if (preg_match('/(\d+)\s*-\s*([A-Za-z]+)/i', $line, $classMatch)) {
        $standard = $classMatch[1];
        $section = trim($classMatch[2]);
        
        // Try to find subject in the same line (after the class)
        $subjectText = '';
        $lineAfterClass = substr($line, strpos($line, $section) + strlen($section));
        $lineAfterClass = trim($lineAfterClass);
        
        // Check if there's text after the class that might be a subject
        if (!empty($lineAfterClass) && !preg_match('/(\d+)\s*-\s*([A-Za-z]+)/i', $lineAfterClass)) {
            $subjectText = $lineAfterClass;
        }
        
        // If no subject in same line, check previous line (subject might come before class)
        if (empty($subjectText) && $i > 0) {
            $prevLine = trim($lines[$i - 1]);
            if (!empty($prevLine) && !preg_match('/(\d+)\s*-\s*([A-Za-z]+)/i', $prevLine) && 
                !preg_match('/^\s*\d+\s*$/', $prevLine) &&
                !preg_match('/\b(mo|tu|we|th|fr|sa|monday|tuesday|wednesday|thursday|friday|saturday)\b/i', $prevLine)) {
                $subjectText = $prevLine;
            }
        }
        
        // If still no subject, check next line
        if (empty($subjectText) && $i < count($lines) - 1) {
            $nextLine = trim($lines[$i + 1]);
            if (!empty($nextLine) && !preg_match('/(\d+)\s*-\s*([A-Za-z]+)/i', $nextLine) &&
                !preg_match('/^\s*\d+\s*$/', $nextLine) &&
                !preg_match('/\b(mo|tu|we|th|fr|sa|monday|tuesday|wednesday|thursday|friday|saturday)\b/i', $nextLine)) {
                $subjectText = $nextLine;
            }
        }
        
        $subjectText = trim($subjectText);
        
        // Use the most recent day/period context if available
        // If current day/period not set, try to infer from position
        $day = $currentDay;
        $period = $currentPeriod;
        
        // If we don't have explicit day/period, try to infer from line position
        // This is a heuristic - may need adjustment based on actual OCR output
        if (!$day || !$period) {
            // Find nearest day header before this line
            foreach ($dayHeaders as $dayHeader) {
                if ($dayHeader['line'] < $i && ($dayHeader['line'] > $lastDayLine || $lastDayLine == -1)) {
                    $day = $dayHeader['day'];
                    break;
                }
            }
            
            // Find nearest period header before this line
            foreach ($periodHeaders as $periodHeader) {
                if ($periodHeader['line'] < $i && ($periodHeader['line'] > $lastPeriodLine || $lastPeriodLine == -1)) {
                    $period = $periodHeader['period'];
                    break;
                }
            }
        }
        
        if ($day && $period && !empty($subjectText)) {
            // Find matching class
            $classKey = strtolower($standard . '-' . $section);
            $class = $classMap[$classKey] ?? $classMap[strtolower($section)] ?? null;
            
            // Find matching subject
            $subject = null;
            $subjectTextLower = strtolower($subjectText);
            if (isset($subjectMap[$subjectTextLower])) {
                $subject = $subjectMap[$subjectTextLower];
            } else {
                foreach ($subjectMap as $subKey => $sub) {
                    if (stripos($subjectTextLower, $subKey) !== false || 
                        stripos($subKey, $subjectTextLower) !== false) {
                        $subject = $sub;
                        break;
                    }
                }
            }
            
            if ($class && $subject) {
                $entries[] = [
                    'teacher_id' => $teacher['id'],
                    'class_id' => $class['id'],
                    'subject_id' => $subject['id'],
                    'day' => $day,
                    'period' => $period,
                    'class_name' => $class['standard'] . '-' . $class['division'],
                    'subject_name' => $subject['name']
                ];
                echo "  Entry: Day {$day}, Period {$period} - {$class['standard']}-{$class['division']} - {$subject['name']}\n";
            } else {
                if (!$class) echo "  [WARN] Class not found: {$standard}-{$section}\n";
                if (!$subject) echo "  [WARN] Subject not found: {$subjectText}\n";
            }
        }
    }
    
    // Also look for standalone subject names (subject appears first, then class in next line)
    foreach ($subjectMap as $subKey => $sub) {
        if (strlen($subKey) > 2 && stripos($line, $subKey) !== false && 
            !preg_match('/(\d+)\s*-\s*([A-Za-z]+)/i', $line)) {
            // Subject found, look for class in next lines
            for ($j = $i + 1; $j < min($i + 3, count($lines)); $j++) {
                $nextLine = trim($lines[$j]);
                if (preg_match('/(\d+)\s*-\s*([A-Za-z]+)/i', $nextLine, $classMatch2)) {
                    $standard2 = $classMatch2[1];
                    $section2 = trim($classMatch2[2]);
                    
                    $day2 = $currentDay;
                    $period2 = $currentPeriod;
                    
                    // Try to infer day/period if not set
                    if (!$day2 || !$period2) {
                        foreach ($dayHeaders as $dayHeader) {
                            if ($dayHeader['line'] < $i) {
                                $day2 = $dayHeader['day'];
                                break;
                            }
                        }
                        foreach ($periodHeaders as $periodHeader) {
                            if ($periodHeader['line'] < $i) {
                                $period2 = $periodHeader['period'];
                                break;
                            }
                        }
                    }
                    
                    if ($day2 && $period2) {
                        $classKey2 = strtolower($standard2 . '-' . $section2);
                        $class2 = $classMap[$classKey2] ?? $classMap[strtolower($section2)] ?? null;
                        
                        if ($class2) {
                            $entries[] = [
                                'teacher_id' => $teacher['id'],
                                'class_id' => $class2['id'],
                                'subject_id' => $sub['id'],
                                'day' => $day2,
                                'period' => $period2,
                                'class_name' => $class2['standard'] . '-' . $class2['division'],
                                'subject_name' => $sub['name']
                            ];
                            echo "  Entry: Day {$day2}, Period {$period2} - {$class2['standard']}-{$class2['division']} - {$sub['name']}\n";
                        }
                    }
                    break;
                }
            }
            break;
        }
    }
}

echo "\n=== Parsing Complete ===\n";
echo "Total entries found: " . count($entries) . "\n\n";

if (empty($entries)) {
    echo "No entries could be parsed from the image.\n";
    echo "The OCR text extraction may need manual review.\n";
    echo "\nExtracted text preview:\n";
    echo substr($textOutput, 0, 500) . "...\n";
    exit(1);
}

// Show preview
echo "Preview of entries to import:\n";
$dayNames = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
foreach ($entries as $entry) {
    echo "  {$dayNames[$entry['day']]} P{$entry['period']} - {$entry['class_name']} - {$entry['subject_name']}\n";
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

foreach ($entries as $entry) {
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
                null // group_name
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

echo "\nDone!\n";

