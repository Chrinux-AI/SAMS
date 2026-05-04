<?php

/**
 * SAMS AI Automation Module
 * Workflow automation and task scheduling interface
 */
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/database.php';
require_login('../../login.php');
if (!has_role('admin')) {
  redirect('../../login.php', 'Admin access required.', 'error');
}

$tenantId = $_SESSION['tenant_id'] ?? 1;
$message = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
  $action = $_POST['action'] ?? '';

  if ($action === 'toggle-workflow' && !empty($_POST['workflow_id'])) {
    $wfId = (int)$_POST['workflow_id'];
    try {
      require_once __DIR__ . '/../../includes/sams-init.php';
      $wf = new SAMS_AIWorkflowOrchestrator();
      $wf->toggleWorkflow($wfId);
      $message = "Workflow status updated.";
    } catch (Throwable $e) {
      $message = "Could not update workflow.";
    }
    try {
      log_activity($_SESSION['user_id'], 'ai_workflow_toggle', 'workflow', $wfId, 'Toggled workflow');
    } catch (Throwable $e) {
    }
  } elseif ($action === 'run-now' && !empty($_POST['workflow_id'])) {
    $wfId = (int)$_POST['workflow_id'];
    try {
      require_once __DIR__ . '/../../includes/sams-init.php';
      $wf = new SAMS_AIWorkflowOrchestrator();
      $result = $wf->executeWorkflow($wfId);
      $message = "Workflow executed. " . (int)($result['tasks_completed'] ?? 0) . " tasks completed.";
    } catch (Throwable $e) {
      $message = "Could not run workflow.";
    }
    try {
      log_activity($_SESSION['user_id'], 'ai_workflow_run', 'workflow', $wfId, 'Manual workflow execution');
    } catch (Throwable $e) {
    }
  } elseif ($action === 'create-workflow') {
    try {
      require_once __DIR__ . '/../../includes/sams-init.php';
      $wf = new SAMS_AIWorkflowOrchestrator();
      $workflowData = [
        'name' => $_POST['name'] ?? 'New Workflow',
        'trigger' => $_POST['trigger'] ?? 'manual',
        'actions' => $_POST['actions'] ?? [],
        'schedule' => $_POST['schedule'] ?? null
      ];
      $wf->createWorkflow($tenantId, $workflowData);
      $message = "Workflow '" . htmlspecialchars($_POST['name'] ?? 'New Workflow') . "' created.";
    } catch (Throwable $e) {
      $message = "Could not create workflow.";
    }
    try {
      log_activity($_SESSION['user_id'], 'ai_workflow_create', 'workflow', null, 'Created workflow');
    } catch (Throwable $e) {
    }
  }
}

// Safe defaults
$workflows = [];
$activeCount = 0;
$executionsToday = 0;
$automationStats = ['time_saved' => '0h', 'accuracy' => '99%'];

// Try AI service
try {
  require_once __DIR__ . '/../../includes/sams-init.php';
  try {
    $workflow = new SAMS_AIWorkflowOrchestrator();
    $workflows = $workflow->getWorkflows($tenantId);
    $activeCount = count(array_filter($workflows, fn($w) => ($w['status'] ?? '') === 'active'));
    $executionsToday = $workflow->getExecutionsCount($tenantId, 'today');
    $automationStats = array_merge($automationStats, $workflow->getAutomationStats($tenantId));
  } catch (Throwable $e) {
  }
} catch (Throwable $e) {
}

