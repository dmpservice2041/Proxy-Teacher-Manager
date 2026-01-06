<?php
require_once __DIR__ . '/../config/database.php';

class ProxyAssignment {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // Create a new proxy assignment
    public function assign($date, $absentTeacherId, $proxyTeacherId, $classId, $periodNo, $mode = 'AUTO', $ruleApplied = '') {
        try {
            $this->pdo->beginTransaction();

            // Delete any existing assignment for this specific slot to prevent duplicates/conflicts
            $deleteStmt = $this->pdo->prepare("
                DELETE FROM proxy_assignments 
                WHERE date = ? AND absent_teacher_id = ? AND class_id = ? AND period_no = ?
            ");
            $deleteStmt->execute([$date, $absentTeacherId, $classId, $periodNo]);

            // Insert assignment
            $stmt = $this->pdo->prepare("
                INSERT INTO proxy_assignments (date, absent_teacher_id, proxy_teacher_id, class_id, period_no, mode, rule_applied)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$date, $absentTeacherId, $proxyTeacherId, $classId, $periodNo, $mode, $ruleApplied]);
            $assignmentId = $this->pdo->lastInsertId();

            // Log action
            $source = ($mode === 'MANUAL') ? 'ADMIN' : 'SYSTEM';
            $logStmt = $this->pdo->prepare("
                INSERT INTO proxy_audit_logs (proxy_assignment_id, action, performed_by, notes)
                VALUES (?, 'CREATED', ?, ?)
            ");
            $logStmt->execute([$assignmentId, $source, "Assigned proxy via $mode using rule: $ruleApplied"]);

            $this->pdo->commit();
            return $assignmentId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Check if a teacher is already assigned as a proxy in a specific slot
    public function isAssignedProxy($teacherId, $date, $periodNo) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM proxy_assignments 
            WHERE proxy_teacher_id = ? AND date = ? AND period_no = ?
        ");
        $stmt->execute([$teacherId, $date, $periodNo]);
        return $stmt->fetchColumn() > 0;
    }

    // Get number of proxies assigned to a teacher on a specific date
    public function getProxyCountForDay($teacherId, $date) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM proxy_assignments 
            WHERE proxy_teacher_id = ? AND date = ?
        ");
        $stmt->execute([$teacherId, $date]);
        return (int)$stmt->fetchColumn();
    }

    // Get number of proxies assigned to a teacher in a week (approximate by date range)
    public function getProxyCountForWeek($teacherId, $startDate, $endDate) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM proxy_assignments 
            WHERE proxy_teacher_id = ? AND date BETWEEN ? AND ?
        ");
        $stmt->execute([$teacherId, $startDate, $endDate]);
        return (int)$stmt->fetchColumn();
    }

    // Delete all assignments for a date
    public function deleteAllForDate($date) {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("DELETE FROM proxy_assignments WHERE date = ?");
            $stmt->execute([$date]);
            
            // Also cleanup audit logs that have no corresponding assignment
            $this->pdo->exec("DELETE FROM proxy_audit_logs WHERE proxy_assignment_id NOT IN (SELECT id FROM proxy_assignments)");
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Get all assignments for a date indexed by slot key
    public function getAssignmentsForDate($date) {
        $stmt = $this->pdo->prepare("
            SELECT absent_teacher_id, proxy_teacher_id, class_id, period_no 
            FROM proxy_assignments 
            WHERE date = ?
        ");
        $stmt->execute([$date]);
        $assignments = $stmt->fetchAll();
        
        $indexed = [];
        foreach ($assignments as $a) {
            $key = $a['absent_teacher_id'] . '|' . $a['period_no'] . '|' . $a['class_id'];
            $indexed[$key] = $a['proxy_teacher_id'];
        }
        return $indexed;
    }
}
