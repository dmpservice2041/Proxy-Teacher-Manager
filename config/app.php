<?php
// Global Application Configuration

// Load database config and try to fetch school name from settings
require_once __DIR__ . '/database.php';
try {
    $pdo = Database::getInstance()->getConnection();
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

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
