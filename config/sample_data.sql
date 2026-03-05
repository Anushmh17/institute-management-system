-- ============================================================
--  Institute Management System - Sample Data for Testing
--  Run AFTER install.sql has been executed
--  All teacher passwords: Teacher@1234
--  All student passwords: Student@1234
-- ============================================================

USE `institute_management`;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. DEPARTMENTS
-- ============================================================
INSERT IGNORE INTO `departments` (`id`, `name`, `code`, `description`) VALUES
  (1, 'Computer Science',        'CS',   'Department covering programming, algorithms, and software engineering.'),
  (2, 'Business Administration', 'BA',   'Department focusing on management, finance, and entrepreneurship.'),
  (3, 'Electrical Engineering',  'EE',   'Department for circuits, signal processing, and embedded systems.'),
  (4, 'Mathematics',             'MATH', 'Department for pure and applied mathematics.'),
  (5, 'Arts & Humanities',       'AH',   'Department covering literature, history, and fine arts.');

-- ============================================================
-- 2. USERS (Teachers – role_id = 2)
--    Password hash for "Teacher@1234" (bcrypt cost 10)
-- ============================================================
INSERT IGNORE INTO `users` (`id`, `role_id`, `username`, `email`, `password`, `full_name`, `phone`, `status`) VALUES
  (10, 2, 'teacher2',  'teacher2@institute.com',  '$2y$10$uQk1GODeAps8o13.r3btz.AbSeRqBBouFHFB6pRXTj0DH7i79spky', 'Dr. Michael Chen',      '+1-555-0201', 'active'),
  (11, 2, 'teacher3',  'teacher3@institute.com',  '$2y$10$uQk1GODeAps8o13.r3btz.AbSeRqBBouFHFB6pRXTj0DH7i79spky', 'Prof. Emily Rodriguez', '+1-555-0202', 'active'),
  (12, 2, 'teacher4',  'teacher4@institute.com',  '$2y$10$uQk1GODeAps8o13.r3btz.AbSeRqBBouFHFB6pRXTj0DH7i79spky', 'Dr. Priya Sharma',      '+1-555-0203', 'active'),
  (13, 2, 'teacher5',  'teacher5@institute.com',  '$2y$10$uQk1GODeAps8o13.r3btz.AbSeRqBBouFHFB6pRXTj0DH7i79spky', 'Mr. Daniel Thompson',   '+1-555-0204', 'active'),
  (14, 2, 'teacher6',  'teacher6@institute.com',  '$2y$10$uQk1GODeAps8o13.r3btz.AbSeRqBBouFHFB6pRXTj0DH7i79spky', 'Ms. Laura Bennett',     '+1-555-0205', 'active'),
  (15, 2, 'teacher7',  'teacher7@institute.com',  '$2y$10$uQk1GODeAps8o13.r3btz.AbSeRqBBouFHFB6pRXTj0DH7i79spky', 'Prof. Ahmed Al-Rashid', '+1-555-0206', 'inactive');

