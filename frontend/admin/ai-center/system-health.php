<?php

/**
 * SAMS AI System Health Monitor
 * Real-time system health checks and optimization
 */
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/database.php';
require_login('../../login.php');
if (!has_role('admin')) {
  redirect('../../login.php', 'Admin access required.', 'error');
}

$message = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
  $action = $_POST['action'] ?? '';
  if ($action === 'optimize') {
    try {
      require_once __DIR__ . '/../../includes/sams-init.php';
      $monitor = new SAMS_SystemHealthMonitor();
      $result = $monitor->optimize();
      $message = ($result['success'] ?? false) ? "Optimization completed. Score improved by " . htmlspecialchars($result['improvement'] ?? '0') . " points." : "Optimization completed with warnings.";
    } catch (Throwable $e) {
      $message = "Optimization service unavailable.";
    }
    try {
      log_activity($_SESSION['user_id'], 'ai_system_optimize', 'system', null, 'Ran system optimization');
    } catch (Throwable $e) {
    }
  } elseif ($action === 'refresh-checks') {
    $message = "Health checks refreshed.";
  }
}

// System health data
$healthScore = 0;
$healthChecks = [];
$recommendations = [];

try {
  require_once __DIR__ . '/../../includes/sams-init.php';
  try {
    $monitor = new SAMS_SystemHealthMonitor();
    $healthData = $monitor->getHealthReport();
    $healthScore = $healthData['score'] ?? 0;
    $healthChecks = $healthData['checks'] ?? [];
    $recommendations = $healthData['recommendations'] ?? [];
  } catch (Throwable $e) {
  }
} catch (Throwable $e) {
}

// Fallback: real PHP/MySQL checks
if (empty($healthChecks)) {
  $phpVer = phpversion();
  $phpOk = version_compare($phpVer, '8.0.0', '>=');
  $memUsed = memory_get_usage(true);
  $memLimit = (int)(ini_get('memory_limit')) * 1024 * 1024;
  $memPct = $memLimit > 0 ? round($memUsed / $memLimit * 100) : 0;
  $memOk = $memPct < 80;
  $diskFree = @disk_free_space(__DIR__);
  $diskTotal = @disk_total_space(__DIR__);
  $diskPct = ($diskTotal > 0) ? round(($diskTotal - $diskFree) / $diskTotal * 100) : 0;
  $diskOk = $diskPct < 85;
  $dbOk = false;
  $dbSize = 'N/A';
  $tableCount = 0;
  try {
    $dbInfo = db()->fetchOne("SELECT SUM(data_length + index_length) AS total_size, COUNT(*) AS table_count FROM information_schema.TABLES WHERE table_schema = DATABASE()");
    $dbOk = true;
    $dbSize = round(($dbInfo['total_size'] ?? 0) / 1024 / 1024, 1) . ' MB';
    $tableCount = $dbInfo['table_count'] ?? 0;
  } catch (Throwable $e) {
  }
  $uploadsDir = __DIR__ . '/../../uploads';
  $uploadsOk = is_writable($uploadsDir);
  $sessionOk = session_status() === PHP_SESSION_ACTIVE;

  $healthChecks = [
    ['name' => 'PHP Version', 'status' => $phpOk ? 'passed' : 'warning', 'detail' => "PHP $phpVer " . ($phpOk ? '(OK)' : '(Upgrade recommended)'), 'icon' => 'fa-code'],
    ['name' => 'Memory Usage', 'status' => $memOk ? 'passed' : 'warning', 'detail' => "$memPct% used of " . round($memLimit / 1024 / 1024) . "MB", 'icon' => 'fa-memory'],
    ['name' => 'Disk Space', 'status' => $diskOk ? 'passed' : 'warning', 'detail' => "$diskPct% used (" . round($diskFree / 1024 / 1024 / 1024, 1) . " GB free)", 'icon' => 'fa-hdd'],
    ['name' => 'Database', 'status' => $dbOk ? 'passed' : 'failed', 'detail' => $dbOk ? "$dbSize across $tableCount tables" : "Connection failed", 'icon' => 'fa-database'],
    ['name' => 'Uploads Directory', 'status' => $uploadsOk ? 'passed' : 'failed', 'detail' => $uploadsOk ? 'Writable' : 'Not writable', 'icon' => 'fa-folder'],
    ['name' => 'Session', 'status' => $sessionOk ? 'passed' : 'failed', 'detail' => $sessionOk ? 'Active' : 'Inactive', 'icon' => 'fa-user-shield'],
  ];
  $passed = count(array_filter($healthChecks, fn($c) => $c['status'] === 'passed'));
  $healthScore = round($passed / count($healthChecks) * 100);

  if (!$phpOk) $recommendations[] = ['title' => 'Upgrade PHP', 'description' => 'PHP 8.0+ is recommended for security and performance.', 'priority' => 'high'];
  if (!$memOk) $recommendations[] = ['title' => 'Increase Memory Limit', 'description' => "Memory usage is at $memPct%. Consider increasing memory_limit.", 'priority' => 'medium'];
  if (!$diskOk) $recommendations[] = ['title' => 'Free Disk Space', 'description' => "Disk usage is at $diskPct%. Consider cleanup.", 'priority' => 'high'];
  if (!$uploadsOk) $recommendations[] = ['title' => 'Fix Upload Permissions', 'description' => 'Uploads directory is not writable.', 'priority' => 'high'];
}

