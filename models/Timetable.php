<?php
require_once __DIR__ . '/../config/database.php';

class Timetable {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getTeacherSchedule($teacherId, $dayOfWeek) {
        $hasGroupName = false;
        try {
            $checkStmt = $this->pdo->query("SHOW COLUMNS FROM timetable LIKE 'group_name'");
            $hasGroupName = $checkStmt->rowCount() > 0;
        } catch (Exception $e) {
        }
        
        $orderBy = $hasGroupName ? "ORDER BY t.period_no ASC, t.group_name ASC" : "ORDER BY t.period_no ASC";
        
        $stmt = $this->pdo->prepare("
            SELECT t.*, c.standard, c.division, s.name as subject_name
            FROM timetable t
            JOIN classes c ON t.class_id = c.id
            JOIN subjects s ON t.subject_id = s.id
            WHERE t.teacher_id = ? AND t.day_of_week = ?
            {$orderBy}
        ");
        $stmt->execute([$teacherId, $dayOfWeek]);
        return $stmt->fetchAll();
    }

    public function getClassSchedule($classId, $dayOfWeek) {
        $hasGroupName = false;
        try {
            $checkStmt = $this->pdo->query("SHOW COLUMNS FROM timetable LIKE 'group_name'");
            $hasGroupName = $checkStmt->rowCount() > 0;
        } catch (Exception $e) {
        }
        
        $orderBy = $hasGroupName ? "ORDER BY t.period_no ASC, t.group_name ASC" : "ORDER BY t.period_no ASC";
        
        $stmt = $this->pdo->prepare("
            SELECT t.*, s.name as subject_name, te.name as teacher_name
            FROM timetable t
            JOIN subjects s ON t.subject_id = s.id
            JOIN teachers te ON t.teacher_id = te.id
            WHERE t.class_id = ? AND t.day_of_week = ?
            {$orderBy}
        ");
        $stmt->execute([$classId, $dayOfWeek]);
        return $stmt->fetchAll();
    }

    public function getClassPeriodEntries($classId, $dayOfWeek, $periodNo) {
        $hasGroupName = false;
        try {
            $checkStmt = $this->pdo->query("SHOW COLUMNS FROM timetable LIKE 'group_name'");
            $hasGroupName = $checkStmt->rowCount() > 0;
        } catch (Exception $e) {
        }
        
        $orderBy = $hasGroupName ? "ORDER BY t.group_name ASC" : "ORDER BY t.id ASC";
        
        $stmt = $this->pdo->prepare("
            SELECT t.*, c.standard, c.division, s.name as subject_name, te.name as teacher_name
            FROM timetable t
            JOIN classes c ON t.class_id = c.id
            JOIN subjects s ON t.subject_id = s.id
            JOIN teachers te ON t.teacher_id = te.id
            WHERE t.class_id = ? AND t.day_of_week = ? AND t.period_no = ?
            {$orderBy}
        ");
        $stmt->execute([$classId, $dayOfWeek, $periodNo]);
        return $stmt->fetchAll();
    }

    public function isTeacherBusy($teacherId, $dayOfWeek, $periodNo) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM timetable 
            WHERE teacher_id = ? AND day_of_week = ? AND period_no = ?
        ");
        $stmt->execute([$teacherId, $dayOfWeek, $periodNo]);
        return $stmt->fetchColumn() > 0;
    }

    public function countFreePeriods($teacherId, $dayOfWeek, $totalPeriods = 8) {
        $busyCount = $this->pdo->prepare("
            SELECT COUNT(*) FROM timetable 
            WHERE teacher_id = ? AND day_of_week = ?
        ");
        $busyCount->execute([$teacherId, $dayOfWeek]);
        $busy = $busyCount->fetchColumn();
        return $totalPeriods - $busy;
    }

    public function add($teacherId, $classId, $subjectId, $day, $period, $groupName = null) {
        $hasGroupName = false;
        try {
            $checkStmt = $this->pdo->query("SHOW COLUMNS FROM timetable LIKE 'group_name'");
            $hasGroupName = $checkStmt->rowCount() > 0;
        } catch (Exception $e) {
        }
        
        if ($hasGroupName) {
            $stmt = $this->pdo->prepare("
                INSERT INTO timetable (teacher_id, class_id, subject_id, day_of_week, period_no, group_name)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$teacherId, $classId, $subjectId, $day, $period, $groupName]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO timetable (teacher_id, class_id, subject_id, day_of_week, period_no)
                VALUES (?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$teacherId, $classId, $subjectId, $day, $period]);
        }
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM timetable WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function update($id, $classId, $subjectId, $groupName = null) {
        $hasGroupName = false;
        try {
            $checkStmt = $this->pdo->query("SHOW COLUMNS FROM timetable LIKE 'group_name'");
            $hasGroupName = $checkStmt->rowCount() > 0;
        } catch (Exception $e) {
        }
        
        if ($hasGroupName) {
            $stmt = $this->pdo->prepare("UPDATE timetable SET class_id = ?, subject_id = ?, group_name = ? WHERE id = ?");
            return $stmt->execute([$classId, $subjectId, $groupName, $id]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE timetable SET class_id = ?, subject_id = ? WHERE id = ?");
            return $stmt->execute([$classId, $subjectId, $id]);
        }
    }
    public function getTeacherClasses($teacherId) {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT class_id 
            FROM timetable 
            WHERE teacher_id = ?
        ");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
