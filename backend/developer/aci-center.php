<?php

/**
 * ACI — Autonomous Command Intelligence Center
 * Full dashboard for ACI monitoring, recommendations, and learning history.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

SecurityGateway::guard([
  'require_auth' => true,
  'require_role' => ['admin', 'developer'],
]);

$aci = CommandBrain::getStatus();
$learning = LearningMemory::getAll();

$page_title    = 'Command Intelligence';
$page_icon     = 'fas fa-brain';
$page_subtitle = 'Autonomous Command Intelligence — Observe · Predict · Decide · Execute · Learn';
$hide_header   = false;

ob_start();
?>

<style>
  .aci-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
  }

  .aci-card {
    background: #1e1e2e;
    border: 1px solid #333;
    border-radius: 12px;
    padding: 20px;
  }

  .aci-card h3 {
    font-size: 0.85rem;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 12px;
  }

  .aci-metric {
    font-size: 2rem;
    font-weight: 700;
  }

  .aci-metric.green {
    color: #00ff88;
  }

  .aci-metric.yellow {
    color: #ffcc00;
  }

  .aci-metric.red {
    color: #ff3366;
  }

  .aci-metric.cyan {
    color: #00ccff;
  }

  .aci-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
  }

  .aci-table th,
  .aci-table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid #333;
    font-size: 0.8rem;
  }

  .aci-table th {
    color: #888;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 1px;
  }

  .aci-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
  }

  .aci-badge.low {
    background: #00ff8833;
    color: #00ff88;
  }

  .aci-badge.medium {
    background: #ffcc0033;
    color: #ffcc00;
  }

  .aci-badge.high {
    background: #ff660033;
    color: #ff6600;
  }

  .aci-badge.critical {
    background: #ff336633;
    color: #ff3366;
  }

  .aci-btn {
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
  }

  .aci-btn:hover {
    background: #2a2a4e;
    border-color: #00ccff;
    color: #00ccff;
  }

  .aci-btn.primary {
    border-color: #00ccff;
    color: #00ccff;
  }

  .aci-section {
    background: #1e1e2e;
    border: 1px solid #333;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
  }

  .aci-section h2 {
    font-size: 1rem;
    color: #ccc;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
</style>

<!-- Status Cards -->
<div class="aci-grid">
  <div class="aci-card">
    <h3><i class="fas fa-signal"></i> Signal Score</h3>
    <div class="aci-metric <?= ($aci['signal_score'] ?? 100) >= 80 ? 'green' : (($aci['signal_score'] ?? 100) >= 50 ? 'yellow' : 'red') ?>">
      <?= $aci['signal_score'] ?? 100 ?>/100
    </div>
  </div>
  <div class="aci-card">
    <h3><i class="fas fa-shield-alt"></i> Risk Level</h3>
    <div class="aci-metric <?= ($aci['risk_level'] ?? 'LOW') === 'LOW' ? 'green' : (($aci['risk_level'] ?? 'LOW') === 'CRITICAL' ? 'red' : 'yellow') ?>">
      <?= htmlspecialchars($aci['risk_level'] ?? 'LOW') ?>
    </div>
  </div>
  <div class="aci-card">
    <h3><i class="fas fa-eye"></i> Predictions</h3>
    <div class="aci-metric cyan"><?= $aci['predictions'] ?? 0 ?></div>
  </div>
  <div class="aci-card">
    <h3><i class="fas fa-bolt"></i> Auto-Executed</h3>
    <div class="aci-metric green"><?= $aci['auto_executed'] ?? 0 ?></div>
  </div>
  <div class="aci-card">
    <h3><i class="fas fa-clock"></i> Last Cycle</h3>
    <div class="aci-metric cyan" style="font-size:1.2rem"><?= htmlspecialchars($aci['last_cycle'] ?? 'Never') ?></div>
  </div>
  <div class="aci-card">
    <h3><i class="fas fa-tachometer-alt"></i> Cycle Time</h3>
    <div class="aci-metric cyan"><?= $aci['cycle_ms'] ?? 0 ?>ms</div>
  </div>
</div>

<!-- Controls -->
<div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap">
  <button class="aci-btn primary" onclick="runCycle()"><i class="fas fa-play"></i> Run ACI Cycle Now</button>
  <a href="<?= route('developer/master-control/') ?>" class="aci-btn"><i class="fas fa-satellite-dish"></i> Back to MCC</a>
  <a href="<?= route('developer/') ?>" class="aci-btn"><i class="fas fa-arrow-left"></i> Dev Portal</a>
</div>

<!-- Active Recommendations -->
<div class="aci-section">
  <h2><i class="fas fa-lightbulb" style="color:#ffcc00"></i> Active Recommendations</h2>
  <?php if (empty($aci['recommendations'])): ?>
    <p style="color:#666;font-size:0.85rem">No active recommendations — system nominal.</p>
  <?php else: ?>
    <table class="aci-table">
      <thead>
        <tr>
          <th>Severity</th>
          <th>Issue</th>
          <th>Action</th>
          <th>Confidence</th>
          <th>Risk</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($aci['recommendations'] as $rec): ?>
          <tr>
            <td><span class="aci-badge <?= strtolower($rec['severity'] ?? 'low') ?>"><?= htmlspecialchars($rec['severity'] ?? '?') ?></span></td>
            <td><?= htmlspecialchars($rec['title'] ?? '') ?></td>
            <td><?= htmlspecialchars($rec['action_label'] ?? $rec['action'] ?? '') ?></td>
            <td><?= htmlspecialchars($rec['confidence'] ?? '?') ?>%</td>
            <td><span class="aci-badge <?= strtolower($rec['risk_level'] ?? 'low') ?>"><?= htmlspecialchars($rec['risk_level'] ?? '?') ?></span></td>
            <td>
              <?php if (!($rec['auto_execute'] ?? false)): ?>
                <button class="aci-btn" onclick="executeAction('<?= htmlspecialchars($rec['action'] ?? '', ENT_QUOTES) ?>')"><i class="fas fa-play"></i> Execute</button>
              <?php else: ?>
                <span style="color:#00ff88;font-size:0.75rem">✓ auto-fixed</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Learning Memory -->
<div class="aci-section">
  <h2><i class="fas fa-graduation-cap" style="color:#00ccff"></i> Learning Memory</h2>
  <?php if (empty($learning)): ?>
    <p style="color:#666;font-size:0.85rem">No learned patterns yet — ACI will learn from executed repairs.</p>
  <?php else: ?>
    <table class="aci-table">
      <thead>
        <tr>
          <th>Problem</th>
          <th>Solution</th>
          <th>Success</th>
          <th>Fail</th>
          <th>Confidence</th>
          <th>Last Seen</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($learning as $l): ?>
          <tr>
            <td><?= htmlspecialchars($l['problem_type'] ?? '') ?></td>
            <td><?= htmlspecialchars($l['solution_action'] ?? '') ?></td>
            <td style="color:#00ff88"><?= (int)($l['success_count'] ?? 0) ?></td>
            <td style="color:#ff3366"><?= (int)($l['fail_count'] ?? 0) ?></td>
            <td><?= round(($l['confidence'] ?? 0) * 100) ?>%</td>
            <td><?= htmlspecialchars($l['last_seen'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script>
  function runCycle() {
    fetch('<?= route('api/aci/cycle.php') ?>', {
        method: 'POST'
      })
      .then(r => r.json())
      .then(d => {
        alert('ACI cycle complete. Signal: ' + (d.data?.signal_score ?? '?') + ', Risk: ' + (d.data?.risk_level ?? '?'));
        location.reload();
      })
      .catch(() => alert('Cycle failed'));
  }

  function executeAction(action) {
    const fd = new FormData();
    fd.append('action', action);
    fetch('<?= route('api/aci/execute.php') ?>', {
        method: 'POST',
        body: fd
      })
      .then(r => r.json())
      .then(d => {
        alert(d.ok ? 'Executed: ' + action : 'Failed: ' + (d.error || 'unknown'));
        location.reload();
      })
      .catch(() => alert('Execution failed'));
  }
</script>

<?php
$page_content = ob_get_clean();
require_once BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
