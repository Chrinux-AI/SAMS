-- Mobile App Integration Database Schema
-- Adds tables for mobile app sync, offline data, and push notifications

-- Device registration for push notifications
CREATE TABLE IF NOT EXISTS `mobile_devices` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `device_type` ENUM('ios', 'android') NOT NULL,
  `device_token` VARCHAR(255) NOT NULL,
  `device_name` VARCHAR(100),
  `app_version` VARCHAR(20),
  `os_version` VARCHAR(20),
  `last_sync` DATETIME,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_device` (`user_id`, `device_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Offline sync queue for mobile apps
CREATE TABLE IF NOT EXISTS `offline_sync_queue` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `device_id` INT NOT NULL,
  `action_type` VARCHAR(50) NOT NULL,
  `data` JSON NOT NULL,
  `sync_status` ENUM('pending', 'synced', 'failed') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `synced_at` DATETIME,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`device_id`) REFERENCES `mobile_devices`(`id`) ON DELETE CASCADE,
  INDEX `idx_sync_status` (`sync_status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Push notification logs
CREATE TABLE IF NOT EXISTS `push_notifications` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `device_id` INT,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `category` VARCHAR(50),
  `priority` ENUM('low', 'normal', 'high') DEFAULT 'normal',
  `payload` JSON,
  `sent_at` DATETIME,
  `delivered_at` DATETIME,
  `opened_at` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`device_id`) REFERENCES `mobile_devices`(`id`) ON DELETE SET NULL,
  INDEX `idx_user_sent` (`user_id`, `sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Geofencing zones for auto check-in
CREATE TABLE IF NOT EXISTS `geofencing_zones` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `latitude` DECIMAL(10, 8) NOT NULL,
  `longitude` DECIMAL(11, 8) NOT NULL,
  `radius` INT NOT NULL COMMENT 'Radius in meters',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
