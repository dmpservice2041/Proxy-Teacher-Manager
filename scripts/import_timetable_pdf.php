<?php
/**
 * Import Timetable from PDF
 * This script parses a PDF timetable file and imports the data into the database
 * 
 * Usage: php scripts/import_timetable_pdf.php [pdf_file_path]
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Timetable.php';
require_once __DIR__ . '/../models/Classes.php';
require_once __DIR__ . '/../models/Subject.php';

// Use PDF parser library
require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;

$pdfPath = $argv[1] ?? '/Users/darpanpatel/Downloads/Teachers Time Table R3.pdf';

if (!file_exists($pdfPath)) {
    die("Error: PDF file not found: {$pdfPath}\n");
}

echo "=== Timetable PDF Import Tool ===\n\n";
echo "PDF File: {$pdfPath}\n";

// Initialize models
$teacherModel = new Teacher();
$timetableModel = new Timetable();
$classModel = new Classes();
$subjectModel = new Subject();

// Get all teachers, classes, and subjects for matching
$allTeachers = $teacherModel->getAllWithDetails();
$allClasses = $classModel->getAll();
$allSubjects = $subjectModel->getAll();

// Create lookup maps
$teacherMap = [];
foreach ($allTeachers as $teacher) {
    $name = strtolower(trim($teacher['name']));
    $teacherMap[$name] = $teacher;
    // Also map by empcode if available
    if (!empty($teacher['empcode'])) {
        $teacherMap['empcode_' . $teacher['empcode']] = $teacher;
    }
}

$classMap = [];
foreach ($allClasses as $class) {
    $key = strtolower(trim($class['standard'] . '-' . $class['division']));
    $classMap[$key] = $class;
}

$subjectMap = [];
foreach ($allSubjects as $subject) {
    $name = strtolower(trim($subject['name']));
    $subjectMap[$name] = $subject;
    // Map common abbreviations
    $abbrevs = [
        'maths' => 'maths',
        'math' => 'maths',
        'eng' => 'english',
        'eng fl' => 'english fl',
        'hindi' => 'hindi',
        'sanskrit' => 'sanskrit',
        'sci' => 'science',
        'science' => 'science',
        'ss' => 's.s',
        'evs' => 'evs',
        'guj' => 'guj',
        'pt' => 'pt',
        'gk' => 'gk'
    ];
    foreach ($abbrevs as $abbrev => $full) {
        if (strpos($name, $full) !== false) {
            $subjectMap[$abbrev] = $subject;
        }
    }
}

echo "Loaded:\n";
echo "  - Teachers: " . count($allTeachers) . "\n";
echo "  - Classes: " . count($allClasses) . "\n";
echo "  - Subjects: " . count($allSubjects) . "\n\n";

// Extract text from PDF
echo "Extracting text from PDF...\n";
$pdfText = '';

try {
    $parser = new Parser();
    $pdf = $parser->parseFile($pdfPath);
    $pdfText = $pdf->getText();
    
    if (empty($pdfText)) {
        // Try extracting from pages individually
        $pages = $pdf->getPages();
        foreach ($pages as $page) {
            $pdfText .= $page->getText() . "\n";
        }
    }
} catch (Exception $e) {
    die("Error extracting text from PDF: " . $e->getMessage() . "\n");
}

if (empty($pdfText)) {
    die("Error: Could not extract text from PDF. The PDF might be image-based or encrypted.\n");
}

echo "Text extracted: " . strlen($pdfText) . " characters\n\n";

// Save extracted text to a file for review
$textFile = __DIR__ . '/../temp/pdf_extracted_text.txt';
file_put_contents($textFile, $pdfText);
echo "Extracted text saved to: {$textFile}\n";
echo "Please review the extracted text to understand the format.\n\n";

// Parse the text (this is a basic parser - may need customization based on PDF format)
echo "Parsing timetable data...\n";
$lines = explode("\n", $pdfText);
$entries = [];
$currentTeacher = null;
$dayMap = [
    'monday' => 1, 'mon' => 1,
    'tuesday' => 2, 'tue' => 2,
    'wednesday' => 3, 'wed' => 3,
    'thursday' => 4, 'thu' => 4,
    'friday' => 5, 'fri' => 5,
    'saturday' => 6, 'sat' => 6
];

$periodPattern = '/p\s*(\d+)|period\s*(\d+)|period\s*(\d+)/i';
$classPattern = '/(\d+)[\s\-]*([a-z]+)/i'; // e.g., "10-A" or "10 A"

foreach ($lines as $lineNum => $line) {
    $line = trim($line);
    if (empty($line)) continue;
    
    // Try to identify teacher name
    foreach ($teacherMap as $key => $teacher) {
        $teacherName = strtolower($teacher['name']);
        if (stripos($line, $teacherName) !== false && strlen($line) < 100) {
            $currentTeacher = $teacher;
            echo "Found teacher: {$teacher['name']}\n";
            break;
        }
    }
    
    // Try to parse timetable entries
    // This is a basic parser - you may need to customize based on your PDF format
    if ($currentTeacher && preg_match($classPattern, $line, $classMatch)) {
        // Found a class reference
        $standard = $classMatch[1];
        $division = strtoupper(trim($classMatch[2]));
        $classKey = strtolower($standard . '-' . $division);
        
        if (isset($classMap[$classKey])) {
            $class = $classMap[$classKey];
            
            // Look for day and period in the same or next lines
            $nextLines = array_slice($lines, $lineNum, 5);
            foreach ($nextLines as $nextLine) {
                foreach ($dayMap as $dayName => $dayNum) {
                    if (stripos($nextLine, $dayName) !== false) {
                        // Found day, look for period
                        if (preg_match($periodPattern, $nextLine, $periodMatch)) {
                            $period = (int)($periodMatch[1] ?? $periodMatch[2] ?? $periodMatch[3] ?? 0);
                            
                            // Look for subject
                            foreach ($subjectMap as $subName => $subject) {
                                if (stripos($nextLine, $subName) !== false) {
                                    $entries[] = [
                                        'teacher_id' => $currentTeacher['id'],
                                        'teacher_name' => $currentTeacher['name'],
                                        'class_id' => $class['id'],
                                        'class_name' => $class['standard'] . '-' . $class['division'],
                                        'subject_id' => $subject['id'],
                                        'subject_name' => $subject['name'],
                                        'day' => $dayNum,
                                        'period' => $period,
                                        'line' => $nextLine
                                    ];
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

echo "\nParsed entries: " . count($entries) . "\n\n";

if (empty($entries)) {
    echo "No entries could be parsed automatically.\n";
    echo "Please review the extracted text file and provide the format details.\n";
    echo "Or consider converting the PDF to Excel/CSV format for easier import.\n";
    exit(1);
}

// Show preview
echo "Preview of parsed entries (first 10):\n";
foreach (array_slice($entries, 0, 10) as $entry) {
    $dayName = ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$entry['day']];
    echo "  {$entry['teacher_name']} | {$entry['class_name']} | {$entry['subject_name']} | {$dayName} P{$entry['period']}\n";
}

if (count($entries) > 10) {
    echo "  ... and " . (count($entries) - 10) . " more\n";
}

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
                $existingEntry['class_id'] == $entry['class_id']) {
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
                null // group_name - can be set later
            );
            $imported++;
        } else {
            $skipped++;
        }
    } catch (Exception $e) {
        $errors[] = "Error importing {$entry['teacher_name']} - {$entry['class_name']} - {$entry['subject_name']}: " . $e->getMessage();
    }
}

echo "\n=== Import Complete ===\n";
echo "Imported: {$imported}\n";
echo "Skipped (already exists): {$skipped}\n";
echo "Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
}

echo "\nNote: The PDF parser is basic and may need customization based on your PDF format.\n";
echo "If many entries were missed, please review the extracted text file and adjust the parser.\n";

