<?php
/**
 * Generate timetable JSON from structured text
 * This helps convert timetable data from descriptions into JSON format
 */

$dayMap = [
    'mo' => 1, 'monday' => 1,
    'tu' => 2, 'tuesday' => 2,
    'we' => 3, 'wednesday' => 3,
    'th' => 4, 'thursday' => 4,
    'fr' => 5, 'friday' => 5,
    'sa' => 6, 'saturday' => 6
];

// Example usage - you can add more teachers here
$teachers = [
    [
        'name' => 'Ms. Kalpana P',
        'entries' => [
            ['day' => 'mo', 'period' => 2, 'subject' => 'Gujarati', 'class' => '4-Jasmine'],
            ['day' => 'mo', 'period' => 3, 'subject' => 'Hindi Reading', 'class' => '5-Tulip'],
            ['day' => 'mo', 'period' => 5, 'subject' => 'Library', 'class' => '6-Rose'],
            ['day' => 'mo', 'period' => 7, 'subject' => 'Hindi', 'class' => '3-Orchid'],
            ['day' => 'tu', 'period' => 8, 'subject' => 'Library', 'class' => '3-Orchid'],
            ['day' => 'we', 'period' => 7, 'subject' => 'Gujarati', 'class' => '3-Jasmine'],
            ['day' => 'th', 'period' => 4, 'subject' => 'Library', 'class' => '3-Rose'],
            ['day' => 'fr', 'period' => 8, 'subject' => 'Library', 'class' => '6-Tulip'],
            ['day' => 'sa', 'period' => 1, 'subject' => 'Gujarati', 'class' => '5-Tulip']
        ]
    ],
    [
        'name' => 'Sr. Anit',
        'entries' => [
            ['day' => 'mo', 'period' => 5, 'subject' => 'Value Education', 'class' => '7-Jasmine'],
            ['day' => 'tu', 'period' => 6, 'subject' => 'Value Education', 'class' => '6-Rose'],
            ['day' => 'tu', 'period' => 7, 'subject' => 'Value Education', 'class' => '7-Jasmine'],
            ['day' => 'we', 'period' => 7, 'subject' => 'Value Education', 'class' => '6-Rose'],
            ['day' => 'th', 'period' => 4, 'subject' => 'Value Education', 'class' => '8-Rose']
        ]
    ],
    [
        'name' => 'Sr. Talisa',
        'entries' => [
            ['day' => 'mo', 'period' => 3, 'subject' => 'Value Education', 'class' => '1-Tulip'],
            ['day' => 'mo', 'period' => 4, 'subject' => 'Value Education', 'class' => '2-Jasmine'],
            ['day' => 'mo', 'period' => 6, 'subject' => 'English Reading', 'class' => '2-Tulip'],
            ['day' => 'mo', 'period' => 7, 'subject' => 'English', 'class' => '1-Rose'],
            ['day' => 'mo', 'period' => 8, 'subject' => 'English', 'class' => '2-Rose'],
            ['day' => 'tu', 'period' => 2, 'subject' => 'English', 'class' => '2-Rose'],
            ['day' => 'tu', 'period' => 4, 'subject' => 'English Reading', 'class' => '1-Jasmine'],
            ['day' => 'tu', 'period' => 5, 'subject' => 'Cursive Writing', 'class' => '2-Orchid'],
            ['day' => 'tu', 'period' => 6, 'subject' => 'English', 'class' => '1-Rose'],
            ['day' => 'we', 'period' => 3, 'subject' => 'English', 'class' => '2-Rose'],
            ['day' => 'we', 'period' => 5, 'subject' => 'English', 'class' => '1-Rose'],
            ['day' => 'th', 'period' => 2, 'subject' => 'English', 'class' => '2-Rose'],
            ['day' => 'th', 'period' => 5, 'subject' => 'English', 'class' => '2-Rose'],
            ['day' => 'th', 'period' => 7, 'subject' => 'English', 'class' => '1-Rose'],
            ['day' => 'th', 'period' => 8, 'subject' => 'Cursive Writing', 'class' => '1-Tulip'],
            ['day' => 'fr', 'period' => 3, 'subject' => 'English', 'class' => '2-Rose'],
            ['day' => 'fr', 'period' => 4, 'subject' => 'Value Education', 'class' => '2-Jasmine'],
            ['day' => 'fr', 'period' => 7, 'subject' => 'English', 'class' => '1-Rose'],
            ['day' => 'fr', 'period' => 8, 'subject' => 'Value Education', 'class' => '1-Tulip'],
            ['day' => 'sa', 'period' => 3, 'subject' => 'English', 'class' => '1-Rose']
        ]
    ]
];

$outputDir = __DIR__ . '/../data/timetables';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

foreach ($teachers as $teacherData) {
    $teacherName = $teacherData['name'];
    $entries = [];
    
    foreach ($teacherData['entries'] as $entry) {
        $day = $dayMap[strtolower($entry['day'])] ?? $entry['day'];
        $entries[] = [
            'day' => $day,
            'period' => $entry['period'],
            'subject' => $entry['subject'],
            'class' => $entry['class']
        ];
    }
    
    $json = [
        'teacher_name' => $teacherName,
        'entries' => $entries
    ];
    
    $filename = strtolower(str_replace([' ', '.', 'Sr.', 'Ms.', 'Mr.'], ['_', '', '', '', ''], $teacherName)) . '.json';
    $filename = preg_replace('/[^a-z0-9_]/', '', $filename);
    $filepath = $outputDir . '/' . $filename;
    
    file_put_contents($filepath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Created: {$filename} with " . count($entries) . " entries\n";
}

echo "\nDone! Run: php scripts/import_all_timetables.php\n";

