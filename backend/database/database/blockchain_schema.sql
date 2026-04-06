-- Blockchain Integration Database Schema
-- Adds tables for blockchain records, certificates, and verification

-- Blockchain record hashes
CREATE TABLE IF NOT EXISTS `blockchain_records` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `record_type` ENUM('attendance', 'grade', 'certificate', 'achievement') NOT NULL,
  `record_id` INT NOT NULL COMMENT 'ID of the original record',
  `user_id` INT NOT NULL,
  `record_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of record data',
  `blockchain_tx_hash` VARCHAR(66) COMMENT 'Transaction hash on blockchain',
  `blockchain_network` VARCHAR(50) DEFAULT 'ethereum',
  `block_number` BIGINT,
  `is_verified` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `verified_at` DATETIME,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_record` (`record_type`, `record_id`),
  INDEX `idx_tx_hash` (`blockchain_tx_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Digital certificates (NFT-based)
CREATE TABLE IF NOT EXISTS `digital_certificates` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `certificate_type` VARCHAR(100) NOT NULL COMMENT 'diploma, attendance_perfect, achievement',
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `issued_date` DATE NOT NULL,
  `nft_token_id` VARCHAR(100),
  `nft_contract_address` VARCHAR(42),
  `metadata_uri` VARCHAR(500),
  `blockchain_record_id` INT,
  `is_public` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`blockchain_record_id`) REFERENCES `blockchain_records`(`id`) ON DELETE SET NULL,
  INDEX `idx_user_type` (`user_id`, `certificate_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Blockchain verification requests
CREATE TABLE IF NOT EXISTS `blockchain_verifications` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `verification_code` VARCHAR(32) NOT NULL,
  `record_type` VARCHAR(50) NOT NULL,
  `record_data` JSON NOT NULL,
  `requester_ip` VARCHAR(45),
  `is_verified` TINYINT(1),
  `verified_at` DATETIME,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_code` (`verification_code`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Smart contract events log
CREATE TABLE IF NOT EXISTS `smart_contract_events` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `contract_address` VARCHAR(42) NOT NULL,
  `event_name` VARCHAR(100) NOT NULL,
  `event_data` JSON,
  `tx_hash` VARCHAR(66),
  `block_number` BIGINT,
  `processed` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_contract_event` (`contract_address`, `event_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
