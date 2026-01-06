<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    // 1. Connect without DB
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Create DB
    echo "Creating Database 'proxy_teacher_db'...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS proxy_teacher_db");
    $pdo->exec("USE proxy_teacher_db");

    // 3. Load Schema
    echo "Loading Schema...\n";
    $sql = file_get_contents(__DIR__ . '/../sql/schema.sql');
    
    // Split SQL by semicolon to execute statements individually as PDO::exec might not handle multiple
    // But usually simple dumps work if no delimiter issues.
    // Let's try direct execution first, if fails, we split.
    // Ideally, schema.sql has simple CREATE TABLE statements.
    
    $pdo->exec($sql);
    echo "Database setup complete.\n";

    // 4. Seed Admin
    echo "Seeding Default Admin (admin / admin123)...\n";
    // Using a manual insert here to avoid dependency issues if User model isn't fully ready or compatible with this script context,
    // but better to use standard hash.
    $passHash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT IGNORE INTO users (username, password_hash, role) VALUES ('admin', '$passHash', 'ADMIN')");
    echo "Admin seeded.\n";

} catch (PDOException $e) {
    echo "DB Setup Error: " . $e->getMessage() . "\n";
    exit(1);
}
