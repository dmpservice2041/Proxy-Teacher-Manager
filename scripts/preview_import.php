<?php
/**
 * Enhanced Preview Timetable Extraction
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Timetable.php';
require_once __DIR__ . '/../models/Classes.php';
require_once __DIR__ . '/../models/Subject.php';

$screenshotDir = __DIR__ . '/../data/screenshots';
$ocrHelper = __DIR__ . '/ocr_helper.js';
$images = glob($screenshotDir . '/*.{png,jpg,jpeg}', GLOB_BRACE);

$teacherModel = new Teacher();
$classModel = new Classes();
$subjectModel = new Subject();

$allTeachers = $teacherModel->getAllWithDetails();
$allClasses = $classModel->getAll();
$allSubjects = $subjectModel->getAll();

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
        $cStd = $c['standard'];
        $cDiv = strtolower($c['division']);
        $key = $cStd . $cDiv;
        if ($text == $key || stripos($text, $key) !== false) return $c;
        if (preg_match('/^(\d+)([a-z]{3,})/', $text, $m)) {
            if ($m[1] == $cStd && (stripos($cDiv, substr($m[2],0,3)) === 0 || stripos($m[2], substr($cDiv,0,3)) === 0)) return $c;
        }
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
                foreach ($vars as $v) {
                    if (stripos($v, $text) !== false || stripos($text, $v) !== false) return $s;
                }
            }
        }
    }
    return null;
}

$dayMap = ['mo' => 1, 'monday' => 1, 'tu' => 2, 'tuesday' => 2, 'we' => 3, 'wednesday' => 3, 
           'th' => 4, 'thursday' => 4, 'fr' => 5, 'friday' => 5, 'sa' => 6, 'saturday' => 6];

$report = [];

foreach ($images as $index => $imagePath) {
    $filename = basename($imagePath);
    echo "Processing [".($index+1)."/".count($images)."]: {$filename}... ";
    
    $command = "node " . escapeshellarg($ocrHelper) . " " . escapeshellarg($imagePath) . " 2>/dev/null";
    $text = shell_exec($command);
    if (empty($text)) { echo "FAILED\n"; continue; }
    
    // Teacher Detection
    $foundTeacher = null;
    $lines = explode("\n", $text);
    $possibleNames = [];
    foreach ($lines as $line) {
        if (preg_match('/Teacher\s*:?\s*(.+)/i', $line, $matches)) $possibleNames[] = strtolower(trim($matches[1]));
    }
    $possibleNames[] = strtolower(preg_replace('/[^A-Za-z ]/', ' ', $filename));
    
    foreach ($possibleNames as $nameSearch) {
        $cleanSearch = preg_replace('/^(ms\.?|mr\.?|mrs\.?|miss|prof\.?|dr\.?|sr\.?)\s+/i', '', $nameSearch);
        foreach ($allTeachers as $t) {
            $dbNameClean = preg_replace('/^(ms\.?|mr\.?|mrs\.?|miss|prof\.?|dr\.?|sr\.?)\s+/i', '', strtolower($t['name']));
            if ($dbNameClean === $cleanSearch || stripos($dbNameClean, $cleanSearch) !== false || (strlen($cleanSearch) > 3 && stripos($dbNameClean, $cleanSearch) !== false)) {
                $foundTeacher = $t; break 2;
            }
        }
    }
    
    if (!$foundTeacher) { echo "TEACHER NOT FOUND\n"; continue; }
    echo "Teacher: {$foundTeacher['name']}... ";
    
    $dayLines = [];
    foreach ($lines as $lIdx => $line) {
        $lineLower = strtolower(trim($line));
        foreach ($dayMap as $dayName => $dayNum) {
            if (preg_match('/^' . $dayName . '\b/i', $lineLower)) { $dayLines[$dayNum] = $lIdx; break; }
        }
    }
    asort($dayLines);
    $dayNums = array_keys($dayLines);
    
    $entries = [];
    for ($i = 0; $i < count($dayNums); $i++) {
        $dayNum = $dayNums[$i];
        $startLine = $dayLines[$dayNum];
        $endLine = ($i + 1 < count($dayNums)) ? $dayLines[$dayNums[$i+1]] : count($lines);
        $dayLinesBlock = array_slice($lines, $startLine, $endLine - $startLine);
        
        $gridRows = [];
        foreach ($dayLinesBlock as $dlb) {
            $parts = preg_split('/[|]|\s{3,}/', $dlb);
            $parts = array_map('trim', $parts);
            $parts = array_filter($parts, function($p) { return !empty($p) && !preg_match('/^[0-9:]+$/', $p); });
            if (!empty($parts)) $gridRows[] = array_values($parts);
        }
        
        // Correlate Subjects and Classes in the day block
        // Every cell has a Subject and a Class. Sometimes they are on the same line, sometimes adjacent lines.
        $dayEntries = [];
        for ($r = 0; $r < count($gridRows); $r++) {
            $currentRow = $gridRows[$r];
            foreach ($currentRow as $cIdx => $val) {
                // Option A: Same cell has both "Class Subject"
                if (preg_match('/(\d+)[- ]([A-Za-z]+)\s+(.+)/', $val, $m) || preg_match('/(.+)\s+(\d+)[- ]([A-Za-z]+)/', $val, $m)) {
                    $cls = fuzzyMatchClass($m[1].$m[2], $allClasses);
                    $sub = fuzzyMatchSubject($m[3], $allSubjects, $subjectVariations);
                    if ($cls && $sub) { $dayEntries[] = ['cls'=>$cls, 'sub'=>$sub]; continue; }
                }
                
                // Option B: Subject in current row, Class in next row (or vice versa)
                if ($r + 1 < count($gridRows)) {
                    $nextRow = $gridRows[$r+1];
                    $valNext = $nextRow[$cIdx] ?? ($nextRow[0] ?? ''); // Fallback to first part if column shifted
                    
                    $sub = fuzzyMatchSubject($val, $allSubjects, $subjectVariations);
                    $cls = fuzzyMatchClass($valNext, $allClasses);
                    if ($sub && $cls) { $dayEntries[] = ['cls'=>$cls, 'sub'=>$sub]; continue; }
                    
                    $cls = fuzzyMatchClass($val, $allClasses);
                    $sub = fuzzyMatchSubject($valNext, $allSubjects, $subjectVariations);
                    if ($cls && $sub) { $dayEntries[] = ['cls'=>$cls, 'sub'=>$sub]; continue; }
                }
            }
        }
        
        // Map dayEntries to periods sequential
        foreach ($dayEntries as $deIdx => $de) {
            $period = $deIdx + 1;
            if ($period > 8) break;
            $entries[] = [
                'day' => $dayNum,
                'period' => $period,
                'class' => $de['cls']['standard'] . '-' . $de['cls']['division'],
                'subject' => $de['sub']['name']
            ];
        }
    }
    
    echo count($entries) . " entries.\n";
    $report[$filename] = [ 'teacher' => $foundTeacher['name'], 'entries' => $entries ];
}

file_put_contents(__DIR__ . '/../import_report.json', json_encode($report, JSON_PRETTY_PRINT));
echo "\nReport saved to import_report.json\n";