-- ============================================================
-- 3. USERS (Students – role_id = 3)
--    Password hash for "Student@1234" (bcrypt cost 10)
-- ============================================================
INSERT IGNORE INTO `users` (`id`, `role_id`, `username`, `email`, `password`, `full_name`, `phone`, `status`) VALUES
  (20, 3, 'student2',  'student2@institute.com',  '$2y$10$Ov4lCLIYbZXO2k/MW/2K0u8F/XPiZpA2IXqP/Vz.01DWyBVzoqBLS', 'Aisha Patel',        '+1-555-0301', 'active'),
  (21, 3, 'student3',  'student3@institute.com',  '$2y$10$Ov4lCLIYbZXO2k/MW/2K0u8F/XPiZpA2IXqP/Vz.01DWyBVzoqBLS', 'Carlos Mendoza',     '+1-555-0302', 'active'),
  (22, 3, 'student4',  'student4@institute.com',  '$2y$10$Ov4lCLIYbZXO2k/MW/2K0u8F/XPiZpA2IXqP/Vz.01DWyBVzoqBLS', 'Sophie Turner',      '+1-555-0303', 'active'),
  (23, 3, 'student5',  'student5@institute.com',  '$2y$10$Ov4lCLIYbZXO2k/MW/2K0u8F/XPiZpA2IXqP/Vz.01DWyBVzoqBLS', 'Ravi Kumar',         '+1-555-0304', 'active'),
  (24, 3, 'student6',  'student6@institute.com',  '$2y$10$Ov4lCLIYbZXO2k/MW/2K0u8F/XPiZpA2IXqP/Vz.01DWyBVzoqBLS', 'Fatima Al-Hassan',   '+1-555-0305', 'active'),
  (25, 3, 'student7',  'student7@institute.com',  '$2y$10$Ov4lCLIYbZXO2k/MW/2K0u8F/XPiZpA2IXqP/Vz.01DWyBVzoqBLS', 'Liam O\'Brien',      '+1-555-0306', 'active'),
  (26, 3, 'student8',  'student8@institute.com',  '$2y$10$Ov4lCLIYbZXO2k/MW/2K0u8F/XPiZpA2IXqP/Vz.01DWyBVzoqBLS', 'Mei-Ling Zhang',     '+1-555-0307', 'active'),
  (27, 3, 'student9',  'student9@institute.com',  '$2y$10$Ov4lCLIYbZXO2k/MW/2K0u8F/XPiZpA2IXqP/Vz.01DWyBVzoqBLS', 'Marcus Williams',    '+1-555-0308', 'active'),
  (28, 3, 'student10', 'student10@institute.com', '$2y$10$Ov4lCLIYbZXO2k/MW/2K0u8F/XPiZpA2IXqP/Vz.01DWyBVzoqBLS', 'Natasha Ivanova',    '+1-555-0309', 'active'),
  (29, 3, 'student11', 'student11@institute.com', '$2y$10$Ov4lCLIYbZXO2k/MW/2K0u8F/XPiZpA2IXqP/Vz.01DWyBVzoqBLS', 'Omar Abdullah',      '+1-555-0310', 'active'),
  (30, 3, 'student12', 'student12@institute.com', '$2y$10$Ov4lCLIYbZXO2k/MW/2K0u8F/XPiZpA2IXqP/Vz.01DWyBVzoqBLS', 'Isabella Garcia',    '+1-555-0311', 'active'),
  (31, 3, 'student13', 'student13@institute.com', '$2y$10$Ov4lCLIYbZXO2k/MW/2K0u8F/XPiZpA2IXqP/Vz.01DWyBVzoqBLS', 'Arjun Nair',         '+1-555-0312', 'inactive');

-- ============================================================
-- 4. TEACHERS (profile records)
-- ============================================================
-- First link the existing teacher1 user (id=2) to teacher profile
INSERT IGNORE INTO `teachers` (`id`, `user_id`, `teacher_id`, `department_id`, `qualification`, `specialization`, `salary`, `join_date`, `address`) VALUES
  (1, 2,  'TCH-001', 1, 'M.Sc. Computer Science, Stanford University',   'Web Development & Algorithms',            65000.00, '2021-06-01', '12 Oak Lane, Tech City, CA 90210'),
  (2, 10, 'TCH-002', 1, 'Ph.D. Artificial Intelligence, MIT',            'Machine Learning & Data Science',         85000.00, '2020-03-15', '45 Pine Avenue, Silicon Valley, CA 94025'),
  (3, 11, 'TCH-003', 2, 'Ph.D. Business Mgmt, Harvard University',       'Organizational Behavior & Marketing',     78000.00, '2019-09-01', '7 Elm Street, Boston, MA 02101'),
  (4, 12, 'TCH-004', 3, 'Ph.D. Electrical Eng., IIT Bombay',             'Power Systems & Renewable Energy',        72000.00, '2022-01-10', '33 Maple Drive, Energy Park, TX 75001'),
  (5, 13, 'TCH-005', 4, 'M.Sc. Pure Mathematics, Cambridge University',  'Number Theory & Cryptography',            60000.00, '2023-07-20', '18 Birch Road, Scholar Town, UK E1 6AN'),
  (6, 14, 'TCH-006', 5, 'M.A. Fine Arts, Royal College of Art',          'Visual Arts & Digital Media',             55000.00, '2021-11-05', '92 Cedar Boulevard, Arts District, NY 10001'),
  (7, 15, 'TCH-007', 2, 'MBA, INSEAD Business School',                   'International Finance & Risk Management', 90000.00, '2018-04-22', '5 Date Palm Street, Dubai, UAE');

