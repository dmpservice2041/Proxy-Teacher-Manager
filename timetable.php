<?php
require_once 'config/app.php';
require_once 'includes/header.php';
require_once 'models/Teacher.php';
require_once 'models/Timetable.php';
require_once 'models/Classes.php';
require_once 'models/Subject.php';
require_once 'models/Settings.php';

$viewMode = $_GET['view_mode'] ?? 'teacher';
$teacherId = $_GET['teacher_id'] ?? '';
$classId = $_GET['class_id'] ?? '';
$message = '';
$error = '';

$teacherModel = new Teacher();
$timetableModel = new Timetable();
$classModel = new Classes();
$subjectModel = new Subject();
$settingsModel = new Settings();
require_once 'models/BlockedPeriod.php';
$blockedPeriodModel = new BlockedPeriod();

$totalPeriods = $settingsModel->get('total_periods', 8);
$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['delete_entry'])) {
            $timetableModel->delete($_POST['entry_id']);
            $message = "Entry deleted.";
        } elseif (isset($_POST['add_entry']) || isset($_POST['update_entry'])) {
            // Fix: If teacher_id_select is provided (Class View), use it. Otherwise use hidden entry.
            $tid = !empty($_POST['teacher_id_select']) ? $_POST['teacher_id_select'] : $_POST['teacher_id_entry']; 
            $cid = $_POST['class_id'];
            $sid = $_POST['subject_id'];
            $dow = $_POST['day_of_week'];
            $period = $_POST['period_no'];
            $groupName = !empty($_POST['group_name']) ? trim($_POST['group_name']) : null;
            $entryId = $_POST['entry_id'] ?? null;

            // VALIDATION: Check if Period is Blocked for this Class/Day
            $dayName = $days[$dow]; // 1='Monday' etc.
            if ($blockedPeriodModel->isBlocked($dayName, $period, $cid)) {
                 $error = "Cannot add entry: Period $period is BLOCKED for this class on $dayName.";
            } else {
                if ($entryId) {
                    $timetableModel->update($entryId, $cid, $sid, $groupName);
                    $message = "Timetable entry updated.";
                } else {
                    // Add new
                    $timetableModel->add($tid, $cid, $sid, $dow, $period, $groupName);
                    $message = "Timetable entry added.";
                }
            }
        } elseif (isset($_POST['add_group_entry'])) {
            // Add multiple group entries at once
            $tid = !empty($_POST['teacher_id_select']) ? $_POST['teacher_id_select'] : $_POST['teacher_id_entry'];
            $cid = $_POST['class_id'];
            $dow = $_POST['day_of_week'];
            $period = $_POST['period_no'];
            $subjects = $_POST['group_subjects'] ?? [];
            $groupNames = $_POST['group_names'] ?? [];
            
            $added = 0;
            $blockErrorCount = 0;
            $dayName = $days[$dow];

            foreach ($subjects as $index => $sid) {
                if (!empty($sid)) {
                    // Check Block
                    if ($blockedPeriodModel->isBlocked($dayName, $period, $cid)) {
                         $blockErrorCount++;
                         continue;
                    }
                    
                    $groupName = !empty($groupNames[$index]) ? trim($groupNames[$index]) : null;
                    $timetableModel->add($tid, $cid, $sid, $dow, $period, $groupName);
                    $added++;
                }
            }
            if ($blockErrorCount > 0) {
                 $error = "Skipped $blockErrorCount entries because the period is blocked.";
            } else {
                 $message = "$added group entry/entries added.";
            }
        }
        $teacherId = $_POST['teacher_id_hidden'] ?? $teacherId; 
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$teachers = $teacherModel->getAllActive();
$classes = $classModel->getAll();
try {
    $subjects = $subjectModel->getAll();
} catch (Exception $e) {
    $error = "Error loading subjects: " . $e->getMessage();
    $subjects = [];
}

$schedule = [];
if ($viewMode === 'teacher' && $teacherId) {
    for ($d = 1; $d <= 6; $d++) {
        $daySchedule = $timetableModel->getTeacherSchedule($teacherId, $d);
        $schedule[$d] = $daySchedule;
    }
} elseif ($viewMode === 'class' && $classId) {
    for ($d = 1; $d <= 6; $d++) {
        $daySchedule = $timetableModel->getClassSchedule($classId, $d);
        $schedule[$d] = $daySchedule;
    }
}

