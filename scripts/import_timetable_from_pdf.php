<?php
/**
 * Import Timetable from PDF - Advanced Parser
 * Parses the specific PDF format and imports timetable data
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

echo "=== Importing Timetable from PDF ===\n\n";

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
    // Remove prefixes
    $cleanName = preg_replace('/^(ms\.?|mr\.?|mrs\.?|miss|prof\.?|dr\.?|sr\.?)\s+/i', '', $name);
    $teacherMap[$cleanName] = $teacher;
    $teacherMap[$name] = $teacher;
    
    // Also map first name + last name
    $nameParts = explode(' ', $cleanName);
    if (count($nameParts) >= 2) {
        $teacherMap[strtolower($nameParts[0] . ' ' . $nameParts[1])] = $teacher;
    }
}

$classMap = [];
foreach ($allClasses as $class) {
    $key = strtolower(trim($class['standard'] . '-' . $class['division']));
    $classMap[$key] = $class;
    
    // Map by section name (like "Orchid", "Rose", "Tulip", "Jasmine")
    if (!empty($class['section_name'])) {
        $classMap[strtolower(trim($class['section_name']))] = $class;
    }
}

$subjectMap = [];
$subjectVariations = [
    'mathematics' => ['maths', 'math', 'mathematics'],
    'english grammar' => ['english grammar', 'eng grammar'],
    'english fl' => ['english fl', 'english', 'eng fl'],
    'english reading' => ['english reading', 'eng reading'],
    'evs' => ['evs', 'environment', 'environmental'],
    'gk' => ['gk', 'general knowledge'],
    'v.ed' => ['v.ed', 'value education'],
    'conv' => ['conv', 'conversation'],
    'cw' => ['cw', 'cursive writing'],
    'mpt' => ['mpt'],
    'pt' => ['pt', 'physical training'],
    'drawing' => ['drawing'],
    'guj' => ['guj', 'gujarati'],
    'hindi' => ['hindi'],
    'sanskrit' => ['sanskrit'],
    'science' => ['science', 'sci'],
    's.s' => ['s.s', 'social studies', 'social science']
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
$entries = [];
$currentTeacher = null;
$currentEntries = [];
$inTeacherSection = false;

// Day mapping
$dayMap = ['mo' => 1, 'tu' => 2, 'we' => 3, 'th' => 4, 'fr' => 5, 'sa' => 6];

// Parse the text
for ($i = 0; $i < count($lines); $i++) {
    $line = trim($lines[$i]);
    if (empty($line)) continue;
    
    // Look for "Teacher [Name]" pattern
    if (preg_match('/Teacher\s+(Ms\.?|Mr\.?|Mrs\.?|Miss|Prof\.?|Dr\.?|Sr\.?)?\s*(.+)/i', $line, $teacherMatch)) {
        // Save previous teacher's entries
        if ($currentTeacher && !empty($currentEntries)) {
            $entries = array_merge($entries, $currentEntries);
        }
        
        $teacherName = trim($teacherMatch[2]);
        $cleanName = preg_replace('/^(ms\.?|mr\.?|mrs\.?|miss|prof\.?|dr\.?|sr\.?)\s+/i', '', strtolower($teacherName));
        
        $currentTeacher = null;
        foreach ($teacherMap as $key => $teacher) {
            if (stripos($key, $cleanName) !== false || stripos($cleanName, $key) !== false) {
                $currentTeacher = $teacher;
                echo "Found teacher: {$teacher['name']} (ID: {$teacher['id']})\n";
                break;
            }
        }
        
        $currentEntries = [];
        $inTeacherSection = true;
        continue;
    }
    
    // If we're in a teacher section, look for class-subject patterns
    if ($currentTeacher && $inTeacherSection) {
        // Look for pattern like "2 - Orchid" or "1 - Rose"
        if (preg_match('/(\d+)\s*-\s*([A-Za-z]+)/i', $line, $classMatch)) {
            $standard = $classMatch[1];
            $section = trim($classMatch[2]);
            
            // Look for subject in next 3 lines
            $subjectText = '';
            for ($j = $i + 1; $j < min($i + 4, count($lines)); $j++) {
                $nextLine = trim($lines[$j]);
                if (empty($nextLine)) continue;
                
                // Skip if it's another class pattern
                if (preg_match('/(\d+)\s*-\s*([A-Za-z]+)/i', $nextLine)) {
                    break;
                }
                
                // Skip if it's a day header
                if (preg_match('/\b(mo|tu|we|th|fr|sa)\b/i', $nextLine)) {
                    break;
                }
                
                // Skip if it's a period number
                if (preg_match('/^\s*\d+\s*$/', $nextLine)) {
                    break;
                }
                
                // Skip if it's time
                if (preg_match('/\d+:\d+/', $nextLine)) {
                    break;
                }
                
                $subjectText .= ' ' . $nextLine;
            }
            
            $subjectText = trim($subjectText);
            
            if (!empty($subjectText)) {
                // Find matching class
                $classKey = strtolower($standard . '-' . $section);
                $class = $classMap[$classKey] ?? $classMap[strtolower($section)] ?? null;
                
                // Find matching subject
                $subject = null;
                foreach ($subjectMap as $subKey => $sub) {
                    if (stripos($subjectText, $subKey) !== false) {
                        $subject = $sub;
                        break;
                    }
                }
                
                if ($class && $subject) {
                    // We found a class-subject pair
                    // Now we need to determine day and period
                    // The entries appear in order, so we'll need to track position
                    // For now, we'll create entries that need manual day/period assignment
                    $currentEntries[] = [
                        'teacher_id' => $currentTeacher['id'],
                        'teacher_name' => $currentTeacher['name'],
                        'class_id' => $class['id'],
                        'class_name' => $class['standard'] . '-' . $class['division'],
                        'subject_id' => $subject['id'],
                        'subject_name' => $subject['name'],
                        'raw_text' => $line . ' ' . $subjectText
                    ];
                    
                    echo "  Found: {$class['standard']}-{$class['division']} -> {$subject['name']}\n";
                } else {
                    if (!$class) {
                        echo "  Warning: Class not found for '{$standard}-{$section}'\n";
                    }
                    if (!$subject) {
                        echo "  Warning: Subject not found for '{$subjectText}'\n";
                    }
                }
            }
        }
        
        // If we see day headers, we've moved past the entries list
        if (preg_match('/\b(mo|tu|we|th|fr|sa)\b/i', $line)) {
            $inTeacherSection = false;
        }
    }
}

// Save final teacher's entries
if ($currentTeacher && !empty($currentEntries)) {
    $entries = array_merge($entries, $currentEntries);
}

echo "\n=== Parsing Summary ===\n";
echo "Total entries found: " . count($entries) . "\n\n";

if (empty($entries)) {
    echo "No entries could be parsed. Please check the PDF format.\n";
    exit(1);
}

// Group entries by teacher
$byTeacher = [];
foreach ($entries as $entry) {
    $tid = $entry['teacher_id'];
    if (!isset($byTeacher[$tid])) {
        $byTeacher[$tid] = [];
    }
    $byTeacher[$tid][] = $entry;
}

echo "Entries by teacher:\n";
foreach ($byTeacher as $tid => $teacherEntries) {
    $teacher = $teacherMap[array_search($tid, array_column($allTeachers, 'id'))] ?? null;
    if ($teacher) {
        echo "  {$teacher['name']}: " . count($teacherEntries) . " entries\n";
    }
}

echo "\n=== IMPORTANT ===\n";
echo "The parser found class-subject pairs but cannot automatically determine\n";
echo "which day and period they belong to from the PDF text extraction.\n\n";
echo "You have two options:\n";
echo "1. Manually enter the timetable using the web interface (timetable.php)\n";
echo "2. Provide the data in Excel/CSV format for easier import\n\n";
echo "The parsed entries have been identified but need day/period assignment.\n";
echo "Please use the timetable.php page to add entries manually, or provide\n";
echo "the timetable in a structured format (Excel/CSV).\n";

