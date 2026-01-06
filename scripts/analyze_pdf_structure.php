<?php
/**
 * Analyze PDF Structure to understand the format
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Smalot\PdfParser\Parser;

$pdfPath = $argv[1] ?? '/Users/darpanpatel/Downloads/Teachers Time Table R3.pdf';

if (!file_exists($pdfPath)) {
    die("Error: PDF file not found: {$pdfPath}\n");
}

echo "=== PDF Structure Analysis ===\n\n";

$parser = new Parser();
$pdf = $parser->parseFile($pdfPath);
$pages = $pdf->getPages();

echo "Total Pages: " . count($pages) . "\n\n";

// Analyze first few pages to understand structure
for ($pageNum = 0; $pageNum < min(3, count($pages)); $pageNum++) {
    $page = $pages[$pageNum];
    $text = $page->getText();
    $lines = explode("\n", $text);
    
    echo "=== Page " . ($pageNum + 1) . " ===\n";
    echo "Total lines: " . count($lines) . "\n\n";
    
    // Find teacher sections
    $teacherFound = false;
    $entryCount = 0;
    $inEntrySection = false;
    
    for ($i = 0; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) continue;
        
        // Look for teacher name
        if (preg_match('/Teacher\s+(.+)/i', $line, $match)) {
            $teacherName = trim($match[1]);
            echo "Teacher: {$teacherName}\n";
            $teacherFound = true;
            $entryCount = 0;
            $inEntrySection = false;
            continue;
        }
        
        // Look for day headers
        if (preg_match('/\b(Mo|Tu|We|Th|Fr|Sa)\b/i', $line)) {
            if ($teacherFound) {
                echo "  Day headers found at line " . ($i + 1) . "\n";
                echo "  Entries before headers: {$entryCount}\n";
            }
            continue;
        }
        
        // Look for period numbers
        if (preg_match('/^\s*(\d+)\s*$/', $line)) {
            continue;
        }
        
        // Look for class-subject pattern
        if (preg_match('/(\d+)\s*-\s*([A-Za-z]+)/i', $line)) {
            if ($teacherFound) {
                $entryCount++;
                if ($entryCount <= 5) {
                    echo "  Entry {$entryCount}: {$line}\n";
                    // Show next few lines for subject
                    for ($j = $i + 1; $j < min($i + 4, count($lines)); $j++) {
                        $nextLine = trim($lines[$j]);
                        if (!empty($nextLine) && !preg_match('/(\d+)\s*-\s*([A-Za-z]+)/i', $nextLine)) {
                            echo "    -> {$nextLine}\n";
                        }
                    }
                }
            }
        }
    }
    
    echo "\n";
}

// Now analyze the full structure pattern
echo "=== Full Structure Pattern ===\n";
$allText = '';
foreach ($pages as $page) {
    $allText .= $page->getText() . "\n";
}

$lines = explode("\n", $allText);
$teachers = [];
$currentTeacher = null;
$currentEntries = [];

for ($i = 0; $i < count($lines); $i++) {
    $line = trim($lines[$i]);
    if (empty($line)) continue;
    
    // Find teacher
    if (preg_match('/Teacher\s+(.+)/i', $line, $match)) {
        // Save previous teacher
        if ($currentTeacher && !empty($currentEntries)) {
            $teachers[$currentTeacher] = $currentEntries;
        }
        
        $currentTeacher = trim($match[1]);
        $currentEntries = [];
        
        // Look backwards for entries (entries come BEFORE teacher name)
        $entryIndex = 0;
        for ($j = $i - 1; $j >= max(0, $i - 200); $j--) {
            $prevLine = trim($lines[$j]);
            if (empty($prevLine)) continue;
            
            // Stop if we hit another teacher
            if (preg_match('/Teacher\s+/i', $prevLine)) break;
            
            // Stop if we hit day headers (we've gone too far back)
            if (preg_match('/\b(Mo|Tu|We|Th|Fr|Sa)\b/i', $prevLine)) {
                // This is the start of the grid, entries are before this
                continue;
            }
            
            // Look for class-subject pattern
            if (preg_match('/(\d+)\s*-\s*([A-Za-z]+)/i', $prevLine, $classMatch)) {
                $standard = $classMatch[1];
                $section = trim($classMatch[2]);
                
                // Get subject from following lines
                $subjectText = '';
                for ($k = $j + 1; $k < min($j + 4, $i); $k++) {
                    $subLine = trim($lines[$k]);
                    if (empty($subLine)) continue;
                    if (preg_match('/(\d+)\s*-\s*([A-Za-z]+)/i', $subLine)) break;
                    if (preg_match('/Teacher\s+/i', $subLine)) break;
                    $subjectText .= ' ' . $subLine;
                }
                
                $subjectText = trim($subjectText);
                if (!empty($subjectText)) {
                    array_unshift($currentEntries, [
                        'class' => "{$standard}-{$section}",
                        'subject' => $subjectText,
                        'index' => $entryIndex++
                    ]);
                }
            }
        }
    }
}

// Save last teacher
if ($currentTeacher && !empty($currentEntries)) {
    $teachers[$currentTeacher] = $currentEntries;
}

echo "\nFound " . count($teachers) . " teachers with entries:\n\n";
foreach ($teachers as $teacher => $entries) {
    echo "{$teacher}: " . count($entries) . " entries\n";
    if (count($entries) > 0) {
        echo "  First 3: ";
        foreach (array_slice($entries, 0, 3) as $entry) {
            echo "{$entry['class']}->{$entry['subject']} ";
        }
        echo "\n";
        echo "  Last 3: ";
        foreach (array_slice($entries, -3) as $entry) {
            echo "{$entry['class']}->{$entry['subject']} ";
        }
        echo "\n";
    }
}

echo "\n=== Analysis Complete ===\n";

