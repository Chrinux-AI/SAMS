<?php

/**
 * ═══════════════════════════════════════════════════
 *  MASTER CONTROL CENTER (MCC)
 *  SAMS — God-Mode Dashboard
 *  Unified operational authority for the entire ASOS.
 * ═══════════════════════════════════════════════════
 */
require_once __DIR__ . '/../../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/router.php';
require_once BASE_PATH . '/app/bootstrap.php';

// ── Access Control: Developer / Super Admin only ──
SecurityGateway::guard([
  'require_auth' => true,
  'require_role' => ['admin', 'developer'],
]);

// Load MCC controllers
$controllerPath = __DIR__ . '/controllers/';
require_once $controllerPath . 'SystemController.php';
require_once $controllerPath . 'SecurityController.php';
require_once $controllerPath . 'AIController.php';
require_once $controllerPath . 'DevOpsController.php';
require_once $controllerPath . 'DatabaseController.php';
require_once $controllerPath . 'HealingController.php';
require_once $controllerPath . 'InstitutionController.php';
require_once $controllerPath . 'ACIController.php';
require_once $controllerPath . 'AICController.php';
require_once $controllerPath . 'OSController.php';

// Initial data load (server-side for first paint)
$system      = SystemController::getStatus();
$security    = SecurityController::getStatus();
$ai          = AIController::getStatus();
$devops      = DevOpsController::getStatus();
$database    = DatabaseController::getStatus();
$healing     = HealingController::getStatus();
$institution = InstitutionController::getStatus();
$aci         = ACIController::getStatus();
$aicData     = AICController::getStatus();
$osStatus    = OSController::getStatus();

$userName = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['email'] ?? 'Developer');
$baseUrl  = route('developer/master-control');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="mcc-base" content="<?= $baseUrl ?>">
  <title>Master Control Center — <?= htmlspecialchars(APP_NAME ?? 'SAMS') ?></title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/mcc.css">
</head>

