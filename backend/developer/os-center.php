<?php

/**
 * Developer Portal — OS Center Dashboard
 *
 * Autonomous School Operating System control center.
 * Shows OS health, subsystem status, process scheduler, automation rules.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

require_admin('../login.php');

// Handle AJAX actions
if (isset($_GET['action'])) {
  header('Content-Type: application/json');
  $action = $_GET['action'];

  if ($action === 'run_cycle') {
    try {
      ProcessScheduler::seedDefaults();
      AutomationEngine::seedDefaults();
      $result = OSKernel::run();
      echo json_encode(['success' => true, 'result' => $result]);
    } catch (\Throwable $e) {
      echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
  }

  if ($action === 'get_state') {
    try {
      $state = InstitutionalState::snapshot();
      echo json_encode(['success' => true, 'state' => $state]);
    } catch (\Throwable $e) {
      echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
  }

  echo json_encode(['error' => 'Unknown action']);
  exit;
}

// Load data
$osData       = OSKernel::getDashboardData();
$osHealth     = $osData['os_health'] ?? 0;
$schedulerStats = ProcessScheduler::getStats();
$automationStats = AutomationEngine::getStats();
$deviceStats  = DeviceIntegration::getStats();
$resourceStats = ResourceManager::getStats();
$policyStats  = PolicyRuntime::getStats();
$lastRun      = $osData['timestamp'] ?? 'Never';
$duration     = $osData['duration'] ?? 0;

$page_title = 'OS Center';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?> — Developer Portal</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'JetBrains Mono', 'Fira Code', monospace;
      background: #0a0a1a;
      color: #e0e0e0;
      min-height: 100vh;
    }

    .top-bar {
      background: linear-gradient(90deg, #0d0d2b, #1a1a3e);
      border-bottom: 1px solid rgba(0, 229, 255, .15);
      padding: .8rem 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .top-bar a {
      color: #00e5ff;
      text-decoration: none;
      font-size: .85rem;
    }

    .top-bar h1 {
      font-size: 1.1rem;
      color: #00e5ff;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 1.5rem;
    }

    .score-hero {
      text-align: center;
      padding: 2rem;
      background: linear-gradient(135deg, rgba(0, 229, 255, .08), rgba(224, 64, 251, .08));
      border: 1px solid rgba(0, 229, 255, .15);
      border-radius: 16px;
      margin-bottom: 1.5rem;
    }

    .score-hero .score {
      font-size: 4rem;
      font-weight: 900;
      background: linear-gradient(135deg, #00e5ff, #e040fb);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .score-hero .label {
      font-size: .8rem;
      opacity: .6;
      margin-top: .3rem;
    }

    .score-meta {
      display: flex;
      justify-content: center;
      gap: 3rem;
      margin-top: 1rem;
    }

    .score-meta .item {
      text-align: center;
    }

    .score-meta .val {
      font-size: 1.3rem;
      font-weight: 700;
      color: #00e5ff;
    }

    .score-meta .lbl {
      font-size: .7rem;
      opacity: .5;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .card {
      background: #111128;
      border: 1px solid rgba(255, 255, 255, .06);
      border-radius: 12px;
      padding: 1.2rem;
    }

    .card h3 {
      font-size: .85rem;
      font-weight: 600;
      margin-bottom: .8rem;
      color: #00e5ff;
      display: flex;
      align-items: center;
      gap: .5rem;
    }

    .card .stat {
      font-size: 2rem;
      font-weight: 800;
      color: #fff;
    }

    .card .detail {
      font-size: .75rem;
      opacity: .5;
      margin-top: .3rem;
    }

    .btn {
      background: linear-gradient(135deg, #00e5ff, #00b8d4);
      color: #000;
      border: none;
      padding: .7rem 1.5rem;
      border-radius: 8px;
      font-weight: 700;
      cursor: pointer;
      font-size: .85rem;
      margin: .3rem;
    }

    .btn:hover {
      opacity: .9;
      transform: translateY(-1px);
    }

    .btn-secondary {
      background: rgba(255, 255, 255, .08);
      color: #e0e0e0;
      border: 1px solid rgba(255, 255, 255, .1);
    }

    .actions {
      text-align: center;
      margin-bottom: 1.5rem;
    }

    .phases-table {
      width: 100%;
      border-collapse: collapse;
      background: #111128;
      border-radius: 12px;
      overflow: hidden;
    }

    .phases-table th,
    .phases-table td {
      padding: .6rem 1rem;
      text-align: left;
      border-bottom: 1px solid rgba(255, 255, 255, .05);
      font-size: .8rem;
    }

    .phases-table th {
      color: #00e5ff;
      font-weight: 600;
    }

    .status-ok {
      color: #00ff41;
    }

    .status-error {
      color: #f44336;
    }

    .status-warn {
      color: #ffab00;
    }

    #result {
      margin-top: 1rem;
      padding: 1rem;
      background: #111128;
      border-radius: 8px;
      display: none;
      font-size: .8rem;
    }
  </style>
</head>

<body>

  <div class="top-bar">
    <a href="<?= dev_route('index.php') ?>"><i class="fas fa-arrow-left"></i> Portal</a>
    <h1><i class="fas fa-microchip"></i> OS Center</h1>
    <span style="font-size:.75rem;opacity:.5"><?= date('H:i:s') ?></span>
  </div>

  <div class="container">

    <div class="score-hero">
      <div class="score"><?= $osHealth ?>/100</div>
      <div class="label">OS Health Score</div>
      <div class="score-meta">
        <div class="item">
          <div class="val"><?= $schedulerStats['total_tasks'] ?? 0 ?></div>
          <div class="lbl">Tasks</div>
        </div>
        <div class="item">
          <div class="val"><?= $automationStats['total_rules'] ?? 0 ?></div>
          <div class="lbl">Rules</div>
        </div>
        <div class="item">
          <div class="val"><?= $deviceStats['total'] ?? 0 ?></div>
          <div class="lbl">Devices</div>
        </div>
        <div class="item">
          <div class="val"><?= round($duration, 2) ?>s</div>
          <div class="lbl">Duration</div>
        </div>
      </div>
    </div>

    <div class="actions">
      <button class="btn" onclick="runCycle()"><i class="fas fa-play"></i> Run OS Cycle</button>
      <button class="btn btn-secondary" onclick="getState()"><i class="fas fa-eye"></i> Institutional State</button>
    </div>
    <div id="result"></div>

    <div class="grid">
      <div class="card">
        <h3><i class="fas fa-clock"></i> Process Scheduler</h3>
        <div class="stat"><?= $schedulerStats['enabled'] ?? 0 ?></div>
        <div class="detail">Active tasks / <?= $schedulerStats['total_tasks'] ?? 0 ?> total — <?= $schedulerStats['total_runs'] ?? 0 ?> runs</div>
      </div>
      <div class="card">
        <h3><i class="fas fa-bolt"></i> Automation Engine</h3>
        <div class="stat"><?= $automationStats['enabled'] ?? 0 ?></div>
        <div class="detail">Active rules — <?= $automationStats['total_triggers'] ?? 0 ?> triggers</div>
      </div>
      <div class="card">
        <h3><i class="fas fa-comments"></i> Communication OS</h3>
        <div class="stat"><span class="status-ok">Online</span></div>
        <div class="detail">In-app, email, WhatsApp channels</div>
      </div>
      <div class="card">
        <h3><i class="fas fa-tablet-alt"></i> Device Integration</h3>
        <div class="stat"><?= $deviceStats['total'] ?? 0 ?></div>
        <div class="detail">Registered devices</div>
      </div>
      <div class="card">
        <h3><i class="fas fa-shield-alt"></i> Policy Runtime</h3>
        <div class="stat"><span class="status-ok"><?= $policyStats['session_status'] ?? 'N/A' ?></span></div>
        <div class="detail">Rate: <?= $policyStats['rate_current'] ?? 0 ?>/<?= $policyStats['rate_limit'] ?? 120 ?></div>
      </div>
      <div class="card">
        <h3><i class="fas fa-folder-open"></i> Resource Manager</h3>
        <div class="stat"><?= $resourceStats['uploads_files'] ?? 0 ?></div>
        <div class="detail">Files — <?= $resourceStats['uploads_size'] ?? '0 B' ?> total</div>
      </div>
      <div class="card">
        <h3><i class="fas fa-graduation-cap"></i> Academic Runtime</h3>
        <div class="stat"><span class="status-ok">Active</span></div>
        <div class="detail">Attendance & class management</div>
      </div>
      <div class="card">
        <h3><i class="fas fa-id-card"></i> Identity Core</h3>
        <div class="stat"><span class="status-ok">Secured</span></div>
        <div class="detail">Session & role management</div>
      </div>
    </div>

    <?php if (!empty($osData['phases'])): ?>
      <h3 style="color:#00e5ff;margin-bottom:.8rem;font-size:.9rem"><i class="fas fa-list"></i> Last Cycle Phases</h3>
      <table class="phases-table">
        <thead>
          <tr>
            <th>Phase</th>
            <th>Status</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($osData['phases'] as $name => $phase): ?>
            <tr>
              <td><?= htmlspecialchars($name) ?></td>
              <td>
                <?php if (isset($phase['error'])): ?>
                  <span class="status-error"><i class="fas fa-times-circle"></i> Error</span>
                <?php else: ?>
                  <span class="status-ok"><i class="fas fa-check-circle"></i> OK</span>
                <?php endif; ?>
              </td>
              <td style="opacity:.6"><?= htmlspecialchars(substr(json_encode($phase), 0, 120)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <div style="text-align:center;margin-top:2rem;font-size:.7rem;opacity:.3">
      Last run: <?= htmlspecialchars($lastRun) ?> — SAMS Autonomous School OS v1.0
    </div>
  </div>

  <script>
    async function runCycle() {
      const res = document.getElementById('result');
      res.style.display = 'block';
      res.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running OS cycle...';
      try {
        const r = await fetch('?action=run_cycle');
        const d = await r.json();
        if (d.success) {
          res.innerHTML = '<span class="status-ok">✓</span> OS cycle complete — Health: ' + (d.result.os_health || 0) + '/100, Duration: ' + (d.result.duration || 0) + 's';
          setTimeout(() => location.reload(), 2000);
        } else {
          res.innerHTML = '<span class="status-error">✗</span> ' + (d.error || 'Unknown error');
        }
      } catch (e) {
        res.innerHTML = '<span class="status-error">✗</span> ' + e.message;
      }
    }

    async function getState() {
      const res = document.getElementById('result');
      res.style.display = 'block';
      res.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading state...';
      try {
        const r = await fetch('?action=get_state');
        const d = await r.json();
        if (d.success) {
          res.innerHTML = '<pre style="white-space:pre-wrap;font-size:.75rem">' + JSON.stringify(d.state, null, 2) + '</pre>';
        } else {
          res.innerHTML = '<span class="status-error">✗</span> ' + (d.error || 'Unknown');
        }
      } catch (e) {
        res.innerHTML = '<span class="status-error">✗</span> ' + e.message;
      }
    }
  </script>

</body>

</html>
