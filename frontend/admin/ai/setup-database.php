<?php
/**
 * SAMS AI Database Setup
 * Creates necessary tables for AI automation features
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$db = db();

// Create jobs table for background processing
$jobsTable = "
CREATE TABLE IF NOT EXISTS jobs (
    id VARCHAR(36) PRIMARY KEY,
    queue_name VARCHAR(50) NOT NULL,
    data JSON NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    progress INT DEFAULT 0,
    total INT DEFAULT 0,
    error TEXT,
    results JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_queue_status (queue_name, status),
    INDEX idx_created_at (created_at),
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

// Create otp_tokens table for OTP verification
$otpTokensTable = "
CREATE TABLE IF NOT EXISTS otp_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

// Create ai_extraction_logs table for logging
$aiExtractionLogsTable = "
CREATE TABLE IF NOT EXISTS ai_extraction_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id VARCHAR(36),
    entity_type VARCHAR(20),
    entity_data JSON,
    status VARCHAR(20),
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job_id (job_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

// Create password_set_logs table for password setup tracking
$passwordSetLogsTable = "
CREATE TABLE IF NOT EXISTS password_set_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

// Execute table creation
try {
    $db->createTable($jobsTable);
    echo "✓ Jobs table created successfully\n";
} catch (Exception $e) {
    echo "✗ Error creating jobs table: " . $e->getMessage() . "\n";
}

try {
    $db->createTable($otpTokensTable);
    echo "✓ OTP tokens table created successfully\n";
} catch (Exception $e) {
    echo "✗ Error creating OTP tokens table: " . $e->getMessage() . "\n";
}

try {
    $db->createTable($aiExtractionLogsTable);
    echo "✓ AI extraction logs table created successfully\n";
} catch (Exception $e) {
    echo "✗ Error creating AI extraction logs table: " . $e->getMessage() . "\n";
}

try {
    $db->createTable($passwordSetLogsTable);
    echo "✓ Password set logs table created successfully\n";
} catch (Exception $e) {
    echo "✗ Error creating password set logs table: " . $e->getMessage() . "\n";
}

echo "\nDatabase setup completed!\n";
echo "AI automation features are now ready to use.\n";
?>