$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include '../../includes/favicon-loader.php'; ?>
  <script src="../../assets/js/theme-loader.js"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AI Automation - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/professional-ui.css">
    <?php include '../../includes/sams-head-bootstrap.php'; ?>

  <link rel="stylesheet" href="../../assets/css/sidebar-nav.css">
  <style>
    .automation-header {
      background: linear-gradient(135deg, #059669, #10B981);
      color: #fff;
      padding: 2rem;
      border-radius: var(--radius-xl, 16px);
      margin-bottom: 2rem
    }

    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem
    }

    .stat-box {
      background: var(--color-surface, #fff);
      border: 1px solid var(--color-border, #e5e7eb);
      border-radius: var(--radius-lg, 12px);
      padding: 1.5rem;
      text-align: center
    }

    .stat-box i {
      font-size: 2rem;
      color: #4F46E5;
      margin-bottom: .75rem;
      display: block
    }

    .stat-value {
      font-size: 2rem;
      font-weight: 700
    }

    .stat-label {
      font-size: .875rem;
      color: var(--color-text-secondary, #6b7280);
      margin-top: .25rem
    }

    .workflow-card {
      background: var(--color-surface, #fff);
      border: 1px solid var(--color-border, #e5e7eb);
      border-radius: var(--radius-lg, 12px);
      padding: 1.5rem;
      margin-bottom: 1rem;
      transition: all .2s
    }

    .workflow-card:hover {
      border-color: #4F46E5;
      box-shadow: 0 4px 12px rgba(0, 0, 0, .05)
    }

    .workflow-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 1rem;
      flex-wrap: wrap;
      gap: .5rem
    }

    .workflow-title {
      font-size: 1.125rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: .75rem
    }

    .workflow-status {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .375rem .75rem;
      border-radius: var(--radius-md, 8px);
      font-size: .75rem;
      font-weight: 600
    }

    .workflow-status.active {
      background: #D1FAE5;
      color: #059669
    }

    .workflow-status.paused {
      background: #F3F4F6;
      color: #6B7280
    }

    .workflow-meta {
      display: flex;
      gap: 1.5rem;
      font-size: .875rem;
      color: var(--color-text-secondary, #6b7280);
      margin-bottom: 1rem;
      flex-wrap: wrap
    }

    .workflow-actions-preview {
      background: var(--color-background-secondary, #f9fafb);
      border-radius: var(--radius-md, 8px);
      padding: 1rem;
      margin-bottom: 1rem
    }

    .workflow-actions-preview h4 {
      font-size: .875rem;
      font-weight: 600;
      margin-bottom: .75rem;
      color: var(--color-text-secondary, #6b7280)
    }

    .action-list {
      list-style: none;
      padding: 0;
      margin: 0
    }

    .action-list li {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .5rem 0;
      font-size: .875rem
    }

    .action-list li i {
      color: #059669
    }

    .workflow-controls {
      display: flex;
      gap: .75rem;
      flex-wrap: wrap
    }

    .btn-toggle,
    .btn-run {
      padding: .5rem 1rem;
      border-radius: var(--radius-md, 8px);
      font-size: .875rem;
      font-weight: 500;
      cursor: pointer;
      border: none;
      transition: all .2s
    }

    .btn-toggle {
      background: #F3F4F6;
      color: #6B7280
    }

    .btn-run {
      background: #4F46E5;
      color: #fff;
      display: inline-flex;
      align-items: center;
      gap: .5rem
    }

    .preset-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem
    }

    .preset-card {
      background: var(--color-surface, #fff);
      border: 2px dashed var(--color-border, #e5e7eb);
      border-radius: var(--radius-lg, 12px);
      padding: 1.5rem;
      cursor: pointer;
      transition: all .2s;
      text-align: center
    }

    .preset-card:hover {
      border-color: #4F46E5;
      background: #eef2ff
    }

    .preset-card i {
      font-size: 2.5rem;
      color: #4F46E5;
      margin-bottom: 1rem;
      display: block
    }

    .preset-card h3 {
      font-weight: 600;
      margin-bottom: .5rem
    }

    .preset-card p {
      font-size: .875rem;
      color: var(--color-text-secondary, #6b7280)
    }
  </style>
</head>

<body>
  <div class="app-layout">
    <?php include '../../includes/sidebar-nav.php'; ?>
    <main class="main-content">
      <div class="cyber-header">
        <div class="page-icon-orb"><i class="fas fa-cogs"></i></div>
        <div>
          <h1>AI Automation</h1>
          <p>Workflow automation and intelligent triggers</p>
        </div>
      </div>
      <div class="cyber-content" style="max-width:1400px;margin:0 auto;padding:24px;">

        <?php if ($message): ?>
          <div style="padding:1rem;margin-bottom:1.5rem;background:#D1FAE5;border:1px solid #22C55E;border-radius:8px;color:#065F46;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>

        <div class="automation-header">
          <h1><i class="fas fa-cogs"></i> AI Automation</h1>
          <p>Automate routine tasks, workflows, and notifications with intelligent triggers</p>
        </div>

        <!-- Stats -->
        <div class="stats-row">
          <div class="stat-box"><i class="fas fa-play-circle"></i>
            <div class="stat-value"><?php echo (int)$activeCount; ?></div>
            <div class="stat-label">Active Workflows</div>
          </div>
          <div class="stat-box"><i class="fas fa-check-double"></i>
            <div class="stat-value"><?php echo (int)$executionsToday; ?></div>
            <div class="stat-label">Executions Today</div>
          </div>
          <div class="stat-box"><i class="fas fa-clock"></i>
            <div class="stat-value"><?php echo htmlspecialchars($automationStats['time_saved'] ?? '0h'); ?></div>
            <div class="stat-label">Time Saved This Week</div>
          </div>
          <div class="stat-box"><i class="fas fa-robot"></i>
            <div class="stat-value"><?php echo htmlspecialchars($automationStats['accuracy'] ?? '99%'); ?></div>
            <div class="stat-label">Accuracy</div>
          </div>
        </div>

        <!-- Quick Presets -->
        <h2 style="margin-bottom:1rem;">Quick Start Presets</h2>
        <div class="preset-grid">
          <div class="preset-card"><i class="fas fa-bell"></i>
            <h3>Attendance Reminders</h3>
            <p>Remind teachers to mark attendance 30 min before class ends</p>
          </div>
          <div class="preset-card"><i class="fas fa-graduation-cap"></i>
            <h3>Grade Alerts</h3>
            <p>Notify parents when grades drop below threshold</p>
          </div>
          <div class="preset-card"><i class="fas fa-database"></i>
            <h3>Backup Cleanup</h3>
            <p>Auto-delete old backups and verify integrity weekly</p>
          </div>
          <div class="preset-card"><i class="fas fa-money-bill-wave"></i>
            <h3>Fee Reminders</h3>
            <p>Send payment reminders 3 days before due date</p>
          </div>
        </div>

        <!-- Workflows List -->
        <h2 style="margin-bottom:1rem;">Your Workflows</h2>
        <?php if (empty($workflows)): ?>
          <div style="text-align:center;padding:3rem;background:var(--color-surface,#fff);border-radius:var(--radius-lg,12px);">
            <i class="fas fa-cogs" style="font-size:3rem;color:var(--color-text-muted,#9ca3af);margin-bottom:1rem;display:block;"></i>
            <h3>No Workflows Yet</h3>
            <p style="color:var(--color-text-secondary,#6b7280);">Create your first automation workflow to get started</p>
          </div>
        <?php else: ?>
          <?php foreach ($workflows as $wf): ?>
            <div class="workflow-card">
              <div class="workflow-header">
                <div class="workflow-title">
                  <i class="fas fa-<?php echo htmlspecialchars($wf['icon'] ?? 'cog'); ?>" style="color:#4F46E5;"></i>
                  <?php echo htmlspecialchars($wf['name'] ?? 'Unnamed'); ?>
                </div>
                <span class="workflow-status <?php echo htmlspecialchars($wf['status'] ?? 'paused'); ?>">
                  <i class="fas fa-<?php echo ($wf['status'] ?? '') === 'active' ? 'play' : 'pause'; ?>"></i>
                  <?php echo htmlspecialchars(ucfirst($wf['status'] ?? 'Paused')); ?>
                </span>
              </div>
              <div class="workflow-meta">
                <span><i class="fas fa-bolt"></i> Trigger: <?php echo htmlspecialchars($wf['trigger_description'] ?? 'Manual'); ?></span>
                <span><i class="fas fa-clock"></i> Last run: <?php echo htmlspecialchars($wf['last_run'] ?? 'Never'); ?></span>
                <span><i class="fas fa-check-circle"></i> <?php echo (int)($wf['execution_count'] ?? 0); ?> runs</span>
              </div>
              <?php if (!empty($wf['actions'])): ?>
                <div class="workflow-actions-preview">
                  <h4>Actions</h4>
                  <ul class="action-list">
                    <?php foreach ($wf['actions'] as $act): ?>
                      <li><i class="fas fa-check"></i> <?php echo htmlspecialchars($act); ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>
              <div class="workflow-controls">
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="toggle-workflow">
                  <input type="hidden" name="workflow_id" value="<?php echo (int)($wf['id'] ?? 0); ?>">
                  <button type="submit" class="btn-toggle">
                    <i class="fas fa-<?php echo ($wf['status'] ?? '') === 'active' ? 'pause' : 'play'; ?>"></i>
                    <?php echo ($wf['status'] ?? '') === 'active' ? 'Pause' : 'Activate'; ?>
                  </button>
                </form>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="run-now">
                  <input type="hidden" name="workflow_id" value="<?php echo (int)($wf['id'] ?? 0); ?>">
                  <button type="submit" class="btn-run"><i class="fas fa-play"></i> Run Now</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

      </div>
    </main>
  </div>
  <script src="../../assets/js/main.js"></script>
</body>

</html>
