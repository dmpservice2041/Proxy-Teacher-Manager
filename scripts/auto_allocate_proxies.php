<?php
require_once __DIR__ . '/../config/app.php';

// Authentication required
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/../services/ProxyAllocationService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$date = $_POST['date'] ?? null;

if (!$date) {
    echo json_encode(['success' => false, 'message' => 'Date is required']);
    exit;
}

try {
    $service = new ProxyAllocationService();
    
    // VALIDATION: Check if any attendance exists for this date
    $attendanceModel = new Attendance();
    $presentCount = $attendanceModel->isPresent(1, $date) ? 1 : 0; // Poor check, need count method or use service
    // Better: Check raw count query or use existing model method
    // Attendance::getAllForDate returns array.
    $presentTeachers = $attendanceModel->getAllForDate($date);
    $hasPresent = false;
    foreach ($presentTeachers as $pt) {
        if ($pt['status'] === 'Present') {
            $hasPresent = true;
            break;
        }
    }
    
    if (!$hasPresent) {
         echo json_encode([
            'success' => false, 
            'message' => 'No teachers are marked Present for this date. Please mark attendance first.'
        ]);
        exit;
    }

    $result = $service->autoAllocateAll($date);
    
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
