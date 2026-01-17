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
    $result = $service->autoAllocateAll($date);
    
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
