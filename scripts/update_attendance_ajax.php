<?php
require_once __DIR__ . '/../config/app.php';

// Authentication required
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/../services/AttendanceService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$teacherId = $_POST['teacher_id'] ?? null;
$date = $_POST['date'] ?? null;
$status = $_POST['status'] ?? null;

if (!$teacherId || !$date || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    $service = new AttendanceService();
    // 'API' is the source, sticking to what was in attendance.php
    $service->markAttendance($teacherId, $date, $status, 'API');
    
    // Determine new button/badge states for frontend convenience
    $newStatus = $status; // 'Present' or 'Absent'
    
    echo json_encode([
        'success' => true, 
        'new_status' => $newStatus,
        'message' => 'Attendance updated successfully'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
