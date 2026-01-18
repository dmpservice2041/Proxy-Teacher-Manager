<?php
require_once 'config/app.php';
require_once 'includes/header.php';
require_once 'services/ProxyEngine.php';
require_once 'reports/ProxyExcelReport.php';
require_once 'services/ProxyAllocationService.php';

$pdo = Database::getInstance()->getConnection();
$today = date('Y-m-d');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_attendance WHERE date = ? AND status = 'Absent'");
$stmt->execute([$today]);
$absentCount = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM proxy_assignments WHERE date = ?");
$stmt->execute([$today]);
$proxyCount = $stmt->fetchColumn();


$allocationService = new ProxyAllocationService();
$allSlots = $allocationService->getAbsentSlots($today);

$pendingSlots = array_filter($allSlots, function($s) {
    return empty($s['assigned_proxy_id']);
});
$notAllocatedCount = count($pendingSlots);

$message = '';
if (isset($_POST['generate_proxies'])) {
    $engine = new ProxyEngine();
    $logs = $engine->generateProxies($today);
    $message = count($logs) . " actions performed.";
}

$stmt = $pdo->prepare("
    SELECT pa.*, t_absent.name as absent, t_proxy.name as proxy, c.standard, c.division
    FROM proxy_assignments pa
    JOIN teachers t_absent ON pa.absent_teacher_id = t_absent.id
    JOIN teachers t_proxy ON pa.proxy_teacher_id = t_proxy.id
    JOIN classes c ON pa.class_id = c.id
    WHERE pa.date = ?
    ORDER BY pa.period_no
");
$stmt->execute([$today]);
$assignments = $stmt->fetchAll();

require_once 'models/Settings.php';
$settingsModel = new Settings();
$schoolName = $settingsModel->get('school_name', defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Proxy System');
$schoolLogo = $settingsModel->get('school_logo', '');
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
        --success-gradient: linear-gradient(135deg, #059669 0%, #10B981 100%);
        --danger-gradient: linear-gradient(135deg, #DC2626 0%, #EF4444 100%);
        --warning-gradient: linear-gradient(135deg, #D97706 0%, #F59E0B 100%);
        --info-gradient: linear-gradient(135deg, #2563EB 0%, #3B82F6 100%);
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
        background: white;
        padding: 1.5rem;
        border-radius: 1rem;
        box-shadow: var(--card-shadow);
    }

    .school-branding {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .school-logo-wrapper {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        overflow: hidden;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .school-logo-img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .page-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: #111827;
        margin: 0;
        line-height: 1.2;
    }
    
    .page-subtitle {
        color: #6B7280;
        font-size: 0.875rem;
        margin: 0;
    }

    .stats-card {
        background: white;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.05);
        height: 100%;
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
        font-size: 2.5rem;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 0.5rem;
    }

    .stats-label {
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #6B7280;
    }

    .card-danger .stats-icon-wrapper { background: rgba(220, 38, 38, 0.1); color: #DC2626; }
    .card-danger .stats-value { color: #DC2626; }

    .card-success .stats-icon-wrapper { background: rgba(16, 185, 129, 0.1); color: #059669; }
    .card-success .stats-value { color: #059669; }

    .card-warning .stats-icon-wrapper { background: rgba(217, 119, 6, 0.1); color: #D97706; }
    .card-warning .stats-value { color: #D97706; }

    .action-card {
        background: white;
        border-radius: 1rem;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        border: 1px solid rgba(0,0,0,0.05);
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        transition: all 0.2s;
    }
    
    .action-card:hover {
        background-color: #f9fafb;
    }
    
    .action-btn {
        width: 100%;
        padding: 0.75rem;
        border-radius: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.875rem;
        transition: all 0.2s;
    }

    .table-card {
        background: white;
        border-radius: 1rem;
        box-shadow: var(--card-shadow);
        overflow: hidden;
        border: 1px solid rgba(0,0,0,0.05);
        margin-bottom: 2rem;
    }

    .table-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #f3f4f6;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background-color: #fff;
    }

    .table-title {
        font-weight: 700;
        color: #111827;
        font-size: 1.1rem;
        margin: 0;
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

    .period-badge {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: #eff6ff;
        color: #3b82f6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.875rem;
    }

    .status-badge {
        padding: 0.35em 0.8em;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 9999px;
    }
    
    .status-badge.allocated { background-color: #d1fae5; color: #065f46; }
    .status-badge.pending { background-color: #fee2e2; color: #991b1b; }
    .status-badge.manual { background-color: #fef3c7; color: #92400e; }
    .status-badge.auto { background-color: #e0e7ff; color: #3730a3; }

</style>

<div class="main-content">
    
    <!-- Page Header with School Branding -->
    <div class="page-header">
        <div class="school-branding">
            <?php if (!empty($schoolLogo) && file_exists($schoolLogo)): ?>
                <div class="school-logo-wrapper">
                    <img src="<?php echo htmlspecialchars($schoolLogo); ?>" alt="School Logo" class="school-logo-img">
                </div>
            <?php else: ?>
                 <div class="school-logo-wrapper">
                    <i class="fas fa-school text-muted fs-3"></i>
                </div>
            <?php endif; ?>
            <div>
                <h1 class="page-title"><?php echo htmlspecialchars($schoolName); ?></h1>
                <p class="page-subtitle">Dashboard Overview &bull; <?php echo date('l, d F Y'); ?></p>
            </div>
        </div>
        <div class="d-flex gap-3">
             <a href="attendance.php" class="btn btn-light bg-gray-100 fw-bold border">
                <i class="fas fa-user-clock me-2 text-primary"></i> Attendance
            </a>
            <a href="reports.php" class="btn btn-light bg-gray-100 fw-bold border">
                <i class="fas fa-file-alt me-2 text-info"></i> Reports
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-info border-0 shadow-sm rounded-3 mb-4 d-flex align-items-center gap-3">
            <i class="fas fa-info-circle fs-4"></i>
            <div><?php echo $message; ?></div>
        </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="row g-4 mb-5">
        <!-- Absent Teachers -->
        <div class="col-md-3">
            <div class="stats-card card-danger">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stats-value"><?php echo $absentCount; ?></div>
                <div class="stats-label">Absent Teachers</div>
                <div class="position-absolute top-0 end-0 p-3 opacity-10">
                    <i class="fas fa-user-times fa-5x text-danger transform rotate-12" style="opacity: 0.1;"></i>
                </div>
            </div>
        </div>

        <!-- Proxies Assigned -->
        <div class="col-md-3">
            <div class="stats-card card-success">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stats-value"><?php echo $proxyCount; ?></div>
                <div class="stats-label">Proxies Assigned</div>
                <div class="position-absolute top-0 end-0 p-3">
                    <i class="fas fa-check-circle fa-5x text-success transform rotate-12" style="opacity: 0.1;"></i>
                </div>
            </div>
        </div>

        <!-- Pending Allocations -->
        <div class="col-md-3">
            <div class="stats-card card-warning">
                <div class="stats-icon-wrapper">
                    <i class="fas fa-clock"></i>
                </div>
                <?php if ($notAllocatedCount > 0): ?>
                    <div class="stats-value text-danger"><?php echo $notAllocatedCount; ?></div>
                    <div class="stats-label text-danger">Pending Slots</div>
                <?php else: ?>
                    <div class="stats-value text-success">0</div>
                    <div class="stats-label text-success">All Clear</div>
                <?php endif; ?>
                <div class="position-absolute top-0 end-0 p-3">
                    <i class="fas fa-exclamation-triangle fa-5x text-warning transform rotate-12" style="opacity: 0.1;"></i>
                </div>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="col-md-3">
            <div class="action-card bg-primary bg-gradient text-white border-0 position-relative overflow-hidden">
                <div class="flex-grow-1 d-flex flex-column justify-content-center align-items-center z-1">
                    <div class="mb-3 d-flex align-items-center justify-content-center rounded-circle" 
                         style="width: 70px; height: 70px; background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(8px); border: 1px solid rgba(255, 255, 255, 0.3); box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);">
                         <i class="fas fa-magic fa-2x text-white"></i>
                    </div>
                    <h5 class="fw-bold mb-3">Allocation Console</h5>
                    <a href="proxy_allocation.php" class="btn btn-light text-primary fw-bold shadow-sm w-100 rounded-pill stretched-link">
                        Manage Proxies <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
                <!-- Decor -->
                 <div class="position-absolute top-0 end-0 p-0">
                    <i class="fas fa-shapes fa-8x text-white" style="opacity: 0.1; transform: translate(30%, -30%);"></i>
                </div>
            </div>
        </div>
    </div>


    <!-- Content Area: Pending Slots (High Priority) -->
    <?php if ($notAllocatedCount > 0): ?>
    <div class="table-card border-warning">
        <div class="table-header bg-warning bg-opacity-10">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-exclamation-triangle text-warning"></i>
                <h5 class="table-title text-warning-dark">Pending Allocations (<?php echo $notAllocatedCount; ?>)</h5>
            </div>
            <a href="proxy_allocation.php" class="btn btn-sm btn-warning fw-bold shadow-sm">
                Assign Now <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th class="text-center" width="80">Period</th>
                        <th>Class Details</th>
                        <th>Absent Teacher</th>
                        <th>Subject</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pendingSlots as $slot): ?>
                    <tr>
                        <td class="text-center">
                            <div class="period-badge mx-auto bg-warning bg-opacity-10 text-warning"><?php echo $slot['period_no']; ?></div>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo $slot['standard'] . '-' . $slot['division']; ?></div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle bg-light text-secondary d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                    <?php echo substr($slot['teacher_name'], 0, 1); ?>
                                </div>
                                <span class="text-dark"><?php echo htmlspecialchars($slot['teacher_name']); ?></span>
                            </div>
                        </td>
                        <td class="text-secondary fw-500"><?php echo htmlspecialchars($slot['subject_name']); ?></td>
                        <td class="text-center">
                            <span class="status-badge pending">Pending</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>


    <!-- Today's Allocations -->
    <div class="table-card">
        <div class="table-header">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-list-alt text-primary"></i>
                <h5 class="table-title">Today's Activity Feed</h5>
            </div>
             <a href="reports.php" class="btn btn-sm btn-outline-secondary">
                View All <i class="fas fa-external-link-alt ms-1"></i>
            </a>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th class="text-center" width="80">Period</th>
                        <th>Class</th>
                        <th>Absent Teacher</th>
                        <th>Proxy Replacement</th>
                        <th>Mode</th>
                        <th width="100" class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($assignments) > 0): ?>
                        <?php foreach($assignments as $row): ?>
                        <tr>
                            <td class="text-center">
                                <div class="period-badge mx-auto"><?php echo $row['period_no']; ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo $row['standard'] . '-' . $row['division']; ?></span>
                            </td>
                            <td>
                                <div class="text-muted small text-uppercase fw-bold mb-1">Absent</div>
                                <div class="fw-bold text-danger"><?php echo htmlspecialchars($row['absent']); ?></div>
                            </td>
                            <td>
                                <div class="text-muted small text-uppercase fw-bold mb-1">Covered By</div>
                                <div class="fw-bold text-success">
                                    <i class="fas fa-check-circle me-1 small"></i>
                                    <?php echo htmlspecialchars($row['proxy']); ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($row['mode'] === 'MANUAL'): ?>
                                    <span class="status-badge manual"><i class="fas fa-hand-pointer me-1"></i> Manual</span>
                                <?php else: ?>
                                    <span class="status-badge auto"><i class="fas fa-robot me-1"></i> Auto</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <i class="fas fa-lock text-muted" title="Locked"></i>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <div class="text-muted mb-2"><i class="fas fa-calendar-check fa-3x opacity-25"></i></div>
                                <h6 class="text-muted">No allocations yet for today.</h6>
                                <?php if ($notAllocatedCount == 0 && $absentCount == 0): ?>
                                    <p class="small text-success">All teachers are present! No proxies needed.</p>
                                <?php else: ?>
                                    <a href="proxy_allocation.php" class="btn btn-sm btn-primary mt-2">Start Allocation</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
