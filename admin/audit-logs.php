<?php

/**
 * Audit Logs & Activity Monitoring
 * Comprehensive activity tracking with filtering, search, and real-time monitoring
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$full_name = $_SESSION['full_name'];

// Filters
$action_filter = $_GET['action'] ?? '';
$user_filter = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';

// Build query
$where = [];
$params = [];

if ($action_filter) {
    $where[] = 'al.action = ?';
    $params[] = $action_filter;
}

if ($user_filter) {
    $where[] = 'al.user_id = ?';
    $params[] = $user_filter;
}

if ($date_from && $date_to) {
    $where[] = 'DATE(al.created_at) BETWEEN ? AND ?';
    $params[] = $date_from;
    $params[] = $date_to;
}

if ($search) {
    $where[] = '(al.description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get audit logs
$logs = db()->fetchAll("
    SELECT al.*,
           CONCAT(u.first_name, ' ', u.last_name) as user_name,
           u.email, u.role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    {$where_clause}
    ORDER BY al.created_at DESC
    LIMIT 100
", $params);

// Get statistics
$stats = [
    'total_logs' => db()->count('audit_logs'),
    'today_logs' => db()->count('audit_logs', 'DATE(created_at) = CURDATE()'),
    'unique_users_today' => db()->fetchOne("SELECT COUNT(DISTINCT user_id) as count FROM audit_logs WHERE DATE(created_at) = CURDATE()")['count'],
    'failed_logins_today' => db()->count('audit_logs', 'action = ? AND DATE(created_at) = CURDATE()', ['failed_login'])
];

// Get action types for filter
$action_types = db()->fetchAll("SELECT DISTINCT action FROM audit_logs ORDER BY action");

// Get recent users for filter
$recent_users = db()->fetchAll("
    SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) as name
    FROM users u
    INNER JOIN audit_logs al ON u.id = al.user_id
    ORDER BY name
    LIMIT 50
");

// Detect anomalies (multiple failed logins)
$anomalies = db()->fetchAll("
    SELECT user_id, COUNT(*) as attempts,
           MAX(created_at) as last_attempt,
           u.first_name, u.last_name, u.email
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE action = 'failed_login'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    GROUP BY user_id
    HAVING attempts >= 3
    ORDER BY attempts DESC
");

$page_title = 'Audit Logs & Activity Monitor';
$page_icon = 'clipboard-list';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
        <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        .filter-bar {
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.05), rgba(138, 43, 226, 0.05));
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .log-entry {
            background: rgba(0, 191, 255, 0.03);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 12px;
            transition: all 0.3s;
        }

        .log-entry:hover {
            border-color: var(--cyber-cyan);
            box-shadow: 0 0 15px rgba(0, 191, 255, 0.2);
        }

        .action-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .action-login {
            background: rgba(0, 255, 127, 0.2);
            color: var(--neon-green);
        }

        .action-logout {
            background: rgba(100, 100, 100, 0.2);
            color: rgba(255, 255, 255, 0.7);
        }

        .action-create {
            background: rgba(0, 191, 255, 0.2);
            color: var(--cyber-cyan);
        }

        .action-update {
            background: rgba(255, 165, 0, 0.2);
            color: var(--golden-pulse);
        }

        .action-delete {
            background: rgba(255, 69, 0, 0.2);
            color: var(--cyber-red);
        }

        .action-failed_login {
            background: rgba(255, 0, 0, 0.2);
            color: #ff4444;
        }

        .anomaly-alert {
            background: rgba(255, 69, 0, 0.1);
            border: 1px solid var(--cyber-red);
            border-left-width: 4px;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                box-shadow: 0 0 10px rgba(255, 69, 0, 0.3);
            }

            50% {
                box-shadow: 0 0 20px rgba(255, 69, 0, 0.6);
            }
        }
    </style>
</head>
<body>
    
    

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <button onclick="exportLogs()" class="cyber-btn">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <button onclick="location.reload()" class="cyber-btn primary">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <div class="user-card" style="padding:8px 15px;margin:0;">
                        <div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;">
                            <?php echo strtoupper(substr($full_name, 0, 2)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="user-role">Administrator</div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="cyber-content slide-in">
                <!-- Statistics Cards -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px;">
                    <div class="holo-card" style="text-align:center;">
                        <i class="fas fa-clipboard-list" style="font-size:2.5rem;color:var(--cyber-cyan);margin-bottom:10px;"></i>
                        <div style="font-size:2.5rem;font-weight:900;color:var(--cyber-cyan);"><?php echo number_format($stats['total_logs']); ?></div>
                        <div style="color:var(--text-muted);">Total Logs</div>
                    </div>
                    <div class="holo-card" style="text-align:center;">
                        <i class="fas fa-calendar-day" style="font-size:2.5rem;color:var(--neon-green);margin-bottom:10px;"></i>
                        <div style="font-size:2.5rem;font-weight:900;color:var(--neon-green);"><?php echo number_format($stats['today_logs']); ?></div>
                        <div style="color:var(--text-muted);">Activities Today</div>
                    </div>
                    <div class="holo-card" style="text-align:center;">
                        <i class="fas fa-users" style="font-size:2.5rem;color:var(--hologram-purple);margin-bottom:10px;"></i>
                        <div style="font-size:2.5rem;font-weight:900;color:var(--hologram-purple);"><?php echo number_format($stats['unique_users_today']); ?></div>
                        <div style="color:var(--text-muted);">Active Users Today</div>
                    </div>
                    <div class="holo-card" style="text-align:center;">
                        <i class="fas fa-exclamation-triangle" style="font-size:2.5rem;color:var(--cyber-red);margin-bottom:10px;"></i>
                        <div style="font-size:2.5rem;font-weight:900;color:var(--cyber-red);"><?php echo number_format($stats['failed_logins_today']); ?></div>
                        <div style="color:var(--text-muted);">Failed Logins Today</div>
                    </div>
                </div>

                <!-- Anomaly Alerts -->
                <?php if (!empty($anomalies)): ?>
                    <div class="holo-card" style="margin-bottom:25px;">
                        <h3 style="color:var(--cyber-red);margin-bottom:15px;">
                            <i class="fas fa-shield-alt"></i> Security Anomalies Detected
                        </h3>
                        <?php foreach ($anomalies as $anomaly): ?>
                            <div class="anomaly-alert">
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <div>
                                        <strong style="color:var(--cyber-red);font-size:1.1rem;">
                                            <i class="fas fa-user-lock"></i> Multiple Failed Login Attempts
                                        </strong>
                                        <div style="color:var(--text-muted);margin-top:5px;">
                                            User: <?php echo htmlspecialchars($anomaly['first_name'] . ' ' . $anomaly['last_name']); ?>
                                            (<?php echo htmlspecialchars($anomaly['email']); ?>)
                                        </div>
                                        <div style="color:var(--text-muted);margin-top:3px;">
                                            Last Attempt: <?php echo format_datetime($anomaly['last_attempt']); ?>
                                        </div>
                                    </div>
                                    <div style="text-align:center;">
                                        <div style="font-size:2rem;font-weight:900;color:var(--cyber-red);"><?php echo $anomaly['attempts']; ?></div>
                                        <div style="color:var(--text-muted);font-size:0.85rem;">Attempts</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="filter-bar">
                    <form method="GET" class="form-grid">
                        <div>
                            <label class="cyber-label">Action Type</label>
                            <select name="action" class="cyber-input">
                                <option value="">All Actions</option>
                                <?php foreach ($action_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['action']); ?>" <?php echo $action_filter === $type['action'] ? 'selected' : ''; ?>>
                                        <?php echo ucwords(str_replace('_', ' ', $type['action'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="cyber-label">User</label>
                            <select name="user_id" class="cyber-input">
                                <option value="">All Users</option>
                                <?php foreach ($recent_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="cyber-label">Date From</label>
                            <input type="date" name="date_from" class="cyber-input" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div>
                            <label class="cyber-label">Date To</label>
                            <input type="date" name="date_to" class="cyber-input" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div>
                            <label class="cyber-label">Search</label>
                            <input type="text" name="search" class="cyber-input" placeholder="Search logs..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div style="display:flex;gap:10px;align-items:flex-end;">
                            <button type="submit" class="cyber-btn primary" style="flex:1;">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="audit-logs.php" class="cyber-btn" style="flex:1;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Logs List -->
                <div class="holo-card">
                    <h3 style="margin-bottom:20px;">Activity Logs (<?php echo count($logs); ?> entries)</h3>
                    <?php if (empty($logs)): ?>
                        <div style="text-align:center;padding:50px;color:var(--text-muted);">
                            <i class="fas fa-inbox" style="font-size:3rem;margin-bottom:15px;opacity:0.3;"></i>
                            <div>No logs found matching your criteria</div>
                        </div>
                    <?php else: ?>
                        <div id="logs-container">
                            <?php foreach ($logs as $log): ?>
                                <div class="log-entry">
                                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:15px;">
                                        <div style="flex:1;">
                                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                                                <span class="action-badge action-<?php echo strtolower(str_replace(' ', '_', $log['action'])); ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $log['action'])); ?>
                                                </span>
                                                <strong style="color:var(--text-primary);"><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></strong>
                                                <span class="cyber-badge" style="background:rgba(138,43,226,0.2);"><?php echo ucfirst($log['role'] ?? 'N/A'); ?></span>
                                            </div>
                                            <div style="color:var(--text-muted);margin-bottom:5px;">
                                                <?php echo htmlspecialchars($log['description'] ?? 'No description'); ?>
                                            </div>
                                            <div style="color:var(--text-muted);font-size:0.85rem;">
                                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($log['email'] ?? 'N/A'); ?> •
                                                <i class="fas fa-globe"></i> <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                        <div style="text-align:right;white-space:nowrap;">
                                            <div style="color:var(--cyber-cyan);font-size:0.9rem;margin-bottom:3px;">
                                                <i class="fas fa-clock"></i> <?php echo format_datetime($log['created_at']); ?>
                                            </div>
                                            <div style="color:var(--text-muted);font-size:0.85rem;">
                                                <?php
                                                $time_ago = time() - strtotime($log['created_at']);
                                                if ($time_ago < 60) echo 'Just now';
                                                elseif ($time_ago < 3600) echo round($time_ago / 60) . ' min ago';
                                                elseif ($time_ago < 86400) echo round($time_ago / 3600) . ' hours ago';
                                                else echo round($time_ago / 86400) . ' days ago';
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'export-logs.php?' + params.toString();
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>