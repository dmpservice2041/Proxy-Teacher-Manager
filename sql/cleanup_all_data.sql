-- CLEANUP SCRIPT: Remove all historical data and teachers
-- Run this to start fresh with API-fetched data
-- WARNING: This will delete ALL existing data!

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

-- Step 7: Remove all API sync logs (optional - if you want to clear sync history)
DELETE FROM api_sync_log;

-- Step 8: Reset auto-increment counters (optional - starts IDs from 1 again)
ALTER TABLE proxy_audit_logs AUTO_INCREMENT = 1;
ALTER TABLE proxy_assignments AUTO_INCREMENT = 1;
ALTER TABLE teacher_attendance AUTO_INCREMENT = 1;
ALTER TABLE timetable AUTO_INCREMENT = 1;
ALTER TABLE teachers AUTO_INCREMENT = 1;
ALTER TABLE api_sync_log AUTO_INCREMENT = 1;

-- Cleanup complete! You can now fetch fresh data from the API.
SELECT 'Database cleanup completed successfully!' as status;
