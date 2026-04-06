<?php

/**
 * Phase-2 Database Migration
 * Creates all new tables required by the Phase-2 architecture.
 *
 * Tables created:
 *  - user_notifications (notification engine)
 *  - broadcast_events (SSE broadcaster)
 *  - system_events (event dispatcher log)
 *  - message_reads (delivery status tracking)
 *  - updates (extended notices — aliases notices table columns)
 *
 * Tables altered:
 *  - audit_logs (add model, target_id, user_agent columns if missing)
 *  - notices (add target_role column if missing)
 *  - conversation_messages (add status, is_edited, is_deleted, deleted_at columns if missing)
 *  - conversation_participants (add unread_count, last_message_at, last_read_at if missing)
 */

require_once __DIR__ . '/includes/config.php';

$results = [];

function runMigration(string $name, string $sql): void
{
  global $results;
  try {
    db()->query($sql);
    $results[] = "✅ {$name}";
  } catch (\Throwable $e) {
    $msg = $e->getMessage();
    if (str_contains($msg, 'already exists') || str_contains($msg, 'Duplicate column')) {
      $results[] = "⏭️ {$name} (already exists)";
    } else {
      $results[] = "❌ {$name}: {$msg}";
    }
  }
}

// ==========================================
// NEW TABLES
// ==========================================

runMigration('Create user_notifications table', "
    CREATE TABLE IF NOT EXISTS user_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        body TEXT,
        type VARCHAR(50) DEFAULT 'info',
        link VARCHAR(500) DEFAULT NULL,
        reference_id INT DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        read_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_read (user_id, is_read),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

runMigration('Create broadcast_events table', "
    CREATE TABLE IF NOT EXISTS broadcast_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        channel VARCHAR(100) NOT NULL,
        event_type VARCHAR(100) NOT NULL,
        payload JSON,
        target_user_id INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_channel (channel),
        INDEX idx_target (target_user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

runMigration('Create system_events table', "
    CREATE TABLE IF NOT EXISTS system_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_name VARCHAR(100) NOT NULL,
        payload JSON,
        user_id INT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_event (event_name),
        INDEX idx_user (user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ==========================================
// ALTER EXISTING TABLES (add missing columns)
// ==========================================

// audit_logs: add model, target_id, user_agent
$auditCols = ['model', 'target_id', 'user_agent'];
foreach ($auditCols as $col) {
  $check = db()->fetchOne("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = :col", ['db' => DB_NAME, 'col' => $col]);
  if (!$check || $check['cnt'] == 0) {
    $type = match ($col) {
      'model' => "VARCHAR(100) DEFAULT NULL",
      'target_id' => "INT DEFAULT NULL",
      'user_agent' => "VARCHAR(500) DEFAULT NULL",
    };
    runMigration("Add audit_logs.{$col}", "ALTER TABLE audit_logs ADD COLUMN {$col} {$type}");
  } else {
    $results[] = "⏭️ audit_logs.{$col} already exists";
  }
}

// notices: add target_role if missing
$check = db()->fetchOne("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'notices' AND COLUMN_NAME = 'target_role'", ['db' => DB_NAME]);
if (!$check || $check['cnt'] == 0) {
  runMigration('Add notices.target_role', "ALTER TABLE notices ADD COLUMN target_role VARCHAR(50) DEFAULT NULL");
} else {
  $results[] = "⏭️ notices.target_role already exists";
}

// conversation_messages: add status, is_edited, is_deleted, deleted_at
$cmCols = [
  'status'     => "VARCHAR(20) DEFAULT 'sent'",
  'is_edited'  => "TINYINT(1) DEFAULT 0",
  'is_deleted' => "TINYINT(1) DEFAULT 0",
  'deleted_at' => "DATETIME DEFAULT NULL",
  'updated_at' => "DATETIME DEFAULT NULL",
  'attachment_path' => "VARCHAR(500) DEFAULT NULL",
];
foreach ($cmCols as $col => $type) {
  $check = db()->fetchOne("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'conversation_messages' AND COLUMN_NAME = :col", ['db' => DB_NAME, 'col' => $col]);
  if (!$check || $check['cnt'] == 0) {
    runMigration("Add conversation_messages.{$col}", "ALTER TABLE conversation_messages ADD COLUMN {$col} {$type}");
  } else {
    $results[] = "⏭️ conversation_messages.{$col} already exists";
  }
}

// conversation_participants: add unread_count, last_message_at, last_read_at
$cpCols = [
  'unread_count'    => "INT DEFAULT 0",
  'last_message_at' => "DATETIME DEFAULT NULL",
  'last_read_at'    => "DATETIME DEFAULT NULL",
];
foreach ($cpCols as $col => $type) {
  $check = db()->fetchOne("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'conversation_participants' AND COLUMN_NAME = :col", ['db' => DB_NAME, 'col' => $col]);
  if (!$check || $check['cnt'] == 0) {
    runMigration("Add conversation_participants.{$col}", "ALTER TABLE conversation_participants ADD COLUMN {$col} {$type}");
  } else {
    $results[] = "⏭️ conversation_participants.{$col} already exists";
  }
}

// ==========================================
// CREATE DIRECTORIES
// ==========================================

$dirs = [
  BASE_PATH . '/cache/data',
  BASE_PATH . '/storage/logs',
  BASE_PATH . '/storage/rate_limits',
  BASE_PATH . '/uploads/profiles/admin',
  BASE_PATH . '/uploads/profiles/teacher',
  BASE_PATH . '/uploads/profiles/student',
  BASE_PATH . '/uploads/profiles/parent',
  BASE_PATH . '/uploads/profiles/general',
];
foreach ($dirs as $dir) {
  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
    $results[] = "📁 Created: " . str_replace(BASE_PATH, '', $dir);
  }
}

// .htaccess for cache dir
$htaccess = BASE_PATH . '/cache/.htaccess';
if (!is_file($htaccess)) {
  file_put_contents($htaccess, "Deny from all\n");
  $results[] = "📁 Created cache/.htaccess";
}

// ==========================================
// OUTPUT RESULTS
// ==========================================

echo "<h2>Phase-2 Migration Results</h2><pre>\n";
foreach ($results as $r) {
  echo $r . "\n";
}
echo "</pre>\n<p><strong>Migration complete.</strong></p>";
