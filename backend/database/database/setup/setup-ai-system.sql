-- Create necessary tables for AI user creation and class management

-- AI Creation Log Table
CREATE TABLE IF NOT EXISTS `ai_creation_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `source_data` text DEFAULT NULL,
  `ai_confidence` decimal(3,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Account Activations Table
CREATE TABLE IF NOT EXISTS `account_activations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activation_method` varchar(50) NOT NULL DEFAULT 'manual',
  `activated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_activated_at` (`activated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Classes Table
CREATE TABLE IF NOT EXISTS `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_name` varchar(255) NOT NULL,
  `class_code` varchar(50) NOT NULL,
  `grade_level` varchar(20) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `capacity` int(11) DEFAULT 30,
  `academic_year` int(4) NOT NULL,
  `semester` varchar(20) DEFAULT '1',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_class_code` (`class_code`),
  KEY `idx_grade_level` (`grade_level`),
  KEY `idx_teacher_id` (`teacher_id`),
  KEY `idx_academic_year` (`academic_year`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_classes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Class Enrollments Table
CREATE TABLE IF NOT EXISTS `class_enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `enrollment_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `grade_achieved` varchar(5) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_class_student` (`class_id`, `student_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_status` (`status`),
  KEY `idx_enrollment_date` (`enrollment_date`),
  CONSTRAINT `fk_enrollments_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enrollments_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add created_by column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS `created_by` varchar(50) DEFAULT 'manual';

-- Add email_verified_at column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS `email_verified_at` timestamp NULL DEFAULT NULL;

-- Add password_set_at column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS `password_set_at` timestamp NULL DEFAULT NULL;

-- Add date_of_birth column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS `date_of_birth` date DEFAULT NULL;

-- Add address column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS `address` text DEFAULT NULL;

-- Add phone column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS `phone` varchar(20) DEFAULT NULL;

-- Add department column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS `department` varchar(255) DEFAULT NULL;

-- Add employee_id column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS `employee_id` varchar(50) DEFAULT NULL;

-- Add full_name column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS `full_name` varchar(255) DEFAULT NULL;
