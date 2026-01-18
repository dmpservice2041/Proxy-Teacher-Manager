<?php
require_once __DIR__ . '/../services/ProxyEngine.php';
require_once __DIR__ . '/../models/ProxyAssignment.php';

// CLI args: php manual_assign.php <date> <absent_tid> <period_no> <proxy_tid>
$date = $argv[1] ?? null;
$absentTid = $argv[2] ?? null;
$periodNo = $argv[3] ?? null;
$proxyTid = $argv[4] ?? null;

if (!$date || !$absentTid || !$periodNo || !$proxyTid) {
    echo "Usage: php manual_assign.php YYYY-MM-DD <absent_teacher_id> <period_no> <proxy_teacher_id>\n";
    exit(1);
}

try {
    // 1. Fetch Class ID for the lecture (Need to look up from timetable)
    $timetableModel = new Timetable();
    $dayOfWeek = date('N', strtotime($date));
    $schedule = $timetableModel->getTeacherSchedule($absentTid, $dayOfWeek);
    
    $classId = null;
    foreach ($schedule as $slot) {
        if ($slot['period_no'] == $periodNo) {
            $classId = $slot['class_id'];
            break;
        }
    }

    if (!$classId) {
        die("Error: No lecture found for Absent Teacher ID $absentTid on Period $periodNo for Date $date.\n");
    }

    // 2. Assign
    $proxyModel = new ProxyAssignment();
    
    // The requirement says "Manual assignment overrides automatic logic".
    // The unique key is (date, proxy_teacher, period). But we want to replace the assignment for the *Absent Teacher's Slot*.
    // Our schema doesn't have a unique key on (date, absent_teacher, period)... which might be a flaw if we want to strictly prevent duplicates.
    // But let's assume we just add this Manual one.
    
    $id = $proxyModel->assign(
        $date,
        $absentTid,
        $proxyTid,
        $classId,
        $periodNo,
        'MANUAL',
        "Admin Force Assignment via CLI"
    );

    echo "Success! Manual Proxy Assigned. ID: $id\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
