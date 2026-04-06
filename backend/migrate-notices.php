<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

echo "Adding scheduled_at and visibility columns to notices...\n";

try {
  $cols = db()->fetchAll('SHOW COLUMNS FROM notices LIKE "scheduled_at"');
  if (empty($cols)) {
    db()->query('ALTER TABLE notices ADD COLUMN scheduled_at DATETIME DEFAULT NULL AFTER expires_at');
    echo "[OK] Added scheduled_at column.\n";
  } else {
    echo "[SKIP] scheduled_at already exists.\n";
  }
} catch (Exception $e) {
  echo "[ERR] scheduled_at: " . $e->getMessage() . "\n";
}

try {
  $cols = db()->fetchAll('SHOW COLUMNS FROM notices LIKE "visibility"');
  if (empty($cols)) {
    db()->query("ALTER TABLE notices ADD COLUMN visibility VARCHAR(20) DEFAULT 'authenticated' AFTER scheduled_at");
    echo "[OK] Added visibility column.\n";
  } else {
    echo "[SKIP] visibility already exists.\n";
  }
} catch (Exception $e) {
  echo "[ERR] visibility: " . $e->getMessage() . "\n";
}

echo "Done!\n";
