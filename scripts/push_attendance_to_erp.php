<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/AttendanceService.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? date('Y-m-d');
$service = new AttendanceService();

try {
    $attendanceRecords = $service->getAttendanceForDate($date);
    
    if (empty($attendanceRecords)) {
        echo json_encode(['success' => false, 'message' => 'No attendance records found for this date.']);
        exit;
    }

    $payload = [];
    foreach ($attendanceRecords as $record) {
        // We only push "presents" as punch logs to the ERP
        if ($record['status'] === 'Present' && !empty($record['in_time'])) {
            // Combine date and in_time for punchDateTime
            $punchDateTime = $date . ' ' . $record['in_time'];
            
            // We use empcode if available, otherwise database ID (fallback)
            // Most ERPs expect the biometric unique ID (empcode)
            $empId = !empty($record['empcode']) ? $record['empcode'] : $record['teacher_id'];

            $payload[] = [
                'empUniqueID' => (string)$empId,
                'punchDateTime' => $punchDateTime
            ];
        }
    }

    if (empty($payload)) {
        echo json_encode(['success' => false, 'message' => 'No "Present" records with punch times to sync.']);
        exit;
    }

    // --- API Configuration ---
    $url = 'https://entab.online/api/OpenAPI/PostStaffAttendanceAPI';
    $headerKey = 'AyjyrW05tN1HUCOHI1aZMQ==';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'HeaderKey: ' . $headerKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo json_encode(['success' => false, 'message' => 'CURL Error: ' . $curlError]);
    } else {
        // Return the exact response from the ERP API
        echo json_encode([
            'success' => $httpCode >= 200 && $httpCode < 300,
            'api_response' => json_decode($response, true) ?: $response,
            'http_code' => $httpCode,
            'records_sent' => count($payload)
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Internal Error: ' . $e->getMessage()]);
}
