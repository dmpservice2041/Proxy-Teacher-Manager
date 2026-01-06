<?php
/**
 * Test script to verify eTime Office API connection
 * Run this from command line: php scripts/test_etime_api.php
 */

require_once __DIR__ . '/../config/etime_config.php';

echo "=== eTime Office API Test ===\n\n";

// Load configuration
$config = require __DIR__ . '/../config/etime_config.php';

echo "Configuration Loaded:\n";
echo "  Corporate ID: " . $config['corporate_id'] . "\n";
echo "  Username: " . $config['username'] . "\n";
echo "  Base URL: " . $config['base_url'] . "\n\n";

// Generate auth header
$authString = sprintf(
    '%s:%s:%s:true',
    $config['corporate_id'],
    $config['username'],
    $config['password']
);
$authHeader = 'Basic ' . base64_encode($authString);

echo "Auth Header Generated: " . substr($authHeader, 0, 30) . "...\n\n";

// Test with today's date
$today = date('d/m/Y');
echo "Fetching attendance for: $today\n\n";

// Build API URL
$url = sprintf(
    '%s%s?Empcode=ALL&FromDate=%s&ToDate=%s',
    $config['base_url'],
    $config['endpoints']['inout_punch_data'],
    $today,
    $today
);

echo "API URL: $url\n\n";
echo "Making API Request...\n";

// Make request
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: ' . $authHeader,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";

if ($curlError) {
    echo "❌ cURL Error: $curlError\n";
    exit(1);
}

if ($httpCode !== 200) {
    echo "❌ HTTP Error: $httpCode\n";
    echo "Response: $response\n";
    exit(1);
}

// Decode response
$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ JSON Decode Error: " . json_last_error_msg() . "\n";
    echo "Raw Response: $response\n";
    exit(1);
}

echo "✅ API Response Received!\n\n";

// Display response structure
echo "=== API Response Structure ===\n";
echo "Error: " . ($data['Error'] ? 'true' : 'false') . "\n";
echo "Message: " . ($data['Msg'] ?? 'N/A') . "\n";
echo "IsAdmin: " . ($data['IsAdmin'] ? 'true' : 'false') . "\n";
echo "\n";

if (isset($data['InOutPunchData']) && is_array($data['InOutPunchData'])) {
    $records = $data['InOutPunchData'];
    echo "Total Records: " . count($records) . "\n\n";
    
    if (count($records) > 0) {
        echo "=== Sample Records (First 5) ===\n";
        foreach (array_slice($records, 0, 5) as $index => $record) {
            echo "\nRecord " . ($index + 1) . ":\n";
            echo "  Empcode: " . ($record['Empcode'] ?? 'N/A') . "\n";
            echo "  Name: " . ($record['Name'] ?? 'N/A') . "\n";
            echo "  Status: " . ($record['Status'] ?? 'N/A') . "\n";
            echo "  IN Time: " . ($record['INTime'] ?? 'N/A') . "\n";
            echo "  OUT Time: " . ($record['OUTTime'] ?? 'N/A') . "\n";
            echo "  Date: " . ($record['DateString'] ?? 'N/A') . "\n";
        }
        
        echo "\n=== Data Fields Available ===\n";
        if (count($records) > 0) {
            $fields = array_keys($records[0]);
            foreach ($fields as $field) {
                echo "  ✓ $field\n";
            }
        }
    } else {
        echo "⚠️  No attendance records found for $today\n";
        echo "Tip: Try a different date that has attendance data\n";
    }
} else {
    echo "❌ No InOutPunchData found in response\n";
    echo "\nFull Response:\n";
    print_r($data);
}

echo "\n=== Test Complete ===\n";
