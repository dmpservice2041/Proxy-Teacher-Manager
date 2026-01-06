-- Create junction table for Teacher-Section (Many-to-Many)
CREATE TABLE IF NOT EXISTS teacher_sections (
    teacher_id INT NOT NULL,
    section_id INT NOT NULL,
    PRIMARY KEY (teacher_id, section_id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Migrate existing data (if any)
INSERT IGNORE INTO teacher_sections (teacher_id, section_id)
SELECT id, section_id FROM teachers WHERE section_id IS NOT NULL;

-- Drop the single column (We will do this later manually or assume it's unused, to be safe I'll leave it but stop using it)
-- ALTER TABLE teachers DROP COLUMN section_id; 

SELECT 'teacher_sections table created and migrated' as status;
