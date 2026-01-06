-- Migration: Simplify Teacher Structure (Fixed)
-- Remove section, max_proxy columns and teacher_subjects table

-- Step 1: Drop teacher_subjects table first (no dependencies)
DROP TABLE IF EXISTS teacher_subjects;

-- Step 2: Drop foreign key constraint from teachers table
-- Find the constraint name first
SET @constraint_name = (
    SELECT CONSTRAINT_NAME 
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'teachers' 
    AND COLUMN_NAME = 'section_id' 
    AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1
);

-- Drop the foreign key if it exists
SET @sql = IF(@constraint_name IS NOT NULL, 
    CONCAT('ALTER TABLE teachers DROP FOREIGN KEY ', @constraint_name), 
    'SELECT "No foreign key to drop"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Now drop the columns
ALTER TABLE teachers 
    DROP COLUMN IF EXISTS section_id,
    DROP COLUMN IF EXISTS max_proxy_per_day,
    DROP COLUMN IF EXISTS max_proxy_per_week;

SELECT 'Teacher structure simplified successfully!' as status;
