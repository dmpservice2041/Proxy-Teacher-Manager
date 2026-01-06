<?php
require_once 'config/app.php';
require_once 'includes/header.php';

$date = $_GET['date'] ?? date('Y-m-d');
?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar/Actions Column -->
        <div class="col-md-3">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Report Controls</h5>
                </div>
                <div class="card-body">
                    <form id="reportForm">
                        <div class="mb-3">
                            <label class="form-label">Select Date</label>
                            <input type="date" name="date" id="reportDate" class="form-control" value="<?php echo $date; ?>">
                        </div>
                        <button type="button" id="btnPreview" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-eye me-1"></i> View Preview
                        </button>
                        <button type="button" id="btnGenerate" class="btn btn-success w-100 mb-2">
                            <i class="fas fa-file-excel me-1"></i> Generate Excel
                        </button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Recently Generated</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush" id="recentFilesList">
                        <?php 
                        $files = glob("exports/Proxy_Report_*.xlsx");
                        rsort($files);
                        $files = array_slice($files, 0, 10);
                        if (empty($files)): ?>
                            <li class="list-group-item text-muted small">No reports found</li>
                        <?php else: ?>
                            <?php foreach ($files as $f): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center small">
                                    <a href="<?php echo $f; ?>" class="text-decoration-none truncate" title="<?php echo $f; ?>">
                                        <i class="fas fa-file-excel text-success me-1"></i> <?php echo str_replace('exports/Proxy_Report_', '', str_replace('.xlsx', '', $f)); ?>
                                    </a>
                                    <span class="badge bg-light text-dark border"><?php echo round(filesize($f)/1024, 1); ?> KB</span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Preview Column -->
        <div class="col-md-9">
            <div id="statusMessage"></div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Report Preview: <span id="displayDate"><?php echo $date; ?></span></h5>
                    <div id="loadingSpinner" class="spinner-border spinner-border-sm text-primary" role="status" style="display: none;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0" id="previewTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="80">Period</th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Absent Teacher</th>
                                    <th>Proxy Teacher</th>
                                    <th>Mode</th>
                                </tr>
                            </thead>
                            <tbody id="previewBody">
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        Select a date and click "View Preview" to see proxy assignments.
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function() {
    function loadPreview() {
        const date = $('#reportDate').val();
        $('#displayDate').text(date);
        $('#loadingSpinner').show();
        $('#statusMessage').empty();

        $.ajax({
            url: 'scripts/get_report_data.php',
            type: 'GET',
            data: { date: date },
            success: function(response) {
                $('#loadingSpinner').hide();
                if (response.success) {
                    const data = response.data;
                    let html = '';
                    
                    if (data.length === 0) {
                        html = '<tr><td colspan="6" class="text-center py-5 text-muted">No proxy assignments found for this date.</td></tr>';
                    } else {
                        data.forEach(row => {
                            html += `<tr>
                                <td><span class="badge bg-info text-dark">Period ${row.period_no}</span></td>
                                <td>${row.class_name}</td>
                                <td class="fw-bold text-primary">${row.subject_name || '-'}</td>
                                <td>${row.absent_teacher}</td>
                                <td class="fw-bold text-success">${row.proxy_teacher}</td>
                                <td><span class="badge ${row.mode === 'MANUAL' ? 'bg-warning text-dark' : 'bg-secondary'}">${row.mode}</span></td>
                            </tr>`;
                        });
                    }
                    $('#previewBody').html(html);
                } else {
                    $('#statusMessage').html(`<div class="alert alert-danger">Error: ${response.error}</div>`);
                }
            },
            error: function() {
                $('#loadingSpinner').hide();
                $('#statusMessage').html('<div class="alert alert-danger">Failed to fetch report data.</div>');
            }
        });
    }

    $('#btnPreview').on('click', loadPreview);

    $('#btnGenerate').on('click', function() {
        const date = $('#reportDate').val();
        const $btn = $(this);
        const originalHtml = $btn.html();
        
        $btn.html('<span class="spinner-border spinner-border-sm me-1"></span> Generating...').prop('disabled', true);
        $('#statusMessage').empty();

        $.ajax({
            url: 'scripts/generate_daily_proxy.php',
            type: 'GET',
            data: { date: date },
            success: function(response) {
                $btn.html(originalHtml).prop('disabled', false);
                if (response.success) {
                    const filename = response.files.excel;
                    $('#statusMessage').html(`
                        <div class="alert alert-success d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-check-circle me-1"></i> Report generated successfully!</span>
                            <a href="${filename}" class="btn btn-sm btn-light border" download>
                                <i class="fas fa-download me-1"></i> Download Now
                            </a>
                        </div>
                    `);
                    // Reload the recent files list (simple page refresh for now or dynamic append)
                    location.reload(); 
                } else {
                    $('#statusMessage').html(`<div class="alert alert-danger">Error: ${response.error}</div>`);
                }
            },
            error: function() {
                $btn.html(originalHtml).prop('disabled', false);
                $('#statusMessage').html('<div class="alert alert-danger">Failed to generate report.</div>');
            }
        });
    });

    // Auto-load if date is in URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('date')) {
        loadPreview();
    }
});
</script>

<style>
.truncate {
    display: inline-block;
    max-width: 150px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
