<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/etime_config.php';
require_once __DIR__ . '/../services/ETimeService.php';

// Initialize Service
$etimeService = new ETimeService();

echo "=== Debugging API Time Fields ===\n";

$date = date('d/m/Y', strtotime("-1 day")); // Yesterday

$config = require __DIR__ . '/../config/etime_config.php';

// ... URL construction same ...
$url = sprintf(
    '%s%s?Empcode=ALL&FromDate=%s&ToDate=%s',
    $config['base_url'],
    $config['endpoints']['inout_punch_data'],
    $date,
    $date
);

echo "URL: " . $url . "\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Exact options from ETimeService:
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . base64_encode($config['username'] . ':' . $config['password'])
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['InOutPunchData']) && !empty($data['InOutPunchData'])) {
        echo "Found " . count($data['InOutPunchData']) . " records.\n";
        
        // Print the first 2 records structure
        $sample = array_slice($data['InOutPunchData'], 0, 2);
        print_r($sample);
    } else {
        echo "No InOutPunchData found or empty array.\n";
        print_r($data);
    }
} else {
    echo "API Error Code: $httpCode\n";
    echo "Response: $response\n";
}
