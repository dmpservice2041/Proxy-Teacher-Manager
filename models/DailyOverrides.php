<?php
require_once __DIR__ . '/../config/database.php';

class DailyOverrides {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function add($date, $type, $targetId, $period = null, $reason = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO daily_overrides (date, type, target_id, period_no, reason)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$date, $type, $targetId, $period, $reason]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM daily_overrides WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getAllForDate($date) {
        $stmt = $this->pdo->prepare("
            SELECT do.*, 
                   CASE 
                       WHEN do.type = 'TEACHER_DUTY' THEN t.name 
                       WHEN do.type = 'CLASS_ABSENT' THEN CONCAT(c.standard, '-', c.division, ' (', s.name, ')')
                   END as target_name
            FROM daily_overrides do
            LEFT JOIN teachers t ON do.type = 'TEACHER_DUTY' AND do.target_id = t.id
            LEFT JOIN classes c ON do.type = 'CLASS_ABSENT' AND do.target_id = c.id
            LEFT JOIN sections s ON c.section_id = s.id
            WHERE do.date = ?
            ORDER BY do.type, do.period_no
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }

    // Returns: ['TEACHER_DUTY' => [TID => [Period => true]], 'CLASS_ABSENCE' => [CID => [Period => true]]]
    public function getOverridesMap($date) {
        $stmt = $this->pdo->prepare("SELECT * FROM daily_overrides WHERE date = ?");
        $stmt->execute([$date]);
        $rows = $stmt->fetchAll();
        
        $map = [
            'TEACHER_DUTY' => [],
            'CLASS_ABSENT' => []
        ];
        
        foreach ($rows as $row) {
            $type = $row['type'];
            $tid = $row['target_id'];
            $p = $row['period_no']; // NULL means all day
            
            if (!isset($map[$type][$tid])) {
                $map[$type][$tid] = [];
            }
            
            $key = $p === null ? 0 : $p;
            $map[$type][$tid][$key] = true;
        }
        return $map;
    }
}
