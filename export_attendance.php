<?php
require_once 'config/app.php';
require_once 'models/Attendance.php';
require_once 'models/Teacher.php';

$attendanceModel = new Attendance();
$teacherModel = new Teacher();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? 'daily';
    
    if ($type === 'daily') {
        $date = $_POST['date'] ?? date('Y-m-d');
        exportDaily($attendanceModel, $date);
    } elseif ($type === 'range') {
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        exportRange($attendanceModel, $teacherModel, $startDate, $endDate);
    }
}

function exportDaily($model, $date) {
    $data = $model->getAllForDate($date);
    $filename = "attendance_daily_" . $date . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['EmpCode', 'Name', 'Date', 'Status', 'In Time', 'Out Time', 'Source']);
    
    foreach ($data as $row) {
        fputcsv($output, [
            $row['empcode'],
            $row['name'], 
            $row['date'],
             $row['status'],
             $row['in_time'],
             $row['out_time'],
             $row['source']
        ]);
    }
    fclose($output);
    exit;
}

function exportRange($attModel, $teachModel, $startDate, $endDate) {
    $rawData = $attModel->getAttendanceRange($startDate, $endDate);
    $allTeachers = $teachModel->getAllActive(); 
    
    $attendanceMap = [];
    foreach ($rawData as $row) {
        $attendanceMap[$row['teacher_id']][$row['date']] = $row;
    }
    
    $period = new DatePeriod(
        new DateTime($startDate),
        new DateInterval('P1D'),
        (new DateTime($endDate))->modify('+1 day')
    );
    
    $days = [];
    foreach ($period as $dt) {
        $days[] = $dt->format('Y-m-d');
    }
    
    $monthName = date('F-Y', strtotime($startDate));
    
    $filename = "attendance_report_" . $startDate . "_to_" . $endDate . ".xls";
    
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<!--[if gte mso 9]>
    <xml>
      <x:ExcelWorkbook>
        <x:ExcelWorksheets>
          <x:ExcelWorksheet>
            <x:Name>Sheet1</x:Name>
            <x:WorksheetOptions>
              <x:DisplayGridlines>False</x:DisplayGridlines>
              <x:FitToPage/>
              <x:Print>
                <x:FitHeight>0</x:FitHeight>
                <x:FitWidth>1</x:FitWidth>
                <x:ValidPrinterInfo/>
                <x:Scale>100</x:Scale>
                <x:HorizontalResolution>600</x:HorizontalResolution>
                <x:VerticalResolution>600</x:VerticalResolution>
              </x:Print>
            </x:WorksheetOptions>
          </x:ExcelWorksheet>
        </x:ExcelWorksheets>
      </x:ExcelWorkbook>
    </xml>
    <![endif]-->';
    echo '<style>
        table { border-collapse: collapse; font-family: Calibri, Arial, sans-serif; font-size: 11px; table-layout: fixed; }
        th, td { border: 1px solid #999; padding: 4px; text-align: center; vertical-align: middle; white-space: nowrap; }
        .header-main { background-color: #4F46E5; color: white; font-weight: bold; font-size: 14px; text-align: right; padding: 10px; }
        .teacher-row { font-weight: bold; text-align: left; }
        .teacher-cell { background-color: #E0E7FF; border-top: 2px solid #000; }
        .sub-header-cell { background-color: #F3F4F6; }
        .stat-box { font-weight: bold; padding: 0 5px; }
        .text-green { color: #059669; font-weight: bold; }
        .text-red { color: #DC2626; font-weight: bold; }
        .text-blue { color: #2563EB; font-weight: bold; }
        .bg-red-light { background-color: #FEE2E2; color: #DC2626; }
        .bg-green-light { background-color: #D1FAE5; color: #059669; }
        .col-header { background-color: #e5e7eb; font-weight: bold; width: 80px; text-align: left; padding-left: 5px; }
        .day-col { width: 35px; }
    </style>';
    echo '</head>';
    echo '<body>';
    
    // Calculate Total Grid Columns
    $totalGridCols = count($days) + 1; // +1 for the Metric column
    
    echo '<table>';
    
    // Header
    echo '<tr>';
    echo '<td colspan="' . $totalGridCols . '" class="header-main">Attendance Report: ' . $monthName . '</td>';
    echo '</tr>';

    foreach ($allTeachers as $teacher) {
        $tid = $teacher['id'];
        
        // Calculate Statistics
        $stats = ['P' => 0, 'A' => 0, 'WO' => 0, 'HL' => 0, 'LV' => 0];
        $totalWorkMinutes = 0;
        
        foreach ($days as $day) {
            $isSunday = (date('w', strtotime($day)) == 0);
            if (isset($attendanceMap[$tid][$day])) {
                 $st = $attendanceMap[$tid][$day]['status'];
                 $shortStatus = match($st) {
                    'Present' => 'P', 'Absent' => 'A', 'Weekly Off' => 'WO',
                    'Holiday' => 'HL', 'Leave' => 'LV', default => 'A'
                };
            } else {
                $shortStatus = $isSunday ? 'WO' : 'A';
            }
            if (isset($stats[$shortStatus])) $stats[$shortStatus]++;
            
            if (isset($attendanceMap[$tid][$day]['in_time'], $attendanceMap[$tid][$day]['out_time'])) {
                $t1 = strtotime($attendanceMap[$tid][$day]['in_time']);
                $t2 = strtotime($attendanceMap[$tid][$day]['out_time']);
                if ($t2 > $t1) $totalWorkMinutes += ($t2 - $t1) / 60;
            }
        }
        
        $totalWorkHours = floor($totalWorkMinutes / 60);
        $totalWorkMins = $totalWorkMinutes % 60;
        $workStr = sprintf("%02d:%02d", $totalWorkHours, $totalWorkMins);
        
        // --- TEACHER INFO ROW ---
        // Calculate colspans to cover the grid width exactly
        $leftColSpan = 5; // Fixed width for Name/Emp part if days allow
        if ($totalGridCols < 6) $leftColSpan = 2; // Adjust for small ranges
        $rightColSpan = $totalGridCols - $leftColSpan;
        if ($rightColSpan < 1) $rightColSpan = 1;

        echo '<tr class="teacher-row">';
        echo '<td class="teacher-cell" colspan="' . $leftColSpan . '" style="text-align: left; padding-left: 10px; overflow: hidden;">';
        echo '<span style="color:#444;">Emp:</span> ' . $teacher['empcode'] . ' | <span style="color:#000;">' . $teacher['name'] . '</span>';
        echo '</td>';
        
        echo '<td class="teacher-cell" colspan="' . $rightColSpan . '" style="text-align: left;">';
        echo '<span class="text-green stat-box" style="margin-right:10px;">P: ' . $stats['P'] . '</span>';
        echo '<span class="text-blue stat-box" style="margin-right:10px;">WO: ' . $stats['WO'] . '</span>';
        echo '<span class="text-red stat-box" style="margin-right:10px;">A: ' . $stats['A'] . '</span>';
        echo '<span class="stat-box" style="color:#666; margin-right:10px;">L/H: ' . ($stats['LV'] + $stats['HL']) . '</span>';
        echo '<span style="float:right; font-weight:normal; color:#444;">Work: <b>' . $workStr . '</b></span>';
        echo '</td>';
        echo '</tr>';
        
        // --- DATES HEADER ---
        // --- DATES HEADER ---
        echo '<tr>';
        echo '<td rowspan="2" class="col-header">Metric</td>'; 
        foreach ($days as $day) {
            echo '<td class="day-col sub-header-cell">' . date('j', strtotime($day)) . '</td>';
        }
        echo '</tr>';
        
        echo '<tr>';
        foreach ($days as $day) {
            $dName = date('D', strtotime($day));
            $color = ($dName == 'Sun') ? 'color:red;' : '';
            echo '<td class="day-col sub-header-cell" style="font-size:10px; ' . $color . '">' . $dName . '</td>';
        }
        echo '</tr>';
        
        // --- IN ---
        echo '<tr>';
        echo '<td class="col-header">IN</td>';
        foreach ($days as $day) {
            $val = isset($attendanceMap[$tid][$day]['in_time']) ? date('H:i', strtotime($attendanceMap[$tid][$day]['in_time'])) : '-';
            echo '<td style="color:#555;">' . $val . '</td>';
        }
        echo '</tr>';
        
        // --- OUT ---
        echo '<tr>';
        echo '<td class="col-header">OUT</td>';
        foreach ($days as $day) {
             $val = isset($attendanceMap[$tid][$day]['out_time']) ? date('H:i', strtotime($attendanceMap[$tid][$day]['out_time'])) : '-';
             echo '<td style="color:#555;">' . $val . '</td>';
        }
        echo '</tr>';
        
        // --- WORK ---
        echo '<tr>';
        echo '<td class="col-header">WORK</td>';
         foreach ($days as $day) {
            $in = $attendanceMap[$tid][$day]['in_time'] ?? null;
            $out = $attendanceMap[$tid][$day]['out_time'] ?? null;
            $dur = '-';
            if ($in && $out) {
                 $diff = strtotime($out) - strtotime($in);
                 if ($diff > 0) $dur = gmdate('H:i', $diff);
            }
            echo '<td style="font-size:10px;">' . $dur . '</td>';
        }
        echo '</tr>';
        
        // --- STATUS ---
        echo '<tr>';
        echo '<td class="col-header">Status</td>';
        foreach ($days as $day) {
            $isSunday = (date('w', strtotime($day)) == 0);
            if (isset($attendanceMap[$tid][$day])) {
                $code = match($attendanceMap[$tid][$day]['status']) {
                    'Present' => 'P', 'Absent' => 'A', 'Weekly Off' => 'WO',
                    'Holiday' => 'HL', 'Leave' => 'LV', default => 'A'
                };
            } else {
                 $code = $isSunday ? 'WO' : 'A';
            }
            
            $style = match($code) {
                'P' => 'class="text-green bg-green-light"',
                'A' => 'class="text-red bg-red-light"',
                'WO' => 'class="text-blue"',
                default => ''
            };
            echo '<td ' . $style . '>' . $code . '</td>';
        }
        echo '</tr>';
        
        // Divider
        echo '<tr><td colspan="' . $totalGridCols . '" style="border:none; height: 15px;"></td></tr>';
    }
    
    echo '</table>';
    echo '</body></html>';
    exit;
}
