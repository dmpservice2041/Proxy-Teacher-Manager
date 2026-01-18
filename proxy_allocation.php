<?php
require_once 'config/app.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/header.php';
require_once 'services/ProxyAllocationService.php';

$allocationService = new ProxyAllocationService();
$date = $_POST['date_param'] ?? $_GET['date'] ?? date('Y-m-d');
$allocationService->enableBulkMode($date); // Optimization: Preload all data for the day
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_allocations'])) {
    try {
        $proxyModel = new ProxyAssignment();
        $allocations = $_POST['allocations'] ?? [];
        $savedCount = 0;

        foreach ($allocations as $slotKey => $proxyId) {
            if (empty($proxyId)) continue;

            // slotKey format: absent_teacher_id|period_no|class_id(|subject_id)
            $parts = explode('|', $slotKey);
            $absentId = $parts[0];
            $periodNo = $parts[1];
            $classId = $parts[2];
            $subjectId = $parts[3] ?? null;
            
            $proxyModel->assign($date, $absentId, $proxyId, $classId, $periodNo, 'MANUAL', 'Interactive Batch Allocation', $subjectId);
            $savedCount++;
        }

        $message = "Successfully saved $savedCount proxy assignments.";
    } catch (Exception $e) {
        $error = "Error saving allocations: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
    try {
        $proxyModel = new ProxyAssignment();
        $proxyModel->deleteAllForDate($date);
        $message = "All proxy assignments for " . $date . " have been deleted.";
        // Use JavaScript redirect to preserve date parameter
        echo '<script>window.location.href = "proxy_allocation.php?date=' . urlencode($date) . '";</script>';
        exit;
    } catch (Exception $e) {
        $error = "Error deleting allocations: " . $e->getMessage();
    }
}

require_once 'models/DailyOverrides.php';
$overridesModel = new DailyOverrides();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_override'])) {
    try {
        $type = $_POST['override_type']; // TEACHER_DUTY or CLASS_ABSENT
        $targetId = $_POST['target_id'];
        $period = !empty($_POST['period_no']) ? $_POST['period_no'] : null;
        $reason = $_POST['reason'];
        
        $overridesModel->add($date, $type, $targetId, $period, $reason);
        $message = "Schedule override added successfully.";
    } catch (Exception $e) {
        $error = "Error adding override: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_override'])) {
    try {
        $overridesModel->delete($_POST['override_id']);
        $message = "Override removed.";
    } catch (Exception $e) {
        $error = "Error deleting override: " . $e->getMessage();
    }
}

$todaysOverrides = $overridesModel->getAllForDate($date);

require_once 'models/Teacher.php';
require_once 'models/Classes.php';
$allTeachersList = (new Teacher())->getAllActive(); 
$allClassesList = (new Classes())->getAll();

