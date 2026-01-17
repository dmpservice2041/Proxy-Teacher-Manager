<?php
require_once 'config/app.php';
require_once 'includes/header.php';
require_once 'models/Teacher.php';
require_once 'models/Classes.php';

$allTeachers = (new Teacher())->getAllActive(); // Ensure this returns name/empcode/id
$allClasses = (new Classes())->getAll();

$date = $_GET['date'] ?? date('Y-m-d');
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
        --primary-color: #4F46E5;
        --secondary-color: #6B7280;
        --success-color: #10B981;
        --danger-color: #EF4444;
        --warning-color: #F59E0B;
        --bg-light: #F3F4F6;
        --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    
    body {
        background-color: var(--bg-light);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    .main-content {
        padding: 2rem;
    }

    /* Page Header */
    .page-header {
        background: var(--primary-gradient);
        padding: 2.5rem 2rem;
        border-radius: 16px;
        color: white;
        margin-bottom: 2rem;
        box-shadow: var(--card-shadow);
        position: relative;
    }
    .page-header::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url('assets/pattern.png');
        opacity: 0.1;
        border-radius: 16px;
    }
    .page-header > * {
        position: relative;
        z-index: 1;
    }

    /* Cards */
    .glass-card {
        background: white;
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.5);
        box-shadow: var(--card-shadow);
        overflow: hidden;
    }
    
    .card-header-custom {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #E5E7EB;
        background: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .card-title-custom {
        font-weight: 600;
        color: #111827;
        margin: 0;
        font-size: 1.1rem;
    }

    .card-body-custom {
        padding: 1.5rem;
    }

    /* Controls */
    .control-label {
        font-weight: 500;
        color: #374151;
        margin-bottom: 0.5rem;
        display: block;
    }
    
    .form-control-custom {
        border-radius: 8px;
        border: 1px solid #D1D5DB;
        padding: 0.625rem 1rem;
        transition: all 0.2s;
    }
    .form-control-custom:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    /* Buttons */
    .btn-custom {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.2s;
        border: none;
    }
    .btn-primary-custom {
        background: var(--primary-gradient);
        color: white;
        box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.4);
    }
    .btn-primary-custom:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 8px -1px rgba(79, 70, 229, 0.6);
        color: white;
    }
    .btn-success-custom {
        background: #10B981;
        color: white;
        box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.4);
    }
    .btn-success-custom:hover {
        background: #059669;
        color: white;
        transform: translateY(-1px);
    }

    /* Table */
    .table-custom th {
        background: #F9FAFB;
        font-weight: 600;
        color: #374151;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        padding: 1rem;
    }
    .table-custom td {
        padding: 1rem;
        vertical-align: middle;
        border-color: #F3F4F6;
        color: #4B5563;
        font-size: 0.9rem;
        white-space: normal;
        word-wrap: break-word;
    }
    .table-hover tbody tr:hover {
        background-color: #F9FAFB;
    }

    /* Recent Files */
    .file-item {
        padding: 1rem;
        border-radius: 8px;
        transition: background 0.2s;
        border: 1px solid transparent;
        margin-bottom: 0.5rem;
    }
    .file-item:hover {
        background: #F9FAFB;
        border-color: #E5E7EB;
    }
    .file-icon {
        width: 36px;
        height: 36px;
        background: #ECFDF5;
        color: #10B981;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
    }
</style>

