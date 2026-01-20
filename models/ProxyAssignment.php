<?php
require_once __DIR__ . '/../config/database.php';

class ProxyAssignment {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function assign($date, $absentTeacherId, $proxyTeacherId, $classId, $periodNo, $mode = 'AUTO', $ruleApplied = '', $subjectId = null) {
        $startedTransaction = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $startedTransaction = true;
            }

            $sql = "DELETE FROM proxy_assignments WHERE date = ? AND absent_teacher_id = ? AND class_id = ? AND period_no = ?";
            $params = [$date, $absentTeacherId, $classId, $periodNo];
            
            if ($subjectId) {
                $sql .= " AND subject_id = ?";
                $params[] = $subjectId;
            }

            $deleteStmt = $this->pdo->prepare($sql);
            $deleteStmt->execute($params);

            $stmt = $this->pdo->prepare("
                INSERT INTO proxy_assignments (date, absent_teacher_id, proxy_teacher_id, class_id, period_no, mode, rule_applied, subject_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$date, $absentTeacherId, $proxyTeacherId, $classId, $periodNo, $mode, $ruleApplied, $subjectId]);
            $assignmentId = $this->pdo->lastInsertId();

            $source = ($mode === 'MANUAL') ? 'ADMIN' : 'SYSTEM';
            $logStmt = $this->pdo->prepare("
                INSERT INTO proxy_audit_logs (proxy_assignment_id, action, performed_by, notes)
                VALUES (?, 'CREATED', ?, ?)
            ");
            $logStmt->execute([$assignmentId, $source, "Assigned proxy via $mode using rule: $ruleApplied"]);

            if ($startedTransaction) {
                $this->pdo->commit();
            }
            return $assignmentId;
        } catch (Exception $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function isAssignedProxy($teacherId, $date, $periodNo) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM proxy_assignments 
            WHERE proxy_teacher_id = ? AND date = ? AND period_no = ?
        ");
        $stmt->execute([$teacherId, $date, $periodNo]);
        return $stmt->fetchColumn() > 0;
    }

    public function getProxyCountForDay($teacherId, $date) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM proxy_assignments 
            WHERE proxy_teacher_id = ? AND date = ?
        ");
        $stmt->execute([$teacherId, $date]);
        return (int)$stmt->fetchColumn();
    }

    public function getProxyCountForWeek($teacherId, $startDate, $endDate) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM proxy_assignments 
            WHERE proxy_teacher_id = ? AND date BETWEEN ? AND ?
        ");
        $stmt->execute([$teacherId, $startDate, $endDate]);
        return (int)$stmt->fetchColumn();
    }

    public function deleteAllForDate($date) {
        $startedTransaction = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $startedTransaction = true;
            }
            
            $stmt = $this->pdo->prepare("DELETE FROM proxy_assignments WHERE date = ?");
            $stmt->execute([$date]);
            
            $this->pdo->exec("DELETE FROM proxy_audit_logs WHERE proxy_assignment_id NOT IN (SELECT id FROM proxy_assignments)");
            
            if ($startedTransaction) {
                $this->pdo->commit();
            }
            return true;
        } catch (Exception $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function getAssignmentsForDate($date) {
        $stmt = $this->pdo->prepare("
            SELECT absent_teacher_id, proxy_teacher_id, class_id, period_no, subject_id 
            FROM proxy_assignments 
            WHERE date = ?
        ");
        $stmt->execute([$date]);
        $assignments = $stmt->fetchAll();
        
        $indexed = [];
        foreach ($assignments as $a) {
            // Key format: absent_teacher_id|period_no|class_id|subject_id
            $key = $a['absent_teacher_id'] . '|' . $a['period_no'] . '|' . $a['class_id'];
            if ($a['subject_id']) {
                $key .= '|' . $a['subject_id'];
            }
            $indexed[$key] = $a['proxy_teacher_id'];
        }
        return $indexed;
    }
    public function getReportData($filters) {
        $sql = "SELECT pa.*, 
                       t1.name as absent_teacher_name, t1.empcode as absent_teacher_code,
                       t2.name as proxy_teacher_name, t2.empcode as proxy_teacher_code,
                       CONCAT(c.standard, '-', c.division, IF(sec.name IS NOT NULL, CONCAT(' (', sec.name, ')'), '')) as class_name,
                       s.name as subject_name
                FROM proxy_assignments pa
                LEFT JOIN teachers t1 ON pa.absent_teacher_id = t1.id
                LEFT JOIN teachers t2 ON pa.proxy_teacher_id = t2.id
                LEFT JOIN classes c ON pa.class_id = c.id
                LEFT JOIN sections sec ON c.section_id = sec.id
                LEFT JOIN subjects s ON pa.subject_id = s.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $sql .= " AND pa.date BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
        } elseif (!empty($filters['date'])) {
            $sql .= " AND pa.date = ?";
            $params[] = $filters['date'];
        }
        
        if (!empty($filters['teacher_id'])) {
            // Teacher wise: show where they were the PROXY teacher (work done)
            $sql .= " AND pa.proxy_teacher_id = ?";
            $params[] = $filters['teacher_id'];
        }
        
        if (!empty($filters['class_id'])) {
            $sql .= " AND pa.class_id = ?";
            $params[] = $filters['class_id'];
        }
        
        $sql .= " ORDER BY pa.date ASC, t1.name ASC, pa.period_no ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
