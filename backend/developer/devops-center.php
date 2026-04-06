<?php

/**
 * DevOps Center — Autonomous DevOps Visual Dashboard
 * Admin-only cyberpunk-themed operational command center.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once __DIR__ . '/../app/bootstrap.php';

require_admin('../login.php');

// Gather dashboard data
$data = DevOpsKernel::getDashboardData();
$lastRun = $data['last_run'];
$metrics = $data['metrics'];
$security = $data['security'];
$drift = $data['drift'];
$incidents = $data['incidents'];
$perf = $data['performance'];
$dbSuggestions = $data['db_suggestions'];
$tableStats = $data['table_stats'];
$deploymentSafe = $data['deployment_safe'];
$learning = $data['learning'];

$systemScore = $lastRun['system_score'] ?? 0;
$healthScore = $lastRun['health_score'] ?? 0;
$securityScore = $lastRun['security_score'] ?? 100;

// Score colors
function devopsScoreColor(int $score): string
{
  if ($score >= 98) return '#00ff41';
  if ($score >= 80) return '#00d4ff';
  if ($score >= 60) return '#ffaa00';
  return '#ff4444';
}

$sysColor = devopsScoreColor($systemScore);
$hlthColor = devopsScoreColor($healthScore);
$secColor = devopsScoreColor($securityScore);

$recentLogs = [];
try {
  $recentLogs = ErrorCollector::getRecentLogs(30);
} catch (\Throwable $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>DevOps Center — <?= htmlspecialchars(APP_NAME ?? 'SAMS') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --bg: #080c14;
      --card: #0e1422;
      --card-hover: #141c2e;
      --border: #1a2540;
      --text: #d4dce8;
      --muted: #6b7b94;
      --green: #00ff41;
      --blue: #00d4ff;
      --red: #ff3b3b;
      --yellow: #ffc107;
      --purple: #b057ff;
      --glow-g: 0 0 20px rgba(0, 255, 65, .12);
      --glow-b: 0 0 20px rgba(0, 212, 255, .12);
    }

    * {
      box-sizing: border-box;
    }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Segoe UI', system-ui, sans-serif;
      margin: 0;
      min-height: 100vh;
    }

    .topbar {
      background: var(--card);
      border-bottom: 1px solid var(--border);
      padding: 10px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .topbar h1 {
      font-size: 17px;
      margin: 0;
      color: var(--green);
      text-transform: uppercase;
      letter-spacing: 2px;
      font-weight: 700;
    }

    .topbar a {
      color: var(--muted);
      text-decoration: none;
      font-size: 13px;
      margin-left: 16px;
    }

    .topbar a:hover {
      color: var(--blue);
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 14px;
      padding: 18px 24px;
      max-width: 1680px;
      margin: 0 auto;
    }

    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 18px;
      transition: border-color .2s;
    }

    .card:hover {
      border-color: #2a3a5e;
    }

    .card-title {
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: var(--muted);
      margin-bottom: 10px;
      font-weight: 700;
    }

    .span2 {
      grid-column: span 2;
    }

    .span3 {
      grid-column: span 3;
    }

    .score-big {
      font-size: 56px;
      font-weight: 800;
      line-height: 1;
      text-align: center;
      font-variant-numeric: tabular-nums;
    }

    .score-sm {
      font-size: 28px;
      font-weight: 700;
      text-align: center;
      font-variant-numeric: tabular-nums;
    }

    .score-label {
      font-size: 12px;
      color: var(--muted);
      text-align: center;
      margin-top: 2px;
    }

    .stat-row {
      display: flex;
      justify-content: space-between;
      padding: 6px 0;
      border-bottom: 1px solid #141c2e;
      font-size: 13px;
    }

    .stat-row:last-child {
      border-bottom: none;
    }

    .stat-k {
      color: var(--muted);
    }

    .stat-v {
      font-weight: 600;
      font-variant-numeric: tabular-nums;
    }

    .badge-ok {
      background: rgba(0, 255, 65, .12);
      color: var(--green);
      padding: 2px 8px;
      border-radius: 3px;
      font-size: 11px;
      font-weight: 600;
    }

    .badge-warn {
      background: rgba(255, 193, 7, .12);
      color: var(--yellow);
      padding: 2px 8px;
      border-radius: 3px;
      font-size: 11px;
      font-weight: 600;
    }

    .badge-crit {
      background: rgba(255, 59, 59, .12);
      color: var(--red);
      padding: 2px 8px;
      border-radius: 3px;
      font-size: 11px;
      font-weight: 600;
    }

    .badge-info {
      background: rgba(0, 212, 255, .12);
      color: var(--blue);
      padding: 2px 8px;
      border-radius: 3px;
      font-size: 11px;
      font-weight: 600;
    }

    .tbl {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
    }

    .tbl th {
      text-align: left;
      padding: 6px 8px;
      color: var(--muted);
      border-bottom: 1px solid var(--border);
      font-weight: 700;
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .tbl td {
      padding: 6px 8px;
      border-bottom: 1px solid #121828;
    }

    .tbl tr:hover td {
      background: var(--card-hover);
    }

    .log-box {
      background: #060a12;
      border: 1px solid var(--border);
      border-radius: 4px;
      padding: 10px;
      font-family: 'Cascadia Code', 'Fira Code', 'Consolas', monospace;
      font-size: 11px;
      max-height: 320px;
      overflow-y: auto;
      line-height: 1.5;
      color: #6b7b94;
    }

    .log-box .lh {
      color: var(--blue);
      font-weight: 600;
    }

    .log-box .le {
      color: var(--red);
    }

    .log-box .ls {
      color: var(--green);
    }

    .btn-run {
      background: linear-gradient(135deg, var(--green), #00cc33);
      color: #000;
      border: none;
      padding: 10px 22px;
      border-radius: 6px;
      font-weight: 700;
      cursor: pointer;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .btn-run:hover {
      box-shadow: var(--glow-g);
    }

    .btn-run:disabled {
      opacity: .5;
      cursor: not-allowed;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }

    .pill-safe {
      background: rgba(0, 255, 65, .08);
      color: var(--green);
      border: 1px solid rgba(0, 255, 65, .2);
    }

    .pill-danger {
      background: rgba(255, 59, 59, .08);
      color: var(--red);
      border: 1px solid rgba(255, 59, 59, .2);
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: 1
      }

      50% {
        opacity: .4
      }
    }

    .scanning {
      animation: pulse 1s infinite;
    }

    @media(max-width:900px) {
      .grid {
        grid-template-columns: 1fr;
      }

      .span2,
      .span3 {
        grid-column: span 1;
      }
    }
  </style>
</head>

<body>
  <div class="topbar">
    <h1><i class="fas fa-satellite-dish"></i>&nbsp; DevOps Center</h1>
    <div>
      <a href="../developer/autofix-center.php"><i class="fas fa-wrench"></i> Autofix</a>
      <a href="../developer/system-monitor.php"><i class="fas fa-chart-line"></i> Monitor</a>
      <a href="../admin/dashboard.php"><i class="fas fa-home"></i> Admin</a>
    </div>
  </div>

  <div class="grid">
    <!-- System Score -->
    <div class="card" style="text-align:center;">
      <div class="card-title">System Score</div>
      <div class="score-big" style="color:<?= $sysColor ?>"><?= $systemScore ?></div>
      <div class="score-label">/ 100</div>
    </div>
    <!-- Health Score -->
    <div class="card" style="text-align:center;">
      <div class="card-title">Health</div>
      <div class="score-sm" style="color:<?= $hlthColor ?>"><?= $healthScore ?></div>
      <div class="score-label">Autofix Loop</div>
    </div>
    <!-- Security Score -->
    <div class="card" style="text-align:center;">
      <div class="card-title">Security</div>
      <div class="score-sm" style="color:<?= $secColor ?>"><?= $securityScore ?></div>
      <div class="score-label">Hardener</div>
    </div>
    <!-- Deployment Status -->
    <div class="card" style="text-align:center;">
      <div class="card-title">Deployment</div>
      <div style="margin-top:10px;">
        <?php if ($deploymentSafe): ?>
          <span class="pill pill-safe"><i class="fas fa-check-circle"></i> SAFE</span>
        <?php else: ?>
          <span class="pill pill-danger"><i class="fas fa-shield-halved"></i> BLOCKED</span>
        <?php endif; ?>
      </div>
      <div class="score-label" style="margin-top:8px;">Guard Status</div>
    </div>

    <!-- Controls -->
    <div class="card">
      <div class="card-title">Controls</div>
      <button class="btn-run" id="btnRun" onclick="runDevOps()"><i class="fas fa-play"></i>&nbsp; Run DevOps Cycle</button>
      <div id="runStatus" style="margin-top:10px;font-size:12px;color:var(--muted);"></div>
      <?php if ($lastRun): ?>
        <div style="margin-top:12px;">
          <div class="stat-row"><span class="stat-k">Last Run</span><span class="stat-v"><?= htmlspecialchars($lastRun['timestamp'] ?? '') ?></span></div>
          <div class="stat-row"><span class="stat-k">Elapsed</span><span class="stat-v"><?= $lastRun['elapsed_ms'] ?? 0 ?>ms</span></div>
          <div class="stat-row"><span class="stat-k">Repairs</span><span class="stat-v"><?= $lastRun['repairs'] ?? 0 ?></span></div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Incident Status -->
    <div class="card">
      <div class="card-title">Incidents</div>
      <div class="stat-row"><span class="stat-k">Active</span><span class="stat-v"><?= $incidents['incident_count'] ?? 0 ?></span></div>
      <div class="stat-row"><span class="stat-k">Critical</span><span class="stat-v" style="color:<?= ($incidents['critical'] ?? 0) > 0 ? 'var(--red)' : 'var(--green)' ?>"><?= $incidents['critical'] ?? 0 ?></span></div>
      <div class="stat-row"><span class="stat-k">Safe Mode</span><span class="stat-v"><?= ($incidents['safe_mode'] ?? false) ? '<span class="badge-crit">ON</span>' : '<span class="badge-ok">OFF</span>' ?></span></div>
      <div class="stat-row"><span class="stat-k">Actions Taken</span><span class="stat-v"><?= $incidents['actions_taken'] ?? 0 ?></span></div>
    </div>

    <!-- Drift Status -->
    <div class="card">
      <div class="card-title">Config Drift</div>
      <div class="stat-row"><span class="stat-k">Status</span><span class="stat-v"><?= ($drift['drifted'] ?? false) ? '<span class="badge-warn">DRIFTED</span>' : '<span class="badge-ok">STABLE</span>' ?></span></div>
      <div class="stat-row"><span class="stat-k">Deviations</span><span class="stat-v"><?= $drift['deviation_count'] ?? 0 ?></span></div>
      <div class="stat-row"><span class="stat-k">Critical</span><span class="stat-v"><?= $drift['critical'] ?? 0 ?></span></div>
      <div class="stat-row"><span class="stat-k">Baseline</span><span class="stat-v"><?= ($drift['baseline_exists'] ?? false) ? '<span class="badge-ok">SET</span>' : '<span class="badge-warn">NONE</span>' ?></span></div>
    </div>

    <!-- Performance -->
    <div class="card">
      <div class="card-title">Performance</div>
      <div class="stat-row"><span class="stat-k">Cache Entries</span><span class="stat-v"><?= $perf['cache_entries'] ?? 0 ?></span></div>
      <div class="stat-row"><span class="stat-k">Cache Size</span><span class="stat-v"><?= $perf['cache_size_kb'] ?? 0 ?> KB</span></div>
      <div class="stat-row"><span class="stat-k">Compression</span><span class="stat-v"><?= ($perf['compression'] ?? false) ? '<span class="badge-ok">ZLIB</span>' : '<span class="badge-warn">OFF</span>' ?></span></div>
      <div class="stat-row"><span class="stat-k">OPcache</span><span class="stat-v"><?= ($perf['opcache'] ?? false) ? '<span class="badge-ok">ON</span>' : '<span class="badge-warn">OFF</span>' ?></span></div>
    </div>

    <!-- Metrics -->
    <div class="card span2">
      <div class="card-title">System Metrics (Latest Snapshot)</div>
      <?php if (!empty($metrics)): ?>
        <div style="max-height:260px;overflow-y:auto;">
          <table class="tbl">
            <thead>
              <tr>
                <th>Metric</th>
                <th>Value</th>
                <th>Severity</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($metrics as $m): ?>
                <tr>
                  <td><?= htmlspecialchars($m['metric'] ?? '') ?></td>
                  <td class="stat-v"><?= htmlspecialchars($m['value'] ?? '') ?></td>
                  <td>
                    <?php
                    $sev = $m['severity'] ?? 'normal';
                    if ($sev === 'critical') echo '<span class="badge-crit">CRITICAL</span>';
                    elseif ($sev === 'warning') echo '<span class="badge-warn">WARNING</span>';
                    else echo '<span class="badge-ok">NORMAL</span>';
                    ?>
                  </td>
                  <td style="white-space:nowrap;"><?= htmlspecialchars($m['recorded_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="color:var(--muted);font-size:12px;">No metrics recorded yet. Run a DevOps cycle to collect data.</p>
      <?php endif; ?>
    </div>

    <!-- DB Index Suggestions -->
    <div class="card span2">
      <div class="card-title">Database Optimization Suggestions</div>
      <?php if (!empty($dbSuggestions)): ?>
        <div style="max-height:220px;overflow-y:auto;">
          <table class="tbl">
            <thead>
              <tr>
                <th>Table</th>
                <th>Column</th>
                <th>Reason</th>
                <th>SQL</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($dbSuggestions, 0, 10) as $s): ?>
                <tr>
                  <td><?= htmlspecialchars($s['table'] ?? '') ?></td>
                  <td><?= htmlspecialchars($s['column'] ?? '') ?></td>
                  <td><?= htmlspecialchars($s['reason'] ?? '') ?></td>
                  <td style="font-family:monospace;font-size:10px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($s['sql'] ?? '') ?>"><?= htmlspecialchars($s['sql'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="color:var(--muted);font-size:12px;">No index suggestions — all checked columns are indexed.</p>
      <?php endif; ?>
    </div>

    <!-- Learning Engine -->
    <div class="card span2">
      <div class="card-title"><i class="fas fa-brain"></i>&nbsp; Learning Engine — Recurring Patterns</div>
      <?php if (!empty($learning)): ?>
        <div style="max-height:220px;overflow-y:auto;">
          <table class="tbl">
            <thead>
              <tr>
                <th>Category</th>
                <th>Pattern</th>
                <th>Action</th>
                <th>Occurrences</th>
                <th>Last Seen</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($learning as $l): ?>
                <tr>
                  <td><span class="badge-info"><?= htmlspecialchars($l['category'] ?? '') ?></span></td>
                  <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($l['pattern'] ?? '') ?></td>
                  <td><?= htmlspecialchars($l['action_taken'] ?? '') ?></td>
                  <td class="stat-v"><?= htmlspecialchars($l['occurrences'] ?? 0) ?></td>
                  <td style="white-space:nowrap;"><?= htmlspecialchars($l['last_seen'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="color:var(--muted);font-size:12px;">No patterns learned yet. Patterns accumulate over time.</p>
      <?php endif; ?>
    </div>

    <!-- Table Stats -->
    <div class="card span2">
      <div class="card-title">Database Table Stats</div>
      <?php if (!empty($tableStats)): ?>
        <div style="max-height:260px;overflow-y:auto;">
          <table class="tbl">
            <thead>
              <tr>
                <th>Table</th>
                <th>Rows</th>
                <th>Size (KB)</th>
                <th>Free (KB)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($tableStats, 0, 20) as $t): ?>
                <tr>
                  <td><?= htmlspecialchars($t['name'] ?? '') ?></td>
                  <td class="stat-v"><?= number_format((int)($t['rows'] ?? 0)) ?></td>
                  <td class="stat-v"><?= htmlspecialchars($t['size_kb'] ?? '0') ?></td>
                  <td><?= ((float)($t['free_kb'] ?? 0)) > 10 ? '<span style="color:var(--yellow)">' . htmlspecialchars($t['free_kb']) . '</span>' : htmlspecialchars($t['free_kb'] ?? '0') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="color:var(--muted);font-size:12px;">No table stats available.</p>
      <?php endif; ?>
    </div>

    <!-- Live Log -->
    <div class="card span3">
      <div class="card-title">DevOps Log <small style="float:right;font-weight:400;color:var(--muted);">logs/autofix.log</small></div>
      <div class="log-box" id="logBox">
        <?php foreach ($recentLogs as $line):
          $cls = '';
          if (str_contains($line, '═══') || str_contains($line, '===')) $cls = 'lh';
          elseif (str_contains($line, 'ERROR') || str_contains($line, 'CRITICAL') || str_contains($line, 'FATAL')) $cls = 'le';
          elseif (str_contains($line, 'Created') || str_contains($line, 'complete') || str_contains($line, 'Repaired') || str_contains($line, 'SAFE')) $cls = 'ls';
        ?>
          <div class="<?= $cls ?>"><?= htmlspecialchars($line) ?></div>
        <?php endforeach; ?>
        <?php if (empty($recentLogs)): ?>
          <div>No log entries yet. Run a DevOps cycle to generate data.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    function runDevOps() {
      const btn = document.getElementById('btnRun');
      const st = document.getElementById('runStatus');
      btn.disabled = true;
      btn.classList.add('scanning');
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>&nbsp; Running...';
      st.textContent = 'DevOps cycle running...';

      fetch('../cron/devops.php?key=dashboard', {
          credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(d => {
          btn.disabled = false;
          btn.classList.remove('scanning');
          btn.innerHTML = '<i class="fas fa-play"></i>&nbsp; Run DevOps Cycle';
          if (d.status === 'completed') {
            st.innerHTML = '<span style="color:var(--green)">✓ Complete — System: ' + d.system_score + ' | Health: ' + d.health_score + ' | Security: ' + d.security_score + ' | ' + d.elapsed_ms + 'ms</span>';
            setTimeout(() => location.reload(), 2000);
          } else if (d.status === 'skipped') {
            st.innerHTML = '<span style="color:var(--yellow)">⚠ ' + (d.message || 'Already running') + '</span>';
          } else {
            st.innerHTML = '<span style="color:var(--red)">✗ ' + (d.message || 'Error') + '</span>';
          }
        })
        .catch(e => {
          btn.disabled = false;
          btn.classList.remove('scanning');
          btn.innerHTML = '<i class="fas fa-play"></i>&nbsp; Run DevOps Cycle';
          st.innerHTML = '<span style="color:var(--red)">✗ ' + e.message + '</span>';
        });
    }
    const lb = document.getElementById('logBox');
    if (lb) lb.scrollTop = lb.scrollHeight;
  </script>
</body>

</html>
