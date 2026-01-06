-- SAFER CLEANUP: Only remove proxy assignments and teachers
-- Keeps sections, classes, and subjects intact
-- Run this if you want to preserve your master data structure

-- Step 1: Remove all proxy audit logs
DELETE FROM proxy_audit_logs;

-- Step 2: Remove all proxy assignments
DELETE FROM proxy_assignments;

-- Step 3: Remove all teacher attendance records
DELETE FROM teacher_attendance;

-- Step 4: Remove all timetable entries
DELETE FROM timetable;

-- Step 5: (Skipped) teacher_subjects table removed from schema
-- DELETE FROM teacher_subjects;

-- Step 6: Remove all teachers
DELETE FROM teachers;

-- Step 7: Reset auto-increment counters for cleaned tables
ALTER TABLE proxy_audit_logs AUTO_INCREMENT = 1;
ALTER TABLE proxy_assignments AUTO_INCREMENT = 1;
ALTER TABLE teacher_attendance AUTO_INCREMENT = 1;
ALTER TABLE timetable AUTO_INCREMENT = 1;
ALTER TABLE teachers AUTO_INCREMENT = 1;

-- Note: This preserves sections, classes, subjects, and users
SELECT 'Teacher data cleanup completed! Sections, Classes, and Subjects preserved.' as status;
