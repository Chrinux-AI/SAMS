<?php

/**
 * Developer — Logs Viewer
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

require_admin('../login.php');

$page_title = 'System Logs';
$page_icon = 'fas fa-scroll';
$page_subtitle = 'Error & Activity Logs';
$user_role = $_SESSION['role'] ?? '';
if ($user_role === 'admin' || $user_role === 'developer') {
  $page_css = [route('assets/theme/cyberpunk-dev.css')];
}

// Load recent logs
$logs = [];
try {
  $logs = ErrorCollector::getRecentLogs(100);
} catch (\Throwable $e) {
}

// Load system log file
$systemLogs = [];
$logFile = BASE_PATH . '/storage/logs/system.log';
if (is_file($logFile)) {
  $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $systemLogs = array_slice(array_reverse($lines), 0, 50);
}

ob_start();
?>

<style>
  .log-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .8rem;
    font-family: 'Courier New', monospace;
  }

  .log-table th {
    text-align: left;
    padding: .5rem;
    border-bottom: 1px solid rgba(255, 255, 255, .1);
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .5px;
    opacity: .6;
  }

  .log-table td {
    padding: .4rem .5rem;
    border-bottom: 1px solid rgba(255, 255, 255, .04);
    word-break: break-word;
  }

  .log-HIGH,
  .log-CRITICAL {
    color: #ff4444;
  }

  .log-MEDIUM {
    color: #ffaa00;
  }

  .log-LOW,
  .log-INFO {
    color: #00ff41;
  }

  .syslog-line {
    padding: .3rem .5rem;
    font-size: .75rem;
    font-family: monospace;
    border-bottom: 1px solid rgba(255, 255, 255, .03);
    opacity: .8;
  }

  .log-section {
    background: var(--card-bg, #1a1a2e);
    border: 1px solid rgba(255, 255, 255, .08);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    overflow-x: auto;
  }

  .log-section h3 {
    margin: 0 0 1rem;
    font-size: 1rem;
  }
</style>

<div class="log-section">
  <h3><i class="fas fa-exclamation-circle"></i> Error Collector Logs (<?= count($logs) ?>)</h3>
  <?php if (empty($logs)): ?>
    <p style="opacity:.6;">No recent logs.</p>
  <?php else: ?>
    <table class="log-table">
      <thead>
        <tr>
          <th>Time</th>
          <th>Module</th>
          <th>Severity</th>
          <th>Message</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_slice($logs, 0, 50) as $log): ?>
          <tr>
            <td style="white-space:nowrap;"><?= htmlspecialchars($log['timestamp'] ?? $log['created_at'] ?? '') ?></td>
            <td><?= htmlspecialchars($log['module'] ?? '') ?></td>
            <td class="log-<?= htmlspecialchars($log['severity'] ?? 'INFO') ?>"><?= htmlspecialchars($log['severity'] ?? 'INFO') ?></td>
            <td><?= htmlspecialchars($log['message'] ?? $log['problem'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="log-section">
  <h3><i class="fas fa-file-alt"></i> System Log File (last 50 lines)</h3>
  <?php if (empty($systemLogs)): ?>
    <p style="opacity:.6;">No system log entries.</p>
  <?php else: ?>
    <?php foreach ($systemLogs as $line): ?>
      <div class="syslog-line"><?= htmlspecialchars($line) ?></div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
