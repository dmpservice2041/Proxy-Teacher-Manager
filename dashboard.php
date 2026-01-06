<?php
require_once 'config/app.php';
require_once 'includes/header.php';
require_once 'services/ProxyEngine.php';
require_once 'reports/ProxyExcelReport.php';
require_once 'services/ProxyAllocationService.php';

// Quick Stats Handling (Assuming implementation in services, or raw query here)
// For simplicity, we'll instantiate services but direct DB might be faster for dashboard KPI.
// Let's implement a simple direct query in dashboard for KPI.
$pdo = Database::getInstance()->getConnection();
$today = date('Y-m-d');

// KPI 1: Total Absent Teachers
$stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_attendance WHERE date = ? AND status = 'Absent'");
$stmt->execute([$today]);
$absentCount = $stmt->fetchColumn();

// KPI 2: Total Proxies Assigned
$stmt = $pdo->prepare("SELECT COUNT(*) FROM proxy_assignments WHERE date = ?");
$stmt->execute([$today]);
$proxyCount = $stmt->fetchColumn();

// KPI 3: Coverage %
// Let's just show raw numbers.

// Fetch All Absent Slots for the new logic
$allocationService = new ProxyAllocationService();
$allSlots = $allocationService->getAbsentSlots($today);

$pendingSlots = array_filter($allSlots, function($s) {
    return empty($s['assigned_proxy_id']);
});
$notAllocatedCount = count($pendingSlots);

// Handle Generate Action
$message = '';
if (isset($_POST['generate_proxies'])) {
    $engine = new ProxyEngine();
    $logs = $engine->generateProxies($today);
    
    // Simple output formatting
    $message = count($logs) . " actions performed.";
}

// Fetch Today's Assignments
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

?>

<div class="container-fluid">
    <div class="row mb-4">
        <!-- KPI 1: Absent Teachers -->
        <div class="col-md-3">
            <div class="card text-white bg-danger mb-3 shadow-sm">
                <div class="card-header">Absent Teachers</div>
                <div class="card-body">
                    <h5 class="card-title"><?php echo $absentCount; ?></h5>
                    <p class="card-text small">Teachers marked absent today.</p>
                </div>
            </div>
        </div>

        <!-- KPI 2: Proxies Assigned -->
        <div class="col-md-3">
            <div class="card text-white bg-success mb-3 shadow-sm">
                <div class="card-header">Proxies Assigned</div>
                <div class="card-body">
                    <h5 class="card-title"><?php echo $proxyCount; ?></h5>
                    <p class="card-text small">Total slots covered today.</p>
                </div>
            </div>
        </div>

        <!-- KPI 3: Not Allocations (Pending) -->
        <div class="col-md-3">
            <div class="card text-white bg-warning mb-3 shadow-sm">
                <div class="card-header">Not Allocations</div>
                <div class="card-body">
                    <h5 class="card-title"><?php echo $notAllocatedCount; ?></h5>
                    <p class="card-text small">Pending proxy assignments.</p>
                </div>
            </div>
        </div>

        <!-- Action 1: Allocation Tool -->
        <div class="col-md-3 d-none d-md-block">
            <div class="card text-white bg-primary mb-3 shadow-sm">
                <div class="card-header text-white">Proxy Allocation</div>
                <div class="card-body">
                    <h5 class="card-title text-white">Allocation Tool</h5>
                    <a href="proxy_allocation.php" class="btn btn-light btn-sm w-100">
                        <i class="fas fa-magic"></i> Open Allocator
                    </a>
                </div>
            </div>
        </div>

        <!-- Action 2: View Reports -->
        <div class="col-md-3">
            <div class="card text-white bg-info mb-3 shadow-sm">
                <div class="card-header text-white">Documentation</div>
                <div class="card-body">
                    <h5 class="card-title text-white">Reports Center</h5>
                    <a href="reports.php?date=<?php echo $today; ?>" class="btn btn-light btn-sm w-100">
                        <i class="fas fa-file-excel"></i> View/Print Reports
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            Today's Allocations (<?php echo $today; ?>)
        </div>
        <div class="card-body">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Class</th>
                        <th>Absent Teacher</th>
                        <th>Proxy Teacher</th>
                        <th>Mode</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($assignments) > 0): ?>
                        <?php foreach($assignments as $row): ?>
                        <tr>
                            <td><?php echo $row['period_no']; ?></td>
                            <td><?php echo $row['standard'] . '-' . $row['division']; ?></td>
                            <td><?php echo htmlspecialchars($row['absent']); ?></td>
                            <td><?php echo htmlspecialchars($row['proxy']); ?></td>
                            <td>
                                <span class="badge <?php echo $row['mode'] === 'MANUAL' ? 'bg-warning' : 'bg-info'; ?>">
                                    <?php echo $row['mode']; ?>
                                </span>
                            </td>
                            <td>Locked</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center">No assignments found for today.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
    <div class="card mt-4 border-warning">
        <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
            <span>Today's Not Allocations (Pending)</span>
            <a href="proxy_allocation.php" class="btn btn-dark btn-sm">Assign Now</a>
        </div>
        <div class="card-body">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th class="text-center">Period</th>
                        <th>Class</th>
                        <th>Absent Teacher</th>
                        <th>Subject</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pendingSlots) > 0): ?>
                        <?php foreach($pendingSlots as $slot): ?>
                        <tr>
                            <td class="text-center"><span class="badge bg-secondary"><?php echo $slot['period_no']; ?></span></td>
                            <td><?php echo $slot['standard'] . '-' . $slot['division']; ?></td>
                            <td><?php echo htmlspecialchars($slot['teacher_name']); ?></td>
                            <td><?php echo htmlspecialchars($slot['subject_name']); ?></td>
                            <td class="text-center">
                                <span class="badge bg-danger">NOT ALLOCATED</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-success fw-bold"><i class="fas fa-check-circle"></i> All periods have been allocated!</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
