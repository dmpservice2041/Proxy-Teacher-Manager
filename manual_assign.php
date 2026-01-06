<?php
require_once 'config/app.php';
require_once 'includes/header.php';
require_once 'models/Teacher.php';
require_once 'models/Timetable.php';
require_once 'models/ProxyAssignment.php';

$teacherModel = new Teacher();
$teachers = $teacherModel->getAllActive();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $absentId = $_POST['absent_teacher_id'];
    $proxyId = $_POST['proxy_teacher_id'];
    $periodNo = $_POST['period_no'];

    if ($absentId == $proxyId) {
        $error = "Absent teacher and Proxy teacher cannot be the same.";
    } else {
        try {
            // Find Class/Subject for this slot
            $timetableModel = new Timetable();
            $dayOfWeek = date('N', strtotime($date));
            $schedule = $timetableModel->getTeacherSchedule($absentId, $dayOfWeek);
            
            $classId = null;
            foreach ($schedule as $slot) {
                if ($slot['period_no'] == $periodNo) {
                    $classId = $slot['class_id'];
                    break;
                }
            }

            if ($classId) {
                $proxyModel = new ProxyAssignment();
                $id = $proxyModel->assign($date, $absentId, $proxyId, $classId, $periodNo, 'MANUAL', 'Admin Web UI Override');
                $message = "Successfully assigned proxy (ID: $id)";
            } else {
                $error = "No lecture found for the Absent Teacher at Period $periodNo on this date.";
            }

        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header">Manual Proxy Assignment</div>
        <div class="card-body">
            <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

            <form method="POST">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>Period No</label>
                        <input type="number" name="period_no" class="form-control" min="1" max="10" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Absent Teacher</label>
                        <select name="absent_teacher_id" class="form-select" required>
                            <option value="">Select Absent Teacher</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Proxy Teacher (Replacement)</label>
                        <select name="proxy_teacher_id" class="form-select" required>
                            <option value="">Select Proxy Teacher</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-warning">Force Assign</button>
                <a href="dashboard.php" class="btn btn-secondary">Back</a>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
