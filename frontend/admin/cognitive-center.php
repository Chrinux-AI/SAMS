<?php

/**
 * Cognitive Center — Admin Dashboard
 *
 * Displays: institutional health score, predictive insights,
 * policy recommendations, reasoning logs, improvement trends
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once __DIR__ . '/../app/bootstrap.php';

require_admin('../login.php');

$data = CognitiveKernel::getDashboardData();
$lastRun = $data['last_run'];
$model = $data['model'];
$academic = $data['academic'];
$adaptive = $data['adaptive'];
$interaction = $data['interaction'];
$policy = $data['policy'];
$insights = $data['insights'];
$ethics = $data['ethics'];
$memory = $data['memory'];

$cogScore = $lastRun['cognitive_score'] ?? 0;
$intScore = $lastRun['intelligence_score'] ?? 0;
$acadScore = $lastRun['academic_score'] ?? 0;
$adaptScore = $lastRun['adaptive_score'] ?? 0;
$interScore = $lastRun['interaction_score'] ?? 0;
$ethicsSafe = $lastRun['ethics_safe'] ?? true;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Cognitive Center — <?= htmlspecialchars(APP_NAME ?? 'SAMS') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --bg: #0a0e1a;
      --card: rgba(14, 20, 36, .95);
      --border: rgba(100, 180, 255, .08);
      --cyan: #00e5ff;
      --green: #00e88f;
      --red: #ff3366;
      --yellow: #ffcc02;
      --purple: #b388ff;
      --text: #c0d0e0;
      --muted: #506080;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      min-height: 100vh;
    }

    .top {
      background: var(--card);
      border-bottom: 1px solid var(--border);
      padding: 12px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .top h1 {
      font-size: 16px;
      font-weight: 600;
      color: var(--cyan);
    }

    .top .links a {
      color: var(--muted);
      text-decoration: none;
      font-size: 12px;
      margin-left: 14px;
      transition: color .2s;
    }

    .top .links a:hover {
      color: var(--cyan);
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 14px;
      padding: 18px 20px;
      max-width: 1600px;
      margin: 0 auto;
    }

    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 16px;
      transition: border-color .3s;
    }

    .card:hover {
      border-color: rgba(100, 180, 255, .2);
    }

    .card-t {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: var(--cyan);
      margin-bottom: 10px;
      font-weight: 600;
    }

    .span2 {
      grid-column: span 2;
    }

    .span3 {
      grid-column: span 3;
    }

    .score-lg {
      font-size: 52px;
      font-weight: 200;
      text-align: center;
      line-height: 1.1;
    }

    .score-sm {
      font-size: 28px;
      font-weight: 300;
      text-align: center;
    }

    .score-lbl {
      font-size: 10px;
      color: var(--muted);
      text-align: center;
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-top: 4px;
    }

    .row {
      display: flex;
      justify-content: space-between;
      padding: 5px 0;
      border-bottom: 1px solid rgba(100, 180, 255, .04);
      font-size: 12px;
    }

    .row:last-child {
      border-bottom: none;
    }

    .rk {
      color: var(--muted);
    }

    .rv {
      color: var(--text);
    }

    .tag {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 10px;
      font-weight: 500;
    }

    .tag-ok {
      background: rgba(0, 232, 143, .1);
      color: var(--green);
    }

    .tag-warn {
      background: rgba(255, 204, 2, .1);
      color: var(--yellow);
    }

    .tag-crit {
      background: rgba(255, 51, 102, .1);
      color: var(--red);
    }

    .tag-info {
      background: rgba(0, 229, 255, .1);
      color: var(--cyan);
    }

    .tag-purple {
      background: rgba(179, 136, 255, .1);
      color: var(--purple);
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
      font-weight: 500;
      font-size: 10px;
      text-transform: uppercase;
      letter-spacing: .5px;
      border-bottom: 1px solid rgba(100, 180, 255, .08);
    }

    .tbl td {
      padding: 5px 6px;
      border-bottom: 1px solid rgba(100, 180, 255, .03);
    }

    .insight-card {
      padding: 8px 10px;
      border-left: 3px solid var(--cyan);
      margin-bottom: 8px;
      background: rgba(0, 229, 255, .02);
      border-radius: 0 4px 4px 0;
      font-size: 12px;
    }

    .insight-card.sev-critical {
      border-left-color: var(--red);
    }

    .insight-card.sev-high {
      border-left-color: var(--yellow);
    }

    .insight-card.sev-medium {
      border-left-color: var(--purple);
    }

    .insight-card .it {
      font-weight: 600;
      margin-bottom: 2px;
    }

    .insight-card .id {
      color: var(--muted);
      font-size: 11px;
    }

    .btn {
      background: transparent;
      border: 1px solid var(--cyan);
      color: var(--cyan);
      padding: 8px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 11px;
      font-weight: 500;
      transition: all .2s;
    }

    .btn:hover {
      background: rgba(0, 229, 255, .1);
    }

    .btn:disabled {
      opacity: .3;
      cursor: not-allowed;
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
  <div class="top">
    <h1><i class="fas fa-brain"></i>&nbsp; Cognitive Center</h1>
    <div class="links">
      <a href="intelligence-center.php"><i class="fas fa-satellite-dish"></i> Intelligence</a>
      <a href="../developer/intelligence-center.php"><i class="fas fa-code"></i> Developer</a>
      <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    </div>
  </div>

  <div class="grid">

    <!-- Cognitive Score -->
    <div class="card" style="text-align:center;">
      <div class="card-t">Cognitive Score</div>
      <div class="score-lg" style="color:<?= $cogScore >= 90 ? 'var(--green)' : ($cogScore >= 70 ? 'var(--cyan)' : 'var(--yellow)') ?>"><?= $cogScore ?></div>
      <div class="score-lbl">Institutional IQ</div>
    </div>

    <!-- Sub-scores -->
    <div class="card" style="text-align:center;">
      <div class="card-t">Intelligence</div>
      <div class="score-sm" style="color:<?= $intScore >= 90 ? 'var(--green)' : 'var(--cyan)' ?>"><?= $intScore ?></div>
      <div class="score-lbl">Platform Layer</div>
    </div>
    <div class="card" style="text-align:center;">
      <div class="card-t">Academic</div>
      <div class="score-sm" style="color:<?= $acadScore >= 90 ? 'var(--green)' : ($acadScore >= 70 ? 'var(--yellow)' : 'var(--red)') ?>"><?= $acadScore ?></div>
      <div class="score-lbl">Academic Health</div>
    </div>
    <div class="card" style="text-align:center;">
      <div class="card-t">Adaptive</div>
      <div class="score-sm" style="color:<?= $adaptScore >= 85 ? 'var(--green)' : 'var(--yellow)' ?>"><?= $adaptScore ?></div>
      <div class="score-lbl">Learning Adapt.</div>
    </div>
    <div class="card" style="text-align:center;">
      <div class="card-t">Interaction</div>
      <div class="score-sm" style="color:<?= $interScore >= 85 ? 'var(--green)' : 'var(--yellow)' ?>"><?= $interScore ?></div>
      <div class="score-lbl">Human Model</div>
    </div>

    <!-- Ethics -->
    <div class="card" style="text-align:center;">
      <div class="card-t">Ethics Guard</div>
      <div class="score-sm" style="color:<?= $ethicsSafe ? 'var(--green)' : 'var(--red)' ?>"><?= $ethicsSafe ? 'SAFE' : 'ALERT' ?></div>
      <div class="score-lbl"><?= $ethics['rules_count'] ?? 0 ?> Rules Active</div>
      <?php if (!empty($ethics['recent_blocks'])): ?>
        <div style="margin-top:6px;font-size:10px;color:var(--muted);"><?= count($ethics['recent_blocks']) ?> recent blocks</div>
      <?php endif; ?>
    </div>

    <!-- Controls -->
    <div class="card">
      <div class="card-t"><i class="fas fa-play"></i> Controls</div>
      <button class="btn" id="btnRun" onclick="runCycle()"><i class="fas fa-brain"></i>&nbsp; Run Cognitive Cycle</button>
      <div id="runSt" style="margin-top:8px;font-size:11px;color:var(--muted);"></div>
      <?php if ($lastRun): ?>
        <div style="margin-top:10px;">
          <div class="row"><span class="rk">Last Run</span><span class="rv"><?= htmlspecialchars($lastRun['timestamp'] ?? '') ?></span></div>
          <div class="row"><span class="rk">Elapsed</span><span class="rv"><?= $lastRun['elapsed_ms'] ?? 0 ?>ms</span></div>
          <div class="row"><span class="rk">Insights</span><span class="rv"><?= $lastRun['insights_generated'] ?? 0 ?></span></div>
          <div class="row"><span class="rk">Policies Triggered</span><span class="rv"><?= $lastRun['policies_triggered'] ?? 0 ?></span></div>
          <div class="row"><span class="rk">Frictions</span><span class="rv"><?= $lastRun['frictions_detected'] ?? 0 ?></span></div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Executive Insights -->
    <div class="card span2">
      <div class="card-t"><i class="fas fa-lightbulb"></i> Executive Insights</div>
      <?php if (!empty($insights['insights'])): ?>
        <?php foreach (array_slice($insights['insights'], 0, 8) as $in): ?>
          <div class="insight-card sev-<?= htmlspecialchars($in['severity'] ?? 'info') ?>">
            <div class="it"><?= htmlspecialchars($in['title'] ?? '') ?></div>
            <div class="id"><?= htmlspecialchars($in['detail'] ?? '') ?></div>
            <span class="tag tag-<?= ($in['severity'] ?? 'info') === 'critical' ? 'crit' : (($in['severity'] ?? 'info') === 'high' ? 'warn' : 'info') ?>"><?= htmlspecialchars($in['severity'] ?? 'info') ?></span>
            <span style="font-size:10px;color:var(--muted);margin-left:6px;"><?= round(($in['confidence'] ?? 0) * 100) ?>% confidence</span>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="color:var(--muted);font-size:12px;">No insights generated yet. Run a cognitive cycle.</div>
      <?php endif; ?>
    </div>

    <!-- Institutional Model -->
    <div class="card span2">
      <div class="card-t"><i class="fas fa-university"></i> Institutional Model</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div>
          <h4 style="font-size:11px;color:var(--cyan);margin-bottom:6px;">Student Engagement</h4>
          <div class="row"><span class="rk">Students</span><span class="rv"><?= $model['student_summary']['total_students'] ?? 0 ?></span></div>
          <div class="row"><span class="rk">Attendance</span><span class="rv"><?= $model['student_summary']['overall_rate'] ?? 100 ?>%</span></div>
          <div class="row"><span class="rk">At Risk</span><span class="rv"><?= $model['student_summary']['at_risk_count'] ?? 0 ?></span></div>
        </div>
        <div>
          <h4 style="font-size:11px;color:var(--cyan);margin-bottom:6px;">Staff Efficiency</h4>
          <div class="row"><span class="rk">Teachers</span><span class="rv"><?= $model['staff_summary']['total_teachers'] ?? 0 ?></span></div>
          <div class="row"><span class="rk">Avg Active Days</span><span class="rv"><?= $model['staff_summary']['avg_active_days'] ?? 0 ?></span></div>
        </div>
      </div>
      <?php if (!empty($model['frictions'])): ?>
        <h4 style="font-size:11px;color:var(--yellow);margin:10px 0 6px;">Structural Frictions</h4>
        <?php foreach (array_slice($model['frictions'], 0, 5) as $f): ?>
          <div class="row">
            <span class="tag tag-<?= ($f['severity'] ?? '') === 'critical' ? 'crit' : 'warn' ?>"><?= htmlspecialchars($f['type'] ?? '') ?></span>
            <span class="rv" style="font-size:11px;"><?= htmlspecialchars($f['detail'] ?? '') ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Policy Engine -->
    <div class="card span2">
      <div class="card-t"><i class="fas fa-gavel"></i> Policy Engine</div>
      <?php if (!empty($policy['policies'])): ?>
        <table class="tbl">
          <thead>
            <tr>
              <th>Type</th>
              <th>Policy</th>
              <th>S/F</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($policy['policies'] as $p): ?>
              <tr>
                <td><span class="tag tag-info"><?= htmlspecialchars($p['policy_type'] ?? '') ?></span></td>
                <td><?= htmlspecialchars($p['name'] ?? '') ?></td>
                <td style="color:var(--green)"><?= $p['success_count'] ?? 0 ?><span style="color:var(--muted)">/</span><span style="color:var(--red)"><?= $p['fail_count'] ?? 0 ?></span></td>
                <td><?= (int) ($p['active'] ?? 0) ? '<span class="tag tag-ok">Active</span>' : '<span class="tag tag-warn">Inactive</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div style="color:var(--muted);font-size:12px;">No policies defined.</div>
      <?php endif; ?>
    </div>

    <!-- Academic Insights -->
    <div class="card">
      <div class="card-t"><i class="fas fa-graduation-cap"></i> Academic Reasoning</div>
      <div class="row"><span class="rk">Score</span><span class="rv" style="color:<?= ($academic['score'] ?? 100) >= 90 ? 'var(--green)' : 'var(--yellow)' ?>"><?= $academic['score'] ?? 0 ?>/100</span></div>
      <div class="row"><span class="rk">Insights</span><span class="rv"><?= $academic['insight_count'] ?? 0 ?></span></div>
      <?php foreach (array_slice($academic['insights'] ?? [], 0, 4) as $ai): ?>
        <div class="row"><span class="tag tag-<?= ($ai['severity'] ?? '') === 'high' ? 'warn' : 'info' ?>"><?= htmlspecialchars($ai['type'] ?? '') ?></span><span class="rv" style="font-size:10px;"><?= htmlspecialchars(substr($ai['detail'] ?? '', 0, 60)) ?></span></div>
      <?php endforeach; ?>
    </div>

    <!-- Adaptive Learning -->
    <div class="card">
      <div class="card-t"><i class="fas fa-robot"></i> Adaptive Learning</div>
      <div class="row"><span class="rk">Score</span><span class="rv" style="color:<?= ($adaptive['score'] ?? 100) >= 85 ? 'var(--green)' : 'var(--yellow)' ?>"><?= $adaptive['score'] ?? 0 ?>/100</span></div>
      <div class="row"><span class="rk">Recommendations</span><span class="rv"><?= $adaptive['recommendation_count'] ?? 0 ?></span></div>
      <div class="row"><span class="rk">Adaptations</span><span class="rv"><?= $adaptive['adaptation_count'] ?? 0 ?></span></div>
      <?php foreach (array_slice($adaptive['adaptations'] ?? [], 0, 3) as $ad): ?>
        <div class="row"><span class="tag tag-purple"><?= htmlspecialchars($ad['type'] ?? '') ?></span><span class="rv" style="font-size:10px;"><?= htmlspecialchars(substr($ad['action'] ?? '', 0, 50)) ?></span></div>
      <?php endforeach; ?>
    </div>

    <!-- Human Interaction -->
    <div class="card">
      <div class="card-t"><i class="fas fa-users"></i> Human Interaction</div>
      <div class="row"><span class="rk">Score</span><span class="rv" style="color:<?= ($interaction['score'] ?? 100) >= 85 ? 'var(--green)' : 'var(--yellow)' ?>"><?= $interaction['score'] ?? 0 ?>/100</span></div>
      <?php
      $roles = $interaction['roles'] ?? [];
      foreach ($roles as $role => $info):
        $level = $info['level'] ?? 'unknown';
        $color = $level === 'high' ? 'var(--green)' : ($level === 'moderate' ? 'var(--yellow)' : 'var(--red)');
      ?>
        <div class="row"><span class="rk"><?= ucfirst($role) ?></span><span class="rv" style="color:<?= $color ?>"><?= $level ?></span></div>
      <?php endforeach; ?>
      <?php foreach (array_slice($interaction['frictions'] ?? [], 0, 3) as $f): ?>
        <div class="row"><span class="tag tag-warn"><?= htmlspecialchars($f['type'] ?? '') ?></span><span class="rv" style="font-size:10px;"><?= htmlspecialchars(substr($f['suggestion'] ?? '', 0, 50)) ?></span></div>
      <?php endforeach; ?>
    </div>

    <!-- Institutional Memory -->
    <div class="card">
      <div class="card-t"><i class="fas fa-database"></i> Institutional Memory</div>
      <?php $ls = $memory['learning_score'] ?? []; ?>
      <div class="row"><span class="rk">Learning Score</span><span class="rv" style="color:<?= ($ls['score'] ?? 100) >= 80 ? 'var(--green)' : 'var(--yellow)' ?>"><?= $ls['score'] ?? 0 ?>%</span></div>
      <div class="row"><span class="rk">Total Memories</span><span class="rv"><?= $ls['total_memories'] ?? 0 ?></span></div>
      <div class="row"><span class="rk">Positive</span><span class="rv" style="color:var(--green)"><?= $ls['positive'] ?? 0 ?></span></div>
      <div class="row"><span class="rk">Negative</span><span class="rv" style="color:var(--red)"><?= $ls['negative'] ?? 0 ?></span></div>
      <div class="row"><span class="rk">Avg Confidence</span><span class="rv"><?= round(($ls['avg_confidence'] ?? 0.5) * 100) ?>%</span></div>
      <?php foreach (array_slice($memory['categories'] ?? [], 0, 4) as $cat): ?>
        <div class="row"><span class="rk"><?= htmlspecialchars($cat['category']) ?></span><span class="rv"><?= $cat['cnt'] ?></span></div>
      <?php endforeach; ?>
    </div>

  </div>

  <script>
    function runCycle() {
      const btn = document.getElementById('btnRun'),
        st = document.getElementById('runSt');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>&nbsp; Processing...';
      st.textContent = 'Cognitive cycle initiated...';

      fetch('../cron/cognitive.php?key=dashboard', {
          credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(d => {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-brain"></i>&nbsp; Run Cognitive Cycle';
          if (d.status === 'completed') {
            st.innerHTML = '<span style="color:var(--green)">Complete — Score: ' + d.cognitive_score + ' | ' + d.elapsed_ms + 'ms</span>';
            setTimeout(() => location.reload(), 2000);
          } else {
            st.innerHTML = '<span style="color:var(--yellow)">' + (d.message || 'unexpected response') + '</span>';
          }
        })
        .catch(e => {
          btn.disabled = false;
          btn.innerHTML = '<i class="fas fa-brain"></i>&nbsp; Run Cognitive Cycle';
          st.innerHTML = '<span style="color:var(--red)">Error: ' + e.message + '</span>';
        });
    }
  </script>
</body>

</html>
