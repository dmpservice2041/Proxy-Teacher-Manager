<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
// Assuming Composer's autoloader is available in the root
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProxyExcelReport {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function generateDailyReport($date) {
        // Fetch data
        $sql = "
            SELECT 
                pa.date,
                t_absent.name as absent_teacher,
                t_proxy.name as proxy_teacher,
                CONCAT(c.standard, '-', c.division) as class_name,
                s.name as subject,
                pa.period_no,
                pa.mode
            FROM proxy_assignments pa
            JOIN teachers t_absent ON pa.absent_teacher_id = t_absent.id
            JOIN teachers t_proxy ON pa.proxy_teacher_id = t_proxy.id
            JOIN classes c ON pa.class_id = c.id
            JOIN subjects s ON pa.period_no = s.id -- NOTE: schema might need joining via timetable or subject_id if stored in pa. 
            -- correction: proxy_assignment table doesn't have subject_id directly per my schema, 
            -- but the Requirement 2.D says it has 'class_id' and 'period_no'. 
            -- To get Subject, we need to look it up from Timetable for that class/period.
            -- JOIN timetable tt ON ... -> Wait, schedule is unique.
            WHERE pa.date = ?
            ORDER BY pa.period_no ASC
        ";
        
        // Correcting the SQL to fetch subject from Timetable based on Class+Day+Period
        // Or if I added subject_id to proxy_assignments? 
        // In my schema I didn't add subject_id to `proxy_assignments` but the prompt implies we need it. 
        // Timetable is the source of truth for "What subject should have been taught".
        
        $dayOfWeek = date('N', strtotime($date));
        $data = $this->fetchReportData($date, $dayOfWeek);

        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            die("Error: PhpSpreadsheet library not found. Please run 'composer require phpoffice/phpspreadsheet'");
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // --- Page Setup for A4 ---
        $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0); // Automatic height
        $sheet->getPageMargins()->setTop(0.5);
        $sheet->getPageMargins()->setRight(0.5);
        $sheet->getPageMargins()->setLeft(0.5);
        $sheet->getPageMargins()->setBottom(0.5);

        // Global Font Style
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(12);

        // Title with School Name
        if (defined('SCHOOL_NAME')) {
            $sheet->setCellValue('A1', SCHOOL_NAME);
            $sheet->mergeCells('A1:G1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            
            $sheet->setCellValue('A2', "Daily Proxy Report - $date");
            $sheet->mergeCells('A2:G2');
            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            
            $startRow = 4; // Start headers at row 4
        } else {
            $sheet->setTitle("Proxy Report $date");
            $startRow = 1;
        }

        // Header
        $headers = ['Date', 'Period', 'Class', 'Subject', 'Absent Teacher', 'Proxy Teacher', 'Mode'];
        $col = 'A';
        foreach ($headers as $header) {
            $cell = $col . $startRow;
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('EEEEEE');
            $col++;
        }

        // Rows
        $row = $startRow + 1;
        foreach ($data as $record) {
            $sheet->setCellValue('A' . $row, $record['date']);
            $sheet->setCellValue('B' . $row, $record['period_no']);
            $sheet->setCellValue('C' . $row, $record['class_name']);
            $sheet->setCellValue('D' . $row, $record['subject_name']);
            $sheet->setCellValue('E' . $row, $record['absent_teacher']);
            $sheet->setCellValue('F' . $row, $record['proxy_teacher']);
            $sheet->setCellValue('G' . $row, $record['mode']);
            $row++;
        }

        $lastRow = $row - 1;
        $range = "A$startRow:G$lastRow";

        // Borders and Alignment
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];
        $sheet->getStyle($range)->applyFromArray($styleArray);

        // Auto-size columns or fixed width to fit A4
        $columnWidths = [
            'A' => 12, // Date
            'B' => 8,  // Period
            'C' => 10,  // Class
            'D' => 20, // Subject
            'E' => 25, // Absent
            'F' => 25, // Proxy
            'G' => 10, // Mode
        ];
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "Proxy_Report_$date.xlsx";
        $writer->save(__DIR__ . '/../exports/' . $filename);
        
        return $filename;
    }

    private function fetchReportData($date, $dayOfWeek) {
        $stmt = $this->pdo->prepare("
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
            -- Join Timetable to find the original subject for this slot
            LEFT JOIN timetable tt ON (
                tt.class_id = pa.class_id 
                AND tt.period_no = pa.period_no 
                AND tt.day_of_week = ?
            )
            LEFT JOIN subjects s ON tt.subject_id = s.id
            WHERE pa.date = ?
            ORDER BY pa.period_no
        ");
        $stmt->execute([$dayOfWeek, $date]);
        return $stmt->fetchAll();
    }
}
