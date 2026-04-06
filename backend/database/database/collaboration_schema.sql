-- Collaboration Platform Database Schema
-- Adds tables for video calls, whiteboards, and project management

-- Collaboration rooms
CREATE TABLE IF NOT EXISTS `collaboration_rooms` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `room_name` VARCHAR(100) NOT NULL,
  `room_type` ENUM('video_call', 'whiteboard', 'project') NOT NULL,
  `creator_id` INT NOT NULL,
  `class_id` INT,
  `room_code` VARCHAR(20) NOT NULL,
  `max_participants` INT DEFAULT 50,
  `is_active` TINYINT(1) DEFAULT 1,
  `scheduled_start` DATETIME,
  `scheduled_end` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `closed_at` DATETIME,
  FOREIGN KEY (`creator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_room_code` (`room_code`),
  INDEX `idx_active` (`is_active`, `scheduled_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Room participants
CREATE TABLE IF NOT EXISTS `room_participants` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `room_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role` ENUM('host', 'moderator', 'participant') DEFAULT 'participant',
  `joined_at` DATETIME,
  `left_at` DATETIME,
  `duration_minutes` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES `collaboration_rooms`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_room_user` (`room_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Whiteboard sessions
CREATE TABLE IF NOT EXISTS `whiteboard_sessions` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `room_id` INT NOT NULL,
  `whiteboard_data` LONGTEXT COMMENT 'JSON canvas data',
  `version` INT DEFAULT 1,
  `last_modified_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES `collaboration_rooms`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`last_modified_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Project management boards
CREATE TABLE IF NOT EXISTS `project_boards` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `room_id` INT NOT NULL,
  `board_name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES `collaboration_rooms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Project tasks
CREATE TABLE IF NOT EXISTS `project_tasks` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `board_id` INT NOT NULL,
  `task_title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `assigned_to` INT,
  `status` ENUM('todo', 'in_progress', 'review', 'done') DEFAULT 'todo',
  `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
  `due_date` DATE,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`board_id`) REFERENCES `project_boards`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_status` (`status`, `due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Call recordings
CREATE TABLE IF NOT EXISTS `call_recordings` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `room_id` INT NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `duration_seconds` INT,
  `file_size_mb` DECIMAL(10, 2),
  `consent_obtained` TINYINT(1) DEFAULT 0,
  `is_available` TINYINT(1) DEFAULT 1,
  `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME,
  FOREIGN KEY (`room_id`) REFERENCES `collaboration_rooms`(`id`) ON DELETE CASCADE,
  INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
