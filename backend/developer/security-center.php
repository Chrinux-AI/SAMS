<?php

/**
 * Security Center — Security posture, threat detection, and audit dashboard.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

require_admin('../login.php');

// Gather security data
$secData = ['threats' => 0, 'blocked' => 0, 'score' => 100, 'audit_entries' => 0, 'recent_audits' => []];
$behaviorAlerts = [];
$securityScore = 100;

try {
  $pdo = db()->getConnection();

  // Audit log count (last 24h)
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
  $stmt->execute();
  $secData['audit_entries'] = (int) $stmt->fetchColumn();

  // Recent audit entries
  $stmt = $pdo->prepare("SELECT action, model, details, user_id, created_at
                           FROM audit_logs ORDER BY created_at DESC LIMIT 20");
  $stmt->execute();
  $secData['recent_audits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Failed login attempts (last 24h)
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs
                           WHERE action LIKE '%failed%login%' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
  $stmt->execute();
  $secData['threats'] = (int) $stmt->fetchColumn();
} catch (\Throwable $e) {
  // Tables may not exist yet
}

// SecurityHardener data
try {
  $hardenData = SecurityHardener::assess();
  $securityScore = $hardenData['security_score'] ?? 100;
  $secData['blocked'] = $hardenData['blocked_ips'] ?? 0;
} catch (\Throwable $e) {
  // Service may not be initialized
}

// Behavior monitor alerts
try {
  $behaviorAlerts = BehaviorMonitor::getRecentAlerts(10);
} catch (\Throwable $e) {
  $behaviorAlerts = [];
}

// DevOps security data
$devopsSec = [];
try {
  $dd = DevOpsKernel::getDashboardData();
  $devopsSec = $dd['security'] ?? [];
} catch (\Throwable $e) {
}

function secScoreColor(int $s): string
{
  return $s >= 90 ? '#00ff41' : ($s >= 70 ? '#ffaa00' : '#ff4444');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Security Center — <?= htmlspecialchars(APP_NAME ?? 'SAMS') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --bg: #080c14;
      --card: #0d1117;
      --border: rgba(239, 83, 80, .15);
      --text: #e0e0e0;
      --accent: #ef5350;
    }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 20px;
    }

    .top-bar {
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 24px;
    }

    .top-bar h1 {
      font-size: 1.6rem;
      color: var(--accent);
      margin: 0;
    }

    .back-btn {
      color: var(--accent);
      text-decoration: none;
      font-size: .9rem;
      padding: 6px 14px;
      border: 1px solid var(--border);
      border-radius: 8px;
    }

    .back-btn:hover {
      background: rgba(239, 83, 80, .1);
      color: #fff;
    }

    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 14px;
      margin-bottom: 24px;
    }

    .stat-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 18px;
      text-align: center;
    }

    .stat-val {
      font-size: 1.8rem;
      font-weight: 700;
    }

    .stat-label {
      font-size: .8rem;
      opacity: .6;
      margin-top: 4px;
    }

    .panel {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
    }

    .panel h3 {
      color: var(--accent);
      font-size: 1.1rem;
      margin: 0 0 14px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: .85rem;
    }

    th {
      text-align: left;
      padding: 8px 10px;
      border-bottom: 1px solid var(--border);
      color: var(--accent);
      opacity: .8;
      font-weight: 600;
    }

    td {
      padding: 8px 10px;
      border-bottom: 1px solid rgba(255, 255, 255, .04);
    }

    tr:hover td {
      background: rgba(239, 83, 80, .04);
    }

    .badge-safe {
      background: #00ff4122;
      color: #00ff41;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: .75rem;
    }

    .badge-threat {
      background: #ff444422;
      color: #ff4444;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: .75rem;
    }

    .alert-row {
      padding: 8px 12px;
      border-left: 3px solid #ffaa00;
      margin-bottom: 6px;
      background: rgba(255, 255, 255, .02);
      border-radius: 0 6px 6px 0;
      font-size: .85rem;
    }
  </style>
</head>

<body>

  <div class="top-bar">
    <a href="<?= route('developer/index.php') ?>" class="back-btn"><i class="fas fa-arrow-left"></i> Portal</a>
    <h1><i class="fas fa-shield-virus"></i> Security Center</h1>
    <span class="stat-val" style="margin-left:auto;font-size:1.2rem;color:<?= secScoreColor($securityScore) ?>"><?= $securityScore ?>/100</span>
  </div>

  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-val" style="color:<?= secScoreColor($securityScore) ?>"><?= $securityScore ?></div>
      <div class="stat-label">Security Score</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:<?= $secData['threats'] > 0 ? '#ff4444' : '#00ff41' ?>"><?= $secData['threats'] ?></div>
      <div class="stat-label">Threats (24h)</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#ffaa00"><?= $secData['blocked'] ?></div>
      <div class="stat-label">Blocked IPs</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#00d4ff"><?= number_format($secData['audit_entries']) ?></div>
      <div class="stat-label">Audit Entries (24h)</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#e040fb"><?= count($behaviorAlerts) ?></div>
      <div class="stat-label">Behavior Alerts</div>
    </div>
  </div>

  <?php if (!empty($devopsSec)): ?>
    <div class="panel">
      <h3><i class="fas fa-lock"></i> Security Hardening Status</h3>
      <div class="stats-row" style="margin-bottom:0">
        <?php foreach ($devopsSec as $check => $status): ?>
          <div style="padding:8px 12px; font-size:.85rem">
            <?php if ($status): ?>
              <span class="badge-safe"><i class="fas fa-check"></i></span>
            <?php else: ?>
              <span class="badge-threat"><i class="fas fa-times"></i></span>
            <?php endif; ?>
            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $check))) ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($behaviorAlerts): ?>
    <div class="panel">
      <h3><i class="fas fa-user-secret"></i> Behavior Alerts</h3>
      <?php foreach ($behaviorAlerts as $alert): ?>
        <div class="alert-row"><?= htmlspecialchars(is_string($alert) ? $alert : ($alert['message'] ?? json_encode($alert))) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="panel">
    <h3><i class="fas fa-clipboard-list"></i> Recent Audit Log</h3>
    <div style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>Time</th>
            <th>Action</th>
            <th>Model</th>
            <th>User</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($secData['recent_audits'])): ?>
            <tr>
              <td colspan="5" style="text-align:center;opacity:.5">No audit entries found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($secData['recent_audits'] as $a): ?>
              <tr>
                <td><?= date('M j H:i', strtotime($a['created_at'])) ?></td>
                <td><strong><?= htmlspecialchars($a['action'] ?? '') ?></strong></td>
                <td><?= htmlspecialchars($a['model'] ?? '') ?></td>
                <td><?= htmlspecialchars($a['user_id'] ?? '—') ?></td>
                <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars(mb_substr($a['details'] ?? '', 0, 80)) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
