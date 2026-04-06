<?php

/**
 * System Monitor — Developer Observability Dashboard
 * Admin-only realtime system health overview.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';

require_admin('../login.php');

// ── Gather Metrics ──────────────────────────────────────────────

// Memory
$mem_usage  = memory_get_usage(true);
$mem_peak   = memory_get_peak_usage(true);

// PHP info
$php_version = PHP_VERSION;
$sapi        = php_sapi_name();
$os          = PHP_OS;
$extensions  = get_loaded_extensions();
sort($extensions);

// Uptime (MySQL)
$db_uptime = 0;
try {
  $row = db()->fetchOne("SHOW GLOBAL STATUS LIKE 'Uptime'");
  $db_uptime = (int)($row['Value'] ?? 0);
} catch (\Throwable $e) {
}

$db_uptime_human = sprintf('%dd %dh %dm', intdiv($db_uptime, 86400), intdiv($db_uptime % 86400, 3600), intdiv($db_uptime % 3600, 60));

// DB size
$db_size = 0;
try {
  $row = db()->fetchOne(
    "SELECT SUM(data_length + index_length) AS total_size
         FROM information_schema.TABLES WHERE table_schema = ?",
    [DB_NAME]
  );
  $db_size = (int)($row['total_size'] ?? 0);
} catch (\Throwable $e) {
}

// Slow queries
$slow_queries = 0;
try {
  $row = db()->fetchOne("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
  $slow_queries = (int)($row['Value'] ?? 0);
} catch (\Throwable $e) {
}

// Active connections
$connections = 0;
try {
  $row = db()->fetchOne("SHOW GLOBAL STATUS LIKE 'Threads_connected'");
  $connections = (int)($row['Value'] ?? 0);
} catch (\Throwable $e) {
}

// Table row counts for key tables
$table_stats = [];
$key_tables = ['users', 'attendance', 'classes', 'notices', 'audit_logs', 'security_events', 'sessions'];
foreach ($key_tables as $tbl) {
  try {
    if (table_exists($tbl)) {
      $c = db()->fetchOne("SELECT COUNT(*) AS cnt FROM `{$tbl}`");
      $table_stats[$tbl] = (int)($c['cnt'] ?? 0);
    }
  } catch (\Throwable $e) {
    $table_stats[$tbl] = '—';
  }
}

// Recent errors from log file
$recent_errors = [];
$log_file = BASE_PATH . '/storage/logs/system.log';
if (is_file($log_file)) {
  $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $recent_errors = array_slice(array_reverse($lines), 0, 20);
}

// Disk usage for uploads/
$uploads_size = 0;
$uploads_path = BASE_PATH . '/uploads';
if (is_dir($uploads_path)) {
  $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads_path, RecursiveDirectoryIterator::SKIP_DOTS));
  foreach ($iter as $f) {
    $uploads_size += $f->getSize();
  }
}

// Cache stats
$cache_dir = BASE_PATH . '/cache';
$cache_files = 0;
$cache_size = 0;
if (is_dir($cache_dir)) {
  $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cache_dir, RecursiveDirectoryIterator::SKIP_DOTS));
  foreach ($iter as $f) {
    $cache_files++;
    $cache_size += $f->getSize();
  }
}

// Recent audit entries
$recent_audit = [];
try {
  if (table_exists('audit_logs')) {
    $recent_audit = db()->fetchAll(
      "SELECT al.*, u.full_name FROM audit_logs al
             LEFT JOIN users u ON al.user_id = u.id
             ORDER BY al.created_at DESC LIMIT 15"
    );
  }
} catch (\Throwable $e) {
}

// Format bytes helper
function fmt_bytes(int $bytes, int $precision = 1): string
{
  if ($bytes === 0) return '0 B';
  $units = ['B', 'KB', 'MB', 'GB'];
  $i = floor(log($bytes, 1024));
  return round($bytes / pow(1024, $i), $precision) . ' ' . $units[$i];
}

// ── Page Setup ──────────────────────────────────────────────────
$page_title    = 'System Monitor';
$page_icon     = 'fas fa-heartbeat';
$page_subtitle = 'Enterprise Observability Dashboard';

ob_start();
?>

<!-- Stats Row -->
<div class="stats-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px,1fr)); gap:16px; margin-bottom:24px;">
  <div class="stat-card" style="--stat-color:var(--primary);">
    <div class="stat-icon"><i class="fas fa-memory"></i></div>
    <div class="stat-value"><?= fmt_bytes($mem_usage) ?></div>
    <div class="stat-label">Memory Usage (peak <?= fmt_bytes($mem_peak) ?>)</div>
  </div>
  <div class="stat-card" style="--stat-color:#10b981;">
    <div class="stat-icon"><i class="fas fa-database"></i></div>
    <div class="stat-value"><?= fmt_bytes($db_size) ?></div>
    <div class="stat-label">Database Size</div>
  </div>
  <div class="stat-card" style="--stat-color:#f59e0b;">
    <div class="stat-icon"><i class="fas fa-clock"></i></div>
    <div class="stat-value"><?= $db_uptime_human ?></div>
    <div class="stat-label">MySQL Uptime</div>
  </div>
  <div class="stat-card" style="--stat-color:#ef4444;">
    <div class="stat-icon"><i class="fas fa-tachometer-alt"></i></div>
    <div class="stat-value"><?= number_format($slow_queries) ?></div>
    <div class="stat-label">Slow Queries (lifetime)</div>
  </div>
  <div class="stat-card" style="--stat-color:#8b5cf6;">
    <div class="stat-icon"><i class="fas fa-plug"></i></div>
    <div class="stat-value"><?= $connections ?></div>
    <div class="stat-label">Active DB Connections</div>
  </div>
  <div class="stat-card" style="--stat-color:#06b6d4;">
    <div class="stat-icon"><i class="fas fa-hdd"></i></div>
    <div class="stat-value"><?= fmt_bytes($uploads_size) ?></div>
    <div class="stat-label">Uploads Disk</div>
  </div>
  <div class="stat-card" style="--stat-color:#ec4899;">
    <div class="stat-icon"><i class="fas fa-archive"></i></div>
    <div class="stat-value"><?= $cache_files ?> files</div>
    <div class="stat-label">Cache (<?= fmt_bytes($cache_size) ?>)</div>
  </div>
</div>

<!-- Two-column layout -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">

  <!-- Table Row Counts -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-table"></i> Table Statistics</h3>
    </div>
    <div class="card-body">
      <table style="width:100%; font-size:.85rem;">
        <thead>
          <tr>
            <th style="text-align:left; padding:6px 8px;">Table</th>
            <th style="text-align:right; padding:6px 8px;">Rows</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($table_stats as $tbl => $cnt): ?>
            <tr>
              <td style="padding:6px 8px; font-family:monospace;"><?= htmlspecialchars($tbl) ?></td>
              <td style="text-align:right; padding:6px 8px; font-weight:600;"><?= is_int($cnt) ? number_format($cnt) : $cnt ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- PHP Environment -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fab fa-php"></i> PHP Environment</h3>
    </div>
    <div class="card-body" style="font-size:.85rem;">
      <div style="display:grid; grid-template-columns:auto 1fr; gap:4px 16px;">
        <strong>Version:</strong> <span><?= $php_version ?></span>
        <strong>SAPI:</strong> <span><?= htmlspecialchars($sapi) ?></span>
        <strong>OS:</strong> <span><?= htmlspecialchars($os) ?></span>
        <strong>Extensions:</strong> <span><?= count($extensions) ?> loaded</span>
        <strong>Max Upload:</strong> <span><?= ini_get('upload_max_filesize') ?></span>
        <strong>Memory Limit:</strong> <span><?= ini_get('memory_limit') ?></span>
        <strong>Max Exec Time:</strong> <span><?= ini_get('max_execution_time') ?>s</span>
        <strong>Session Handler:</strong> <span><?= ini_get('session.save_handler') ?></span>
      </div>
    </div>
  </div>
</div>

<!-- Recent Audit Logs -->
<div class="card" style="margin-bottom:24px;">
  <div class="card-header">
    <h3><i class="fas fa-shield-alt"></i> Recent Audit Trail</h3>
  </div>
  <div class="card-body">
    <?php if (empty($recent_audit)): ?>
      <p style="color:var(--text-secondary); text-align:center; padding:20px;">No audit logs available.</p>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table style="width:100%; font-size:.82rem; border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:1px solid var(--border-color,#e2e8f0);">
              <th style="text-align:left; padding:8px;">Time</th>
              <th style="text-align:left; padding:8px;">User</th>
              <th style="text-align:left; padding:8px;">Action</th>
              <th style="text-align:left; padding:8px;">Details</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_audit as $log): ?>
              <tr style="border-bottom:1px solid var(--border-color,#f1f5f9);">
                <td style="padding:6px 8px; white-space:nowrap;"><?= htmlspecialchars($log['created_at'] ?? '') ?></td>
                <td style="padding:6px 8px;"><?= htmlspecialchars($log['full_name'] ?? 'System') ?></td>
                <td style="padding:6px 8px; font-family:monospace; font-size:.78rem;"><?= htmlspecialchars($log['action'] ?? '') ?></td>
                <td style="padding:6px 8px; max-width:300px; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars(mb_substr($log['details'] ?? '', 0, 80)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Recent Errors -->
<div class="card" style="margin-bottom:24px;">
  <div class="card-header">
    <h3><i class="fas fa-exclamation-triangle"></i> Recent Error Log</h3>
  </div>
  <div class="card-body">
    <?php if (empty($recent_errors)): ?>
      <p style="color:var(--text-secondary); text-align:center; padding:20px;">No recent errors — system healthy! ✓</p>
    <?php else: ?>
      <pre style="background:var(--bg-secondary,#f1f5f9); padding:14px; border-radius:10px; overflow-x:auto; font-size:.75rem; line-height:1.6; max-height:400px; overflow-y:auto;"><?php
                                                                                                                                                                                    foreach ($recent_errors as $line) {
                                                                                                                                                                                      echo htmlspecialchars($line) . "\n";
                                                                                                                                                                                    }
                                                                                                                                                                                    ?></pre>
    <?php endif; ?>
  </div>
</div>

<!-- PHP Extensions -->
<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-puzzle-piece"></i> Loaded Extensions (<?= count($extensions) ?>)</h3>
  </div>
  <div class="card-body">
    <div style="display:flex; flex-wrap:wrap; gap:6px;">
      <?php foreach ($extensions as $ext): ?>
        <span style="background:var(--bg-secondary,#f1f5f9); padding:3px 10px; border-radius:6px; font-size:.75rem; font-family:monospace;"><?= htmlspecialchars($ext) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
