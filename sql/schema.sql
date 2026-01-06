-- Database Schema for Proxy Teacher Allocation System

-- 1. Sections (KG, Primary, High School)
CREATE TABLE IF NOT EXISTS sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL, -- e.g., 'Primary', 'High School'
    priority INT NOT NULL DEFAULT 1 -- 1 = highest priority for proxy assignment
) ENGINE=InnoDB;

-- 2. Teachers
CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    empcode VARCHAR(20) NULL COMMENT 'Employee code from eTime Office biometric system',
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_empcode (empcode)
) ENGINE=InnoDB;

-- 3. Classes (Standard & Division)
CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    standard VARCHAR(10) NOT NULL, -- e.g., '10', '9', '5'
    division VARCHAR(50) NOT NULL, -- e.g., 'A', 'B', 'Commerce', 'Science'
    section_id INT NOT NULL,
    FOREIGN KEY (section_id) REFERENCES sections(id)
) ENGINE=InnoDB;

-- 4. Subjects
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- 5. Teacher Subjects (Pivot Table for Many-to-Many)
CREATE TABLE IF NOT EXISTS teacher_subjects (
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    PRIMARY KEY (teacher_id, subject_id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. Timetable (The schedule)
CREATE TABLE IF NOT EXISTS timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '1=Monday, 7=Sunday',
    period_no INT NOT NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    UNIQUE KEY unique_schedule (teacher_id, day_of_week, period_no), -- No teacher can be in 2 places
    UNIQUE KEY unique_class_schedule (class_id, day_of_week, period_no) -- No class can have 2 teachers
) ENGINE=InnoDB;

-- 7. Teacher Attendance
CREATE TABLE IF NOT EXISTS teacher_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('Present', 'Absent') DEFAULT 'Present',
    source ENUM('API', 'Excel') DEFAULT 'API',
    locked TINYINT(1) DEFAULT 0 COMMENT 'If 1, cannot be modified automatically',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    UNIQUE KEY unique_daily_attendance (teacher_id, date)
) ENGINE=InnoDB;

-- 8. Proxy Assignments
CREATE TABLE IF NOT EXISTS proxy_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    absent_teacher_id INT NOT NULL,
    proxy_teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    period_no INT NOT NULL,
    mode ENUM('AUTO', 'MANUAL') DEFAULT 'AUTO',
    rule_applied VARCHAR(255) COMMENT 'Explanation of which rule selected this teacher',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (absent_teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (proxy_teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (class_id) REFERENCES classes(id),
    UNIQUE KEY unique_proxy (date, proxy_teacher_id, period_no) -- Use indexes to prevent double booking
) ENGINE=InnoDB;

-- 9. Proxy Audit Logs (History of actions)
CREATE TABLE IF NOT EXISTS proxy_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proxy_assignment_id INT,
    action VARCHAR(50) NOT NULL COMMENT 'CREATED, UPDATED, DELETED, MANUAL_OVERRIDE',
    performed_by VARCHAR(50) DEFAULT 'SYSTEM',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (proxy_assignment_id) REFERENCES proxy_assignments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 10. Users (Authentication)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('ADMIN', 'STAFF') DEFAULT 'ADMIN',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 11. API Sync Log (Track eTime Office API fetches)
CREATE TABLE IF NOT EXISTS api_sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sync_date DATE NOT NULL,
    fetch_type ENUM('DAILY', 'INCREMENTAL') DEFAULT 'DAILY',
    status ENUM('SUCCESS', 'FAILED') NOT NULL,
    records_processed INT DEFAULT 0,
    last_record VARCHAR(50) NULL COMMENT 'For incremental sync tracking (e.g., 092020$456)',
    error_message TEXT NULL,
    synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed Default Admin if not exists (Password: admin123)
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi (This is just an example hash, PHP password_hash will vary)
-- We will handle seeding in setup script usually, but here is a safe insert ignore for a basic admin
-- INSERT IGNORE INTO users (username, password_hash) VALUES ('admin', '$2y$10$GsX5f...example...');
