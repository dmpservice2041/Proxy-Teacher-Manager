<?php
require_once 'config/app.php';
require_once 'includes/header.php';
require_once 'services/AttendanceService.php';
require_once 'services/ETimeService.php';
require_once 'models/Teacher.php';

$date = $_GET['date'] ?? date('Y-m-d');
$service = new AttendanceService();
$etimeService = new ETimeService();
$teacherModel = new Teacher();

// Get last sync info (if table exists)
$lastSync = null;
try {
    $lastSync = $etimeService->getLastSync();
} catch (Exception $e) {
    // Table might not exist if migration hasn't been run
}
$successMessage = null;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['fetch_from_api'])) {
        // Fetch attendance from eTime Office API
        try {
            $result = $etimeService->fetchDailyAttendance($date);
            if ($result['success']) {
                // Get total teachers in database
                $totalTeachers = count($teacherModel->getAllActive());
                $apiRecords = $result['total_in_api'] ?? 0;
                
                $successMessage = $result['message'];
                
                // Add detailed stats if available
                if (isset($result['stats'])) {
                    $stats = $result['stats'];
                    $successMessage .= "<br><small><i class='fas fa-info-circle'></i> ";
                    $successMessage .= "Total Active: {$stats['total_active']} | ";
                    $successMessage .= "With Empcode: {$stats['with_empcode']} | ";
                    $successMessage .= "Without Empcode: {$stats['without_empcode']} | ";
                    $successMessage .= "Processed: {$stats['processed']}";
                    if ($stats['unprocessed'] > 0) {
                        $successMessage .= " | <span class='text-warning'><strong>⚠️ Unprocessed: {$stats['unprocessed']}</strong></span>";
                    }
                    $successMessage .= "</small>";
                } else {
                    $successMessage .= "<br><small><i class='fas fa-info-circle'></i> Total teachers in database: {$totalTeachers} | Found in API: {$apiRecords}</small>";
                }
                
                // Store skipped details for display
                if (!empty($result['skipped'])) {
                    $skippedEmployees = $result['skipped'];
                    $successMessage .= " <a href='#' onclick='document.getElementById(\"skippedDetails\").style.display=\"block\"; return false;'>View Details (" . count($skippedEmployees) . ")</a>";
                }
            } else {
                $error = $result['message'];
            }
        } catch (Exception $e) {
            $error = 'API Fetch Error: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')';
            error_log('ETime API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }
    elseif (isset($_POST['delete_attendance'])) {
        try {
            $service->deleteAttendanceForDate($date);
            $message = "Attendance data cleared for $date.";
            echo "<script>alert('Attendance Data Deleted!'); window.location.href='?date=$date';</script>";
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    elseif (isset($_POST['teacher_id'])) {
        // Toggle Status
        $teacherId = $_POST['teacher_id'];
        $currentStatus = $_POST['current_status'];
        $newStatus = ($currentStatus == 'Absent') ? 'Present' : 'Absent';
        
        try {
            $service->markAttendance($teacherId, $date, $newStatus, 'API'); 
            echo "<script>window.location.href='?date=$date';</script>";
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Fetch Data
$teachers = $teacherModel->getAllWithDetails(); // Fetch ALL teachers (Active + Inactive)

// Filter out those with no empcode? No, user wants to see attendance.
// But getAllWithDetails returns inactive too. Perfect.

// Fetch full attendance data for the date

// Fetch full attendance data for the date
$attendanceRecords = $service->getAttendanceForDate($date);

// Process attendance into a map and count stats
$attendanceMap = [];
$absentCount = 0;
$presentCount = 0; // Explicitly counted from records or derived?
// Actually simpler:
// 1. Map existing records
foreach ($attendanceRecords as $rec) {
    $attendanceMap[$rec['teacher_id']] = $rec;
    if ($rec['status'] == 'Absent') {
        $absentCount++;
    }
}
// 2. Active Count is total teachers
$activeCount = count($teachers);
// 3. Present is Active - Absent (Assuming default is Present)
$presentCount = $activeCount - $absentCount;

// Sort teachers by Empcode for display consistency with screenshot
usort($teachers, function($a, $b) {
    return (int)($a['empcode'] ?? 0) - (int)($b['empcode'] ?? 0);
});


?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Attendance</h2>
        <div class="d-flex align-items-center">
            <form class="d-flex me-3">
                <input type="date" name="date" class="form-control me-2" value="<?php echo $date; ?>" onchange="this.form.submit()">
            </form>
            <form method="POST" class="me-2">
                <input type="hidden" name="fetch_from_api" value="1">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-cloud-download-alt"></i> Fetch from API
                </button>
            </form>
            <form method="POST" onsubmit="return confirm('WARNING: Are you sure you want to delete ALL attendance data for this date? This cannot be undone.');">
                <input type="hidden" name="delete_attendance" value="1">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash-alt"></i> Delete Data
                </button>
            </form>
        </div>
    </div>

    <?php if ($lastSync): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Last Sync: <?php echo date('d M Y, h:i A', strtotime($lastSync['synced_at'])); ?> 
        | Status: <strong><?php echo $lastSync['status']; ?></strong>
        | Records: <?php echo $lastSync['records_processed']; ?>
    </div>
    <?php endif; ?>

    <?php
    // Stats calculation moved up
    ?>


    <!-- Stats Cards -->
    <div class="row mb-4">
        <!-- Active Employees -->
        <div class="col-md-4">
            <div class="card border-primary h-100">
                <div class="row g-0 h-100">
                    <div class="col-4 bg-primary text-white d-flex align-items-center justify-content-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                    <div class="col-8">
                        <div class="card-body text-center p-2">
                            <h3 class="card-title text-primary fw-bold mb-0"><?php echo $activeCount; ?></h3>
                            <p class="card-text text-muted small">Active Emp.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Present Employees -->
        <div class="col-md-4">
            <div class="card border-success h-100">
                <div class="row g-0 h-100">
                    <div class="col-4 bg-success text-white d-flex align-items-center justify-content-center">
                        <i class="fas fa-check fa-2x"></i>
                    </div>
                    <div class="col-8">
                        <div class="card-body text-center p-2">
                            <h3 class="card-title text-success fw-bold mb-0"><?php echo $presentCount; ?></h3>
                            <p class="card-text text-muted small">Present Emp.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Absent Employees -->
        <div class="col-md-4">
            <div class="card border-danger h-100">
                <div class="row g-0 h-100">
                    <div class="col-4 bg-danger text-white d-flex align-items-center justify-content-center">
                        <i class="fas fa-times fa-2x"></i>
                    </div>
                    <div class="col-8">
                        <div class="card-body text-center p-2">
                            <h3 class="card-title text-danger fw-bold mb-0"><?php echo $absentCount; ?></h3>
                            <p class="card-text text-muted small">Absent Emp.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($successMessage)): ?><div class="alert alert-success"><?php echo $successMessage; ?></div><?php endif; ?>
    <?php if (isset($error)): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>


    <?php if (isset($skippedEmployees) && !empty($skippedEmployees)): ?>
    <div id="skippedDetails" style="display: none;" class="card mb-3 border-warning">
        <div class="card-header bg-warning text-dark">
            <h6 class="mb-0">
                <i class="fas fa-exclamation-triangle"></i> Skipped Employees (<?php echo count($skippedEmployees); ?>)
                <button type="button" class="btn btn-sm btn-close float-end" onclick="this.parentElement.parentElement.parentElement.style.display='none'"></button>
            </h6>
        </div>
        <div class="card-body">
            <p class="small mb-2">The following employees were not processed:</p>
            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>Emp Code</th>
                        <th>Name</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($skippedEmployees as $emp): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($emp['empcode']); ?></td>
                        <td><?php echo htmlspecialchars($emp['name']); ?></td>
                        <td><small><?php echo htmlspecialchars($emp['reason']); ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-bordered table-striped mb-0 text-center align-middle">
                <thead>
                    <tr>
                        <th class="text-center">Empcode</th>
                        <th class="text-start">Name</th>
                        <th class="text-center">INTime</th>
                        <th class="text-center">OUTTime</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teachers as $t): ?>
                        <?php 
                            $att = $attendanceMap[$t['id']] ?? null;
                            $isAbsent = ($att && $att['status'] == 'Absent');
                            $inTime = $att['in_time'] ?? '--:--';
                            $outTime = $att['out_time'] ?? '--:--';
                            $isActive = $t['is_active'] == 1;
                            $rowClass = !$isActive ? 'table-secondary text-muted' : '';
                        ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td>
                                <?php echo htmlspecialchars($t['empcode'] ?? '-'); ?>
                                <?php if (!$isActive): ?>
                                    <span class="badge bg-secondary" style="font-size: 0.6rem;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-start"><?php echo htmlspecialchars($t['name']); ?></td>
                            <td><?php echo htmlspecialchars($inTime); ?></td>
                            <td><?php echo htmlspecialchars($outTime); ?></td>
                            <td>
                                <form method="POST" class="m-0">
                                    <input type="hidden" name="teacher_id" value="<?php echo $t['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $isAbsent ? 'Absent' : 'Present'; ?>">
                                    <button type="submit" class="btn btn-sm fw-bold <?php echo $isAbsent ? 'text-danger' : 'text-dark'; ?>" style="background:none; border:none;">
                                        <?php echo $isAbsent ? 'A' : 'P'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
