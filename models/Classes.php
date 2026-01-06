<?php
require_once __DIR__ . '/../config/database.php';

class Classes {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getAll() {
        $stmt = $this->pdo->query("
            SELECT c.*, s.name as section_name 
            FROM classes c
            JOIN sections s ON c.section_id = s.id
            ORDER BY c.standard, c.division
        ");
        return $stmt->fetchAll();
    }

    public function add($standard, $division, $sectionId) {
        $stmt = $this->pdo->prepare("INSERT INTO classes (standard, division, section_id) VALUES (?, ?, ?)");
        return $stmt->execute([$standard, $division, $sectionId]);
    }

    public function update($id, $standard, $division, $sectionId) {
        $stmt = $this->pdo->prepare("UPDATE classes SET standard = ?, division = ?, section_id = ? WHERE id = ?");
        return $stmt->execute([$standard, $division, $sectionId, $id]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM classes WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function find($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM classes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}
// Note: Class name is 'Classes' to avoid reserved keyword 'Class'
