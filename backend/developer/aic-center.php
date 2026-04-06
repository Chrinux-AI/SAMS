<?php

/**
 * AIC — Institutional Consciousness Center
 * Full dashboard for institutional intelligence monitoring.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

SecurityGateway::guard([
  'require_auth' => true,
  'require_role' => ['admin', 'developer'],
]);

$aic = InstitutionBrain::getStatus();

$page_title    = 'Institution Intelligence';
$page_icon     = 'fas fa-university';
$page_subtitle = 'Autonomous Institutional Consciousness — Attendance · Workload · Engagement · Predictions · Policy';
$hide_header   = false;

ob_start();
?>

<style>
  .aic-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
  }

  .aic-card {
    background: #1a1a2e;
    border: 1px solid #333;
    border-radius: 12px;
    padding: 20px;
  }

  .aic-card h3 {
    font-size: 0.8rem;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 10px;
  }

  .aic-val {
    font-size: 2rem;
    font-weight: 700;
  }

  .aic-val.green {
    color: #00ff88;
  }

  .aic-val.yellow {
    color: #ffcc00;
  }

  .aic-val.red {
    color: #ff3366;
  }

  .aic-val.cyan {
    color: #00ccff;
  }

  .aic-section {
    background: #1a1a2e;
    border: 1px solid #333;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
  }

  .aic-section h2 {
    font-size: 1rem;
    color: #ccc;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .aic-table {
    width: 100%;
    border-collapse: collapse;
  }

  .aic-table th,
  .aic-table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid #333;
    font-size: 0.8rem;
  }

  .aic-table th {
    color: #888;
    text-transform: uppercase;
    font-size: 0.7rem;
  }

  .aic-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
  }

  .aic-badge.critical {
    background: #ff336633;
    color: #ff3366;
  }

  .aic-badge.high {
    background: #ff660033;
    color: #ff6600;
  }

  .aic-badge.medium {
    background: #ffcc0033;
    color: #ffcc00;
  }

  .aic-badge.low {
    background: #00ff8833;
    color: #00ff88;
  }

  .aic-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: 1px solid #444;
    background: #1a1a2e;
    color: #ddd;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.2s;
    text-decoration: none;
  }

  .aic-btn:hover {
    background: #2a2a4e;
    border-color: #00ccff;
    color: #00ccff;
  }

  .aic-btn.primary {
    border-color: #00ccff;
    color: #00ccff;
  }
</style>

<!-- Status Cards -->
<div class="aic-grid">
  <div class="aic-card">
    <h3><i class="fas fa-heartbeat"></i> Health Score</h3>
    <div class="aic-val <?= ($aic['health_score'] ?? 100) >= 80 ? 'green' : (($aic['health_score'] ?? 100) >= 50 ? 'yellow' : 'red') ?>">
      <?= $aic['health_score'] ?? 100 ?>/100
    </div>
  </div>
  <div class="aic-card">
    <h3><i class="fas fa-calendar-check"></i> Attendance Rate</h3>
    <div class="aic-val <?= ($aic['attendance']['overall_rate'] ?? 0) >= 85 ? 'green' : 'yellow' ?>">
      <?= $aic['attendance']['overall_rate'] ?? 0 ?>%
    </div>
  </div>
  <div class="aic-card">
    <h3><i class="fas fa-balance-scale"></i> Workload Balance</h3>
    <div class="aic-val <?= ($aic['workload']['balance_score'] ?? 100) >= 70 ? 'green' : 'yellow' ?>">
      <?= $aic['workload']['balance_score'] ?? 100 ?>
    </div>
  </div>
  <div class="aic-card">
    <h3><i class="fas fa-users"></i> Engagement</h3>
    <div class="aic-val <?= ($aic['engagement']['engagement_score'] ?? 100) >= 75 ? 'green' : 'yellow' ?>">
      <?= $aic['engagement']['engagement_score'] ?? 100 ?>%
    </div>
  </div>
  <div class="aic-card">
    <h3><i class="fas fa-exclamation-triangle"></i> Risk Alerts</h3>
    <div class="aic-val <?= count($aic['risk_alerts'] ?? []) === 0 ? 'green' : 'red' ?>">
      <?= count($aic['risk_alerts'] ?? []) ?>
    </div>
  </div>
  <div class="aic-card">
    <h3><i class="fas fa-chart-line"></i> Efficiency</h3>
    <div class="aic-val cyan"><?= $aic['policy']['efficiency_score'] ?? 100 ?></div>
  </div>
</div>

<!-- Controls -->
<div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap">
  <button class="aic-btn primary" onclick="runAICCycle()"><i class="fas fa-play"></i> Run AIC Cycle</button>
  <a href="<?= route('developer/master-control/') ?>" class="aic-btn"><i class="fas fa-satellite-dish"></i> Back to MCC</a>
  <a href="<?= route('developer/') ?>" class="aic-btn"><i class="fas fa-arrow-left"></i> Dev Portal</a>
</div>

<!-- Risk Alerts -->
<div class="aic-section">
  <h2><i class="fas fa-exclamation-triangle" style="color:#ff3366"></i> Risk Alerts</h2>
  <?php if (empty($aic['risk_alerts'])): ?>
    <p style="color:#666;font-size:0.85rem">No active risk alerts — institution operating normally.</p>
  <?php else: ?>
    <table class="aic-table">
      <thead>
        <tr>
          <th>Severity</th>
          <th>Source</th>
          <th>Alert</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($aic['risk_alerts'] as $alert): ?>
          <tr>
            <td><span class="aic-badge <?= strtolower($alert['severity'] ?? 'low') ?>"><?= htmlspecialchars($alert['severity'] ?? '?') ?></span></td>
            <td><?= htmlspecialchars($alert['source'] ?? '?') ?></td>
            <td><?= htmlspecialchars($alert['message'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Attendance Details -->
<div class="aic-section">
  <h2><i class="fas fa-calendar-check" style="color:#00ff88"></i> Attendance Intelligence</h2>
  <div class="aic-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
    <div><strong style="color:#888;font-size:0.75rem">Present Today</strong><br><span style="color:#00ff88;font-size:1.3rem"><?= $aic['attendance']['present_today'] ?? 0 ?></span></div>
    <div><strong style="color:#888;font-size:0.75rem">Absent Today</strong><br><span style="color:#ff3366;font-size:1.3rem"><?= $aic['attendance']['absent_today'] ?? 0 ?></span></div>
    <div><strong style="color:#888;font-size:0.75rem">Late Today</strong><br><span style="color:#ffcc00;font-size:1.3rem"><?= $aic['attendance']['late_today'] ?? 0 ?></span></div>
    <div><strong style="color:#888;font-size:0.75rem">Chronic Absent</strong><br><span style="color:#ff6600;font-size:1.3rem"><?= $aic['attendance']['chronic_absent'] ?? 0 ?></span></div>
    <div><strong style="color:#888;font-size:0.75rem">Trend</strong><br><span style="color:#00ccff;font-size:1.3rem"><?= htmlspecialchars($aic['attendance']['trends']['direction'] ?? 'unknown') ?></span></div>
  </div>
</div>

<!-- Policy Recommendations -->
<div class="aic-section">
  <h2><i class="fas fa-gavel" style="color:#ffcc00"></i> Policy Recommendations</h2>
  <?php if (empty($aic['policy']['recommendations'] ?? [])): ?>
    <p style="color:#666;font-size:0.85rem">No policy recommendations at this time.</p>
  <?php else: ?>
    <table class="aic-table">
      <thead>
        <tr>
          <th>Priority</th>
          <th>Area</th>
          <th>Recommendation</th>
          <th>Detail</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($aic['policy']['recommendations'] as $rec): ?>
          <tr>
            <td><span class="aic-badge <?= strtolower($rec['priority'] ?? 'low') ?>"><?= htmlspecialchars($rec['priority'] ?? '?') ?></span></td>
            <td><?= htmlspecialchars($rec['area'] ?? '') ?></td>
            <td><?= htmlspecialchars($rec['title'] ?? '') ?></td>
            <td style="color:#aaa;font-size:0.75rem"><?= htmlspecialchars($rec['detail'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script>
  function runAICCycle() {
    fetch('<?= route('api/aic/insights.php') ?>', {
        method: 'POST'
      })
      .then(r => r.json())
      .then(d => {
        alert('AIC cycle complete. Health Score: ' + (d.data?.health_score ?? '?'));
        location.reload();
      })
      .catch(() => alert('AIC cycle failed'));
  }
</script>

<?php
$page_content = ob_get_clean();
require_once BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
