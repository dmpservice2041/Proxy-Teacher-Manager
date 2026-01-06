-- Migration: Add InTime and OutTime to teacher_attendance
ALTER TABLE teacher_attendance
ADD COLUMN in_time VARCHAR(10) NULL AFTER status,
ADD COLUMN out_time VARCHAR(10) NULL AFTER in_time;

SELECT 'Attendance table updated with InTime/OutTime columns' as status;
