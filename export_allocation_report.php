<?php
require_once 'config/app.php';
require_once 'models/ProxyAssignment.php';

header('Content-Type: application/json');

if (!is_dir('exports')) {
    mkdir('exports', 0777, true);
}

$reportType = $_POST['report_type'] ?? 'daily';
$filters = [];
$titleSuffix = "";

try {
    if ($reportType === 'daily') {
        $date = $_POST['date'] ?? date('Y-m-d');
        $filters['date'] = $date;
        $filename = "Allocation_Daily_" . $date . ".xls";
        $title = "Daily Allocation Report: " . date('d M Y', strtotime($date));
    } else {
        $startDate = $_POST['start_date'] ?? date('Y-m-01');
        $endDate = $_POST['end_date'] ?? date('Y-m-t');
        $filters['start_date'] = $startDate;
        $filters['end_date'] = $endDate;
        $filename = "Allocation_Report_" . $startDate . "_to_" . $endDate . ".xls";
        $title = "Allocation Report: " . date('d M Y', strtotime($startDate)) . " to " . date('d M Y', strtotime($endDate));
        
        if ($reportType === 'teacher' && !empty($_POST['teacher_id'])) {
            $filters['teacher_id'] = $_POST['teacher_id'];
            $filename = "Teacher_Allocation_" . $startDate . "_" . $endDate . ".xls";
        }
        if ($reportType === 'class' && !empty($_POST['class_id'])) {
            $filters['class_id'] = $_POST['class_id'];
            $filename = "Class_Allocation_" . $startDate . "_" . $endDate . ".xls";
        }
    }

    $data = (new ProxyAssignment())->getReportData($filters);

    ob_start();

    // HTML Structure
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
                <x:PaperSizeIndex>9</x:PaperSizeIndex>
                <x:Scale>100</x:Scale>
                <x:HorizontalResolution>600</x:HorizontalResolution>
                <x:VerticalResolution>600</x:VerticalResolution>
              </x:Print>
              <x:PageSetup>
                <x:Layout x:Orientation="Portrait"/>
              </x:PageSetup>
            </x:WorksheetOptions>
          </x:ExcelWorksheet>
        </x:ExcelWorksheets>
      </x:ExcelWorkbook>
    </xml>
    <![endif]-->';
    echo '<style>
        body { background-color: #FFFFFF; font-family: Calibri, Arial, sans-serif; font-size: 11px; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        th, td { border: 1px solid #000000; padding: 5px; text-align: left; vertical-align: middle; white-space: normal; word-wrap: break-word; }
        th { background-color: #f3f4f6; color: #000000; font-weight: bold; text-align: left; }
        tr.data-row { height: 40px; } /* Double height rows */
        .header-school { font-size: 16px; font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #FFFFFF; }
        .header-report { font-size: 14px; font-weight: bold; text-align: center; border: 1px solid #000000; background-color: #FFFFFF; }
        .text-center { text-align: center; }
    </style>';
    echo '</head>';
    echo '<body>';

    echo '<table>';
    
    // Column Widths
    echo '<col width="80" />';  // Date (Reduced)
    echo '<col width="50" />';  // Period
    echo '<col width="100" />'; // Class
    echo '<col width="100" />'; // Subject
    echo '<col width="150" />'; // Absent Teacher
    echo '<col width="150" />'; // Proxy Teacher
    echo '<col width="200" />'; // Sign (Increased)

    $displayDate = "";
    if ($reportType === 'daily') {
         $displayDate = date('d-m-Y', strtotime($date));
    } else {
         $displayDate = date('d-m-Y', strtotime($startDate)) . " to " . date('d-m-Y', strtotime($endDate));
    }

    // Report Title Rows
    echo '<tr>';
    echo '<td colspan="7" class="header-school">St. Mary\'s English School - Bhavnagar</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td colspan="7" class="header-report">Daily Proxy Report - ' . $displayDate . '</td>';
    echo '</tr>';

    // Table Header
    echo '<tr>';
    echo '<th>Date</th>';
    echo '<th>Period</th>';
    echo '<th>Class</th>';
    echo '<th>Subject</th>';
    echo '<th>Absent Teacher</th>';
    echo '<th>Proxy Teacher</th>';
    echo '<th>Sign</th>';
    echo '</tr>';

    if (empty($data)) {
        echo '<tr><td colspan="7" class="text-center" style="padding: 20px;">No records found for the selected criteria.</td></tr>';
    } else {
        foreach ($data as $row) {
            
            // Remove section name (content in parentheses) and any stray parentheses
            $className = trim(strtok($row['class_name'], '('));
            $className = str_replace(')', '', $className);

            echo '<tr class="data-row">';
            echo '<td>' . date('d-m-Y', strtotime($row['date'])) . '</td>';
            echo '<td class="text-center">' . $row['period_no'] . '</td>';
            echo '<td>' . $className . '</td>';
            echo '<td>' . ($row['subject_name'] ?? '-') . '</td>';
            echo '<td>' . strtoupper($row['absent_teacher_name']) . '</td>';
            echo '<td>' . strtoupper($row['proxy_teacher_name']) . '</td>';
            echo '<td></td>'; // Empty Sign column
            echo '</tr>';
        }
    }

    echo '</table>';
    echo '</body></html>';

    $content = ob_get_clean();
    $filePath = 'exports/' . $filename;
    
    if (file_put_contents($filePath, $content)) {
        echo json_encode(['success' => true, 'file' => $filePath]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save report file.']);
    }

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
