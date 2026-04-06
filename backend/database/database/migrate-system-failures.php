<?php

/**
 * Migration: Create system_failures table for the Autonomous Fix Loop.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/database.php';
require_once INCLUDES_PATH . '/functions.php';

echo "=== system_failures table migration ===\n";

if (table_exists('system_failures')) {
  echo "Table 'system_failures' already exists. Skipping.\n";
} else {
  db()->query("CREATE TABLE system_failures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        error_type VARCHAR(100) NOT NULL,
        module VARCHAR(100) NOT NULL,
        fix_applied TEXT,
        success TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sf_module (module),
        INDEX idx_sf_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  echo "Created 'system_failures' table.\n";
}

echo "Done.\n";