-- ============================================================
-- 5. COURSES
-- ============================================================
INSERT IGNORE INTO `courses` (`id`, `department_id`, `name`, `code`, `description`, `duration_months`, `fee`, `max_students`, `status`) VALUES
  (1, 1, 'Bachelor of Computer Science',        'BCS-101',  'A comprehensive program covering core CS fundamentals, programming, and software development.',   36, 12000.00, 60, 'active'),
  (2, 1, 'Diploma in Web Development',          'DWD-201',  'Hands-on course covering HTML, CSS, JavaScript, PHP, and modern frameworks.',                    12,  4500.00, 40, 'active'),
  (3, 2, 'Bachelor of Business Administration', 'BBA-301',  'Covers management principles, economics, marketing, and entrepreneurship.',                      36, 10000.00, 70, 'active'),
  (4, 3, 'Bachelor of Electrical Engineering',  'BEE-401',  'Covers circuit theory, electronics, signal processing, and embedded systems.',                   48, 14000.00, 50, 'active'),
  (5, 4, 'Diploma in Applied Mathematics',      'DAM-501',  'Focuses on calculus, linear algebra, statistics, and numerical methods.',                        18,  5500.00, 35, 'active'),
  (6, 5, 'Certificate in Graphic Design',       'CGD-601',  'Practical certificate covering design principles, typography, and modern design tools.',           6,  2000.00, 30, 'active'),
  (7, 1, 'Master of Data Science',              'MDS-701',  'Advanced program in machine learning, big data, and AI for graduates.',                          24, 18000.00, 25, 'active');

-- ============================================================
-- 6. SUBJECTS
-- ============================================================
INSERT IGNORE INTO `subjects` (`id`, `course_id`, `teacher_id`, `name`, `code`, `credit_hours`, `max_marks`, `pass_marks`) VALUES
  -- BCS subjects
  (1,  1, 1, 'Introduction to Programming',     'CS101', 4, 100, 40),
  (2,  1, 2, 'Data Structures & Algorithms',    'CS201', 4, 100, 40),
  (3,  1, 2, 'Machine Learning Fundamentals',   'CS301', 3, 100, 40),
  (4,  1, 1, 'Database Management Systems',     'CS202', 3, 100, 40),
  -- DWD subjects
  (5,  2, 1, 'HTML & CSS Essentials',           'WD101', 3, 100, 40),
  (6,  2, 1, 'JavaScript & Modern Frameworks',  'WD201', 4, 100, 40),
  (7,  2, 1, 'Backend Development with PHP',    'WD301', 3, 100, 40),
  -- BBA subjects
  (8,  3, 3, 'Principles of Management',        'BA101', 3, 100, 40),
  (9,  3, 3, 'Marketing Management',            'BA201', 3, 100, 40),
  (10, 3, 7, 'Financial Accounting',            'BA301', 3, 100, 40),
  -- BEE subjects
  (11, 4, 4, 'Circuit Theory',                  'EE101', 4, 100, 40),
  (12, 4, 4, 'Digital Electronics',             'EE201', 3, 100, 40),
  (13, 4, 4, 'Signal Processing',               'EE301', 3, 100, 40),
  -- DAM subjects
  (14, 5, 5, 'Calculus & Differential Equations','MA101', 4, 100, 40),
  (15, 5, 5, 'Linear Algebra',                  'MA201', 3, 100, 40),
  -- CGD subjects
  (16, 6, 6, 'Design Principles & Color Theory','GD101', 3, 100, 40),
  (17, 6, 6, 'Typography & Layout',             'GD201', 2, 100, 40),
  -- MDS subjects
  (18, 7, 2, 'Deep Learning & Neural Networks', 'DS101', 4, 100, 40),
  (19, 7, 2, 'Big Data Analytics',              'DS201', 3, 100, 40);

