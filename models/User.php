<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function login($credential, $password) {
        // Allow login by Username OR Email
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$credential, $credential]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
             // Set Session
             $_SESSION['user_id'] = $user['id'];
             $_SESSION['username'] = $user['username'];
             $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
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

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT id, username, full_name, email, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function updateProfile($id, $username, $fullName, $email) {
        $stmt = $this->pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ? WHERE id = ?");
        return $stmt->execute([$username, $fullName, $email, $id]);
    }

    public function updatePassword($id, $newHash) {
        $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        return $stmt->execute([$newHash, $id]);
    }

    public function verifyPassword($id, $password) {
        $stmt = $this->pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();
        return password_verify($password, $hash);
    }
    public function getUserByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function setResetToken($email, $token) {
        // Expires in 1 hour
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $this->pdo->prepare("UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE email = ?");
        return $stmt->execute([$token, $expires, $email]);
    }

    public function getUserByResetToken($token) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_expires_at > NOW()");
        $stmt->execute([$token]);
        return $stmt->fetch();
    }

    public function clearResetToken($id) {
        $stmt = $this->pdo->prepare("UPDATE users SET reset_token = NULL, reset_expires_at = NULL WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
