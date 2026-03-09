-- SAMS schema snapshot
-- Generated at 2026-03-08T22:51:14-04:00
-- Database: attendance_system

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table: account_activations
-- --------------------------------------------------------
DROP TABLE IF EXISTS `account_activations`;
CREATE TABLE `account_activations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activation_method` varchar(50) NOT NULL DEFAULT 'otp',
  `activated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: ai_analytics
-- --------------------------------------------------------
DROP TABLE IF EXISTS `ai_analytics`;
CREATE TABLE `ai_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_name` varchar(100) NOT NULL,
  `accuracy_rate` decimal(5,2) DEFAULT 0.00,
  `status` enum('active','inactive','training') DEFAULT 'inactive',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `model_name` (`model_name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: ai_conversations
-- --------------------------------------------------------
DROP TABLE IF EXISTS `ai_conversations`;
CREATE TABLE `ai_conversations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `message` text NOT NULL,
  `response` text NOT NULL,
  `intent` varchar(60) NOT NULL DEFAULT 'general',
  `risk_level` varchar(20) NOT NULL DEFAULT 'low',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ai_conv_tenant_user_time` (`tenant_id`,`user_id`,`created_at`),
  KEY `idx_ai_conv_intent` (`intent`),
  CONSTRAINT `fk_ai_conv_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `school_tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: ai_rate_limits
