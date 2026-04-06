<?php

/**
 * Developer — Ecosystem Center Dashboard
 * Distributed institutional intelligence visualization.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

require_admin('../login.php');

// Handle run action
if (isset($_GET['run']) && $_GET['run'] === '1') {
  $result = EcosystemKernel::run();
  header('Content-Type: application/json');
  echo json_encode($result);
  exit;
}

$data = EcosystemKernel::getDashboardData();
$lastRun = $data['last_run'];
$tenants = $data['tenants'];
$federation = $data['federation'];
$exchange = $data['exchange'];
$trust = $data['trust'];
$consensus = $data['consensus'];
$deployment = $data['deployment'];
$analytics = $data['analytics'];

$ecosystemScore = $lastRun['ecosystem_score'] ?? 0;
$cognitiveScore = $lastRun['cognitive_score'] ?? 0;
$infraScore = $lastRun['infrastructure_score'] ?? 0;
$trustStatus = $lastRun['trust_status'] ?? 'active';

function ecoColor(int $s): string
{
  if ($s >= 90) return '#00ff41';
  if ($s >= 70) return '#00e5ff';
  if ($s >= 50) return '#ffaa00';
  return '#ff4444';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Ecosystem Center — <?= htmlspecialchars(APP_NAME ?? 'SAMS') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --bg: #030810;
      --card: rgba(8, 20, 40, .92);
      --border: rgba(0, 229, 255, .1);
      --text: #b0c4de;
      --muted: #3a5070;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box
    }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Inter', system-ui, sans-serif;
      min-height: 100vh;
      overflow-x: hidden
    }

    .eco-header {
      padding: 1.5rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid var(--border)
    }

    .eco-header h1 {
      font-size: 1.4rem;
      font-weight: 700;
      background: linear-gradient(135deg, #00e5ff, #00ff41);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent
    }

    .eco-header .back {
      color: var(--text);
      text-decoration: none;
      opacity: .6;
      font-size: .85rem
    }

    .eco-header .back:hover {
      opacity: 1
    }

    .eco-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 1rem;
      padding: 1.5rem 2rem
    }

    .eco-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 1.2rem 1.5rem;
      transition: border-color .2s
    }

    .eco-card:hover {
      border-color: rgba(0, 229, 255, .25)
    }

    .eco-card h3 {
      font-size: .85rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      opacity: .6;
      margin-bottom: .8rem
    }

    .eco-score-hero {
      grid-column: 1/-1;
      text-align: center;
      padding: 2rem;
      background: linear-gradient(135deg, rgba(0, 229, 255, .08), rgba(0, 255, 65, .05));
      border-radius: 16px;
      border: 1px solid rgba(0, 229, 255, .12)
    }

    .eco-score-hero .score {
      font-size: 5rem;
      font-weight: 800;
      color: <?= ecoColor($ecosystemScore) ?>
    }

    .eco-score-hero .sub {
      font-size: .9rem;
      opacity: .6;
      margin-top: .3rem
    }

    .score-row {
      display: flex;
      justify-content: center;
      gap: 3rem;
      margin-top: 1rem
    }

    .score-row .item {
      text-align: center
    }

    .score-row .val {
      font-size: 1.6rem;
      font-weight: 700
    }

    .score-row .lbl {
      font-size: .7rem;
      opacity: .5
    }

    .metric {
      display: flex;
      justify-content: space-between;
      padding: .5rem 0;
      border-bottom: 1px solid rgba(255, 255, 255, .04);
      font-size: .85rem
    }

    .metric:last-child {
      border: none
    }

    .metric .mv {
      font-weight: 600
    }

    .btn-run {
      padding: .5rem 1.2rem;
      background: linear-gradient(135deg, #00e5ff, #00ff41);
      color: #000;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      font-size: .85rem;
      text-decoration: none
    }

    .btn-run:hover {
      opacity: .85
    }

    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: .7rem;
      font-weight: 600
    }

    .badge-ok {
      background: rgba(0, 255, 65, .12);
      color: #00ff41
    }

    .badge-warn {
      background: rgba(255, 170, 0, .12);
      color: #ffaa00
    }

    .trust-bar {
      height: 6px;
      border-radius: 3px;
      background: rgba(255, 255, 255, .06);
      margin-top: .5rem;
      overflow: hidden
    }

    .trust-fill {
      height: 100%;
      border-radius: 3px;
      background: linear-gradient(90deg, #00e5ff, #00ff41)
    }
  </style>
</head>

<body>

  <div class="eco-header">
    <div>
      <a href="<?= htmlspecialchars(route('developer/index.php')) ?>" class="back"><i class="fas fa-arrow-left"></i> Developer Portal</a>
      <h1><i class="fas fa-globe"></i> Ecosystem Intelligence Center</h1>
    </div>
    <a href="?run=1" class="btn-run" onclick="this.textContent='Running...';"><i class="fas fa-play"></i> Run Cycle</a>
  </div>

  <div class="eco-grid">
    <!-- Hero Score -->
    <div class="eco-score-hero eco-card">
      <div class="score"><?= $ecosystemScore ?>/100</div>
      <div class="sub">Autonomous Educational Ecosystem Score</div>
      <div class="score-row">
        <div class="item">
          <div class="val" style="color:#e040fb"><?= $cognitiveScore ?></div>
          <div class="lbl">Cognitive</div>
        </div>
        <div class="item">
          <div class="val" style="color:#00e5ff"><?= $infraScore ?></div>
          <div class="lbl">Infrastructure</div>
        </div>
        <div class="item">
          <div class="val" style="color:<?= $trustStatus === 'active' ? '#00ff41' : '#ffaa00' ?>"><?= strtoupper($trustStatus) ?></div>
          <div class="lbl">Trust</div>
        </div>
      </div>
    </div>

    <!-- Tenants -->
    <div class="eco-card">
      <h3><i class="fas fa-building"></i> Multi-Tenant</h3>
      <div class="metric"><span>Total Institutions</span><span class="mv"><?= $tenants['total'] ?? 0 ?></span></div>
      <div class="metric"><span>Active</span><span class="mv" style="color:#00ff41"><?= $tenants['active'] ?? 0 ?></span></div>
      <div class="metric"><span>Pending</span><span class="mv" style="color:#ffaa00"><?= $tenants['pending'] ?? 0 ?></span></div>
      <div class="metric"><span>Isolation</span><span class="badge badge-ok"><?= $tenants['isolation'] ?? 'strict' ?></span></div>
    </div>

    <!-- Federation -->
    <div class="eco-card">
      <h3><i class="fas fa-project-diagram"></i> Federation Engine</h3>
      <div class="metric"><span>Total Patterns</span><span class="mv"><?= $federation['total'] ?? 0 ?></span></div>
      <div class="metric"><span>Approved</span><span class="mv" style="color:#00ff41"><?= $federation['approved'] ?? 0 ?></span></div>
      <div class="metric"><span>Distributed</span><span class="mv" style="color:#00e5ff"><?= $federation['distributed'] ?? 0 ?></span></div>
      <div class="metric"><span>Pending</span><span class="mv"><?= $federation['pending'] ?? 0 ?></span></div>
      <div class="metric"><span>Model</span><span class="badge badge-ok"><?= htmlspecialchars($federation['model'] ?? 'Abstract') ?></span></div>
    </div>

    <!-- Knowledge Exchange -->
    <div class="eco-card">
      <h3><i class="fas fa-exchange-alt"></i> Knowledge Exchange</h3>
      <div class="metric"><span>Total Patterns</span><span class="mv"><?= $exchange['total_patterns'] ?? 0 ?></span></div>
      <div class="metric"><span>Effective (≥70%)</span><span class="mv" style="color:#00ff41"><?= $exchange['effective'] ?? 0 ?></span></div>
      <div class="metric"><span>Total Adoptions</span><span class="mv"><?= $exchange['total_adoptions'] ?? 0 ?></span></div>
      <div class="metric"><span>Sync Model</span><span class="badge badge-ok"><?= htmlspecialchars($exchange['sync_model'] ?? '') ?></span></div>
    </div>

    <!-- Trust Boundary -->
    <div class="eco-card">
      <h3><i class="fas fa-shield-alt"></i> Trust Boundary</h3>
      <div class="metric"><span>Security Model</span><span class="mv" style="font-size:.75rem"><?= htmlspecialchars($trust['model'] ?? '') ?></span></div>
      <div class="metric"><span>Encryption</span><span class="badge badge-ok"><?= $trust['encryption'] ?? 'AES-256' ?></span></div>
      <div class="metric"><span>Signing</span><span class="badge badge-ok"><?= $trust['signing'] ?? 'HMAC-SHA256' ?></span></div>
      <div class="metric"><span>Blocked Fields</span><span class="mv"><?= $trust['blocked_fields'] ?? 0 ?></span></div>
      <div class="trust-bar">
        <div class="trust-fill" style="width:100%"></div>
      </div>
    </div>

    <!-- Consensus Guard -->
    <div class="eco-card">
      <h3><i class="fas fa-gavel"></i> Consensus Guard</h3>
      <div class="metric"><span>Min Consensus</span><span class="mv"><?= $consensus['minimum_consensus'] ?? 3 ?> institutions</span></div>
      <div class="metric"><span>Active Checks</span><span class="mv"><?= count($consensus['checks'] ?? []) ?></span></div>
      <div class="metric"><span>Blocked Categories</span><span class="mv"><?= $consensus['blocked_categories'] ?? 0 ?></span></div>
      <div class="metric"><span>Status</span><span class="badge badge-ok"><?= $consensus['status'] ?? 'active' ?></span></div>
    </div>

    <!-- Deployment -->
    <div class="eco-card">
      <h3><i class="fas fa-rocket"></i> Deployment Manager</h3>
      <div class="metric"><span>Auto-Provision</span><span class="badge badge-ok"><?= ($deployment['auto_provision'] ?? false) ? 'YES' : 'NO' ?></span></div>
      <div class="metric"><span>Provisioning Steps</span><span class="mv"><?= count($deployment['steps'] ?? []) ?></span></div>
      <div class="metric"><span>Status</span><span class="badge badge-ok"><?= $deployment['status'] ?? 'ready' ?></span></div>
    </div>

    <!-- Last Run -->
    <div class="eco-card">
      <h3><i class="fas fa-clock"></i> Last Cycle</h3>
      <div class="metric"><span>Timestamp</span><span class="mv" style="font-size:.8rem"><?= htmlspecialchars($lastRun['timestamp'] ?? 'Never') ?></span></div>
      <div class="metric"><span>Elapsed</span><span class="mv"><?= $lastRun['elapsed_ms'] ?? '—' ?>ms</span></div>
      <div class="metric"><span>Patterns Synced</span><span class="mv"><?= $lastRun['patterns_synced'] ?? 0 ?></span></div>
      <div class="metric"><span>Federation Processed</span><span class="mv"><?= $lastRun['federation_processed'] ?? 0 ?></span></div>
      <div class="metric"><span>Deployment Ready</span><span class="badge <?= ($lastRun['deployment_ready'] ?? false) ? 'badge-ok' : 'badge-warn' ?>"><?= ($lastRun['deployment_ready'] ?? false) ? 'YES' : 'NO' ?></span></div>
    </div>
  </div>

</body>

</html>
