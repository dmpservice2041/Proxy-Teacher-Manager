<?php
require_once __DIR__ . '/../config/database.php';

class Attendance {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // Mark attendance
    public function markAttendance($teacherId, $date, $status = 'Present', $source = 'API', $inTime = null, $outTime = null) {
        // Locking removed as per user request
        
        $stmt = $this->pdo->prepare("
            INSERT INTO teacher_attendance (teacher_id, date, status, source, in_time, out_time)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status), 
                source = VALUES(source),
                in_time = VALUES(in_time),
                out_time = VALUES(out_time)
        ");
        return $stmt->execute([$teacherId, $date, $status, $source, $inTime, $outTime]);
    }

    // Check if attendance is locked
    public function isLocked($teacherId, $date) {
        $stmt = $this->pdo->prepare("SELECT locked FROM teacher_attendance WHERE teacher_id = ? AND date = ?");
        $stmt->execute([$teacherId, $date]);
        return (bool)$stmt->fetchColumn();
    }

    // Lock attendance for a date (bulk)
    public function lockAll($date) {
        $stmt = $this->pdo->prepare("UPDATE teacher_attendance SET locked = 1 WHERE date = ?");
        return $stmt->execute([$date]);
    }

    // Delete all attendance for a date
    public function deleteAll($date) {
        $stmt = $this->pdo->prepare("DELETE FROM teacher_attendance WHERE date = ?");
        return $stmt->execute([$date]);
    }

    // Get all attendance records for a date with teacher empcode and name
    public function getAllForDate($date) {
        $stmt = $this->pdo->prepare("
            SELECT ta.*, t.empcode, t.name 
            FROM teacher_attendance ta
            JOIN teachers t ON ta.teacher_id = t.id
            WHERE ta.date = ?
            ORDER BY CAST(t.empcode AS UNSIGNED), t.empcode
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }

    // Get absent teachers for a date (Including both ACTIVE and INACTIVE)
    public function getAbsentTeachers($date) {
        $stmt = $this->pdo->prepare("
            SELECT t.*, ta.status
            FROM teachers t
            JOIN teacher_attendance ta ON t.id = ta.teacher_id
            WHERE ta.date = ? AND ta.status = 'Absent'
            ORDER BY t.is_active DESC, CAST(t.empcode AS UNSIGNED), t.empcode
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }
    
    // Check if teacher is present
    public function isPresent($teacherId, $date) {
         $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM teacher_attendance 
            WHERE teacher_id = ? AND date = ? AND status = 'Present'
        ");
        $stmt->execute([$teacherId, $date]);
        return $stmt->fetchColumn() > 0;
    }

    // Get attendance record for a specific teacher and date
    public function getAttendanceForTeacher($teacherId, $date) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM teacher_attendance 
            WHERE teacher_id = ? AND date = ?
        ");
        $stmt->execute([$teacherId, $date]);
        return $stmt->fetch();
    }
    // Get attendance records for a date range (for reports)
    public function getAttendanceRange($startDate, $endDate) {
        $stmt = $this->pdo->prepare("
            SELECT ta.*, t.empcode, t.name as teacher_name
            FROM teacher_attendance ta
            JOIN teachers t ON ta.teacher_id = t.id
            WHERE ta.date BETWEEN ? AND ?
            ORDER BY t.empcode, ta.date
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }
}
