-- Sustainability and Wellness Tracking Database Schema
-- Adds tables for eco metrics, wellness logs, and gamification

-- Sustainability metrics tracking
CREATE TABLE IF NOT EXISTS `sustainability_metrics` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `metric_type` VARCHAR(50) NOT NULL COMMENT 'paperless, recycling, digital_submission',
  `points_earned` INT DEFAULT 0,
  `description` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_type` (`user_id`, `metric_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Wellness tracking logs
CREATE TABLE IF NOT EXISTS `wellness_logs` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `log_date` DATE NOT NULL,
  `mood` ENUM('excellent', 'good', 'neutral', 'stressed', 'poor'),
  `stress_level` INT COMMENT '1-10 scale',
  `energy_level` INT COMMENT '1-10 scale',
  `sleep_hours` DECIMAL(4, 2),
  `notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user_date` (`user_id`, `log_date`),
  INDEX `idx_date` (`log_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User sustainability and wellness scores
CREATE TABLE IF NOT EXISTS `user_wellness_scores` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `eco_points` INT DEFAULT 0,
  `wellness_score` DECIMAL(5, 2) DEFAULT 0.00 COMMENT '0-100 scale',
  `badges_earned` JSON COMMENT 'Array of badge IDs',
  `last_calculated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Gamification badges
CREATE TABLE IF NOT EXISTS `gamification_badges` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `badge_type` ENUM('eco', 'wellness', 'attendance', 'academic') NOT NULL,
  `criteria` JSON COMMENT 'Achievement criteria',
  `icon_url` VARCHAR(255),
  `points_value` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User badge achievements
CREATE TABLE IF NOT EXISTS `user_badges` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `badge_id` INT NOT NULL,
  `earned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`badge_id`) REFERENCES `gamification_badges`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user_badge` (`user_id`, `badge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sustainability challenges
CREATE TABLE IF NOT EXISTS `sustainability_challenges` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `goal_type` VARCHAR(50) NOT NULL,
  `goal_target` INT NOT NULL,
  `reward_points` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Challenge participation
CREATE TABLE IF NOT EXISTS `challenge_participants` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `challenge_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `progress` INT DEFAULT 0,
  `completed` TINYINT(1) DEFAULT 0,
  `completed_at` DATETIME,
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`challenge_id`) REFERENCES `sustainability_challenges`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_challenge_user` (`challenge_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
