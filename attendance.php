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

$lastSync = null;
try {
    $lastSync = $etimeService->getLastSync();
} catch (Exception $e) {
    // Table might not exist if migration hasn't been run
}
$successMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['fetch_from_api'])) {
        try {
            $result = $etimeService->fetchDailyAttendance($date);
            if ($result['success']) {
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

$teachers = $teacherModel->getAllWithDetails(); // Fetch ALL teachers (Active + Inactive)

$attendanceRecords = $service->getAttendanceForDate($date);

$attendanceMap = [];
$absentCount = 0;
foreach ($attendanceRecords as $rec) {
    $attendanceMap[$rec['teacher_id']] = $rec;
    if ($rec['status'] == 'Absent') {
        $absentCount++;
    }
}

$activeCount = count($teachers);
$presentCount = $activeCount - $absentCount;

$absentList = $service->getAbsentTeachers($date);

$presentList = [];
$activeList = $teachers; // Already fetched and sorted

foreach ($teachers as $t) {
    $att = $attendanceMap[$t['id']] ?? null;
    if ($att && $att['status'] == 'Present') {
        $presentList[] = $t;
    }
}

usort($teachers, function($a, $b) {
    return (int)($a['empcode'] ?? 0) - (int)($b['empcode'] ?? 0);
});

?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
        --success-gradient: linear-gradient(135deg, #059669 0%, #10B981 100%);
        --danger-gradient: linear-gradient(135deg, #DC2626 0%, #EF4444 100%);
        --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --hover-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    body {
        background-color: #f3f4f6;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    .main-content {
        padding: 2rem;
    }

    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 2rem 2.5rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1.5rem;
    }

    .page-title {
        color: white;
        font-weight: 700;
        font-size: 1.75rem;
        margin: 0;
    }
    
    .page-subtitle {
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.95rem;
        font-weight: 400;
    }
    
    .header-actions {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .btn-header {
        border-radius: 10px;
        font-weight: 600;
        padding: 0.65rem 1.25rem;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-export {
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }
    
    .btn-export:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        color: white;
    }
    
    .btn-fetch-api {
        background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
    }
    
    .btn-fetch-api:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
        color: white;
    }
    
    .btn-sync-erp {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(245, 87, 108, 0.3);
    }
    
    .btn-sync-erp:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(245, 87, 108, 0.4);
        color: white;
    }
    
    .btn-more {
        background: white;
        color: #667eea;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 0.65rem 1rem;
    }
    
    .btn-more:hover {
        background: #f8f9fa;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .date-input {
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.3);
        background: rgba(255, 255, 255, 0.95);
        padding: 0.65rem 1rem;
        font-size: 0.9rem;
        color: #333;
        font-weight: 500;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }
    
    .date-input:focus {
        outline: none;
        border-color: white;
        background: white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .stats-card {
        background: white;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.05);
        height: 100%;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--hover-shadow);
    }

    .stats-icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        font-size: 1.25rem;
    }

    .stats-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 0.5rem;
    }

    .stats-label {
        font-size: 0.875rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .card-primary .stats-icon-wrapper { background: rgba(79, 70, 229, 0.1); color: #4F46E5; }
    .card-primary .stats-value { color: #4F46E5; }
    .card-primary .stats-label { color: #6B7280; }

    .card-success .stats-icon-wrapper { background: rgba(16, 185, 129, 0.1); color: #059669; }
    .card-success .stats-value { color: #059669; }
    
    .card-danger .stats-icon-wrapper { background: rgba(220, 38, 38, 0.1); color: #DC2626; }
    .card-danger .stats-value { color: #DC2626; }

    .table-card {
        background: white;
        border-radius: 1rem;
        box-shadow: var(--card-shadow);
        overflow: hidden;
        border: 1px solid rgba(0,0,0,0.05);
    }

    .table thead th {
        background-color: #f9fafb;
        color: #6b7280;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #e5e7eb;
    }

    .table tbody td {
        padding: 1rem 1.5rem;
        vertical-align: middle;
        color: #374151;
        border-bottom: 1px solid #f3f4f6;
    }

    .table tbody tr:last-child td {
        border-bottom: none;
    }

    .table tbody tr:hover {
        background-color: #f9fafb;
    }

    .status-badge {
        padding: 0.35em 0.8em;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 9999px;
    }

    .status-badge.present { background-color: #d1fae5; color: #065f46; }
    .status-badge.absent { background-color: #fee2e2; color: #991b1b; }
    .status-badge.inactive { background-color: #f3f4f6; color: #4b5563; }

    .action-btn {
        transition: all 0.2s;
        border-radius: 0.5rem;
        font-weight: 500;
    }
    
    .action-btn:hover {
        transform: translateY(-1px);
    }

    .date-input {
        background: white;
        border: 1px solid #e5e7eb;
        padding: 0.5rem 1rem;
        border-radius: 0.5rem;
        font-weight: 500;
        color: #374151;
        outline: none;
        transition: border-color 0.2s;
    }
    .date-input:focus {
        border-color: #4F46E5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    /* Modal Styling */
    .modal-content {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    .modal-header {
        border-bottom: 1px solid #f3f4f6;
        padding: 1.5rem;
    }
    .modal-body {
        padding: 0;
    }
    .modal-title {
        font-weight: 700;
        color: #111827;
    }
    .list-group-item {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #f3f4f6;
    }
</style>

<div class="main-content">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Daily Attendance</h1>
            <p class="page-subtitle mb-0">Manage and track teacher attendance for <?php echo date('l, F j, Y', strtotime($date)); ?></p>
        </div>
        <div class="header-actions">
            <!-- Date Picker -->
            <form class="m-0">
                <input type="date" name="date" class="date-input" value="<?php echo $date; ?>" onchange="this.form.submit()">
            </form>

            <!-- Export Button -->
            <button type="button" class="btn btn-header btn-export" data-bs-toggle="modal" data-bs-target="#exportModal">
                <i class="fas fa-file-export"></i>
                <span>Export</span>
            </button>
            
            <!-- Fetch API Button -->
            <form method="POST" class="m-0">
                <input type="hidden" name="fetch_from_api" value="1">
                <button type="submit" class="btn btn-header btn-fetch-api">
                    <i class="fas fa-cloud-download-alt"></i>
                    <span>Fetch API</span>
                </button>
            </form>
            
            <!-- Sync ERP Button -->
            <button type="button" id="btnSyncToErp" class="btn btn-header btn-sync-erp">
                <i class="fas fa-sync"></i>
                <span>Sync ERP</span>
            </button>
            
            <!-- More Actions Dropdown -->
            <div class="dropdown">
                <button class="btn btn-header btn-more dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                    <li>
                        <form method="POST" onsubmit="return confirm('WARNING: Are you sure you want to delete ALL attendance data for this date?');">
                            <input type="hidden" name="delete_attendance" value="1">
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="fas fa-trash-alt me-2"></i> Clear All Data
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($lastSync): ?>
    <div class="alert alert-info border-0 shadow-sm rounded-3 d-flex align-items-center gap-3 mb-4">
        <div class="bg-info bg-opacity-10 p-2 rounded-circle text-info">
            <i class="fas fa-info"></i>
        </div>
        <div>
            <div class="fw-bold">Last Sync Status: <?php echo $lastSync['status']; ?></div>
            <div class="small text-muted">
                <?php echo date('d M Y, h:i A', strtotime($lastSync['synced_at'])); ?> • <?php echo $lastSync['records_processed']; ?> records
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($successMessage)): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-check-circle text-success fs-5"></i>
                <div><?php echo $successMessage; ?></div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4">
             <div class="d-flex align-items-center gap-2">
                <i class="fas fa-exclamation-circle text-danger fs-5"></i>
                <div><?php echo $error; ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Skipped Details -->
    <?php if (isset($skippedEmployees) && !empty($skippedEmployees)): ?>
    <div id="skippedDetails" style="display: none;" class="card border-warning mb-4 shadow-sm">
        <div class="card-header bg-warning bg-opacity-10 border-warning text-warning-dark fw-bold d-flex justify-content-between align-items-center">
            <span><i class="fas fa-exclamation-triangle me-2"></i> Skipped Employees (<?php echo count($skippedEmployees); ?>)</span>
            <button type="button" class="btn-close" onclick="this.closest('.card').style.display='none'"></button>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Emp Code</th><th>Name</th><th>Reason</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($skippedEmployees as $emp): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($emp['empcode']); ?></td>
                        <td><?php echo htmlspecialchars($emp['name']); ?></td>
                        <td class="text-danger small"><?php echo htmlspecialchars($emp['reason']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stats-card card-primary" data-bs-toggle="modal" data-bs-target="#activeModal">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-value"><?php echo $activeCount; ?></div>
                <div class="stats-label">Total Active</div>
                <div class="position-absolute top-0 end-0 p-3 opacity-10">
                    <i class="fas fa-users fa-4x text-primary transform rotate-12"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card card-success" data-bs-toggle="modal" data-bs-target="#presentModal">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stats-value"><?php echo $presentCount; ?></div>
                <div class="stats-label">Present Today</div>
                <div class="position-absolute top-0 end-0 p-3 opacity-10">
                    <i class="fas fa-check fa-4x text-success"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stats-card card-danger" data-bs-toggle="modal" data-bs-target="#absentModal">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stats-value"><?php echo $absentCount; ?></div>
                <div class="stats-label">Absent Today</div>
                <div class="position-absolute top-0 end-0 p-3 opacity-10">
                    <i class="fas fa-times fa-4x text-danger"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th width="100">Code</th>
                        <th>Teacher Name</th>
                        <th width="150" class="text-center">In Time</th>
                        <th width="150" class="text-center">Out Time</th>
                        <th width="120" class="text-center">Status</th>
                        <th width="100" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teachers as $t): ?>
                        <?php 
                            $att = $attendanceMap[$t['id']] ?? null;
                            $isAbsent = ($att && $att['status'] == 'Absent');
                            $inTime = $att['in_time'] ?? '-';
                            $outTime = $att['out_time'] ?? '-';
                            $isActive = $t['is_active'] == 1;
                            
                            $opacity = $isActive ? '1' : '0.5';
                        ?>
                        <tr style="opacity: <?php echo $opacity; ?>">
                            <td>
                                <span class="fw-bold text-secondary">#<?php echo htmlspecialchars($t['empcode'] ?? 'N/A'); ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center fw-bold text-primary" style="width: 36px; height: 36px; font-size: 0.8rem;">
                                        <?php echo substr($t['name'], 0, 1); ?>
                                    </div>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($t['name']); ?></div>
                                    <?php if (!$isActive): ?>
                                        <span class="badge bg-secondary text-white small" style="font-size: 0.6rem;">Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center font-monospace text-muted"><?php echo htmlspecialchars($inTime); ?></td>
                            <td class="text-center font-monospace text-muted"><?php echo htmlspecialchars($outTime); ?></td>
                            <td class="text-center">
                                <?php if ($isAbsent): ?>
                                    <span class="status-badge absent">Absent</span>
                                <?php else: ?>
                                    <span class="status-badge present">Present</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($isAbsent): ?>
                                    <button type="button" class="btn btn-sm btn-outline-success w-100 rounded-3 btn-attendance" 
                                            data-teacher-id="<?php echo $t['id']; ?>" 
                                            data-status="Present"
                                            data-date="<?php echo $date; ?>">
                                        Mark P
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger w-100 rounded-3 btn-attendance" 
                                            data-teacher-id="<?php echo $t['id']; ?>" 
                                            data-status="Absent"
                                            data-date="<?php echo $date; ?>">
                                        Mark A
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modals -->

<!-- Absent Modal -->
<div class="modal fade" id="absentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger bg-opacity-10 border-0">
                <h5 class="modal-title text-danger fw-bold"><i class="fas fa-user-times me-2"></i> Absent Teachers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <?php if (empty($absentList)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-clipboard-check fa-3x mb-3 opacity-25"></i>
                        <p>No teachers marked absent.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $activeAbsent = array_filter($absentList, fn($t) => $t['is_active'] == 1);
                    $inactiveAbsent = array_filter($absentList, fn($t) => $t['is_active'] == 0);
                    ?>
                    
                    <!-- Active Teachers -->
                    <?php if (!empty($activeAbsent)): ?>
                        <div class="p-3 pb-2">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-success px-3 py-2 me-2">Active Staff</span>
                                <small class="text-muted fw-medium"><?php echo count($activeAbsent); ?> absent</small>
                            </div>
                            <div class="list-group list-group-flush">
                                <?php foreach ($activeAbsent as $at): ?>
                                <div class="list-group-item border-0 border-bottom d-flex justify-content-between align-items-center py-3 px-0">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" style="width: 44px; height: 44px; font-size: 1.2rem; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                            <?php echo strtoupper(substr($at['name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($at['name']); ?></div>
                                            <div class="small text-muted">Emp Code: <?php echo htmlspecialchars($at['empcode']); ?></div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center flex-shrink-0">
                                        <span class="badge bg-success px-2 py-1">Active</span>
                                        <span class="badge bg-danger px-2 py-1">Absent</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Inactive Teachers -->
                    <?php if (!empty($inactiveAbsent)): ?>
                        <div class="bg-light p-3 <?php echo !empty($activeAbsent) ? 'mt-2' : ''; ?>">
                            <div class="d-flex align-items-center mb-3">
                                <span class="badge bg-secondary px-3 py-2 me-2">Inactive Staff</span>
                                <small class="text-muted fw-medium"><?php echo count($inactiveAbsent); ?> absent</small>
                            </div>
                            <div class="list-group list-group-flush">
                                <?php foreach ($inactiveAbsent as $at): ?>
                                <div class="list-group-item bg-transparent border-0 border-bottom d-flex justify-content-between align-items-center py-3 px-0">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" style="width: 44px; height: 44px; font-size: 1.2rem; background: #6c757d;">
                                            <?php echo strtoupper(substr($at['name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($at['name']); ?></div>
                                            <div class="small text-muted">Emp Code: <?php echo htmlspecialchars($at['empcode']); ?></div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center flex-shrink-0">
                                        <span class="badge bg-secondary px-2 py-1">Inactive</span>
                                        <span class="badge bg-danger bg-opacity-75 px-2 py-1">Absent</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Present Modal -->
<div class="modal fade" id="presentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-success"><i class="fas fa-check-circle me-2"></i> Present Teachers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush">
                    <?php foreach ($presentList as $pt): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($pt['name']); ?></div>
                            <div class="small text-muted">Emp: <?php echo htmlspecialchars($pt['empcode']); ?></div>
                        </div>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill">Present</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Active Modal -->
<div class="modal fade" id="activeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-primary"><i class="fas fa-users me-2"></i> All Active Teachers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush">
                    <?php foreach ($activeList as $at): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($at['name']); ?></div>
                            <div class="small text-muted">Emp: <?php echo htmlspecialchars($at['empcode']); ?></div>
                        </div>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill">Active</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="fas fa-file-export text-success me-2 bg-success bg-opacity-10 p-2 rounded-circle"></i>
                    Export Attendance
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form action="export_attendance.php" method="POST">
                    
                    <div class="mb-4">
                        <label class="form-label text-muted small fw-bold text-uppercase mb-3">Report Format</label>
                        <div class="d-flex flex-column gap-3">
                            <label class="report-option-card d-flex align-items-center p-3 rounded-3 border cursor-pointer" style="transition: all 0.2s;">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio" name="type" id="exportDaily" value="daily" checked style="transform: scale(1.2);">
                                </div>
                                <div class="ms-3">
                                    <div class="fw-bold text-dark">Daily Summary</div>
                                    <div class="text-muted small">Standard CSV report for a single day</div>
                                </div>
                                <div class="ms-auto text-muted"><i class="fas fa-file-csv fa-lg"></i></div>
                            </label>

                            <label class="report-option-card d-flex align-items-center p-3 rounded-3 border cursor-pointer" style="transition: all 0.2s;">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio" name="type" id="exportRange" value="range" style="transform: scale(1.2);">
                                </div>
                                <div class="ms-3">
                                    <div class="fw-bold text-dark">Monthly / Date Range</div>
                                    <div class="text-muted small">Detailed Excel sheet with In/Out & Status calculation</div>
                                </div>
                                <div class="ms-auto text-success"><i class="fas fa-file-excel fa-lg"></i></div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="bg-light p-3 rounded-3 border mb-4">
                        <div id="dailyInputs">
                            <label class="form-label fw-semibold text-dark mb-2">Select Date</label>
                            <input type="date" name="date" class="form-control form-control-lg border-0 shadow-sm" value="<?php echo $date; ?>">
                        </div>
                        
                        <div id="rangeInputs" style="display:none;">
                            <label class="form-label fw-semibold text-dark mb-2">Date Range</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="form-floating">
                                        <input type="date" name="start_date" id="floatingStart" class="form-control border-0 shadow-sm" value="<?php echo date('Y-m-01'); ?>">
                                        <label for="floatingStart">From</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-floating">
                                        <input type="date" name="end_date" id="floatingEnd" class="form-control border-0 shadow-sm" value="<?php echo date('Y-m-d'); ?>">
                                        <label for="floatingEnd">To</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg shadow-sm" style="background: var(--success-gradient); border: none;">
                            <i class="fas fa-download me-2"></i> Download Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    /* Styling for the report option selection */
    .report-option-card:hover {
        background-color: #f8fafc;
        border-color: #4F46E5 !important;
        box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.1);
    }
    .report-option-card:has(input:checked) {
        border-color: #4F46E5 !important;
        background-color: #EEF2FF;
    }
    .cursor-pointer { cursor: pointer; }
</style>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const radDaily = document.getElementById('exportDaily');
        const radRange = document.getElementById('exportRange');
        const dailyInputs = document.getElementById('dailyInputs');
        const rangeInputs = document.getElementById('rangeInputs');
        
        function toggleInputs() {
            if (radRange.checked) {
                dailyInputs.style.display = 'none';
                rangeInputs.style.display = 'block';
            } else {
                dailyInputs.style.display = 'block';
                rangeInputs.style.display = 'none';
            }
        }
        
        radDaily.addEventListener('change', toggleInputs);
        radRange.addEventListener('change', toggleInputs);
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $(document).on('click', '.btn-attendance', function() {
        const btn = $(this);
        const id = btn.data('teacher-id');
        const date = btn.data('date');
        const status = btn.data('status'); // Status to SET
        
        btn.prop('disabled', true);
        
        $.ajax({
            url: 'scripts/update_attendance_ajax.php',
            type: 'POST',
            data: { teacher_id: id, date: date, status: status },
            dataType: 'json',
            success: function(resp) {
                btn.prop('disabled', false);
                if (resp.success) {
                    const row = btn.closest('tr');
                    const badge = row.find('.status-badge');
                    
                    if (status === 'Present') {
                        btn.html('Mark A')
                           .removeClass('btn-outline-success')
                           .addClass('btn-outline-danger')
                           .data('status', 'Absent');
                        
                        badge.text('Present')
                             .removeClass('absent')
                             .addClass('present');
                    } else {
                        btn.html('Mark P')
                           .removeClass('btn-outline-danger')
                           .addClass('btn-outline-success')
                           .data('status', 'Present');
                        
                        badge.text('Absent')
                             .removeClass('present')
                             .addClass('absent');
                    }
                } else {
                    alert('Error: ' + resp.message);
                }
            },
            error: function(xhr) {
                 btn.prop('disabled', false);
                 console.error(xhr.responseText);
                 alert('Request failed. See console for details.');
            }
        });
    });

    $('#btnSyncToErp').on('click', function() {
        showSyncConfirmModal();
    });
    
    function showSyncConfirmModal() {
        const modalHtml = `
            <div class="modal fade" id="syncConfirmModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header border-0 pb-2" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <h5 class="modal-title text-white fw-bold">
                                <i class="fas fa-sync-alt me-2"></i>Sync to ERP
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center py-4">
                            <div class="mb-3">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 3.5rem; color: #667eea;"></i>
                            </div>
                            <h5 class="fw-bold mb-2">Push Attendance to ERP?</h5>
                            <p class="text-muted mb-0">This will send today's attendance records to the ERP system.</p>
                            <div class="mt-3 p-3 rounded" style="background: #f8f9fa;">
                                <small class="text-muted">
                                    <i class="fas fa-calendar-day me-1"></i>
                                    Date: <span class="fw-semibold"><?php echo date('F j, Y', strtotime($date)); ?></span>
                                </small>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cancel
                            </button>
                            <button type="button" class="btn btn-primary" id="btnConfirmSync">
                                <i class="fas fa-paper-plane me-2"></i>Sync Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#syncConfirmModal').remove();
        $('body').append(modalHtml);
        
        setTimeout(() => {
            const modal = new bootstrap.Modal(document.getElementById('syncConfirmModal'));
            modal.show();
            
            $('#btnConfirmSync').off('click').on('click', function() {
                modal.hide();
                executeSyncToErp();
            });
        }, 100);
    }
    
    function executeSyncToErp() {
        const $btn = $('#btnSyncToErp');
        const originalHtml = $btn.html();
        
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Syncing...');
        
        $.ajax({
            url: 'scripts/push_attendance_to_erp.php',
            type: 'GET',
            data: { date: '<?php echo $date; ?>' },
            success: function(response) {
                if (response.success) {
                    showSyncResultModal(response);
                } else {
                    showSyncErrorModal(response.message || 'Unknown error occurred');
                }
            },
            error: function(xhr, status, error) {
                showSyncErrorModal('Connection error: Unable to reach ERP server. Please check your network connection.');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    }
    
    function showSyncResultModal(response) {
        const apiResponse = response.api_response || {};
        const recordsSent = response.records_sent || 0;
        const recordsSkipped = response.records_skipped || 0;
        const totalRecords = response.total_records || recordsSent;
        const skippedDetails = response.skipped_details || [];
        const httpStatus = apiResponse.httpStatusCode || apiResponse.HTTPStatusCode || 'Unknown';
        const errorMsg = apiResponse.Error || apiResponse.error || '';
        const resultStatus = apiResponse.Result?.result || apiResponse.result || 'Unknown';
        const valueMsg = apiResponse.Value || apiResponse.value || '';
        
        let statusBadge = '';
        let statusIcon = '';
        let messageText = '';
        
        if (httpStatus == 200 && (resultStatus === 'updated' || resultStatus.toLowerCase() === 'success')) {
            statusBadge = '<span class="badge bg-success">Success</span>';
            statusIcon = '<i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>';
            messageText = valueMsg || 'Attendance records synchronized successfully';
        } else if (errorMsg) {
            statusBadge = '<span class="badge bg-danger">Error</span>';
            statusIcon = '<i class="fas fa-exclamation-circle text-danger" style="font-size: 3rem;"></i>';
            messageText = errorMsg;
        } else {
            statusBadge = '<span class="badge bg-warning">Partial</span>';
            statusIcon = '<i class="fas fa-info-circle text-warning" style="font-size: 3rem;"></i>';
            messageText = 'Sync completed with warnings';
        }
        
        let skippedSection = '';
        if (recordsSkipped > 0 && skippedDetails.length > 0) {
            let skippedList = '';
            skippedDetails.forEach(skip => {
                skippedList += `
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <div class="fw-semibold small">${skip.name}</div>
                            <div class="text-muted" style="font-size: 0.75rem;">Emp: ${skip.empcode}</div>
                        </div>
                        <span class="badge bg-light text-dark">${skip.reason}</span>
                    </div>
                `;
            });
            
            skippedSection = `
                <div class="mt-4">
                    <button class="btn btn-sm btn-outline-warning w-100" type="button" data-bs-toggle="collapse" data-bs-target="#skippedList">
                        <i class="fas fa-exclamation-triangle me-2"></i>${recordsSkipped} Record${recordsSkipped > 1 ? 's' : ''} Not Sent
                        <i class="fas fa-chevron-down ms-2"></i>
                    </button>
                    <div class="collapse mt-2" id="skippedList">
                        <div class="card card-body" style="max-height: 250px; overflow-y: auto;">
                            ${skippedList}
                        </div>
                    </div>
                </div>
            `;
        }
        
        const modalHtml = `
            <div class="modal fade" id="syncResultModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header border-0 pb-0">
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center pt-0 pb-4">
                            <div class="mb-3">${statusIcon}</div>
                            <h4 class="fw-bold mb-3">ERP Sync ${statusBadge}</h4>
                            <p class="text-muted mb-4">${messageText}</p>
                            
                            <div class="d-flex justify-content-center gap-4 mb-3">
                                <div class="text-center">
                                    <div class="fw-bold" style="font-size: 2rem; color: #10b981;">${recordsSent}</div>
                                    <div class="small text-muted">Sent</div>
                                </div>
                                ${recordsSkipped > 0 ? `
                                <div class="text-center">
                                    <div class="fw-bold" style="font-size: 2rem; color: #f59e0b;">${recordsSkipped}</div>
                                    <div class="small text-muted">Skipped</div>
                                </div>
                                ` : ''}
                                <div class="text-center">
                                    <div class="fw-bold" style="font-size: 2rem; color: #667eea;">${totalRecords}</div>
                                    <div class="small text-muted">Total</div>
                                </div>
                            </div>
                            
                            ${skippedSection}
                            
                            <button type="button" class="btn btn-primary px-4 mt-3" data-bs-dismiss="modal" onclick="location.reload()">
                                <i class="fas fa-check me-2"></i>Close & Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#syncResultModal').remove();
        $('body').append(modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('syncResultModal'));
        modal.show();
    }
    
    function showSyncErrorModal(errorMessage) {
        const modalHtml = `
            <div class="modal fade" id="syncErrorModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header border-0 bg-danger bg-opacity-10">
                            <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Sync Failed</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">${errorMessage}</p>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#syncErrorModal').remove();
        $('body').append(modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('syncErrorModal'));
        modal.show();
    }
});
</script>
</body>
</html>