-- --------------------------------------------------------
DROP TABLE IF EXISTS `ai_rate_limits`;
CREATE TABLE `ai_rate_limits` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `requests_minute` int(11) NOT NULL DEFAULT 0,
  `requests_hour` int(11) NOT NULL DEFAULT 0,
  `minute_window_start` datetime NOT NULL,
  `hour_window_start` datetime NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_rate_limit` (`tenant_id`,`user_id`),
  CONSTRAINT `fk_ai_rl_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `school_tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: alert_acknowledgments
-- --------------------------------------------------------
DROP TABLE IF EXISTS `alert_acknowledgments`;
CREATE TABLE `alert_acknowledgments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alert_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `acknowledged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ack` (`alert_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_alert` (`alert_id`),
  CONSTRAINT `alert_acknowledgments_ibfk_1` FOREIGN KEY (`alert_id`) REFERENCES `emergency_alerts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `alert_acknowledgments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: asset_categories
-- --------------------------------------------------------
DROP TABLE IF EXISTS `asset_categories`;
CREATE TABLE `asset_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_code` (`category_code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: assets
-- --------------------------------------------------------
DROP TABLE IF EXISTS `assets`;
CREATE TABLE `assets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_code` varchar(50) NOT NULL,
  `asset_name` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `manufacturer` varchar(100) DEFAULT NULL,
  `model_number` varchar(50) DEFAULT NULL,
  `serial_number` varchar(50) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(10,2) DEFAULT NULL,
  `supplier_name` varchar(100) DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL COMMENT 'Department/Room where located',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'Staff ID if assigned',
  `condition` enum('excellent','good','fair','poor','damaged') DEFAULT 'good',
  `status` enum('in_use','available','maintenance','retired','lost') DEFAULT 'available',
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_code` (`asset_code`),
  KEY `category_id` (`category_id`),
  KEY `idx_asset_code` (`asset_code`),
  KEY `idx_status` (`status`),
  CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: attendance
-- --------------------------------------------------------
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','late','excused','half_day') DEFAULT 'present',
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `marked_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`student_id`,`date`),
  KEY `idx_date` (`date`),
  KEY `idx_student` (`student_id`),
  KEY `idx_class` (`class_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: attendance_biometric
-- --------------------------------------------------------
DROP TABLE IF EXISTS `attendance_biometric`;
CREATE TABLE `attendance_biometric` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `credential_id` varchar(255) DEFAULT NULL,
  `scan_type` enum('fingerprint','face','card') DEFAULT 'fingerprint',
  `scan_data` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('verified','rejected','suspicious') DEFAULT 'verified',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `attendance_biometric_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: attendance_records
-- --------------------------------------------------------
DROP TABLE IF EXISTS `attendance_records`;
CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent','late','excused') DEFAULT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: behavior_logs
-- --------------------------------------------------------
DROP TABLE IF EXISTS `behavior_logs`;
CREATE TABLE `behavior_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `type` enum('positive','negative','neutral') NOT NULL,
  `severity` enum('minor','moderate','major','severe') DEFAULT 'minor',
  `category` varchar(100) DEFAULT NULL COMMENT 'e.g., Participation, Conduct, Academic, etc.',
  `incident_description` text NOT NULL,
  `action_taken` text DEFAULT NULL,
  `parent_notified` tinyint(1) DEFAULT 0,
  `admin_notified` tinyint(1) DEFAULT 0,
  `incident_date` date NOT NULL,
  `incident_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `witnesses` text DEFAULT NULL COMMENT 'Other staff who witnessed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_teacher` (`teacher_id`),
  KEY `idx_type` (`type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_date` (`incident_date`),
  CONSTRAINT `behavior_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `behavior_logs_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: biometric_auth_logs
-- --------------------------------------------------------
DROP TABLE IF EXISTS `biometric_auth_logs`;
CREATE TABLE `biometric_auth_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `credential_id` varchar(255) DEFAULT NULL,
  `auth_type` enum('fingerprint','face','device') DEFAULT 'fingerprint',
  `status` enum('success','failed','denied') DEFAULT 'success',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `biometric_auth_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: biometric_credentials
-- --------------------------------------------------------
DROP TABLE IF EXISTS `biometric_credentials`;
CREATE TABLE `biometric_credentials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `credential_id` varchar(255) NOT NULL,
  `public_key` text NOT NULL,
  `counter` int(11) DEFAULT 0,
  `device_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credential_id` (`credential_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_credential_id` (`credential_id`),
  CONSTRAINT `biometric_credentials_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: biometric_enrollment
-- --------------------------------------------------------
DROP TABLE IF EXISTS `biometric_enrollment`;
CREATE TABLE `biometric_enrollment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `biometric_type` enum('facial','fingerprint','voice') NOT NULL,
  `biometric_hash` text NOT NULL COMMENT 'Encrypted biometric template',
  `enrollment_quality` decimal(5,2) DEFAULT NULL COMMENT 'Quality score 0-100',
  `is_active` tinyint(1) DEFAULT 1,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_biometric` (`user_id`,`biometric_type`),
  CONSTRAINT `biometric_enrollment_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: biometric_verification_logs
-- --------------------------------------------------------
DROP TABLE IF EXISTS `biometric_verification_logs`;
CREATE TABLE `biometric_verification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `biometric_type` enum('facial','fingerprint','voice') NOT NULL,
  `verification_result` enum('success','failed','liveness_failed') NOT NULL,
  `confidence_score` decimal(5,2) DEFAULT NULL COMMENT 'Match confidence 0-100',
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  `verified_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_result` (`user_id`,`verification_result`),
  CONSTRAINT `biometric_verification_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: blockchain_records
-- --------------------------------------------------------
DROP TABLE IF EXISTS `blockchain_records`;
CREATE TABLE `blockchain_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `record_type` enum('attendance','grade','certificate','achievement') NOT NULL,
  `record_id` int(11) NOT NULL COMMENT 'ID of the original record',
  `user_id` int(11) NOT NULL,
  `record_hash` varchar(64) NOT NULL COMMENT 'SHA-256 hash of record data',
  `blockchain_tx_hash` varchar(66) DEFAULT NULL COMMENT 'Transaction hash on blockchain',
  `blockchain_network` varchar(50) DEFAULT 'ethereum',
  `block_number` bigint(20) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_record` (`record_type`,`record_id`),
  KEY `idx_tx_hash` (`blockchain_tx_hash`),
  CONSTRAINT `blockchain_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: blockchain_verifications
-- --------------------------------------------------------
DROP TABLE IF EXISTS `blockchain_verifications`;
CREATE TABLE `blockchain_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `verification_code` varchar(32) NOT NULL,
  `record_type` varchar(50) NOT NULL,
  `record_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`record_data`)),
  `requester_ip` varchar(45) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code` (`verification_code`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: budget_items
-- --------------------------------------------------------
DROP TABLE IF EXISTS `budget_items`;
CREATE TABLE `budget_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `fiscal_year` varchar(10) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `budgeted_amount` decimal(12,2) DEFAULT 0.00,
  `actual_amount` decimal(12,2) DEFAULT 0.00,
  `variance` decimal(12,2) GENERATED ALWAYS AS (`budgeted_amount` - `actual_amount`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_year` (`fiscal_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: call_recordings
-- --------------------------------------------------------
DROP TABLE IF EXISTS `call_recordings`;
CREATE TABLE `call_recordings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `duration_seconds` int(11) DEFAULT NULL,
  `file_size_mb` decimal(10,2) DEFAULT NULL,
  `consent_obtained` tinyint(1) DEFAULT 0,
  `is_available` tinyint(1) DEFAULT 1,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `call_recordings_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `collaboration_rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: challenge_participants
-- --------------------------------------------------------
DROP TABLE IF EXISTS `challenge_participants`;
CREATE TABLE `challenge_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `challenge_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `progress` int(11) DEFAULT 0,
  `completed` tinyint(1) DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_challenge_user` (`challenge_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `challenge_participants_ibfk_1` FOREIGN KEY (`challenge_id`) REFERENCES `sustainability_challenges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `challenge_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: chat_contacts
-- --------------------------------------------------------
DROP TABLE IF EXISTS `chat_contacts`;
CREATE TABLE `chat_contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `contact_user_id` int(11) NOT NULL,
  `nickname` varchar(100) DEFAULT NULL COMMENT 'Custom name for contact',
  `is_favorite` tinyint(1) DEFAULT 0,
  `is_blocked` tinyint(1) DEFAULT 0,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_contact` (`user_id`,`contact_user_id`),
  KEY `contact_user_id` (`contact_user_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_favorite` (`is_favorite`),
  KEY `idx_blocked` (`is_blocked`),
  CONSTRAINT `chat_contacts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_contacts_ibfk_2` FOREIGN KEY (`contact_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: chat_recent_contacts
-- --------------------------------------------------------
DROP TABLE IF EXISTS `chat_recent_contacts`;
CREATE TABLE `chat_recent_contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `contact_user_id` int(11) NOT NULL,
  `last_interaction_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `interaction_count` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_recent` (`user_id`,`contact_user_id`),
  KEY `contact_user_id` (`contact_user_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_last_interaction` (`last_interaction_at`),
  CONSTRAINT `chat_recent_contacts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_recent_contacts_ibfk_2` FOREIGN KEY (`contact_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: chat_room_members
-- --------------------------------------------------------
DROP TABLE IF EXISTS `chat_room_members`;
CREATE TABLE `chat_room_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('admin','moderator','member') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_member` (`room_id`,`user_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `chat_room_members_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_room_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: chat_room_messages
-- --------------------------------------------------------
DROP TABLE IF EXISTS `chat_room_messages`;
CREATE TABLE `chat_room_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `message_type` enum('text','file','image','link') DEFAULT 'text',
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reply_to_message_id` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `is_edited` tinyint(1) DEFAULT 0,
  `edited_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_room` (`room_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_reply` (`reply_to_message_id`),
  CONSTRAINT `chat_room_messages_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_room_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: chat_rooms
-- --------------------------------------------------------
DROP TABLE IF EXISTS `chat_rooms`;
CREATE TABLE `chat_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `room_type` enum('class','study_group','club','general') DEFAULT 'general',
  `created_by` int(11) NOT NULL,
  `max_members` int(11) DEFAULT 50,
  `is_private` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`room_type`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `chat_rooms_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: class_biometric_settings
-- --------------------------------------------------------
DROP TABLE IF EXISTS `class_biometric_settings`;
CREATE TABLE `class_biometric_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `biometric_enabled` tinyint(1) DEFAULT 0,
  `require_liveness` tinyint(1) DEFAULT 1,
  `fallback_method` enum('qr','manual','id') DEFAULT 'qr',
  `min_confidence` decimal(5,2) DEFAULT 85.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_class` (`class_id`),
  CONSTRAINT `class_biometric_settings_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: class_enrollments
-- --------------------------------------------------------
DROP TABLE IF EXISTS `class_enrollments`;
CREATE TABLE `class_enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `enrolled_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','dropped','completed') NOT NULL DEFAULT 'active',
  `enrolled_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`class_id`,`student_id`),
  KEY `idx_class` (`class_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: classes
-- --------------------------------------------------------
DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_name` varchar(100) NOT NULL,
  `grade_level` varchar(20) NOT NULL,
  `section` varchar(10) DEFAULT 'A',
  `capacity` int(11) DEFAULT 35,
  `current_students` int(11) DEFAULT 0,
  `class_teacher_id` int(11) DEFAULT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `academic_year` varchar(20) NOT NULL DEFAULT '2024/2025',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_grade_level` (`grade_level`),
  KEY `idx_academic_year` (`academic_year`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: collaboration_rooms
-- --------------------------------------------------------
DROP TABLE IF EXISTS `collaboration_rooms`;
CREATE TABLE `collaboration_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_name` varchar(100) NOT NULL,
  `room_type` enum('video_call','whiteboard','project') NOT NULL,
  `creator_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `room_code` varchar(20) NOT NULL,
  `max_participants` int(11) DEFAULT 50,
  `is_active` tinyint(1) DEFAULT 1,
  `scheduled_start` datetime DEFAULT NULL,
  `scheduled_end` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_room_code` (`room_code`),
  KEY `creator_id` (`creator_id`),
  KEY `class_id` (`class_id`),
  KEY `idx_active` (`is_active`,`scheduled_start`),
  CONSTRAINT `collaboration_rooms_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `collaboration_rooms_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: contact_custom_names
-- --------------------------------------------------------
DROP TABLE IF EXISTS `contact_custom_names`;
CREATE TABLE `contact_custom_names` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `custom_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_contact` (`user_id`,`contact_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_contact` (`contact_id`),
  CONSTRAINT `contact_custom_names_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_custom_names_ibfk_2` FOREIGN KEY (`contact_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: conversation_messages
-- --------------------------------------------------------
DROP TABLE IF EXISTS `conversation_messages`;
CREATE TABLE `conversation_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `attachments` text DEFAULT NULL COMMENT 'JSON array of file paths',
  `is_read_by` text DEFAULT NULL COMMENT 'JSON array of user IDs who have read',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reply_to_message_id` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `is_edited` tinyint(1) DEFAULT 0,
  `edited_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_conversation` (`conversation_id`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_reply` (`reply_to_message_id`),
  CONSTRAINT `conversation_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: conversation_participants
-- --------------------------------------------------------
DROP TABLE IF EXISTS `conversation_participants`;
CREATE TABLE `conversation_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_read_at` timestamp NULL DEFAULT NULL,
  `is_muted` tinyint(1) DEFAULT 0,
  `is_archived` tinyint(1) DEFAULT 0,
  `is_pinned` tinyint(1) DEFAULT 0,
  `notification_enabled` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_participant` (`conversation_id`,`user_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_archived` (`is_archived`),
  KEY `idx_pinned` (`is_pinned`),
  CONSTRAINT `conversation_participants_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: conversations
-- --------------------------------------------------------
DROP TABLE IF EXISTS `conversations`;
CREATE TABLE `conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL,
  `started_by` int(11) NOT NULL,
  `participants` text NOT NULL COMMENT 'JSON array of user IDs',
  `last_message_at` timestamp NULL DEFAULT NULL,
  `is_group` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_archived` tinyint(1) DEFAULT 0,
  `is_muted` tinyint(1) DEFAULT 0,
  `last_message_text` text DEFAULT NULL,
  `last_message_sender_id` int(11) DEFAULT NULL,
  `unread_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `started_by` (`started_by`),
  KEY `idx_participants` (`participants`(100)),
  KEY `idx_last_message` (`last_message_at`),
  KEY `idx_archived` (`is_archived`),
  CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`started_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: departments
-- --------------------------------------------------------
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_name` varchar(100) NOT NULL,
  `department_code` varchar(20) NOT NULL,
  `head_of_department` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `department_code` (`department_code`),
  KEY `idx_department_code` (`department_code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: digital_certificates
-- --------------------------------------------------------
DROP TABLE IF EXISTS `digital_certificates`;
CREATE TABLE `digital_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `certificate_type` varchar(100) NOT NULL COMMENT 'diploma, attendance_perfect, achievement',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `issued_date` date NOT NULL,
  `nft_token_id` varchar(100) DEFAULT NULL,
  `nft_contract_address` varchar(42) DEFAULT NULL,
  `metadata_uri` varchar(500) DEFAULT NULL,
  `blockchain_record_id` int(11) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `blockchain_record_id` (`blockchain_record_id`),
  KEY `idx_user_type` (`user_id`,`certificate_type`),
  CONSTRAINT `digital_certificates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `digital_certificates_ibfk_2` FOREIGN KEY (`blockchain_record_id`) REFERENCES `blockchain_records` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: emergency_alerts
-- --------------------------------------------------------
DROP TABLE IF EXISTS `emergency_alerts`;
CREATE TABLE `emergency_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `severity` enum('info','warning','critical') DEFAULT 'info',
  `target_roles` varchar(100) DEFAULT NULL COMMENT 'Comma-separated, NULL = all',
  `send_email` tinyint(1) DEFAULT 1,
  `send_sms` tinyint(1) DEFAULT 0,
  `requires_acknowledgment` tinyint(1) DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_severity` (`severity`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `emergency_alerts_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: exam_schedule
-- --------------------------------------------------------
DROP TABLE IF EXISTS `exam_schedule`;
CREATE TABLE `exam_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `examination_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `exam_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `duration_minutes` int(11) NOT NULL,
  `total_marks` decimal(6,2) DEFAULT 100.00,
  `passing_marks` decimal(6,2) DEFAULT 40.00,
  `room_number` varchar(50) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject_id` (`subject_id`),
  KEY `idx_exam_date` (`exam_date`),
  KEY `idx_examination` (`examination_id`),
  CONSTRAINT `exam_schedule_ibfk_1` FOREIGN KEY (`examination_id`) REFERENCES `examinations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exam_schedule_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: examinations
-- --------------------------------------------------------
DROP TABLE IF EXISTS `examinations`;
CREATE TABLE `examinations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_name` varchar(100) NOT NULL,
  `exam_type` enum('midterm','final','quarterly','monthly','weekly','surprise') DEFAULT 'midterm',
  `academic_year` varchar(20) NOT NULL,
  `grade_level` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `is_published` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_academic_year` (`academic_year`),
  KEY `idx_grade` (`grade_level`),
  KEY `idx_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: expense_approvals
-- --------------------------------------------------------
DROP TABLE IF EXISTS `expense_approvals`;
CREATE TABLE `expense_approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `expense_id` int(11) NOT NULL,
  `approver_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `comments` text DEFAULT NULL,
  `acted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `expense_id` (`expense_id`),
  CONSTRAINT `expense_approvals_ibfk_1` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: expenses
-- --------------------------------------------------------
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `expense_number` varchar(50) NOT NULL,
  `expense_date` date NOT NULL,
  `expense_category` enum('salary','utilities','maintenance','supplies','transport','food','infrastructure','equipment','marketing','other') NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','cheque','card','online','bank_transfer') NOT NULL,
  `vendor_name` varchar(100) DEFAULT NULL,
  `bill_number` varchar(50) DEFAULT NULL,
  `bill_file` varchar(255) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `paid_by` int(11) NOT NULL,
  `status` enum('pending','approved','paid','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `expense_number` (`expense_number`),
  KEY `idx_expense_date` (`expense_date`),
  KEY `idx_category` (`expense_category`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: fee_invoices
-- --------------------------------------------------------
DROP TABLE IF EXISTS `fee_invoices`;
CREATE TABLE `fee_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `student_id` int(11) NOT NULL,
  `invoice_number` varchar(30) DEFAULT NULL,
  `fee_structure_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('pending','partial','paid','overdue','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: fee_payments
-- --------------------------------------------------------
DROP TABLE IF EXISTS `fee_payments`;
CREATE TABLE `fee_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fee_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_method` enum('credit_card','debit_card','bank_transfer','paypal','cash','check') NOT NULL,
  `payment_date` datetime NOT NULL,
  `payment_status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'completed',
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fee_payments` (`fee_id`),
  KEY `idx_transaction` (`transaction_id`),
  KEY `idx_payment_date` (`payment_date`),
  CONSTRAINT `fee_payments_ibfk_1` FOREIGN KEY (`fee_id`) REFERENCES `student_fees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: fee_structures
-- --------------------------------------------------------
DROP TABLE IF EXISTS `fee_structures`;
CREATE TABLE `fee_structures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fee_name` varchar(100) NOT NULL,
  `fee_type` enum('tuition','admission','annual','exam','transport','hostel','library','sports','lab','misc') NOT NULL,
  `grade_level` varchar(20) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `frequency` enum('one_time','monthly','quarterly','half_yearly','yearly') DEFAULT 'one_time',
  `due_day` int(2) DEFAULT NULL COMMENT 'Day of month for recurring fees',
  `description` text DEFAULT NULL,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `academic_year` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fee_type` (`fee_type`),
  KEY `idx_grade` (`grade_level`),
  KEY `idx_academic_year` (`academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: forum_bans
-- --------------------------------------------------------
DROP TABLE IF EXISTS `forum_bans`;
CREATE TABLE `forum_bans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `banned_by` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'NULL = permanent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: forum_categories
-- --------------------------------------------------------
DROP TABLE IF EXISTS `forum_categories`;
CREATE TABLE `forum_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'comments',
  `color` varchar(20) DEFAULT '#00f3ff',
  `allowed_roles` varchar(100) DEFAULT NULL COMMENT 'Comma-separated roles, NULL = all',
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_order` (`display_order`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: forum_posts
-- --------------------------------------------------------
DROP TABLE IF EXISTS `forum_posts`;
CREATE TABLE `forum_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `thread_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `is_reported` tinyint(1) DEFAULT 0,
  `parent_post_id` int(11) DEFAULT NULL COMMENT 'For nested replies',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `parent_post_id` (`parent_post_id`),
  KEY `idx_thread` (`thread_id`),
  KEY `idx_reported` (`is_reported`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `forum_posts_ibfk_1` FOREIGN KEY (`thread_id`) REFERENCES `forum_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_posts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_posts_ibfk_3` FOREIGN KEY (`parent_post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: forum_reports
-- --------------------------------------------------------
DROP TABLE IF EXISTS `forum_reports`;
CREATE TABLE `forum_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reporter_id` int(11) NOT NULL,
  `thread_id` int(11) DEFAULT NULL,
  `post_id` int(11) DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `status` enum('pending','reviewed','resolved','dismissed') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `reporter_id` (`reporter_id`),
  KEY `thread_id` (`thread_id`),
  KEY `post_id` (`post_id`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `forum_reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_reports_ibfk_2` FOREIGN KEY (`thread_id`) REFERENCES `forum_threads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_reports_ibfk_3` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_reports_ibfk_4` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: forum_threads
-- --------------------------------------------------------
DROP TABLE IF EXISTS `forum_threads`;
CREATE TABLE `forum_threads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `is_reported` tinyint(1) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `reply_count` int(11) DEFAULT 0,
  `last_activity_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_pinned` (`is_pinned`),
  KEY `idx_activity` (`last_activity_at`),
  KEY `idx_reported` (`is_reported`),
  CONSTRAINT `forum_threads_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `forum_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forum_threads_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: forum_user_stats
-- --------------------------------------------------------
DROP TABLE IF EXISTS `forum_user_stats`;
CREATE TABLE `forum_user_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `posts_count` int(11) DEFAULT 0,
  `threads_count` int(11) DEFAULT 0,
  `warnings_count` int(11) DEFAULT 0,
  `reputation` int(11) DEFAULT 0,
  `last_activity` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: forum_warnings
-- --------------------------------------------------------
DROP TABLE IF EXISTS `forum_warnings`;
CREATE TABLE `forum_warnings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `issued_by` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `severity` enum('mild','moderate','severe') DEFAULT 'mild',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: gamification_badges
-- --------------------------------------------------------
DROP TABLE IF EXISTS `gamification_badges`;
CREATE TABLE `gamification_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `badge_type` enum('eco','wellness','attendance','academic') NOT NULL,
  `criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Achievement criteria' CHECK (json_valid(`criteria`)),
  `icon_url` varchar(255) DEFAULT NULL,
  `points_value` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: geofencing_zones
-- --------------------------------------------------------
DROP TABLE IF EXISTS `geofencing_zones`;
CREATE TABLE `geofencing_zones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `radius` int(11) NOT NULL COMMENT 'Radius in meters',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: google_form_submissions
-- --------------------------------------------------------
DROP TABLE IF EXISTS `google_form_submissions`;
CREATE TABLE `google_form_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form_response_id` varchar(255) DEFAULT NULL,
  `raw_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_data`)),
  `extracted_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extracted_data`)),
  `processing_status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_user_id` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`processing_status`),
  KEY `idx_user` (`created_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: grading_schemes
-- --------------------------------------------------------
DROP TABLE IF EXISTS `grading_schemes`;
CREATE TABLE `grading_schemes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scheme_name` varchar(100) NOT NULL,
  `min_percentage` decimal(5,2) NOT NULL,
  `max_percentage` decimal(5,2) NOT NULL,
  `grade` varchar(5) NOT NULL,
  `grade_point` decimal(3,2) DEFAULT NULL,
  `description` varchar(100) DEFAULT NULL,
  `is_passing` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_percentage` (`min_percentage`,`max_percentage`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: hostel_mess
-- --------------------------------------------------------
DROP TABLE IF EXISTS `hostel_mess`;
CREATE TABLE `hostel_mess` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hostel_id` int(11) NOT NULL,
  `menu_date` date NOT NULL,
  `meal_type` enum('breakfast','lunch','snacks','dinner') NOT NULL,
  `menu_items` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_menu_date` (`menu_date`),
  KEY `idx_hostel` (`hostel_id`),
  CONSTRAINT `hostel_mess_ibfk_1` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: hostel_rooms
-- --------------------------------------------------------
DROP TABLE IF EXISTS `hostel_rooms`;
CREATE TABLE `hostel_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hostel_id` int(11) NOT NULL,
  `room_number` varchar(20) NOT NULL,
  `floor_number` int(2) DEFAULT NULL,
  `room_type` enum('single','double','triple','quad','dormitory') DEFAULT 'double',
  `capacity` int(2) NOT NULL,
  `current_occupancy` int(2) DEFAULT 0,
  `facilities` text DEFAULT NULL COMMENT 'JSON array of facilities',
  `rent_amount` decimal(8,2) DEFAULT NULL,
  `status` enum('available','occupied','maintenance','reserved') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_hostel_room` (`hostel_id`,`room_number`),
  KEY `idx_status` (`status`),
  CONSTRAINT `hostel_rooms_ibfk_1` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: hostels
-- --------------------------------------------------------
DROP TABLE IF EXISTS `hostels`;
CREATE TABLE `hostels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hostel_name` varchar(100) NOT NULL,
  `hostel_type` enum('boys','girls','mixed','staff') DEFAULT 'boys',
  `total_floors` int(2) DEFAULT NULL,
  `total_rooms` int(4) DEFAULT NULL,
  `total_capacity` int(4) DEFAULT NULL,
  `warden_name` varchar(100) DEFAULT NULL,
  `warden_contact` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `facilities` text DEFAULT NULL COMMENT 'JSON array of facilities',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: inventory_items
-- --------------------------------------------------------
DROP TABLE IF EXISTS `inventory_items`;
CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_code` varchar(50) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `unit_of_measure` varchar(20) DEFAULT NULL COMMENT 'pieces, kg, liters, etc.',
  `reorder_level` int(11) DEFAULT 0 COMMENT 'Minimum quantity before reorder',
  `current_stock` int(11) DEFAULT 0,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_code` (`item_code`),
  KEY `category_id` (`category_id`),
  KEY `idx_item_code` (`item_code`),
  CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: leave_applications
-- --------------------------------------------------------
DROP TABLE IF EXISTS `leave_applications`;
CREATE TABLE `leave_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `staff_type` enum('teacher','staff') NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(3) NOT NULL,
  `reason` text NOT NULL,
  `substitute_arrangement` text DEFAULT NULL,
  `contact_during_leave` varchar(20) DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `approval_remarks` text DEFAULT NULL,
  `applied_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `leave_type_id` (`leave_type_id`),
  KEY `idx_staff` (`staff_id`,`staff_type`),
  KEY `idx_dates` (`start_date`,`end_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `leave_applications_ibfk_1` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: leave_types
-- --------------------------------------------------------
DROP TABLE IF EXISTS `leave_types`;
CREATE TABLE `leave_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `leave_name` varchar(50) NOT NULL,
  `leave_code` varchar(10) NOT NULL,
  `total_days` int(3) NOT NULL COMMENT 'Annual quota',
  `applicable_to` enum('all','teaching','non_teaching') DEFAULT 'all',
  `is_paid` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leave_code` (`leave_code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: ledger_entries
-- --------------------------------------------------------
DROP TABLE IF EXISTS `ledger_entries`;
CREATE TABLE `ledger_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `entry_date` date NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_code` varchar(20) DEFAULT NULL,
  `type` enum('debit','credit') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `category` enum('income','expense','asset','liability','equity') DEFAULT 'income',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_date` (`entry_date`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: library_book_requests
-- --------------------------------------------------------
DROP TABLE IF EXISTS `library_book_requests`;
CREATE TABLE `library_book_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `request_date` date NOT NULL,
  `request_type` enum('reserve','recommend_purchase') DEFAULT 'reserve',
  `status` enum('pending','approved','issued','rejected','cancelled') DEFAULT 'pending',
  `processed_by` int(11) DEFAULT NULL,
  `processed_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `book_id` (`book_id`),
  KEY `member_id` (`member_id`),
  KEY `idx_request_date` (`request_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `library_book_requests_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `library_books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `library_book_requests_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `library_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: library_books
-- --------------------------------------------------------
DROP TABLE IF EXISTS `library_books`;
CREATE TABLE `library_books` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `isbn` varchar(20) DEFAULT NULL,
  `book_title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `publisher` varchar(100) DEFAULT NULL,
  `publication_year` year(4) DEFAULT NULL,
  `edition` varchar(50) DEFAULT NULL,
  `category` enum('fiction','non_fiction','reference','textbook','magazine','journal','other') DEFAULT 'non_fiction',
  `subject` varchar(100) DEFAULT NULL,
  `language` varchar(50) DEFAULT 'English',
  `total_copies` int(11) DEFAULT 1,
  `available_copies` int(11) DEFAULT 1,
  `rack_number` varchar(20) DEFAULT NULL,
  `price` decimal(8,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `added_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_isbn` (`isbn`),
  KEY `idx_title` (`book_title`),
  KEY `idx_author` (`author`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: library_categories
-- --------------------------------------------------------
DROP TABLE IF EXISTS `library_categories`;
CREATE TABLE `library_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: library_fines
-- --------------------------------------------------------
DROP TABLE IF EXISTS `library_fines`;
CREATE TABLE `library_fines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('pending','paid','waived') DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `transaction_id` (`transaction_id`),
  CONSTRAINT `library_fines_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `library_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: library_issue_return
-- --------------------------------------------------------
DROP TABLE IF EXISTS `library_issue_return`;
CREATE TABLE `library_issue_return` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `days_overdue` int(11) DEFAULT 0,
  `fine_amount` decimal(8,2) DEFAULT 0.00,
  `fine_paid` tinyint(1) DEFAULT 0,
  `issued_by` int(11) NOT NULL,
  `returned_to` int(11) DEFAULT NULL,
  `status` enum('issued','returned','lost','damaged') DEFAULT 'issued',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `book_id` (`book_id`),
  KEY `idx_issue_date` (`issue_date`),
  KEY `idx_status` (`status`),
  KEY `idx_member` (`member_id`),
  CONSTRAINT `library_issue_return_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `library_books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `library_issue_return_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `library_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: library_members
-- --------------------------------------------------------
DROP TABLE IF EXISTS `library_members`;
CREATE TABLE `library_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_type` enum('student','teacher','staff','parent') NOT NULL,
  `member_id` int(11) NOT NULL COMMENT 'ID from respective table',
  `membership_number` varchar(50) NOT NULL,
  `membership_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `max_books_allowed` int(2) DEFAULT 3,
  `max_days_allowed` int(3) DEFAULT 14,
  `deposit_amount` decimal(8,2) DEFAULT 0.00,
  `fine_amount` decimal(8,2) DEFAULT 0.00,
  `status` enum('active','inactive','suspended','expired') DEFAULT 'active',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `membership_number` (`membership_number`),
  KEY `idx_membership_number` (`membership_number`),
  KEY `idx_member_type_id` (`member_type`,`member_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: library_reservations
-- --------------------------------------------------------
DROP TABLE IF EXISTS `library_reservations`;
CREATE TABLE `library_reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `book_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('active','fulfilled','cancelled','expired') DEFAULT 'active',
  `reserved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `book_id` (`book_id`),
  CONSTRAINT `library_reservations_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `library_books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: library_transactions
-- --------------------------------------------------------
DROP TABLE IF EXISTS `library_transactions`;
CREATE TABLE `library_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `book_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('issue','return','renew','reserve') DEFAULT 'issue',
  `issue_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `status` enum('issued','returned','overdue','lost') DEFAULT 'issued',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_book` (`book_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `library_transactions_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `library_books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: lti_configurations
-- --------------------------------------------------------
DROP TABLE IF EXISTS `lti_configurations`;
CREATE TABLE `lti_configurations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lms_platform` varchar(50) NOT NULL COMMENT 'moodle, canvas, blackboard, etc.',
  `lms_name` varchar(100) NOT NULL COMMENT 'Display name for this LMS instance',
  `client_id` varchar(255) NOT NULL COMMENT 'OAuth client ID from LMS',
  `issuer` varchar(500) NOT NULL COMMENT 'LMS issuer URL',
  `deployment_id` varchar(255) NOT NULL COMMENT 'LTI deployment identifier',
  `public_key` text NOT NULL COMMENT 'Public RSA key in PEM format',
  `private_key` text NOT NULL COMMENT 'Private RSA key in PEM format (encrypted)',
  `auth_login_url` varchar(500) DEFAULT NULL COMMENT 'OIDC auth endpoint',
  `auth_token_url` varchar(500) DEFAULT NULL COMMENT 'OAuth token endpoint',
  `keyset_url` varchar(500) DEFAULT NULL COMMENT 'JWK set URL',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Enable/disable this integration',
  `auto_sync_enabled` tinyint(1) DEFAULT 0 COMMENT 'Auto sync attendance to grades',
  `sync_frequency` int(11) DEFAULT 3600 COMMENT 'Sync interval in seconds',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_sync_at` timestamp NULL DEFAULT NULL COMMENT 'Last successful sync timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_lms_client` (`lms_platform`,`client_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_platform` (`lms_platform`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LMS connection configurations';

-- --------------------------------------------------------
-- Table: lti_grade_sync_log
-- --------------------------------------------------------
DROP TABLE IF EXISTS `lti_grade_sync_log`;
CREATE TABLE `lti_grade_sync_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lti_config_id` int(11) NOT NULL COMMENT 'FK to lti_configurations',
  `user_id` int(11) NOT NULL COMMENT 'FK to users (student)',
  `lms_context_id` varchar(255) NOT NULL COMMENT 'LMS course ID',
  `lms_resource_link_id` varchar(255) DEFAULT NULL COMMENT 'Grade column link',
  `attendance_percentage` decimal(5,2) DEFAULT NULL COMMENT 'Calculated attendance %',
  `grade_value` decimal(5,2) DEFAULT NULL COMMENT 'Grade sent to LMS (0-100)',
  `sync_type` enum('manual','auto','bulk') DEFAULT 'auto',
  `status` enum('pending','success','failed') DEFAULT 'pending',
  `error_message` text DEFAULT NULL COMMENT 'Error details if failed',
  `retry_count` int(11) DEFAULT 0 COMMENT 'Number of retry attempts',
  `synced_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lti_config_id` (`lti_config_id`),
  KEY `idx_user_context` (`user_id`,`lms_context_id`),
  KEY `idx_status` (`status`,`retry_count`),
  KEY `idx_sync_date` (`synced_at`),
  CONSTRAINT `lti_grade_sync_log_ibfk_1` FOREIGN KEY (`lti_config_id`) REFERENCES `lti_configurations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lti_grade_sync_log_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Grade passback sync history';

-- --------------------------------------------------------
-- Table: lti_links
-- --------------------------------------------------------
DROP TABLE IF EXISTS `lti_links`;
CREATE TABLE `lti_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lti_config_id` int(11) NOT NULL COMMENT 'FK to lti_configurations',
  `resource_type` varchar(50) NOT NULL COMMENT 'attendance, grades, assignments, etc.',
  `resource_id` int(11) DEFAULT NULL COMMENT 'Local resource ID (class_id, assignment_id, etc.)',
  `resource_url` varchar(500) NOT NULL COMMENT 'Deep link URL',
  `lms_context_id` varchar(255) DEFAULT NULL COMMENT 'LMS course ID',
  `lms_resource_link_id` varchar(255) DEFAULT NULL COMMENT 'LMS resource link ID',
  `title` varchar(255) NOT NULL COMMENT 'Display title in LMS',
  `description` text DEFAULT NULL COMMENT 'Link description',
  `icon_url` varchar(500) DEFAULT NULL COMMENT 'Resource icon URL',
  `launch_count` int(11) DEFAULT 0 COMMENT 'Number of times launched',
  `last_launched_at` timestamp NULL DEFAULT NULL COMMENT 'Last launch timestamp',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `lms_resource_link_id` (`lms_resource_link_id`),
  KEY `lti_config_id` (`lti_config_id`),
  KEY `idx_resource_type` (`resource_type`,`resource_id`),
  KEY `idx_context` (`lms_context_id`),
  CONSTRAINT `lti_links_ibfk_1` FOREIGN KEY (`lti_config_id`) REFERENCES `lti_configurations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Deep link resources for LMS embedding';

-- --------------------------------------------------------
-- Table: lti_sessions
-- --------------------------------------------------------
DROP TABLE IF EXISTS `lti_sessions`;
CREATE TABLE `lti_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lti_config_id` int(11) NOT NULL COMMENT 'FK to lti_configurations',
  `user_id` int(11) NOT NULL COMMENT 'FK to users table',
  `lms_user_id` varchar(255) DEFAULT NULL COMMENT 'User ID in the LMS',
  `lms_context_id` varchar(255) DEFAULT NULL COMMENT 'Course/context ID in LMS',
  `lms_resource_link_id` varchar(255) DEFAULT NULL COMMENT 'Specific resource link',
  `launch_params` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Full LTI launch parameters' CHECK (json_valid(`launch_params`)),
  `session_token` varchar(64) DEFAULT NULL COMMENT 'Internal session identifier',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Client IP for security',
  `user_agent` text DEFAULT NULL COMMENT 'Browser user agent',
  `is_valid` tinyint(1) DEFAULT 1 COMMENT 'Session validity flag',
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'Session expiration time',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `lti_config_id` (`lti_config_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_context` (`lms_context_id`),
  KEY `idx_session_token` (`session_token`),
  KEY `idx_valid_sessions` (`is_valid`,`expires_at`),
  CONSTRAINT `lti_sessions_ibfk_1` FOREIGN KEY (`lti_config_id`) REFERENCES `lti_configurations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lti_sessions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='LTI launch session tracking';

-- --------------------------------------------------------
-- Table: meeting_bookings
-- --------------------------------------------------------
DROP TABLE IF EXISTS `meeting_bookings`;
CREATE TABLE `meeting_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slot_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `parent_notes` text DEFAULT NULL,
  `teacher_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_booking` (`slot_id`,`booking_date`,`parent_id`),
  KEY `student_id` (`student_id`),
  KEY `idx_slot` (`slot_id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_date` (`booking_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `meeting_bookings_ibfk_1` FOREIGN KEY (`slot_id`) REFERENCES `meeting_slots` (`id`) ON DELETE CASCADE,
  CONSTRAINT `meeting_bookings_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `meeting_bookings_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: meeting_slots
-- --------------------------------------------------------
DROP TABLE IF EXISTS `meeting_slots`;
CREATE TABLE `meeting_slots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `max_bookings` int(11) DEFAULT 1 COMMENT 'bookings per slot',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_teacher` (`teacher_id`),
  KEY `idx_day` (`day_of_week`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `meeting_slots_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: message_attachments
-- --------------------------------------------------------
DROP TABLE IF EXISTS `message_attachments`;
CREATE TABLE `message_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) NOT NULL COMMENT 'image/jpeg, application/pdf, etc',
  `file_size` int(11) NOT NULL COMMENT 'bytes',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_message` (`message_id`),
  CONSTRAINT `message_attachments_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: message_delivery_status
-- --------------------------------------------------------
DROP TABLE IF EXISTS `message_delivery_status`;
CREATE TABLE `message_delivery_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `status` enum('sent','delivered','read') DEFAULT 'sent',
  `delivered_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_delivery` (`message_id`,`recipient_id`),
  KEY `idx_status` (`status`),
  KEY `idx_recipient` (`recipient_id`),
  CONSTRAINT `message_delivery_status_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_delivery_status_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: message_edit_history
-- --------------------------------------------------------
DROP TABLE IF EXISTS `message_edit_history`;
CREATE TABLE `message_edit_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `original_text` text NOT NULL,
  `edited_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_message` (`message_id`),
  CONSTRAINT `message_edit_history_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: message_forwards
-- --------------------------------------------------------
DROP TABLE IF EXISTS `message_forwards`;
CREATE TABLE `message_forwards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_message_id` int(11) NOT NULL,
  `forwarded_message_id` int(11) NOT NULL,
  `forwarded_by_user_id` int(11) NOT NULL,
  `forwarded_to_conversation_id` int(11) DEFAULT NULL,
  `forwarded_to_chat_room_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `forwarded_by_user_id` (`forwarded_by_user_id`),
  KEY `idx_original` (`original_message_id`),
  KEY `idx_forwarded` (`forwarded_message_id`),
  CONSTRAINT `message_forwards_ibfk_1` FOREIGN KEY (`original_message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_forwards_ibfk_2` FOREIGN KEY (`forwarded_message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_forwards_ibfk_3` FOREIGN KEY (`forwarded_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: message_mentions
-- --------------------------------------------------------
DROP TABLE IF EXISTS `message_mentions`;
CREATE TABLE `message_mentions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `mentioned_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_message` (`message_id`),
  KEY `idx_mentioned` (`mentioned_user_id`),
  CONSTRAINT `message_mentions_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_mentions_ibfk_2` FOREIGN KEY (`mentioned_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: message_reactions
-- --------------------------------------------------------
DROP TABLE IF EXISTS `message_reactions`;
CREATE TABLE `message_reactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reaction` varchar(50) NOT NULL COMMENT 'emoji or reaction type',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_reaction` (`message_id`,`user_id`,`reaction`),
  KEY `user_id` (`user_id`),
  KEY `idx_message` (`message_id`),
  CONSTRAINT `message_reactions_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_reactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: message_recipients
-- --------------------------------------------------------
DROP TABLE IF EXISTS `message_recipients`;
CREATE TABLE `message_recipients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_message_recipient` (`message_id`,`recipient_id`),
  KEY `idx_recipient` (`recipient_id`),
  KEY `idx_message` (`message_id`),
  KEY `idx_read` (`is_read`),
  CONSTRAINT `message_recipients_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_recipients_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: message_threads
-- --------------------------------------------------------
DROP TABLE IF EXISTS `message_threads`;
CREATE TABLE `message_threads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_message_id` int(11) NOT NULL,
  `reply_message_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_message_id`),
  KEY `idx_reply` (`reply_message_id`),
  CONSTRAINT `message_threads_ibfk_1` FOREIGN KEY (`parent_message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_threads_ibfk_2` FOREIGN KEY (`reply_message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: messages
-- --------------------------------------------------------
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `recipient_role` varchar(20) DEFAULT NULL COMMENT 'all, student, teacher, parent, admin, or NULL for direct',
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_edited` tinyint(1) DEFAULT 0,
  `edited_at` timestamp NULL DEFAULT NULL,
  `reply_to_message_id` int(11) DEFAULT NULL,
  `forwarded_from_message_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_receiver` (`receiver_id`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_role` (`recipient_role`),
  KEY `idx_read` (`is_read`),
  KEY `idx_created` (`created_at`),
  KEY `idx_reply` (`reply_to_message_id`),
  KEY `idx_forwarded` (`forwarded_from_message_id`),
  KEY `idx_deleted` (`is_deleted`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: mobile_devices
-- --------------------------------------------------------
DROP TABLE IF EXISTS `mobile_devices`;
CREATE TABLE `mobile_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_type` enum('ios','android') NOT NULL,
  `device_token` varchar(255) NOT NULL,
  `device_name` varchar(100) DEFAULT NULL,
  `app_version` varchar(20) DEFAULT NULL,
  `os_version` varchar(20) DEFAULT NULL,
  `last_sync` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_device` (`user_id`,`device_type`),
  CONSTRAINT `mobile_devices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: notices
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notices`;
CREATE TABLE `notices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `category` enum('academic','sports','emergency','event','maintenance','general') DEFAULT 'general',
  `priority` enum('normal','high','urgent') DEFAULT 'normal',
  `target_roles` varchar(100) DEFAULT NULL COMMENT 'Comma-separated roles (NULL = all roles)',
  `status` enum('active','archived') DEFAULT 'active',
  `is_pinned` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_status_expires` (`status`,`expires_at`),
  KEY `idx_category` (`category`),
  KEY `idx_pinned` (`is_pinned`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `notices_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: notification_preferences
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notification_preferences`;
CREATE TABLE `notification_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `enabled` tinyint(1) DEFAULT 1,
  `email_enabled` tinyint(1) DEFAULT 1,
  `push_enabled` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_type` (`user_id`,`notification_type`),
  CONSTRAINT `notification_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: notifications
-- --------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info' COMMENT 'info, message, alert, success, error',
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_read` (`is_read`),
  KEY `idx_type` (`type`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: offline_sync_queue
-- --------------------------------------------------------
DROP TABLE IF EXISTS `offline_sync_queue`;
CREATE TABLE `offline_sync_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data`)),
  `sync_status` enum('pending','synced','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `synced_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `device_id` (`device_id`),
  KEY `idx_sync_status` (`sync_status`,`created_at`),
  CONSTRAINT `offline_sync_queue_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `offline_sync_queue_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `mobile_devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: parent_student
-- --------------------------------------------------------
DROP TABLE IF EXISTS `parent_student`;
CREATE TABLE `parent_student` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `relationship` enum('mother','father','guardian','other') NOT NULL DEFAULT 'guardian',
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_parent_student` (`parent_id`,`student_id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_student` (`student_id`),
  CONSTRAINT `parent_student_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parent_student_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: parents
-- --------------------------------------------------------
DROP TABLE IF EXISTS `parents`;
CREATE TABLE `parents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `office_address` text DEFAULT NULL,
  `office_phone` varchar(20) DEFAULT NULL,
  `relationship_to_student` enum('father','mother','guardian') DEFAULT 'guardian',
  `income_level` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_relationship` (`relationship_to_student`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: payroll
-- --------------------------------------------------------
DROP TABLE IF EXISTS `payroll`;
CREATE TABLE `payroll` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `pay_period` varchar(20) NOT NULL COMMENT 'e.g. 2026-03',
  `basic_salary` decimal(12,2) DEFAULT 0.00,
  `allowances` decimal(12,2) DEFAULT 0.00,
  `deductions` decimal(12,2) DEFAULT 0.00,
  `tax` decimal(12,2) DEFAULT 0.00,
  `net_pay` decimal(12,2) DEFAULT 0.00,
  `amount` decimal(12,2) DEFAULT 0.00 COMMENT 'alias for net_pay',
  `payment_date` date DEFAULT NULL,
  `status` enum('draft','processed','paid') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_period` (`pay_period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: performance_reviews
-- --------------------------------------------------------
DROP TABLE IF EXISTS `performance_reviews`;
CREATE TABLE `performance_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `staff_type` enum('teacher','staff') NOT NULL,
  `review_period_start` date NOT NULL,
  `review_period_end` date NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `overall_rating` decimal(3,2) DEFAULT NULL COMMENT '1-5 scale',
  `punctuality_rating` decimal(3,2) DEFAULT NULL,
  `work_quality_rating` decimal(3,2) DEFAULT NULL,
  `communication_rating` decimal(3,2) DEFAULT NULL,
  `teamwork_rating` decimal(3,2) DEFAULT NULL,
  `achievements` text DEFAULT NULL,
  `areas_of_improvement` text DEFAULT NULL,
  `goals_for_next_period` text DEFAULT NULL,
  `reviewer_comments` text DEFAULT NULL,
  `employee_comments` text DEFAULT NULL,
  `review_date` date NOT NULL,
  `status` enum('draft','submitted','acknowledged','finalized') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff` (`staff_id`,`staff_type`),
  KEY `idx_review_period` (`review_period_start`,`review_period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: project_boards
-- --------------------------------------------------------
DROP TABLE IF EXISTS `project_boards`;
CREATE TABLE `project_boards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `board_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  CONSTRAINT `project_boards_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `collaboration_rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: project_tasks
-- --------------------------------------------------------
DROP TABLE IF EXISTS `project_tasks`;
CREATE TABLE `project_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `board_id` int(11) NOT NULL,
  `task_title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `status` enum('todo','in_progress','review','done') DEFAULT 'todo',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `due_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `board_id` (`board_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `created_by` (`created_by`),
  KEY `idx_status` (`status`,`due_date`),
  CONSTRAINT `project_tasks_ibfk_1` FOREIGN KEY (`board_id`) REFERENCES `project_boards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_tasks_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_tasks_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: purchase_orders
-- --------------------------------------------------------
DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) NOT NULL,
  `po_date` date NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `supplier_contact` varchar(20) DEFAULT NULL,
  `supplier_address` text DEFAULT NULL,
  `items` text NOT NULL COMMENT 'JSON array of items',
  `subtotal` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `status` enum('draft','submitted','approved','received','cancelled') DEFAULT 'draft',
  `approved_by` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `idx_po_number` (`po_number`),
  KEY `idx_status` (`status`),
  KEY `idx_po_date` (`po_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: push_notification_logs
-- --------------------------------------------------------
DROP TABLE IF EXISTS `push_notification_logs`;
CREATE TABLE `push_notification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('attendance','message','assignment','announcement','grade','event','general') DEFAULT 'general',
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `clicked` tinyint(1) DEFAULT 0,
  `clicked_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscription_id` (`subscription_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_sent_at` (`sent_at`),
  CONSTRAINT `push_notification_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `push_notification_logs_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `push_subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: push_notifications
-- --------------------------------------------------------
DROP TABLE IF EXISTS `push_notifications`;
CREATE TABLE `push_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `priority` enum('low','normal','high') DEFAULT 'normal',
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `sent_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `device_id` (`device_id`),
  KEY `idx_user_sent` (`user_id`,`sent_at`),
  CONSTRAINT `push_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `push_notifications_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `mobile_devices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: push_subscriptions
-- --------------------------------------------------------
DROP TABLE IF EXISTS `push_subscriptions`;
CREATE TABLE `push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `endpoint` text NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `push_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: pwa_analytics
-- --------------------------------------------------------
DROP TABLE IF EXISTS `pwa_analytics`;
CREATE TABLE `pwa_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `event_type` enum('install','uninstall','page_view','offline_access','sync','notification_click','share') NOT NULL,
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`event_data`)),
  `page_url` varchar(500) DEFAULT NULL,
  `is_offline` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `pwa_analytics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: pwa_cache_manifest
-- --------------------------------------------------------
DROP TABLE IF EXISTS `pwa_cache_manifest`;
CREATE TABLE `pwa_cache_manifest` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_type` enum('page','asset','api','image','font') NOT NULL,
  `resource_path` varchar(500) NOT NULL,
  `cache_strategy` enum('cache-first','network-first','network-only','cache-only') DEFAULT 'cache-first',
  `priority` int(11) DEFAULT 5,
  `max_age` int(11) DEFAULT 86400 COMMENT 'Cache max age in seconds',
  `is_critical` tinyint(1) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_path` (`resource_path`),
  KEY `idx_resource_type` (`resource_type`),
  KEY `idx_priority` (`priority`),
  KEY `idx_is_critical` (`is_critical`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: pwa_feature_flags
-- --------------------------------------------------------
DROP TABLE IF EXISTS `pwa_feature_flags`;
CREATE TABLE `pwa_feature_flags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feature_name` varchar(100) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `feature_name` (`feature_name`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `pwa_feature_flags_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: pwa_installations
-- --------------------------------------------------------
DROP TABLE IF EXISTS `pwa_installations`;
CREATE TABLE `pwa_installations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `device_type` enum('android','ios','desktop','other') NOT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `screen_resolution` varchar(50) DEFAULT NULL,
  `installed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_active` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_installed_at` (`installed_at`),
  CONSTRAINT `pwa_installations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: room_participants
-- --------------------------------------------------------
DROP TABLE IF EXISTS `room_participants`;
CREATE TABLE `room_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('host','moderator','participant') DEFAULT 'participant',
  `joined_at` datetime DEFAULT NULL,
  `left_at` datetime DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_room_user` (`room_id`,`user_id`),
  CONSTRAINT `room_participants_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `collaboration_rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `room_participants_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: salary_payments
-- --------------------------------------------------------
DROP TABLE IF EXISTS `salary_payments`;
CREATE TABLE `salary_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `salary_structure_id` int(11) NOT NULL,
  `payment_month` date NOT NULL COMMENT 'First day of payment month',
  `payment_date` date NOT NULL,
  `gross_salary` decimal(10,2) NOT NULL,
  `total_deductions` decimal(10,2) DEFAULT 0.00,
  `net_salary` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','cheque','bank_transfer') NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `paid_by` int(11) NOT NULL,
  `slip_generated` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_staff_month` (`staff_id`,`payment_month`),
  KEY `salary_structure_id` (`salary_structure_id`),
  KEY `idx_payment_month` (`payment_month`),
  KEY `idx_payment_date` (`payment_date`),
  CONSTRAINT `salary_payments_ibfk_1` FOREIGN KEY (`salary_structure_id`) REFERENCES `salary_structure` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: salary_structure
-- --------------------------------------------------------
DROP TABLE IF EXISTS `salary_structure`;
CREATE TABLE `salary_structure` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL COMMENT 'References teachers or other staff table',
  `basic_salary` decimal(10,2) NOT NULL,
  `hra` decimal(10,2) DEFAULT 0.00 COMMENT 'House Rent Allowance',
  `da` decimal(10,2) DEFAULT 0.00 COMMENT 'Dearness Allowance',
  `ta` decimal(10,2) DEFAULT 0.00 COMMENT 'Transport Allowance',
  `medical_allowance` decimal(10,2) DEFAULT 0.00,
  `other_allowances` decimal(10,2) DEFAULT 0.00,
  `pf_deduction` decimal(10,2) DEFAULT 0.00 COMMENT 'Provident Fund',
  `tax_deduction` decimal(10,2) DEFAULT 0.00,
  `other_deductions` decimal(10,2) DEFAULT 0.00,
  `gross_salary` decimal(10,2) NOT NULL,
  `net_salary` decimal(10,2) NOT NULL,
  `effective_from` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_effective_from` (`effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: scholarships
-- --------------------------------------------------------
DROP TABLE IF EXISTS `scholarships`;
CREATE TABLE `scholarships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `student_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('full','partial','merit','need_based') DEFAULT 'partial',
  `percentage` decimal(5,2) DEFAULT 0.00,
  `amount` decimal(12,2) DEFAULT 0.00,
  `academic_year` varchar(10) DEFAULT NULL,
  `status` enum('active','expired','revoked') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: school_tenants
-- --------------------------------------------------------
DROP TABLE IF EXISTS `school_tenants`;
CREATE TABLE `school_tenants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `contact_email` varchar(190) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_school_tenants_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: smart_contract_events
-- --------------------------------------------------------
DROP TABLE IF EXISTS `smart_contract_events`;
CREATE TABLE `smart_contract_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contract_address` varchar(42) NOT NULL,
  `event_name` varchar(100) NOT NULL,
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`event_data`)),
  `tx_hash` varchar(66) DEFAULT NULL,
  `block_number` bigint(20) DEFAULT NULL,
  `processed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contract_event` (`contract_address`,`event_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: staff
-- --------------------------------------------------------
DROP TABLE IF EXISTS `staff`;
CREATE TABLE `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `department_id` int(11) NOT NULL,
  `designation` varchar(100) NOT NULL,
  `employment_type` enum('permanent','temporary','contract','part_time') DEFAULT 'permanent',
  `date_of_joining` date NOT NULL,
  `date_of_leaving` date DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `experience_years` int(3) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `documents` text DEFAULT NULL COMMENT 'JSON array of document paths',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `idx_employee_id` (`employee_id`),
  CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: staff_attendance
-- --------------------------------------------------------
DROP TABLE IF EXISTS `staff_attendance`;
CREATE TABLE `staff_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL COMMENT 'Can reference teachers or staff table',
  `staff_type` enum('teacher','staff') NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `status` enum('present','absent','half_day','late','on_leave') DEFAULT 'present',
  `working_hours` decimal(4,2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `marked_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_staff_date` (`staff_id`,`staff_type`,`attendance_date`),
  KEY `idx_attendance_date` (`attendance_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: stock_transactions
-- --------------------------------------------------------
DROP TABLE IF EXISTS `stock_transactions`;
CREATE TABLE `stock_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `transaction_type` enum('in','out','adjustment') NOT NULL,
  `transaction_date` date NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `supplier_name` varchar(100) DEFAULT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `issued_to` varchar(100) DEFAULT NULL,
  `performed_by` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_item` (`item_id`),
  CONSTRAINT `stock_transactions_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: student_fees
-- --------------------------------------------------------
DROP TABLE IF EXISTS `student_fees`;
CREATE TABLE `student_fees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `fee_type` enum('tuition','books','uniform','transport','activities','examination','library','laboratory','other') NOT NULL DEFAULT 'tuition',
  `amount` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) DEFAULT 0.00,
  `due_date` date NOT NULL,
  `status` enum('pending','partial','paid','overdue') NOT NULL DEFAULT 'pending',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `idx_student_fees` (`student_id`),
  KEY `idx_fee_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  CONSTRAINT `student_fees_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_fees_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: student_messages
-- --------------------------------------------------------
DROP TABLE IF EXISTS `student_messages`;
CREATE TABLE `student_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `from_student_id` int(11) NOT NULL,
  `to_student_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `parent_message_id` int(11) DEFAULT NULL COMMENT 'For threading/replies',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_message_id` (`parent_message_id`),
  KEY `idx_from` (`from_student_id`),
  KEY `idx_to` (`to_student_id`),
  KEY `idx_conversation` (`conversation_id`),
  CONSTRAINT `student_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_messages_ibfk_2` FOREIGN KEY (`from_student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_messages_ibfk_3` FOREIGN KEY (`to_student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_messages_ibfk_4` FOREIGN KEY (`parent_message_id`) REFERENCES `student_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: students
-- --------------------------------------------------------
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `admission_number` varchar(50) NOT NULL,
  `roll_number` varchar(20) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `admission_date` date DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `previous_school` varchar(200) DEFAULT NULL,
  `transfer_certificate` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `assigned_student_id` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admission_number` (`admission_number`),
  KEY `idx_admission_number` (`admission_number`),
  KEY `idx_class_id` (`class_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: study_group_members
-- --------------------------------------------------------
DROP TABLE IF EXISTS `study_group_members`;
CREATE TABLE `study_group_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_member` (`group_id`,`student_id`),
  KEY `idx_group` (`group_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `study_group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `study_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `study_group_members_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: study_groups
-- --------------------------------------------------------
DROP TABLE IF EXISTS `study_groups`;
CREATE TABLE `study_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `class_id` int(11) NOT NULL,
  `creator_id` int(11) NOT NULL,
  `max_members` int(11) DEFAULT 5,
  `meeting_schedule` varchar(255) DEFAULT NULL COMMENT 'e.g., Tuesdays 3-5pm',
  `status` enum('open','closed','full') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`),
  KEY `idx_class` (`class_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `study_groups_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `study_groups_ibfk_2` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: subjects
-- --------------------------------------------------------
DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `subject_type` enum('core','elective','optional','extra_curricular') DEFAULT 'core',
  `grade_level` varchar(20) DEFAULT NULL COMMENT 'e.g., Grade 1, Grade 2, High School',
  `credit_hours` decimal(4,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `subject_code` (`subject_code`),
  KEY `idx_subject_code` (`subject_code`),
  KEY `idx_grade_level` (`grade_level`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='School subjects catalog';

-- --------------------------------------------------------
-- Table: suppliers
-- --------------------------------------------------------
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_code` varchar(20) NOT NULL,
  `supplier_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `gst_number` varchar(20) DEFAULT NULL,
  `pan_number` varchar(20) DEFAULT NULL,
  `bank_details` text DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `credit_limit` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `supplier_code` (`supplier_code`),
  KEY `idx_supplier_code` (`supplier_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: sustainability_challenges
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sustainability_challenges`;
CREATE TABLE `sustainability_challenges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `goal_type` varchar(50) NOT NULL,
  `goal_target` int(11) NOT NULL,
  `reward_points` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `sustainability_challenges_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: sustainability_metrics
-- --------------------------------------------------------
DROP TABLE IF EXISTS `sustainability_metrics`;
CREATE TABLE `sustainability_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `metric_type` varchar(50) NOT NULL COMMENT 'paperless, recycling, digital_submission',
  `points_earned` int(11) DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_type` (`user_id`,`metric_type`),
  CONSTRAINT `sustainability_metrics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: syllabus
-- --------------------------------------------------------
DROP TABLE IF EXISTS `syllabus`;
CREATE TABLE `syllabus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `grade_level` varchar(20) NOT NULL,
  `syllabus_title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `topics` text DEFAULT NULL COMMENT 'JSON array of topics',
  `learning_objectives` text DEFAULT NULL,
  `textbook_reference` varchar(255) DEFAULT NULL,
  `uploaded_file` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `subject_id` (`subject_id`),
  KEY `idx_academic_year` (`academic_year`),
  KEY `idx_grade` (`grade_level`),
  CONSTRAINT `syllabus_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: teacher_resources
-- --------------------------------------------------------
DROP TABLE IF EXISTS `teacher_resources`;
CREATE TABLE `teacher_resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL COMMENT 'in bytes',
  `file_type` varchar(50) DEFAULT NULL,
  `category` enum('lesson_plan','worksheet','presentation','assignment','study_guide','other') DEFAULT 'other',
  `is_public` tinyint(1) DEFAULT 0 COMMENT '1=shared with all teachers, 0=private',
  `download_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_teacher` (`teacher_id`),
  KEY `idx_public` (`is_public`),
  KEY `idx_category` (`category`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `teacher_resources_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: teachers
-- --------------------------------------------------------
DROP TABLE IF EXISTS `teachers`;
CREATE TABLE `teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `qualification` varchar(100) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `experience_years` int(11) DEFAULT 0,
  `date_joined` date DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT 0.00,
  `is_class_teacher` tinyint(1) DEFAULT 0,
  `subjects_handled` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_employee_id` (`employee_id`),
  CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: tenant_users
-- --------------------------------------------------------
DROP TABLE IF EXISTS `tenant_users`;
CREATE TABLE `tenant_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_override` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_tenant_users_user` (`user_id`),
  CONSTRAINT `fk_tenant_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `school_tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: transport_allocations
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transport_allocations`;
CREATE TABLE `transport_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `student_id` int(11) NOT NULL,
  `route_id` int(11) NOT NULL,
  `stop_name` varchar(100) DEFAULT NULL,
  `pickup_time` time DEFAULT NULL,
  `drop_time` time DEFAULT NULL,
  `academic_year` varchar(10) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_student` (`student_id`),
  KEY `route_id` (`route_id`),
  CONSTRAINT `transport_allocations_ibfk_1` FOREIGN KEY (`route_id`) REFERENCES `transport_routes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: transport_assignments
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transport_assignments`;
CREATE TABLE `transport_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `route_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `conductor_id` int(11) DEFAULT NULL,
  `shift` enum('morning','afternoon','both') DEFAULT 'both',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `driver_id` (`driver_id`),
  KEY `idx_route` (`route_id`),
  KEY `idx_vehicle` (`vehicle_id`),
  CONSTRAINT `transport_assignments_ibfk_1` FOREIGN KEY (`route_id`) REFERENCES `transport_routes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transport_assignments_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `transport_vehicles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transport_assignments_ibfk_3` FOREIGN KEY (`driver_id`) REFERENCES `transport_drivers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: transport_drivers
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transport_drivers`;
CREATE TABLE `transport_drivers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `driver_name` varchar(100) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `license_number` varchar(50) NOT NULL,
  `license_type` varchar(20) DEFAULT NULL,
  `license_expiry` date NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `date_of_joining` date NOT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('active','on_leave','suspended','resigned') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  UNIQUE KEY `license_number` (`license_number`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: transport_fuel_log
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transport_fuel_log`;
CREATE TABLE `transport_fuel_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `vehicle_id` int(11) NOT NULL,
  `fill_date` date NOT NULL,
  `quantity_liters` decimal(8,2) DEFAULT NULL,
  `cost_per_liter` decimal(8,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `odometer_reading` int(11) DEFAULT NULL,
  `station` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `vehicle_id` (`vehicle_id`),
  CONSTRAINT `transport_fuel_log_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `transport_vehicles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: transport_maintenance
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transport_maintenance`;
CREATE TABLE `transport_maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `vehicle_id` int(11) NOT NULL,
  `type` enum('routine','repair','emergency') DEFAULT 'routine',
  `description` text DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT 0.00,
  `scheduled_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed') DEFAULT 'scheduled',
  `vendor` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `vehicle_id` (`vehicle_id`),
  CONSTRAINT `transport_maintenance_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `transport_vehicles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: transport_routes
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transport_routes`;
CREATE TABLE `transport_routes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `route_number` varchar(20) NOT NULL,
  `route_name` varchar(100) NOT NULL,
  `starting_point` varchar(255) NOT NULL,
  `ending_point` varchar(255) NOT NULL,
  `total_distance_km` decimal(6,2) DEFAULT NULL,
  `estimated_time_minutes` int(11) DEFAULT NULL,
  `fare_amount` decimal(8,2) DEFAULT NULL,
  `stops` text DEFAULT NULL COMMENT 'JSON array of stops with coordinates',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `route_number` (`route_number`),
  KEY `idx_route_number` (`route_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: transport_trips
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transport_trips`;
CREATE TABLE `transport_trips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `route_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `trip_date` date NOT NULL,
  `trip_type` enum('morning_pickup','afternoon_drop','special') DEFAULT 'morning_pickup',
  `departure_time` time DEFAULT NULL,
  `arrival_time` time DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_date` (`trip_date`),
  KEY `route_id` (`route_id`),
  KEY `vehicle_id` (`vehicle_id`),
  CONSTRAINT `transport_trips_ibfk_1` FOREIGN KEY (`route_id`) REFERENCES `transport_routes` (`id`),
  CONSTRAINT `transport_trips_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `transport_vehicles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: transport_vehicles
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transport_vehicles`;
CREATE TABLE `transport_vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_number` varchar(20) NOT NULL,
  `vehicle_type` enum('bus','van','mini_bus','car') DEFAULT 'bus',
  `vehicle_model` varchar(100) DEFAULT NULL,
  `manufacturing_year` year(4) DEFAULT NULL,
  `seating_capacity` int(3) NOT NULL,
  `registration_number` varchar(50) NOT NULL,
  `insurance_number` varchar(50) DEFAULT NULL,
  `insurance_expiry` date DEFAULT NULL,
  `fitness_certificate_expiry` date DEFAULT NULL,
  `permit_expiry` date DEFAULT NULL,
  `last_service_date` date DEFAULT NULL,
  `next_service_date` date DEFAULT NULL,
  `gps_device_id` varchar(50) DEFAULT NULL,
  `status` enum('active','maintenance','inactive','retired') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicle_number` (`vehicle_number`),
  UNIQUE KEY `registration_number` (`registration_number`),
  KEY `idx_vehicle_number` (`vehicle_number`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: typing_indicators
-- --------------------------------------------------------
DROP TABLE IF EXISTS `typing_indicators`;
CREATE TABLE `typing_indicators` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `conversation_id` int(11) DEFAULT NULL,
  `chat_room_id` int(11) DEFAULT NULL,
  `is_typing` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_conversation` (`conversation_id`),
  KEY `idx_room` (`chat_room_id`),
  KEY `idx_updated` (`updated_at`),
  CONSTRAINT `typing_indicators_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_badges
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user_badges`;
CREATE TABLE `user_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_badge` (`user_id`,`badge_id`),
  KEY `badge_id` (`badge_id`),
  CONSTRAINT `user_badges_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_badges_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `gamification_badges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: user_online_status
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user_online_status`;
CREATE TABLE `user_online_status` (
  `user_id` int(11) NOT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `last_seen` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  KEY `idx_online` (`is_online`),
  KEY `idx_last_seen` (`last_seen`),
  CONSTRAINT `user_online_status_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_sync_status
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user_sync_status`;
CREATE TABLE `user_sync_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `last_sync` timestamp NULL DEFAULT NULL,
  `device_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`device_info`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_last_sync` (`last_sync`),
  CONSTRAINT `user_sync_status_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: user_wellness_scores
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user_wellness_scores`;
CREATE TABLE `user_wellness_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `eco_points` int(11) DEFAULT 0,
  `wellness_score` decimal(5,2) DEFAULT 0.00 COMMENT '0-100 scale',
  `badges_earned` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of badge IDs' CHECK (json_valid(`badges_earned`)),
  `last_calculated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`),
  CONSTRAINT `user_wellness_scores_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `role` enum('admin','teacher','student','parent','accountant','librarian','counselor','nurse','admin_officer','class_teacher','subject_coordinator','vice_principal','principal','superadmin','owner','transport','visitor','general') NOT NULL DEFAULT 'student',
  `is_active` tinyint(1) DEFAULT 1,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 1,
  `approved` tinyint(1) DEFAULT 1,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email_verification_token` varchar(64) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `assigned_id` varchar(50) DEFAULT NULL,
  `verification_token` varchar(255) DEFAULT NULL,
  `password_set_at` datetime DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: vehicle_maintenance
-- --------------------------------------------------------
DROP TABLE IF EXISTS `vehicle_maintenance`;
CREATE TABLE `vehicle_maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `maintenance_date` date NOT NULL,
  `maintenance_type` enum('routine_service','repair','breakdown','inspection','oil_change','tire_change','other') NOT NULL,
  `description` text NOT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `vendor_name` varchar(100) DEFAULT NULL,
  `next_service_date` date DEFAULT NULL,
  `odometer_reading` int(11) DEFAULT NULL,
  `performed_by` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vehicle` (`vehicle_id`),
  KEY `idx_maintenance_date` (`maintenance_date`),
  CONSTRAINT `vehicle_maintenance_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `transport_vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: voice_messages
-- --------------------------------------------------------
DROP TABLE IF EXISTS `voice_messages`;
CREATE TABLE `voice_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `duration_seconds` int(11) NOT NULL,
  `file_size` int(11) NOT NULL,
  `waveform_data` text DEFAULT NULL COMMENT 'JSON array of amplitude values',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_message` (`message_id`),
  CONSTRAINT `voice_messages_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: wellness_logs
-- --------------------------------------------------------
DROP TABLE IF EXISTS `wellness_logs`;
CREATE TABLE `wellness_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `mood` enum('excellent','good','neutral','stressed','poor') DEFAULT NULL,
  `stress_level` int(11) DEFAULT NULL COMMENT '1-10 scale',
  `energy_level` int(11) DEFAULT NULL COMMENT '1-10 scale',
  `sleep_hours` decimal(4,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_date` (`user_id`,`log_date`),
  KEY `idx_date` (`log_date`),
  CONSTRAINT `wellness_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: whiteboard_sessions
-- --------------------------------------------------------
DROP TABLE IF EXISTS `whiteboard_sessions`;
CREATE TABLE `whiteboard_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `whiteboard_data` longtext DEFAULT NULL COMMENT 'JSON canvas data',
  `version` int(11) DEFAULT 1,
  `last_modified_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  KEY `last_modified_by` (`last_modified_by`),
  CONSTRAINT `whiteboard_sessions_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `collaboration_rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `whiteboard_sessions_ibfk_2` FOREIGN KEY (`last_modified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