$absentSlots = $allocationService->getAbsentSlots($date);
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
        --success-gradient: linear-gradient(135deg, #059669 0%, #10B981 100%);
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
        margin-bottom: 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .page-title {
        font-size: 1.875rem;
        font-weight: 800;
        color: #111827;
        margin: 0;
    }

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
    
    .table tbody tr:hover {
        background-color: #fafbfc;
    }

    .info-badge {
        padding: 0.35em 0.8em;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 6px;
        display: inline-block;
        margin-right: 4px;
    }
    .badge-period { background-color: #eff6ff; color: #1d4ed8; }
    .badge-class { background-color: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
    .badge-group { background-color: #fffbeb; color: #92400e; border: 1px solid #fcd34d; }

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
    
    .nav-tabs .nav-link {
        color: #6B7280;
        border: none;
        border-bottom: 2px solid transparent;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
    }
    .nav-tabs .nav-link.active {
        color: #4F46E5;
        border-bottom-color: #4F46E5;
        background: none;
    }

    /* Select2 Customization */
    /* Hide uninitialized select to prevent FOUC */
    select.candidate-select:not(.select2-hidden-accessible) {
        opacity: 0;
        height: 0;
        width: 0;
        position: absolute;
        overflow: hidden;
    }

    .select2-container--default .select2-selection--single {
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        height: 48px; /* Taller input */
        background-color: #fff;
        display: flex;
        align-items: center;
        transition: all 0.2s;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }
    .select2-container--default .select2-selection--single:hover {
        border-color: #cbd5e1;
    }
    .select2-container--default.select2-container--open .select2-selection--single {
        border-color: #4F46E5;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: normal;
        padding-left: 16px;
        padding-right: 30px;
        color: #1e293b;
        font-weight: 500;
        font-size: 0.95rem;
        width: 100%;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 46px;
        top: 1px;
        right: 12px;
        width: 20px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #94a3b8 transparent transparent transparent;
        border-width: 6px 5px 0 5px;
    }
    .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
        border-color: transparent transparent #94a3b8 transparent;
        border-width: 0 5px 6px 5px;
    }
    .select2-dropdown {
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        border-top: none;
        overflow: hidden;
        margin-top: 4px; /* Space between input and dropdown */
    }
    .select2-search--dropdown {
        padding: 8px;
    }
    .select2-search__field {
        border-radius: 0.375rem;
        border: 1px solid #e2e8f0;
        padding: 6px 12px;
    }
    .select2-results__option {
        padding: 8px 16px;
        font-size: 0.9rem;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #4F46E5;
        color: white;
    }
    
    .selected-proxy-wrapper {
        min-height: 42px;
        display: flex;
        align-items: center;
    }
    
    .collision-warning {
        font-size: 0.8rem;
        display: block;
        margin-top: 2px;
    }
    
    /* Enhanced Header Styles */
    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 2rem 2.5rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
    }
    
    .page-title {
        color: white;
        font-weight: 700;
        font-size: 1.75rem;
        margin-bottom: 0.25rem;
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
    
    .btn-daily-adjustments {
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }
    
    .btn-daily-adjustments:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .btn-auto-generate {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(245, 87, 108, 0.3);
    }
    
    .btn-auto-generate:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(245, 87, 108, 0.4);
    }
    
    .btn-view-report {
        background: white;
        color: #667eea;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-view-report:hover {
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
</style>

<div class="main-content">
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-md-4">
                <h1 class="page-title">Proxy Allocation</h1>
                <p class="page-subtitle mb-0">Manage substitutions for <?php echo date('l, F j, Y', strtotime($date)); ?></p>
            </div>
            <div class="col-md-8">
                <div class="header-actions justify-content-md-end">
                    <button class="btn btn-header btn-daily-adjustments" data-bs-toggle="modal" data-bs-target="#overridesModal">
                        <i class="fas fa-calendar-check"></i>
                        <span>Daily Adjustments</span>
                    </button>
                    <button class="btn btn-header btn-auto-generate" id="btnAutoAllocate">
                        <i class="fas fa-magic"></i>
                        <span>Auto Generate</span>
                    </button>
                    <form class="m-0">
                        <input type="date" name="date" class="date-input" value="<?php echo $date; ?>" onchange="this.form.submit()">
                    </form>
                    <a href="reports.php" class="btn btn-header btn-view-report">
                        <i class="fas fa-chart-bar"></i>
                        <span>View Report</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-3">
            <i class="fas fa-check-circle fs-4 text-success"></i>
            <div><?php echo $message; ?></div>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-3">
            <i class="fas fa-exclamation-circle fs-4 text-danger"></i>
            <div><?php echo $error; ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" id="allocationForm">
        <input type="hidden" name="date_param" value="<?php echo htmlspecialchars($date); ?>">
        <div class="table-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th width="35%">Absent Teacher & Slot Details</th>
                            <th width="35%">Available Proxy Candidate</th>
                            <th width="30%">Selection Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($absentSlots)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-5">
                                    <div class="text-muted mb-2"><i class="fas fa-calendar-day fa-3x opacity-25"></i></div>
                                    <h5 class="text-muted">No absent teachers found for this date.</h5>
                                    <p class="small text-muted">Enjoy a proxy-free day!</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($absentSlots as $slot): ?>
                                <?php 
                                $existingProxyId = $slot['assigned_proxy_id'];
                                $candidates = $allocationService->getAvailableCandidates($date, $slot['period_no'], $existingProxyId);
                                $slotKey = $slot['teacher_id'] . '|' . $slot['period_no'] . '|' . $slot['class_id'];
                                if (!empty($slot['subject_id'])) {
                                    $slotKey .= '|' . $slot['subject_id'];
                                }
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3 mb-2">
                                            <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                                                <?php echo substr($slot['teacher_name'], 0, 1); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($slot['teacher_name']); ?></div>
                                                <div class="small text-secondary fw-bold text-uppercase"><?php echo htmlspecialchars($slot['subject_name']); ?></div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <span class="info-badge badge-period">Period <?php echo $slot['period_no']; ?></span>
                                            <span class="info-badge badge-class"><?php echo $slot['standard'] . '-' . $slot['division']; ?></span>
                                            <?php if ($slot['group_name']): ?>
                                                <span class="info-badge badge-group">Group: <?php echo htmlspecialchars($slot['group_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="mb-1 position-relative">
                                            <select name="allocations[<?php echo $slotKey; ?>]" class="form-select candidate-select" data-slot="<?php echo $slotKey; ?>">
                                                <option value="">Select Replacement...</option>
                                                <?php foreach ($candidates as $candidate): ?>
                                                    <option value="<?php echo $candidate['id']; ?>" 
                                                            data-initial-free="<?php echo $candidate['free_periods']; ?>"
                                                            <?php echo ($existingProxyId == $candidate['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($candidate['name']); ?> (<?php echo $candidate['free_periods']; ?> Free)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <?php if (empty($candidates)): ?>
                                            <div class="d-inline-flex align-items-center gap-2 mt-2 px-3 py-2 rounded-3 bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size: 0.85rem;">
                                                <i class="fas fa-exclamation-circle"></i> 
                                                <span class="fw-medium">No free teachers available</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="selected-proxy-wrapper">
                                            <div id="display-<?php echo str_replace('|', '-', $slotKey); ?>" class="selected-proxy-name <?php echo $existingProxyId ? 'text-success' : 'text-muted'; ?> fw-bold">
                                                <?php 
                                                if ($existingProxyId) {
                                                    foreach ($candidates as $c) {
                                                        if ($c['id'] == $existingProxyId) {
                                                            echo '<i class="fas fa-check-circle me-2"></i>' . htmlspecialchars($c['name']);
                                                            break;
                                                        }
                                                    }
                                                } else {
                                                    echo '<span class="fw-normal fst-italic"><i class="fas fa-minus-circle me-2"></i>Not Assigned</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($absentSlots)): ?>
            <div class="card-footer bg-white p-4 border-top">
                <div class="d-flex justify-content-between align-items-center">
                    <button type="submit" name="delete_all" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete ALL proxy assignments for this date? This cannot be undone.')">
                        <i class="fas fa-trash-alt me-2"></i> Clear All
                    </button>
                    <div class="d-flex gap-2">
                         <button type="button" class="btn btn-primary px-4 shadow-sm" id="previewBtn">
                            <i class="fas fa-eye me-2"></i> Preview
                        </button>
                        <button type="submit" name="save_allocations" class="btn btn-success px-5 fw-bold shadow-sm">
                            <i class="fas fa-save me-2"></i> Save Allocations
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem;">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Confirm Assignments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="previewTableContainer" class="rounded-3 overflow-hidden border">
                    <!-- Dynamic content -->
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep Editing</button>
                <button type="button" class="btn btn-success px-4" onclick="$('#allocationForm').submit()">Confirm & Save</button>
            </div>
        </div>
    </div>
</div>

<!-- External Libs -->
<!-- Daily Overrides Modal -->
<div class="modal fade" id="overridesModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Daily Schedule Adjustments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Make temporary adjustments for <strong class="text-dark"><?php echo date('d M Y', strtotime($date)); ?></strong>.</p>
                
                <ul class="nav nav-tabs mb-4" id="overrideTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="duty-tab" data-bs-toggle="tab" data-bs-target="#duty-pane" type="button" role="tab">Teacher Duties</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="absence-tab" data-bs-toggle="tab" data-bs-target="#absence-pane" type="button" role="tab">Class Absences</button>
                    </li>
                </ul>
                
                <div class="tab-content" id="overrideTabContent">
                    <!-- Teacher Duty Tab -->
                    <div class="tab-pane fade show active" id="duty-pane" role="tabpanel">
                        <form method="POST" class="row g-3 align-items-end mb-4 bg-light p-3 rounded-3 border">
                            <input type="hidden" name="add_override" value="1">
                            <input type="hidden" name="override_type" value="TEACHER_DUTY">
                            
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Teacher</label>
                                <select name="target_id" class="form-select" required>
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($allTeachersList as $t): ?>
                                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Period</label>
                                <select name="period_no" class="form-select">
                                    <option value="">All Day</option>
                                    <?php for($p=1; $p<=8; $p++): ?>
                                        <option value="<?php echo $p; ?>">Period <?php echo $p; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Reason</label>
                                <input type="text" name="reason" class="form-control" placeholder="e.g. Exam Duty" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Add</button>
                            </div>
                        </form>
                        
                        <!-- List of Duties -->
                        <div class="list-group">
                            <div class="list-group-item bg-light fw-bold text-muted small">Current Duties for Today</div>
                            <?php 
                            $hasDuties = false;
                            foreach ($todaysOverrides as $ov): 
                                if ($ov['type'] !== 'TEACHER_DUTY') continue;
                                $hasDuties = true;
                            ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($ov['target_name']); ?></div>
                                        <div class="small text-muted">
                                            <?php echo $ov['period_no'] ? "Period " . $ov['period_no'] : "Full Day"; ?>
                                            &bull; 
                                            <span class="text-dark"><?php echo htmlspecialchars($ov['reason']); ?></span>
                                        </div>
                                    </div>
                                    <form method="POST" class="m-0">
                                        <input type="hidden" name="delete_override" value="1">
                                        <input type="hidden" name="override_id" value="<?php echo $ov['id']; ?>">
                                        <button type="submit" class="btn btn-link text-danger p-0" title="Remove"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$hasDuties): ?>
                                <div class="list-group-item text-center text-muted py-3 small">No extra duties assigned.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Class Absence Tab -->
                    <div class="tab-pane fade" id="absence-pane" role="tabpanel">
                        <form method="POST" class="row g-3 align-items-end mb-4 bg-light p-3 rounded-3 border">
                            <input type="hidden" name="add_override" value="1">
                            <input type="hidden" name="override_type" value="CLASS_ABSENT">
                            
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Class</label>
                                <select name="target_id" class="form-select" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($allClassesList as $c): ?>
                                        <option value="<?php echo $c['id']; ?>">
                                            <?php echo $c['standard'].'-'.$c['division'].' ('.$c['section_name'].')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Period</label>
                                <select name="period_no" class="form-select">
                                    <option value="">All Day</option>
                                    <?php for($p=1; $p<=8; $p++): ?>
                                        <option value="<?php echo $p; ?>">Period <?php echo $p; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Reason</label>
                                <input type="text" name="reason" class="form-control" placeholder="e.g. Field Trip" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Add</button>
                            </div>
                        </form>
                        
                        <!-- List of Absences -->
                        <div class="list-group">
                            <div class="list-group-item bg-light fw-bold text-muted small">Class Absences for Today</div>
                            <?php 
                            $hasAbsences = false;
                            foreach ($todaysOverrides as $ov): 
                                if ($ov['type'] !== 'CLASS_ABSENT') continue;
                                $hasAbsences = true;
                            ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($ov['target_name']); ?></div>
                                        <div class="small text-muted">
                                            <?php echo $ov['period_no'] ? "Period " . $ov['period_no'] : "Full Day"; ?>
                                            &bull; 
                                            <span class="text-dark"><?php echo htmlspecialchars($ov['reason']); ?></span>
                                        </div>
                                    </div>
                                    <form method="POST" class="m-0">
                                        <input type="hidden" name="delete_override" value="1">
                                        <input type="hidden" name="override_id" value="<?php echo $ov['id']; ?>">
                                        <button type="submit" class="btn btn-link text-danger p-0" title="Remove"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$hasAbsences): ?>
                                <div class="list-group-item text-center text-muted py-3 small">No class absences recorded.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- ... existing scripts ... -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // 1. Initialize Select2
    $('.candidate-select').select2({
        width: '100%',
        placeholder: "Search for a teacher...",
        allowClear: true
    }).on('change', function() {
        const slotslug = $(this).data('slot').replace(/\|/g, '-');
        const val = $(this).val();
        const selectedText = val ? $(this).find('option:selected').text().split('(')[0].trim() : '';
        
        const $display = $(`#display-${slotslug}`);
        
        if (val) {
            $display.html(`<i class="fas fa-check-circle me-2"></i>${selectedText}`)
                    .addClass('text-success').removeClass('text-muted');
        } else {
            $display.html('<span class="fw-normal fst-italic"><i class="fas fa-minus-circle me-2"></i>Not Assigned</span>')
                    .addClass('text-muted').removeClass('text-success');
        }
        updateFreePeriodCounts();
    });

    // 2. Collision Detection
    $('.candidate-select').on('change', function() {
        const periodTeachers = {}; // period -> { teacher_id -> [elements] }
        let hasCollision = false;
        
        // Reset styles
        $('.candidate-select').next('.select2-container').find('.select2-selection').css('border-color', '#e5e7eb');
        $('.collision-warning').remove();
        // Remove text-danger/success from display names (reset to success if assigned)
        $('.selected-proxy-name.text-danger').removeClass('text-danger').addClass('text-success');

        // Group selections by period
        $('.candidate-select').each(function() {
            const val = $(this).val();
            if (!val) return;
            
            const slotKey = $(this).data('slot');
            const period = slotKey.split('|')[1];
            
            if (!periodTeachers[period]) periodTeachers[period] = {};
            if (!periodTeachers[period][val]) periodTeachers[period][val] = [];
            
            periodTeachers[period][val].push($(this));
        });

        // Detect collisions
        for (const period in periodTeachers) {
            for (const teacherId in periodTeachers[period]) {
                const elements = periodTeachers[period][teacherId];
                if (elements.length > 1) {
                    // Check logic for Merged Groups (Same Class)
                    const classIds = new Set();
                    elements.forEach($el => {
                        const slotKey = $el.data('slot'); 
                        const classId = slotKey.split('|')[2];
                        classIds.add(classId);
                    });

                    if (classIds.size === 1) {
                        // Same Class -> Valid Merge (Green)
                        elements.forEach($el => {
                             $el.next('.select2-container').find('.select2-selection').css('border-color', '#10B981'); // Success Green
                             const slotKey = $el.data('slot').replace(/\|/g, '-');
                             const $display = $(`#display-${slotKey}`);
                             
                             if ($display.find('.collision-warning').length === 0) {
                                 $display.append(' <div class="collision-warning text-success fw-normal"><i class="fas fa-code-branch me-1"></i>Merged Group</div>');
                             }
                        });
                    } else {
                        // Diff Class -> Invalid Collision (Red)
                        hasCollision = true;
                        elements.forEach($el => {
                            $el.next('.select2-container').find('.select2-selection').css('border-color', '#EF4444'); // Danger Red
                            const slotKey = $el.data('slot').replace(/\|/g, '-');
                            const $display = $(`#display-${slotKey}`);
                            
                            $display.removeClass('text-success').addClass('text-danger');
                            if ($display.find('.collision-warning').length === 0) {
                                $display.append(' <div class="collision-warning text-danger fw-bold"><i class="fas fa-exclamation-circle me-1"></i>Double Assignment!</div>');
                            }
                        });
                    }
                }
            }
        }

        // Global Warning
        if (hasCollision) {
            if (!$('#global-warning').length) {
                // Insert before table-card
                $('<div id="global-warning" class="alert alert-danger shadow-sm rounded-3 d-flex align-items-center mb-4"><i class="fas fa-exclamation-triangle me-2 fs-4"></i><div><strong>Conflict Detected:</strong> One or more teachers are assigned to multiple classes in the same period.</div></div>').insertBefore('.table-card');
            }
        } else {
            $('#global-warning').remove();
        }
    });

    // 3. Free Period Calculation
    function updateFreePeriodCounts() {
        $('.candidate-select').each(function() {
            const $select = $(this);
            
            $select.find('option').each(function() {
                const $option = $(this);
                const teacherId = $option.val();
                const initialFree = parseInt($option.attr('data-initial-free'));
                
                if (teacherId && !isNaN(initialFree)) {
                    // Check usage across all dropdowns
                    const busyPeriods = new Set();
                    
                    $('.candidate-select').each(function() {
                        if ($(this).val() == teacherId) {
                            const slot = $(this).data('slot'); 
                            const period = slot.split('|')[1];
                            busyPeriods.add(period);
                        }
                    });
                    
                    const usedCount = busyPeriods.size;
                    const remaining = initialFree - usedCount;
                    
                    const teacherName = $option.text().split('(')[0].trim();
                    $option.text(`${teacherName} (${remaining} Free)`);
                }
            });

            // Trigger Select2 update
            if ($select.data('select2')) {
                const selection = $select.val();
                if (selection) {
                    const $opt = $select.find(`option[value="${selection}"]`);
                    // We only want the name in the box, not the count if possible, but standard Select2 shows label
                    $select.next('.select2-container').find('.select2-selection__rendered').text($opt.text());
                }
            }
        });
    }

    // Initial Trigger
    $('.candidate-select').first().trigger('change');
    updateFreePeriodCounts();

    // 4. Preview Modal
    $('#previewBtn').on('click', function() {
        let previewHtml = '<table class="table table-hover mb-0"><thead><tr class="table-light"><th>Absent Teacher</th><th>Period</th><th>Subject</th><th>Replacement</th></tr></thead><tbody>';
        let count = 0;

        $('.candidate-select').each(function() {
            const val = $(this).val();
            if (!val) return;

            const proxyName = $(this).find('option:selected').text().split('(')[0].trim();
            const $row = $(this).closest('tr');
            const absentName = $row.find('.fw-bold.text-dark').text();
            const period = $row.find('.badge-period').text().replace('Period ','');
            const subject = $row.find('.small.text-secondary').text();

            previewHtml += `<tr>
                <td class="fw-bold">${absentName}</td>
                <td><span class="badge bg-light text-dark border">P${period}</span></td>
                <td>${subject}</td>
                <td class="text-success fw-bold"><i class="fas fa-check me-1"></i>${proxyName}</td>
            </tr>`;
            count++;
        });

        previewHtml += '</tbody></table>';

        if (count === 0) {
            previewHtml = '<div class="p-4 text-center text-muted"><i class="fas fa-inbox fa-3x mb-3 opacity-25"></i><br>No matching assignments found to save.</div>';
        }

        $('#previewTableContainer').html(previewHtml);
        var previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
        previewModal.show();
    });

    // 5. Auto Generate Handler
    $('#btnAutoAllocate').on('click', function() {
        showAutoGenerateConfirmModal();
    });
    
    function showAutoGenerateConfirmModal() {
        const modalHtml = `
            <div class="modal fade" id="autoGenerateConfirmModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header border-0 pb-2" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <h5 class="modal-title text-white fw-bold">
                                <i class="fas fa-magic me-2"></i>Auto Generate Proxies
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body py-4">
                            <div class="text-center mb-3">
                                <i class="fas fa-robot" style="font-size: 3rem; color: #667eea;"></i>
                            </div>
                            <h5 class="fw-bold text-center mb-3">Automatically Assign All Empty Slots?</h5>
                            <p class="text-muted text-center mb-4">This will use smart logic to assign the best matching teacher for each unassigned slot.</p>
                            
                            <div class="card bg-light border-0 mb-3">
                                <div class="card-body py-3">
                                    <h6 class="fw-semibold mb-2"><i class="fas fa-cog me-2 text-primary"></i>Assignment Logic:</h6>
                                    <ul class="mb-0 small">
                                        <li>Prioritizes teachers from the same section</li>
                                        <li>Considers subject expertise</li>
                                        <li>Avoids large priority gaps</li>
                                        <li>Prevents teacher overloading</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cancel
                            </button>
                            <button type="button" class="btn btn-primary" id="btnConfirmAutoGenerate">
                                <i class="fas fa-bolt me-2"></i>Generate Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#autoGenerateConfirmModal').remove();
        $('body').append(modalHtml);
        
        setTimeout(() => {
            const modal = new bootstrap.Modal(document.getElementById('autoGenerateConfirmModal'));
            modal.show();
            
            $('#btnConfirmAutoGenerate').off('click').on('click', function() {
                modal.hide();
                executeAutoGenerate();
            });
        }, 100);
    }
    
    function executeAutoGenerate() {
        const $btn = $('#btnAutoAllocate');
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        
        $.ajax({
            url: 'scripts/auto_allocate_proxies.php',
            type: 'POST',
            data: { date: '<?php echo $date; ?>' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAutoGenerateResultModal(response.count);
                } else {
                    showAutoGenerateErrorModal(response.message);
                }
            },
            error: function(xhr) {
                showAutoGenerateErrorModal('Connection error occurred. Please try again.');
                console.error(xhr.responseText);
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    }
    
    function showAutoGenerateResultModal(count) {
        const modalHtml = `
            <div class="modal fade" id="autoGenerateResultModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header border-0 pb-0">
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center pt-0 pb-4">
                            <div class="mb-3">
                                <i class="fas fa-check-circle text-success" style="font-size: 3.5rem;"></i>
                            </div>
                            <h4 class="fw-bold mb-2">Auto-Generation Complete!</h4>
                            <p class="text-muted mb-4">Successfully assigned proxies to empty slots</p>
                            
                            <div class="d-inline-block px-4 py-3 rounded" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <div class="fw-bold text-white" style="font-size: 2.5rem;">${count}</div>
                                <div class="small text-white opacity-75">Assignments Created</div>
                            </div>
                            
                            <button type="button" class="btn btn-primary px-4 mt-4" onclick="window.location.href='proxy_allocation.php?date=<?php echo urlencode($date); ?>'">
                                <i class="fas fa-sync-alt me-2"></i>Reload & View Assignments
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#autoGenerateResultModal').remove();
        $('body').append(modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('autoGenerateResultModal'));
        
        // Auto-reload when modal is closed (clicked X or outside)
        document.getElementById('autoGenerateResultModal').addEventListener('hidden.bs.modal', function () {
            window.location.href = 'proxy_allocation.php?date=<?php echo urlencode($date); ?>';
        });
        
        modal.show();
    }
    
    function showAutoGenerateErrorModal(errorMessage) {
        const modalHtml = `
            <div class="modal fade" id="autoGenerateErrorModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header border-0 bg-danger bg-opacity-10">
                            <h5 class="modal-title text-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>Auto-Generation Failed
                            </h5>
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
        
        $('#autoGenerateErrorModal').remove();
        $('body').append(modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('autoGenerateErrorModal'));
        modal.show();
    }
});
</script>
</body>
</html>