<body class="mcc-body">

  <!-- ════════ TOP BAR ════════ -->
  <div class="mcc-topbar">
    <div style="display:flex;align-items:center;gap:16px">
      <h1><i class="fas fa-satellite-dish"></i> MASTER CONTROL CENTER</h1>
      <span id="sys-status-pill" class="mcc-status-pill <?= strtolower($system['system']) === 'stable' ? 'stable' : (strtolower($system['system']) === 'warning' ? 'warning' : 'critical') ?>">
        <?= htmlspecialchars($system['system']) ?>
      </span>
    </div>
    <div class="mcc-topbar-actions">
      <span style="color:var(--mcc-muted);font-size:0.75rem"><i class="fas fa-clock"></i> Refresh: <span id="last-refresh">--</span></span>
      <a href="<?= route('developer/') ?>"><i class="fas fa-arrow-left"></i> Dev Portal</a>
      <span style="color:var(--mcc-muted);font-size:0.75rem"><i class="fas fa-user-shield"></i> <?= $userName ?></span>
    </div>
  </div>

  <div class="mcc-container">

    <!-- ════════ ROW 1: SYSTEM | SECURITY | AI | DEVOPS ════════ -->
    <div class="mcc-grid">

      <!-- 1. SYSTEM STATUS -->
      <div class="mcc-card accent-green">
        <div class="mcc-card-header">
          <span class="mcc-card-title"><i class="fas fa-heartbeat"></i> System Status</span>
          <span class="pulse-dot green"></span>
        </div>
        <div class="mcc-score">
          <div class="mcc-score-value" id="sys-os-health" style="color:<?= $system['os_health'] >= 90 ? 'var(--mcc-green)' : ($system['os_health'] >= 50 ? 'var(--mcc-yellow)' : 'var(--mcc-red)') ?>"><?= $system['os_health'] ?></div>
          <div class="mcc-score-label">OS Health</div>
        </div>
        <div class="mcc-metric"><span class="mcc-metric-label">Active Users</span><span class="mcc-metric-value" id="sys-active-users"><?= $system['active_users'] ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Sessions</span><span class="mcc-metric-value" id="sys-sessions"><?= $system['active_sessions'] ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">DB Latency</span><span class="mcc-metric-value" id="sys-db-latency"><?= $system['db_latency_ms'] ?> ms</span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Errors/hr</span><span class="mcc-metric-value" id="sys-errors"><?= $system['errors_last_hour'] ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Memory</span><span class="mcc-metric-value" id="sys-memory"><?= $system['memory_mb'] ?> MB</span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Stability</span><span class="mcc-metric-value" id="sys-stability"><?= $system['stability_score'] ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">PHP</span><span class="mcc-metric-value" id="sys-php"><?= $system['php_version'] ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Server Time</span><span class="mcc-metric-value" id="sys-time"><?= $system['server_time'] ?></span></div>
      </div>

      <!-- 2. SECURITY COMMAND -->
      <div class="mcc-card accent-red">
        <div class="mcc-card-header">
          <span class="mcc-card-title"><i class="fas fa-shield-alt"></i> Security</span>
          <div class="mcc-score" style="padding:0">
            <div class="mcc-score-value" id="sec-score" style="font-size:1.6rem;color:<?= $security['security_score'] >= 90 ? 'var(--mcc-green)' : 'var(--mcc-yellow)' ?>"><?= $security['security_score'] ?></div>
          </div>
        </div>
        <div class="mcc-metric"><span class="mcc-metric-label">Failed Logins (24h)</span><span class="mcc-metric-value" id="sec-failed"><?= $security['failed_logins_24h'] ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Blocked IPs</span><span class="mcc-metric-value" id="sec-blocked"><?= $security['blocked_ips'] ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Rate Limit Hits</span><span class="mcc-metric-value" id="sec-ratelimit"><?= $security['rate_limit_hits'] ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Suspicious</span><span class="mcc-metric-value" id="sec-suspicious"><?= $security['suspicious_activity'] ?></span></div>
        <div class="mcc-btn-group">
          <button class="mcc-btn danger" data-label="Force Logout All" onclick="mccAction('restart-service.php',{service:'sessions'},this)"><i class="fas fa-sign-out-alt"></i> Force Logout All</button>
          <button class="mcc-btn" data-label="Maintenance ON" onclick="mccAction('restart-service.php',{service:'maintenance_on'},this)"><i class="fas fa-hard-hat"></i> Maintenance ON</button>
          <button class="mcc-btn success" data-label="Maintenance OFF" onclick="mccAction('restart-service.php',{service:'maintenance_off'},this)"><i class="fas fa-check"></i> Maintenance OFF</button>
        </div>
      </div>

      <!-- 3. AI CONTROL -->
      <div class="mcc-card accent-magenta">
        <div class="mcc-card-header">
          <span class="mcc-card-title"><i class="fas fa-robot"></i> AI Center</span>
          <div class="mcc-score" style="padding:0">
            <div class="mcc-score-value" id="ai-health" style="font-size:1.6rem;color:<?= $ai['ai_health'] >= 90 ? 'var(--mcc-green)' : 'var(--mcc-yellow)' ?>"><?= $ai['ai_health'] ?></div>
          </div>
        </div>
        <div class="mcc-metric"><span class="mcc-metric-label">Active Modules</span><span class="mcc-metric-value" id="ai-active"><?= $ai['active_count'] ?>/<?= $ai['total_modules'] ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Cache</span><span class="mcc-metric-value" id="ai-cache"><?= $ai['cache_files'] ?> (<?= $ai['cache_size_kb'] ?> KB)</span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Training Sets</span><span class="mcc-metric-value" id="ai-training"><?= $ai['training_sets'] ?> sets</span></div>
        <div id="ai-modules-list" style="margin-top:8px">
          <?php foreach ($ai['modules'] as $m): ?>
            <div class="mcc-metric">
              <span class="mcc-metric-label"><?= htmlspecialchars($m['name']) ?></span>
              <span class="mcc-badge <?= $m['active'] ? 'online' : 'offline' ?>"><?= htmlspecialchars($m['status']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="mcc-btn-group">
          <button class="mcc-btn" data-label="Clear AI Cache" onclick="mccAction('cache-clear.php',{target:'ai'},this)"><i class="fas fa-broom"></i> Clear AI Cache</button>
        </div>
      </div>

      <!-- 4. DEVOPS COMMAND -->
      <div class="mcc-card accent-orange">
        <div class="mcc-card-header">
          <span class="mcc-card-title"><i class="fas fa-rocket"></i> DevOps</span>
          <div class="mcc-score" style="padding:0">
            <div class="mcc-score-value" id="devops-score" style="font-size:1.6rem;color:<?= ($devops['devops_score'] ?? 0) >= 90 ? 'var(--mcc-green)' : 'var(--mcc-yellow)' ?>"><?= $devops['devops_score'] ?? 0 ?></div>
          </div>
        </div>
        <div class="mcc-metric"><span class="mcc-metric-label">Last Deployment</span><span class="mcc-metric-value" id="devops-deploy"><?= htmlspecialchars($devops['last_deployment'] ?? 'N/A') ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Cron Health</span><span class="mcc-metric-value" id="devops-cron"><?= htmlspecialchars($devops['cron_health'] ?? 'unknown') ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Backup Status</span><span class="mcc-metric-value" id="devops-backup"><?= htmlspecialchars($devops['backup_status'] ?? 'unknown') ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Fix Loop</span><span class="mcc-metric-value" id="devops-fixloop"><?= htmlspecialchars($devops['fix_loop_last'] ?? 'N/A') ?></span></div>
        <div class="mcc-btn-group">
          <button class="mcc-btn" data-label="Run Auto Fix" onclick="mccAction('repair-trigger.php',{action:'autofix'},this)"><i class="fas fa-wrench"></i> Run Auto Fix</button>
          <button class="mcc-btn" data-label="Rebuild Routes" onclick="mccAction('repair-trigger.php',{action:'routes'},this)"><i class="fas fa-route"></i> Rebuild Routes</button>
        </div>
      </div>

    </div><!-- /mcc-grid row 1 -->

    <!-- ════════ ROW 2: DATABASE | USERS/INSTITUTION | HEALING ════════ -->
    <div class="mcc-grid-3">

      <!-- 5. DATABASE -->
      <div class="mcc-card accent-cyan">
        <div class="mcc-card-header">
          <span class="mcc-card-title"><i class="fas fa-database"></i> Database</span>
          <span class="mcc-metric-value" style="font-size:0.75rem;color:var(--mcc-muted)" id="db-name"><?= htmlspecialchars($database['db_name'] ?? '') ?></span>
        </div>
        <div class="mcc-metric"><span class="mcc-metric-label">Tables</span><span class="mcc-metric-value" id="db-tables"><?= $database['total_tables'] ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Total Rows</span><span class="mcc-metric-value" id="db-rows"><?= number_format($database['total_rows']) ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Size</span><span class="mcc-metric-value" id="db-size"><?= $database['total_size_mb'] ?> MB</span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Warnings</span><span class="mcc-metric-value" id="db-warn-count"><?= count($database['schema_warnings'] ?? []) ?></span></div>

        <div style="margin-top:10px;max-height:120px;overflow-y:auto">
          <table class="mcc-table">
            <thead>
              <tr>
                <th>Table</th>
                <th>Rows</th>
                <th>Size</th>
              </tr>
            </thead>
            <tbody id="db-table-list">
              <?php foreach (array_slice($database['tables'] ?? [], 0, 10) as $t): ?>
                <tr>
                  <td><?= htmlspecialchars($t['name']) ?></td>
                  <td><?= number_format($t['rows']) ?></td>
                  <td><?= $t['size_kb'] ?> KB</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div id="db-warnings" style="margin-top:8px;max-height:80px;overflow-y:auto">
          <?php if (empty($database['schema_warnings'])): ?>
            <div class="mcc-event ok">No schema warnings</div>
          <?php else: ?>
            <?php foreach (array_slice($database['schema_warnings'], 0, 5) as $w): ?>
              <div class="mcc-event warn"><?= htmlspecialchars($w) ?></div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="mcc-btn-group">
          <button class="mcc-btn" data-label="Optimize Tables" onclick="mccAction('repair-trigger.php',{action:'optimize'},this)"><i class="fas fa-tachometer-alt"></i> Optimize</button>
          <button class="mcc-btn" data-label="Run Migrations" onclick="mccAction('repair-trigger.php',{action:'migrations'},this)"><i class="fas fa-sync"></i> Migrations</button>
        </div>
      </div>

      <!-- 6. INSTITUTIONAL STATE -->
      <div class="mcc-card accent-purple">
        <div class="mcc-card-header">
          <span class="mcc-card-title"><i class="fas fa-university"></i> Institution</span>
        </div>
        <div class="mcc-metric"><span class="mcc-metric-label">Academic Mode</span><span class="mcc-metric-value good" id="inst-academic"><?= htmlspecialchars($institution['academic_mode']) ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Attendance Window</span><span class="mcc-metric-value good" id="inst-attendance"><?= htmlspecialchars($institution['attendance_window']) ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Messaging</span><span class="mcc-metric-value" id="inst-messaging"><?= htmlspecialchars($institution['messaging_status']) ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">AI Services</span><span class="mcc-metric-value" id="inst-ai"><?= htmlspecialchars($institution['ai_services']) ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Active Users</span><span class="mcc-metric-value" id="inst-users"><?= $institution['total_users'] ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Maintenance</span><span class="mcc-metric-value" id="inst-maintenance"><?= $institution['maintenance_mode'] ? 'ON' : 'OFF' ?></span></div>
        <div id="inst-breakdown" style="margin-top:8px;border-top:1px solid rgba(255,255,255,0.05);padding-top:8px">
          <?php foreach ($institution['user_breakdown'] as $role => $cnt): ?>
            <div class="mcc-metric"><span class="mcc-metric-label"><?= htmlspecialchars($role) ?></span><span class="mcc-metric-value"><?= $cnt ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 7. HEALING ENGINE -->
      <div class="mcc-card accent-green">
        <div class="mcc-card-header">
          <span class="mcc-card-title"><i class="fas fa-medkit"></i> Healing Engine</span>
          <div class="mcc-score" style="padding:0">
            <div class="mcc-score-value" id="heal-score" style="font-size:1.6rem;color:<?= ($healing['stability_score'] ?? 0) >= 90 ? 'var(--mcc-green)' : 'var(--mcc-yellow)' ?>"><?= $healing['stability_score'] ?? 0 ?></div>
          </div>
        </div>
        <div class="mcc-metric"><span class="mcc-metric-label">Last Run</span><span class="mcc-metric-value" id="heal-lastrun"><?= htmlspecialchars($healing['last_run'] ?? 'Never') ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Repairs Today</span><span class="mcc-metric-value" id="heal-repairs"><?= $healing['repairs_today'] ?></span></div>
        <div class="mcc-metric"><span class="mcc-metric-label">Total Repairs</span><span class="mcc-metric-value" id="heal-total"><?= $healing['total_repairs'] ?></span></div>

        <div id="heal-log" class="mcc-event-stream" style="margin-top:10px;max-height:150px">
          <?php foreach (array_slice(array_reverse($healing['recent_repairs'] ?? []), 0, 10) as $line): ?>
            <div class="mcc-event <?= strpos($line, '[OK]') !== false ? 'ok' : (strpos($line, '[WARN]') !== false ? 'warn' : (strpos($line, '[ERR]') !== false ? 'err' : 'info')) ?>"><?= htmlspecialchars($line) ?></div>
          <?php endforeach; ?>
        </div>

        <div class="mcc-btn-group">
          <button class="mcc-btn success" data-label="Run Healing Cycle" onclick="mccAction('repair-trigger.php',{action:'healing'},this)"><i class="fas fa-heartbeat"></i> Run Healing Cycle</button>
        </div>
      </div>

    </div><!-- /mcc-grid-3 row 2 -->

    <!-- ════════ ROW 3: COMMAND INTELLIGENCE (ACI) ════════ -->
    <div class="mcc-card accent-purple" style="margin-bottom:16px">
      <div class="mcc-card-header">
        <span class="mcc-card-title"><i class="fas fa-brain"></i> Command Intelligence (ACI)</span>
        <span class="mcc-badge <?= ($aci['risk_level'] ?? 'LOW') === 'LOW' ? 'online' : (($aci['risk_level'] ?? 'LOW') === 'CRITICAL' ? 'offline' : 'warning') ?>">
          <?= htmlspecialchars($aci['risk_level'] ?? 'LOW') ?>
        </span>
      </div>

      <div class="mcc-grid" style="grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 12px">
        <div class="mcc-metric"><span class="mcc-metric-val" id="aci-signal"><?= $aci['signal_score'] ?? 100 ?></span><span class="mcc-metric-label">Signal Score</span></div>
        <div class="mcc-metric"><span class="mcc-metric-val" id="aci-predictions"><?= $aci['predictions'] ?? 0 ?></span><span class="mcc-metric-label">Predictions</span></div>
        <div class="mcc-metric"><span class="mcc-metric-val" id="aci-executed"><?= $aci['auto_executed'] ?? 0 ?></span><span class="mcc-metric-label">Auto-Executed</span></div>
        <div class="mcc-metric"><span class="mcc-metric-val" id="aci-cycle"><?= $aci['cycle_ms'] ?? 0 ?>ms</span><span class="mcc-metric-label">Cycle Time</span></div>
      </div>

      <div id="aci-recommendations" class="mcc-event-stream" style="max-height:180px;margin-bottom:12px">
        <?php if (empty($aci['recommendations'])): ?>
          <div class="mcc-event info">No active recommendations — system nominal</div>
        <?php else: ?>
          <?php foreach ($aci['recommendations'] as $rec): ?>
            <div class="mcc-event <?= strtolower($rec['severity'] ?? 'info') === 'critical' ? 'error' : (strtolower($rec['severity'] ?? 'info') === 'high' ? 'warning' : 'info') ?>">
              <i class="<?= htmlspecialchars($rec['icon'] ?? 'fas fa-info-circle') ?>"></i>
              <strong>[<?= htmlspecialchars($rec['confidence'] ?? '?') ?>%]</strong>
              <?= htmlspecialchars($rec['title'] ?? '') ?>
              <?php if (!($rec['auto_execute'] ?? false)): ?>
                <button class="mcc-btn small" onclick="mccAction('../api/aci/execute.php',{action:'<?= htmlspecialchars($rec['action'] ?? '', ENT_QUOTES) ?>'},this)" style="margin-left:8px;padding:2px 8px;font-size:0.65rem">
                  <?= htmlspecialchars($rec['action_label'] ?? 'Fix') ?>
                </button>
              <?php else: ?>
                <span style="color:var(--mcc-green);font-size:0.65rem;margin-left:8px">✓ auto-fixed</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="mcc-btn-group">
        <button class="mcc-btn" data-label="Run ACI Cycle" onclick="mccAction('../api/aci/cycle.php',{},this)"><i class="fas fa-play"></i> Run Cycle Now</button>
        <a href="<?= route('developer/aci-center.php') ?>" class="mcc-btn" style="text-decoration:none"><i class="fas fa-external-link-alt"></i> Full ACI Dashboard</a>
      </div>
    </div>

    <!-- ════════ ROW 4: INSTITUTION INTELLIGENCE (AIC) ════════ -->
    <div class="mcc-card accent-green" style="margin-bottom:16px">
      <div class="mcc-card-header">
        <span class="mcc-card-title"><i class="fas fa-university"></i> Institution Intelligence (AIC)</span>
        <span class="mcc-badge <?= ($aicData['health_score'] ?? 100) >= 80 ? 'online' : (($aicData['health_score'] ?? 100) >= 50 ? 'warning' : 'offline') ?>">
          Health: <?= $aicData['health_score'] ?? 100 ?>/100
        </span>
      </div>

      <div class="mcc-grid" style="grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 12px">
        <div class="mcc-metric"><span class="mcc-metric-val" id="aic-attendance"><?= $aicData['attendance']['overall_rate'] ?? 0 ?>%</span><span class="mcc-metric-label">Attendance</span></div>
        <div class="mcc-metric"><span class="mcc-metric-val" id="aic-workload"><?= $aicData['workload']['balance_score'] ?? 100 ?></span><span class="mcc-metric-label">Workload Balance</span></div>
        <div class="mcc-metric"><span class="mcc-metric-val" id="aic-engagement"><?= $aicData['engagement']['engagement_score'] ?? 100 ?>%</span><span class="mcc-metric-label">Engagement</span></div>
        <div class="mcc-metric"><span class="mcc-metric-val" id="aic-risks"><?= count($aicData['risk_alerts'] ?? []) ?></span><span class="mcc-metric-label">Risk Alerts</span></div>
        <div class="mcc-metric"><span class="mcc-metric-val" id="aic-efficiency"><?= $aicData['policy']['efficiency_score'] ?? 100 ?></span><span class="mcc-metric-label">Efficiency</span></div>
      </div>

      <div id="aic-alerts" class="mcc-event-stream" style="max-height:120px;margin-bottom:12px">
        <?php if (empty($aicData['risk_alerts'])): ?>
          <div class="mcc-event info">No risk alerts — institution operating normally</div>
        <?php else: ?>
          <?php foreach (array_slice($aicData['risk_alerts'], 0, 5) as $alert): ?>
            <div class="mcc-event <?= strtolower($alert['severity'] ?? 'info') === 'critical' ? 'error' : (strtolower($alert['severity'] ?? 'info') === 'high' ? 'warning' : 'info') ?>">
              <strong>[<?= htmlspecialchars($alert['source'] ?? '?') ?>]</strong>
              <?= htmlspecialchars($alert['message'] ?? '') ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="mcc-btn-group">
        <button class="mcc-btn" data-label="Run AIC Cycle" onclick="mccAction('../../api/aic/insights.php',{},this)"><i class="fas fa-play"></i> Run AIC Cycle</button>
        <a href="<?= route('developer/aic-center.php') ?>" class="mcc-btn" style="text-decoration:none"><i class="fas fa-external-link-alt"></i> Full AIC Dashboard</a>
      </div>
    </div>

    <!-- ════════ ROW 5: AUTONOMOUS SCHOOL OS ════════ -->
    <div class="mcc-card accent-pink" style="margin-bottom:16px">
      <div class="mcc-card-header">
        <span class="mcc-card-title"><i class="fas fa-microchip"></i> Autonomous School OS</span>
        <span class="mcc-badge <?= ($osStatus['os_health'] ?? 0) >= 80 ? 'online' : (($osStatus['os_health'] ?? 0) >= 50 ? 'warning' : 'offline') ?>">
          Health: <?= $osStatus['os_health'] ?? 0 ?>/100
        </span>
      </div>

      <div class="mcc-grid" style="grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 12px">
        <div class="mcc-metric"><span class="mcc-metric-val" id="os-health"><?= $osStatus['os_health'] ?? 0 ?></span><span class="mcc-metric-label">OS Health</span></div>
        <div class="mcc-metric"><span class="mcc-metric-val" id="os-phases"><?= $osStatus['phase_count'] ?? 0 ?></span><span class="mcc-metric-label">Phases</span></div>
        <div class="mcc-metric"><span class="mcc-metric-val" id="os-errors"><?= $osStatus['phase_errors'] ?? 0 ?></span><span class="mcc-metric-label">Phase Errors</span></div>
        <div class="mcc-metric"><span class="mcc-metric-val" id="os-duration"><?= $osStatus['duration'] ?? 0 ?>s</span><span class="mcc-metric-label">Cycle Time</span></div>
        <div class="mcc-metric"><span class="mcc-metric-val" id="os-boot"><?= $osStatus['boot_time'] ?? 0 ?>s</span><span class="mcc-metric-label">Boot Time</span></div>
      </div>

      <div class="mcc-event-stream" style="max-height:100px;margin-bottom:12px">
        <?php if ($osStatus['phase_errors'] === 0 && $osStatus['phase_count'] > 0): ?>
          <div class="mcc-event info">All <?= $osStatus['phase_count'] ?> OS phases nominal — <?= implode(', ', $osStatus['phases']) ?></div>
        <?php elseif ($osStatus['phase_count'] === 0): ?>
          <div class="mcc-event warning">OS kernel has not completed a cycle yet</div>
        <?php else: ?>
          <div class="mcc-event error"><?= $osStatus['phase_errors'] ?> phase error(s) detected in last cycle</div>
        <?php endif; ?>
        <?php if ($osStatus['last_cycle'] && $osStatus['last_cycle'] !== 'never' && $osStatus['last_cycle'] !== 'error'): ?>
          <div class="mcc-event info">Last cycle: <?= htmlspecialchars($osStatus['last_cycle']) ?></div>
        <?php endif; ?>
      </div>

      <div class="mcc-btn-group">
        <a href="<?= route('developer/os-center.php') ?>" class="mcc-btn" style="text-decoration:none"><i class="fas fa-external-link-alt"></i> Full OS Dashboard</a>
      </div>
    </div>

    <!-- ════════ LIVE EVENT STREAM ════════ -->
    <div class="mcc-card accent-cyan" style="margin-bottom:16px">
      <div class="mcc-card-header">
        <span class="mcc-card-title"><i class="fas fa-stream"></i> Live Event Stream</span>
        <span style="color:var(--mcc-muted);font-size:0.7rem">Auto-refreshes every 5s</span>
      </div>
      <div id="live-events" class="mcc-event-stream" style="max-height:200px">
        <div class="mcc-event info">Loading events...</div>
      </div>
    </div>

    <!-- ════════ EMERGENCY CONTROLS (GOD MODE) ════════ -->
    <div class="mcc-emergency">
      <div class="mcc-card-header">
        <span class="mcc-card-title"><i class="fas fa-exclamation-triangle"></i> Emergency Controls — God Mode</span>
        <span class="mcc-badge <?= SecurityController::isMaintenanceMode() ? 'offline' : 'online' ?>">
          <?= SecurityController::isMaintenanceMode() ? 'MAINTENANCE ACTIVE' : 'NORMAL OPERATION' ?>
        </span>
      </div>

      <div class="mcc-btn-group" style="justify-content:center;gap:16px;flex-wrap:wrap">
        <button class="mcc-btn danger" data-label="🔴 SYSTEM FREEZE" onclick="mccEmergency('activate')" style="min-width:200px">
          <i class="fas fa-ban"></i> SYSTEM FREEZE
        </button>
        <button class="mcc-btn success" data-label="🟢 LIFT LOCKDOWN" onclick="mccEmergency('deactivate')" style="min-width:200px">
          <i class="fas fa-unlock"></i> LIFT LOCKDOWN
        </button>
        <button class="mcc-btn" data-label="Global Cache Reset" onclick="mccAction('cache-clear.php',{target:'all'},this)" style="min-width:200px">
          <i class="fas fa-broom"></i> GLOBAL CACHE RESET
        </button>
        <button class="mcc-btn" data-label="Force Sync Panels" onclick="mccAction('restart-service.php',{service:'sync_panels'},this)" style="min-width:200px">
          <i class="fas fa-sync-alt"></i> FORCE SYNC ALL PANELS
        </button>
      </div>
    </div>

  </div><!-- /mcc-container -->

  <script src="<?= $baseUrl ?>/assets/mcc.js"></script>
</body>

</html>