-- ============================================================
-- 7. STUDENTS (profile records)
-- ============================================================
INSERT IGNORE INTO `students` (`id`, `user_id`, `student_id`, `course_id`, `roll_number`, `date_of_birth`, `gender`, `blood_group`, `address`, `guardian_name`, `guardian_phone`, `guardian_email`, `admission_date`, `batch_year`, `status`) VALUES
  -- Existing student1 (user_id=3)
  (1,  3,  'STU-0001', 1, 'BCS-2023-001', '2002-05-14', 'male',   'O+',  '22 Willow Way, Springfield, IL', 'Robert Wilson',    '+1-555-4001', 'r.wilson@email.com',   '2023-09-01', 2023, 'active'),
  -- New students
  (2,  20, 'STU-0002', 1, 'BCS-2023-002', '2003-02-28', 'female', 'A+',  '8 Jasmine Gardens, Mumbai, IN', 'Raj Patel',        '+91-98765401', 'raj.patel@email.com',  '2023-09-01', 2023, 'active'),
  (3,  21, 'STU-0003', 3, 'BBA-2023-001', '2001-11-07', 'male',   'B+',  '45 Calle Luna, Madrid, ES',     'Elena Mendoza',    '+34-91-555-01', 'emendoza@email.com',  '2023-09-01', 2023, 'active'),
  (4,  22, 'STU-0004', 2, 'DWD-2024-001', '2004-08-19', 'female', 'AB-', '14 Victoria Road, London, UK',  'George Turner',    '+44-20-7946-01', 'gturner@email.com',  '2024-01-15', 2024, 'active'),
  (5,  23, 'STU-0005', 4, 'BEE-2023-001', '2002-03-25', 'male',   'O-',  '77 MG Road, Bangalore, IN',     'Suresh Kumar',     '+91-98765402', 's.kumar@email.com',    '2023-09-01', 2023, 'active'),
  (6,  24, 'STU-0006', 3, 'BBA-2023-002', '2003-06-12', 'female', 'A-',  '22 Nile View, Cairo, EG',       'Hassan Al-Hassan', '+20-2-2345-678', 'h.hassan@email.com', '2023-09-01', 2023, 'active'),
  (7,  25, 'STU-0007', 1, 'BCS-2024-001', '2004-01-30', 'male',   'B-',  '3 Merrion Square, Dublin, IE',  'Brigid O\'Brien',  '+353-1-555-0101', 'brien@email.com',   '2024-01-15', 2024, 'active'),
  (8,  26, 'STU-0008', 7, 'MDS-2024-001', '1999-09-05', 'female', 'O+',  '88 Nanjing Road, Shanghai, CN', 'Wei Zhang',        '+86-21-5555-0101', 'w.zhang@email.com', '2024-01-15', 2024, 'active'),
  (9,  27, 'STU-0009', 2, 'DWD-2023-001', '2002-12-17', 'male',   'A+',  '5901 Lincoln Ave, Chicago, IL', 'Diana Williams',   '+1-555-4009', 'd.williams@email.com', '2023-09-01', 2023, 'active'),
  (10, 28, 'STU-0010', 7, 'MDS-2024-002', '1998-07-22', 'female', 'AB+', '15 Nevsky Pr., St. Petersburg', 'Boris Ivanov',     '+7-812-555-0101', 'b.ivanov@email.com', '2024-01-15', 2024, 'active'),
  (11, 29, 'STU-0011', 4, 'BEE-2023-002', '2002-04-18', 'male',   'O+',  '12 King Fahd Road, Riyadh, SA', 'Khalid Abdullah',  '+966-11-555-0101', 'k.abdl@email.com',  '2023-09-01', 2023, 'active'),
  (12, 30, 'STU-0012', 5, 'DAM-2023-001', '2003-10-09', 'female', 'B+',  '67 Gran Via, Barcelona, ES',    'Juan Garcia',      '+34-93-555-01', 'j.garcia@email.com',  '2023-09-01', 2023, 'active'),
  (13, 31, 'STU-0013', 6, 'CGD-2024-001', '2001-05-03', 'male',   'O-',  '201 Brigade Road, Kochi, IN',   'Sunita Nair',      '+91-98765403', 's.nair@email.com',     '2024-01-15', 2024, 'inactive');

-- ============================================================
-- 8. ENROLLMENTS
-- ============================================================
INSERT IGNORE INTO `enrollments` (`student_id`, `course_id`, `status`) VALUES
  (1,  1, 'active'),
  (2,  1, 'active'),
  (3,  3, 'active'),
  (4,  2, 'active'),
  (5,  4, 'active'),
  (6,  3, 'active'),
  (7,  1, 'active'),
  (8,  7, 'active'),
  (9,  2, 'active'),
  (10, 7, 'active'),
  (11, 4, 'active'),
  (12, 5, 'active'),
  (13, 6, 'dropped');

