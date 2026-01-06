<?php
require_once 'config/app.php';
require_once 'includes/header.php';
require_once 'services/ProxyAllocationService.php';

$allocationService = new ProxyAllocationService();
$date = $_GET['date'] ?? date('Y-m-d');
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_allocations'])) {
    try {
        $proxyModel = new ProxyAssignment();
        $allocations = $_POST['allocations'] ?? [];
        $savedCount = 0;

        foreach ($allocations as $slotKey => $proxyId) {
            if (empty($proxyId)) continue;

            // slotKey format: absent_teacher_id|period_no|class_id
            list($absentId, $periodNo, $classId) = explode('|', $slotKey);
            
            $proxyModel->assign($date, $absentId, $proxyId, $classId, $periodNo, 'MANUAL', 'Interactive Batch Allocation');
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
    } catch (Exception $e) {
        $error = "Error deleting allocations: " . $e->getMessage();
    }
}

$absentSlots = $allocationService->getAbsentSlots($date);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Interactive Proxy Allocation</h2>
        <form class="d-flex align-items-center">
            <label class="me-2 mb-0">Date:</label>
            <input type="date" name="date" class="form-control w-auto me-2" value="<?php echo $date; ?>" onchange="this.form.submit()">
        </form>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

    <form method="POST" id="allocationForm">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="35%">Absent Slot Details</th>
                            <th width="35%">Available Proxy Teachers</th>
                            <th width="30%">Selected Replacement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($absentSlots)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-5 text-muted">
                                    No absent teachers found for this date.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($absentSlots as $slot): ?>
                                <?php 
                                $existingProxyId = $slot['assigned_proxy_id'];
                                $candidates = $allocationService->getAvailableCandidates($date, $slot['period_no'], $existingProxyId);
                                $slotKey = $slot['teacher_id'] . '|' . $slot['period_no'] . '|' . $slot['class_id'];
                                ?>
                                <tr class="align-middle">
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($slot['teacher_name']); ?></div>
                                        <div class="small">
                                            <span class="badge bg-info text-dark">Period <?php echo $slot['period_no']; ?></span>
                                            <span class="badge bg-secondary"><?php echo $slot['standard'] . '-' . $slot['division']; ?></span>
                                            <span class="text-primary fw-bold ms-1"><?php echo htmlspecialchars($slot['subject_name']); ?></span>
                                            <?php if ($slot['group_name']): ?>
                                                <span class="badge bg-light text-dark border ms-1">Group: <?php echo htmlspecialchars($slot['group_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <select name="allocations[<?php echo $slotKey; ?>]" class="form-select candidate-select" data-slot="<?php echo $slotKey; ?>">
                                            <option value="">-- Select Proxy --</option>
                                            <?php foreach ($candidates as $candidate): ?>
                                                <option value="<?php echo $candidate['id']; ?>" 
                                                        data-initial-free="<?php echo $candidate['free_periods']; ?>"
                                                        <?php echo ($existingProxyId == $candidate['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($candidate['name']); ?> (<?php echo $candidate['free_periods']; ?> Free)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (empty($candidates)): ?>
                                            <div class="text-danger small mt-1">
                                                <i class="fas fa-exclamation-triangle"></i> No teachers free in this period.
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div id="display-<?php echo str_replace('|', '-', $slotKey); ?>" class="selected-proxy-name <?php echo $existingProxyId ? 'text-success' : 'text-muted'; ?> fw-bold">
                                            <?php 
                                            if ($existingProxyId) {
                                                foreach ($candidates as $c) {
                                                    if ($c['id'] == $existingProxyId) {
                                                        echo htmlspecialchars($c['name']);
                                                        break;
                                                    }
                                                }
                                            } else {
                                                echo "-";
                                            }
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white text-end py-3">
                <?php if (!empty($absentSlots)): ?>
                    <button type="button" class="btn btn-outline-primary px-4 me-2" id="previewBtn">
                        <i class="fas fa-eye me-1"></i> Preview Assignments
                    </button>
                    <button type="submit" name="save_allocations" class="btn btn-success px-4">
                        <i class="fas fa-save me-1"></i> Save all proxy
                    </button>
                    <button type="submit" name="delete_all" class="btn btn-danger ms-2" onclick="return confirm('Are you sure you want to delete ALL proxy assignments for this date? This cannot be undone.')">
                        <i class="fas fa-trash me-1"></i> Clear All Today
                    </button>
                <?php endif; ?>
                <a href="reports.php" class="btn btn-outline-primary ms-2 <?php echo empty($message) ? 'disabled' : ''; ?>">
                    <i class="fas fa-print me-1"></i> View/Print Report
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assignment Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="previewTableContainer">
                    <!-- Dynamic content -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close Preview</button>
                <button type="button" class="btn btn-success" onclick="$('#allocationForm').submit()">Save all proxy</button>
            </div>
        </div>
    </div>
</div>

<!-- Add jQuery and Select2 for better experience -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('.candidate-select').select2({
        width: '100%',
        placeholder: "Search for a teacher...",
        allowClear: true
    }).on('change', function() {
        const selectedText = $(this).find('option:selected').text();
        const slot = $(this).data('slot').replace(/\|/g, '-');
        const val = $(this).val();
        
        if (val) {
            $(`#display-${slot}`).text(selectedText).addClass('text-success').removeClass('text-muted');
        } else {
            $(`#display-${slot}`).text('-').addClass('text-muted').removeClass('text-success');
        }
        updateFreePeriodCounts();
    });

    // Check for collisions (same teacher assigned to different slots in same period)
    $('.candidate-select').on('change', function() {
        const periodTeachers = {}; // period -> { teacher_id -> [elements] }
        let hasCollision = false;
        
        // Reset all styles first
        $('.candidate-select').next('.select2-container').find('.select2-selection').css('border-color', '#ced4da');
        $('.collision-warning').remove();
        $('.selected-proxy-name').removeClass('text-danger').addClass('text-success');

        $('.candidate-select').each(function() {
            const val = $(this).val();
            if (!val) return;
            
            const slotKey = $(this).data('slot');
            const period = slotKey.split('|')[1];
            
            if (!periodTeachers[period]) periodTeachers[period] = {};
            if (!periodTeachers[period][val]) periodTeachers[period][val] = [];
            
            periodTeachers[period][val].push($(this));
        });

        // Detect and highlight collisions
        for (const period in periodTeachers) {
            for (const teacherId in periodTeachers[period]) {
                const elements = periodTeachers[period][teacherId];
                if (elements.length > 1) {
                    hasCollision = true;
                    elements.forEach($el => {
                        $el.next('.select2-container').find('.select2-selection').css('border-color', '#dc3545');
                        const slotKey = $el.data('slot').replace(/\|/g, '-');
                        $(`#display-${slotKey}`).removeClass('text-success').addClass('text-danger')
                            .append(' <span class="collision-warning small fst-italic">(Double Assignment!)</span>');
                    });
                }
            }
        }

        if (hasCollision) {
            if (!$('#global-warning').length) {
                $('.card-footer').prepend('<div id="global-warning" class="alert alert-warning py-2 small mb-2"><i class="fas fa-exclamation-triangle"></i> Warning: One or more teachers are assigned to multiple slots in the same period.</div>');
            }
        } else {
            $('#global-warning').remove();
        }
    });

    // Dynamic Free Periods Calculation
    function updateFreePeriodCounts() {
        const selectionCounts = {};
        
        // Count selections across all dropdowns
        $('.candidate-select').each(function() {
            const val = $(this).val();
            if (val) {
                selectionCounts[val] = (selectionCounts[val] || 0) + 1;
            }
        });

        // Update every option in every dropdown
        $('.candidate-select').each(function() {
            const $select = $(this);
            const currentSelectedVal = $select.val();
            
            $select.find('option').each(function() {
                const $option = $(this);
                const teacherId = $option.val();
                const initialFree = $option.attr('data-initial-free');
                
                if (teacherId && initialFree !== undefined) {
                    const count = selectionCounts[teacherId] || 0;
                    const remaining = parseInt(initialFree) - count;
                    
                    // Logic: the 'remaining' count for a teacher is (total - all selections).
                    // BUT for the dropdown where the teacher is ALREADY selected, 
                    // the current display should reflect that they HAVE used 1 of their total.
                    // Wait, if I select Talisha (8), the row she is in should say "Talisha (7 Free)"
                    // and every OTHER row should also say "Talisha (7 Free)".
                    
                    const teacherName = $option.text().split(' (')[0].trim();
                    $option.text(`${teacherName} (${remaining} Free)`);
                }
            });

            // Refresh Select2 display with new option text
            if ($select.data('select2')) {
                const selection = $select.select2('data')[0];
                if (selection && selection.id) {
                    const $opt = $select.find(`option[value="${selection.id}"]`);
                    $select.next('.select2-container').find('.select2-selection__rendered').text($opt.text());
                }
            }
        });
    }

    // Trigger on load for existing selections
    $('.candidate-select').first().trigger('change');
    updateFreePeriodCounts();

    // Preview Button Logic
    $('#previewBtn').on('click', function() {
        let previewHtml = '<table class="table table-sm table-striped"><thead><tr><th>Absent Teacher</th><th>Period</th><th>Subject</th><th>Replacement</th></tr></thead><tbody>';
        let count = 0;

        $('.candidate-select').each(function() {
            const val = $(this).val();
            if (!val) return;

            const proxyName = $(this).find('option:selected').text();
            const $row = $(this).closest('tr');
            const absentName = $row.find('.fw-bold').first().text();
            const details = $row.find('.small').text().trim();
            const periodMatch = details.match(/Period (\d+)/);
            const period = periodMatch ? periodMatch[1] : '?';
            const subject = $row.find('.text-primary').text();

            previewHtml += `<tr>
                <td>${absentName}</td>
                <td>P${period}</td>
                <td>${subject}</td>
                <td class="fw-bold text-success">${proxyName}</td>
            </tr>`;
            count++;
        });

        previewHtml += '</tbody></table>';

        if (count === 0) {
            previewHtml = '<div class="alert alert-info">No proxies have been selected yet.</div>';
        }

        $('#previewTableContainer').html(previewHtml);
        var previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
        previewModal.show();
    });
});
</script>

<style>
.select2-container--default .select2-selection--single {
    height: 38px;
    padding-top: 4px;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 36px;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
