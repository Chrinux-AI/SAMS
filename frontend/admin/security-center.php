<?php

/**
 * SAMS Security Center — Live security dashboard for administrators.
 * Displays: active sessions, risk scores, blocked attacks, anomaly alerts, AI threat decisions.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin('../login.php');

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? ($_SESSION['first_name'] ?? 'Admin') . ' ' . ($_SESSION['last_name'] ?? '');

// Gather security data
$threatSummary = class_exists('SecurityAI') ? SecurityAI::getThreatSummary(24) : ['critical' => 0, 'suspicious' => 0, 'total' => 0];
$activeSessions = class_exists('SessionIntelligence') ? SessionIntelligence::getAllActiveSessions(50) : [];
$recentThreats = class_exists('SecurityAI') ? SecurityAI::getRecentThreats(20) : [];
$riskEvents = class_exists('BehaviorMonitor') ? BehaviorMonitor::getRecentRiskEvents(20) : [];
$activeDefenses = class_exists('AutoDefense') ? AutoDefense::getActiveDefenses() : ['ip_bans' => [], 'locked_accounts' => [], 'forensic_snapshots' => []];
$forensicsSummary = class_exists('AdminForensics') ? AdminForensics::getSummary(24) : ['total_actions' => 0, 'high_risk' => 0, 'by_type' => [], 'top_admins' => []];
$promptStats = class_exists('PromptFirewall') ? PromptFirewall::getStats(24) : ['total' => 0, 'by_severity' => []];
$busEvents = class_exists('SecurityEventBus') ? SecurityEventBus::getRecentEvents(20) : [];

// Count active threats
$activeBans = count($activeDefenses['ip_bans']);
$lockedAccounts = count($activeDefenses['locked_accounts']);
$totalActiveSessions = count($activeSessions);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <script src="../assets/js/theme-loader.js"></script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Security Center - <?php echo APP_NAME; ?></title>
  <link rel="manifest" href="/attendance/manifest.json">
  <meta name="theme-color" content="#4F46E5">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="../assets/css/professional-ui.css" rel="stylesheet">
  <style>
    .security-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }

    .sec-stat {
      background: var(--card-bg);
      border-radius: 16px;
      padding: 1.5rem;
      border: 1px solid var(--border-color);
    }

    .sec-stat .icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      margin-bottom: .75rem;
    }

    .sec-stat .label {
      font-size: .85rem;
      color: var(--text-muted);
      margin-bottom: .25rem;
    }

    .sec-stat .value {
      font-size: 2rem;
      font-weight: 800;
    }

    .icon-green {
      background: rgba(16, 185, 129, .12);
      color: #10B981;
    }

    .icon-red {
      background: rgba(239, 68, 68, .12);
      color: #EF4444;
    }

    .icon-yellow {
      background: rgba(245, 158, 11, .12);
      color: #F59E0B;
    }

    .icon-blue {
      background: rgba(59, 130, 246, .12);
      color: #3B82F6;
    }

    .icon-purple {
      background: rgba(139, 92, 246, .12);
      color: #8B5CF6;
    }

    .sec-table {
      width: 100%;
      border-collapse: collapse;
    }

    .sec-table th {
      text-align: left;
      padding: .75rem 1rem;
      font-size: .8rem;
      text-transform: uppercase;
      color: var(--text-muted);
      border-bottom: 1px solid var(--border-color);
    }

    .sec-table td {
      padding: .75rem 1rem;
      border-bottom: 1px solid var(--border-color);
      font-size: .9rem;
    }

    .badge {
      display: inline-block;
      padding: 2px 10px;
      border-radius: 999px;
      font-size: .75rem;
      font-weight: 600;
    }

    .badge-critical {
      background: #FEE2E2;
      color: #DC2626;
    }

    .badge-suspicious {
      background: #FEF3C7;
      color: #D97706;
    }

    .badge-normal {
      background: #D1FAE5;
      color: #059669;
    }

    .badge-info {
      background: #DBEAFE;
      color: #2563EB;
    }

    .panel {
      background: var(--card-bg);
      border-radius: 16px;
      border: 1px solid var(--border-color);
      margin-bottom: 2rem;
      overflow: hidden;
    }

    .panel-header {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .panel-header h3 {
      font-size: 1.05rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: .5rem;
    }

    .panel-body {
      padding: 1.5rem;
      overflow-x: auto;
    }

    .two-col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2rem;
    }

    @media (max-width: 900px) {
      .two-col {
        grid-template-columns: 1fr;
      }
    }

    .empty-state {
      text-align: center;
      padding: 2rem;
      color: var(--text-muted);
      font-size: .9rem;
    }

    .risk-bar {
      height: 6px;
      border-radius: 3px;
      background: var(--border-color);
      overflow: hidden;
    }

    .risk-bar-fill {
      height: 100%;
      border-radius: 3px;
      transition: width .3s;
    }
  </style>
</head>

<body>
  <div class="app-layout">
    <?php include '../includes/sidebar-nav.php'; ?>

    <main class="main-content">
      <header class="top-header">
        <div class="page-title-area">
          <button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active'); document.querySelector('.sidebar-overlay').classList.toggle('active');">
            <i class="fas fa-bars"></i>
          </button>
          <div class="page-icon"><i class="fas fa-shield-halved"></i></div>
          <div>
            <h1>Security Center</h1>
            <p class="page-subtitle">Zero-Trust Security Monitoring — Phase 3</p>
          </div>
        </div>
        <div class="header-actions">
          <div class="datetime-display">
            <div class="date-text"><?php echo date('l, M j, Y'); ?></div>
            <div class="time-text" id="live-time"><?php echo date('h:i A'); ?></div>
          </div>
          <div class="header-user">
            <div class="avatar"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
            <div>
              <div class="user-name"><?php echo htmlspecialchars($full_name); ?></div>
              <div class="user-role">Administrator</div>
            </div>
          </div>
        </div>
      </header>

      <div class="content-wrapper fade-in">

        <!-- Security Stats Grid -->
        <div class="security-grid">
          <div class="sec-stat">
            <div class="icon icon-blue"><i class="fas fa-users"></i></div>
            <div class="label">Active Sessions</div>
            <div class="value"><?php echo $totalActiveSessions; ?></div>
          </div>
          <div class="sec-stat">
            <div class="icon icon-red"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="label">Threats (24h)</div>
            <div class="value"><?php echo $threatSummary['total']; ?></div>
          </div>
          <div class="sec-stat">
            <div class="icon icon-yellow"><i class="fas fa-ban"></i></div>
            <div class="label">IP Bans Active</div>
            <div class="value"><?php echo $activeBans; ?></div>
          </div>
          <div class="sec-stat">
            <div class="icon icon-purple"><i class="fas fa-lock"></i></div>
            <div class="label">Locked Accounts</div>
            <div class="value"><?php echo $lockedAccounts; ?></div>
          </div>
          <div class="sec-stat">
            <div class="icon icon-green"><i class="fas fa-robot"></i></div>
            <div class="label">AI Prompt Blocks (24h)</div>
            <div class="value"><?php echo $promptStats['total']; ?></div>
          </div>
          <div class="sec-stat">
            <div class="icon icon-red"><i class="fas fa-gavel"></i></div>
            <div class="label">High-Risk Admin Actions</div>
            <div class="value"><?php echo $forensicsSummary['high_risk']; ?></div>
          </div>
        </div>

        <!-- Two-column layout -->
        <div class="two-col">

          <!-- Recent Threat Detections -->
          <div class="panel">
            <div class="panel-header">
              <h3><i class="fas fa-crosshairs"></i> AI Threat Detections</h3>
              <span class="badge badge-critical"><?php echo $threatSummary['critical']; ?> Critical</span>
            </div>
            <div class="panel-body">
              <?php if (empty($recentThreats)): ?>
                <div class="empty-state"><i class="fas fa-check-circle" style="color:#10B981;font-size:2rem;"></i><br>No threats detected</div>
              <?php else: ?>
                <table class="sec-table">
                  <thead>
                    <tr>
                      <th>User</th>
                      <th>Severity</th>
                      <th>Time</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach (array_slice($recentThreats, 0, 10) as $t): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($t['full_name'] ?? 'System'); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($t['severity'] ?? 'info'); ?>"><?php echo htmlspecialchars($t['severity'] ?? 'unknown'); ?></span></td>
                        <td><?php echo date('M j, g:i A', strtotime($t['created_at'])); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
          </div>

          <!-- Behavior Risk Events -->
          <div class="panel">
            <div class="panel-header">
              <h3><i class="fas fa-user-shield"></i> Behavior Risk Events</h3>
            </div>
            <div class="panel-body">
              <?php if (empty($riskEvents)): ?>
                <div class="empty-state"><i class="fas fa-thumbs-up" style="color:#10B981;font-size:2rem;"></i><br>All behavior normal</div>
              <?php else: ?>
                <table class="sec-table">
                  <thead>
                    <tr>
                      <th>User</th>
                      <th>Level</th>
                      <th>Score</th>
                      <th>Time</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach (array_slice($riskEvents, 0, 10) as $r):
                      $details = json_decode($r['details'] ?? '{}', true);
                      $score = $details['risk_score'] ?? 0;
                      $barColor = $score > 80 ? '#EF4444' : ($score > 60 ? '#F59E0B' : ($score > 30 ? '#3B82F6' : '#10B981'));
                    ?>
                      <tr>
                        <td><?php echo htmlspecialchars($r['full_name'] ?? 'Unknown'); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($r['severity'] ?? 'info'); ?>"><?php echo htmlspecialchars($details['level'] ?? $r['severity'] ?? 'unknown'); ?></span></td>
                        <td>
                          <div style="display:flex;align-items:center;gap:.5rem;">
                            <span style="font-weight:600;"><?php echo $score; ?></span>
                            <div class="risk-bar" style="flex:1;">
                              <div class="risk-bar-fill" style="width:<?php echo $score; ?>%;background:<?php echo $barColor; ?>;"></div>
                            </div>
                          </div>
                        </td>
                        <td><?php echo date('M j, g:i A', strtotime($r['created_at'])); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Active Sessions -->
        <div class="panel">
          <div class="panel-header">
            <h3><i class="fas fa-desktop"></i> Active Sessions</h3>
            <span class="badge badge-info"><?php echo $totalActiveSessions; ?> online</span>
          </div>
          <div class="panel-body">
            <?php if (empty($activeSessions)): ?>
              <div class="empty-state">No active sessions</div>
            <?php else: ?>
              <table class="sec-table">
                <thead>
                  <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>IP</th>
                    <th>Browser</th>
                    <th>Platform</th>
                    <th>Risk</th>
                    <th>Last Activity</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($activeSessions as $s):
                    $riskColor = ($s['risk_score'] ?? 0) > 60 ? '#EF4444' : (($s['risk_score'] ?? 0) > 30 ? '#F59E0B' : '#10B981');
                  ?>
                    <tr>
                      <td><?php echo htmlspecialchars($s['full_name'] ?? 'Unknown'); ?></td>
                      <td><span class="badge badge-info"><?php echo htmlspecialchars($s['role'] ?? '-'); ?></span></td>
                      <td><code><?php echo htmlspecialchars($s['ip_address'] ?? '-'); ?></code></td>
                      <td><?php echo htmlspecialchars($s['browser'] ?? '-'); ?></td>
                      <td><?php echo htmlspecialchars($s['platform'] ?? '-'); ?></td>
                      <td>
                        <span style="color:<?php echo $riskColor; ?>;font-weight:700;"><?php echo (int) ($s['risk_score'] ?? 0); ?></span>
                      </td>
                      <td><?php echo date('g:i A', strtotime($s['last_activity'])); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>

        <!-- Two-column: Active Defenses + Security Event Bus -->
        <div class="two-col">

          <!-- Active Defenses -->
          <div class="panel">
            <div class="panel-header">
              <h3><i class="fas fa-shield-virus"></i> Active Defenses</h3>
            </div>
            <div class="panel-body">
              <?php if ($activeBans === 0 && $lockedAccounts === 0): ?>
                <div class="empty-state"><i class="fas fa-peace" style="color:#10B981;font-size:2rem;"></i><br>No active defenses</div>
              <?php else: ?>
                <?php if (!empty($activeDefenses['ip_bans'])): ?>
                  <h4 style="margin-bottom:.75rem;font-size:.9rem;color:var(--text-muted);">IP Bans</h4>
                  <table class="sec-table">
                    <thead>
                      <tr>
                        <th>IP Address</th>
                        <th>Reason</th>
                        <th>Expires</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($activeDefenses['ip_bans'] as $ban): ?>
                        <tr>
                          <td><code><?php echo htmlspecialchars($ban['ip_address']); ?></code></td>
                          <td><?php echo htmlspecialchars(mb_substr($ban['reason'] ?? '', 0, 60)); ?></td>
                          <td><?php echo date('M j, g:i A', strtotime($ban['expires_at'])); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                <?php endif; ?>
                <?php if (!empty($activeDefenses['locked_accounts'])): ?>
                  <h4 style="margin:.75rem 0;font-size:.9rem;color:var(--text-muted);">Locked Accounts</h4>
                  <table class="sec-table">
                    <thead>
                      <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Locked Until</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($activeDefenses['locked_accounts'] as $lock): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($lock['full_name']); ?></td>
                          <td><?php echo htmlspecialchars($lock['email']); ?></td>
                          <td><?php echo date('M j, g:i A', strtotime($lock['locked_until'])); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Security Event Bus -->
          <div class="panel">
            <div class="panel-header">
              <h3><i class="fas fa-satellite-dish"></i> Security Event Feed</h3>
            </div>
            <div class="panel-body">
              <?php if (empty($busEvents)): ?>
                <div class="empty-state"><i class="fas fa-broadcast-tower" style="color:#3B82F6;font-size:2rem;"></i><br>No recent security events</div>
              <?php else: ?>
                <table class="sec-table">
                  <thead>
                    <tr>
                      <th>Event</th>
                      <th>Severity</th>
                      <th>User</th>
                      <th>Time</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach (array_slice($busEvents, 0, 10) as $e): ?>
                      <tr>
                        <td><?php echo htmlspecialchars(str_replace('bus_', '', $e['event_type'] ?? '')); ?></td>
                        <td><span class="badge badge-<?php echo htmlspecialchars($e['severity'] ?? 'info'); ?>"><?php echo htmlspecialchars($e['severity'] ?? '-'); ?></span></td>
                        <td><?php echo htmlspecialchars($e['full_name'] ?? 'System'); ?></td>
                        <td><?php echo date('g:i A', strtotime($e['created_at'])); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Admin Forensics Summary -->
        <div class="panel">
          <div class="panel-header">
            <h3><i class="fas fa-fingerprint"></i> Admin Action Forensics (24h)</h3>
            <span class="badge badge-info"><?php echo $forensicsSummary['total_actions']; ?> actions</span>
          </div>
          <div class="panel-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;">
              <div>
                <h4 style="margin-bottom:.75rem;font-size:.9rem;color:var(--text-muted);">Actions by Type</h4>
                <?php if (empty($forensicsSummary['by_type'])): ?>
                  <p style="color:var(--text-muted);font-size:.9rem;">No admin actions recorded</p>
                <?php else: ?>
                  <?php foreach ($forensicsSummary['by_type'] as $t): ?>
                    <div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid var(--border-color);">
                      <span style="font-size:.9rem;"><?php echo htmlspecialchars($t['action_type']); ?></span>
                      <span style="font-weight:700;"><?php echo (int) $t['cnt']; ?></span>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <div>
                <h4 style="margin-bottom:.75rem;font-size:.9rem;color:var(--text-muted);">Top Admins</h4>
                <?php if (empty($forensicsSummary['top_admins'])): ?>
                  <p style="color:var(--text-muted);font-size:.9rem;">No data</p>
                <?php else: ?>
                  <?php foreach ($forensicsSummary['top_admins'] as $a): ?>
                    <div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid var(--border-color);">
                      <span style="font-size:.9rem;"><?php echo htmlspecialchars($a['full_name'] ?? 'Admin #' . $a['admin_id']); ?></span>
                      <span style="font-weight:700;"><?php echo (int) $a['action_count']; ?></span>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <script>
    setInterval(() => {
      const el = document.getElementById('live-time');
      if (el) el.textContent = new Date().toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
      });
    }, 1000);

    // Auto-refresh every 30 seconds
    setTimeout(() => location.reload(), 30000);
  </script>
  <script src="../assets/js/main.js"></script>
</body>

</html>
