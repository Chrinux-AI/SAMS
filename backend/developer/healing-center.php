<?php

/**
 * Developer — Self-Healing Center Dashboard
 * Live stability monitoring, active repairs, recovered failures.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

require_admin('../login.php');

// Handle run action
if (isset($_GET['run']) && $_GET['run'] === '1') {
  $result = HealingKernel::run();
  header('Content-Type: application/json');
  echo json_encode($result);
  exit;
}

// Handle cache flush
if (isset($_GET['flush']) && $_GET['flush'] === '1') {
  $result = CacheSynchronizer::flush('all');
  header('Content-Type: application/json');
  echo json_encode(['flushed' => $result]);
  exit;
}

$dashboard = HealingKernel::getDashboardData();
$lastRun = $dashboard['last_run'];
$memory = $dashboard['memory'];
$routes = $dashboard['routes'];

$stabilityScore = $lastRun['stability_score'] ?? 0;
$lastTimestamp = $lastRun['timestamp'] ?? 'Never';
$duration = $lastRun['duration'] ?? 0;
$phases = $lastRun['phases'] ?? [];

function healColor(int $s): string
{
  if ($s >= 90) return '#00ff41';
  if ($s >= 70) return '#00e5ff';
  if ($s >= 50) return '#ffaa00';
  return '#ff4444';
}

function healBadge(string $status): string
{
  $colors = [
    'healthy' => '#00ff41',
    'verified' => '#00ff41',
    'all_healthy' => '#00ff41',
    'passed' => '#00ff41',
    'degraded' => '#ffaa00',
    'issues' => '#ffaa00',
    'issues_found' => '#ffaa00',
    'failed' => '#ff4444',
    'error' => '#ff4444',
  ];
  $c = $colors[$status] ?? '#888';
  $safe = htmlspecialchars($status);
  return "<span style='background:rgba(" . implode(',', array_map('hexdec', str_split(ltrim($c, '#'), 2))) . ",0.12);color:$c;padding:2px 10px;border-radius:8px;font-size:0.78rem'>$safe</span>";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Healing Center — <?= htmlspecialchars(APP_NAME ?? 'SAMS') ?></title>
  <link rel="stylesheet" href="<?= route('assets/theme/cyberpunk-dev.css') ?>">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', system-ui, sans-serif;
      background: #0a0e1a;
      color: #c0d0e0;
      min-height: 100vh;
    }

    .top-bar {
      background: rgba(10, 14, 26, 0.95);
      border-bottom: 1px solid rgba(0, 229, 255, 0.12);
      padding: 14px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .top-bar h1 {
      font-size: 1.1rem;
    }

    .top-bar a {
      color: #00e5ff;
      text-decoration: none;
      font-size: 0.85rem;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 24px;
    }

    .score-hero {
      text-align: center;
      padding: 32px;
      margin-bottom: 24px;
      border-radius: 12px;
      background: linear-gradient(135deg, rgba(0, 229, 255, 0.06), rgba(0, 255, 65, 0.04));
      border: 1px solid rgba(0, 229, 255, 0.12);
    }

    .score-value {
      font-size: 4rem;
      font-weight: 700;
      line-height: 1;
    }

    .score-label {
      font-size: 0.9rem;
      color: #6a8aaa;
      margin-top: 6px;
    }

    .score-meta {
      display: flex;
      justify-content: center;
      gap: 24px;
      margin-top: 12px;
      font-size: 0.82rem;
      color: #4a6a8a;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }

    .card {
      background: rgba(12, 20, 40, 0.92);
      border: 1px solid rgba(0, 229, 255, 0.08);
      border-radius: 10px;
      padding: 20px;
    }

    .card h3 {
      font-size: 0.95rem;
      margin-bottom: 12px;
      color: #e0e8f0;
    }

    .metric {
      display: flex;
      justify-content: space-between;
      padding: 6px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.03);
      font-size: 0.85rem;
    }

    .metric:last-child {
      border-bottom: none;
    }

    .btn-action {
      display: inline-block;
      padding: 8px 20px;
      border-radius: 6px;
      font-size: 0.85rem;
      cursor: pointer;
      border: none;
      text-decoration: none;
      margin-right: 8px;
    }

    .btn-run {
      background: linear-gradient(135deg, #00e5ff, #00b8d4);
      color: #000;
    }

    .btn-flush {
      background: linear-gradient(135deg, #e040fb, #aa00ff);
      color: #fff;
    }

    .btn-action:hover {
      transform: translateY(-1px);
    }

    .actions {
      text-align: center;
      margin-bottom: 24px;
    }

    .phase-list {
      font-size: 0.83rem;
    }

    .phase-item {
      padding: 6px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    }

    .phase-item:last-child {
      border-bottom: none;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.82rem;
    }

    table th {
      text-align: left;
      padding: 8px;
      border-bottom: 1px solid rgba(0, 229, 255, 0.1);
      color: #6a8aaa;
    }

    table td {
      padding: 8px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.03);
    }
  </style>
</head>

<body>

  <div class="top-bar">
    <h1>Self-Healing Center</h1>
    <a href="<?= dev_route('index.php') ?>">&#8592; Developer Portal</a>
  </div>

  <div class="container">
    <!-- Score Hero -->
    <div class="score-hero">
      <div class="score-value" style="color:<?= healColor($stabilityScore) ?>"><?= $stabilityScore ?></div>
      <div class="score-label">Platform Stability Score</div>
      <div class="score-meta">
        <span>Last cycle: <?= htmlspecialchars($lastTimestamp) ?></span>
        <span>Duration: <?= $duration ?>s</span>
        <span>Repairs today: <?= $memory['today'] ?? 0 ?></span>
      </div>
    </div>

    <!-- Actions -->
    <div class="actions">
      <button class="btn-action btn-run" onclick="runHealing()">Run Healing Cycle</button>
      <button class="btn-action btn-flush" onclick="flushCache()">Flush All Caches</button>
    </div>

    <!-- Phase Cards -->
    <div class="grid">
      <!-- Detection -->
      <div class="card">
        <h3>Fault Detection</h3>
        <div class="metric"><span>Faults Found</span><span><?= $phases['detection']['faults_found'] ?? 0 ?></span></div>
        <?php if (!empty($phases['detection']['faults'] ?? [])): ?>
          <div class="phase-list">
            <?php foreach (array_slice($phases['detection']['faults'], 0, 5) as $f): ?>
              <div class="phase-item"><?= htmlspecialchars($f) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Repair -->
      <div class="card">
        <h3>Auto Repair</h3>
        <div class="metric"><span>Attempted</span><span><?= $phases['repair']['attempted'] ?? 0 ?></span></div>
        <div class="metric"><span>Repaired</span><span style="color:#00ff41"><?= $phases['repair']['repaired'] ?? 0 ?></span></div>
        <?php if (!empty($phases['repair']['outcomes'] ?? [])): ?>
          <div class="phase-list">
            <?php foreach (array_slice($phases['repair']['outcomes'], 0, 5) as $o): ?>
              <div class="phase-item"><?= htmlspecialchars($o) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Verification -->
      <div class="card">
        <h3>Integrity Verification</h3>
        <?php
        $vPhase = $phases['verification'] ?? [];
        if (isset($vPhase['health_check'])): ?>
          <div class="metric"><span>Health Check</span><?= healBadge($vPhase['health_check']) ?></div>
        <?php else: ?>
          <div class="metric"><span>Verified</span><span><?= $vPhase['verified'] ?? 0 ?>/<?= $vPhase['total'] ?? 0 ?></span></div>
        <?php endif; ?>
      </div>

      <!-- UI Integrity -->
      <div class="card">
        <h3>UI Integrity</h3>
        <?php $ui = $phases['ui_integrity'] ?? []; ?>
        <div class="metric"><span>Tab Issues</span><span><?= $ui['tab_issues'] ?? 0 ?></span></div>
        <div class="metric"><span>Dashboard Issues</span><span><?= $ui['dashboard_issues'] ?? 0 ?></span></div>
        <div class="metric"><span>Layout Issues</span><span><?= $ui['layout_issues'] ?? 0 ?></span></div>
        <div class="metric"><span>Status</span><?= healBadge($ui['status'] ?? 'unknown') ?></div>
      </div>

      <!-- Services -->
      <div class="card">
        <h3>Service Health</h3>
        <?php $svc = $phases['services'] ?? []; ?>
        <div class="metric"><span>Services</span><span><?= $svc['total_services'] ?? 0 ?></span></div>
        <div class="metric"><span>Healthy</span><span style="color:#00ff41"><?= $svc['healthy'] ?? 0 ?></span></div>
        <div class="metric"><span>Status</span><?= healBadge($svc['status'] ?? 'unknown') ?></div>
      </div>

      <!-- Routes -->
      <div class="card">
        <h3>Route Index</h3>
        <div class="metric"><span>Total Routes</span><span><?= $routes['total_routes'] ?? 0 ?></span></div>
        <div class="metric"><span>Index Age</span><span><?= ($routes['index_age'] ?? -1) >= 0 ? ($routes['index_age'] . 's') : 'N/A' ?></span></div>
      </div>

      <!-- Healing Memory -->
      <div class="card">
        <h3>Healing Memory</h3>
        <div class="metric"><span>Total Repairs</span><span><?= $memory['total_repairs'] ?? 0 ?></span></div>
        <div class="metric"><span>Success Rate</span><span><?= $memory['success_rate'] ?? 0 ?>%</span></div>
        <div class="metric"><span>Today</span><span><?= $memory['today'] ?? 0 ?></span></div>
      </div>

      <!-- Schema -->
      <div class="card">
        <h3>Schema Repair</h3>
        <?php $schema = $phases['schema'] ?? []; ?>
        <div class="metric"><span>Index Fixes</span><span><?= count($schema['indexes'] ?? []) ?></span></div>
        <div class="metric"><span>Column Fixes</span><span><?= count($schema['columns'] ?? []) ?></span></div>
        <div class="metric"><span>Orphans</span><span><?= count($schema['orphans'] ?? []) ?></span></div>
      </div>
    </div>

    <!-- Top Faults Table -->
    <?php if (!empty($memory['top_faults'] ?? [])): ?>
      <div class="card" style="margin-bottom:24px">
        <h3>Top Fault Types (Learned)</h3>
        <table>
          <thead>
            <tr>
              <th>Fault Type</th>
              <th>Count</th>
              <th>Fix Rate</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($memory['top_faults'] as $tf): ?>
              <tr>
                <td><?= htmlspecialchars($tf['fault_type']) ?></td>
                <td><?= $tf['cnt'] ?></td>
                <td><?= $tf['rate'] ?>%</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <script>
    async function runHealing() {
      const btn = document.querySelector('.btn-run');
      btn.textContent = 'Running...';
      btn.disabled = true;
      try {
        const r = await fetch('?run=1');
        const d = await r.json();
        alert('Healing cycle complete. Stability: ' + (d.stability_score || '?') + '/100');
        location.reload();
      } catch (e) {
        alert('Healing cycle failed: ' + e.message);
      } finally {
        btn.textContent = 'Run Healing Cycle';
        btn.disabled = false;
      }
    }

    async function flushCache() {
      const btn = document.querySelector('.btn-flush');
      btn.textContent = 'Flushing...';
      btn.disabled = true;
      try {
        const r = await fetch('?flush=1');
        const d = await r.json();
        alert('Caches flushed: ' + JSON.stringify(d.flushed));
        location.reload();
      } catch (e) {
        alert('Flush failed: ' + e.message);
      } finally {
        btn.textContent = 'Flush All Caches';
        btn.disabled = false;
      }
    }
  </script>

</body>

</html>
