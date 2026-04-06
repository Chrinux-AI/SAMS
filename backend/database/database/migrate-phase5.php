<?php

/**
 * Phase-5 Migration — Autonomous Architecture
 * Adds: schedule column to classes, consistency_issues table
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$results = [];

// 1. Add schedule column to classes table
try {
  $cols = db()->fetchAll("SHOW COLUMNS FROM classes LIKE 'schedule'");
  if (empty($cols)) {
    db()->query("ALTER TABLE classes ADD COLUMN schedule VARCHAR(255) DEFAULT NULL AFTER room_number");
    $results[] = '✅ Added schedule column to classes table';
  } else {
    $results[] = '⏭️ schedule column already exists in classes';
  }
} catch (Throwable $e) {
  $results[] = '❌ schedule column: ' . $e->getMessage();
}

// 2. Create consistency_issues table
try {
  $exists = db()->fetchOne("SHOW TABLES LIKE 'consistency_issues'");
  if (!$exists) {
    db()->query("CREATE TABLE consistency_issues (
            id INT AUTO_INCREMENT PRIMARY KEY,
            table_name VARCHAR(100) NOT NULL,
            record_id INT DEFAULT NULL,
            issue_type VARCHAR(50) NOT NULL DEFAULT 'mismatch',
            message TEXT NOT NULL,
            request_url VARCHAR(500) DEFAULT NULL,
            user_id INT DEFAULT NULL,
            resolved TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ci_table (table_name),
            INDEX idx_ci_type (issue_type),
            INDEX idx_ci_resolved (resolved)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = '✅ Created consistency_issues table';
  } else {
    $results[] = '⏭️ consistency_issues table already exists';
  }
} catch (Throwable $e) {
  $results[] = '❌ consistency_issues: ' . $e->getMessage();
}

// 3. Ensure broadcast_events table exists (needed by AutoSyncEngine)
try {
  $exists = db()->fetchOne("SHOW TABLES LIKE 'broadcast_events'");
  if (!$exists) {
    db()->query("CREATE TABLE broadcast_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            channel VARCHAR(100) NOT NULL DEFAULT 'global',
            event_type VARCHAR(100) NOT NULL,
            payload JSON DEFAULT NULL,
            user_ids JSON DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_be_channel (channel),
            INDEX idx_be_read (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = '✅ Created broadcast_events table';
  } else {
    $results[] = '⏭️ broadcast_events table already exists';
  }
} catch (Throwable $e) {
  $results[] = '❌ broadcast_events: ' . $e->getMessage();
}

// 4. Ensure system_events table exists (needed by EventDispatcher)
try {
  $exists = db()->fetchOne("SHOW TABLES LIKE 'system_events'");
  if (!$exists) {
    db()->query("CREATE TABLE system_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_name VARCHAR(150) NOT NULL,
            payload JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_se_name (event_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $results[] = '✅ Created system_events table';
  } else {
    $results[] = '⏭️ system_events table already exists';
  }
} catch (Throwable $e) {
  $results[] = '❌ system_events: ' . $e->getMessage();
}

echo "<h2>Phase-5 Migration Results</h2><pre>\n";
foreach ($results as $r) {
  echo $r . "\n";
}
echo "</pre>\n";
echo "<p><strong>Migration complete.</strong></p>\n";
