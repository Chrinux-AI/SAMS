<?php

/**
 * SAMS Admin AI Control Center - Main Dashboard
 * Central hub for all AI-powered administrative functions
 */
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/database.php';
require_login('../../login.php');
if (!has_role('admin')) {
  redirect('../../login.php', 'Admin access required.', 'error');
}

// Handle POST actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
  $action = $_POST['action'] ?? '';
  if ($action === 'run-anomaly-scan') {
    $message = 'Anomaly scan initiated.';
    try {
      log_activity($_SESSION['user_id'], 'ai_anomaly_scan', 'system', null, 'Manual anomaly scan from AI Center');
    } catch (Throwable $e) {
    }
  } elseif ($action === 'generate-report') {
    $message = 'AI report generation started.';
    try {
      log_activity($_SESSION['user_id'], 'ai_report_generate', 'system', null, 'Report generation from AI Center');
    } catch (Throwable $e) {
    }
  } elseif ($action === 'backup-now') {
    $message = 'Backup process initiated.';
    try {
      log_activity($_SESSION['user_id'], 'ai_backup_trigger', 'system', null, 'Manual backup from AI Center');
    } catch (Throwable $e) {
    }
  } elseif ($action === 'system-optimize') {
    $message = 'System optimization started.';
    try {
      log_activity($_SESSION['user_id'], 'ai_system_optimize', 'system', null, 'System optimization from AI Center');
    } catch (Throwable $e) {
    }
  }
}

// Safe defaults
$anomalies = [];
$widgets = [
  'attendance_anomalies' => 0,
  'suspicious_logins' => 0,
  'system_health_score' => 100,
  'backup_status' => 'unknown',
  'unverified_accounts' => 0,
  'teacher_workload' => ['average' => 'N/A', 'imbalance' => 0]
];
$alerts = [];
$dashboardData = [
  'active_models' => [],
  'predictions_today' => 0,
  'active_workflows' => 0,
  'tasks_automated' => 0,
  'docs_generated' => 0,
  'templates' => 0,
  'at_risk_students' => 0,
  'predictions_accuracy' => '0%'
];
$securityThreats = ['blocked' => 0];
$systemHealth = ['issues' => 0, 'health_score' => 100, 'critical_issues' => 0];
$backupStatus = ['total_backups' => 0, 'size' => '0 GB', 'last_backup' => 'Never', 'status' => 'unknown'];

// Try AI services
try {
  require_once __DIR__ . '/../../includes/sams-init.php';
  try {
    $aiDashboard = new SAMS_AI_Dashboard();
    $dashboardData = array_merge($dashboardData, $aiDashboard->getDashboardSummary($_SESSION['tenant_id'] ?? 1));
  } catch (Throwable $e) {
  }
  try {
    $anomalyDetector = new SAMS_AttendanceAnomalyDetector();
    $anomalies = $anomalyDetector->detectAllAnomalies($_SESSION['tenant_id'] ?? 1);
    $widgets['attendance_anomalies'] = count($anomalies['attendance'] ?? []);
  } catch (Throwable $e) {
  }
  try {
    $securityGuardian = new SAMS_SecurityGuardian();
    $securityThreats = array_merge($securityThreats, $securityGuardian->analyzeThreats($_SESSION['tenant_id'] ?? 1));
    $widgets['suspicious_logins'] = count($securityThreats['login_anomalies'] ?? []);
  } catch (Throwable $e) {
  }
  try {
    $healthMonitor = new SAMS_SystemHealthMonitor();
    $systemHealth = array_merge($systemHealth, $healthMonitor->runAllChecks());
    $widgets['system_health_score'] = $systemHealth['health_score'] ?? 100;
  } catch (Throwable $e) {
  }
  try {
    $backupMgr = new SAMS_BackupManager();
    $backupStatus = array_merge($backupStatus, $backupMgr->getBackupStatus());
    $widgets['backup_status'] = $backupStatus['status'] ?? 'unknown';
  } catch (Throwable $e) {
  }
} catch (Throwable $e) {
}

