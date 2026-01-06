<?php
/**
 * Analyze API data to see what's being returned vs what's in database
 * Usage: php scripts/analyze_api_data.php [date]
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/ETimeService.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Attendance.php';

$date = $argv[1] ?? date('Y-m-d');

echo "=== API Data Analysis for {$date} ===\n\n";

$etimeService = new ETimeService();
$teacherModel = new Teacher();
$attendanceModel = new Attendance();

// Get all teachers from database
$allTeachers = $teacherModel->getAllWithDetails();
$activeTeachers = $teacherModel->getAllActive();

echo "📊 Database Statistics:\n";
echo "   Total Teachers: " . count($allTeachers) . "\n";
echo "   Active Teachers: " . count($activeTeachers) . "\n";
echo "   Inactive Teachers: " . (count($allTeachers) - count($activeTeachers)) . "\n";

$withEmpcode = 0;
$withoutEmpcode = 0;
$activeWithEmpcode = 0;
$inactiveWithEmpcode = 0;

foreach ($allTeachers as $teacher) {
    if (!empty($teacher['empcode'])) {
        $withEmpcode++;
        if ($teacher['is_active']) {
            $activeWithEmpcode++;
        } else {
            $inactiveWithEmpcode++;
        }
    } else {
        $withoutEmpcode++;
    }
}

echo "   With Empcode: {$withEmpcode} (Active: {$activeWithEmpcode}, Inactive: {$inactiveWithEmpcode})\n";
echo "   Without Empcode: {$withoutEmpcode}\n\n";

// Fetch from API
echo "🔍 Fetching from API...\n";
try {
    $etimeDate = date('d/m/Y', strtotime($date));
    $config = require __DIR__ . '/../config/etime_config.php';
    
    $url = sprintf(
        '%s%s?Empcode=ALL&FromDate=%s&ToDate=%s',
        $config['base_url'],
        $config['endpoints']['inout_punch_data'],
        $etimeDate,
        $etimeDate
    );
    
    $ch = curl_init();
    $authString = sprintf('%s:%s:%s:true', $config['corporate_id'], $config['username'], $config['password']);
    $authHeader = 'Basic ' . base64_encode($authString);
    
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
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "❌ HTTP Error: {$httpCode}\n";
        exit(1);
    }
    
    $data = json_decode($response, true);
    
    if ($data['Error'] === false && isset($data['InOutPunchData'])) {
        $apiRecords = $data['InOutPunchData'];
        echo "✅ API Response Received\n";
        echo "   Total Records in API: " . count($apiRecords) . "\n\n";
        
        // Analyze API data
        $apiEmpcodes = [];
        $apiPresent = 0;
        $apiAbsent = 0;
        $apiWithTimes = 0;
        $apiWithoutTimes = 0;
        $apiStatusCounts = [];
        
        foreach ($apiRecords as $record) {
            $empcode = trim($record['Empcode'] ?? '');
            $status = trim($record['Status'] ?? '');
            $inTime = $record['INTime'] ?? null;
            $outTime = $record['OUTTime'] ?? null;
            
            if ($empcode) {
                $apiEmpcodes[] = $empcode;
            }
            
            // Count status
            if (!isset($apiStatusCounts[$status])) {
                $apiStatusCounts[$status] = 0;
            }
            $apiStatusCounts[$status]++;
            
            // Determine present/absent
            if ($inTime && $inTime !== '--:--' && $inTime !== '') {
                $apiWithTimes++;
                $apiPresent++;
            } elseif ($status === 'A' || $status === '' || (!$inTime && !$outTime)) {
                $apiAbsent++;
                if (!$inTime && !$outTime) {
                    $apiWithoutTimes++;
                }
            } else {
                // Check status mapping
                $baseStatus = strtoupper(explode('/', $status)[0]);
                if ($baseStatus === 'P' || $baseStatus === 'WO') {
                    $apiPresent++;
                } else {
                    $apiAbsent++;
                }
            }
        }
        
        echo "📊 API Data Analysis:\n";
        echo "   Unique Empcodes: " . count(array_unique($apiEmpcodes)) . "\n";
        echo "   Present (has times): {$apiPresent}\n";
        echo "   Absent (no times or status A): {$apiAbsent}\n";
        echo "   With Times: {$apiWithTimes}\n";
        echo "   Without Times: {$apiWithoutTimes}\n\n";
        
        echo "   Status Breakdown:\n";
        foreach ($apiStatusCounts as $status => $count) {
            echo "      '{$status}': {$count}\n";
        }
        echo "\n";
        
        // Compare with database
        echo "🔍 Comparison:\n";
        $dbEmpcodes = [];
        foreach ($allTeachers as $teacher) {
            if (!empty($teacher['empcode'])) {
                $dbEmpcodes[] = trim($teacher['empcode']);
            }
        }
        
        $apiEmpcodesUnique = array_unique($apiEmpcodes);
        $inApiNotInDb = array_diff($apiEmpcodesUnique, $dbEmpcodes);
        $inDbNotInApi = array_diff($dbEmpcodes, $apiEmpcodesUnique);
        
        echo "   In API but not in DB: " . count($inApiNotInDb) . "\n";
        if (count($inApiNotInDb) > 0 && count($inApiNotInDb) <= 20) {
            foreach ($inApiNotInDb as $empcode) {
                echo "      - {$empcode}\n";
            }
        }
        
        echo "   In DB but not in API: " . count($inDbNotInApi) . "\n";
        if (count($inDbNotInApi) > 0 && count($inDbNotInApi) <= 20) {
            foreach ($inDbNotInApi as $empcode) {
                $teacher = $teacherModel->findByEmpcode($empcode);
                $status = $teacher ? ($teacher['is_active'] ? 'Active' : 'Inactive') : 'Unknown';
                echo "      - {$empcode} ({$status})\n";
            }
        }
        echo "\n";
        
        // Check attendance records
        $attendanceRecords = $attendanceModel->getAllForDate($date);
        $presentCount = 0;
        $absentCount = 0;
        
        foreach ($attendanceRecords as $att) {
            if ($att['status'] === 'Present') {
                $presentCount++;
            } else {
                $absentCount++;
            }
        }
        
        echo "📋 Current Database Attendance for {$date}:\n";
        echo "   Present: {$presentCount}\n";
        echo "   Absent: {$absentCount}\n";
        echo "   Total Records: " . count($attendanceRecords) . "\n";
        
    } else {
        echo "❌ API Error: " . ($data['Msg'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Analysis Complete ===\n";

