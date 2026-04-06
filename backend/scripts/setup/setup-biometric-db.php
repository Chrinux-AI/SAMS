<?php
require_once 'includes/config.php';
require_once 'includes/database.php';

echo "Setting up Biometric Authentication System...\n\n";

try {
    // Create biometric_credentials table
    echo "Creating biometric_credentials table...\n";
    $sql1 = "CREATE TABLE IF NOT EXISTS biometric_credentials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        credential_id VARCHAR(255) NOT NULL UNIQUE,
        public_key TEXT NOT NULL,
        counter INT DEFAULT 0,
        device_name VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_used TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_credential_id (credential_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    db()->query($sql1);
    echo "✓ biometric_credentials table created\n";

    // Create biometric_auth_logs table
    echo "Creating biometric_auth_logs table...\n";
    $sql2 = "CREATE TABLE IF NOT EXISTS biometric_auth_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        credential_id VARCHAR(255),
        auth_type ENUM('fingerprint', 'face', 'device') DEFAULT 'fingerprint',
        status ENUM('success', 'failed', 'denied') DEFAULT 'success',
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    db()->query($sql2);
    echo "✓ biometric_auth_logs table created\n";

    // Create attendance_biometric table
    echo "Creating attendance_biometric table...\n";
    $sql3 = "CREATE TABLE IF NOT EXISTS attendance_biometric (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        credential_id VARCHAR(255),
        scan_type ENUM('fingerprint', 'face', 'card') DEFAULT 'fingerprint',
        scan_data TEXT,
        location VARCHAR(255),
        latitude DECIMAL(10, 8) NULL,
        longitude DECIMAL(11, 8) NULL,
        status ENUM('verified', 'rejected', 'suspicious') DEFAULT 'verified',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    db()->query($sql3);
    echo "✓ attendance_biometric table created\n";

    echo "\n✅ Biometric system setup complete!\n";
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
}
