<?php
/**
 * Advanced Grid Aggregator for OCR Text
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Timetable.php';
require_once __DIR__ . '/../models/Classes.php';
require_once __DIR__ . '/../models/Subject.php';

$screenshotDir = __DIR__ . '/../data/screenshots';
$ocrHelper = __DIR__ . '/ocr_helper.js';
$images = ["Ambili.png", "Anita.png", "Daxa.png", "Nisha.png", "Purnima.png", "Mohini.png"]; // Start with the named ones

$teacherModel = new Teacher();
$classModel = new Classes();
$subjectModel = new Subject();
$allTeachers = $teacherModel->getAllWithDetails();
$allClasses = $classModel->getAll();
$allSubjects = $subjectModel->getAll();

$dayMap = ['mo' => 1, 'monday' => 1, 'tu' => 2, 'tuesday' => 2, 'we' => 3, 'wednesday' => 3, 
           'th' => 4, 'thursday' => 4, 'fr' => 5, 'friday' => 5, 'sa' => 6, 'saturday' => 6];

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
    'gujarati' => ['guj', 'gujarati', 'guara', 'guarat'],
    'hindi' => ['hindi', 'hina', 'find'],
    'hindi reading' => ['hindi reading'],
    'sanskrit' => ['sanskrit', 'sans'],
    'science' => ['science', 'sci'],
    'social studies' => ['s.s', 'social studies', 'social science'],
    'commerce' => ['com', 'commerce'],
    'library' => ['lib', 'library'],
    'games' => ['games', 'game'],
    'computer' => ['computer', 'comp', 'ict']
];

function fuzzyMatchClass($text, $allClasses) {
    if (empty($text)) return null;
    $text = strtolower(preg_replace('/[^a-z0-9]/', '', $text));
    foreach ($allClasses as $c) {
        $key = strtolower($c['standard'] . $c['division']);
        if (stripos($text, $key) !== false || (strlen($text) > 3 && stripos($key, $text) !== false)) return $c;
    }
    return null;
}

function fuzzyMatchSubject($text, $allSubjects, $subjectVariations) {
    if (empty($text)) return null;
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z]/', '', $text);
    if (strlen($text) < 2) return null;
    foreach ($allSubjects as $s) {
        $sName = strtolower($s['name']);
        if (stripos($sName, $text) !== false || stripos($text, $sName) !== false) return $s;
        foreach ($subjectVariations as $target => $vars) {
            if ($sName == $target) {
                foreach ($vars as $v) if (stripos($v, $text) !== false || stripos($text, $v) !== false) return $s;
            }
        }
    }
    return null;
}

foreach ($images as $filename) {
    $imagePath = $screenshotDir . '/' . $filename;
    if (!file_exists($imagePath)) continue;
    
    echo "--- Teacher: {$filename} ---\n";
    $command = "node " . escapeshellarg($ocrHelper) . " " . escapeshellarg($imagePath) . " 2>/dev/null";
    $text = shell_exec($command);
    
    $lines = explode("\n", $text);
    $dayLines = [];
    foreach ($lines as $lIdx => $line) {
        $lineLower = strtolower(trim($line));
        foreach ($dayMap as $dayName => $dayNum) {
            if (preg_match('/^' . $dayName . '\b/i', $lineLower)) { $dayLines[$dayNum] = $lIdx; break; }
        }
    }
    asort($dayLines);
    $dayNums = array_keys($dayLines);
    
    foreach ($dayNums as $idx => $dayNum) {
        $startLine = $dayLines[$dayNum];
        $endLine = ($idx + 1 < count($dayNums)) ? $dayLines[$dayNums[$idx+1]] : count($lines);
        $block = array_slice($lines, $startLine, $endLine - $startLine);
        
        // Aggregate all columns in this block
        $columns = [];
        foreach ($block as $line) {
            $parts = preg_split('/[|]|\s{3,}/', trim($line));
            foreach ($parts as $pIdx => $p) {
                if (empty(trim($p))) continue;
                $columns[$pIdx][] = trim($p);
            }
        }
        
        echo "Day {$dayNum}:\n";
        foreach ($columns as $cIdx => $parts) {
            // Join parts and find Class/Subject
            $fullText = implode(' ', $parts);
            $cls = fuzzyMatchClass($fullText, $allClasses);
            $sub = fuzzyMatchSubject($fullText, $allSubjects, $subjectVariations);
            
            if ($cls && $sub) {
                echo "  Period " . ($cIdx + 1) . ": {$cls['standard']}-{$cls['division']} {$sub['name']}\n";
            }
        }
    }
    echo "\n";
}
