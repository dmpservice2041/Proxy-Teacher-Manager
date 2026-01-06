<?php
$text = "Teacher Ms. Avani
PC OE FC ON FO OC FE OE
soles a0 1010 | soto rots | snro%snso | sso ras | tas 1aan | oon°racn
Hindi | Guarat Gujarat
setup | 3-orchia 5-Rose
Hindi | Hind | Gujarati | Guarat Gujarat Find:
Tu
S-Tup | 4-Rose | 6-Rose | 4-Orchid 5-Rose 3-Rose
WPT | Hina | Fina Vidi | Guarai | Hing | Gujarat
We Reading Roading
s-Tuin | S-Tuip | 4-Rose 3-Suamine | 3-Oreid | 5-Oretia | 4-Ovetia
indi Gujarati | Gujarat
Th
4-Rose S-Rose | 6-Rose
Gujarati | Gujarat | Hindi Hindi
Fr Reading
6-Roso | 3-0rchid | 3-Rose 4 dasmine
Hindi | Guiarat
5-saamina | 4-Orctid";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Classes.php';
require_once __DIR__ . '/../models/Subject.php';

$classModel = new Classes();
$subjectModel = new Subject();
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
    $text = strtolower(preg_replace('/[^a-z0-9]/', '', $text));
    foreach ($allClasses as $c) {
        $key = strtolower($c['standard'] . $c['division']);
        if ($text == $key || stripos($text, $key) !== false || stripos($key, $text) !== false) return $c;
        // Try partials like "3orchia" vs "3orchid"
        if (preg_match('/^(\d+)([a-z]+)/', $text, $m)) {
            if ($m[1] == $c['standard'] && (stripos($c['division'], $m[2]) === 0 || stripos($m[2], substr($c['division'], 0, 3)) === 0)) return $c;
        }
    }
    return null;
}

function fuzzyMatchSubject($text, $allSubjects, $subjectVariations) {
    if (empty($text)) return null;
    $text = strtolower(trim($text));
    foreach ($allSubjects as $s) {
        $sName = strtolower($s['name']);
        if ($sName == $text || stripos($sName, $text) !== false || stripos($text, $sName) !== false) return $s;
        foreach ($subjectVariations as $target => $vars) {
            foreach ($vars as $v) {
                if ($v == $text || stripos($v, $text) !== false || stripos($text, $v) !== false) {
                    if ($sName == $target) return $s;
                }
            }
        }
    }
    return null;
}

$lines = explode("\n", $text);
$dayMap = ['mo' => 1, 'monday' => 1, 'tu' => 2, 'tuesday' => 2, 'we' => 3, 'wednesday' => 3, 
           'th' => 4, 'thursday' => 4, 'fr' => 5, 'friday' => 5, 'sa' => 6, 'saturday' => 6];

$currentDay = 1; // Default Monday
$daysData = [];

foreach ($lines as $line) {
    $lineLower = strtolower(trim($line));
    foreach ($dayMap as $dayName => $dayNum) {
        if (preg_match('/^' . $dayName . '\b/i', $lineLower)) {
            $currentDay = $dayNum;
            continue 2;
        }
    }
    
    if (strpos($line, '|') !== false) {
        $parts = explode('|', $line);
        $daysData[$currentDay][] = array_map('trim', $parts);
    } else {
        // Handle lines without | but with spaces
        $parts = preg_split('/\s{2,}/', $line);
        if (count($parts) > 2) {
            $daysData[$currentDay][] = array_map('trim', $parts);
        }
    }
}

// Now correlate lines within a day
foreach ($daysData as $day => $rows) {
    echo "Day {$day}:\n";
    for ($r = 0; $r < count($rows) - 1; $r++) {
        $row1 = $rows[$r];
        $row2 = $rows[$r+1];
        
        // Try all combinations
        foreach ($row1 as $cIdx => $val1) {
            if (empty($val1)) continue;
            
            // Try Val1 as Subject, Val2 as Class OR vice versa
            $val2 = $row2[$cIdx] ?? '';
            
            $sub = fuzzyMatchSubject($val1, $allSubjects, $subjectVariations);
            $cls = fuzzyMatchClass($val2, $allClasses);
            
            if ($sub && $cls) {
                echo "  Found: {$sub['name']} in {$cls['standard']}-{$cls['division']}\n";
            } else {
                // Swap
                $sub = fuzzyMatchSubject($val2, $allSubjects, $subjectVariations);
                $cls = fuzzyMatchClass($val1, $allClasses);
                if ($sub && $cls) {
                    echo "  Found: {$sub['name']} in {$cls['standard']}-{$cls['division']}\n";
                }
            }
        }
    }
}
