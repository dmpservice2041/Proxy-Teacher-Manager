<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function login($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
             // Set Session
             $_SESSION['user_id'] = $user['id'];
             $_SESSION['username'] = $user['username'];
             $_SESSION['role'] = $user['role'];
             return true;
        }
        return false;
    }

    // Create a new user (Utility for initial setup)
    public function create($username, $password, $role = 'ADMIN') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        return $stmt->execute([$username, $hash, $role]);
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}
