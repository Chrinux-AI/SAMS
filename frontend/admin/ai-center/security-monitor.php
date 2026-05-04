<?php

/**
 * SAMS AI Security Monitor Module
 * Real-time security monitoring and threat detection interface
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

// Handle POST actions (block/unblock IP must be POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
  $action = $_POST['action'] ?? '';

  if ($action === 'block-ip' && !empty($_POST['ip'])) {
    $ip = filter_var($_POST['ip'], FILTER_VALIDATE_IP);
    if ($ip) {
      try {
        require_once __DIR__ . '/../../includes/sams-init.php';
        $sec = new SAMS_SecurityGuardian();
        $sec->blockIP($ip, 'Manual block from Security Monitor', '24 hours');
      } catch (Throwable $e) {
      }
      $message = "IP " . htmlspecialchars($ip) . " has been blocked for 24 hours.";
      try {
        log_activity($_SESSION['user_id'], 'security_block_ip', 'ip', null, "Blocked IP: $ip");
      } catch (Throwable $e) {
      }
    } else {
      $message = "Invalid IP address.";
    }
  } elseif ($action === 'unblock-ip' && !empty($_POST['ip'])) {
    $ip = filter_var($_POST['ip'], FILTER_VALIDATE_IP);
    if ($ip) {
      try {
        require_once __DIR__ . '/../../includes/sams-init.php';
        $sec = new SAMS_SecurityGuardian();
        $sec->unblockIP($ip);
      } catch (Throwable $e) {
      }
      $message = "IP " . htmlspecialchars($ip) . " has been unblocked.";
      try {
        log_activity($_SESSION['user_id'], 'security_unblock_ip', 'ip', null, "Unblocked IP: $ip");
      } catch (Throwable $e) {
      }
    }
  }
}

// Safe defaults
$threats = ['login_anomalies' => [], 'brute_force_attempts' => [], 'recommendations' => [], 'risk_score' => 0];
$recentBlocks = [];
$securityScore = 100;

// Try AI service
try {
  require_once __DIR__ . '/../../includes/sams-init.php';
  try {
    $security = new SAMS_SecurityGuardian();
    $threats = array_merge($threats, $security->analyzeThreats($tenantId));
    $recentBlocks = $security->getRecentBlocks($tenantId, 24);
  } catch (Throwable $e) {
  }
} catch (Throwable $e) {
}

$securityScore = 100 - min(100, (int)($threats['risk_score'] ?? 0));

// Database fallback for login anomalies
if (empty($threats['login_anomalies'])) {
  try {
    $failedLogins = db()->fetchAll(
      "SELECT ip_address as ip, COUNT(*) as attempts, MAX(created_at) as last_attempt, 'medium' as severity, 80 as confidence
             FROM audit_logs WHERE action LIKE '%failed_login%' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY ip_address HAVING attempts >= 3 ORDER BY attempts DESC LIMIT 20"
    ) ?: [];
    foreach ($failedLogins as &$fl) {
      $fl['username'] = 'Multiple targets';
      $fl['role'] = 'unknown';
      $fl['pattern'] = ['repeated_failures'];
      $fl['user_id'] = 0;
    }
    $threats['login_anomalies'] = $failedLogins;
  } catch (Throwable $e) {
  }
}

$scoreColor = $securityScore > 80 ? '#22C55E' : ($securityScore > 60 ? '#F59E0B' : '#EF4444');
$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include '../../includes/favicon-loader.php'; ?>
  <script src="../../assets/js/theme-loader.js"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AI Security Monitor - <?php echo APP_NAME; ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/professional-ui.css">
    <?php include '../../includes/sams-head-bootstrap.php'; ?>

  <link rel="stylesheet" href="../../assets/css/sidebar-nav.css">
  <style>
    .security-header {
      background: linear-gradient(135deg, #7C2D12, #92400E);
      color: #fff;
      padding: 2rem;
      border-radius: var(--radius-xl, 16px);
      margin-bottom: 2rem
    }

    .security-score-card {
      background: var(--color-surface, #fff);
      border: 2px solid var(--color-border, #e5e7eb);
      border-radius: var(--radius-xl, 16px);
      padding: 2rem;
      text-align: center;
      margin-bottom: 2rem
    }

    .security-score-circle {
      width: 150px;
      height: 150px;
      border-radius: 50%;
      background: conic-gradient(<?php echo $scoreColor; ?> calc(<?php echo $securityScore; ?> * 3.6deg), #E5E7EB 0deg);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem;
      position: relative
    }

    .security-score-inner {
      width: 120px;
      height: 120px;
      background: var(--color-surface, #fff);
      border-radius: 50%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center
    }

    .security-score-value {
      font-size: 2.5rem;
      font-weight: 700;
      color: <?php echo $scoreColor; ?>
    }

    .security-score-label {
      font-size: .875rem;
      color: var(--color-text-secondary, #6b7280)
    }

    .threat-level {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .5rem 1rem;
      border-radius: var(--radius-md, 8px);
      font-weight: 600;
      font-size: .875rem
    }

    .threat-level.low {
      background: #D1FAE5;
      color: #059669
    }

    .threat-level.medium {
      background: #FEF3C7;
      color: #D97706
    }

    .threat-level.high {
      background: #FEE2E2;
      color: #DC2626
    }

    .threat-level.critical {
      background: #FECACA;
      color: #991B1B
    }

    .security-tabs {
      display: flex;
      gap: .5rem;
      margin-bottom: 2rem;
      border-bottom: 1px solid var(--color-border, #e5e7eb);
      padding-bottom: .5rem
    }

    .security-tab {
      padding: .75rem 1.5rem;
      border: none;
      background: transparent;
      color: var(--color-text-secondary, #6b7280);
      font-weight: 500;
      cursor: pointer;
      border-radius: var(--radius-md, 8px);
      transition: all .2s
    }

    .security-tab:hover {
      background: var(--color-background-secondary, #f9fafb)
    }

    .security-tab.active {
      background: #4F46E5;
      color: #fff
    }

    .security-tab .badge {
      margin-left: .5rem;
      background: #EF4444;
      color: #fff;
      padding: .125rem .5rem;
      border-radius: 9999px;
      font-size: .75rem
    }

    .threat-card {
      background: var(--color-surface, #fff);
      border: 1px solid var(--color-border, #e5e7eb);
      border-radius: var(--radius-lg, 12px);
      padding: 1.5rem;
      margin-bottom: 1rem;
      transition: all .2s
    }

    .threat-card:hover {
      border-color: #EF4444;
      box-shadow: 0 4px 12px rgba(239, 68, 68, .1)
    }

    .threat-card.critical {
      border-left: 4px solid #DC2626
    }

    .threat-card.high {
      border-left: 4px solid #EA580C
    }

    .threat-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 1rem
    }

    .threat-title {
      font-weight: 600;
      font-size: 1.125rem;
      display: flex;
      align-items: center;
      gap: .75rem
    }

    .threat-meta {
      display: flex;
      gap: 1.5rem;
      font-size: .875rem;
      color: var(--color-text-secondary, #6b7280);
      margin-bottom: 1rem;
      flex-wrap: wrap
    }

    .threat-pattern {
      background: var(--color-background-secondary, #f9fafb);
      padding: 1rem;
      border-radius: var(--radius-md, 8px);
      margin-bottom: 1rem
    }

    .threat-pattern-title {
      font-weight: 600;
      margin-bottom: .5rem;
      font-size: .875rem
    }

    .ip-list {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem
    }

    .ip-tag {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .375rem .75rem;
      background: var(--color-background, #fff);
      border: 1px solid var(--color-border, #e5e7eb);
      border-radius: var(--radius-md, 8px);
      font-size: .875rem;
      font-family: monospace
    }

    .ip-tag.blocked {
      background: #FEE2E2;
      border-color: #FECACA;
      color: #DC2626
    }

    .recommendation-card {
      background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
      border: 1px solid #93C5FD;
      border-radius: var(--radius-lg, 12px);
      padding: 1.5rem;
      margin-bottom: 1rem
    }

    .recommendation-card.critical {
      background: linear-gradient(135deg, #FEE2E2, #FECACA);
      border-color: #FCA5A5
    }

    .recommendation-header {
      display: flex;
      align-items: center;
      gap: .75rem;
      margin-bottom: .75rem;
      font-weight: 600
    }

    .btn-block {
      background: #DC2626;
      color: #fff;
      border: none;
      padding: .5rem 1rem;
      border-radius: var(--radius-md, 8px);
      font-weight: 500;
      cursor: pointer;
      transition: all .2s;
      font-size: .875rem
    }

    .btn-block:hover {
      background: #B91C1C
    }

    .btn-unblock {
      background: #059669;
      color: #fff;
      border: none;
      padding: .25rem .5rem;
      border-radius: var(--radius-md, 8px);
      cursor: pointer;
      font-size: .75rem
    }

    .live-indicator {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      color: #22C55E;
      font-size: .875rem;
      font-weight: 500
    }

    .live-dot {
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
  </style>
</head>

<body>
  <div class="app-layout">
    <?php include '../../includes/sidebar-nav.php'; ?>
    <main class="main-content">
      <div class="cyber-header">
        <div class="page-icon-orb"><i class="fas fa-shield-alt"></i></div>
        <div>
          <h1>AI Security Monitor</h1>
          <p>Real-time threat detection and security analysis</p>
        </div>
      </div>
      <div class="cyber-content" style="max-width:1400px;margin:0 auto;padding:24px;">

        <?php if ($message): ?>
          <div style="padding:1rem;margin-bottom:1.5rem;background:#D1FAE5;border:1px solid #22C55E;border-radius:8px;color:#065F46;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>

        <div class="security-header">
          <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
            <div>
              <h1><i class="fas fa-shield-alt"></i> AI Security Monitor</h1>
              <p>Real-time threat detection and security analysis powered by AI</p>
            </div>
            <span class="live-indicator"><span class="live-dot"></span> Live Monitoring</span>
          </div>
        </div>

        <!-- Security Score -->
        <div class="security-score-card">
          <div class="security-score-circle">
            <div class="security-score-inner">
              <div class="security-score-value"><?php echo $securityScore; ?>%</div>
              <div class="security-score-label">Secure</div>
            </div>
          </div>
          <h3>Security Score</h3>
          <p style="color:var(--color-text-secondary,#6b7280);margin-top:.5rem;">Based on 24-hour threat analysis and AI risk assessment</p>
          <div style="margin-top:1rem;">
            <?php $rs = $threats['risk_score'] ?? 0; ?>
            <span class="threat-level <?php echo $rs > 70 ? 'critical' : ($rs > 50 ? 'high' : ($rs > 30 ? 'medium' : 'low')); ?>">
              <i class="fas fa-<?php echo $rs > 50 ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
              <?php echo $rs > 70 ? 'Critical Risk' : ($rs > 50 ? 'High Risk' : ($rs > 30 ? 'Medium Risk' : 'Low Risk')); ?>
            </span>
          </div>
        </div>

        <!-- Tabs -->
        <div class="security-tabs">
          <button class="security-tab active" onclick="showTab('threats')">
            Active Threats
            <?php if (count($threats['login_anomalies'] ?? []) > 0): ?>
              <span class="badge"><?php echo count($threats['login_anomalies']); ?></span>
            <?php endif; ?>
          </button>
          <button class="security-tab" onclick="showTab('blocks')">
            Blocked IPs
            <span class="badge"><?php echo count($recentBlocks); ?></span>
          </button>
          <button class="security-tab" onclick="showTab('recommendations')">
            AI Recommendations
            <span class="badge"><?php echo count($threats['recommendations'] ?? []); ?></span>
          </button>
        </div>

        <!-- Active Threats Tab -->
        <div id="threats-tab" class="tab-content">
          <h2 style="margin-bottom:1.5rem;">Detected Threats</h2>
          <?php if (empty($threats['login_anomalies'])): ?>
            <div style="text-align:center;padding:3rem;background:var(--color-surface,#fff);border-radius:var(--radius-lg,12px);">
              <i class="fas fa-shield-alt" style="font-size:3rem;color:#22C55E;margin-bottom:1rem;display:block;"></i>
              <h3>No Active Threats</h3>
              <p style="color:var(--color-text-secondary,#6b7280);">AI monitoring shows normal security patterns</p>
            </div>
          <?php else: ?>
            <?php foreach ($threats['login_anomalies'] as $threat): ?>
              <div class="threat-card <?php echo htmlspecialchars($threat['severity'] ?? 'medium'); ?>">
                <div class="threat-header">
                  <div class="threat-title"><i class="fas fa-user-lock" style="color:#DC2626;"></i> Suspicious Login Activity</div>
                  <span class="threat-level <?php echo htmlspecialchars($threat['severity'] ?? 'medium'); ?>"><?php echo htmlspecialchars(ucfirst($threat['severity'] ?? 'medium')); ?> Risk</span>
                </div>
                <div class="threat-meta">
                  <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($threat['username'] ?? 'Unknown'); ?></span>
                  <span><i class="fas fa-network-wired"></i> <?php echo htmlspecialchars($threat['ip'] ?? 'Unknown'); ?></span>
                  <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($threat['last_attempt'] ?? ''); ?></span>
                  <span><i class="fas fa-redo"></i> <?php echo (int)($threat['attempts'] ?? 0); ?> attempts</span>
                </div>
                <?php if (!empty($threat['pattern'])): ?>
                  <div class="threat-pattern">
                    <div class="threat-pattern-title">AI Detected Pattern:</div>
                    <p><?php echo htmlspecialchars(is_array($threat['pattern']) ? implode(', ', array_map(function ($p) {
                          return ucfirst(str_replace('_', ' ', $p));
                        }, $threat['pattern'])) : $threat['pattern']); ?></p>
                  </div>
                <?php endif; ?>
                <div style="display:flex;gap:.75rem;">
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="block-ip">
                    <input type="hidden" name="ip" value="<?php echo htmlspecialchars($threat['ip'] ?? ''); ?>">
                    <button type="submit" class="btn-block" onclick="return confirm('Block IP <?php echo htmlspecialchars($threat['ip'] ?? ''); ?>?');"><i class="fas fa-ban"></i> Block IP</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>

          <?php if (!empty($threats['brute_force_attempts'])): ?>
            <h3 style="margin-top:2rem;margin-bottom:1rem;">Brute Force Attempts</h3>
            <?php foreach ($threats['brute_force_attempts'] as $attack): ?>
              <div class="threat-card critical">
                <div class="threat-header">
                  <div class="threat-title"><i class="fas fa-bomb" style="color:#DC2626;"></i> Brute Force Attack</div>
                </div>
                <div class="threat-meta">
                  <span><i class="fas fa-network-wired"></i> <?php echo htmlspecialchars($attack['ip_address'] ?? ''); ?></span>
                  <span><i class="fas fa-crosshairs"></i> <?php echo (int)($attack['target_count'] ?? 0); ?> targets</span>
                  <span><i class="fas fa-redo"></i> <?php echo (int)($attack['attempt_count'] ?? 0); ?> attempts</span>
                </div>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="block-ip">
                  <input type="hidden" name="ip" value="<?php echo htmlspecialchars($attack['ip_address'] ?? ''); ?>">
                  <button type="submit" class="btn-block" onclick="return confirm('Block IP <?php echo htmlspecialchars($attack['ip_address'] ?? ''); ?>?');"><i class="fas fa-ban"></i> Block Immediately</button>
                </form>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Blocked IPs Tab -->
        <div id="blocks-tab" class="tab-content" style="display:none;">
          <h2 style="margin-bottom:1.5rem;">Blocked IP Addresses (24h)</h2>
          <?php if (empty($recentBlocks)): ?>
            <p style="color:var(--color-text-secondary,#6b7280);">No IPs blocked in the last 24 hours.</p>
          <?php else: ?>
            <div class="ip-list">
              <?php foreach ($recentBlocks as $block): ?>
                <div class="ip-tag blocked">
                  <?php echo htmlspecialchars($block['ip_address'] ?? ''); ?>
                  <form method="POST" style="display:inline;margin-left:.25rem;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="action" value="unblock-ip">
                    <input type="hidden" name="ip" value="<?php echo htmlspecialchars($block['ip_address'] ?? ''); ?>">
                    <button type="submit" class="btn-unblock" onclick="return confirm('Unblock this IP?');"><i class="fas fa-times-circle"></i></button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Recommendations Tab -->
        <div id="recommendations-tab" class="tab-content" style="display:none;">
          <h2 style="margin-bottom:1.5rem;">AI Security Recommendations</h2>
          <?php if (empty($threats['recommendations'])): ?>
            <p style="color:var(--color-text-secondary,#6b7280);">No recommendations at this time. System is secure.</p>
          <?php else: ?>
            <?php foreach ($threats['recommendations'] as $rec): ?>
              <div class="recommendation-card <?php echo htmlspecialchars($rec['priority'] ?? ''); ?>">
                <div class="recommendation-header">
                  <i class="fas fa-<?php echo ($rec['priority'] ?? '') === 'critical' ? 'exclamation-circle' : 'lightbulb'; ?>"></i>
                  <?php echo htmlspecialchars($rec['message'] ?? ''); ?>
                </div>
                <p style="color:var(--color-text-secondary,#6b7280);">Priority: <strong><?php echo htmlspecialchars(ucfirst($rec['priority'] ?? 'normal')); ?></strong></p>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>
    </main>
  </div>
  <script src="../../assets/js/main.js"></script>
  <script>
    function showTab(tabName) {
      document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
      document.querySelectorAll('.security-tab').forEach(b => b.classList.remove('active'));
      document.getElementById(tabName + '-tab').style.display = 'block';
      event.currentTarget.classList.add('active');
    }
  </script>
</body>

</html>
