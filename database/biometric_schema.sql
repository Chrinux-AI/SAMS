-- Biometric Attendance System Database Schema
-- Adds tables for biometric data, verification logs, and enrollment

-- Biometric enrollment data (hashed for security)
CREATE TABLE IF NOT EXISTS `biometric_enrollment` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `biometric_type` ENUM('facial', 'fingerprint', 'voice') NOT NULL,
  `biometric_hash` TEXT NOT NULL COMMENT 'Encrypted biometric template',
  `enrollment_quality` DECIMAL(5, 2) COMMENT 'Quality score 0-100',
  `is_active` TINYINT(1) DEFAULT 1,
  `enrolled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user_biometric` (`user_id`, `biometric_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Biometric verification logs
CREATE TABLE IF NOT EXISTS `biometric_verification_logs` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `biometric_type` ENUM('facial', 'fingerprint', 'voice') NOT NULL,
  `verification_result` ENUM('success', 'failed', 'liveness_failed') NOT NULL,
  `confidence_score` DECIMAL(5, 2) COMMENT 'Match confidence 0-100',
  `ip_address` VARCHAR(45),
  `device_info` VARCHAR(255),
  `verified_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_result` (`user_id`, `verification_result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Biometric settings per class
CREATE TABLE IF NOT EXISTS `class_biometric_settings` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `class_id` INT NOT NULL,
  `biometric_enabled` TINYINT(1) DEFAULT 0,
  `require_liveness` TINYINT(1) DEFAULT 1,
  `fallback_method` ENUM('qr', 'manual', 'id') DEFAULT 'qr',
  `min_confidence` DECIMAL(5, 2) DEFAULT 85.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_class` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