-- ============================================================
-- 9. CLASSES (Weekly Schedule)
-- ============================================================
INSERT IGNORE INTO `classes` (`id`, `subject_id`, `teacher_id`, `room`, `day_of_week`, `start_time`, `end_time`, `type`, `status`) VALUES
  -- CS subjects
  (1,  1, 1, 'Room 101', 'Monday',    '09:00:00', '10:30:00', 'lecture',  'scheduled'),
  (2,  1, 1, 'Lab A',    'Wednesday', '11:00:00', '13:00:00', 'lab',      'scheduled'),
  (3,  2, 2, 'Room 102', 'Tuesday',   '09:00:00', '10:30:00', 'lecture',  'scheduled'),
  (4,  2, 2, 'Room 102', 'Thursday',  '09:00:00', '10:30:00', 'tutorial', 'scheduled'),
  (5,  3, 2, 'Room 103', 'Wednesday', '09:00:00', '10:30:00', 'lecture',  'scheduled'),
  (6,  4, 1, 'Room 104', 'Friday',    '09:00:00', '10:30:00', 'lecture',  'scheduled'),
  -- WD subjects
  (7,  5, 1, 'Lab B',    'Monday',    '11:00:00', '12:30:00', 'lecture',  'scheduled'),
  (8,  6, 1, 'Lab B',    'Wednesday', '11:00:00', '13:00:00', 'lab',      'scheduled'),
  (9,  7, 1, 'Lab B',    'Friday',    '11:00:00', '12:30:00', 'lecture',  'scheduled'),
  -- BA subjects
  (10, 8,  3, 'Room 201', 'Monday',    '14:00:00', '15:30:00', 'lecture',  'scheduled'),
  (11, 9,  3, 'Room 201', 'Wednesday', '14:00:00', '15:30:00', 'lecture',  'scheduled'),
  (12, 10, 7, 'Room 202', 'Friday',    '14:00:00', '15:30:00', 'lecture',  'scheduled'),
  -- EE subjects
  (13, 11, 4, 'Room 301', 'Tuesday',   '11:00:00', '12:30:00', 'lecture',  'scheduled'),
  (14, 12, 4, 'Lab C',    'Thursday',  '11:00:00', '13:00:00', 'lab',      'scheduled'),
  (15, 13, 4, 'Room 302', 'Friday',    '11:00:00', '12:30:00', 'lecture',  'scheduled'),
  -- MA subjects
  (16, 14, 5, 'Room 401', 'Monday',    '09:00:00', '10:30:00', 'lecture',  'scheduled'),
  (17, 15, 5, 'Room 401', 'Thursday',  '09:00:00', '10:30:00', 'lecture',  'scheduled'),
  -- GD subjects
  (18, 16, 6, 'Studio 1', 'Tuesday',   '14:00:00', '16:00:00', 'lecture',  'scheduled'),
  (19, 17, 6, 'Studio 1', 'Thursday',  '14:00:00', '15:30:00', 'lecture',  'scheduled'),
  -- DS subjects
  (20, 18, 2, 'Room 105', 'Monday',    '16:00:00', '17:30:00', 'lecture',  'scheduled'),
  (21, 19, 2, 'Room 105', 'Wednesday', '16:00:00', '17:30:00', 'lecture',  'scheduled'),
  -- One cancelled and one completed class for variety
  (22, 1,  1, 'Room 101', 'Monday',    '09:00:00', '10:30:00', 'lecture',  'cancelled'),
  (23, 2,  2, 'Room 102', 'Tuesday',   '09:00:00', '10:30:00', 'exam',     'completed');

