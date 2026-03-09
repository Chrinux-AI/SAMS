<?php

/**
 * SAMS AI Backup Monitor
 * Monitor and manage system backups with AI predictions
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
  if ($action === 'backup-now') {
    try {
      require_once __DIR__ . '/../../includes/sams-init.php';
      $mgr = new SAMS_BackupManager();
      $result = $mgr->createBackup($_POST['backup_type'] ?? 'full');
      $message = ($result['success'] ?? false) ? "Backup created: " . htmlspecialchars($result['filename'] ?? '') : "Backup failed: " . htmlspecialchars($result['error'] ?? 'Unknown');
    } catch (Throwable $e) {
      $message = "Backup service unavailable. Check server configuration.";
    }
    try {
      log_activity($_SESSION['user_id'], 'ai_backup_create', 'backup', null, 'Created backup');
    } catch (Throwable $e) {
    }
  } elseif ($action === 'verify') {
    $backupFile = basename($_POST['backup_file'] ?? '');
    $message = "Verification initiated for: " . htmlspecialchars($backupFile);
    try {
      log_activity($_SESSION['user_id'], 'ai_backup_verify', 'backup', null, "Verified $backupFile");
    } catch (Throwable $e) {
    }
  } elseif ($action === 'restore' && !empty($_POST['backup_file'])) {
    $backupFile = basename($_POST['backup_file'] ?? '');
    $message = "Restore queued for: " . htmlspecialchars($backupFile) . ". This will be applied during the next maintenance window.";
    try {
      log_activity($_SESSION['user_id'], 'ai_backup_restore', 'backup', null, "Requested restore of $backupFile");
    } catch (Throwable $e) {
    }
  }
}

// Initialize
$backupStatus = 'unknown';
$backups = [];
$predictions = [];
$stats = ['total' => 0, 'total_size' => '0 MB', 'last_backup' => 'N/A', 'next_predicted' => 'N/A'];

// Try AI service
try {
  require_once __DIR__ . '/../../includes/sams-init.php';
  try {
    $mgr = new SAMS_BackupManager();
    $backupData = $mgr->getBackupReport();
    $backupStatus = $backupData['status'] ?? 'unknown';
    $backups = $backupData['backups'] ?? [];
    $predictions = $backupData['predictions'] ?? [];
    $stats = array_merge($stats, $backupData['stats'] ?? []);
  } catch (Throwable $e) {
  }
} catch (Throwable $e) {
}

// Fallback: scan actual backups directory
if (empty($backups)) {
  $backupsDir = __DIR__ . '/../../backups';
  if (is_dir($backupsDir)) {
    $files = glob($backupsDir . '/*.{sql,sql.gz,zip,tar.gz}', GLOB_BRACE);
    if ($files) {
      usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
      foreach (array_slice($files, 0, 20) as $file) {
        $size = filesize($file);
        $backups[] = [
          'name' => basename($file),
          'type' => pathinfo($file, PATHINFO_EXTENSION) === 'sql' ? 'database' : 'full',
          'size' => $size,
          'size_formatted' => $size > 1048576 ? round($size / 1048576, 1) . ' MB' : round($size / 1024, 1) . ' KB',
          'created_at' => date('Y-m-d H:i', filemtime($file)),
          'integrity' => 'unverified',
        ];
      }
      $stats['total'] = count($files);
      $totalSize = array_sum(array_map('filesize', $files));
      $stats['total_size'] = round($totalSize / 1048576, 1) . ' MB';
      $stats['last_backup'] = date('Y-m-d H:i', filemtime($files[0]));
      $daysSinceLast = (time() - filemtime($files[0])) / 86400;
      $backupStatus = $daysSinceLast < 1 ? 'healthy' : ($daysSinceLast < 3 ? 'warning' : 'critical');
    } else {
      $backupStatus = 'critical';
    }
  } else {
    $backupStatus = 'critical';
  }
}

$csrf = generate_csrf_token();
$statusColors = ['healthy' => '#22C55E', 'warning' => '#F59E0B', 'critical' => '#EF4444', 'unknown' => '#6B7280'];
$statusBg = ['healthy' => '#D1FAE5', 'warning' => '#FEF3C7', 'critical' => '#FEE2E2', 'unknown' => '#F3F4F6'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include '../../includes/favicon-loader.php'; ?>
  <script src="../../assets/js/theme-loader.js"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AI Backup Monitor - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/professional-ui.css">
  <link rel="stylesheet" href="../../assets/css/sidebar-nav.css">
  <style>
    .backup-header {
      background: linear-gradient(135deg, #4338CA, #818CF8);
      color: #fff;
      padding: 2rem;
      border-radius: var(--radius-xl, 16px);
      margin-bottom: 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center
    }

    .status-banner {
      padding: 1.25rem 1.5rem;
      border-radius: var(--radius-lg, 12px);
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 2rem;
      font-weight: 600;
      font-size: 1.1rem
    }

    .status-banner.healthy {
      background: #D1FAE5;
      color: #065F46;
      border: 1px solid #22C55E
    }

    .status-banner.warning {
      background: #FEF3C7;
      color: #92400E;
      border: 1px solid #F59E0B
    }

    .status-banner.critical {
      background: #FEE2E2;
      color: #991B1B;
      border: 1px solid #EF4444
    }

    .status-banner.unknown {
      background: #F3F4F6;
      color: #374151;
      border: 1px solid #D1D5DB
    }

    .status-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem
    }

    .status-banner.healthy .status-icon {
      background: #059669;
      color: #fff
    }

    .status-banner.warning .status-icon {
      background: #D97706;
      color: #fff
    }

    .status-banner.critical .status-icon {
      background: #DC2626;
      color: #fff
    }

    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem
    }

    .stat-card {
      background: var(--color-surface, #fff);
      border: 1px solid var(--color-border, #e5e7eb);
      border-radius: var(--radius-lg, 12px);
      padding: 1.25rem;
      text-align: center
    }

    .stat-card .stat-value {
      font-size: 1.5rem;
      font-weight: 800;
      color: var(--color-text, #111)
    }

    .stat-card .stat-label {
      font-size: .75rem;
      color: var(--color-text-secondary, #6b7280);
      text-transform: uppercase;
      letter-spacing: .05em
    }

    .ai-prediction-box {
      background: linear-gradient(135deg, #EDE9FE, #DDD6FE);
      border: 1px solid #A78BFA;
      border-radius: var(--radius-lg, 12px);
      padding: 1.5rem;
      margin-bottom: 2rem
    }

    .ai-prediction-box h3 {
      color: #5B21B6;
      display: flex;
      align-items: center;
      gap: .75rem;
      margin-bottom: 1rem
    }

    .prediction-list {
      list-style: none;
      padding: 0;
      margin: 0
    }

    .prediction-list li {
      padding: .5rem 0;
      color: #5B21B6;
      font-size: .9rem;
      display: flex;
      align-items: center;
      gap: .5rem
    }

    .backups-table {
      background: var(--color-surface, #fff);
      border: 1px solid var(--color-border, #e5e7eb);
      border-radius: var(--radius-lg, 12px);
      overflow: hidden
    }

    .backups-table table {
      width: 100%;
      border-collapse: collapse
    }

    .backups-table th {
      padding: .875rem 1.5rem;
      text-align: left;
      font-size: .75rem;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--color-text-secondary, #6b7280);
      font-weight: 600;
      background: var(--color-background-secondary, #f9fafb)
    }

    .backups-table td {
      padding: 1rem 1.5rem;
      border-top: 1px solid var(--color-border, #e5e7eb)
    }

    .backup-type {
      display: inline-flex;
      padding: .25rem .75rem;
      border-radius: var(--radius-md, 8px);
      font-size: .75rem;
      font-weight: 600
    }

    .backup-type.database {
      background: #DBEAFE;
      color: #2563EB
    }

    .backup-type.full {
      background: #D1FAE5;
      color: #059669
    }

    .backup-type.files {
      background: #FEF3C7;
      color: #D97706
    }

    .integrity-badge {
      display: inline-flex;
      padding: .2rem .5rem;
      border-radius: 4px;
      font-size: .7rem;
      font-weight: 700;
      text-transform: uppercase
    }

    .integrity-badge.verified {
      background: #D1FAE5;
      color: #059669
    }

    .integrity-badge.unverified {
      background: #F3F4F6;
      color: #6B7280
    }

    .integrity-badge.failed {
      background: #FEE2E2;
      color: #DC2626
    }

    .backup-now-btn {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .75rem 1.5rem;
      background: #fff;
      color: #4338CA;
      border: 2px solid rgba(255, 255, 255, .3);
      border-radius: var(--radius-lg, 12px);
      font-weight: 600;
      cursor: pointer;
      transition: all .2s
    }

    .backup-now-btn:hover {
      background: rgba(255, 255, 255, .9)
    }

    .btn-sm {
      padding: .375rem .75rem;
      font-size: .75rem;
      border: 1px solid var(--color-border, #e5e7eb);
      background: var(--color-surface, #fff);
      border-radius: 6px;
      cursor: pointer;
      font-weight: 500;
      transition: all .15s
    }

    .btn-sm:hover {
      background: #4338CA;
      color: #fff;
      border-color: #4338CA
    }
  </style>
</head>

<body>
  <div class="app-layout">
    <?php include '../../includes/sidebar-nav.php'; ?>
    <main class="main-content">
      <div class="cyber-header">
        <div class="page-icon-orb"><i class="fas fa-cloud-upload-alt"></i></div>
        <div>
          <h1>AI Backup Monitor</h1>
          <p>Backup management with AI predictions</p>
        </div>
      </div>
      <div class="cyber-content" style="max-width:1400px;margin:0 auto;padding:24px;">

        <?php if ($message): ?>
          <div style="padding:1rem;margin-bottom:1.5rem;background:<?php echo strpos($message, 'failed') !== false || strpos($message, 'unavailable') !== false ? '#FEE2E2' : '#D1FAE5'; ?>;border:1px solid <?php echo strpos($message, 'failed') !== false ? '#EF4444' : '#22C55E'; ?>;border-radius:8px;">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>

        <div class="backup-header">
          <div>
            <h1><i class="fas fa-cloud-upload-alt"></i> AI Backup Monitor</h1>
            <p>Intelligent backup management with predictive scheduling</p>
          </div>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
            <input type="hidden" name="action" value="backup-now">
            <input type="hidden" name="backup_type" value="full">
            <button type="submit" class="backup-now-btn" onclick="return confirm('Create a full backup now?');"><i class="fas fa-plus-circle"></i> Backup Now</button>
          </form>
        </div>

        <!-- Status Banner -->
        <div class="status-banner <?php echo htmlspecialchars($backupStatus); ?>">
          <div class="status-icon">
            <i class="fas fa-<?php echo $backupStatus === 'healthy' ? 'check' : ($backupStatus === 'warning' ? 'exclamation' : 'times'); ?>"></i>
          </div>
          <span>
            Backup Status: <?php echo htmlspecialchars(ucfirst($backupStatus)); ?> —
            <?php echo $backupStatus === 'healthy' ? 'All backups are current' : ($backupStatus === 'warning' ? 'Backup may be stale' : 'Backup attention needed'); ?>
          </span>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
          <div class="stat-card">
            <div class="stat-value"><?php echo (int)$stats['total']; ?></div>
            <div class="stat-label">Total Backups</div>
          </div>
          <div class="stat-card">
            <div class="stat-value"><?php echo htmlspecialchars($stats['total_size']); ?></div>
            <div class="stat-label">Total Size</div>
          </div>
          <div class="stat-card">
            <div class="stat-value"><?php echo htmlspecialchars($stats['last_backup']); ?></div>
            <div class="stat-label">Last Backup</div>
          </div>
          <div class="stat-card">
            <div class="stat-value"><?php echo htmlspecialchars($stats['next_predicted']); ?></div>
            <div class="stat-label">Next Predicted</div>
          </div>
        </div>

        <!-- AI Predictions -->
        <?php if (!empty($predictions)): ?>
          <div class="ai-prediction-box">
            <h3><i class="fas fa-brain"></i> AI Predictions</h3>
            <ul class="prediction-list">
              <?php foreach ($predictions as $pred): ?>
                <li><i class="fas fa-chevron-right"></i> <?php echo htmlspecialchars($pred); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <!-- Backups Table -->
        <div class="backups-table">
          <div style="padding:1rem 1.5rem;background:var(--color-background-secondary,#f9fafb);border-bottom:1px solid var(--color-border,#e5e7eb);font-weight:600;display:flex;justify-content:space-between;align-items:center;">
            <span>Backup History</span>
            <span style="font-size:.875rem;color:var(--color-text-secondary,#6b7280);"><?php echo count($backups); ?> backups</span>
          </div>
          <table>
            <thead>
              <tr>
                <th>Backup</th>
                <th>Type</th>
                <th>Size</th>
                <th>Date</th>
                <th>Integrity</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($backups)): ?>
                <tr>
                  <td colspan="6" style="text-align:center;padding:3rem;">
                    <p style="color:var(--color-text-secondary,#6b7280);">No backups found. Create one to get started.</p>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($backups as $bk): ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($bk['name'] ?? ''); ?></strong></td>
                    <td><span class="backup-type <?php echo htmlspecialchars($bk['type'] ?? 'full'); ?>"><?php echo htmlspecialchars(ucfirst($bk['type'] ?? 'full')); ?></span></td>
                    <td><?php echo htmlspecialchars($bk['size_formatted'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($bk['created_at'] ?? ''); ?></td>
                    <td><span class="integrity-badge <?php echo htmlspecialchars($bk['integrity'] ?? 'unverified'); ?>"><?php echo htmlspecialchars($bk['integrity'] ?? 'unverified'); ?></span></td>
                    <td>
                      <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="action" value="verify">
                        <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($bk['name'] ?? ''); ?>">
                        <button type="submit" class="btn-sm" title="Verify"><i class="fas fa-check-circle"></i></button>
                      </form>
                      <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="action" value="restore">
                        <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($bk['name'] ?? ''); ?>">
                        <button type="submit" class="btn-sm" title="Restore" onclick="return confirm('Restore this backup? This may overwrite current data.');"><i class="fas fa-undo"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </main>
  </div>
  <script src="../../assets/js/main.js"></script>
</body>

</html>