// $days defined at top
?>


<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
        --primary-color: #4F46E5;
        --secondary-color: #6B7280;
        --success-color: #10B981;
        --danger-color: #EF4444;
        --warning-color: #F59E0B;
        --bg-light: #F9FAFB;
        --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --hover-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    body {
        background-color: #F3F4F6;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    .main-content {
        padding: 2rem;
    }

    .page-header {
        background: var(--primary-gradient);
        padding: 2.5rem 2rem;
        border-radius: 16px;
        color: white;
        margin-bottom: 2rem;
        box-shadow: var(--card-shadow);
        position: relative;
        /* overflow: hidden; REMOVED to allow dropdowns to spill out */
    }

    .page-header::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        background: url('assets/pattern.png'); /* Optional texture */
        opacity: 0.1;
        border-radius: 16px; /* Moved radius here since parent is not clipping */
        overflow: hidden; 
    }

    /* Header Content Z-Index Fix */
    .page-header > * {
        position: relative;
        z-index: 1;
    }

    /* ... other styles ... */

    .glass-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: var(--card-shadow);
        border-radius: 16px;
        transition: all 0.3s ease;
    }

    /* Filter Card Styling */
    .filter-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem 2rem;
        border: 1px solid #E5E7EB;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .filter-label {
        font-weight: 600;
        color: #4B5563;
        font-size: 0.95rem;
        margin-right: 1rem;
        white-space: nowrap;
    }
    .slot-content {
        width: 100%;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
    }

    .slot-badge {
        background-color: #EEF2FF;
        color: var(--primary-color);
        font-weight: 600;
        font-size: 0.8rem;
        padding: 4px 10px;
        border-radius: 12px;
        line-height: 1.2;
        border: 1px solid rgba(79, 70, 229, 0.1);
        display: inline-block;
        max-width: 100%;
        text-overflow: ellipsis;
        white-space: nowrap;
        overflow: hidden;
    }

    .slot-text {
        color: #374151;
        font-size: 0.85rem;
        font-weight: 500;
        line-height: 1.3;
    }
    
    .slot-subtext {
        color: #6B7280;
        font-size: 0.75rem;
    }
    
    .slot-cell.blocked {
        background-color: #FEE2E2 !important; /* light red */
        cursor: not-allowed;
        opacity: 0.7;
    }
    
    /* Ensure empty slot content (plus icon) is centered */
    .slot-cell.empty {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Premium Select2 Styling */
    .select2-container--default .select2-selection--single {
        background-color: #F9FAFB;
        border: 1px solid #D1D5DB;
        border-radius: 8px;
        height: 46px; /* Taller for better touch target */
        display: flex;
        align-items: center;
        transition: all 0.2s ease;
    }
    
    .select2-container--default .select2-selection--single:hover {
        border-color: var(--primary-color);
        background-color: #fff;
    }
    
    .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }
    
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #111827;
        font-weight: 500;
        padding-left: 12px;
        padding-right: 30px; /* Space for arrow */
        line-height: normal;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 44px;
        width: 30px;
        right: 4px;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color: #6B7280 transparent transparent transparent;
        border-width: 6px 5px 0 5px;
    }
    
    .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
        border-color: transparent transparent #6B7280 transparent;
        border-width: 0 5px 6px 5px;
    }
    
    .select2-dropdown {
        border: 1px solid #E5E7EB;
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        z-index: 1050; /* Ensure above other elements */
    }
    
    .select2-search--dropdown .select2-search__field {
        border-radius: 6px;
        border: 1px solid #D1D5DB;
        padding: 8px;
    }
    
    .select2-results__option {
        padding: 8px 12px;
        font-size: 0.95rem;
    }
    
    .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
        background-color: var(--primary-color);
        color: white;
    }

    /* PDF Export Specific Styles */
    /* PDF Export Specific Styles */
    .pdf-mode {
        background: white !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
    .pdf-mode .fa-plus, 
    .pdf-mode .opacity-25 {
        display: none !important;
    }
    .pdf-mode table, 
    .pdf-mode th, 
    .pdf-mode td {
        border: 1px solid #000 !important;
    }
    .pdf-mode table {
        table-layout: fixed !important;
        width: 100% !important;
    }
    .pdf-mode th:first-child {
        width: 8% !important;
    }
    .pdf-mode th:not(:first-child) {
        width: 11.5% !important; /* 92% / 8 periods */
    }
    .pdf-mode th {
        font-size: 11px !important;
        padding: 5px !important;
        background-color: #f3f4f6 !important;
    }
    .pdf-mode td {
        height: auto !important; /* Override fixed height */
    }
    .pdf-mode .slot-cell {
        border: none !important;
        min-height: 45px !important; /* Reduced height for compact view */
        padding: 4px !important;
    }
    .pdf-mode .slot-badge {
        font-size: 10px !important;
        padding: 2px 6px !important;
        margin-bottom: 2px !important;
    }
    .pdf-mode .slot-text {
        font-size: 10px !important;
        line-height: 1.1 !important;
        white-space: normal !important;
        word-break: break-all !important;
        overflow-wrap: anywhere !important;
        max-width: 100% !important;
    }
    .pdf-mode .slot-subtext {
        font-size: 9px !important;
        display: block !important;
        white-space: normal !important;
        word-break: break-all !important;
        overflow-wrap: anywhere !important;
        max-width: 100% !important;
    }
    .pdf-mode .slot-badge {
        white-space: normal !important;
        word-break: break-word !important;
        height: auto !important;
        max-width: 100% !important;
    }

    .btn-rounded {
        border-radius: 50px;
    }
    
    /* Action Bar Styling */
    .action-btn {
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 8px 16px;
        border-radius: 8px;
        transition: all 0.2s ease;
        font-weight: 500;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none !important;
    }
    .action-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
        transform: translateY(-1px);
    }
    .action-btn.active {
        background: white;
        color: var(--primary-color);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        font-weight: 600;
    }
    .action-btn i {
        font-size: 1rem;
    }
    
    /* Dropdown customization for action bar */
    .action-dropdown .dropdown-menu {
        border: none;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        margin-top: 8px;
        overflow: hidden;
    }
    .action-dropdown .dropdown-item {
        padding: 10px 16px;
        font-size: 0.9rem;
    }
    .action-dropdown .dropdown-item:active {
        background-color: var(--primary-color);
    }
