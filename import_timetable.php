<?php
/**
 * Web Interface for Timetable PDF Import
 */
require_once 'config/app.php';
require_once 'includes/header.php';
require_once 'models/Teacher.php';
require_once 'models/Timetable.php';
require_once 'models/Classes.php';
require_once 'models/Subject.php';
require_once 'vendor/autoload.php';

use Smalot\PdfParser\Parser;

$message = '';
$error = '';
$extractedText = '';
$parsedEntries = [];

$teacherModel = new Teacher();
$timetableModel = new Timetable();
$classModel = new Classes();
$subjectModel = new Subject();

// Get all data for matching
$allTeachers = $teacherModel->getAllWithDetails();
$allClasses = $classModel->getAll();
$allSubjects = $subjectModel->getAll();

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    if ($_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        $uploadedFile = $_FILES['pdf_file']['tmp_name'];
        $fileName = $_FILES['pdf_file']['name'];
        
        // Validate PDF
        $fileType = mime_content_type($uploadedFile);
        if ($fileType !== 'application/pdf') {
            $error = "Invalid file type. Please upload a PDF file.";
        } else {
            try {
                // Extract text from PDF
                $parser = new Parser();
                $pdf = $parser->parseFile($uploadedFile);
                $extractedText = $pdf->getText();
                
                if (empty($extractedText)) {
                    // Try extracting from pages individually
                    $pages = $pdf->getPages();
                    foreach ($pages as $page) {
                        $extractedText .= $page->getText() . "\n";
                    }
                }
                
                if (empty($extractedText)) {
                    $error = "Could not extract text from PDF. The PDF might be image-based or encrypted.";
                } else {
                    $message = "Successfully extracted " . strlen($extractedText) . " characters from PDF.";
                }
            } catch (Exception $e) {
                $error = "Error processing PDF: " . $e->getMessage();
            }
        }
    } else {
        $error = "File upload error: " . $_FILES['pdf_file']['error'];
    }
}

// Handle import action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_entries'])) {
    $entriesJson = $_POST['entries_json'] ?? '[]';
    $entries = json_decode($entriesJson, true);
    
    if (is_array($entries)) {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($entries as $entry) {
            try {
                // Check if entry already exists
                $existing = $timetableModel->getTeacherSchedule($entry['teacher_id'], $entry['day']);
                $exists = false;
                foreach ($existing as $existingEntry) {
                    if ($existingEntry['period_no'] == $entry['period'] && 
                        $existingEntry['class_id'] == $entry['class_id']) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    $timetableModel->add(
                        $entry['teacher_id'],
                        $entry['class_id'],
                        $entry['subject_id'],
                        $entry['day'],
                        $entry['period'],
                        $entry['group_name'] ?? null
                    );
                    $imported++;
                } else {
                    $skipped++;
                }
            } catch (Exception $e) {
                $errors[] = "Error: " . $e->getMessage();
            }
        }
        
        $message = "Import complete: {$imported} imported, {$skipped} skipped, " . count($errors) . " errors.";
        if (!empty($errors)) {
            $error = implode("<br>", array_slice($errors, 0, 10));
        }
    }
}
?>

<div class="container mt-4">
    <h2>Import Timetable from PDF</h2>
    
    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Step 1: Upload PDF File</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="pdf_file" class="form-label">Select PDF File</label>
                            <input type="file" class="form-control" id="pdf_file" name="pdf_file" accept=".pdf" required>
                            <small class="form-text text-muted">Upload the timetable PDF file</small>
                        </div>
                        <button type="submit" class="btn btn-primary">Extract Text from PDF</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($extractedText)): ?>
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Step 2: Review Extracted Text</h5>
                </div>
                <div class="card-body">
                    <p><strong>Extracted Text:</strong> (<?php echo strlen($extractedText); ?> characters)</p>
                    <textarea class="form-control" rows="20" readonly><?php echo htmlspecialchars($extractedText); ?></textarea>
                    <p class="mt-2"><small class="text-muted">Please review the extracted text to understand the format. The parser will attempt to identify teachers, classes, subjects, days, and periods.</small></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Step 3: Parse and Import</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Due to the complexity of PDF table parsing, please manually review the extracted text above and use the timetable page to add entries, or provide the timetable data in Excel/CSV format for easier import.</p>
                    <p><strong>Alternative:</strong> You can also manually enter timetable data using the <a href="timetable.php">Timetable page</a>.</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Reference Data</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6>Teachers (<?php echo count($allTeachers); ?>)</h6>
                            <select class="form-select" size="5" readonly>
                                <?php foreach (array_slice($allTeachers, 0, 20) as $t): ?>
                                    <option><?php echo htmlspecialchars($t['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <h6>Classes (<?php echo count($allClasses); ?>)</h6>
                            <select class="form-select" size="5" readonly>
                                <?php foreach (array_slice($allClasses, 0, 20) as $c): ?>
                                    <option><?php echo htmlspecialchars($c['standard'] . '-' . $c['division']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <h6>Subjects (<?php echo count($allSubjects); ?>)</h6>
                            <select class="form-select" size="5" readonly>
                                <?php foreach ($allSubjects as $s): ?>
                                    <option><?php echo htmlspecialchars($s['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