<div class="main-content container-fluid">
    <!-- Header -->
    <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-center mb-5">
        <div>
            <h1 class="fw-bold mb-1">Daily Reports</h1>
            <p class="mb-0 opacity-75">Generate and download proxy allocation reports.</p>
        </div>
        <div>
            <!-- Header Actions if needed -->
        </div>
    </div>

    <div class="row g-4">
        <!-- Sidebar: Controls & History -->
        <div class="col-lg-4">
            
            <!-- Controls Card -->
            <div class="glass-card mb-4">
                <div class="card-header-custom">
                    <h3 class="card-title-custom"><i class="fas fa-sliders-h me-2 text-primary"></i> Report Options</h3>
                </div>
                <div class="card-body-custom">
                    <form id="reportForm">
                        <div class="mb-3">
                            <label class="control-label">Report Type</label>
                            <select name="report_type" id="reportType" class="form-select form-control-custom">
                                <option value="daily">One Day Report</option>
                                <option value="range">Date Range Report (All Teachers)</option>
                                <option value="teacher">Teacher Wise Report</option>
                                <option value="class">Class Wise Report</option>
                            </select>
                        </div>

                        <!-- One Day Input -->
                        <div id="dailyInput" class="mb-3">
                            <label class="control-label">Select Date</label>
                            <input type="date" name="date" id="reportDate" class="form-control form-control-custom" value="<?php echo $date; ?>">
                        </div>

                        <!-- Range Inputs -->
                        <div id="rangeInputs" class="mb-3" style="display:none;">
                            <label class="control-label">Date Range</label>
                            <div class="d-flex gap-2">
                                <div class="flex-grow-1">
                                    <label class="small text-muted mb-1">From</label>
                                    <input type="date" name="start_date" id="startDate" class="form-control form-control-custom" value="<?php echo date('Y-m-01'); ?>">
                                </div>
                                <div class="flex-grow-1">
                                    <label class="small text-muted mb-1">To</label>
                                    <input type="date" name="end_date" id="endDate" class="form-control form-control-custom" value="<?php echo date('Y-m-t'); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Teacher Select -->
                        <div id="teacherInput" class="mb-3" style="display:none;">
                            <label class="control-label">Select Teacher</label>
                            <select name="teacher_id" id="teacherId" class="form-select form-control-custom">
                                <option value="">-- Select Teacher --</option>
                                <?php foreach ($allTeachers as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Class Select -->
                        <div id="classInput" class="mb-3" style="display:none;">
                            <label class="control-label">Select Class</label>
                            <select name="class_id" id="classId" class="form-select form-control-custom">
                                <option value="">-- Select Class --</option>
                                <?php foreach ($allClasses as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= $c['standard'] . '-' . $c['division'] . ' (' . ($c['stream'] ?? '') . ')' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="d-grid gap-3">
                            <button type="button" id="btnPreview" class="btn btn-custom btn-primary-custom">
                                <i class="fas fa-eye me-2"></i> View Preview
                            </button>
                            <button type="button" id="btnGenerate" class="btn btn-custom btn-success-custom">
                                <i class="fas fa-file-excel me-2"></i> Download Excel Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recent Files Card -->
            <div class="glass-card">
                <div class="card-header-custom">
                    <h3 class="card-title-custom"><i class="fas fa-history me-2 text-secondary"></i> Recent Downloads</h3>
                </div>
                <div class="card-body-custom p-3">
                    <div id="recentFilesList">
                        <?php 
                        // Fetch all Excel files
                        $files = glob("exports/*.{xls,xlsx}", GLOB_BRACE);
                        usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
                        $files = array_slice($files, 0, 5);
                        
                        if (empty($files)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="far fa-folder-open fa-2x mb-2 opacity-50"></i>
                                <p class="small mb-0">No recent reports found</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($files as $f): 
                                $filename = basename($f);
                                $displayName = "Unknown Report";
                                
                                if (strpos($filename, 'Allocation_Daily_') === 0) {
                                    $datePart = str_replace(['Allocation_Daily_', '.xls'], '', $filename);
                                    $displayName = "One Day: " . date("d M", strtotime($datePart));
                                } elseif (strpos($filename, 'Allocation_Report_') === 0) {
                                    $parts = str_replace(['Allocation_Report_', '.xls'], '', $filename);
                                    $dates = explode('_to_', $parts);
                                    $displayName = "Range: " . date("d M", strtotime($dates[0])) . " - " . date("d M", strtotime($dates[1]));
                                } elseif (strpos($filename, 'Teacher_Allocation_') === 0) {
                                    $parts = str_replace(['Teacher_Allocation_', '.xls'], '', $filename);
                                    // Filename might be START or START_END
                                    // Logic in export script: "Teacher_Allocation_" . $startDate . "_" . $endDate . ".xls" OR just startDate
                                    // Let's just show "Teacher Report"
                                    $displayName = "Teacher Report";
                                } elseif (strpos($filename, 'Class_Allocation_') === 0) {
                                    $displayName = "Class Report";
                                } elseif (strpos($filename, 'Proxy_Report_') === 0) {
                                     // Legacy
                                     $dateStr = str_replace(['Proxy_Report_', '.xlsx'], '', $filename);
                                     $displayName = "Legacy: " . date("d M, Y", strtotime($dateStr));
                                } else {
                                    $displayName = $filename;
                                }

                                $timeAgo = date("h:i A", filemtime($f));
                                $size = round(filesize($f)/1024, 1) . ' KB';
                            ?>
                                <a href="<?php echo $f; ?>?v=<?php echo filemtime($f); ?>" class="text-decoration-none text-dark">
                                    <div class="file-item d-flex align-items-center">
                                        <div class="file-icon">
                                            <i class="fas fa-file-excel fs-5"></i>
                                        </div>
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="fw-semibold text-truncate"><?php echo $displayName; ?></div>
                                            <div class="small text-muted d-flex justify-content-between mt-1">
                                                <span><?php echo $timeAgo; ?></span>
                                                <span><?php echo $size; ?></span>
                                            </div>
                                        </div>
                                        <i class="fas fa-download text-muted ms-2 opacity-50"></i>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content: Preview -->
        <div class="col-lg-8">
            <div id="statusMessage"></div>
            
            <div class="glass-card">
                <div class="card-header-custom">
                    <h3 class="card-title-custom">Preview: <span id="displayDate" class="text-primary"><?php echo date("d M, Y", strtotime($date)); ?></span></h3>
                    <div id="loadingSpinner" class="spinner-border spinner-border-sm text-primary" role="status" style="display: none;"></div>
                </div>
                <div class="card-body-custom p-0">
                    <div class="table-responsive">
                        <table class="table table-custom table-hover mb-0" id="previewTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Period</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Absent Teacher</th>
                                    <th>Proxy Teacher</th>
                                    <th>Mode</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody id="previewBody">
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="py-4 text-muted">
                                            <i class="fas fa-search fa-3x mb-3 opacity-25"></i>
                                            <p>Select a date and click "View Preview"</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Select2 Custom Styling to match theme */
    .select2-container .select2-selection--single {
        height: 40px !important;
        border: 1px solid #D1D5DB !important;
        border-radius: 8px !important;
        padding: 5px 0;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 38px !important;
    }
    .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
        background-color: var(--primary-color) !important;
    }
</style>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize Select2 with search
    $('#teacherId').select2({
        placeholder: "Search for a teacher...",
        allowClear: true,
        width: '100%'
    });
    
    $('#classId').select2({
        placeholder: "Search for a class...",
        allowClear: true,
        width: '100%'
    });

    // Toggle Inputs based on Report Type
    function toggleInputs() {
        const type = $('#reportType').val();
         // Hide all first
        $('#dailyInput, #rangeInputs, #teacherInput, #classInput').hide();
        
        if (type === 'daily') {
            $('#dailyInput').show();
        } else {
            $('#rangeInputs').show();
            if (type === 'teacher') $('#teacherInput').show();
            if (type === 'class') $('#classInput').show();
        }
        // Always show preview button now
        $('#btnPreview').prop('disabled', false).show();
    }

    $('#reportType').on('change', toggleInputs);
    // Initial call
    toggleInputs();

    function loadPreview() {
        const formData = $('#reportForm').serialize();
        
        // Update display date/range text
        let dateText = '';
        const type = $('#reportType').val();
        if (type === 'daily') {
             const date = $('#reportDate').val();
             dateText = new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        } else {
             const start = $('#startDate').val();
             const end = $('#endDate').val();
             dateText = new Date(start).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) + ' to ' + new Date(end).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        }
        $('#displayDate').text(dateText);
        
        $('#loadingSpinner').show();
        $('#statusMessage').empty();

        $.ajax({
            url: 'scripts/get_report_data.php',
            type: 'GET',
            cache: false, // Prevent caching
            data: formData,
            success: function(response) {
                $('#loadingSpinner').hide();
                if (response.success) {
                    const data = response.data;
                    let html = '';
                    
                    if (data.length === 0) {
                        html = `<tr><td colspan="8" class="text-center py-5 text-muted">No proxy assignments found for these criteria.</td></tr>`;
                    } else {
                        data.forEach(row => {
                            const badgeClass = row.mode === 'MANUAL' ? 'bg-warning text-dark' : 'bg-primary text-white';
                            // Format date for display
                            const rowDate = new Date(row.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
                            
                            html += `<tr>
                                <td>${rowDate}</td>
                                <td><span class="badge bg-light text-dark border">P-${row.period_no}</span></td>
                                <td class="fw-medium">${row.class_name}</td>
                                <td class="text-secondary">${row.subject_name || '-'}</td>
                                <td class="text-danger fw-medium">${row.absent_teacher_name || row.absent_teacher || '-'}</td>
                                <td class="text-success fw-bold">${row.proxy_teacher_name || row.proxy_teacher || '-'}</td>
                                <td><span class="badge ${badgeClass} rounded-pill border border-white shadow-sm" style="font-size: 0.7rem; padding: 0.35em 0.8em;">${row.mode}</span></td>
                                <td class="text-muted small">${row.notes || '-'}</td>
                            </tr>`;
                        });
                    }
                    $('#previewBody').html(html);
                } else {
                    $('#statusMessage').html(`<div class="alert alert-danger shadow-sm border-0 rounded-3">Error: ${response.error}</div>`);
                }
            },
            error: function() {
                $('#loadingSpinner').hide();
                $('#statusMessage').html('<div class="alert alert-danger shadow-sm border-0 rounded-3">Failed to fetch report data.</div>');
            }
        });
    }

    $('#btnPreview').on('click', loadPreview);

    $('#btnGenerate').on('click', function() {
        const $btn = $(this);
        const originalHtml = $btn.html();
        const formData = $('#reportForm').serialize(); // Serialize all form data
        
        $btn.html('<span class="spinner-border spinner-border-sm me-2"></span>Generating...').prop('disabled', true);
        $('#statusMessage').empty();

        $.ajax({
            url: 'export_allocation_report.php', // New Export Script
            type: 'POST',
            data: formData,
            success: function(response) {
                $btn.html(originalHtml).prop('disabled', false);
                // response might be JSON or direct file download.
                // If the script forces download headers, AJAX won't handle it well.
                // Ideally, existing logic expects JSON with a file path.
                
                try {
                     // Check if response is JSON (if script returns JSON with file link)
                    if (response.success) {
                        const filename = response.file + '?v=' + new Date().getTime();
                        window.location.href = filename;

                        $('#statusMessage').html(`
                            <div class="alert alert-success d-flex justify-content-between align-items-center shadow-sm border-0 rounded-3 mb-4">
                                <span><i class="fas fa-check-circle me-2"></i> Report ready! Downloading...</span>
                                <a href="${filename}" class="btn btn-sm btn-light text-success fw-bold" download>
                                    Manual Download
                                </a>
                            </div>
                        `);
                        setTimeout(() => location.reload(), 2500); 
                    } else {
                        $('#statusMessage').html(`<div class="alert alert-danger shadow-sm border-0 rounded-3 mb-4">Error: ${response.error || 'Unknown error'}</div>`);
                    }
                } catch(e) {
                     // Fallback if direct output
                     console.error(e);
                }
            },
            error: function(xhr) {
                $btn.html(originalHtml).prop('disabled', false);
                $('#statusMessage').html(`<div class="alert alert-danger shadow-sm border-0 rounded-3 mb-4">Failed to generate report. ${xhr.statusText}</div>`);
            }
        });
    });

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('date')) {
        loadPreview();
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
