<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? date('Y-m-d');
$dayOfWeek = date('N', strtotime($date));

require_once __DIR__ . '/../models/ProxyAssignment.php';

header('Content-Type: application/json');

try {
    $filters = [];
    
    // Parse filters from GET request
    $reportType = $_GET['report_type'] ?? 'daily';
    
    if ($reportType === 'daily') {
         $filters['date'] = $_GET['date'] ?? date('Y-m-d');
    } else {
         if (!empty($_GET['start_date'])) $filters['start_date'] = $_GET['start_date'];
         if (!empty($_GET['end_date'])) $filters['end_date'] = $_GET['end_date'];
         if (!empty($_GET['teacher_id'])) $filters['teacher_id'] = $_GET['teacher_id'];
         if (!empty($_GET['class_id'])) $filters['class_id'] = $_GET['class_id'];
    }

    $model = new ProxyAssignment();
    $data = $model->getReportData($filters);

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
