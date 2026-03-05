-- ============================================================
--  Institute Management System - Database Schema
--  Version: 1.0 | Engine: InnoDB | Charset: utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS `institute_management`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `institute_management`;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table: roles
-- ----------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id`         TINYINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(30)       NOT NULL UNIQUE,
  `label`      VARCHAR(50)       NOT NULL,
  `created_at` TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `roles` (`id`, `name`, `label`) VALUES
  (1, 'admin',   'Administrator'),
  (2, 'teacher', 'Teacher'),
  (3, 'student', 'Student');

-- ----------------------------
-- Table: users
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `role_id`      TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `username`     VARCHAR(60)     NOT NULL UNIQUE,
  `email`        VARCHAR(120)    NOT NULL UNIQUE,
  `password`     VARCHAR(255)    NOT NULL,
  `full_name`    VARCHAR(120)    NOT NULL,
  `phone`        VARCHAR(20)     DEFAULT NULL,
  `profile_photo`VARCHAR(255)    DEFAULT NULL,
  `status`       ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `last_login`   DATETIME        DEFAULT NULL,
  `csrf_token`   VARCHAR(64)     DEFAULT NULL,
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_role` (`role_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default accounts (Password: Admin@1234, Teacher@1234, Student@1234)
INSERT IGNORE INTO `users` (`id`, `role_id`, `username`, `email`, `password`, `full_name`, `status`) VALUES
  (1, 1, 'admin',    'admin@institute.com',    '$2y$10$gSqvzuvy53wO/Sht1WUzMuF.19ivtp9PaiHUityYhWB8FtDyhqON.', 'System Administrator', 'active'),
  (2, 2, 'teacher1', 'teacher1@institute.com', '$2y$10$uQk1GODeAps8o13.r3btz.AbSeRqBBouFHFB6pRXTj0DH7i79spky', 'Sarah Johnson',        'active'),
  (3, 3, 'student1', 'student1@institute.com', '$2y$10$Ov4lCLIYbZXO2k/MW/2K0u8F/XPiZpA2IXqP/Vz.01DWyBVzoqBLS', 'James Wilson',         'active');

-- ----------------------------
-- Table: departments
-- ----------------------------
CREATE TABLE IF NOT EXISTS `departments` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)  NOT NULL UNIQUE,
  `code`        VARCHAR(10)   NOT NULL UNIQUE,
  `head_user_id`INT UNSIGNED  DEFAULT NULL,
  `description` TEXT          DEFAULT NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_dept_head` FOREIGN KEY (`head_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: teachers
-- ----------------------------
CREATE TABLE IF NOT EXISTS `teachers` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED  NOT NULL UNIQUE,
  `teacher_id`     VARCHAR(20)   NOT NULL UNIQUE,
  `department_id`  INT UNSIGNED  DEFAULT NULL,
  `qualification`  VARCHAR(200)  DEFAULT NULL,
  `specialization` VARCHAR(200)  DEFAULT NULL,
  `salary`         DECIMAL(10,2) DEFAULT NULL,
  `join_date`      DATE          DEFAULT NULL,
  `address`        TEXT          DEFAULT NULL,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_teacher_user` (`user_id`),
  KEY `idx_teacher_dept` (`department_id`),
  CONSTRAINT `fk_teacher_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_teacher_dept` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: courses
-- ----------------------------
CREATE TABLE IF NOT EXISTS `courses` (
  `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `department_id`  INT UNSIGNED   DEFAULT NULL,
  `name`           VARCHAR(150)   NOT NULL,
  `code`           VARCHAR(20)    NOT NULL UNIQUE,
  `description`    TEXT           DEFAULT NULL,
  `duration_months`TINYINT UNSIGNED NOT NULL DEFAULT 12,
  `fee`            DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `max_students`   SMALLINT UNSIGNED NOT NULL DEFAULT 50,
  `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_course_dept` (`department_id`),
  CONSTRAINT `fk_course_dept` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: subjects
-- ----------------------------
CREATE TABLE IF NOT EXISTS `subjects` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `course_id`   INT UNSIGNED  NOT NULL,
  `teacher_id`  INT UNSIGNED  DEFAULT NULL,
  `name`        VARCHAR(150)  NOT NULL,
  `code`        VARCHAR(20)   NOT NULL UNIQUE,
  `credit_hours`TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `max_marks`   SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  `pass_marks`  SMALLINT UNSIGNED NOT NULL DEFAULT 40,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subject_course` (`course_id`),
  KEY `idx_subject_teacher` (`teacher_id`),
  CONSTRAINT `fk_subject_course`  FOREIGN KEY (`course_id`)  REFERENCES `courses`(`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_subject_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: students
-- ----------------------------
CREATE TABLE IF NOT EXISTS `students` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED  NOT NULL UNIQUE,
  `student_id`      VARCHAR(20)   NOT NULL UNIQUE,
  `course_id`       INT UNSIGNED  DEFAULT NULL,
  `roll_number`     VARCHAR(20)   DEFAULT NULL,
  `date_of_birth`   DATE          DEFAULT NULL,
  `gender`          ENUM('male','female','other') DEFAULT NULL,
  `blood_group`     VARCHAR(5)    DEFAULT NULL,
  `address`         TEXT          DEFAULT NULL,
  `guardian_name`   VARCHAR(120)  DEFAULT NULL,
  `guardian_phone`  VARCHAR(20)   DEFAULT NULL,
  `guardian_email`  VARCHAR(120)  DEFAULT NULL,
  `admission_date`  DATE          NOT NULL DEFAULT (CURRENT_DATE),
  `batch_year`      YEAR          DEFAULT NULL,
  `status`          ENUM('active','inactive','graduated','dropped') NOT NULL DEFAULT 'active',
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_user`   (`user_id`),
  KEY `idx_student_course` (`course_id`),
  KEY `idx_student_status` (`status`),
  CONSTRAINT `fk_student_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_student_course` FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`)  ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: classes (schedules)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `classes` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `subject_id`  INT UNSIGNED  NOT NULL,
  `teacher_id`  INT UNSIGNED  DEFAULT NULL,
  `room`        VARCHAR(50)   DEFAULT NULL,
  `day_of_week` ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time`  TIME          NOT NULL,
  `end_time`    TIME          NOT NULL,
  `class_date`  DATE          DEFAULT NULL COMMENT 'For one-time classes - NULL = recurring',
  `type`        ENUM('lecture','lab','tutorial','exam') NOT NULL DEFAULT 'lecture',
  `status`      ENUM('scheduled','cancelled','completed') NOT NULL DEFAULT 'scheduled',
  `notes`       TEXT          DEFAULT NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_class_subject` (`subject_id`),
  KEY `idx_class_teacher` (`teacher_id`),
  KEY `idx_class_date`    (`class_date`),
  CONSTRAINT `fk_class_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_class_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`)  ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: enrollments
-- ----------------------------
CREATE TABLE IF NOT EXISTS `enrollments` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `student_id`  INT UNSIGNED  NOT NULL,
  `course_id`   INT UNSIGNED  NOT NULL,
  `enrolled_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status`      ENUM('active','completed','dropped') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enrollment` (`student_id`, `course_id`),
  KEY `idx_enrollment_course` (`course_id`),
  CONSTRAINT `fk_enrollment_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_enrollment_course`  FOREIGN KEY (`course_id`)  REFERENCES `courses`(`id`)  ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: attendance
-- ----------------------------
CREATE TABLE IF NOT EXISTS `attendance` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `student_id`  INT UNSIGNED  NOT NULL,
  `class_id`    INT UNSIGNED  NOT NULL,
  `date`        DATE          NOT NULL,
  `status`      ENUM('present','absent','late','excused') NOT NULL DEFAULT 'absent',
  `marked_by`   INT UNSIGNED  DEFAULT NULL COMMENT 'teacher user_id',
  `notes`       VARCHAR(255)  DEFAULT NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance` (`student_id`, `class_id`, `date`),
  KEY `idx_att_student`  (`student_id`),
  KEY `idx_att_class`    (`class_id`),
  KEY `idx_att_date`     (`date`),
  CONSTRAINT `fk_att_student`  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_att_class`    FOREIGN KEY (`class_id`)   REFERENCES `classes`(`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_att_marker`   FOREIGN KEY (`marked_by`)  REFERENCES `users`(`id`)    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: marks
-- ----------------------------
CREATE TABLE IF NOT EXISTS `marks` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `student_id`    INT UNSIGNED    NOT NULL,
  `subject_id`    INT UNSIGNED    NOT NULL,
  `exam_type`     ENUM('midterm','final','assignment','quiz','practical') NOT NULL DEFAULT 'final',
  `marks_obtained`DECIMAL(6,2)    NOT NULL DEFAULT 0.00,
  `max_marks`     DECIMAL(6,2)    NOT NULL DEFAULT 100.00,
  `grade`         VARCHAR(5)      DEFAULT NULL,
  `remarks`       VARCHAR(255)    DEFAULT NULL,
  `entered_by`    INT UNSIGNED    DEFAULT NULL,
  `exam_date`     DATE            DEFAULT NULL,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_marks` (`student_id`, `subject_id`, `exam_type`),
  KEY `idx_marks_student` (`student_id`),
  KEY `idx_marks_subject` (`subject_id`),
  CONSTRAINT `fk_marks_student`  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_marks_subject`  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_marks_enterer`  FOREIGN KEY (`entered_by`) REFERENCES `users`(`id`)     ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: reports (generated report cards)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `reports` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `student_id`   INT UNSIGNED  NOT NULL,
  `generated_by` INT UNSIGNED  DEFAULT NULL,
  `file_path`    VARCHAR(255)  DEFAULT NULL,
  `report_type`  ENUM('report_card','transcript','certificate') NOT NULL DEFAULT 'report_card',
  `academic_year`VARCHAR(10)   DEFAULT NULL,
  `generated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_student` (`student_id`),
  CONSTRAINT `fk_report_student` FOREIGN KEY (`student_id`)   REFERENCES `students`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_report_by`      FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: activity_logs
-- ----------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED    DEFAULT NULL,
  `action`      VARCHAR(100)    NOT NULL,
  `module`      VARCHAR(50)     DEFAULT NULL,
  `description` TEXT            DEFAULT NULL,
  `ip_address`  VARCHAR(45)     DEFAULT NULL,
  `user_agent`  VARCHAR(255)    DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_user`   (`user_id`),
  KEY `idx_log_module` (`module`),
  KEY `idx_log_date`   (`created_at`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Table: settings
-- ----------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `setting_key`  VARCHAR(80)   NOT NULL UNIQUE,
  `setting_value`TEXT          DEFAULT NULL,
  `setting_group`VARCHAR(40)   NOT NULL DEFAULT 'general',
  `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
  ('institute_name',    'Excellence Institute',   'general'),
  ('institute_address', '123 Knowledge Street, Education City', 'general'),
  ('institute_phone',   '+1 (555) 000-1234',      'general'),
  ('institute_email',   'info@excellence.edu',    'general'),
  ('institute_website', 'https://excellence.edu', 'general'),
  ('academic_year',     '2025-2026',              'academic'),
  ('currency_symbol',   '$',                      'finance'),
  ('timezone',          'Asia/Kolkata',            'general'),
  ('date_format',       'd M Y',                  'general'),
  ('logo_path',         '',                       'general');

SET FOREIGN_KEY_CHECKS = 1;
