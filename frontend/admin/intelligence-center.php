<?php

/**
 * Intelligence Center — Admin Panel
 * Displays operational insights, risk forecasts, recommendations,
 * automation activity, system reasoning logs.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once __DIR__ . '/../app/bootstrap.php';

require_admin('../login.php');

$data = IntelligenceKernel::getDashboardData();
$lastRun = $data['last_run'];
$context = $data['context'];
$behavior = $data['behavior'];
$predictions = $data['predictions'];
$decisions = $data['decisions'];
$devices = $data['devices'];
$graph = $data['graph'];
$devops = $data['devops'];
$workflows = $data['workflows'];
$recentDecisions = $data['recent_decisions'];

$intScore = $lastRun['intelligence_score'] ?? 0;
$devopsScore = $lastRun['devops_score'] ?? 0;
$behaviorScore = $lastRun['behavior_score'] ?? 100;
$predRisk = $lastRun['prediction_risk'] ?? 'low';
$ctxPrimary = $lastRun['context'] ?? 'normal';

function intScoreColor(int $s): string
{
  if ($s >= 95) return '#00ff88';
  if ($s >= 80) return '#00d4ff';
  if ($s >= 60) return '#ffaa00';
  return '#ff4444';
}
$iColor = intScoreColor($intScore);
$dColor = intScoreColor($devopsScore);
$bColor = intScoreColor($behaviorScore);

$riskColors = ['low' => '#00ff88', 'moderate' => '#00d4ff', 'elevated' => '#ffaa00', 'critical' => '#ff4444'];
$riskColor = $riskColors[$predRisk] ?? '#aaa';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Intelligence Center — <?= htmlspecialchars(APP_NAME ?? 'SAMS') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --bg: #060a12;
      --card: #0c1220;
      --card2: #101828;
      --border: #182040;
      --text: #d0d8e8;
      --muted: #5a6a84;
      --green: #00ff88;
      --blue: #00d4ff;
      --red: #ff3b3b;
      --yellow: #ffc107;
      --purple: #b057ff;
      --cyan: #00e5ff;
    }

    * {
      box-sizing: border-box;
    }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Segoe UI', system-ui, sans-serif;
      margin: 0;
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
      font-size: 16px;
      margin: 0;
      color: var(--cyan);
      text-transform: uppercase;
      letter-spacing: 2px;
      font-weight: 700;
    }

    .topbar .links a {
      color: var(--muted);
      text-decoration: none;
      font-size: 12px;
      margin-left: 14px;
    }

    .topbar .links a:hover {
      color: var(--blue);
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 12px;
      padding: 16px 20px;
      max-width: 1700px;
      margin: 0 auto;
    }

    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 16px;
      transition: border-color .2s;
    }

    .card:hover {
      border-color: #2a3a5e;
    }

    .card-t {
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: var(--muted);
      margin-bottom: 8px;
      font-weight: 700;
    }

    .span2 {
      grid-column: span 2;
    }

    .span3 {
      grid-column: span 3;
    }

    .span4 {
      grid-column: span 4;
    }

    .score-big {
      font-size: 52px;
      font-weight: 800;
      line-height: 1;
      text-align: center;
      font-variant-numeric: tabular-nums;
    }

    .score-sm {
      font-size: 26px;
      font-weight: 700;
      text-align: center;
      font-variant-numeric: tabular-nums;
    }

    .score-lbl {
      font-size: 11px;
      color: var(--muted);
      text-align: center;
      margin-top: 2px;
    }

    .sr {
      display: flex;
      justify-content: space-between;
      padding: 5px 0;
      border-bottom: 1px solid #121828;
      font-size: 12px;
    }

    .sr:last-child {
      border-bottom: none;
    }

    .sk {
      color: var(--muted);
    }

    .sv {
      font-weight: 600;
      font-variant-numeric: tabular-nums;
    }

    .badge-ok {
      background: rgba(0, 255, 136, .12);
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

    .badge-purple {
      background: rgba(176, 87, 255, .12);
      color: var(--purple);
      padding: 2px 8px;
      border-radius: 3px;
      font-size: 11px;
      font-weight: 600;
    }

    .tbl {
      width: 100%;
      border-collapse: collapse;
      font-size: 11px;
    }

    .tbl th {
      text-align: left;
      padding: 5px 6px;
      color: var(--muted);
      border-bottom: 1px solid var(--border);
      font-weight: 700;
      font-size: 9px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .tbl td {
      padding: 5px 6px;
      border-bottom: 1px solid #101828;
    }

    .tbl tr:hover td {
      background: var(--card2);
    }

    .btn-run {
      background: linear-gradient(135deg, var(--cyan), #00b8d4);
      color: #000;
      border: none;
      padding: 8px 20px;
      border-radius: 6px;
      font-weight: 700;
      cursor: pointer;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .btn-run:hover {
      box-shadow: 0 0 20px rgba(0, 229, 255, .2);
    }

    .btn-run:disabled {
      opacity: .5;
      cursor: not-allowed;
    }

    .ctx-pill {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 3px 10px;
      border-radius: 16px;
      font-size: 11px;
      font-weight: 600;
      background: rgba(0, 229, 255, .08);
      color: var(--cyan);
      border: 1px solid rgba(0, 229, 255, .2);
    }

    @media(max-width:900px) {
      .grid {
        grid-template-columns: 1fr;
      }

      .span2,
      .span3,
      .span4 {
        grid-column: span 1;
      }
    }
  </style>
</head>

<body>
  <div class="topbar">
    <h1><i class="fas fa-brain"></i>&nbsp; Intelligence Center</h1>
    <div class="links">
      <a href="../developer/devops-center.php"><i class="fas fa-satellite-dish"></i> DevOps</a>
      <a href="../developer/autofix-center.php"><i class="fas fa-wrench"></i> Autofix</a>
      <a href="dashboard.php"><i class="fas fa-home"></i> Admin</a>
    </div>
  </div>

  <div class="grid">
    <!-- Intelligence Score -->
    <div class="card" style="text-align:center;">
      <div class="card-t">Intelligence Score</div>
      <div class="score-big" style="color:<?= $iColor ?>"><?= $intScore ?></div>
      <div class="score-lbl">/ 100</div>
    </div>
    <!-- DevOps Score -->
    <div class="card" style="text-align:center;">
      <div class="card-t">DevOps</div>
      <div class="score-sm" style="color:<?= $dColor ?>"><?= $devopsScore ?></div>
      <div class="score-lbl">Foundation</div>
    </div>
    <!-- Behavior Score -->
    <div class="card" style="text-align:center;">
      <div class="card-t">Behavior</div>
      <div class="score-sm" style="color:<?= $bColor ?>"><?= $behaviorScore ?></div>
      <div class="score-lbl">Anomaly Health</div>
    </div>
    <!-- Risk Level -->
    <div class="card" style="text-align:center;">
      <div class="card-t">Prediction Risk</div>
      <div class="score-sm" style="color:<?= $riskColor ?>;text-transform:uppercase;"><?= htmlspecialchars($predRisk) ?></div>
      <div class="score-lbl">Forecast Level</div>
    </div>

    <!-- Controls -->
    <div class="card">
      <div class="card-t">Controls</div>
      <button class="btn-run" id="btnRun" onclick="runIntel()"><i class="fas fa-bolt"></i>&nbsp; Run Intelligence Cycle</button>
      <div id="runStatus" style="margin-top:8px;font-size:11px;color:var(--muted);"></div>
      <?php if ($lastRun): ?>
        <div style="margin-top:10px;">
          <div class="sr"><span class="sk">Last Run</span><span class="sv"><?= htmlspecialchars($lastRun['timestamp'] ?? '') ?></span></div>
          <div class="sr"><span class="sk">Elapsed</span><span class="sv"><?= $lastRun['elapsed_ms'] ?? 0 ?>ms</span></div>
          <div class="sr"><span class="sk">Decisions</span><span class="sv"><?= $lastRun['decisions_made'] ?? 0 ?></span></div>
          <div class="sr"><span class="sk">Actions</span><span class="sv"><?= $lastRun['actions_executed'] ?? 0 ?></span></div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Context -->
    <div class="card">
      <div class="card-t"><i class="fas fa-crosshairs"></i>&nbsp; Operational Context</div>
      <?php if (!empty($context['contexts'])): ?>
        <?php foreach ($context['contexts'] as $c): ?>
          <div style="margin-bottom:8px;">
            <span class="ctx-pill"><?= htmlspecialchars($c['label']) ?></span>
            <div style="font-size:11px;color:var(--muted);margin-top:3px;"><?= htmlspecialchars($c['detail']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="ctx-pill" style="background:rgba(0,255,136,.08);color:var(--green);border-color:rgba(0,255,136,.2);">Normal Operations</div>
      <?php endif; ?>
    </div>

    <!-- Knowledge Graph -->
    <div class="card">
      <div class="card-t"><i class="fas fa-project-diagram"></i>&nbsp; Knowledge Graph</div>
      <div class="sr"><span class="sk">Nodes</span><span class="sv"><?= number_format($graph['total_nodes'] ?? 0) ?></span></div>
      <div class="sr"><span class="sk">Edges</span><span class="sv"><?= number_format($graph['total_edges'] ?? 0) ?></span></div>
      <?php foreach (array_slice($graph['node_types'] ?? [], 0, 4) as $nt): ?>
        <div class="sr"><span class="sk"><?= htmlspecialchars($nt['node_type']) ?></span><span class="sv"><?= $nt['cnt'] ?></span></div>
      <?php endforeach; ?>
    </div>

    <!-- Devices -->
    <div class="card">
      <div class="card-t"><i class="fas fa-microchip"></i>&nbsp; Device Bridge</div>
      <div class="sr"><span class="sk">Registered</span><span class="sv"><?= $devices['total_devices'] ?? 0 ?></span></div>
      <div class="sr"><span class="sk">Online</span><span class="sv" style="color:var(--green)"><?= $devices['online'] ?? 0 ?></span></div>
      <div class="sr"><span class="sk">Pending Events</span><span class="sv"><?= $devices['pending_events'] ?? 0 ?></span></div>
    </div>

    <!-- Predictions -->
    <div class="card span2">
      <div class="card-t"><i class="fas fa-chart-line"></i>&nbsp; Predictions (<?= $predictions['prediction_count'] ?? 0 ?>)</div>
      <?php if (!empty($predictions['top_predictions'])): ?>
        <div style="max-height:220px;overflow-y:auto;">
          <table class="tbl">
            <thead>
              <tr>
                <th>Type</th>
                <th>Detail</th>
                <th>Severity</th>
                <th>Confidence</th>
                <th>Timeframe</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($predictions['top_predictions'] as $p): ?>
                <tr>
                  <td><span class="badge-info"><?= htmlspecialchars($p['type'] ?? '') ?></span></td>
                  <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($p['detail'] ?? '') ?>"><?= htmlspecialchars($p['detail'] ?? '') ?></td>
                  <td><?php
                      $sev = $p['severity'] ?? 'medium';
                      echo $sev === 'high' ? '<span class="badge-crit">HIGH</span>' : ($sev === 'medium' ? '<span class="badge-warn">MED</span>' : '<span class="badge-ok">LOW</span>');
                      ?></td>
                  <td class="sv"><?= round(($p['confidence'] ?? 0) * 100) ?>%</td>
                  <td><?= htmlspecialchars($p['timeframe'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="color:var(--muted);font-size:11px;">No predictions — system is operating within normal parameters.</p>
      <?php endif; ?>
    </div>

    <!-- Behavioral Anomalies -->
    <div class="card span2">
      <div class="card-t"><i class="fas fa-exclamation-triangle"></i>&nbsp; Behavioral Anomalies (<?= $behavior['anomaly_count'] ?? 0 ?>)</div>
      <?php if (!empty($behavior['top_anomalies'])): ?>
        <div style="max-height:220px;overflow-y:auto;">
          <table class="tbl">
            <thead>
              <tr>
                <th>Type</th>
                <th>Detail</th>
                <th>Severity</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($behavior['top_anomalies'] as $a): ?>
                <tr>
                  <td><span class="badge-purple"><?= htmlspecialchars($a['type'] ?? '') ?></span></td>
                  <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($a['detail'] ?? '') ?></td>
                  <td><?php
                      $sev = $a['severity'] ?? 'medium';
                      echo $sev === 'high' ? '<span class="badge-crit">HIGH</span>' : '<span class="badge-warn">MED</span>';
                      ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="color:var(--muted);font-size:11px;">No anomalies detected — behavioral baseline stable.</p>
      <?php endif; ?>
    </div>

    <!-- Decision Log -->
    <div class="card span2">
      <div class="card-t"><i class="fas fa-gavel"></i>&nbsp; Recent Decisions</div>
      <div class="sr"><span class="sk">Total Decisions</span><span class="sv"><?= $decisions['total_decisions'] ?? 0 ?></span></div>
      <div class="sr"><span class="sk">Auto-Executed</span><span class="sv"><?= $decisions['executed'] ?? 0 ?></span></div>
      <div class="sr"><span class="sk">Last 24h</span><span class="sv"><?= $decisions['decisions_24h'] ?? 0 ?></span></div>
      <?php if (!empty($recentDecisions)): ?>
        <div style="max-height:180px;overflow-y:auto;margin-top:8px;">
          <table class="tbl">
            <thead>
              <tr>
                <th>Signal</th>
                <th>Action</th>
                <th>Risk</th>
                <th>Outcome</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($recentDecisions, 0, 8) as $d): ?>
                <tr>
                  <td><span class="badge-info"><?= htmlspecialchars($d['signal_type'] ?? '') ?></span></td>
                  <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($d['action_taken'] ?? '') ?>"><?= htmlspecialchars($d['action_taken'] ?? '') ?></td>
                  <td class="sv"><?= $d['risk_score'] ?? 0 ?></td>
                  <td><?php
                      $o = $d['outcome'] ?? '';
                      if ($o === 'executed') echo '<span class="badge-ok">EXEC</span>';
                      elseif ($o === 'vetoed') echo '<span class="badge-crit">VETO</span>';
                      else echo '<span class="badge-warn">' . htmlspecialchars($o) . '</span>';
                      ?></td>
                  <td style="white-space:nowrap;font-size:10px;"><?= htmlspecialchars($d['created_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Patterns -->
    <div class="card span2">
      <div class="card-t"><i class="fas fa-wave-square"></i>&nbsp; Detected Patterns (<?= $behavior['pattern_count'] ?? 0 ?>)</div>
      <?php if (!empty($behavior['patterns'])): ?>
        <?php foreach ($behavior['patterns'] as $p): ?>
          <div class="sr">
            <span class="sk"><?= htmlspecialchars($p['type'] ?? '') ?></span>
            <span class="sv"><?= htmlspecialchars($p['detail'] ?? '') ?></span>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="color:var(--muted);font-size:11px;">No patterns detected yet.</p>
      <?php endif; ?>
    </div>

    <!-- Workflows -->
    <div class="card span2">
      <div class="card-t"><i class="fas fa-sitemap"></i>&nbsp; Workflow History</div>
      <?php if (!empty($workflows)): ?>
        <div style="max-height:180px;overflow-y:auto;">
          <table class="tbl">
            <thead>
              <tr>
                <th>Workflow</th>
                <th>Steps</th>
                <th>Outcome</th>
                <th>Time</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($workflows, 0, 8) as $w): ?>
                <tr>
                  <td><span class="badge-info"><?= htmlspecialchars($w['signal_type'] ?? '') ?></span></td>
                  <td><?= htmlspecialchars($w['action_taken'] ?? '') ?></td>
                  <td><?php
                      $o = $w['outcome'] ?? '';
                      echo $o === 'completed' ? '<span class="badge-ok">DONE</span>' : '<span class="badge-warn">' . htmlspecialchars($o) . '</span>';
                      ?></td>
                  <td style="white-space:nowrap;font-size:10px;"><?= htmlspecialchars($w['created_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p style="color:var(--muted);font-size:11px;">No workflows executed yet.</p>
      <?php endif; ?>
    </div>
  </div>

  <script>
    function runIntel() {
      const btn = document.getElementById('btnRun');
      const st = document.getElementById('runStatus');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>&nbsp; Running...';
      st.textContent = 'Intelligence cycle running...';

      fetch('../cron/intelligence.php?key=dashboard', {
          credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(d => {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-bolt"></i>&nbsp; Run Intelligence Cycle';
          if (d.status === 'completed') {
            st.innerHTML = '<span style="color:var(--green)">Done — Score: ' + d.intelligence_score + ' | DevOps: ' + d.devops_score + ' | ' + d.elapsed_ms + 'ms</span>';
            setTimeout(() => location.reload(), 2000);
          } else {
            st.innerHTML = '<span style="color:var(--yellow)">' + (d.message || 'Unexpected response') + '</span>';
          }
        })
        .catch(e => {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-bolt"></i>&nbsp; Run Intelligence Cycle';
          st.innerHTML = '<span style="color:var(--red)">Error: ' + e.message + '</span>';
        });
    }
  </script>
</body>

</html>
