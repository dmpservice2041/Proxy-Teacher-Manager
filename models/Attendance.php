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

    // Get all attendance records for a date with teacher empcode
    public function getAllForDate($date) {
        $stmt = $this->pdo->prepare("
            SELECT ta.*, t.empcode 
            FROM teacher_attendance ta
            JOIN teachers t ON ta.teacher_id = t.id
            WHERE ta.date = ?
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }

    // Get absent teachers for a date (Only ACTIVE teachers need proxies)
    public function getAbsentTeachers($date) {
        $stmt = $this->pdo->prepare("
            SELECT t.* 
            FROM teachers t
            JOIN teacher_attendance ta ON t.id = ta.teacher_id
            WHERE ta.date = ? AND ta.status = 'Absent' AND t.is_active = 1
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
}