-- ============================================================
-- 10. ATTENDANCE (last 2 weeks sample)
-- ============================================================
INSERT IGNORE INTO `attendance` (`student_id`, `class_id`, `date`, `status`, `marked_by`) VALUES
  -- Student 1 (James Wilson) – CS classes
  (1, 1, '2026-02-17', 'present',  2), (1, 1, '2026-02-24', 'present',  2),
  (1, 3, '2026-02-18', 'present',  2), (1, 3, '2026-02-25', 'absent',   2),
  (1, 5, '2026-02-19', 'present',  2),
  -- Student 2 (Aisha Patel) – CS classes
  (2, 1, '2026-02-17', 'present',  2), (2, 1, '2026-02-24', 'late',     2),
  (2, 3, '2026-02-18', 'present',  2), (2, 3, '2026-02-25', 'present',  2),
  -- Student 3 (Carlos Mendoza) – BBA classes
  (3, 10, '2026-02-17', 'present', 11), (3, 10, '2026-02-24', 'present', 11),
  (3, 11, '2026-02-19', 'absent',  11), (3, 11, '2026-02-26', 'present', 11),
  -- Student 4 (Sophie Turner) – WD classes
  (4, 7,  '2026-02-17', 'present',  2), (4, 7,  '2026-02-24', 'excused',  2),
  (4, 8,  '2026-02-19', 'present',  2),
  -- Student 5 (Ravi Kumar) – EE classes
  (5, 13, '2026-02-18', 'present', 12), (5, 13, '2026-02-25', 'present', 12),
  (5, 14, '2026-02-20', 'present', 12), (5, 14, '2026-02-27', 'late',    12),
  -- Student 6 (Fatima) – BBA classes
  (6, 10, '2026-02-17', 'present', 11), (6, 10, '2026-02-24', 'present', 11),
  -- Student 7 (Liam) – CS classes
  (7, 1,  '2026-02-17', 'late',     2), (7, 1,  '2026-02-24', 'present',  2),
  -- Student 8 (Mei-Ling) – MDS classes
  (8, 20, '2026-02-17', 'present',  10), (8, 20, '2026-02-24', 'present', 10),
  (8, 21, '2026-02-19', 'absent',   10),
  -- Student 9 (Marcus) – WD classes
  (9, 7,  '2026-02-17', 'present',  2), (9, 7,  '2026-02-24', 'present',  2),
  -- Student 10 (Natasha) – MDS classes
  (10, 20, '2026-02-17', 'present', 10), (10, 21, '2026-02-19', 'present', 10),
  -- Student 11 (Omar) – EE classes
  (11, 13, '2026-02-18', 'absent',  12), (11, 14, '2026-02-20', 'present', 12),
  -- Student 12 (Isabella) – DAM classes
  (12, 16, '2026-02-17', 'present',  13), (12, 17, '2026-02-20', 'present', 13);

