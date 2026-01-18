<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function login($credential, $password) {
        // Rate limiting: Track failed login attempts
        $this->initRateLimiting();
        
        if ($this->isRateLimited()) {
            $minutesLeft = ceil(($_SESSION['login_lockout_until'] - time()) / 60);
            return ['success' => false, 'message' => "Too many failed attempts. Try again in {$minutesLeft} minutes."];
        }
        
        // Allow login by Username OR Email
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$credential, $credential]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
             $this->clearFailedAttempts();
             
             // Prevent session fixation attacks
             session_regenerate_id(true);
             
             $_SESSION['user_id'] = $user['id'];
             $_SESSION['username'] = $user['username'];
             $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
             $_SESSION['role'] = $user['role'];
             return true;
        }
        
        $this->recordFailedAttempt();
        return false;
    }
    
    private function initRateLimiting() {
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_first_attempt'] = time();
        }
    }
    
    private function isRateLimited() {
        if (isset($_SESSION['login_lockout_until']) && $_SESSION['login_lockout_until'] > time()) {
            return true;
        }
        
        // Reset if lockout expired
        if (isset($_SESSION['login_lockout_until']) && $_SESSION['login_lockout_until'] <= time()) {
            $this->clearFailedAttempts();
        }
        
        return false;
    }
    
    private function recordFailedAttempt() {
        $_SESSION['login_attempts']++;
        
        // Lock account after 5 failed attempts
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_lockout_until'] = time() + (15 * 60); // 15 minutes
        }
    }
    
    private function clearFailedAttempts() {
        unset($_SESSION['login_attempts']);
        unset($_SESSION['login_first_attempt']);
        unset($_SESSION['login_lockout_until']);
    }

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
