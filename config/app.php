<?php
// Global Application Configuration

// Load database config and try to fetch school name from settings
require_once __DIR__ . '/database.php';
try {
    $pdo = Database::getInstance()->getConnection();
    // Use proper quoting for table names if needed, but standard SQL usually fine
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'school_name'");
    $stmt->execute();
    $dbSchoolName = $stmt->fetchColumn();
    define('SCHOOL_NAME', $dbSchoolName ?: 'Springfield High School');
} catch (Exception $e) {
    define('SCHOOL_NAME', 'Springfield High School');
}
define('APP_NAME', 'Proxy Teacher Management System');
define('BASE_URL', 'http://localhost/Proxy_teacher/proxy-teacher/'); // Update based on deployment

// Timezone
date_default_timezone_set('Asia/Kolkata'); // Or user's time zone

// Secure Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    // Prevent session fixation and hijacking
    ini_set('session.cookie_httponly', '1');        // Prevent JavaScript access to session cookie
    ini_set('session.cookie_samesite', 'Strict');   // Prevent CSRF via session cookie
    ini_set('session.use_strict_mode', '1');        // Reject uninitialized session IDs
    ini_set('session.use_only_cookies', '1');       // Only use cookies for sessions
    
    // Enable in production with HTTPS:
    // ini_set('session.cookie_secure', '1');       // Only send cookie over HTTPS
    
    session_start();
}

/**
 * CSRF Protection Functions
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function requireCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            http_response_code(403);
            die('CSRF validation failed. Please refresh and try again.');
        }
    }
}

/**
 * Centralized Error Logging
 * Logs detailed errors to file, returns generic message to user
 */
function logError($exception, $context = '') {
    $logFile = __DIR__ . '/../logs/error.log';
    $logDir = dirname($logFile);
    
    // Ensure log directory exists
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $message = $exception->getMessage();
    $file = $exception->getFile();
    $line = $exception->getLine();
    $trace = $exception->getTraceAsString();
    
    $logEntry = "[{$timestamp}] {$context}\n";
    $logEntry .= "Error: {$message}\n";
    $logEntry .= "File: {$file}:{$line}\n";
    $logEntry .= "Stack Trace:\n{$trace}\n";
    $logEntry .= str_repeat('-', 80) . "\n";
    
    error_log($logEntry, 3, $logFile);
    
    // Return generic error message for user
    return "An error occurred. Please try again or contact support.";
}
