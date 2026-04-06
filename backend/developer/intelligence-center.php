<?php

/**
 * Intelligence Center — Developer Neural Cyberpunk Panel
 * Animated grid background, glowing telemetry, real-time event stream,
 * AI decision visualization, matrix-style logs.
 * CSS GPU acceleration only — no runtime blocking.
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
$graph = $data['graph'];
$devices = $data['devices'];
$recentDecisions = $data['recent_decisions'];

$intScore = $lastRun['intelligence_score'] ?? 0;
$devopsScore = $lastRun['devops_score'] ?? 0;
$behaviorScore = $lastRun['behavior_score'] ?? 100;
$predRisk = $lastRun['prediction_risk'] ?? 'low';

$recentLogs = [];
try {
  $recentLogs = ErrorCollector::getRecentLogs(40);
} catch (\Throwable $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Neural Intelligence — <?= htmlspecialchars(APP_NAME ?? 'SAMS') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --bg: #020408;
      --card: rgba(8, 16, 32, .92);
      --border: rgba(0, 229, 255, .1);
      --text: #b0c4de;
      --muted: #3a5070;
      --cyan: #00e5ff;
      --green: #00ff88;
      --red: #ff2d55;
      --yellow: #ffd740;
      --purple: #bb86fc;
      --matrix: #00ff41;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Cascadia Code', 'Fira Code', 'Consolas', monospace;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* Animated grid background — GPU accelerated */
    .grid-bg {
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      background-image:
        linear-gradient(rgba(0, 229, 255, .03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0, 229, 255, .03) 1px, transparent 1px);
      background-size: 40px 40px;
      animation: gridScroll 20s linear infinite;
      will-change: transform;
    }

    @keyframes gridScroll {
      0% {
        transform: translate3d(0, 0, 0)
      }

      100% {
        transform: translate3d(40px, 40px, 0)
      }
    }

    /* Scanline effect */
    .scanline {
      position: fixed;
      inset: 0;
      z-index: 1;
      pointer-events: none;
      background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0, 229, 255, .015) 2px, rgba(0, 229, 255, .015) 4px);
    }

    .content {
      position: relative;
      z-index: 2;
    }

    .topbar {
      background: var(--card);
      border-bottom: 1px solid var(--border);
      padding: 8px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      backdrop-filter: blur(10px);
    }

    .topbar h1 {
      font-size: 14px;
      color: var(--cyan);
      text-transform: uppercase;
      letter-spacing: 3px;
      font-weight: 400;
      text-shadow: 0 0 10px rgba(0, 229, 255, .3);
    }

    .topbar .links a {
      color: var(--muted);
      text-decoration: none;
      font-size: 11px;
      margin-left: 12px;
      transition: color .2s;
    }

    .topbar .links a:hover {
      color: var(--cyan);
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 10px;
      padding: 14px 16px;
      max-width: 1800px;
      margin: 0 auto;
    }

    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 14px;
      backdrop-filter: blur(8px);
      transition: border-color .3s, box-shadow .3s;
    }

    .card:hover {
      border-color: rgba(0, 229, 255, .3);
      box-shadow: 0 0 20px rgba(0, 229, 255, .05);
    }

    .card-t {
      font-size: 9px;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: var(--cyan);
      margin-bottom: 8px;
      font-weight: 400;
      opacity: .8;
    }

    .span2 {
      grid-column: span 2;
    }

    .span3 {
      grid-column: span 3;
    }

    /* Glowing scores */
    .score-hero {
      font-size: 64px;
      font-weight: 100;
      text-align: center;
      line-height: 1;
      font-variant-numeric: tabular-nums;
      text-shadow: 0 0 30px currentColor;
      animation: scorePulse 3s ease-in-out infinite;
    }

    @keyframes scorePulse {

      0%,
      100% {
        opacity: 1;
        filter: brightness(1)
      }

      50% {
        opacity: .8;
        filter: brightness(1.2)
      }
    }

    .score-md {
      font-size: 32px;
      font-weight: 100;
      text-align: center;
      font-variant-numeric: tabular-nums;
      text-shadow: 0 0 15px currentColor;
    }

    .score-lbl {
      font-size: 9px;
      color: var(--muted);
      text-align: center;
      letter-spacing: 2px;
      text-transform: uppercase;
      margin-top: 4px;
    }

    .sr {
      display: flex;
      justify-content: space-between;
      padding: 4px 0;
      border-bottom: 1px solid rgba(0, 229, 255, .04);
      font-size: 11px;
    }

    .sr:last-child {
      border-bottom: none;
    }

    .sk {
      color: var(--muted);
    }

    .sv {
      font-weight: 400;
      color: var(--text);
    }

    .tag {
      display: inline-block;
      padding: 2px 7px;
      border-radius: 3px;
      font-size: 10px;
      font-weight: 400;
    }

    .tag-ok {
      background: rgba(0, 255, 136, .1);
      color: var(--green);
      border: 1px solid rgba(0, 255, 136, .15);
    }

    .tag-warn {
      background: rgba(255, 215, 64, .1);
      color: var(--yellow);
      border: 1px solid rgba(255, 215, 64, .15);
    }

    .tag-crit {
      background: rgba(255, 45, 85, .1);
      color: var(--red);
      border: 1px solid rgba(255, 45, 85, .15);
    }

    .tag-info {
      background: rgba(0, 229, 255, .1);
      color: var(--cyan);
      border: 1px solid rgba(0, 229, 255, .15);
    }

    .tag-purple {
      background: rgba(187, 134, 252, .1);
      color: var(--purple);
      border: 1px solid rgba(187, 134, 252, .15);
    }

    .tbl {
      width: 100%;
      border-collapse: collapse;
      font-size: 10px;
    }

    .tbl th {
      text-align: left;
      padding: 4px 5px;
      color: var(--muted);
      font-weight: 400;
      font-size: 8px;
      text-transform: uppercase;
      letter-spacing: 1px;
      border-bottom: 1px solid rgba(0, 229, 255, .06);
    }

    .tbl td {
      padding: 4px 5px;
      border-bottom: 1px solid rgba(0, 229, 255, .03);
    }

    /* Matrix-style log */
    .log-matrix {
      background: rgba(0, 0, 0, .6);
      border: 1px solid rgba(0, 255, 65, .1);
      border-radius: 4px;
      padding: 10px;
      font-size: 10px;
      max-height: 350px;
      overflow-y: auto;
      line-height: 1.6;
      color: var(--matrix);
      font-family: inherit;
    }

    .log-matrix .l-h {
      color: var(--cyan);
      font-weight: 600;
    }

    .log-matrix .l-e {
      color: var(--red);
    }

    .log-matrix .l-s {
      color: var(--green);
    }

    .log-matrix .l-d {
      color: var(--muted);
      font-size: 9px;
    }

    /* Event stream animation */
    .event-stream {
      max-height: 160px;
      overflow-y: auto;
    }

    .event-item {
      padding: 4px 8px;
      border-left: 2px solid var(--cyan);
      margin-bottom: 4px;
      font-size: 10px;
      animation: eventFade .5s ease-out;
      background: rgba(0, 229, 255, .02);
    }

    @keyframes eventFade {
      from {
        opacity: 0;
        transform: translateX(-10px)
      }

      to {
        opacity: 1;
        transform: translateX(0)
      }
    }

    .btn-neural {
      background: transparent;
      border: 1px solid var(--cyan);
      color: var(--cyan);
      padding: 8px 18px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: 2px;
      font-family: inherit;
      transition: all .3s;
    }

    .btn-neural:hover {
      background: rgba(0, 229, 255, .1);
      box-shadow: 0 0 20px rgba(0, 229, 255, .15);
    }

    .btn-neural:disabled {
      opacity: .3;
      cursor: not-allowed;
    }

    /* Context orb */
    .ctx-orb {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      animation: orbGlow 2s ease-in-out infinite;
      will-change: box-shadow;
    }

    @keyframes orbGlow {

      0%,
      100% {
        box-shadow: 0 0 10px currentColor
      }

      50% {
        box-shadow: 0 0 25px currentColor
      }
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
  <div class="grid-bg"></div>
  <div class="scanline"></div>
  <div class="content">

    <div class="topbar">
      <h1>// neural intelligence</h1>
      <div class="links">
        <a href="../admin/intelligence-center.php"><i class="fas fa-brain"></i> Admin</a>
        <a href="devops-center.php"><i class="fas fa-satellite-dish"></i> DevOps</a>
        <a href="autofix-center.php"><i class="fas fa-wrench"></i> Autofix</a>
      </div>
    </div>

    <div class="grid">
      <!-- Intelligence Score -->
      <div class="card" style="text-align:center;">
        <div class="card-t">intelligence</div>
        <div class="score-hero" style="color:<?= $intScore >= 90 ? 'var(--green)' : ($intScore >= 70 ? 'var(--cyan)' : 'var(--yellow)') ?>"><?= $intScore ?></div>
        <div class="score-lbl">system iq</div>
      </div>

      <!-- DevOps -->
      <div class="card" style="text-align:center;">
        <div class="card-t">devops</div>
        <div class="score-md" style="color:<?= $devopsScore >= 90 ? 'var(--green)' : 'var(--cyan)' ?>"><?= $devopsScore ?></div>
        <div class="score-lbl">infrastructure</div>
      </div>

      <!-- Behavior -->
      <div class="card" style="text-align:center;">
        <div class="card-t">behavior</div>
        <div class="score-md" style="color:<?= $behaviorScore >= 90 ? 'var(--green)' : 'var(--yellow)' ?>"><?= $behaviorScore ?></div>
        <div class="score-lbl">anomaly health</div>
      </div>

      <!-- Context Orb -->
      <div class="card" style="text-align:center;">
        <div class="card-t">context</div>
        <?php
        $ctxLabel = $context['primary'] ?? 'normal';
        $orbColor = $ctxLabel === 'normal' ? 'var(--green)' : ($ctxLabel === 'academic_peak' ? 'var(--purple)' : 'var(--yellow)');
        ?>
        <div class="ctx-orb" style="color:<?= $orbColor ?>;border:1px solid currentColor;">
          <i class="fas fa-<?= $ctxLabel === 'normal' ? 'check' : ($ctxLabel === 'academic_peak' ? 'graduation-cap' : 'bolt') ?>"></i>
        </div>
        <div class="score-lbl" style="margin-top:8px;"><?= htmlspecialchars(str_replace('_', ' ', $ctxLabel)) ?></div>
      </div>

      <!-- Risk Forecast -->
      <div class="card" style="text-align:center;">
        <div class="card-t">risk forecast</div>
        <?php
        $riskColors = ['low' => 'var(--green)', 'moderate' => 'var(--cyan)', 'elevated' => 'var(--yellow)', 'critical' => 'var(--red)'];
        $rColor = $riskColors[$predRisk] ?? 'var(--muted)';
        ?>
        <div class="score-md" style="color:<?= $rColor ?>;text-transform:uppercase;"><?= htmlspecialchars($predRisk) ?></div>
        <div class="score-lbl"><?= $predictions['prediction_count'] ?? 0 ?> predictions</div>
      </div>

      <!-- Controls -->
      <div class="card">
        <div class="card-t">// control</div>
        <button class="btn-neural" id="btnRun" onclick="runCycle()"><i class="fas fa-bolt"></i>&nbsp; execute cycle</button>
        <div id="runSt" style="margin-top:8px;font-size:10px;color:var(--muted);"></div>
        <?php if ($lastRun): ?>
          <div style="margin-top:8px;">
            <div class="sr"><span class="sk">timestamp</span><span class="sv"><?= htmlspecialchars($lastRun['timestamp'] ?? '') ?></span></div>
            <div class="sr"><span class="sk">elapsed</span><span class="sv"><?= $lastRun['elapsed_ms'] ?? 0 ?>ms</span></div>
            <div class="sr"><span class="sk">decisions</span><span class="sv"><?= $lastRun['decisions_made'] ?? 0 ?></span></div>
            <div class="sr"><span class="sk">anomalies</span><span class="sv"><?= $lastRun['anomalies'] ?? 0 ?></span></div>
            <div class="sr"><span class="sk">graph</span><span class="sv"><?= ($lastRun['graph_nodes'] ?? 0) ?>n / <?= ($lastRun['graph_edges'] ?? 0) ?>e</span></div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Knowledge Graph Stats -->
      <div class="card">
        <div class="card-t">// knowledge graph</div>
        <div class="sr"><span class="sk">nodes</span><span class="sv" style="color:var(--cyan)"><?= number_format($graph['total_nodes'] ?? 0) ?></span></div>
        <div class="sr"><span class="sk">edges</span><span class="sv" style="color:var(--cyan)"><?= number_format($graph['total_edges'] ?? 0) ?></span></div>
        <?php foreach (array_slice($graph['relationships'] ?? [], 0, 5) as $r): ?>
          <div class="sr"><span class="sk"><?= htmlspecialchars($r['relationship']) ?></span><span class="sv"><?= $r['cnt'] ?></span></div>
        <?php endforeach; ?>
      </div>

      <!-- AI Decision Stream -->
      <div class="card span2">
        <div class="card-t"><i class="fas fa-gavel"></i>&nbsp; // decision stream</div>
        <div class="event-stream">
          <?php if (!empty($recentDecisions)): ?>
            <?php foreach (array_slice($recentDecisions, 0, 10) as $d): ?>
              <div class="event-item">
                <span class="tag tag-info"><?= htmlspecialchars($d['signal_type'] ?? '') ?></span>
                <span style="margin-left:6px;"><?= htmlspecialchars($d['action_taken'] ?? '') ?></span>
                <span style="float:right;" class="<?= ($d['outcome'] ?? '') === 'executed' ? 'tag tag-ok' : 'tag tag-warn' ?>"><?= htmlspecialchars($d['outcome'] ?? '') ?></span>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="color:var(--muted);font-size:10px;">no decisions recorded yet</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Predictions -->
      <div class="card span2">
        <div class="card-t"><i class="fas fa-chart-line"></i>&nbsp; // predictions</div>
        <?php if (!empty($predictions['top_predictions'])): ?>
          <table class="tbl">
            <thead>
              <tr>
                <th>type</th>
                <th>detail</th>
                <th>sev</th>
                <th>conf</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($predictions['top_predictions'] as $p): ?>
                <tr>
                  <td><span class="tag tag-info"><?= htmlspecialchars($p['type'] ?? '') ?></span></td>
                  <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($p['detail'] ?? '') ?></td>
                  <td><?= ($p['severity'] ?? '') === 'high' ? '<span class="tag tag-crit">H</span>' : '<span class="tag tag-warn">M</span>' ?></td>
                  <td><?= round(($p['confidence'] ?? 0) * 100) ?>%</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div style="color:var(--muted);font-size:10px;">all clear — no predictions</div>
        <?php endif; ?>
      </div>

      <!-- Behavior Patterns -->
      <div class="card span2">
        <div class="card-t"><i class="fas fa-wave-square"></i>&nbsp; // behavior patterns</div>
        <?php if (!empty($behavior['patterns'])): ?>
          <?php foreach ($behavior['patterns'] as $p): ?>
            <div class="sr">
              <span class="sk"><?= htmlspecialchars($p['type'] ?? '') ?></span>
              <span class="sv"><?= htmlspecialchars($p['detail'] ?? '') ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($behavior['top_anomalies'])): ?>
          <div style="margin-top:6px;border-top:1px solid rgba(0,229,255,.06);padding-top:6px;">
            <?php foreach (array_slice($behavior['top_anomalies'], 0, 4) as $a): ?>
              <div class="sr">
                <span class="tag tag-purple"><?= htmlspecialchars($a['type'] ?? '') ?></span>
                <span class="sv" style="font-size:10px;"><?= htmlspecialchars($a['detail'] ?? '') ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if (empty($behavior['patterns']) && empty($behavior['top_anomalies'])): ?>
          <div style="color:var(--muted);font-size:10px;">baseline stable — no patterns</div>
        <?php endif; ?>
      </div>

      <!-- Matrix Log -->
      <div class="card span3">
        <div class="card-t"><i class="fas fa-terminal"></i>&nbsp; // neural log <span style="float:right;font-weight:400;color:var(--muted);">logs/autofix.log</span></div>
        <div class="log-matrix" id="logBox">
          <?php foreach ($recentLogs as $line):
            $cls = 'l-d';
            if (str_contains($line, '═══') || str_contains($line, 'Intelligence') || str_contains($line, 'DevOps')) $cls = 'l-h';
            elseif (str_contains($line, 'ERROR') || str_contains($line, 'CRITICAL') || str_contains($line, 'FATAL')) $cls = 'l-e';
            elseif (str_contains($line, 'complete') || str_contains($line, 'Created') || str_contains($line, 'built') || str_contains($line, 'SAFE')) $cls = 'l-s';
          ?>
            <div class="<?= $cls ?>"><?= htmlspecialchars($line) ?></div>
          <?php endforeach; ?>
          <?php if (empty($recentLogs)): ?>
            <div class="l-d">awaiting neural data stream...</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <script>
    function runCycle() {
      const btn = document.getElementById('btnRun'),
        st = document.getElementById('runSt');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>&nbsp; processing...';
      st.textContent = 'neural cycle initiated...';

      fetch('../cron/intelligence.php?key=dashboard', {
          credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(d => {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-bolt"></i>&nbsp; execute cycle';
          if (d.status === 'completed') {
            st.innerHTML = '<span style="color:var(--green)">complete — iq:' + d.intelligence_score + ' devops:' + d.devops_score + ' ' + d.elapsed_ms + 'ms</span>';
            setTimeout(() => location.reload(), 2000);
          } else {
            st.innerHTML = '<span style="color:var(--yellow)">' + (d.message || 'unexpected') + '</span>';
          }
        })
        .catch(e => {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-bolt"></i>&nbsp; execute cycle';
          st.innerHTML = '<span style="color:var(--red)">err: ' + e.message + '</span>';
        });
    }
    const lb = document.getElementById('logBox');
    if (lb) lb.scrollTop = lb.scrollHeight;
  </script>
</body>

</html>
