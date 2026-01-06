<?php
require_once __DIR__ . '/../services/ProxyEngine.php';
require_once __DIR__ . '/../services/AttendanceService.php';
require_once __DIR__ . '/../reports/ProxyExcelReport.php';
// require_once __DIR__ . '/../reports/ProxyPdfReport.php';

// Set Date (Default to today, or pass via CLI argument or GET)
$isWeb = php_sapi_name() !== 'cli';
$date = $_GET['date'] ?? ($argv[1] ?? date('Y-m-d'));

if ($isWeb) {
    header('Content-Type: application/json');
} else {
    echo "--------------------------------------------------\n";
    echo "Daily Proxy Generation for: $date\n";
    echo "--------------------------------------------------\n";
}

try {
    // 1. Initialize Services
    $engine = new ProxyEngine();
    $attendanceService = new AttendanceService();

    // 2. Run Engine (Optional: only if you want auto-fill)
    // Actually, for reports, we might just want to generate files from existing assignments.
    // If we want to ensure any missing auto-proxies are run:
    // $logs = $engine->generateProxies($date);

    // 3. Generate Reports
    if ($isWeb) {
        $output = ['success' => true, 'logs' => [], 'files' => []];
    }

    // Check if report classes exist (soft check for dependencies)
    if (class_exists('ProxyExcelReport')) {
        $reportParams = new ProxyExcelReport();
        $file = $reportParams->generateDailyReport($date);
        if ($isWeb) {
            $output['files']['excel'] = 'exports/' . $file;
        } else {
            echo " > Excel Report saved: $file\n";
        }
    }

    if ($isWeb) {
        echo json_encode($output);
    } else {
        echo "--------------------------------------------------\n";
        echo "Done.\n";
    }

} catch (Exception $e) {
    if ($isWeb) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } else {
        echo "\n[ERROR] " . $e->getMessage() . "\n";
        exit(1);
    }
}
