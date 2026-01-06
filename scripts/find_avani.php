<?php
require_once __DIR__ . '/../config/app.php';

$screenshotDir = __DIR__ . '/../data/screenshots';
$ocrHelper = __DIR__ . '/ocr_helper.js';
$images = glob($screenshotDir . '/*.{png,jpg,jpeg}', GLOB_BRACE);

echo "Searching for Avani/Bhatt in " . count($images) . " images...\n";

foreach ($images as $imagePath) {
    $filename = basename($imagePath);
    $command = "node " . escapeshellarg($ocrHelper) . " " . escapeshellarg($imagePath) . " 2>/dev/null";
    $text = shell_exec($command);
    
    if (stripos($text, 'Avani') !== false || stripos($text, 'Bhatt') !== false) {
        echo "MATCH FOUND: {$filename}\n";
        echo "TEXT PREVIEW: " . substr($text, 0, 200) . "\n---\n";
    }
}
echo "Done.\n";