-- ============================================================
-- 11. MARKS
-- ============================================================
INSERT IGNORE INTO `marks` (`student_id`, `subject_id`, `exam_type`, `marks_obtained`, `max_marks`, `grade`, `remarks`, `entered_by`, `exam_date`) VALUES
  -- Student 1 (James Wilson) – BCS
  (1, 1, 'midterm',    72.00, 100.00, 'B',  'Good performance',         2, '2025-11-15'),
  (1, 1, 'final',      85.00, 100.00, 'A',  'Excellent improvement',    2, '2026-01-20'),
  (1, 1, 'assignment', 18.00,  20.00, 'A',  NULL,                       2, '2025-10-10'),
  (1, 2, 'midterm',    65.00, 100.00, 'C+', 'Needs more practice',      2, '2025-11-16'),
  (1, 2, 'final',      78.00, 100.00, 'B+', 'Good effort on trees',     2, '2026-01-21'),
  (1, 4, 'midterm',    88.00, 100.00, 'A',  'Outstanding SQL skills',   1, '2025-11-17'),
  -- Student 2 (Aisha Patel) – BCS
  (2, 1, 'midterm',    90.00, 100.00, 'A+', 'Top of the class',         2, '2025-11-15'),
  (2, 1, 'final',      93.00, 100.00, 'A+', 'Exceptional work',         2, '2026-01-20'),
  (2, 2, 'midterm',    82.00, 100.00, 'A',  'Strong understanding',     2, '2025-11-16'),
  (2, 2, 'final',      88.00, 100.00, 'A',  'Excellent algorithms',     2, '2026-01-21'),
  -- Student 3 (Carlos Mendoza) – BBA
  (3, 8, 'midterm',    70.00, 100.00, 'B',  NULL,                       11, '2025-11-15'),
  (3, 8, 'final',      76.00, 100.00, 'B+', 'Improved significantly',   11, '2026-01-20'),
  (3, 9, 'midterm',    55.00, 100.00, 'C',  'Needs to focus on theory', 11, '2025-11-16'),
  (3, 9, 'final',      68.00, 100.00, 'B-', 'Better effort this time',  11, '2026-01-21'),
  -- Student 4 (Sophie Turner) – DWD
  (4, 5, 'midterm',    95.00, 100.00, 'A+', 'Perfect CSS skills',        2, '2025-11-15'),
  (4, 5, 'final',      97.00, 100.00, 'A+', 'Best in class',             2, '2026-01-20'),
  (4, 6, 'midterm',    88.00, 100.00, 'A',  'Great JS projects',         2, '2025-11-16'),
  -- Student 5 (Ravi Kumar) – BEE
  (5, 11, 'midterm',   75.00, 100.00, 'B+', NULL,                       12, '2025-11-15'),
  (5, 11, 'final',     82.00, 100.00, 'A',  'Good circuit analysis',    12, '2026-01-20'),
  (5, 12, 'midterm',   68.00, 100.00, 'B-', 'Needs lab practice',       12, '2025-11-16'),
  -- Student 6 (Fatima) – BBA
  (6, 8, 'midterm',    83.00, 100.00, 'A',  NULL,                       11, '2025-11-15'),
  (6, 8, 'final',      89.00, 100.00, 'A',  'Great leadership essays',  11, '2026-01-20'),
  (6, 10,'midterm',    78.00, 100.00, 'B+', 'Strong with accounting',    1, '2025-11-17'),
  -- Student 7 (Liam O'Brien) – BCS
  (7, 1, 'midterm',    60.00, 100.00, 'C+', 'Late start but improving',  2, '2025-11-15'),
  (7, 1, 'final',      74.00, 100.00, 'B',  'Good final submission',     2, '2026-01-20'),
  -- Student 8 (Mei-Ling Zhang) – MDS
  (8, 18, 'midterm',   92.00, 100.00, 'A+', 'Exceptional ML grasp',      2, '2025-11-15'),
  (8, 18, 'final',     96.00, 100.00, 'A+', 'Best thesis project',       2, '2026-01-20'),
  (8, 19, 'midterm',   88.00, 100.00, 'A',  'Great big data analysis',   2, '2025-11-16'),
  -- Student 9 (Marcus Williams) – DWD
  (9, 5, 'midterm',    74.00, 100.00, 'B',  NULL,                        2, '2025-11-15'),
  (9, 6, 'midterm',    81.00, 100.00, 'A',  'Good React project',        2, '2025-11-16'),
  -- Student 10 (Natasha Ivanova) – MDS
  (10, 18,'midterm',   85.00, 100.00, 'A',  'Strong research skills',    2, '2025-11-15'),
  (10, 19,'midterm',   79.00, 100.00, 'B+', NULL,                        2, '2025-11-16'),
  -- Student 11 (Omar) – BEE
  (11, 11,'midterm',   45.00, 100.00, 'D',  'Needs immediate support',  12, '2025-11-15'),
  (11, 11,'final',     58.00, 100.00, 'C',  'Improved after tutoring',  12, '2026-01-20'),
  -- Student 12 (Isabella) – DAM
  (12, 14,'midterm',   91.00, 100.00, 'A+', 'Excellent calculus work',   13, '2025-11-15'),
  (12, 14,'final',     95.00, 100.00, 'A+', 'Perfect problem solving',   13, '2026-01-20'),
  (12, 15,'midterm',   87.00, 100.00, 'A',  'Strong linear algebra',     13, '2025-11-16');

-- ============================================================
-- 12. Update department heads now that teachers exist
-- ============================================================
UPDATE `departments` SET `head_user_id` = 2  WHERE `id` = 1;   -- Sarah Johnson → CS
UPDATE `departments` SET `head_user_id` = 11 WHERE `id` = 2;   -- Emily Rodriguez → BA
UPDATE `departments` SET `head_user_id` = 12 WHERE `id` = 3;   -- Priya Sharma → EE
UPDATE `departments` SET `head_user_id` = 13 WHERE `id` = 4;   -- Daniel Thompson → MATH
UPDATE `departments` SET `head_user_id` = 14 WHERE `id` = 5;   -- Laura Bennett → AH

SET FOREIGN_KEY_CHECKS = 1;
