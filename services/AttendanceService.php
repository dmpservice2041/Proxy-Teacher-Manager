<?php
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/Teacher.php';

class AttendanceService {
    private $attendanceModel;
    private $teacherModel;

    public function __construct() {
        $this->attendanceModel = new Attendance();
        $this->teacherModel = new Teacher();
    }

    /**
     * Mark a teacher as absent or present.
     * Prevents modification if attendance is locked.
     */
    public function markAttendance($teacherId, $date, $status, $source = 'API') {
        // Validation moved inside try/catch in controller usually, or just pass through
        
        $teacher = $this->teacherModel->find($teacherId);
        if (!$teacher) {
            throw new Exception("Teacher ID $teacherId not found.");
        }

        return $this->attendanceModel->markAttendance($teacherId, $date, $status, $source);
    }

    /**
     * Import attendance from an array (e.g., from Excel or JSON API).
     * Expected format: [['teacher_id' => 1, 'status' => 'Absent'], ...]
     */
    public function bulkImport($data, $date, $source = 'Excel') {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($data as $row) {
            try {
                $this->markAttendance($row['teacher_id'], $date, $row['status'], $source);
                $results['success']++;
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Row Error: " . $e->getMessage();
            }
        }
        return $results;
    }

    /**
     * Delete all attendance records for a specific date
     */
    public function deleteAttendanceForDate($date) {
        return $this->attendanceModel->deleteAll($date);
    }

    public function getAttendanceForDate($date) {
        return $this->attendanceModel->getAllForDate($date);
    }
    
    // Kept for compatibility but effectively deprecated/unused based on new requirement
    public function lockAttendanceForDate($date) {
        return $this->attendanceModel->lockAll($date);
    }

    public function getAbsentTeachers($date) {
        return $this->attendanceModel->getAbsentTeachers($date);
    }

    public function isPresent($teacherId, $date) {
        return $this->attendanceModel->isPresent($teacherId, $date);
    }
}
