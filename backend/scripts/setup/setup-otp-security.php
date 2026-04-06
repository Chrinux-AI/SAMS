<?php
/**
 * Create OTP verification log table for enhanced security
 */

require_once 'includes/config.php';
require_once 'includes/database.php';

// Create OTP verification log table
$sql = "
CREATE TABLE IF NOT EXISTS `otp_verification_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `otp_used` varchar(10) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_success` (`success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    db()->query($sql);
    echo "OTP verification log table created successfully!\n";
} catch (Exception $e) {
    echo "Error creating OTP verification log table: " . $e->getMessage() . "\n";
}

// Add OTP security columns to users table if they don't exist
$columns_to_add = [
    "reset_otp" => "varchar(6) DEFAULT NULL",
    "reset_otp_expiry" => "timestamp NULL DEFAULT NULL",
    "otp_request_count" => "int(11) DEFAULT 0",
    "last_otp_request" => "timestamp NULL DEFAULT NULL",
    "failed_otp_attempts" => "int(11) DEFAULT 0"
];

foreach ($columns_to_add as $column => $definition) {
    $sql = "ALTER TABLE users ADD COLUMN IF NOT EXISTS `$column` $definition";
    try {
        db()->query($sql);
        echo "Added column $column to users table\n";
    } catch (Exception $e) {
        echo "Column $column already exists or error: " . $e->getMessage() . "\n";
    }
}

echo "OTP security setup complete!\n";
?>
