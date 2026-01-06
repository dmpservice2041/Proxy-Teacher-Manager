-- Manually insert teachers missing from API response
-- Based on user provided list

INSERT IGNORE INTO teachers (name, empcode, is_active) VALUES 
('BHUMI GORADIYA', '226', 1),
('BHOOMI KOTHARI', '229', 1);

SELECT 'Missing teachers added successfully' as status;
