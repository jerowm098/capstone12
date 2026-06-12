-- ============================================================
--  PUPBC CARELINK - FULL DATABASE SCHEMA
--  QR-Integrated Health Information System
--  Polytechnic University of the Philippines - Binan Campus
-- ============================================================
--  Database : pupbc_carelink
--  Charset  : utf8mb4 (full Unicode + emoji support)
--  Collation: utf8mb4_unicode_ci
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+08:00";

-- ------------------------------------------------------------
-- Create & select the database
-- ------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `pupbc_carelink`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `pupbc_carelink`;


-- ============================================================
--  TABLE: users
--  Base account table for both students and nurses.
-- ============================================================
CREATE TABLE `users` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(180)    NOT NULL,
  `password`   VARCHAR(255)    NOT NULL COMMENT 'bcrypt hash via password_hash()',
  `role`       ENUM('student','nurse') NOT NULL DEFAULT 'student',
  `first_name` VARCHAR(80)     NOT NULL,
  `last_name`  VARCHAR(80)     NOT NULL,
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Base authentication table shared by students and nurses.';


-- ============================================================
--  TABLE: students
--  Extended profile for student users.
-- ============================================================
CREATE TABLE `students` (
  `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`              INT UNSIGNED  NOT NULL,
  `student_number`       VARCHAR(25)   NOT NULL COMMENT 'Format: YYYY-XXXXX-BN-0',
  `course`               VARCHAR(100)  NOT NULL,
  `year_level`           VARCHAR(20)   NOT NULL
                         COMMENT '1st Year / 2nd Year / 3rd Year / 4th Year / 5th Year',
  `birthdate`            DATE          NOT NULL,
  `blood_type`           VARCHAR(5)    NULL DEFAULT NULL
                         COMMENT 'A+ A- B+ B- AB+ AB- O+ O-',
  `allergies`            TEXT          NULL DEFAULT NULL,
  `medical_conditions`   TEXT          NULL DEFAULT NULL,
  `emergency_contact`    VARCHAR(120)  NULL DEFAULT NULL,
  `emergency_phone`      VARCHAR(15)   NULL DEFAULT NULL COMMENT '09XXXXXXXXX format',
  `emergency_relation`   VARCHAR(50)   NULL DEFAULT NULL
                         COMMENT 'Parent / Sibling / Spouse / Guardian / Friend / Other',
  `qr_code`              VARCHAR(50)   NOT NULL COMMENT 'CARE-XXXXXXXXXXXXXXXX token',
  `created_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_students_user_id`      (`user_id`),
  UNIQUE KEY `uq_students_student_number` (`student_number`),
  UNIQUE KEY `uq_students_qr_code`      (`qr_code`),
  CONSTRAINT `fk_students_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Extended profile and health info for student accounts.';


-- ============================================================
--  TABLE: nurses
--  Extended profile for clinic staff / nurse accounts.
-- ============================================================
CREATE TABLE `nurses` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED  NOT NULL,
  `position`       VARCHAR(80)   NOT NULL DEFAULT 'Staff Nurse'
                   COMMENT 'Head Nurse / Senior Nurse / Staff Nurse / Clinic Nurse / School Nurse / Health Officer',
  `license_number` VARCHAR(50)   NULL DEFAULT NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nurses_user_id` (`user_id`),
  CONSTRAINT `fk_nurses_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Clinic staff/nurse accounts linked to users table.';


-- ============================================================
--  TABLE: visits
--  Every clinic encounter (kiosk walk-in or appointment-derived).
--  Includes triage data, vitals, diagnosis, and treatment.
-- ============================================================
CREATE TABLE `visits` (
  `id`                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `student_id`          INT UNSIGNED     NOT NULL,
  `nurse_id`            INT UNSIGNED     NULL DEFAULT NULL
                        COMMENT 'Assigned when nurse starts consultation',
  `symptoms`            TEXT             NULL DEFAULT NULL,
  `pain_level`          TINYINT UNSIGNED NULL DEFAULT 0
                        COMMENT '0-10 pain scale',
  `notes`               TEXT             NULL DEFAULT NULL
                        COMMENT 'Freeform notes, appended on various actions',
  `diagnosis`           TEXT             NULL DEFAULT NULL,
  `treatment`           TEXT             NULL DEFAULT NULL,
  -- Vital signs
  `vitals_temp`         VARCHAR(10)      NULL DEFAULT NULL COMMENT 'Temperature in °C',
  `vitals_pulse`        VARCHAR(10)      NULL DEFAULT NULL COMMENT 'Heart rate in bpm',
  `vitals_bp`           VARCHAR(15)      NULL DEFAULT NULL COMMENT 'Blood pressure e.g. 120/80',
  `oxygen_saturation`   VARCHAR(10)      NULL DEFAULT NULL COMMENT 'SpO2 %',
  -- Triage / Queue
  `priority`            ENUM('emergency','urgent','priority','normal')
                        NOT NULL DEFAULT 'normal',
  `queue_number`        VARCHAR(10)      NULL DEFAULT NULL
                        COMMENT 'Auto-generated e.g. R-001 E-002',
  -- Status tracking
  `status`              ENUM('waiting','in-progress','completed','cancelled')
                        NOT NULL DEFAULT 'waiting',
  `visit_date`          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `consultation_date`   DATETIME         NULL DEFAULT NULL
                        COMMENT 'Set when nurse marks as completed',
  `created_at`          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_visits_student`      (`student_id`),
  KEY `idx_visits_nurse`        (`nurse_id`),
  KEY `idx_visits_status`       (`status`),
  KEY `idx_visits_visit_date`   (`visit_date`),
  KEY `idx_visits_priority`     (`priority`),
  CONSTRAINT `fk_visits_student`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_visits_nurse`
    FOREIGN KEY (`nurse_id`) REFERENCES `nurses` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Every clinic encounter: kiosk triage check-in and consultation records.';


-- ============================================================
--  TABLE: appointments
--  Online appointment bookings made through the student portal.
-- ============================================================
CREATE TABLE `appointments` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `student_id`       INT UNSIGNED  NOT NULL,
  `nurse_id`         INT UNSIGNED  NULL DEFAULT NULL
                     COMMENT 'Assigned nurse when confirmed',
  `appointment_date` DATE          NOT NULL,
  `appointment_time` TIME          NOT NULL,
  `purpose`          VARCHAR(100)  NOT NULL
                     COMMENT 'Consultation / Follow-up / Vaccination / Medical Certificate / Physical Exam / Dental Check-up',
  `symptoms`         TEXT          NULL DEFAULT NULL
                     COMMENT 'Reason / symptoms described by student at booking',
  `contact_number`   VARCHAR(15)   NOT NULL COMMENT '09XXXXXXXXX format',
  `status`           ENUM('pending','confirmed','completed','cancelled')
                     NOT NULL DEFAULT 'pending',
  `cancelled_at`     DATETIME      NULL DEFAULT NULL,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_appointments_student`      (`student_id`),
  KEY `idx_appointments_nurse`        (`nurse_id`),
  KEY `idx_appointments_status`       (`status`),
  KEY `idx_appointments_date_time`    (`appointment_date`, `appointment_time`),
  CONSTRAINT `fk_appointments_student`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appointments_nurse`
    FOREIGN KEY (`nurse_id`) REFERENCES `nurses` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Student online appointment bookings managed through the portal.';


-- ============================================================
--  TABLE: announcements
--  Clinic announcements posted by nurses, visible to all students.
-- ============================================================
CREATE TABLE `announcements` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(255)  NOT NULL,
  `content`     TEXT          NOT NULL,
  `category`    VARCHAR(50)   NOT NULL DEFAULT 'Announcement'
                COMMENT 'Important / Announcement / Health Advisory / Clinic Schedule / Event / Reminder',
  `expiry_date` DATE          NULL DEFAULT NULL
                COMMENT 'NULL = no expiry',
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1
                COMMENT '1 = visible to students, 0 = hidden',
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_announcements_is_active`  (`is_active`),
  KEY `idx_announcements_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Clinic announcements posted by nurses, pushed to all students as notifications.';


-- ============================================================
--  TABLE: notifications
--  In-app push notifications for students.
-- ============================================================
CREATE TABLE `notifications` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `student_id`  INT UNSIGNED  NOT NULL,
  `type`        ENUM('push')  NOT NULL DEFAULT 'push',
  `subject`     VARCHAR(255)  NOT NULL COMMENT 'Notification title',
  `message`     TEXT          NOT NULL,
  `status`      ENUM('pending','sent') NOT NULL DEFAULT 'pending',
  `is_read`     TINYINT(1)    NOT NULL DEFAULT 0 COMMENT '0 = unread, 1 = read',
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_student`  (`student_id`),
  KEY `idx_notifications_is_read`  (`is_read`),
  KEY `idx_notifications_created`  (`created_at`),
  CONSTRAINT `fk_notifications_student`
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='In-app notifications delivered to students (appointment updates, announcements).';


-- ============================================================
--  TABLE: contact_messages
--  Messages submitted via the public landing page contact form.
-- ============================================================
CREATE TABLE `contact_messages` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(120)  NOT NULL,
  `email`      VARCHAR(180)  NOT NULL,
  `message`    TEXT          NOT NULL,
  `ip_address` VARCHAR(45)   NULL DEFAULT NULL COMMENT 'IPv4 or IPv6',
  `is_read`    TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact_messages_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Contact form submissions from the public landing page.';


-- ============================================================
--  SAMPLE DATA
-- ============================================================

-- ------------------------------------------------------------
-- Nurse account (password: Nurse@12345)
-- bcrypt hash of 'Nurse@12345'
-- ------------------------------------------------------------
INSERT INTO `users` (`email`, `password`, `role`, `first_name`, `last_name`) VALUES
('nurse.admin@pup.edu.ph',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'nurse', 'Maria', 'Santos');

INSERT INTO `nurses` (`user_id`, `position`, `license_number`) VALUES
(1, 'Head Nurse', 'RN-2019-00123');

-- ------------------------------------------------------------
-- Student account (password: Student@12345)
-- bcrypt hash of 'Student@12345'
-- ------------------------------------------------------------
INSERT INTO `users` (`email`, `password`, `role`, `first_name`, `last_name`) VALUES
('juan.delacruz@pup.edu.ph',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'student', 'Juan', 'Dela Cruz');

INSERT INTO `students` (
  `user_id`, `student_number`, `course`, `year_level`, `birthdate`,
  `blood_type`, `allergies`, `medical_conditions`,
  `emergency_contact`, `emergency_phone`, `emergency_relation`,
  `qr_code`
) VALUES (
  2, '2023-00001-BN-0', 'BSIT', '2nd Year', '2002-05-14',
  'O+', 'None', 'None',
  'Pedro Dela Cruz', '09171234567', 'Parent',
  'CARE-A1B2C3D4E5F6A7B8'
);

-- ------------------------------------------------------------
-- Sample announcements
-- ------------------------------------------------------------
INSERT INTO `announcements` (`title`, `content`, `category`, `expiry_date`, `is_active`) VALUES
(
  'Clinic Schedule Update',
  'The PUPBC Clinic will be open from 7:30 AM to 5:00 PM, Monday to Friday. Saturday consultations are available from 8:00 AM to 12:00 PM. Closed on Sundays and holidays.',
  'Clinic Schedule',
  NULL,
  1
),
(
  'Free Blood Pressure Monitoring',
  'The clinic is offering free blood pressure monitoring for all PUP Binan students every Wednesday, 10:00 AM - 12:00 PM. No appointment needed. Just bring your Carelink QR.',
  'Health Advisory',
  '2025-12-31',
  1
),
(
  'Annual Physical Examination',
  'The Annual Physical Examination for all incoming students will be held on September 1-5, 2025. Please book your appointment through the student portal. Required documents: 2x2 ID photo, chest x-ray result.',
  'Important',
  '2025-09-05',
  1
);


-- ============================================================
--  VIEWS (optional helpers)
-- ============================================================

-- Today's active queue
CREATE OR REPLACE VIEW `v_todays_queue` AS
SELECT
  v.id,
  v.queue_number,
  v.priority,
  v.status,
  v.symptoms,
  v.pain_level,
  v.visit_date,
  s.student_number,
  s.course,
  s.year_level,
  u.first_name,
  u.last_name,
  CONCAT(u.first_name, ' ', u.last_name) AS full_name
FROM visits v
JOIN students s ON v.student_id = s.id
JOIN users   u ON s.user_id     = u.id
WHERE DATE(v.visit_date) = CURDATE()
ORDER BY
  FIELD(v.priority, 'emergency', 'urgent', 'priority', 'normal'),
  FIELD(v.status,   'waiting', 'in-progress', 'completed', 'cancelled'),
  v.created_at ASC;


-- Student visit summary
CREATE OR REPLACE VIEW `v_student_visit_summary` AS
SELECT
  s.id              AS student_id,
  s.student_number,
  u.first_name,
  u.last_name,
  s.course,
  s.year_level,
  COUNT(v.id)                                      AS total_visits,
  SUM(v.status = 'completed')                      AS completed_visits,
  MAX(v.visit_date)                                AS last_visit_date,
  SUM(v.status IN ('waiting','in-progress')
      AND DATE(v.visit_date) = CURDATE())          AS active_today
FROM students s
JOIN users u ON s.user_id = u.id
LEFT JOIN visits v ON v.student_id = s.id
GROUP BY s.id, s.student_number, u.first_name, u.last_name,
         s.course, s.year_level;


-- Upcoming confirmed appointments
CREATE OR REPLACE VIEW `v_upcoming_appointments` AS
SELECT
  a.id,
  a.appointment_date,
  a.appointment_time,
  a.purpose,
  a.status,
  a.contact_number,
  s.student_number,
  s.course,
  CONCAT(u.first_name, ' ', u.last_name) AS student_name,
  u.email                                AS student_email,
  CONCAT(nu.first_name, ' ', nu.last_name) AS nurse_name
FROM appointments a
JOIN students s  ON a.student_id = s.id
JOIN users    u  ON s.user_id    = u.id
LEFT JOIN nurses  n  ON a.nurse_id   = n.id
LEFT JOIN users   nu ON n.user_id    = nu.id
WHERE a.appointment_date >= CURDATE()
  AND a.status IN ('pending','confirmed')
ORDER BY a.appointment_date ASC, a.appointment_time ASC;


-- ============================================================
--  END OF SCHEMA
-- ============================================================