</style>

<!-- Main Layout -->
<div class="main-content container-fluid">
    
    <!-- Header Section -->
    <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-center mb-5">
        <div class="mb-3 mb-md-0">
            <h1 class="fw-bold mb-1">Timetable Manager</h1>
            <p class="mb-0 opacity-75">View and manage schedules for teachers and classes.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="?view_mode=teacher" class="action-btn <?php echo $viewMode === 'teacher' ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i> Teacher View
            </a>
            <a href="?view_mode=class" class="action-btn <?php echo $viewMode === 'class' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Class View
            </a>

            <div class="vr mx-1 bg-white opacity-25"></div>

            <?php if ($teacherId || $classId): ?>
                <button onclick="exportToPDF()" class="action-btn">
                    <i class="fas fa-file-pdf"></i> Export Current
                </button>
            <?php endif; ?>

            <!-- Bulk Export Dropdown -->
            <div class="dropdown action-dropdown">
                 <button type="button" class="action-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-layer-group"></i> Bulk PDF
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="timetable_pdf.php?mode=teacher" target="_blank"><i class="fas fa-chalkboard-teacher me-2 text-primary"></i> All Teachers</a></li>
                    <li><a class="dropdown-item" href="timetable_pdf.php?mode=class" target="_blank"><i class="fas fa-users me-2 text-success"></i> All Classes</a></li>
                </ul>
            </div>

            <!-- Import Button -->
            <a href="import_timetable.php" class="action-btn">
                <i class="fas fa-file-import"></i> Import Excel
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            
            <!-- Filters & Messages -->
            <div class="filter-card mb-4">
                <form id="viewForm" class="d-flex align-items-center flex-grow-1" style="max-width: 600px;">
                    <input type="hidden" name="view_mode" value="<?php echo $viewMode; ?>">
                    
                    <div class="d-flex align-items-center w-100">
                        <label for="<?php echo $viewMode === 'teacher' ? 'teacherSelect' : 'classSelect'; ?>" class="filter-label mb-0">
                            <?php echo $viewMode === 'teacher' ? 'Select Teacher:' : 'Select Class:'; ?>
                        </label>
                        
                        <div class="flex-grow-1">
                            <?php if ($viewMode === 'teacher'): ?>
                                <select name="teacher_id" id="teacherSelect" class="form-select" style="width: 100%;">
                                    <option value="">-- Search Teacher --</option>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?php echo $t['id']; ?>" <?php echo $teacherId == $t['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($t['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <select name="class_id" id="classSelect" class="form-select" style="width: 100%;">
                                    <option value="">-- Search Class --</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?php echo $c['id']; ?>" <?php echo $classId == $c['id'] ? 'selected' : ''; ?>>
                                            <?php echo $c['standard'] . '-' . $c['division'] . ' (' . $c['section_name'] . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
                
                <div class="ms-md-auto">
                    <?php if ($message): ?>
                        <div class="alert alert-success py-2 px-3 mb-0 rounded-pill shadow-sm border-0 d-inline-flex align-items-center">
                            <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 px-3 mb-0 rounded-pill shadow-sm border-0 d-inline-flex align-items-center">
                            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Timetable Grid -->
            <?php if ($teacherId || $classId): ?>
                <div class="table-card" id="timetableCard">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 100px;" class="text-center bg-light">Day</th>
                                    <?php for ($p=1; $p<=$totalPeriods; $p++): ?>
                                        <th class="text-center">Period <?php echo $p; ?></th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($days as $dayNum => $dayName): ?>
                                    <tr>
                                        <th class="text-center align-middle bg-light text-secondary"><?php echo substr($dayName, 0, 3); ?></th>
                                        <?php 
                                            $daySlots = $schedule[$dayNum] ?? []; 
                                            $slotsByPeriod = [];
                                            foreach($daySlots as $s) {
                                                $period = $s['period_no'];
                                                if (!isset($slotsByPeriod[$period])) {
                                                    $slotsByPeriod[$period] = [];
                                                }
                                                $slotsByPeriod[$period][] = $s;
                                            }
                                        ?>
                                        
                                        <?php for ($p=1; $p<=$totalPeriods; $p++): ?>
                                            <?php $slots = $slotsByPeriod[$p] ?? []; ?>
                                            
                                            <?php
                                            // Determine if this cell is BLOCKED
                                            $isCellBlocked = false;
                                            $blockReason = '';
                                            
                                            // Check based on View Mode
                                            $blockedMap = $blockedPeriodModel->getBlockedPeriods(); // Full map
                                            $dayFull = $dayNames[$dayNum];
                                            $bToday = $blockedMap[$dayFull] ?? [];
                                            
                                            if (isset($bToday[$p]['global'])) {
                                                $isCellBlocked = true;
                                                $blockReason = "School Closed";
                                            } else {
                                                if ($viewMode === 'class' && $classId) {
                                                     if (isset($bToday[$p][$classId])) {
                                                         $isCellBlocked = true;
                                                         $blockReason = "Class Closed";
                                                     }
                                                } elseif ($viewMode === 'teacher' && $teacherId) {
                                                     // Requires fetching teacher classes. Can be slow in loop? 
                                                     // For View Mode, maybe just check if existing slot coincides? No, we want empty slots to show blocked.
                                                     // We'll trust the general logic: If "Free" (empty) AND calculated as "Off", show blocked.
                                                     // Re-using logic:
                                                     $tClasses = $timetableModel->getTeacherClasses($teacherId);
                                                     if (!empty($tClasses)) {
                                                         $allBlocked = true;
                                                         foreach ($tClasses as $cid) {
                                                             if (!isset($bToday[$p][$cid])) {
                                                                 $allBlocked = false; 
                                                                 break;
                                                             }
                                                         }
                                                         if ($allBlocked) {
                                                             $isCellBlocked = true;
                                                             $blockReason = "Off Duty";
                                                         }
                                                     }
                                                }
                                            }
                                            ?>
                                            
                                            <td>
                                                <div class="slot-cell <?php echo empty($slots) ? 'empty' : ''; ?> <?php echo $isCellBlocked ? 'blocked' : ''; ?>"
                                                     data-day="<?php echo $dayNum; ?>"
                                                     data-period="<?php echo $p; ?>"
                                                     data-slots='<?php echo !empty($slots) ? htmlspecialchars(json_encode($slots), ENT_QUOTES, 'UTF-8') : ''; ?>'
                                                     onclick="<?php echo $isCellBlocked ? '' : 'openSlotModal(this)'; ?>"
                                                     title="<?php echo $isCellBlocked ? $blockReason : ''; ?>">
                                                    
                                                    <?php if ($isCellBlocked): ?>
                                                        <div class="text-danger small fw-bold mt-2">
                                                            <i class="fas fa-ban me-1"></i> <?php echo $blockReason; ?>
                                                        </div>
                                                    <?php elseif (!empty($slots)): ?>
                                                        <?php 
                                                        $firstSlot = $slots[0];
                                                        $isGroupClass = count($slots) > 1 || !empty($firstSlot['group_name']);
                                                        ?>
                                                        <div class="slot-content">
                                                            <?php if ($viewMode === 'teacher'): ?>
                                                                <!-- Teacher View: Class Name (Badge) -> Subject (Text) -->
                                                                <div class="slot-badge"><?php echo $firstSlot['standard'] . '-' . $firstSlot['division']; ?></div>
                                                                
                                                                <?php if ($isGroupClass): ?>
                                                                    <div class="d-flex flex-column gap-1 w-100 mt-1">
                                                                        <?php foreach ($slots as $slot): ?>
                                                                            <div class="slot-text border-top pt-1 small">
                                                                                <span class="text-xs text-muted"><?php echo htmlspecialchars($slot['group_name'] ?? 'Grp'); ?>:</span>
                                                                                <?php echo htmlspecialchars($slot['subject_name']); ?>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="slot-text"><?php echo htmlspecialchars($firstSlot['subject_name']); ?></div>
                                                                <?php endif; ?>
                                                                
                                                            <?php else: ?>
                                                                <!-- Class View: Subject Name (Badge) -> Teacher (Text) -->
                                                                <?php if ($isGroupClass): ?>
                                                                    <div class="d-flex flex-column gap-1 w-100">
                                                                        <?php foreach ($slots as $slot): ?>
                                                                            <div class="border-bottom pb-1 mb-1">
                                                                                <div class="slot-badge mb-1"><?php echo htmlspecialchars($slot['subject_name']); ?></div>
                                                                                <div class="slot-subtext"><?php echo htmlspecialchars($slot['teacher_name']); ?></div>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="slot-badge"><?php echo htmlspecialchars($firstSlot['subject_name']); ?></div>
                                                                    <div class="slot-text"><?php echo htmlspecialchars($firstSlot['teacher_name']); ?></div>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-muted opacity-25">
                                                            <i class="fas fa-plus fa-sm"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        <?php endfor; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <div class="mb-3 text-muted opacity-50">
                        <i class="fas fa-calendar-alt fa-4x"></i>
                    </div>
                    <h5 class="text-muted">Select a <?php echo $viewMode === 'teacher' ? 'Teacher' : 'Class'; ?> to view timetable</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit/Add Modal -->
