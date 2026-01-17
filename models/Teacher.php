<?php
require_once __DIR__ . '/../config/database.php';

class Teacher {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // Fetch all active teachers
    // Fetch all active teachers (with section_ids)
    public function getAllActive() {
        $stmt = $this->pdo->prepare("
             SELECT t.*, GROUP_CONCAT(ts.section_id) as section_ids
             FROM teachers t
             LEFT JOIN teacher_sections ts ON t.id = ts.teacher_id
             WHERE t.is_active = 1
             GROUP BY t.id
             ORDER BY CAST(t.empcode AS UNSIGNED), t.empcode
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getAllWithDetails() {
         $stmt = $this->pdo->query("
            SELECT t.*, GROUP_CONCAT(s.id) as section_ids, GROUP_CONCAT(s.name SEPARATOR ', ') as section_names
            FROM teachers t
            LEFT JOIN teacher_sections ts ON t.id = ts.teacher_id
            LEFT JOIN sections s ON ts.section_id = s.id
            GROUP BY t.id
            ORDER BY t.name
        ");
        return $stmt->fetchAll();
    }

    // Find teacher by ID
    public function find($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM teachers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // Get subjects taught by a teacher
    public function getSubjects($teacherId) {
        $stmt = $this->pdo->prepare("
            SELECT s.* 
            FROM subjects s
            JOIN teacher_subjects ts ON s.id = ts.subject_id
            WHERE ts.teacher_id = ?
        ");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll();
    }
    
    // Get Section details for a teacher
    public function getSections($teacherId) {
        $stmt = $this->pdo->prepare("
            SELECT s.* 
            FROM sections s
            JOIN teacher_sections ts ON s.id = ts.section_id
            WHERE ts.teacher_id = ?
        ");
        $stmt->execute([$teacherId]);
        return $stmt->fetchAll();
    }

    public function add($name, $empcode = null, $sectionIds = []) {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO teachers (name, empcode) VALUES (?, ?)");
            $stmt->execute([$name, $empcode]);
            $teacherId = $this->pdo->lastInsertId();

            if (!empty($sectionIds)) {
                $stmtSec = $this->pdo->prepare("INSERT INTO teacher_sections (teacher_id, section_id) VALUES (?, ?)");
                foreach ($sectionIds as $sid) {
                    $stmtSec->execute([$teacherId, $sid]);
                }
            }
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function assignSubject($teacherId, $subjectId) {
         $stmt = $this->pdo->prepare("INSERT IGNORE INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)");
         return $stmt->execute([$teacherId, $subjectId]);
    }

    public function update($id, $name, $empcode, $isActive, $sectionIds = []) {
        $this->pdo->beginTransaction();
        try {
            // Update Teacher Basic Info
            $stmt = $this->pdo->prepare("
                UPDATE teachers 
                SET name = ?, empcode = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $empcode, $isActive, $id]);

            // Update Sections (Delete all and re-add)
            $delStmt = $this->pdo->prepare("DELETE FROM teacher_sections WHERE teacher_id = ?");
            $delStmt->execute([$id]);

            if (!empty($sectionIds)) {
                $stmtSec = $this->pdo->prepare("INSERT INTO teacher_sections (teacher_id, section_id) VALUES (?, ?)");
                foreach ($sectionIds as $sid) {
                    $stmtSec->execute([$id, $sid]);
                }
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete($id) {
        // Check if teacher has any proxy assignments (as absent or proxy teacher)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM proxy_assignments 
            WHERE absent_teacher_id = ? OR proxy_teacher_id = ?
        ");
        $stmt->execute([$id, $id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            throw new Exception(
                "Cannot delete teacher: This teacher has {$result['count']} historical proxy assignment(s). " .
                "Please use the 'Toggle Active' feature instead to deactivate this teacher."
            );
        }
        
        // Check if teacher has timetable entries
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM timetable WHERE teacher_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            throw new Exception(
                "Cannot delete teacher: This teacher has {$result['count']} timetable entry(ies). " .
                "Please remove timetable entries first or deactivate the teacher instead."
            );
        }
        
        // Check if teacher has attendance records
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM teacher_attendance WHERE teacher_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            throw new Exception(
                "Cannot delete teacher: This teacher has {$result['count']} attendance record(s). " .
                "Please use the 'Toggle Active' feature instead to deactivate this teacher."
            );
        }
        
        // Safe to delete - no dependencies
        // This will cascade delete teacher_subjects due to FK constraint
        $stmt = $this->pdo->prepare("DELETE FROM teachers WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function toggleActive($id) {
        $stmt = $this->pdo->prepare("UPDATE teachers SET is_active = NOT is_active WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Find teacher by employee code (for API integration)
    public function findByEmpcode($empcode) {
        $stmt = $this->pdo->prepare("SELECT * FROM teachers WHERE empcode = ?");
        $stmt->execute([$empcode]);
        return $stmt->fetch();
    }

}
