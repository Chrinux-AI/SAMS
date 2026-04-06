<?php

/**
 * Debug Overlay — Developer Diagnostic Dashboard
 * Admin-only. Shows: recent queries, missing fields, event broadcasts, sync status.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';

require_admin('../login.php');

// ── Gather Data ─────────────────────────────────────────────────

// Recent consistency issues
$consistency_issues = [];
try {
  if (table_exists('consistency_issues')) {
    $consistency_issues = db()->fetchAll(
      "SELECT * FROM consistency_issues ORDER BY created_at DESC LIMIT 30"
    );
  }
} catch (\Throwable $e) {
}

// Recent broadcast events
$recent_broadcasts = [];
try {
  if (table_exists('broadcast_events')) {
    $recent_broadcasts = db()->fetchAll(
      "SELECT * FROM broadcast_events ORDER BY created_at DESC LIMIT 20"
    );
  }
} catch (\Throwable $e) {
}

// Recent system events
$recent_events = [];
try {
  if (table_exists('system_events')) {
    $recent_events = db()->fetchAll(
      "SELECT * FROM system_events ORDER BY created_at DESC LIMIT 20"
    );
  }
} catch (\Throwable $e) {
}

// Table schema audit — detect tables with forms that might have orphan fields
$schema_audit = [];
$audit_tables = ['classes', 'users', 'attendance', 'notices', 'events'];
foreach ($audit_tables as $tbl) {
  try {
    if (table_exists($tbl)) {
      $cols = db()->fetchAll("SHOW COLUMNS FROM `{$tbl}`");
      $schema_audit[$tbl] = array_column($cols, 'Field');
    }
  } catch (\Throwable $e) {
  }
}

// Recent error log entries
$error_entries = [];
try {
  if (table_exists('error_log_entries')) {
    $error_entries = db()->fetchAll(
      "SELECT * FROM error_log_entries ORDER BY created_at DESC LIMIT 15"
    );
  }
} catch (\Throwable $e) {
}

// System metrics
$metrics = [];
try {
  if (table_exists('system_metrics')) {
    $metrics = db()->fetchAll(
      "SELECT metric_name, ROUND(AVG(metric_value), 2) AS avg_val,
                    ROUND(MAX(metric_value), 2) AS max_val, COUNT(*) AS samples
             FROM system_metrics
             WHERE recorded_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY metric_name ORDER BY metric_name"
    );
  }
} catch (\Throwable $e) {
}

$page_title    = 'Debug Overlay';
$page_icon     = 'fas fa-bug';
$page_subtitle = 'Autonomous Diagnostics Dashboard';

ob_start();
?>

<!-- Sync Status -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px,1fr)); gap:16px; margin-bottom:24px;">
  <div class="stat-card" style="--stat-color:#ef4444;">
    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
    <div class="stat-value"><?= count($consistency_issues) ?></div>
    <div class="stat-label">Consistency Issues (recent)</div>
  </div>
  <div class="stat-card" style="--stat-color:#8b5cf6;">
    <div class="stat-icon"><i class="fas fa-broadcast-tower"></i></div>
    <div class="stat-value"><?= count($recent_broadcasts) ?></div>
    <div class="stat-label">Recent Broadcasts</div>
  </div>
  <div class="stat-card" style="--stat-color:#06b6d4;">
    <div class="stat-icon"><i class="fas fa-bolt"></i></div>
    <div class="stat-value"><?= count($recent_events) ?></div>
    <div class="stat-label">Recent Events</div>
  </div>
  <div class="stat-card" style="--stat-color:#10b981;">
    <div class="stat-icon"><i class="fas fa-database"></i></div>
    <div class="stat-value"><?= count($schema_audit) ?></div>
    <div class="stat-label">Tables Audited</div>
  </div>
</div>

<!-- Consistency Issues -->
<div class="card" style="margin-bottom:24px;">
  <div class="card-header">
    <h3><i class="fas fa-shield-alt" style="color:#ef4444;"></i> Data Consistency Issues</h3>
  </div>
  <div class="card-body">
    <?php if (empty($consistency_issues)): ?>
      <p style="color:var(--text-secondary); text-align:center; padding:20px;">No consistency issues detected — data integrity OK ✓</p>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table style="width:100%; font-size:.82rem; border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:1px solid var(--border-color,#e2e8f0);">
              <th style="text-align:left; padding:8px;">Time</th>
              <th style="text-align:left; padding:8px;">Table</th>
              <th style="text-align:left; padding:8px;">Type</th>
              <th style="text-align:left; padding:8px;">Message</th>
              <th style="text-align:left; padding:8px;">URL</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($consistency_issues as $issue): ?>
              <tr style="border-bottom:1px solid var(--border-color,#f1f5f9);">
                <td style="padding:6px 8px; white-space:nowrap;"><?= htmlspecialchars($issue['created_at'] ?? '') ?></td>
                <td style="padding:6px 8px; font-family:monospace;"><?= htmlspecialchars($issue['table_name'] ?? '') ?></td>
                <td style="padding:6px 8px;">
                  <span style="background:<?= ($issue['issue_type'] ?? '') === 'missing_column' ? '#fef3c7' : '#fee2e2' ?>; color:<?= ($issue['issue_type'] ?? '') === 'missing_column' ? '#92400e' : '#991b1b' ?>; padding:2px 8px; border-radius:10px; font-size:.75rem;">
                    <?= htmlspecialchars($issue['issue_type'] ?? '') ?>
                  </span>
                </td>
                <td style="padding:6px 8px; max-width:300px; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars(mb_substr($issue['message'] ?? '', 0, 100)) ?></td>
                <td style="padding:6px 8px; font-size:.75rem;"><?= htmlspecialchars($issue['request_url'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">
  <!-- Schema Audit -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-columns"></i> Table Schema Audit</h3>
    </div>
    <div class="card-body">
      <?php foreach ($schema_audit as $tbl => $cols): ?>
        <div style="margin-bottom:16px;">
          <strong style="font-family:monospace; color:var(--primary);"><?= htmlspecialchars($tbl) ?></strong>
          <span style="font-size:.75rem; color:var(--text-secondary);"> (<?= count($cols) ?> columns)</span>
          <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:6px;">
            <?php foreach ($cols as $col): ?>
              <span style="background:var(--bg-secondary,#f1f5f9); padding:2px 8px; border-radius:4px; font-size:.72rem; font-family:monospace;"><?= htmlspecialchars($col) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Recent Broadcasts -->
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-broadcast-tower"></i> Recent Broadcasts</h3>
    </div>
    <div class="card-body">
      <?php if (empty($recent_broadcasts)): ?>
        <p style="color:var(--text-secondary); text-align:center; padding:16px;">No recent broadcasts</p>
      <?php else: ?>
        <div style="max-height:300px; overflow-y:auto;">
          <?php foreach ($recent_broadcasts as $bc): ?>
            <div style="padding:8px; border-bottom:1px solid var(--border-color,#f1f5f9); font-size:.8rem;">
              <div style="display:flex; justify-content:space-between;">
                <span style="font-family:monospace; font-weight:600;"><?= htmlspecialchars($bc['event'] ?? '') ?></span>
                <span style="color:var(--text-secondary); font-size:.72rem;"><?= htmlspecialchars($bc['created_at'] ?? '') ?></span>
              </div>
              <div style="color:var(--text-secondary); font-size:.72rem; margin-top:2px;"><?= htmlspecialchars(mb_substr($bc['data'] ?? $bc['payload'] ?? '', 0, 80)) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Error Log Entries -->
<div class="card" style="margin-bottom:24px;">
  <div class="card-header">
    <h3><i class="fas fa-exclamation-circle"></i> Structured Error Log</h3>
  </div>
  <div class="card-body">
    <?php if (empty($error_entries)): ?>
      <p style="color:var(--text-secondary); text-align:center; padding:20px;">No structured errors logged ✓</p>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table style="width:100%; font-size:.8rem; border-collapse:collapse;">
          <thead>
            <tr style="border-bottom:1px solid var(--border-color,#e2e8f0);">
              <th style="text-align:left; padding:8px;">Time</th>
              <th style="text-align:left; padding:8px;">Level</th>
              <th style="text-align:left; padding:8px;">Message</th>
              <th style="text-align:left; padding:8px;">File</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($error_entries as $err): ?>
              <tr style="border-bottom:1px solid var(--border-color,#f1f5f9);">
                <td style="padding:6px 8px; white-space:nowrap;"><?= htmlspecialchars($err['created_at'] ?? '') ?></td>
                <td style="padding:6px 8px;">
                  <span style="padding:2px 8px; border-radius:10px; font-size:.72rem; font-weight:600;
                                    background:<?= ($err['level'] ?? '') === 'critical' ? '#fee2e2' : (($err['level'] ?? '') === 'error' ? '#fef3c7' : '#e0f2fe') ?>;
                                    color:<?= ($err['level'] ?? '') === 'critical' ? '#991b1b' : (($err['level'] ?? '') === 'error' ? '#92400e' : '#075985') ?>;">
                    <?= htmlspecialchars($err['level'] ?? 'error') ?>
                  </span>
                </td>
                <td style="padding:6px 8px; max-width:300px; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars(mb_substr($err['message'] ?? '', 0, 100)) ?></td>
                <td style="padding:6px 8px; font-family:monospace; font-size:.72rem;"><?= htmlspecialchars(basename($err['file'] ?? '')) ?>:<?= $err['line'] ?? '' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- System Metrics -->
<?php if (!empty($metrics)): ?>
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-chart-bar"></i> System Metrics (24h)</h3>
    </div>
    <div class="card-body">
      <table style="width:100%; font-size:.85rem;">
        <thead>
          <tr>
            <th style="text-align:left; padding:6px;">Metric</th>
            <th style="text-align:right; padding:6px;">Avg</th>
            <th style="text-align:right; padding:6px;">Max</th>
            <th style="text-align:right; padding:6px;">Samples</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($metrics as $m): ?>
            <tr>
              <td style="padding:6px; font-family:monospace;"><?= htmlspecialchars($m['metric_name']) ?></td>
              <td style="text-align:right; padding:6px;"><?= $m['avg_val'] ?></td>
              <td style="text-align:right; padding:6px; font-weight:600;"><?= $m['max_val'] ?></td>
              <td style="text-align:right; padding:6px; color:var(--text-secondary);"><?= $m['samples'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
