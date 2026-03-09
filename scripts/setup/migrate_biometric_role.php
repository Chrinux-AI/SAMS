<?php

/**
 * Add user_role column to biometric_credentials table
 * Run this once to update the database schema
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

try {
    // Check if column exists
    $result = db()->fetchOne("SHOW COLUMNS FROM biometric_credentials LIKE 'user_role'");

    if (!$result) {
        echo "Adding user_role column to biometric_credentials table...\n";

        db()->query("ALTER TABLE biometric_credentials ADD COLUMN user_role VARCHAR(20) AFTER device_name");

        echo "Column added successfully!\n";

        // Update existing records with role from users table
        echo "Updating existing credentials with user roles...\n";

        db()->query("
            UPDATE biometric_credentials bc
            JOIN users u ON bc.user_id = u.id
            SET bc.user_role = u.role
            WHERE bc.user_role IS NULL
        ");

        echo "Migration completed successfully!\n";
    } else {
        echo "Column user_role already exists. No migration needed.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
