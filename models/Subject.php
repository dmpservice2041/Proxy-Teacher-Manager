<?php
require_once __DIR__ . '/../config/database.php';

class Subject {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM subjects ORDER BY name");
        return $stmt->fetchAll();
    }

    public function add($name) {
        $stmt = $this->pdo->prepare("INSERT INTO subjects (name) VALUES (?)");
        return $stmt->execute([$name]);
    }

    public function update($id, $name) {
        $stmt = $this->pdo->prepare("UPDATE subjects SET name = ? WHERE id = ?");
        return $stmt->execute([$name, $id]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM subjects WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function find($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM subjects WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}
