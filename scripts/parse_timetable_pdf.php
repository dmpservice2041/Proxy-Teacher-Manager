<?php
/**
 * Parse Timetable PDF and extract structured data
 * This script analyzes the PDF structure and extracts timetable entries
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

echo "=== Parsing Timetable PDF ===\n\n";

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
    // Remove common prefixes
    $name = preg_replace('/^(ms\.?|mr\.?|mrs\.?|miss|prof\.?|dr\.?)\s+/i', '', $name);
    $teacherMap[$name] = $teacher;
    
    // Also try with original name
    $teacherMap[strtolower(trim($teacher['name']))] = $teacher;
}

$classMap = [];
foreach ($allClasses as $class) {
    $key = strtolower(trim($class['standard'] . '-' . $class['division']));
    $classMap[$key] = $class;
    // Also map by section name if available
    if (!empty($class['section_name'])) {
        $classMap[strtolower(trim($class['section_name']))] = $class;
    }
}

$subjectMap = [];
foreach ($allSubjects as $subject) {
    $name = strtolower(trim($subject['name']));
    $subjectMap[$name] = $subject;
    
    // Map variations
    $variations = [
        'mathematics' => 'maths',
        'math' => 'maths',
        'english grammar' => 'english grammar',
        'english fl' => 'english fl',
        'english reading' => 'english reading',
        'environment' => 'evs',
        'evs' => 'evs',
        'general knowledge' => 'gk',
        'gk' => 'gk',
        'value education' => 'v.ed',
        'v.ed' => 'v.ed',
        'conversation' => 'conv',
        'conv' => 'conv',
        'cursive writing' => 'cw',
        'cw' => 'cw'
    ];
    
    foreach ($variations as $var => $match) {
        if (stripos($name, $match) !== false || stripos($match, $name) !== false) {
            $subjectMap[$var] = $subject;
        }
    }
}

// Parse PDF
$parser = new Parser();
$pdf = $parser->parseFile($pdfPath);
$pages = $pdf->getPages();

echo "PDF has " . count($pages) . " pages\n\n";

$allText = '';
foreach ($pages as $pageNum => $page) {
    $allText .= $page->getText() . "\n";
}

// Save extracted text
file_put_contents(__DIR__ . '/../temp/pdf_full_text.txt', $allText);
echo "Full text saved to temp/pdf_full_text.txt\n\n";

// Parse the text
$lines = explode("\n", $allText);
$entries = [];
$currentTeacher = null;
$currentTeacherId = null;
$inTimetableSection = false;
$dayHeaders = ['mo', 'tu', 'we', 'th', 'fr', 'sa'];
$dayMap = ['mo' => 1, 'tu' => 2, 'we' => 3, 'th' => 4, 'fr' => 5, 'sa' => 6];

$period = 0;
$day = 0;

// Look for teacher names and their timetables
for ($i = 0; $i < count($lines); $i++) {
    $line = trim($lines[$i]);
    if (empty($line)) continue;
    
    // Check if this line contains a teacher name
    foreach ($teacherMap as $nameKey => $teacher) {
        $teacherName = $teacher['name'];
        $nameParts = explode(' ', strtolower($teacherName));
        
        // Check if line contains teacher name
        if (stripos($line, $teacherName) !== false || 
            (count($nameParts) > 1 && stripos($line, $nameParts[0]) !== false && stripos($line, $nameParts[1]) !== false)) {
            $currentTeacher = $teacher;
            $currentTeacherId = $teacher['id'];
            $inTimetableSection = false;
            echo "Found teacher: {$teacherName} (ID: {$teacher['id']})\n";
            continue 2;
        }
    }
    
    // Look for day headers (Mo Tu We Th Fr Sa)
    if (preg_match('/\b(mo|tu|we|th|fr|sa)\b/i', $line)) {
        $inTimetableSection = true;
        echo "  Found timetable grid for teacher\n";
        continue;
    }
    
    // Look for period numbers (1, 2, 3, etc.)
    if ($inTimetableSection && preg_match('/^\s*(\d+)\s*$/', $line, $periodMatch)) {
        $period = (int)$periodMatch[1];
        continue;
    }
    
    // Look for class-subject patterns like "2 - Orchid" followed by subject
    if ($currentTeacher && preg_match('/(\d+)\s*-\s*([A-Za-z]+)/i', $line, $classMatch)) {
        $standard = $classMatch[1];
        $section = trim($classMatch[2]);
        
        // Look for subject in next few lines
        $subjectFound = null;
        for ($j = $i + 1; $j < min($i + 5, count($lines)); $j++) {
            $nextLine = trim($lines[$j]);
            if (empty($nextLine)) continue;
            
            // Check if this line is a subject
            foreach ($subjectMap as $subKey => $subject) {
                if (stripos($nextLine, $subKey) !== false || stripos($nextLine, $subject['name']) !== false) {
                    $subjectFound = $subject;
                    break 2;
                }
            }
        }
        
        if ($subjectFound) {
            // Try to find class
            $classKey = strtolower($standard . '-' . $section);
            $class = $classMap[$classKey] ?? null;
            
            if (!$class) {
                // Try to find by section name only
                $class = $classMap[strtolower($section)] ?? null;
            }
            
            if ($class && $currentTeacherId) {
                // We found a class-subject pair, but we need to find which day/period
                // This is complex - we'll need to track the grid position
                echo "    Found: {$standard}-{$section} -> {$subjectFound['name']}\n";
                
                // For now, we'll need manual mapping or better grid parsing
                // This is a simplified version - you may need to adjust based on actual PDF structure
            }
        }
    }
}

echo "\n=== Parsing Complete ===\n";
echo "Note: PDF table parsing is complex. The extracted text has been saved.\n";
echo "Please review temp/pdf_full_text.txt and use the web interface for manual import.\n";

