<?php

/**
 * Migration: Add profile_picture column, create directories, ensure tables.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

echo "Running profile picture migration...\n";

// 1. Add profile_picture column
try {
  $cols = db()->fetchAll('SHOW COLUMNS FROM users LIKE "profile_picture"');
  if (empty($cols)) {
    db()->query('ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER last_name');
    echo "[OK] Added profile_picture column to users table.\n";
  } else {
    echo "[SKIP] profile_picture column already exists.\n";
  }
} catch (Exception $e) {
  echo "[ERR] " . $e->getMessage() . "\n";
}

// 2. Create uploads/profiles directory
$dir = __DIR__ . '/uploads/profiles';
if (!is_dir($dir)) {
  mkdir($dir, 0755, true);
  echo "[OK] Created uploads/profiles/ directory.\n";
} else {
  echo "[SKIP] uploads/profiles/ already exists.\n";
}

// 3. Security .htaccess
$htaccess = $dir . '/.htaccess';
if (!file_exists($htaccess)) {
  $content = "# Only allow image files\nOptions -Indexes\n\n# Disable PHP execution\nphp_flag engine off\n\n# Block script files\n<FilesMatch \"\\.(php|phtml|php[345]|html?)$\">\n    Order Deny,Allow\n    Deny from all\n</FilesMatch>\n";
  file_put_contents($htaccess, $content);
  echo "[OK] Created .htaccess for uploads/profiles.\n";
}

// 4. Ensure rate_limits table exists
try {
  $check = db()->fetchAll('SHOW TABLES LIKE "rate_limits"');
  if (empty($check)) {
    db()->query('CREATE TABLE rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rate_key VARCHAR(255) NOT NULL UNIQUE,
            attempts INT DEFAULT 0,
            first_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB');
    echo "[OK] Created rate_limits table.\n";
  } else {
    echo "[SKIP] rate_limits table already exists.\n";
  }
} catch (Exception $e) {
  echo "[INFO] rate_limits: " . $e->getMessage() . "\n";
}

echo "\nMigration complete!\n";
