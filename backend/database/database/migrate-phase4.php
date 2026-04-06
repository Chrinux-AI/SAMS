<?php

/**
 * Phase-4 Enterprise Architecture Migration
 * Creates tables needed by Phase-4 components.
 *
 * Run via: php database/migrate-phase4.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/database.php';
require_once INCLUDES_PATH . '/functions.php';

$db = db();
$created = [];
$errors  = [];

function run_phase4($db, $name, $sql, &$created, &$errors)
{
  try {
    $db->query($sql);
    $created[] = $name;
  } catch (\Throwable $e) {
    $errors[] = "$name: " . $e->getMessage();
  }
}

echo "=== Phase-4 Enterprise Architecture Migration ===\n\n";

// ── user_themes — ThemeManager persistence ──
run_phase4($db, 'user_themes', "CREATE TABLE IF NOT EXISTS user_themes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role VARCHAR(50) DEFAULT NULL,
    theme VARCHAR(50) NOT NULL DEFAULT 'default',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_theme (user_id),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

// ── system_metrics — Observability data ──
run_phase4($db, 'system_metrics', "CREATE TABLE IF NOT EXISTS system_metrics (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(12,4) NOT NULL DEFAULT 0,
    tags JSON DEFAULT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_metric_time (metric_name, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

// ── error_log_entries — Structured error logging ──
run_phase4($db, 'error_log_entries', "CREATE TABLE IF NOT EXISTS error_log_entries (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    level ENUM('error','warning','notice','critical') NOT NULL DEFAULT 'error',
    message TEXT NOT NULL,
    file VARCHAR(500) DEFAULT NULL,
    line INT DEFAULT NULL,
    trace TEXT DEFAULT NULL,
    request_url VARCHAR(500) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_level_time (level, created_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $created, $errors);

// ── Summary ──
echo "Created: " . count($created) . " table(s)\n";
foreach ($created as $t) echo "  ✓ $t\n";

if ($errors) {
  echo "\nErrors: " . count($errors) . "\n";
  foreach ($errors as $e) echo "  ✗ $e\n";
}

echo "\nPhase-4 migration complete.\n";