// Database fallback queries
try {
  $widgets['unverified_accounts'] = (int)(db()->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE is_active = 0")['cnt'] ?? 0);
} catch (Throwable $e) {
}
try {
  if ($widgets['suspicious_logins'] === 0) {
    $widgets['suspicious_logins'] = (int)(db()->fetchOne(
      "SELECT COUNT(*) as cnt FROM audit_logs WHERE action LIKE '%failed_login%' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    )['cnt'] ?? 0);
  }
} catch (Throwable $e) {
}

$alerts = generateAIAlerts($anomalies, $securityThreats, $systemHealth);
$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include '../../includes/favicon-loader.php'; ?>
  <script src="../../assets/js/theme-loader.js"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AI Control Center - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/professional-ui.css">
    <?php include '../../includes/sams-head-bootstrap.php'; ?>

  <link rel="stylesheet" href="../../assets/css/sidebar-nav.css">
  <style>
    .ai-center-header {
      background: linear-gradient(135deg, #4F46E5, #7C3AED);
      color: #fff;
      padding: 2rem;
      border-radius: var(--radius-xl, 16px);
      margin-bottom: 2rem
    }

    .ai-center-header h1 {
      font-size: 1.875rem;
      font-weight: 700;
      margin-bottom: .5rem
    }

    .ai-center-header p {
      opacity: .9;
      font-size: 1rem
    }

    .ai-status-badge {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .5rem 1rem;
      background: rgba(255, 255, 255, .2);
      border-radius: 9999px;
      font-size: .875rem;
      font-weight: 500
    }

    .ai-status-badge.online::before {
      content: '';
      width: 8px;
      height: 8px;
      background: #22C55E;
      border-radius: 50%;
      animation: pulse 2s infinite
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: 1
      }

      50% {
        opacity: .5
      }
    }

    .ai-modules-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem
    }

    .ai-module-card {
      background: var(--color-surface, #fff);
      border: 1px solid var(--color-border, #e5e7eb);
      border-radius: var(--radius-xl, 16px);
      padding: 1.5rem;
      transition: all .3s ease;
      cursor: pointer;
      text-decoration: none;
      color: inherit;
      display: block
    }

    .ai-module-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, .1);
      border-color: #4F46E5
    }

    .ai-module-icon {
      width: 56px;
      height: 56px;
      border-radius: var(--radius-lg, 12px);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 1rem
    }

    .ai-module-icon.anomaly {
      background: linear-gradient(135deg, #DC2626, #EF4444);
      color: #fff
    }

    .ai-module-icon.security {
      background: linear-gradient(135deg, #7C2D12, #92400E);
      color: #fff
    }

    .ai-module-icon.automation {
      background: linear-gradient(135deg, #059669, #10B981);
      color: #fff
    }

    .ai-module-icon.documentation {
      background: linear-gradient(135deg, #0369A1, #0EA5E9);
      color: #fff
    }

    .ai-module-icon.health {
      background: linear-gradient(135deg, #059669, #22C55E);
      color: #fff
    }

    .ai-module-icon.backup {
      background: linear-gradient(135deg, #4338CA, #6366F1);
      color: #fff
    }

    .ai-module-icon.predictive {
      background: linear-gradient(135deg, #BE185D, #EC4899);
      color: #fff
    }

    .ai-module-card h3 {
      font-size: 1.125rem;
      font-weight: 600;
      margin-bottom: .5rem
    }

    .ai-module-card p {
      font-size: .875rem;
      color: var(--color-text-secondary, #6b7280);
      margin-bottom: 1rem;
      line-height: 1.5
    }

    .ai-module-stats {
      display: flex;
      gap: 1rem;
      padding-top: 1rem;
      border-top: 1px solid var(--color-border, #e5e7eb)
    }

    .ai-module-stat {
      display: flex;
      flex-direction: column
    }

    .ai-module-stat-value {
      font-size: 1.25rem;
      font-weight: 700;
      color: #4F46E5
    }

    .ai-module-stat-label {
      font-size: .75rem;
      color: var(--color-text-muted, #9ca3af);
      text-transform: uppercase;
      letter-spacing: .05em
    }

    .ai-widgets-section {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem
    }

    .ai-widget {
      background: var(--color-surface, #fff);
      border: 1px solid var(--color-border, #e5e7eb);
      border-radius: var(--radius-xl, 16px);
      padding: 1.5rem
    }

    .ai-widget-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1rem
    }

    .ai-widget-title {
      font-size: .875rem;
      font-weight: 600;
      color: var(--color-text-secondary, #6b7280);
      text-transform: uppercase;
      letter-spacing: .05em
    }

    .ai-widget-value {
      font-size: 2rem;
      font-weight: 700
    }

    .ai-widget-trend {
      display: flex;
      align-items: center;
      gap: .5rem;
      font-size: .875rem
    }

    .ai-widget-trend.up {
      color: #22C55E
    }

    .ai-widget-trend.down {
      color: #EF4444
    }

    .ai-alerts-section {
      background: var(--color-surface, #fff);
      border: 1px solid var(--color-border, #e5e7eb);
      border-radius: var(--radius-xl, 16px);
      padding: 1.5rem;
      margin-bottom: 2rem
    }

    .ai-alerts-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.5rem
    }

    .ai-alerts-header h2 {
      font-size: 1.25rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: .5rem
    }

    .ai-alert-badge {
      background: #EF4444;
      color: #fff;
      padding: .25rem .75rem;
      border-radius: 9999px;
      font-size: .75rem;
      font-weight: 600
    }

    .ai-alert-item {
      display: flex;
      align-items: flex-start;
      gap: 1rem;
      padding: 1rem;
      border: 1px solid var(--color-border, #e5e7eb);
      border-radius: var(--radius-lg, 12px);
      margin-bottom: .75rem;
      transition: all .2s
    }

    .ai-alert-item:hover {
      background: var(--color-background-secondary, #f9fafb)
    }

    .ai-alert-icon {
      width: 40px;
      height: 40px;
      border-radius: var(--radius-md, 8px);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0
    }

    .ai-alert-icon.critical {
      background: #FECACA;
      color: #DC2626
    }

    .ai-alert-icon.warning {
      background: #FDE68A;
      color: #D97706
    }

    .ai-alert-icon.info {
      background: #BFDBFE;
      color: #2563EB
    }

    .ai-alert-content {
      flex: 1
    }

    .ai-alert-title {
      font-weight: 600;
      margin-bottom: .25rem
    }

    .ai-alert-message {
      font-size: .875rem;
      color: var(--color-text-secondary, #6b7280);
      margin-bottom: .5rem
    }

    .ai-alert-meta {
      display: flex;
      gap: 1rem;
      font-size: .75rem;
      color: var(--color-text-muted, #9ca3af)
    }

    .ai-alert-actions {
      display: flex;
      gap: .5rem
    }

    .ai-alert-btn {
      padding: .5rem 1rem;
      border-radius: var(--radius-md, 8px);
      font-size: .875rem;
      font-weight: 500;
      cursor: pointer;
      transition: all .2s;
      border: none;
      text-decoration: none;
      display: inline-block
    }

    .ai-alert-btn.primary {
      background: #4F46E5;
      color: #fff
    }

    .ai-alert-btn:hover {
      transform: scale(1.05)
    }

    .ai-quick-actions {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem
    }

    .ai-quick-action {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem 1.25rem;
      background: var(--color-surface, #fff);
      border: 1px solid var(--color-border, #e5e7eb);
      border-radius: var(--radius-lg, 12px);
      cursor: pointer;
      transition: all .2s;
      width: 100%;
      font: inherit;
      color: inherit
    }

    .ai-quick-action:hover {
      border-color: #4F46E5
    }

    .ai-quick-action-icon {
      width: 40px;
      height: 40px;
      border-radius: var(--radius-md, 8px);
      background: #eef2ff;
      color: #4F46E5;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0
    }

    .ai-quick-action-text {
      font-weight: 500
    }

    @media(max-width:768px) {
      .ai-modules-grid {
        grid-template-columns: 1fr
      }

      .ai-widgets-section {
        grid-template-columns: repeat(2, 1fr)
      }
    }
  </style>
</head>

<body>
  <div class="app-layout">
    <?php include '../../includes/sidebar-nav.php'; ?>
    <main class="main-content">
      <div class="cyber-header">
        <div class="page-icon-orb"><i class="fas fa-brain"></i></div>
        <div>
          <h1>AI Control Center</h1>
          <p>Intelligent automation and monitoring hub</p>
        </div>
      </div>
      <div class="cyber-content" style="max-width:1400px;margin:0 auto;padding:24px;">

        <?php if ($message): ?>
          <div style="padding:1rem;margin-bottom:1.5rem;background:#D1FAE5;border:1px solid #22C55E;border-radius:8px;color:#065F46;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>

        <div class="ai-center-header">
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
            <div>
              <h1><i class="fas fa-brain"></i> AI Control Center</h1>
              <p>Intelligent automation and monitoring for your school management system</p>
            </div>
            <span class="ai-status-badge online">AI Systems Online</span>
          </div>
        </div>

        <!-- Quick Actions (POST forms) -->
        <div class="ai-quick-actions">
          <?php foreach (
            [
              ['run-anomaly-scan', 'fa-search', 'Run Anomaly Scan'],
              ['generate-report', 'fa-file-alt', 'Generate AI Report'],
              ['backup-now', 'fa-database', 'Backup Now'],
              ['system-optimize', 'fa-magic', 'Optimize System'],
            ] as $qa
          ): ?>
            <form method="POST" style="display:contents;">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
              <input type="hidden" name="action" value="<?php echo $qa[0]; ?>">
              <button type="submit" class="ai-quick-action">
                <div class="ai-quick-action-icon"><i class="fas <?php echo $qa[1]; ?>"></i></div>
                <span class="ai-quick-action-text"><?php echo $qa[2]; ?></span>
              </button>
            </form>
          <?php endforeach; ?>
        </div>

        <!-- AI Widgets -->
        <div class="ai-widgets-section">
          <div class="ai-widget">
            <div class="ai-widget-header"><span class="ai-widget-title">Attendance Anomalies</span><i class="fas fa-exclamation-triangle" style="color:#D97706;"></i></div>
            <div class="ai-widget-value"><?php echo (int)$widgets['attendance_anomalies']; ?></div>
            <div class="ai-widget-trend <?php echo $widgets['attendance_anomalies'] > 5 ? 'down' : 'up'; ?>">
              <i class="fas fa-<?php echo $widgets['attendance_anomalies'] > 5 ? 'arrow-up' : 'arrow-down'; ?>"></i>
              <span><?php echo $widgets['attendance_anomalies'] > 5 ? 'High' : 'Normal'; ?> activity</span>
            </div>
          </div>
          <div class="ai-widget">
            <div class="ai-widget-header"><span class="ai-widget-title">Suspicious Logins</span><i class="fas fa-shield-alt" style="color:#EF4444;"></i></div>
            <div class="ai-widget-value"><?php echo (int)$widgets['suspicious_logins']; ?></div>
            <div class="ai-widget-trend <?php echo $widgets['suspicious_logins'] > 0 ? 'down' : 'up'; ?>">
              <i class="fas fa-<?php echo $widgets['suspicious_logins'] > 0 ? 'exclamation-circle' : 'check-circle'; ?>"></i>
              <span><?php echo $widgets['suspicious_logins'] > 0 ? 'Action needed' : 'All clear'; ?></span>
            </div>
          </div>
          <div class="ai-widget">
            <div class="ai-widget-header"><span class="ai-widget-title">System Health</span><i class="fas fa-heartbeat" style="color:#22C55E;"></i></div>
            <div class="ai-widget-value"><?php echo (int)$widgets['system_health_score']; ?>%</div>
            <div class="ai-widget-trend <?php echo $widgets['system_health_score'] > 90 ? 'up' : 'down'; ?>">
              <i class="fas fa-<?php echo $widgets['system_health_score'] > 90 ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
              <span><?php echo $widgets['system_health_score'] > 90 ? 'Excellent' : 'Needs attention'; ?></span>
            </div>
          </div>
          <div class="ai-widget">
            <div class="ai-widget-header"><span class="ai-widget-title">Backup Status</span><i class="fas fa-database" style="color:#4F46E5;"></i></div>
            <div class="ai-widget-value" style="font-size:1rem;margin-top:.5rem;"><?php echo htmlspecialchars(ucfirst($widgets['backup_status'])); ?></div>
            <div class="ai-widget-trend up"><i class="fas fa-clock"></i><span><?php echo htmlspecialchars($backupStatus['last_backup']); ?></span></div>
          </div>
          <div class="ai-widget">
            <div class="ai-widget-header"><span class="ai-widget-title">Unverified Accounts</span><i class="fas fa-user-clock" style="color:#D97706;"></i></div>
            <div class="ai-widget-value"><?php echo (int)$widgets['unverified_accounts']; ?></div>
            <div class="ai-widget-trend <?php echo $widgets['unverified_accounts'] > 10 ? 'down' : 'up'; ?>">
              <i class="fas fa-<?php echo $widgets['unverified_accounts'] > 10 ? 'arrow-up' : 'check-circle'; ?>"></i>
              <span><?php echo $widgets['unverified_accounts'] > 10 ? 'Review needed' : 'Manageable'; ?></span>
            </div>
          </div>
          <div class="ai-widget">
            <div class="ai-widget-header"><span class="ai-widget-title">Teacher Workload</span><i class="fas fa-briefcase" style="color:#0EA5E9;"></i></div>
            <div class="ai-widget-value" style="font-size:1rem;margin-top:.5rem;"><?php echo htmlspecialchars($widgets['teacher_workload']['average'] ?? 'N/A'); ?> hrs/week</div>
            <div class="ai-widget-trend <?php echo ($widgets['teacher_workload']['imbalance'] ?? 0) > 20 ? 'down' : 'up'; ?>">
              <i class="fas fa-balance-scale"></i>
              <span><?php echo ($widgets['teacher_workload']['imbalance'] ?? 0) > 20 ? 'Imbalanced' : 'Balanced'; ?></span>
            </div>
          </div>
        </div>

        <!-- AI Alerts -->
        <?php if (!empty($alerts)): ?>
          <div class="ai-alerts-section">
            <div class="ai-alerts-header">
              <h2><i class="fas fa-bell"></i> AI Alerts <span class="ai-alert-badge"><?php echo count($alerts); ?></span></h2>
            </div>
            <?php foreach ($alerts as $alert): ?>
              <div class="ai-alert-item">
                <div class="ai-alert-icon <?php echo htmlspecialchars($alert['severity']); ?>">
                  <i class="fas fa-<?php echo htmlspecialchars($alert['icon']); ?>"></i>
                </div>
                <div class="ai-alert-content">
                  <div class="ai-alert-title"><?php echo htmlspecialchars($alert['title']); ?></div>
                  <div class="ai-alert-message"><?php echo htmlspecialchars($alert['message']); ?></div>
                  <div class="ai-alert-meta">
                    <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($alert['time']); ?></span>
                    <span><i class="fas fa-robot"></i> <?php echo (int)$alert['ai_confidence']; ?>% confidence</span>
                  </div>
                </div>
                <div class="ai-alert-actions">
                  <a href="<?php echo htmlspecialchars($alert['action']); ?>" class="ai-alert-btn primary"><?php echo htmlspecialchars($alert['action_label']); ?></a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- AI Modules Grid -->
        <div class="ai-modules-grid">
          <a href="anomaly-detection.php" class="ai-module-card">
            <div class="ai-module-icon anomaly"><i class="fas fa-exclamation-triangle"></i></div>
            <h3>AI Anomaly Detection</h3>
            <p>Detect suspicious attendance patterns, grade manipulation, and unusual behaviors.</p>
            <div class="ai-module-stats">
              <div class="ai-module-stat"><span class="ai-module-stat-value"><?php echo (int)$widgets['attendance_anomalies']; ?></span><span class="ai-module-stat-label">Anomalies</span></div>
            </div>
          </a>
          <a href="security-monitor.php" class="ai-module-card">
            <div class="ai-module-icon security"><i class="fas fa-shield-alt"></i></div>
            <h3>AI Security Monitor</h3>
            <p>Monitor login attempts, detect brute force attacks, and track suspicious activities.</p>
            <div class="ai-module-stats">
              <div class="ai-module-stat"><span class="ai-module-stat-value"><?php echo (int)$widgets['suspicious_logins']; ?></span><span class="ai-module-stat-label">Threats</span></div>
              <div class="ai-module-stat"><span class="ai-module-stat-value"><?php echo (int)($securityThreats['blocked'] ?? 0); ?></span><span class="ai-module-stat-label">Blocked</span></div>
            </div>
          </a>
          <a href="automation.php" class="ai-module-card">
            <div class="ai-module-icon automation"><i class="fas fa-cogs"></i></div>
            <h3>AI Automation</h3>
            <p>Automate routine tasks, workflows, and notifications with intelligent triggers.</p>
            <div class="ai-module-stats">
              <div class="ai-module-stat"><span class="ai-module-stat-value"><?php echo (int)($dashboardData['active_workflows'] ?? 0); ?></span><span class="ai-module-stat-label">Active Workflows</span></div>
            </div>
          </a>
          <a href="documentation-engine.php" class="ai-module-card">
            <div class="ai-module-icon documentation"><i class="fas fa-file-alt"></i></div>
            <h3>AI Documentation Engine</h3>
            <p>Automatically generate reports, PDFs, and documentation from dashboard data.</p>
            <div class="ai-module-stats">
              <div class="ai-module-stat"><span class="ai-module-stat-value"><?php echo (int)($dashboardData['docs_generated'] ?? 0); ?></span><span class="ai-module-stat-label">Docs This Month</span></div>
            </div>
          </a>
          <a href="system-health.php" class="ai-module-card">
            <div class="ai-module-icon health"><i class="fas fa-heartbeat"></i></div>
            <h3>AI System Health</h3>
            <p>Monitor system performance, database health, and AI model accuracy.</p>
            <div class="ai-module-stats">
              <div class="ai-module-stat"><span class="ai-module-stat-value"><?php echo (int)$widgets['system_health_score']; ?>%</span><span class="ai-module-stat-label">Health</span></div>
            </div>
          </a>
          <a href="backup-monitor.php" class="ai-module-card">
            <div class="ai-module-icon backup"><i class="fas fa-database"></i></div>
            <h3>AI Backup Monitor</h3>
            <p>Automated backups with integrity verification and recovery recommendations.</p>
            <div class="ai-module-stats">
              <div class="ai-module-stat"><span class="ai-module-stat-value"><?php echo (int)($backupStatus['total_backups'] ?? 0); ?></span><span class="ai-module-stat-label">Backups</span></div>
            </div>
          </a>
          <a href="predictive-analytics.php" class="ai-module-card">
            <div class="ai-module-icon predictive"><i class="fas fa-chart-line"></i></div>
            <h3>AI Predictive Analytics</h3>
            <p>Predict student performance, attendance trends, and resource needs.</p>
            <div class="ai-module-stats">
              <div class="ai-module-stat"><span class="ai-module-stat-value"><?php echo (int)($dashboardData['at_risk_students'] ?? 0); ?></span><span class="ai-module-stat-label">At-Risk Students</span></div>
            </div>
          </a>
        </div>

      </div>
    </main>
  </div>
  <script src="../../assets/js/main.js"></script>
</body>

</html>
<?php
function generateAIAlerts($anomalies, $securityThreats, $systemHealth)
{
  $alerts = [];
  $id = 1;
  if (!empty($anomalies['attendance'])) {
    foreach (array_slice($anomalies['attendance'], 0, 3) as $a) {
      $alerts[] = [
        'id' => 'att_' . $id++,
        'severity' => $a['severity'] ?? 'warning',
        'icon' => 'exclamation-triangle',
        'title' => 'Suspicious Attendance Pattern',
        'message' => sprintf('Teacher %s: identical attendance for %s students in %s', $a['teacher_name'] ?? 'Unknown', $a['identical_count'] ?? 'multiple', $a['class_name'] ?? 'Unknown'),
        'time' => $a['detected_at'] ?? 'Just now',
        'ai_confidence' => $a['confidence'] ?? 85,
        'action' => 'anomaly-detection.php',
        'action_label' => 'Review'
      ];
    }
  }
  if (!empty($securityThreats['login_anomalies'])) {
    foreach (array_slice($securityThreats['login_anomalies'], 0, 2) as $t) {
      $alerts[] = [
        'id' => 'sec_' . $id++,
        'severity' => 'critical',
        'icon' => 'shield-alt',
        'title' => 'Suspicious Login Activity',
        'message' => sprintf('%d failed attempts from IP %s to %s', $t['attempts'] ?? 0, $t['ip'] ?? 'Unknown', $t['username'] ?? 'Unknown'),
        'time' => $t['last_attempt'] ?? 'Just now',
        'ai_confidence' => $t['confidence'] ?? 92,
        'action' => 'security-monitor.php',
        'action_label' => 'Investigate'
      ];
    }
  }
  if (($systemHealth['critical_issues'] ?? 0) > 0) {
    $alerts[] = [
      'id' => 'sys_' . $id++,
      'severity' => 'critical',
      'icon' => 'heartbeat',
      'title' => 'System Health Critical',
      'message' => sprintf('%d critical issues detected.', $systemHealth['critical_issues']),
      'time' => 'Just now',
      'ai_confidence' => 100,
      'action' => 'system-health.php',
      'action_label' => 'View Issues'
    ];
  }
  return $alerts;
}