$csrf = generate_csrf_token();
$scoreColor = $healthScore >= 80 ? '#22C55E' : ($healthScore >= 60 ? '#F59E0B' : '#EF4444');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include '../../includes/favicon-loader.php'; ?>
  <script src="../../assets/js/theme-loader.js"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AI System Health - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/professional-ui.css">
    <?php include '../../includes/sams-head-bootstrap.php'; ?>

  <link rel="stylesheet" href="../../assets/css/sidebar-nav.css">
  <style>
    .health-header {
      background: linear-gradient(135deg, #059669, #34D399);
      color: #fff;
      padding: 2rem;
      border-radius: var(--radius-xl, 16px);
      margin-bottom: 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center
    }

    .health-score-card {
      background: var(--color-surface, #fff);
      border: 1px solid var(--color-border, #e5e7eb);
      border-radius: var(--radius-xl, 16px);
      padding: 2rem;
      text-align: center;
      margin-bottom: 2rem
    }

    .health-score-circle {
      width: 180px;
      height: 180px;
      border-radius: 50%;
      margin: 0 auto 1.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative
    }

    .health-score-circle .score-value {
      font-size: 3rem;
      font-weight: 800;
      line-height: 1
    }

    .health-score-circle .score-label {
      font-size: .875rem;
      color: var(--color-text-secondary, #6b7280)
    }

    .checks-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem
    }

    .check-card {
      background: var(--color-surface, #fff);
      border: 2px solid var(--color-border, #e5e7eb);
      border-radius: var(--radius-lg, 12px);
      padding: 1.5rem;
      display: flex;
      align-items: center;
      gap: 1rem
    }

    .check-card.passed {
      border-color: #22C55E
    }

    .check-card.warning {
      border-color: #F59E0B
    }

    .check-card.failed {
      border-color: #EF4444
    }

    .check-icon {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem
    }

    .check-card.passed .check-icon {
      background: #D1FAE5;
      color: #059669
    }

    .check-card.warning .check-icon {
      background: #FEF3C7;
      color: #D97706
    }

    .check-card.failed .check-icon {
      background: #FEE2E2;
      color: #DC2626
    }

    .check-status {
      display: inline-flex;
      padding: .25rem .75rem;
      border-radius: var(--radius-md, 8px);
      font-size: .75rem;
      font-weight: 600;
      text-transform: uppercase
    }

    .check-status.passed {
      background: #D1FAE5;
      color: #059669
    }

    .check-status.warning {
      background: #FEF3C7;
      color: #D97706
    }

    .check-status.failed {
      background: #FEE2E2;
      color: #DC2626
    }

    .check-info h3 {
      font-weight: 600;
      margin-bottom: .25rem
    }

    .check-info p {
      font-size: .875rem;
      color: var(--color-text-secondary, #6b7280)
    }

    .recommendation-card {
      background: var(--color-surface, #fff);
      border-left: 4px solid #3B82F6;
      border-radius: var(--radius-lg, 12px);
      padding: 1.25rem 1.5rem;
      margin-bottom: 1rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, .06)
    }

    .recommendation-card h4 {
      font-weight: 600;
      margin-bottom: .25rem;
      display: flex;
      justify-content: space-between;
      align-items: center
    }

    .recommendation-card p {
      font-size: .875rem;
      color: var(--color-text-secondary, #6b7280)
    }

    .priority-badge {
      font-size: .7rem;
      font-weight: 700;
      padding: .2rem .5rem;
      border-radius: 4px;
      text-transform: uppercase
    }

    .priority-badge.high {
      background: #FEE2E2;
      color: #DC2626
    }

    .priority-badge.medium {
      background: #FEF3C7;
      color: #D97706
    }

    .priority-badge.low {
      background: #D1FAE5;
      color: #059669
    }

    .btn-optimize {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .75rem 1.5rem;
      background: #059669;
      color: #fff;
      border: none;
      border-radius: var(--radius-lg, 12px);
      font-weight: 600;
      cursor: pointer;
      font-size: 1rem;
      transition: background .2s
    }

    .btn-optimize:hover {
      background: #047857
    }
  </style>
</head>

<body>
  <div class="app-layout">
    <?php include '../../includes/sidebar-nav.php'; ?>
    <main class="main-content">
      <div class="cyber-header">
        <div class="page-icon-orb"><i class="fas fa-heartbeat"></i></div>
        <div>
          <h1>AI System Health</h1>
          <p>Real-time system monitoring &amp; optimization</p>
        </div>
      </div>
      <div class="cyber-content" style="max-width:1400px;margin:0 auto;padding:24px;">

        <?php if ($message): ?>
          <div style="padding:1rem;margin-bottom:1.5rem;background:<?php echo strpos($message, 'unavailable') !== false || strpos($message, 'warning') !== false ? '#FEF3C7' : '#D1FAE5'; ?>;border:1px solid <?php echo strpos($message, 'unavailable') !== false ? '#F59E0B' : '#22C55E'; ?>;border-radius:8px;">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>

        <div class="health-header">
          <div>
            <h1><i class="fas fa-heartbeat"></i> System Health Monitor</h1>
            <p>AI-powered diagnostics and optimization recommendations</p>
          </div>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <input type="hidden" name="action" value="refresh-checks">
            <button type="submit" style="background:rgba(255,255,255,.2);color:#fff;border:none;padding:.75rem 1.25rem;border-radius:8px;cursor:pointer;font-weight:600;"><i class="fas fa-sync-alt"></i> Refresh</button>
          </form>
        </div>

        <!-- Health Score -->
        <div class="health-score-card">
          <div class="health-score-circle" style="background:conic-gradient(<?php echo $scoreColor; ?> <?php echo $healthScore * 3.6; ?>deg, #e5e7eb 0deg);">
            <div style="width:140px;height:140px;border-radius:50%;background:var(--color-surface,#fff);display:flex;flex-direction:column;align-items:center;justify-content:center;">
              <span class="score-value" style="color:<?php echo $scoreColor; ?>;"><?php echo (int)$healthScore; ?></span>
              <span class="score-label">Health Score</span>
            </div>
          </div>
          <p style="color:var(--color-text-secondary,#6b7280);margin-bottom:1.5rem;">
            <?php
            $passed = count(array_filter($healthChecks, fn($c) => $c['status'] === 'passed'));
            $total = count($healthChecks);
            echo "$passed of $total checks passed";
            ?>
          </p>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <input type="hidden" name="action" value="optimize">
            <button type="submit" class="btn-optimize" onclick="return confirm('Run system optimization?');"><i class="fas fa-bolt"></i> Run Optimization</button>
          </form>
        </div>

        <!-- Health Checks -->
        <h2 style="margin-bottom:1rem;"><i class="fas fa-clipboard-check"></i> System Checks</h2>
        <div class="checks-grid">
          <?php foreach ($healthChecks as $check): ?>
            <div class="check-card <?php echo htmlspecialchars($check['status'] ?? 'warning'); ?>">
              <div class="check-icon"><i class="fas <?php echo htmlspecialchars($check['icon'] ?? 'fa-server'); ?>"></i></div>
              <div class="check-info" style="flex:1;">
                <h3><?php echo htmlspecialchars($check['name'] ?? ''); ?></h3>
                <p><?php echo htmlspecialchars($check['detail'] ?? ''); ?></p>
              </div>
              <span class="check-status <?php echo htmlspecialchars($check['status'] ?? 'warning'); ?>">
                <?php echo htmlspecialchars($check['status'] ?? 'unknown'); ?>
              </span>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Recommendations -->
        <?php if (!empty($recommendations)): ?>
          <h2 style="margin-bottom:1rem;"><i class="fas fa-lightbulb"></i> AI Recommendations</h2>
          <?php foreach ($recommendations as $rec): ?>
            <div class="recommendation-card" style="border-left-color:<?php echo ($rec['priority'] ?? '') === 'high' ? '#EF4444' : (($rec['priority'] ?? '') === 'medium' ? '#F59E0B' : '#3B82F6'); ?>">
              <h4>
                <?php echo htmlspecialchars($rec['title'] ?? ''); ?>
                <span class="priority-badge <?php echo htmlspecialchars($rec['priority'] ?? 'low'); ?>"><?php echo htmlspecialchars($rec['priority'] ?? 'low'); ?></span>
              </h4>
              <p><?php echo htmlspecialchars($rec['description'] ?? ''); ?></p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

      </div>
    </main>
  </div>
  <script src="../../assets/js/main.js"></script>
</body>

</html>
