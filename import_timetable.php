<?php
/**
 * Import Timetable from Excel/CSV
 */
require_once 'config/app.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Handle Template Download - MUST BE BEFORE ANY OUTPUT
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    if (ob_get_level()) ob_end_clean();
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Headers (Added Employee Code)
    $headers = ['Day', 'Period', 'Employee Code (Optional)', 'Teacher Name', 'Class', 'Subject', 'Group Name'];
    $sheet->fromArray([$headers], NULL, 'A1');
    
    // Sample Data
    $sampleData = [
        ['Monday', 1, 'T001', 'John Doe', '10-A', 'Maths', ''],
        ['Tuesday', 2, '', 'Jane Smith', '9-B', 'Science', 'Group 1'], // Group example
        ['Tuesday', 2, 'T003', 'Mike Ross', '9-B', 'English', 'Group 2'] 
    ];
    $sheet->fromArray($sampleData, NULL, 'A2');
    
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="timetable_template.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

require_once 'includes/header.php';
require_once 'models/Teacher.php';
require_once 'models/Timetable.php';
require_once 'models/Classes.php';
require_once 'models/Subject.php';

$teacherModel = new Teacher();
$timetableModel = new Timetable();
$classModel = new Classes();
$subjectModel = new Subject();

$message = '';
$error = '';
$importStats = ['success' => 0, 'failed' => 0, 'errors' => []];

// Handle Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
        try {
            $inputFileName = $_FILES['file']['tmp_name'];
            $spreadsheet = IOFactory::load($inputFileName);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            
            // Remove header
            array_shift($rows);
            
            // Prepare Lookup Maps
            $teachersByName = [];
            $teachersByCode = [];
            foreach ($teacherModel->getAllActive() as $t) {
                $teachersByName[strtolower(trim($t['name']))] = $t['id'];
                if (!empty($t['empcode'])) {
                    $teachersByCode[strtolower(trim($t['empcode']))] = $t['id'];
                }
            }
            
            $classes = [];
            foreach ($classModel->getAll() as $c) {
                $key = strtolower(trim($c['standard'] . '-' . $c['division']));
                $classes[$key] = $c['id'];
            }
            
            $subjects = [];
            foreach ($subjectModel->getAll() as $s) {
                $subjects[strtolower(trim($s['name']))] = $s['id'];
            }
            
            $dayMap = [
                'monday' => 1, 'mon' => 1, '1' => 1,
                'tuesday' => 2, 'tue' => 2, '2' => 2,
                'wednesday' => 3, 'wed' => 3, '3' => 3,
                'thursday' => 4, 'thu' => 4, '4' => 4,
                'friday' => 5, 'fri' => 5, '5' => 5,
                'saturday' => 6, 'sat' => 6, '6' => 6
            ];

            foreach ($rows as $index => $row) {
                $rowNum = $index + 2; 
                
                // Order: Day, Period, EmpCode, Name, Class, Subject, Group
                $dayRaw = strtolower(trim($row[0] ?? ''));
                $period = (int)($row[1] ?? 0);
                $empCode = strtolower(trim($row[2] ?? ''));
                $teacherName = strtolower(trim($row[3] ?? ''));
                $className = strtolower(trim($row[4] ?? ''));
                $subjectName = strtolower(trim($row[5] ?? ''));
                $groupName = trim($row[6] ?? ''); // Adjusted index
                
                if (empty($dayRaw) && empty($teacherName) && empty($empCode)) continue; 

                if (!isset($dayMap[$dayRaw])) {
                    $importStats['failed']++;
                    $importStats['errors'][] = "Row $rowNum: Invalid Day '$dayRaw'";
                    continue;
                }
                $dayId = $dayMap[$dayRaw];
                
                if ($period < 1 || $period > 8) { 
                     $importStats['failed']++;
                     $importStats['errors'][] = "Row $rowNum: Invalid Period '$period'";
                     continue;
                }
                
                // Resolution Logic: EmpCode > Name
                $teacherId = null;
                if (!empty($empCode) && isset($teachersByCode[$empCode])) {
                    $teacherId = $teachersByCode[$empCode];
                } elseif (!empty($teacherName) && isset($teachersByName[$teacherName])) {
                    $teacherId = $teachersByName[$teacherName];
                }

                if (!$teacherId) {
                    $importStats['failed']++;
                    $importStats['errors'][] = "Row $rowNum: Teacher not found (Code: '$empCode', Name: '$teacherName')";
                    continue;
                }
                
                if (!isset($classes[$className])) {
                    $importStats['failed']++;
                    $importStats['errors'][] = "Row $rowNum: Class '$className' not found";
                    continue;
                }
                $classId = $classes[$className];
                
                if (!isset($subjects[$subjectName])) {
                     $importStats['failed']++;
                     $importStats['errors'][] = "Row $rowNum: Subject '$subjectName' not found";
                     continue;
                }
                $subjectId = $subjects[$subjectName];
                
                try {
                    $timetableModel->add($teacherId, $classId, $subjectId, $dayId, $period, empty($groupName) ? null : $groupName);
                    $importStats['success']++;
                } catch (Exception $e) {
                     $importStats['failed']++;
                     $importStats['errors'][] = "Row $rowNum: DB Error - " . $e->getMessage();
                }
            }
            
            $message = "Import processing complete!";
            
        } catch (Exception $e) {
            $error = "File processing error: " . $e->getMessage();
        }
    } else {
        $error = "Upload failed: " . $_FILES['file']['error'];
    }
}

