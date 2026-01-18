<?php
require_once __DIR__ . '/../config/database.php';

class BlockedPeriod {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function block($day, $periodNo, $classId = null, $description = null) {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO blocked_periods (day, period_no, class_id, description) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$day, $periodNo, $classId, $description]);
    }

    public function unblock($day, $periodNo, $classId = null) {
        if ($classId === null) {
            $stmt = $this->pdo->prepare("DELETE FROM blocked_periods WHERE day = ? AND period_no = ? AND class_id IS NULL");
            return $stmt->execute([$day, $periodNo]);
        } else {
            $stmt = $this->pdo->prepare("DELETE FROM blocked_periods WHERE day = ? AND period_no = ? AND class_id = ?");
            return $stmt->execute([$day, $periodNo, $classId]);
        }
    }

    // If $classId is provided, checks if Global is blocked OR Class is blocked.
    public function isBlocked($day, $periodNo, $classId = null) {
        if ($classId) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM blocked_periods WHERE day = ? AND period_no = ? AND (class_id IS NULL OR class_id = ?)");
            $stmt->execute([$day, $periodNo, $classId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT 1 FROM blocked_periods WHERE day = ? AND period_no = ? AND class_id IS NULL");
            $stmt->execute([$day, $periodNo]);
        }
        return (bool) $stmt->fetchColumn();
    }

    public function getBlockedPeriods($classId = null) {
        if ($classId) {
            $stmt = $this->pdo->prepare("SELECT * FROM blocked_periods WHERE class_id = ? OR class_id IS NULL");
            $stmt->execute([$classId]);
        } else {
            // If fetching for "Global" view, only fetch global blocks? 
            // Let's assume this is for the internal optimization map.
            $stmt = $this->pdo->query("SELECT * FROM blocked_periods");
        }
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Transform to convenient lookup array
        // If class_id provided: [day][period] => true (merged)
        // If no class_id: [day][period][class_id_or_global] => true ??
        
        $lookup = [];
        foreach ($results as $row) {
             if ($classId) {
                 $lookup[$row['day']][$row['period_no']] = true;
             } else {
                 // Return structure [day][period]['global'] = T/F, [day][period]['classes'] = [id1, id2]
                 $cid = $row['class_id'] ?? 'global';
                 $lookup[$row['day']][$row['period_no']][$cid] = true;
             }
        }
        return $lookup;
    }
    
    public function getAllForClass($classId = null) {
        if ($classId === 'global' || $classId === null) {
             $stmt = $this->pdo->prepare("SELECT * FROM blocked_periods WHERE class_id IS NULL");
             $stmt->execute();
        } else {
             $stmt = $this->pdo->prepare("SELECT * FROM blocked_periods WHERE class_id = ?");
             $stmt->execute([$classId]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
