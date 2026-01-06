-- Re-add section_id to teachers for Proxy Department restriction
ALTER TABLE teachers
ADD COLUMN section_id INT NULL AFTER name,
ADD FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL;

SELECT 'Section_id re-added to teachers' as status;
