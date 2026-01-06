<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

// use Mpdf\Mpdf;

class ProxyPdfReport {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function generateDailyReport($date) {
        // Reuse logic or query
        $dayOfWeek = date('N', strtotime($date));
        $data = $this->fetchReportData($date, $dayOfWeek);

        if (!class_exists('Mpdf\Mpdf')) {
            die("Error: mPDF library not found. Please run 'composer require mpdf/mpdf'");
        }

        $mpdf = new \Mpdf\Mpdf();
        
        $schoolName = defined('SCHOOL_NAME') ? SCHOOL_NAME : 'School Name';
        $html = "<h2 style='text-align:center;'>$schoolName</h2>";
        $html .= "<h3 style='text-align:center;'>Proxy Allocation Report for $date</h3>";
        $html .= "<table border='1' style='width:100%; border-collapse: collapse;'>";
        $html .= "<thead>
                    <tr style='background-color: #f2f2f2;'>
                        <th>Period</th>
                        <th>Class</th>
                        <th>Subject</th>
                        <th>Absent Teacher</th>
                        <th>Proxy Teacher</th>
                        <th>Mode</th>
                    </tr>
                  </thead><tbody>";

        foreach ($data as $row) {
            $html .= "<tr>
                        <td>{$row['period_no']}</td>
                        <td>{$row['class_name']}</td>
                        <td>{$row['subject_name']}</td>
                        <td>{$row['absent_teacher']}</td>
                        <td>{$row['proxy_teacher']}</td>
                        <td>{$row['mode']}</td>
                      </tr>";
        }
        $html .= "</tbody></table>";

        $mpdf->WriteHTML($html);
        $filename = "Proxy_Report_$date.pdf";
        $mpdf->Output(__DIR__ . '/../../' . $filename, 'F');
        
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
