<?php
/**
 * Debug script to check attendance status for a specific employee
 * Usage: php scripts/debug_attendance.php [empcode] [date]
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../services/ETimeService.php';

$empcode = $argv[1] ?? '21';
$date = $argv[2] ?? date('Y-m-d');

echo "=== Attendance Debug for Employee Code: {$empcode} on {$date} ===\n\n";

$teacherModel = new Teacher();
$attendanceModel = new Attendance();
$etimeService = new ETimeService();

// Find teacher
$teacher = $teacherModel->findByEmpcode($empcode);
if (!$teacher) {
    echo "❌ Teacher not found with empcode: {$empcode}\n";
    exit(1);
}

echo "✅ Teacher Found:\n";
echo "   ID: {$teacher['id']}\n";
echo "   Name: {$teacher['name']}\n";
echo "   Empcode: {$teacher['empcode']}\n";
echo "   Active: " . ($teacher['is_active'] ? 'Yes' : 'No') . "\n\n";

// Check current attendance in database
$attendance = $attendanceModel->getAttendanceForTeacher($teacher['id'], $date);
if ($attendance) {
    echo "📊 Current Database Status:\n";
    echo "   Status: {$attendance['status']}\n";
    echo "   IN Time: " . ($attendance['in_time'] ?? 'NULL') . "\n";
    echo "   OUT Time: " . ($attendance['out_time'] ?? 'NULL') . "\n";
    echo "   Source: {$attendance['source']}\n";
    echo "   Date: {$attendance['date']}\n\n";
} else {
    echo "⚠️  No attendance record found in database for this date\n\n";
}

// Fetch from API to see what API says
echo "🔍 Fetching from API...\n";
try {
    $result = $etimeService->fetchDailyAttendance($date);
    
    if ($result['success']) {
        echo "✅ API Fetch Successful\n";
        echo "   Message: {$result['message']}\n";
        echo "   Records Processed: {$result['records']}\n\n";
        
        // Check if teacher was in skipped list
        if (!empty($result['skipped'])) {
            foreach ($result['skipped'] as $skipped) {
                if (strtolower(trim($skipped['empcode'])) === strtolower(trim($empcode))) {
                    echo "⚠️  Teacher was in skipped list:\n";
                    echo "   Reason: {$skipped['reason']}\n\n";
                }
            }
        }
    } else {
        echo "❌ API Fetch Failed: {$result['message']}\n\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n\n";
}

// Check attendance again after fetch
$attendanceAfter = $attendanceModel->getAttendanceForTeacher($teacher['id'], $date);
if ($attendanceAfter) {
    echo "📊 Status After API Fetch:\n";
    echo "   Status: {$attendanceAfter['status']}\n";
    echo "   IN Time: " . ($attendanceAfter['in_time'] ?? 'NULL') . "\n";
    echo "   OUT Time: " . ($attendanceAfter['out_time'] ?? 'NULL') . "\n\n";
    
    if ($attendance && $attendance['status'] !== $attendanceAfter['status']) {
        echo "✅ Status was updated!\n";
        echo "   Before: {$attendance['status']}\n";
        echo "   After: {$attendanceAfter['status']}\n";
    } elseif ($attendance && $attendance['status'] === $attendanceAfter['status']) {
        echo "⚠️  Status did not change (still {$attendanceAfter['status']})\n";
    }
} else {
    echo "⚠️  Still no attendance record after API fetch\n";
}

echo "\n=== Debug Complete ===\n";

