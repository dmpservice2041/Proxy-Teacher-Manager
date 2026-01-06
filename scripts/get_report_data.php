<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? date('Y-m-d');
$dayOfWeek = date('N', strtotime($date));

try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare("
        SELECT 
            pa.date,
            pa.period_no,
            CONCAT(c.standard, '-', c.division) as class_name,
            t_absent.name as absent_teacher,
            t_proxy.name as proxy_teacher,
            pa.mode,
            s.name as subject_name
        FROM proxy_assignments pa
        JOIN teachers t_absent ON pa.absent_teacher_id = t_absent.id
        JOIN teachers t_proxy ON pa.proxy_teacher_id = t_proxy.id
        JOIN classes c ON pa.class_id = c.id
        LEFT JOIN timetable tt ON (
            tt.class_id = pa.class_id 
            AND tt.period_no = pa.period_no 
            AND tt.day_of_week = ?
        )
        LEFT JOIN subjects s ON tt.subject_id = s.id
        WHERE pa.date = ?
        ORDER BY pa.period_no ASC
    ");
    $stmt->execute([$dayOfWeek, $date]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
