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

$totalPeriods = $settingsModel->get('total_periods', 8);

// Handle Add/Edit/Delete Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['delete_entry'])) {
            $timetableModel->delete($_POST['entry_id']);
            $message = "Entry deleted.";
        } elseif (isset($_POST['add_entry']) || isset($_POST['update_entry'])) {
            $tid = $_POST['teacher_id_entry']; 
            $cid = $_POST['class_id'];
            $sid = $_POST['subject_id'];
            $dow = $_POST['day_of_week'];
            $period = $_POST['period_no'];
            $groupName = !empty($_POST['group_name']) ? trim($_POST['group_name']) : null;
            $entryId = $_POST['entry_id'] ?? null;

            if ($entryId) {
                // Update existing
                $timetableModel->update($entryId, $cid, $sid, $groupName);
                $message = "Timetable entry updated.";
            } else {
                // Add new
                $timetableModel->add($tid, $cid, $sid, $dow, $period, $groupName);
                $message = "Timetable entry added.";
            }
        } elseif (isset($_POST['add_group_entry'])) {
            // Add multiple group entries at once
            $tid = $_POST['teacher_id_entry'];
            $cid = $_POST['class_id'];
            $dow = $_POST['day_of_week'];
            $period = $_POST['period_no'];
            $subjects = $_POST['group_subjects'] ?? [];
            $groupNames = $_POST['group_names'] ?? [];
            
            $added = 0;
            foreach ($subjects as $index => $sid) {
                if (!empty($sid)) {
                    $groupName = !empty($groupNames[$index]) ? trim($groupNames[$index]) : null;
                    $timetableModel->add($tid, $cid, $sid, $dow, $period, $groupName);
                    $added++;
                }
            }
            $message = "$added group entry/entries added.";
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

// Fetch Schedule for Display
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

$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Timetable Viewer</h2>
                <div class="btn-group" role="group">
                    <a href="?view_mode=teacher" class="btn btn-outline-primary <?php echo $viewMode === 'teacher' ? 'active' : ''; ?>">Teacher-wise</a>
                    <a href="?view_mode=class" class="btn btn-outline-primary <?php echo $viewMode === 'class' ? 'active' : ''; ?>">Class-wise</a>
                </div>
            </div>

            <form class="mb-4" id="viewForm">
                <input type="hidden" name="view_mode" value="<?php echo $viewMode; ?>">
                
                <?php if ($viewMode === 'teacher'): ?>
                    <label>Select Teacher:</label>
                    <select name="teacher_id" id="teacherSelect" class="form-select d-inline-block w-auto">
                        <option value="">-- Select --</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo $teacherId == $t['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <label>Select Class:</label>
                    <select name="class_id" id="classSelect" class="form-select d-inline-block w-auto">
                        <option value="">-- Select --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $classId == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo $c['standard'] . '-' . $c['division'] . ' (' . $c['section_name'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </form>

            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

            <?php if ($teacherId || $classId): ?>
                <div class="table-responsive">
                    <table class="table table-bordered text-center table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Day</th>
                                <?php for ($p=1; $p<=$totalPeriods; $p++): ?>
                                    <th>P<?php echo $p; ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($days as $dayNum => $dayName): ?>
                                <tr>
                                    <th><?php echo $dayName; ?></th>
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
                                        <td class="align-middle position-relative p-0">
                                            <div class="p-2 w-100 h-100" 
                                                 style="cursor: pointer; min-height: 60px;"
                                                 data-day="<?php echo $dayNum; ?>"
                                                 data-period="<?php echo $p; ?>"
                                                 data-slots='<?php echo !empty($slots) ? htmlspecialchars(json_encode($slots), ENT_QUOTES, 'UTF-8') : ''; ?>'
                                                 onclick="openSlotModal(this)">
                                                
                                                <?php if (!empty($slots)): ?>
                                                    <?php 
                                                    $firstSlot = $slots[0];
                                                    $isGroupClass = count($slots) > 1 || !empty($firstSlot['group_name']);
                                                    ?>
                                                    
                                                    <?php if ($viewMode === 'teacher'): ?>
                                                        <div class="fw-bold text-primary"><?php echo $firstSlot['standard'] . '-' . $firstSlot['division']; ?></div>
                                                        <?php if ($isGroupClass): ?>
                                                            <?php foreach ($slots as $slot): ?>
                                                                <div class="text-muted small border-bottom mb-1 pb-1">
                                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($slot['group_name'] ?? 'Default'); ?></span>
                                                                    <?php echo htmlspecialchars($slot['subject_name']); ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <div class="text-muted small"><?php echo htmlspecialchars($firstSlot['subject_name']); ?></div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php if ($isGroupClass): ?>
                                                            <?php foreach ($slots as $slot): ?>
                                                                <div class="text-muted small border-bottom mb-1 pb-1">
                                                                    <div class="fw-bold text-primary"><?php echo htmlspecialchars($slot['subject_name']); ?></div>
                                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($slot['group_name'] ?? 'Default'); ?></span>
                                                                    <?php echo htmlspecialchars($slot['teacher_name']); ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php else: ?>
                                                            <div class="fw-bold text-primary"><?php echo htmlspecialchars($firstSlot['subject_name']); ?></div>
                                                            <div class="text-muted small"><?php echo htmlspecialchars($firstSlot['teacher_name']); ?></div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="text-muted" style="opacity: 0.2;"><i class="fas fa-plus"></i></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Please select a <?php echo $viewMode; ?> to view the timetable.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit/Add Modal -->
<div class="modal fade" id="slotModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="slotModalTitle">Manage Slot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="slotForm">
                    <input type="hidden" name="teacher_id_hidden" value="<?php echo $teacherId; ?>">
                    <input type="hidden" name="teacher_id_entry" value="<?php echo $teacherId; ?>">
                    <input type="hidden" name="entry_id" id="entryId">
                    <input type="hidden" name="day_of_week" id="dayOfWeek">
                    <input type="hidden" name="period_no" id="periodNo">

                    <div class="mb-3">
                        <label>Day / Period</label>
                        <input type="text" class="form-control" id="slotInfo" readonly disabled>
                    </div>

                    <div class="mb-3">
                        <label>Class</label>
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
                        <label>Subject</label>
                        <select name="subject_id" id="subjectId" class="form-select" required>
                            <option value="">-- Select Subject --</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Group Name <small class="text-muted">(Optional - for group classes like Hindi/Sanskrit)</small></label>
                        <input type="text" name="group_name" id="groupName" class="form-control" 
                               placeholder="e.g., Hindi, Sanskrit, Group 1, Group 2">
                        <small class="form-text text-muted">Leave empty for regular classes. Use same group name for multiple subjects in same period.</small>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="submit" name="delete_entry" id="deleteBtn" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this entry?');">Delete</button>
                        <button type="submit" name="update_entry" id="saveBtn" class="btn btn-primary">Save Changes</button>
                    </div>
                    
                    <hr class="my-3">
                    
                    <div class="mb-3">
                        <label class="fw-bold">Add Multiple Group Classes</label>
                        <div id="groupEntries">
                            <div class="group-entry mb-2 border p-2 rounded">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="small">Subject</label>
                                        <select name="group_subjects[]" class="form-select form-select-sm">
                                            <option value="">-- Select --</option>
                                            <?php foreach ($subjects as $s): ?>
                                                <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="small">Group Name</label>
                                        <input type="text" name="group_names[]" class="form-control form-control-sm" 
                                               placeholder="e.g., Hindi">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="small">&nbsp;</label>
                                        <button type="button" class="btn btn-sm btn-danger remove-group-entry" style="display: none;">×</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" id="addGroupEntry">+ Add Another Group</button>
                        <button type="submit" name="add_group_entry" id="addGroupBtn" class="btn btn-success">Add All Groups</button>
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

<script>
$(document).ready(function() {
    $('#teacherSelect, #classSelect').select2({
        width: '300px',
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

// subjectOptions is declared above - do not redeclare

function openSlotModal(element) {
    const day = element.getAttribute('data-day');
    const period = element.getAttribute('data-period');
    const slotsJson = element.getAttribute('data-slots');
    const slotsData = slotsJson ? JSON.parse(slotsJson) : [];

    // Reset Form
    document.getElementById('slotForm').reset();
    $('#classId').val(null).trigger('change');
    $('#subjectId').val(null).trigger('change');
    $('#groupName').val('');
    resetGroupEntries();

    // Set Hidden Values
    document.getElementById('dayOfWeek').value = day;
    document.getElementById('periodNo').value = period;
    document.getElementById('slotInfo').value = dayNames[day] + ' - Period ' + period;

    if (slotsData.length > 0) {
        // Edit Mode - show first slot for editing, or allow managing all
        const firstSlot = slotsData[0];
        document.getElementById('slotModalTitle').innerText = slotsData.length > 1 
            ? `Edit Schedule Entry (${slotsData.length} groups)` 
            : 'Edit Schedule Entry';
        document.getElementById('entryId').value = firstSlot.id;
        $('#classId').val(firstSlot.class_id).trigger('change');
        $('#subjectId').val(firstSlot.subject_id).trigger('change');
        $('#groupName').val(firstSlot.group_name || '');
        
        document.getElementById('saveBtn').name = 'update_entry';
        document.getElementById('saveBtn').innerText = 'Update';
        document.getElementById('deleteBtn').style.display = 'block';
        
        // If multiple groups, show them in the group entries section
        if (slotsData.length > 1) {
            slotsData.forEach((slot, index) => {
                if (index === 0) {
                    // First one is already in the main form
                    return;
                }
                addGroupEntryRow(slot.subject_id, slot.group_name || '');
            });
        }
    } else {
        // Add Mode
        document.getElementById('slotModalTitle').innerText = 'Add Schedule Entry';
        document.getElementById('entryId').value = '';
        
        document.getElementById('saveBtn').name = 'add_entry';
        document.getElementById('saveBtn').innerText = 'Add';
        document.getElementById('deleteBtn').style.display = 'none';
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
        <div class="group-entry mb-2 border p-2 rounded">
            <div class="row">
                <div class="col-md-6">
                    <label class="small">Subject</label>
                    <select name="group_subjects[]" class="form-select form-select-sm">
                        ${optionsHTML}
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="small">Group Name</label>
                    <input type="text" name="group_names[]" class="form-control form-control-sm" 
                           placeholder="e.g., Hindi" value="${escapeHtml(groupName)}">
                </div>
                <div class="col-md-1">
                    <label class="small">&nbsp;</label>
                    <button type="button" class="btn btn-sm btn-danger remove-group-entry" style="display: none;">×</button>
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
    
    // Initialize Select2 for new select
    $(newRow).find('select').select2({ dropdownParent: $('#slotModal'), width: '100%' });
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
}

function updateRemoveButtons() {
    const entries = document.querySelectorAll('.group-entry');
    entries.forEach((entry, index) => {
        const removeBtn = entry.querySelector('.remove-group-entry');
        if (entries.length > 1) {
            removeBtn.style.display = 'block';
        } else {
            removeBtn.style.display = 'none';
        }
    });
}

// Add event listeners
$(document).ready(function() {
    $('#addGroupEntry').on('click', function() {
        addGroupEntryRow();
    });
    
    $(document).on('click', '.remove-group-entry', function() {
        $(this).closest('.group-entry').remove();
        updateRemoveButtons();
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
