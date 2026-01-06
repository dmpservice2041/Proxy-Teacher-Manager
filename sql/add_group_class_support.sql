-- Migration: Add Group Class Support to Timetable
-- This allows multiple subjects for the same class in the same period (e.g., Hindi and Sanskrit groups)

-- Step 1: Add group_name column to timetable table
ALTER TABLE timetable 
ADD COLUMN group_name VARCHAR(50) NULL DEFAULT NULL 
COMMENT 'Group name for group classes (e.g., "Hindi", "Sanskrit", "Group 1", "Group 2")';

-- Step 2: Drop the old unique constraint that prevented multiple subjects per class/period
ALTER TABLE timetable 
DROP INDEX unique_class_schedule;

-- Step 3: Add new unique constraint that includes group_name
-- This allows multiple entries for same class/period but with different group names
ALTER TABLE timetable 
ADD UNIQUE KEY unique_class_schedule_group (class_id, day_of_week, period_no, group_name);

-- Step 4: For existing entries without groups, set group_name to NULL (which is allowed)
-- This maintains backward compatibility with existing data