<div class="modal fade" id="slotModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="slotModalTitle">Manage Slot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <!-- Alert container for JS to show block warnings if needed -->
            <div id="modalAlert" class="px-3 pt-2"></div>
            
            <div class="modal-body pt-4">
                <form method="POST" id="slotForm">
                    <input type="hidden" name="teacher_id_hidden" value="<?php echo $teacherId; ?>">
                    <input type="hidden" name="teacher_id_entry" value="<?php echo $teacherId; ?>">
                    <input type="hidden" name="entry_id" id="entryId">
                    <input type="hidden" name="day_of_week" id="dayOfWeek">
                    <input type="hidden" name="period_no" id="periodNo">

                    <div class="mb-4 text-center">
                        <input type="text" class="form-control text-center fw-bold border-0 bg-light rounded-pill" id="slotInfo" readonly disabled>
                    </div>

                    <!-- Teacher Select (Visible only in Class View) -->
                    <div class="mb-3" id="teacherSelectDiv" style="display: none;">
                        <label class="form-label">Teacher</label>
                        <select name="teacher_id_select" id="teacherIdSelect" class="form-select">
                            <option value="">-- Select Teacher --</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Class</label>
                        <select name="class_id" id="classId" class="form-select" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo $c['standard'] . '-' . $c['division'] . ' (' . $c['section_name'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <select name="subject_id" id="subjectId" class="form-select" required>
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Group Name <small class="text-muted fw-normal">(Optional)</small></label>
                        <input type="text" name="group_name" id="groupName" class="form-control rounded-pill" 
                               placeholder="e.g., Hindi, Sanskrit">
                    </div>

                    <div class="d-flex justify-content-between pt-3">
                        <button type="submit" name="delete_entry" id="deleteBtn" class="btn btn-danger btn-rounded px-4" onclick="return confirm('Delete this entry?');">
                            <i class="fas fa-trash-alt me-2"></i> Delete
                        </button>
                        <button type="submit" name="update_entry" id="saveBtn" class="btn btn-primary btn-rounded px-5">
                            <i class="fas fa-check me-2"></i> Save
                        </button>
                    </div>
                    
                    <!-- Advanced Group Section Toggle -->
                    <div class="text-center mt-4 mb-2">
                        <button type="button" class="btn btn-sm btn-link text-decoration-none text-muted" data-bs-toggle="collapse" data-bs-target="#groupSection">
                           <i class="fas fa-layer-group me-1"></i> Advanced: Multiple Groups
                        </button>
                    </div>
                    
                    <div class="collapse" id="groupSection">
                        <div class="card card-body bg-light border-0 shadow-none rounded-3 mt-2">
                             <div id="groupEntries">
                                <!-- Groups injected via JS -->
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary w-100 mt-2 rounded-pill" id="addGroupEntry">
                                <i class="fas fa-plus me-1"></i> Add Another Group
                            </button>
                            <button type="submit" name="add_group_entry" id="addGroupBtn" class="btn btn-success w-100 mt-3 rounded-pill">
                                Save All Groups
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add jQuery and Select2 for Searchable Dropdown -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Add html2pdf library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
$(document).ready(function() {
    $('#teacherSelect, #classSelect').select2({
        width: '100%',
        placeholder: "-- Select --",
        allowClear: true
    });

    // Auto-submit on change
    $('#teacherSelect, #classSelect').on('change', function() {
        if($(this).val()) {
            $('#viewForm').submit();
        }
    });

    // Initialize Modal Select2s inside modal shown event for proper rendering
    $('#slotModal').on('shown.bs.modal', function () {
        $('#classId').select2({ dropdownParent: $('#slotModal'), width: '100%' });
        $('#subjectId').select2({ dropdownParent: $('#slotModal'), width: '100%' });
        $('#teacherIdSelect').select2({ dropdownParent: $('#slotModal'), width: '100%' });
        $('select[name="group_subjects[]"]').select2({ dropdownParent: $('#slotModal'), width: '100%' });
    });
});

const dayNames = {1:'Monday', 2:'Tuesday', 3:'Wednesday', 4:'Thursday', 5:'Friday', 6:'Saturday'};
const subjectOptions = <?php 
    try {
        if (isset($subjects) && is_array($subjects)) {
            $subjectArray = array_map(function($s) { 
                return ['id' => (int)$s['id'], 'name' => htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8')]; 
            }, $subjects);
            echo json_encode($subjectArray, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        } else {
            echo '[]';
        }
    } catch (Exception $e) {
        error_log("Error encoding subjects: " . $e->getMessage());
        echo '[]';
    }
?>;

const blockedMap = <?php 
    // Structure: [DayName][Period][ClassID] (or 'global') => true
    // Need to ensure keys are standard.
    // PHP array: ['Saturday' => [ 4 => [ 'global'=>true, 38=>true ] ] ]
    echo json_encode($blockedPeriodModel->getBlockedPeriods(), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>;


function exportToPDF() {
    const element = document.getElementById('timetableCard');
    const viewMode = '<?php echo $viewMode; ?>';
    let filename = 'timetable.pdf';

    // Try to get a better filename based on selection
    if (viewMode === 'teacher') {
        const teacherName = $('#teacherSelect option:selected').text().trim();
        if (teacherName && teacherName !== '-- Search Teacher --') {
            filename = teacherName + '_Timetable.pdf';
        }
    } else {
        const className = $('#classSelect option:selected').text().trim();
        if (className && className !== '-- Search Class --') {
            filename = 'Class_' + className + '_Timetable.pdf';
        }
    }

    const opt = {
        margin: 0.3, 
        filename: filename,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };

    // Add PDF styling class
    element.classList.add('pdf-mode');

    // Generate and remove class after
    html2pdf().set(opt).from(element).save().then(function() {
        element.classList.remove('pdf-mode');
    });
}

function openSlotModal(element) {
    const day = element.getAttribute('data-day');
    const period = element.getAttribute('data-period');
    const slotsJson = element.getAttribute('data-slots');
    const slotsData = slotsJson ? JSON.parse(slotsJson) : [];

    // Check for Blocked Status (Passed via data-blocked attribute)
    // We need to inject this attribute in PHP loop. For now, we rely on the visual class 'blocked'
    const isBlocked = element.classList.contains('blocked');
    
    if (isBlocked) {
        // User requested "shows blocked in red" -> which we did. 
        // If they click, we can show a warning modal or just disable inputs.
        // Let's allow opening to VIEW (if slots exist) but disable EDITING.
        // Or simply prevent opening if empty?
        // "allowing me to add class in blocked periods" -> Prevent adding.
        
        // If empty and blocked, do nothing (effectively disabled)
        // But the previous onclick logic was: onclick="<?php echo $isCellBlocked ? '' : 'openSlotModal(this)'; ?>"
        // So they CANNOT click if I did that correctly.
        // Double check previous edit to line 538.
    }

    // Reset Form
    document.getElementById('slotForm').reset();
    $('#classId').val(null).trigger('change');
    $('#subjectId').val(null).trigger('change');
    $('#groupName').val('');
    $('#modalAlert').html(''); // Clear alerts
    
    // Enable buttons by default
    $('#saveBtn').prop('disabled', false);
    
    // If we want to strictly prevent adding for a SPECIFIC class that wasn't caught by the main view block:
    // We need JS to check the selected class against valid periods. 
    // This is complex client-side. Server-side validation (already added) covers this.


    document.getElementById('dayOfWeek').value = day;
    document.getElementById('periodNo').value = period;
    document.getElementById('slotInfo').value = dayNames[day] + ' - Period ' + period;

    // Toggle Teacher Select Visibility & Pre-select Class
    const viewMode = '<?php echo $viewMode; ?>';
    const activeClassId = '<?php echo $classId; ?>';
    const teacherDiv = document.getElementById('teacherSelectDiv');
    
    // Clear previous validations
    $('#modalAlert').html('');
    $('#saveBtn').prop('disabled', false);

    if (viewMode === 'class') {
        teacherDiv.style.display = 'block';
        $('#teacherIdSelect').prop('required', true);
        
        // Auto-select current class if adding new
        if (slotsData.length === 0 && activeClassId) {
             $('#classId').val(activeClassId).trigger('change');
        }
    } else {
        teacherDiv.style.display = 'none';
        $('#teacherIdSelect').prop('required', false);
    }
    
    // Trigger validation on load (in case pre-selection is blocked)
    validateClassSelection(day, period);

    if (slotsData.length > 0) {
        // Edit Mode
        const firstSlot = slotsData[0];
        document.getElementById('slotModalTitle').innerText = slotsData.length > 1 
            ? `Edit Entry (${slotsData.length} groups)` 
            : 'Edit Entry';
        document.getElementById('entryId').value = firstSlot.id;
        $('#classId').val(firstSlot.class_id).trigger('change');
        $('#subjectId').val(firstSlot.subject_id).trigger('change');
        $('#groupName').val(firstSlot.group_name || '');
        
        if (viewMode === 'class') {
             $('#teacherIdSelect').val(firstSlot.teacher_id).trigger('change');
        }
        
        document.getElementById('saveBtn').name = 'update_entry';
        document.getElementById('saveBtn').innerHTML = '<i class="fas fa-check me-2"></i> Update';
        document.getElementById('deleteBtn').style.display = 'block';
        
        // Show groups if multiple
        if (slotsData.length > 1) {
             var bsCollapse = new bootstrap.Collapse(document.getElementById('groupSection'), {
                show: true
            });
            slotsData.forEach((slot, index) => {
                if (index === 0) return;
                addGroupEntryRow(slot.subject_id, slot.group_name || '');
            });
        } else {
             new bootstrap.Collapse(document.getElementById('groupSection'), { toggle: false }).hide();
        }
    } else {
        // Add Mode
        document.getElementById('slotModalTitle').innerText = 'Add Entry';
        document.getElementById('entryId').value = '';
        
        document.getElementById('saveBtn').name = 'add_entry';
        document.getElementById('saveBtn').innerHTML = '<i class="fas fa-plus me-2"></i> Add';
        document.getElementById('deleteBtn').style.display = 'none';
        
        new bootstrap.Collapse(document.getElementById('groupSection'), { toggle: false }).hide();
    }

    var myModal = new bootstrap.Modal(document.getElementById('slotModal'));
    myModal.show();
}

function resetGroupEntries() {
    const container = document.getElementById('groupEntries');
    container.innerHTML = createGroupEntryHTML();
    updateRemoveButtons();
    // Reinitialize Select2
    $('#slotModal').find('select[name="group_subjects[]"]').select2({ dropdownParent: $('#slotModal'), width: '100%' });
}

function createGroupEntryHTML(subjectId = '', groupName = '') {
    let optionsHTML = '<option value="">-- Select --</option>';
    subjectOptions.forEach(function(subject) {
        const selected = subjectId == subject.id ? 'selected' : '';
        optionsHTML += `<option value="${subject.id}" ${selected}>${escapeHtml(subject.name)}</option>`;
    });
    
    return `
        <div class="group-entry mb-2 border border-white p-2 rounded shadow-sm bg-white">
            <div class="row g-2">
                <div class="col-6">
                    <label class="small text-muted mb-1">Subject</label>
                    <select name="group_subjects[]" class="form-select form-select-sm border-0 bg-light">
                        ${optionsHTML}
                    </select>
                </div>
                <div class="col-5">
                    <label class="small text-muted mb-1">Group Name</label>
                    <input type="text" name="group_names[]" class="form-control form-control-sm border-0 bg-light" 
                           placeholder="e.g., Hindi" value="${escapeHtml(groupName)}">
                </div>
                <div class="col-1 d-flex align-items-end pb-1">
                    <button type="button" class="btn btn-sm btn-light text-danger remove-group-entry border-0" style="display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
}

function addGroupEntryRow(subjectId = '', groupName = '') {
    const container = document.getElementById('groupEntries');
    const newRow = document.createElement('div');
    newRow.innerHTML = createGroupEntryHTML(subjectId, groupName);
    container.appendChild(newRow);
    updateRemoveButtons();
    $(newRow).find('select').select2({ dropdownParent: $('#slotModal'), width: '100%' });
}

function escapeHtml(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
}

function updateRemoveButtons() {
    const entries = document.querySelectorAll('.group-entry');
    entries.forEach((entry) => {
        const removeBtn = entry.querySelector('.remove-group-entry');
        if (entries.length > 1) {
            removeBtn.style.display = 'block';
        } else {
            removeBtn.style.display = 'none';
        }
    });
}

$(document).ready(function() {
    $('#addGroupEntry').on('click', function() {
        addGroupEntryRow();
    });
    
    $(document).on('click', '.remove-group-entry', function() {
        $(this).closest('.group-entry').remove();
        updateRemoveButtons();
    });
    
    // Real-time Class Block Validation
    $('#classId').on('change', function() {
        const day = document.getElementById('dayOfWeek').value;
        const period = document.getElementById('periodNo').value;
        validateClassSelection(day, period);
    });
});

function validateClassSelection(day, period) {
    const classId = $('#classId').val();
    if (!classId || !day || !period) return;
    
    const dayName = dayNames[day]; // 1->Monday
    const blocksToday = blockedMap[dayName] || {};
    const blocksPeriod = blocksToday[period] || {};
    
    let isBlocked = false;
    let reason = '';
    
    if (blocksPeriod['global']) {
        isBlocked = true;
        reason = 'Global School Block';
    } else if (blocksPeriod[classId]) {
        isBlocked = true;
        reason = 'Class Block';
    }
    
    if (isBlocked) {
        $('#modalAlert').html(`<div class="alert alert-danger mb-0"><i class="fas fa-ban me-2"></i> This period is BLOCKED (${reason}).</div>`);
        $('#saveBtn').prop('disabled', true);
    } else {
        $('#modalAlert').html('');
        $('#saveBtn').prop('disabled', false);
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

