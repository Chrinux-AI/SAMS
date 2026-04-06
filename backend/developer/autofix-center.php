<?php

/**
 * Autofix Center — Autonomous Fix Loop Visual Dashboard
 * Admin-only dashboard for the self-healing engineering system.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once __DIR__ . '/../app/bootstrap.php';

require_admin('../login.php');

// Gather data
$lastRun = AutonomousFixLoop::getLastRun();
$summary = null;
$recentLogs = [];
$recentFailures = [];

try {
  $summary = HealthReporter::getSummary();
} catch (\Throwable $e) {
  $summary = ['score' => 0, 'grade' => '?', 'issues' => 0, 'tests_passed' => 0, 'tests_total' => 0, 'timestamp' => 'N/A'];
}

try {
  $recentLogs = ErrorCollector::getRecentLogs(50);
} catch (\Throwable $e) {
  $recentLogs = [];
}

try {
  $recentFailures = ErrorCollector::getRecentFailures(25);
} catch (\Throwable $e) {
  $recentFailures = [];
}

// Score color
$scoreColor = '#00ff41';
if ($summary['score'] < 98) $scoreColor = '#00d4ff';
if ($summary['score'] < 80) $scoreColor = '#ffaa00';
if ($summary['score'] < 60) $scoreColor = '#ff4444';

$pageTitle = 'Autofix Center';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(APP_NAME ?? 'SAMS') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --bg-primary: #0a0e17;
      --bg-card: #111827;
      --bg-card-hover: #1a2332;
      --border: #1e293b;
      --text-primary: #e2e8f0;
      --text-secondary: #94a3b8;
      --accent-green: #00ff41;
      --accent-blue: #00d4ff;
      --accent-red: #ff4444;
      --accent-yellow: #ffaa00;
      --glow-green: 0 0 20px rgba(0, 255, 65, .15);
      --glow-blue: 0 0 20px rgba(0, 212, 255, .15);
    }

    * {
      box-sizing: border-box;
    }

    body {
      background: var(--bg-primary);
      color: var(--text-primary);
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      margin: 0;
      padding: 0;
      min-height: 100vh;
    }

    .top-bar {
      background: var(--bg-card);
      border-bottom: 1px solid var(--border);
      padding: 12px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .top-bar h1 {
      font-size: 18px;
      margin: 0;
      color: var(--accent-green);
      text-transform: uppercase;
      letter-spacing: 2px;
      font-weight: 600;
    }

    .top-bar .back-link {
      color: var(--text-secondary);
      text-decoration: none;
      font-size: 13px;
    }

    .top-bar .back-link:hover {
      color: var(--accent-blue);
    }

    .dash-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 16px;
      padding: 20px 24px;
      max-width: 1600px;
      margin: 0 auto;
    }

    .card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 20px;
      transition: border-color .2s;
    }

    .card:hover {
      border-color: #334155;
    }

    .card-title {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: var(--text-secondary);
      margin-bottom: 12px;
      font-weight: 600;
    }

    /* Score card */
    .score-card {
      grid-column: span 1;
      text-align: center;
    }

    .score-value {
      font-size: 72px;
      font-weight: 700;
      line-height: 1;
      font-variant-numeric: tabular-nums;
    }

    .score-label {
      font-size: 14px;
      color: var(--text-secondary);
      margin-top: 4px;
    }

    .score-grade {
      display: inline-block;
      font-size: 24px;
      font-weight: 700;
      margin-top: 8px;
      padding: 4px 16px;
      border-radius: 4px;
      border: 1px solid;
    }

    /* Stats row */
    .stat-row {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      border-bottom: 1px solid var(--border);
      font-size: 14px;
    }

    .stat-row:last-child {
      border-bottom: none;
    }

    .stat-label {
      color: var(--text-secondary);
    }

    .stat-value {
      font-weight: 600;
      font-variant-numeric: tabular-nums;
    }

    /* Log viewer */
    .log-card {
      grid-column: 1 / -1;
    }

    .log-viewer {
      background: #0d1117;
      border: 1px solid var(--border);
      border-radius: 4px;
      padding: 12px;
      font-family: 'Cascadia Code', 'Fira Code', 'Consolas', monospace;
      font-size: 12px;
      max-height: 400px;
      overflow-y: auto;
      line-height: 1.6;
      color: #8b949e;
    }

    .log-viewer .log-line {
      white-space: pre-wrap;
      word-break: break-all;
    }

    .log-viewer .log-line.error {
      color: var(--accent-red);
    }

    .log-viewer .log-line.success {
      color: var(--accent-green);
    }

    .log-viewer .log-line.heading {
      color: var(--accent-blue);
      font-weight: 600;
    }

    /* Failures table */
    .fail-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }

    .fail-table th {
      text-align: left;
      padding: 8px;
      color: var(--text-secondary);
      border-bottom: 1px solid var(--border);
      font-weight: 600;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .fail-table td {
      padding: 8px;
      border-bottom: 1px solid #1a1f2e;
    }

    .fail-table tr:hover td {
      background: var(--bg-card-hover);
    }

    .badge-success {
      background: rgba(0, 255, 65, .15);
      color: var(--accent-green);
      padding: 2px 8px;
      border-radius: 3px;
      font-size: 11px;
    }

    .badge-fail {
      background: rgba(255, 68, 68, .15);
      color: var(--accent-red);
      padding: 2px 8px;
      border-radius: 3px;
      font-size: 11px;
    }

    /* Run button */
    .btn-run {
      background: linear-gradient(135deg, #00ff41, #00cc33);
      color: #000;
      border: none;
      padding: 10px 24px;
      border-radius: 6px;
      font-weight: 700;
      cursor: pointer;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 1px;
      transition: box-shadow .2s;
    }

    .btn-run:hover {
      box-shadow: var(--glow-green);
    }

    .btn-run:disabled {
      opacity: .5;
      cursor: not-allowed;
    }

    /* Pulse animation for active scan */
    @keyframes pulse {

      0%,
      100% {
        opacity: 1;
      }

      50% {
        opacity: .4;
      }
    }

    .scanning {
      animation: pulse 1s infinite;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .dash-grid {
        grid-template-columns: 1fr;
        padding: 12px;
      }

      .score-value {
        font-size: 48px;
      }
    }
  </style>
</head>

<body>

  <div class="top-bar">
    <h1><i class="fas fa-shield-halved"></i>&nbsp; Autofix Center</h1>
    <div>
      <a class="back-link" href="../developer/system-monitor.php">
        <i class="fas fa-arrow-left"></i> System Monitor
      </a>
      &nbsp;&nbsp;
      <a class="back-link" href="../admin/dashboard.php">
        <i class="fas fa-home"></i> Admin
      </a>
    </div>
  </div>

  <div class="dash-grid">
    <!-- Health Score -->
    <div class="card score-card">
      <div class="card-title">System Health</div>
      <div class="score-value" id="scoreValue" style="color: <?= $scoreColor ?>">
        <?= $summary['score'] ?>
      </div>
      <div class="score-label">out of 100</div>
      <div class="score-grade" style="color: <?= $scoreColor ?>; border-color: <?= $scoreColor ?>">
        <?= htmlspecialchars($summary['grade']) ?>
      </div>
    </div>

    <!-- Quick Stats -->
    <div class="card">
      <div class="card-title">Current Status</div>
      <div class="stat-row">
        <span class="stat-label">Issues Detected</span>
        <span class="stat-value"><?= $summary['issues'] ?></span>
      </div>
      <div class="stat-row">
        <span class="stat-label">Tests Passed</span>
        <span class="stat-value"><?= $summary['tests_passed'] ?>/<?= $summary['tests_total'] ?></span>
      </div>
      <div class="stat-row">
        <span class="stat-label">Last Scan</span>
        <span class="stat-value"><?= htmlspecialchars($summary['timestamp']) ?></span>
      </div>
    </div>

    <!-- Last Run -->
    <div class="card">
      <div class="card-title">Last Autofix Run</div>
      <?php if ($lastRun): ?>
        <div class="stat-row">
          <span class="stat-label">Score</span>
          <span class="stat-value"><?= $lastRun['score'] ?>/100 (<?= htmlspecialchars($lastRun['grade']) ?>)</span>
        </div>
        <div class="stat-row">
          <span class="stat-label">Iterations</span>
          <span class="stat-value"><?= $lastRun['iterations'] ?></span>
        </div>
        <div class="stat-row">
          <span class="stat-label">Repairs</span>
          <span class="stat-value"><?= $lastRun['repairs'] ?></span>
        </div>
        <div class="stat-row">
          <span class="stat-label">Timestamp</span>
          <span class="stat-value"><?= htmlspecialchars($lastRun['timestamp']) ?></span>
        </div>
      <?php else: ?>
        <p style="color:var(--text-secondary);font-size:13px;">No autofix runs recorded yet.</p>
      <?php endif; ?>
    </div>

    <!-- Controls -->
    <div class="card">
      <div class="card-title">Controls</div>
      <button class="btn-run" id="btnRunNow" onclick="triggerAutofix()">
        <i class="fas fa-play"></i>&nbsp; Run Autofix Now
      </button>
      <div id="runStatus" style="margin-top:12px;font-size:13px;color:var(--text-secondary);"></div>
    </div>

    <!-- Repair History -->
    <div class="card" style="grid-column: span 2;">
      <div class="card-title">Recent Failures &amp; Repairs</div>
      <?php if (!empty($recentFailures)): ?>
        <div style="max-height:300px;overflow-y:auto;">
          <table class="fail-table">
            <thead>
              <tr>
                <th>Module</th>
                <th>Type</th>
                <th>Fix Applied</th>
                <th>Result</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentFailures as $f): ?>
                <tr>
                  <td><?= htmlspecialchars($f['module'] ?? '') ?></td>
                  <td><?= htmlspecialchars($f['error_type'] ?? '') ?></td>
                  <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= htmlspecialchars($f['fix_applied'] ?? '') ?>
                  </td>
                  <td>
                    <?php if (!empty($f['success'])): ?>
                      <span class="badge-success">Fixed</span>
                    <?php else: ?>
                      <span class="badge-fail">Failed</span>
                    <?php endif; ?>
                  </td>
                  <td style="white-space:nowrap;"><?= htmlspecialchars($f['created_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="color:var(--text-secondary);font-size:13px;">No failures recorded yet.</p>
      <?php endif; ?>
    </div>

    <!-- Live Log -->
    <div class="card log-card">
      <div class="card-title">Autofix Log <small style="float:right;font-weight:400;">logs/autofix.log</small></div>
      <div class="log-viewer" id="logViewer">
        <?php
        if (!empty($recentLogs)):
          foreach ($recentLogs as $line):
            $cls = '';
            if (str_contains($line, '===')) $cls = 'heading';
            elseif (str_contains($line, 'ERROR') || str_contains($line, 'FATAL') || str_contains($line, 'Failed')) $cls = 'error';
            elseif (str_contains($line, 'Created') || str_contains($line, 'Repaired') || str_contains($line, 'complete')) $cls = 'success';
        ?>
            <div class="log-line <?= $cls ?>"><?= htmlspecialchars($line) ?></div>
          <?php
          endforeach;
        else:
          ?>
          <div class="log-line">No log entries yet. Run the autofix loop to generate data.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    function triggerAutofix() {
      const btn = document.getElementById('btnRunNow');
      const status = document.getElementById('runStatus');

      btn.disabled = true;
      btn.classList.add('scanning');
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>&nbsp; Running...';
      status.textContent = 'Autofix loop is running...';

      fetch('../cron/autofix.php?key=dashboard', {
          method: 'GET',
          credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
          btn.disabled = false;
          btn.classList.remove('scanning');
          btn.innerHTML = '<i class="fas fa-play"></i>&nbsp; Run Autofix Now';

          if (data.status === 'completed') {
            status.innerHTML = '<span style="color:var(--accent-green)">✓ Complete — Score: ' +
              data.score + '/100 (' + data.grade + '), ' + data.repairs + ' repair(s)</span>';
            // Refresh page after 2 seconds to show updated data
            setTimeout(() => location.reload(), 2000);
          } else if (data.status === 'skipped') {
            status.innerHTML = '<span style="color:var(--accent-yellow)">⚠ ' + (data.message || 'Already running') + '</span>';
          } else {
            status.innerHTML = '<span style="color:var(--accent-red)">✗ ' + (data.message || 'Unknown error') + '</span>';
          }
        })
        .catch(err => {
          btn.disabled = false;
          btn.classList.remove('scanning');
          btn.innerHTML = '<i class="fas fa-play"></i>&nbsp; Run Autofix Now';
          status.innerHTML = '<span style="color:var(--accent-red)">✗ Request failed: ' + err.message + '</span>';
        });
    }

    // Auto-scroll log viewer to bottom
    const lv = document.getElementById('logViewer');
    if (lv) lv.scrollTop = lv.scrollHeight;
  </script>
</body>

</html>
