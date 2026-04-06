<?php

/**
 * Database Monitor — Real-time database health and optimization dashboard.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

require_admin('../login.php');

// Gather DB data
$dbStats = ['tables' => [], 'total_rows' => 0, 'total_size' => 0, 'engine_counts' => []];
$slowQueries = [];
$healthIssues = [];

try {
  $pdo = db()->getConnection();

  // Table statistics
  $stmt = $pdo->query("SELECT TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH,
                         AUTO_INCREMENT, CREATE_TIME, UPDATE_TIME
                         FROM information_schema.TABLES
                         WHERE TABLE_SCHEMA = DATABASE()
                         ORDER BY DATA_LENGTH DESC");
  $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($tables as $t) {
    $size = ($t['DATA_LENGTH'] ?? 0) + ($t['INDEX_LENGTH'] ?? 0);
    $dbStats['tables'][] = [
      'name'       => $t['TABLE_NAME'],
      'engine'     => $t['ENGINE'] ?? 'unknown',
      'rows'       => (int)($t['TABLE_ROWS'] ?? 0),
      'size'       => $size,
      'auto_inc'   => $t['AUTO_INCREMENT'],
      'updated'    => $t['UPDATE_TIME'] ?? $t['CREATE_TIME'],
    ];
    $dbStats['total_rows'] += (int)($t['TABLE_ROWS'] ?? 0);
    $dbStats['total_size'] += $size;
    $eng = $t['ENGINE'] ?? 'unknown';
    $dbStats['engine_counts'][$eng] = ($dbStats['engine_counts'][$eng] ?? 0) + 1;
  }

  // Check for tables without primary keys
  $stmt = $pdo->query("SELECT t.TABLE_NAME
                         FROM information_schema.TABLES t
                         LEFT JOIN information_schema.KEY_COLUMN_USAGE k
                           ON t.TABLE_NAME = k.TABLE_NAME
                           AND t.TABLE_SCHEMA = k.TABLE_SCHEMA
                           AND k.CONSTRAINT_NAME = 'PRIMARY'
                         WHERE t.TABLE_SCHEMA = DATABASE()
                           AND k.TABLE_NAME IS NULL
                           AND t.TABLE_TYPE = 'BASE TABLE'");
  $noPK = $stmt->fetchAll(PDO::FETCH_COLUMN);
  if ($noPK) {
    $healthIssues[] = ['level' => 'warning', 'msg' => count($noPK) . ' table(s) missing primary keys: ' . implode(', ', array_slice($noPK, 0, 5))];
  }

  // Large tables warning
  foreach ($dbStats['tables'] as $t) {
    if ($t['rows'] > 100000) {
      $healthIssues[] = ['level' => 'info', 'msg' => "Large table: {$t['name']} ({$t['rows']} rows)"];
    }
  }

  // DevOps data if available
  try {
    $devopsData = DevOpsKernel::getDashboardData();
    $slowQueries = $devopsData['db_suggestions'] ?? [];
  } catch (\Throwable $e) {
  }
} catch (\Throwable $e) {
  $healthIssues[] = ['level' => 'critical', 'msg' => 'Database connection error: ' . $e->getMessage()];
}

$tableCount = count($dbStats['tables']);
$totalSizeMB = round($dbStats['total_size'] / 1048576, 2);
$dbHealth = empty($healthIssues) ? 100 : max(0, 100 - count($healthIssues) * 10);

function dbScoreColor(int $s): string
{
  return $s >= 90 ? '#00ff41' : ($s >= 70 ? '#00d4ff' : ($s >= 50 ? '#ffaa00' : '#ff4444'));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Database Monitor — <?= htmlspecialchars(APP_NAME ?? 'SAMS') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --bg: #080c14;
      --card: #0d1117;
      --border: rgba(0, 229, 255, .12);
      --text: #e0e0e0;
      --accent: #26c6da;
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
      background: rgba(38, 198, 218, .1);
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
      background: rgba(38, 198, 218, .04);
    }

    .badge-ok {
      background: #00ff4122;
      color: #00ff41;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: .75rem;
    }

    .badge-warn {
      background: #ffaa0022;
      color: #ffaa00;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: .75rem;
    }

    .badge-crit {
      background: #ff444422;
      color: #ff4444;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: .75rem;
    }

    .issue-row {
      padding: 8px 12px;
      border-left: 3px solid var(--accent);
      margin-bottom: 6px;
      background: rgba(255, 255, 255, .02);
      border-radius: 0 6px 6px 0;
      font-size: .85rem;
    }

    .issue-row.warning {
      border-color: #ffaa00;
    }

    .issue-row.critical {
      border-color: #ff4444;
    }
  </style>
</head>

<body>

  <div class="top-bar">
    <a href="<?= route('developer/index.php') ?>" class="back-btn"><i class="fas fa-arrow-left"></i> Portal</a>
    <h1><i class="fas fa-database"></i> Database Monitor</h1>
    <span class="stat-val" style="margin-left:auto;font-size:1.2rem;color:<?= dbScoreColor($dbHealth) ?>"><?= $dbHealth ?>/100</span>
  </div>

  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-val" style="color:var(--accent)"><?= $tableCount ?></div>
      <div class="stat-label">Tables</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#00ff41"><?= number_format($dbStats['total_rows']) ?></div>
      <div class="stat-label">Total Rows</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#e040fb"><?= $totalSizeMB ?> MB</div>
      <div class="stat-label">Database Size</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#ffaa00"><?= count($healthIssues) ?></div>
      <div class="stat-label">Health Issues</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:#00d4ff"><?= implode(', ', array_keys($dbStats['engine_counts'])) ?: 'N/A' ?></div>
      <div class="stat-label">Engines</div>
    </div>
  </div>

  <?php if ($healthIssues): ?>
    <div class="panel">
      <h3><i class="fas fa-exclamation-triangle"></i> Health Issues</h3>
      <?php foreach ($healthIssues as $issue): ?>
        <div class="issue-row <?= htmlspecialchars($issue['level']) ?>"><?= htmlspecialchars($issue['msg']) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="panel">
    <h3><i class="fas fa-table"></i> Table Statistics (Top 30)</h3>
    <div style="overflow-x:auto">
      <table>
        <thead>
          <tr>
            <th>Table</th>
            <th>Engine</th>
            <th>Rows</th>
            <th>Data Size</th>
            <th>Auto Inc</th>
            <th>Last Updated</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($dbStats['tables'], 0, 30) as $t): ?>
            <tr>
              <td><strong><?= htmlspecialchars($t['name']) ?></strong></td>
              <td><span class="badge-ok"><?= htmlspecialchars($t['engine']) ?></span></td>
              <td><?= number_format($t['rows']) ?></td>
              <td><?= round($t['size'] / 1024, 1) ?> KB</td>
              <td><?= $t['auto_inc'] ? number_format($t['auto_inc']) : '—' ?></td>
              <td><?= $t['updated'] ? date('M j, H:i', strtotime($t['updated'])) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($slowQueries): ?>
    <div class="panel">
      <h3><i class="fas fa-clock"></i> Optimization Suggestions</h3>
      <?php foreach (array_slice($slowQueries, 0, 10) as $sq): ?>
        <div class="issue-row warning"><?= htmlspecialchars(is_string($sq) ? $sq : ($sq['suggestion'] ?? json_encode($sq))) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