?>

<div class="main-content container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center mb-5">
        <div>
            <h1 class="fw-bold mb-1">Import Timetable</h1>
            <p class="mb-0 opacity-75">Upload Excel sheet to bulk import schedule data.</p>
        </div>
        <a href="timetable.php" class="btn btn-outline-light">
            <i class="fas fa-arrow-left me-2"></i> Back to Timetable
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success d-flex align-items-center">
                            <i class="fas fa-check-circle me-2"></i>
                            <div>
                                <strong><?php echo $message; ?></strong><br>
                                Imported: <?php echo $importStats['success']; ?><br>
                                Failed: <?php echo $importStats['failed']; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($importStats['errors'])): ?>
                        <div class="alert alert-warning">
                            <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Failed Rows:</h6>
                            <ul class="mb-0 ps-3 small" style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($importStats['errors'] as $err): ?>
                                    <li><?php echo htmlspecialchars($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Step 1: Download Template -->
                    <div class="mb-5 pb-4 border-bottom">
                        <h5 class="fw-bold text-primary mb-3">Step 1: Get the Template</h5>
                        <p class="text-muted mb-3">Download the Excel template to see the required format. Fill it with your timetable data.</p>
                        <a href="?action=download_template" class="btn btn-outline-primary">
                            <i class="fas fa-file-excel me-2"></i> Download Template (.xlsx)
                        </a>
                        
                        <div class="mt-3 p-3 bg-light rounded-3">
                            <small class="d-block fw-bold text-secondary mb-2">Columns Required:</small>
                            <div class="d-flex gap-2 flex-wrap text-muted small">
                                <span class="badge bg-white text-dark border">Day</span>
                                <span class="badge bg-white text-dark border">Period</span>
                                <span class="badge bg-white text-dark border">Teacher Name</span>
                                <span class="badge bg-white text-dark border">Class (e.g. 10-A)</span>
                                <span class="badge bg-white text-dark border">Subject</span>
                                <span class="badge bg-white text-dark border">Group (Optional)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Upload -->
                    <div>
                        <h5 class="fw-bold text-primary mb-3">Step 2: Upload File</h5>
                        <form method="POST" enctype="multipart/form-data" class="dropzone-area">
                            <div class="mb-4">
                                <label for="file" class="form-label">Select Excel File (.xlsx, .xls, .csv)</label>
                                <input type="file" class="form-control form-control-lg" id="file" name="file" accept=".xlsx, .xls, .csv" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-cloud-upload-alt me-2"></i> Import Timetable
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Header Pattern Fix (Reusing your timetable styles) -->
<style>
    .page-header {
        background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
        padding: 2.5rem 2rem;
        border-radius: 16px;
        color: white;
        margin-bottom: 2rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        position: relative;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

