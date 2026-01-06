<?php
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getInstance()->getConnection();

echo "Seeding Database...\n";

// Clear existing data
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
$tables = ['proxy_audit_logs', 'proxy_assignments', 'teacher_attendance', 'timetable', 'teacher_subjects', 'teachers', 'subjects', 'classes', 'sections'];
foreach ($tables as $table) {
    $pdo->exec("TRUNCATE TABLE $table");
}
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");

// 1. Sections
$pdo->exec("
    INSERT INTO sections (id, name, priority) VALUES 
    (1, 'High School', 1),
    (2, 'Primary', 2),
    (3, 'KG', 3)
");

// 2. Subjects
$pdo->exec("
    INSERT INTO subjects (id, name) VALUES 
    (1, 'Maths'), (2, 'Science'), (3, 'English'), (4, 'History'), (5, 'Geography')
");

// 3. Classes
$pdo->exec("
    INSERT INTO classes (id, standard, division, section_id) VALUES 
    (1, '10', 'A', 1), (2, '10', 'B', 1),
    (3, '9', 'A', 1), (4, '9', 'B', 1),
    (5, '5', 'A', 2), (6, '5', 'B', 2)
");

// 4. Teachers
// Teacher 1: High School, Maths (Busy)
// Teacher 2: High School, Science (Absent)
// Teacher 3: High School, Maths (Free - Best Candidate)
// Teacher 4: Primary, Science (Available but lower priority section)
$pdo->exec("
    INSERT INTO teachers (id, name, section_id, max_proxy_per_day, max_proxy_per_week) VALUES 
    (1, 'John Doe (HS-Math)', 1, 2, 5),
    (2, 'Jane Smith (HS-Sci)', 1, 2, 5),
    (3, 'Bob Wilson (HS-Math)', 1, 2, 5),
    (4, 'Alice Brown (Pri-Sci)', 2, 2, 5)
");

// Teacher Subjects
$pdo->exec("
    INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES 
    (1, 1), -- John teaches Maths
    (2, 2), -- Jane teaches Science
    (3, 1), -- Bob teaches Maths
    (4, 2)  -- Alice teaches Science
");

// 5. Timetable (Match Today's Day of Week)
$dayOfWeek = date('N');
$pdo->exec("
    INSERT INTO timetable (teacher_id, class_id, subject_id, day_of_week, period_no) VALUES 
    (2, 1, 2, $dayOfWeek, 1), -- Jane: 10-A Science, Period 1 (NEEDS PROXY)
    (1, 2, 1, $dayOfWeek, 1)  -- John: 10-B Maths, Period 1 (BUSY)
    -- Bob is FREE at Period 1
    -- Alice is FREE at Period 1
");

// 6. Attendance (For Today - assumming script run with today's date or specific test date)
// We will set this dynamically in the test scenario or assume the engine script sets it / reads it.
// For now, let's just insert one 'Absent' record for Jane for '2025-01-01' to test.
$today = date('Y-m-d');
$pdo->exec("
    INSERT INTO teacher_attendance (teacher_id, date, status) VALUES 
    (2, '$today', 'Absent'), -- Jane is Absent
    (1, '$today', 'Present'),
    (3, '$today', 'Present'),
    (4, '$today', 'Present')
");

echo "Seeding Complete. \n";
echo "Scenario Setup: \n";
echo " - Date: $today \n";
echo " - Absent: Jane Smith (HS-Sci) \n";
echo " - Lecture: Period 1, Class 10-A, Science \n";
echo " - Candidates: \n";
echo "    1. John Doe (HS-Math): BUSY (Teaching 10-B) \n";
echo "    2. Bob Wilson (HS-Math): FREE, Same Section (Priority 1), Same Subject? No (Math vs Sci) \n";
echo "    3. Alice Brown (Pri-Sci): FREE, Diff Section (Priority 2), Same Subject (Sci) \n";
echo "Logic Expectation: \n";
echo " - Teacher 1 (John) is disqualified (Busy). \n";
echo " - Teacher 2 (Jane) is Absent. \n";
echo " - Comparing Bob vs Alice: \n";
echo "   - Rule 1 (Same Section): Bob is HS (Same), Alice is Primary. Bob wins. \n";
echo "   - Rule 2 (Priority): HS (1) < Pri (2). Bob wins. \n";
echo "   -> Result should be Bob Wilson. \n";
