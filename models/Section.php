<?php
require_once __DIR__ . '/../config/database.php';

class Section {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function find($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM sections WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    // Get all sections ordered by priority (1 is highest)
    public function getAllOrderedByPriority() {
        $stmt = $this->pdo->query("SELECT * FROM sections ORDER BY priority ASC");
        return $stmt->fetchAll();
    }

    public function add($name, $priority) {
        $stmt = $this->pdo->prepare("INSERT INTO sections (name, priority) VALUES (?, ?)");
        return $stmt->execute([$name, $priority]);
    }

    public function update($id, $name, $priority) {
        $stmt = $this->pdo->prepare("UPDATE sections SET name = ?, priority = ? WHERE id = ?");
        return $stmt->execute([$name, $priority, $id]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM sections WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
