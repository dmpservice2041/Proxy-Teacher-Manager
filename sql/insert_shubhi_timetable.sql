-- Insert Timetable for Ms. Shubhi (ID 15)

-- Clear existing entries for this teacher to avoid duplicates/overlap handling issues in this batch script
DELETE FROM timetable WHERE teacher_id = 15;

-- MONDAY (Day 1)
INSERT INTO timetable (teacher_id, day_of_week, period_no, class_id, subject_id) VALUES
(15, 1, 1, 7, 3),   -- English -> 1-Jasmine
(15, 1, 2, 10, 4),  -- Environment -> 1-Tulip
(15, 1, 3, 8, 12),  -- Value Education -> 1-Rose
(15, 1, 4, 16, 5),  -- English Grammar -> 3-Rose
(15, 1, 5, 14, 15), -- Conversation -> 2-Tulip
(15, 1, 6, 13, 5),  -- English Grammar -> 2-Orchid
(15, 1, 8, 9, 18);  -- English Reading -> 1-Orchid

-- TUESDAY (Day 2)
INSERT INTO timetable (teacher_id, day_of_week, period_no, class_id, subject_id) VALUES
(15, 2, 1, 7, 3),   -- English -> 1-Jasmine
(15, 2, 2, 8, 15),  -- Conversation -> 1-Rose
(15, 2, 5, 16, 5),  -- English Grammar -> 3-Rose
(15, 2, 6, 12, 18), -- English Reading -> 2-Rose
(15, 2, 7, 13, 5),  -- English Grammar -> 2-Orchid
(15, 2, 8, 10, 4);  -- Environment -> 1-Tulip

-- WEDNESDAY (Day 3)
INSERT INTO timetable (teacher_id, day_of_week, period_no, class_id, subject_id) VALUES
(15, 3, 1, 7, 19),  -- MPT -> 1-Jasmine
(15, 3, 2, 7, 3),   -- English -> 1-Jasmine
(15, 3, 3, 13, 5),  -- English Grammar -> 2-Orchid
(15, 3, 4, 8, 15),  -- Conversation -> 1-Rose
(15, 3, 5, 12, 16), -- Cursive Writing (CW) -> 2-Rose
(15, 3, 6, 16, 5),  -- English Grammar -> 3-Rose
(15, 3, 8, 10, 4);  -- Environment -> 1-Tulip

-- THURSDAY (Day 4)
INSERT INTO timetable (teacher_id, day_of_week, period_no, class_id, subject_id) VALUES
(15, 4, 1, 7, 3),   -- English -> 1-Jasmine
(15, 4, 3, 10, 4),  -- Environment -> 1-Tulip
(15, 4, 4, 8, 12),  -- Value Education -> 1-Rose
(15, 4, 6, 16, 5),  -- English Grammar -> 3-Rose
(15, 4, 8, 13, 5);  -- English Grammar -> 2-Orchid

-- FRIDAY (Day 5)
INSERT INTO timetable (teacher_id, day_of_week, period_no, class_id, subject_id) VALUES
(15, 5, 1, 7, 3),   -- English -> 1-Jasmine
(15, 5, 2, 16, 5),  -- English Grammar -> 3-Rose
(15, 5, 3, 17, 13), -- General Knowledge -> 3-Orchid
(15, 5, 4, 14, 15), -- Conversation -> 2-Tulip
(15, 5, 5, 10, 4);  -- Environment -> 1-Tulip

-- SATURDAY (Day 6)
INSERT INTO timetable (teacher_id, day_of_week, period_no, class_id, subject_id) VALUES
(15, 6, 1, 7, 3),   -- English -> 1-Jasmine
(15, 6, 2, 13, 5),  -- English Grammar -> 2-Orchid
(15, 6, 3, 10, 4);  -- Environment -> 1-Tulip

SELECT 'Timetable for Ms. Shubhi inserted successfully.' as status;
