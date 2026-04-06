<?php

/**
 * Phase-3 Security Architecture — Database Migration
 *
 * Creates all tables and columns required by Phase-3 security services.
 *
 * NEW TABLES:
 *  - behavior_log      (BehaviorMonitor action tracking)
 *  - security_events   (SecurityAI, PromptFirewall, SecurityEventBus, AutoDefense)
 *  - session_intelligence (SessionIntelligence device/IP tracking)
 *  - audit_trails      (AdminForensics detailed admin audit trail)
 *  - forensic_snapshots (AdminForensics + AutoDefense pre-action snapshots)
 *  - ip_bans           (AutoDefense IP blocking)
 *  - api_nonces        (ApiSecurityMiddleware replay prevention)
 *
 * ALTERED TABLES:
 *  - users: add account_locked, locked_until columns
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

runMigration('Create behavior_log table', "
    CREATE TABLE IF NOT EXISTS behavior_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        role VARCHAR(50) DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        metadata JSON,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_action (action),
        INDEX idx_created (created_at),
        INDEX idx_user_created (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

runMigration('Create security_events table', "
    CREATE TABLE IF NOT EXISTS security_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_type VARCHAR(100) NOT NULL,
        severity VARCHAR(20) DEFAULT 'info',
        user_id INT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        details JSON,
        resolved TINYINT(1) DEFAULT 0,
        resolved_at DATETIME DEFAULT NULL,
        resolved_by INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_type (event_type),
        INDEX idx_severity (severity),
        INDEX idx_user (user_id),
        INDEX idx_created (created_at),
        INDEX idx_unresolved (resolved, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

runMigration('Create session_intelligence table', "
    CREATE TABLE IF NOT EXISTS session_intelligence (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_id VARCHAR(128) NOT NULL,
        device_hash VARCHAR(64) DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        browser VARCHAR(100) DEFAULT NULL,
        platform VARCHAR(100) DEFAULT NULL,
        role VARCHAR(50) DEFAULT NULL,
        risk_score INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_session (session_id),
        INDEX idx_active (is_active, last_activity),
        INDEX idx_user_active (user_id, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

runMigration('Create audit_trails table', "
    CREATE TABLE IF NOT EXISTS audit_trails (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        action_type VARCHAR(100) NOT NULL,
        affected_model VARCHAR(100) DEFAULT NULL,
        target_id INT DEFAULT NULL,
        old_value JSON,
        new_value JSON,
        description TEXT,
        ip_address VARCHAR(45) DEFAULT NULL,
        device VARCHAR(500) DEFAULT NULL,
        risk_score INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin (admin_id),
        INDEX idx_action (action_type),
        INDEX idx_model (affected_model),
        INDEX idx_target (affected_model, target_id),
        INDEX idx_created (created_at),
        INDEX idx_risk (risk_score)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

runMigration('Create forensic_snapshots table', "
    CREATE TABLE IF NOT EXISTS forensic_snapshots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        snapshot JSON NOT NULL,
        trigger_action VARCHAR(100) DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_trigger (trigger_action),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

runMigration('Create ip_bans table', "
    CREATE TABLE IF NOT EXISTS ip_bans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        reason VARCHAR(500) DEFAULT NULL,
        banned_by INT DEFAULT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip (ip_address),
        INDEX idx_expires (expires_at),
        UNIQUE KEY uk_ip (ip_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

runMigration('Create api_nonces table', "
    CREATE TABLE IF NOT EXISTS api_nonces (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nonce VARCHAR(64) NOT NULL,
        user_id INT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_nonce (nonce),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ==========================================
// ALTER EXISTING TABLES
// ==========================================

// users: add account_locked
$check = db()->fetchOne(
  "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'account_locked'",
  ['db' => DB_NAME]
);
if (!$check || $check['cnt'] == 0) {
  runMigration('Add users.account_locked', "ALTER TABLE users ADD COLUMN account_locked TINYINT(1) DEFAULT 0");
} else {
  $results[] = "⏭️ users.account_locked already exists";
}

// users: add locked_until
$check = db()->fetchOne(
  "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'locked_until'",
  ['db' => DB_NAME]
);
if (!$check || $check['cnt'] == 0) {
  runMigration('Add users.locked_until', "ALTER TABLE users ADD COLUMN locked_until DATETIME DEFAULT NULL");
} else {
  $results[] = "⏭️ users.locked_until already exists";
}

// ==========================================
// CREATE DIRECTORIES
// ==========================================

$dirs = [
  BASE_PATH . '/storage/forensics',
  BASE_PATH . '/storage/security',
  BASE_PATH . '/storage/nonces',
];
foreach ($dirs as $dir) {
  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
    $results[] = "📁 Created: " . str_replace(BASE_PATH, '', $dir);
  }
}

// Protect storage dirs with .htaccess
foreach (['forensics', 'security', 'nonces'] as $subdir) {
  $htaccess = BASE_PATH . "/storage/{$subdir}/.htaccess";
  if (!is_file($htaccess)) {
    file_put_contents($htaccess, "Deny from all\n");
    $results[] = "🔒 Created {$subdir}/.htaccess";
  }
}

// ==========================================
// OUTPUT RESULTS
// ==========================================

echo "<h2>Phase-3 Security Migration Results</h2><pre>\n";
foreach ($results as $r) {
  echo "{$r}\n";
}
echo "</pre>\n<p><strong>Phase-3 migration complete.</strong> " . count($results) . " operations executed.</p>";
