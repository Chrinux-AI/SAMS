<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$page_title = 'Activity Monitor';
$page_icon = 'history';
$full_name = $_SESSION['full_name'];

// Get filter parameters
$filter_user = $_GET['user_id'] ?? '';
$filter_action = $_GET['action_type'] ?? '';
$filter_date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$filter_date_to = $_GET['date_to'] ?? date('Y-m-d');

// Build query
$where_conditions = ["DATE(al.created_at) BETWEEN ? AND ?"];
$params = [$filter_date_from, $filter_date_to];

if ($filter_user) {
    $where_conditions[] = "al.user_id = ?";
    $params[] = $filter_user;
}

if ($filter_action) {
    $where_conditions[] = "al.action_type = ?";
    $params[] = $filter_action;
}

$where_clause = implode(' AND ', $where_conditions);

// Get activity logs
$activities = db()->fetchAll("
    SELECT al.*,
           u.first_name, u.last_name, u.role, u.email,
           CONCAT(u.first_name, ' ', u.last_name) as user_name
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    WHERE $where_clause
    ORDER BY al.created_at DESC
    LIMIT 500
", $params);

// Get statistics
$stats = db()->fetchOne("
    SELECT
        COUNT(*) as total_activities,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT DATE(created_at)) as active_days
    FROM activity_logs
    WHERE DATE(created_at) BETWEEN ? AND ?
", [$filter_date_from, $filter_date_to]);

// Get users for filter
$users = db()->fetchAll("
    SELECT id, first_name, last_name, role
    FROM users
    WHERE status = 'active'
    ORDER BY first_name, last_name
");

// Get action types for filter
$action_types = db()->fetchAll("
    SELECT DISTINCT action_type
    FROM activity_logs
    ORDER BY action_type
");
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
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    
    <style>
        .activity-log {
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.05), rgba(138, 43, 226, 0.05));
            border-left: 4px solid var(--cyber-cyan);
            padding: 15px 20px;
            margin-bottom: 12px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .activity-log:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(0, 191, 255, 0.2);
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .activity-user {
            color: var(--cyber-cyan);
            font-weight: 600;
            font-size: 1rem;
        }

        .activity-time {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.85rem;
        }

        .activity-action {
            padding: 4px 12px;
            background: rgba(0, 191, 255, 0.2);
            border: 1px solid var(--cyber-cyan);
            border-radius: 15px;
            font-size: 0.75rem;
            text-transform: uppercase;
            margin-right: 8px;
        }

        .activity-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin-top: 8px;
        }

        .activity-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(0, 191, 255, 0.2);
        }

        .detail-item {
            font-size: 0.8rem;
        }

        .detail-label {
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 2px;
        }

        .detail-value {
            color: var(--cyber-cyan);
            font-family: monospace;
        }

        .filter-bar {
            background: rgba(0, 191, 255, 0.05);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-label {
            color: var(--cyber-cyan);
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
        }

        .filter-input {
            width: 100%;
            padding: 10px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--cyber-cyan);
            border-radius: 8px;
            color: var(--cyber-cyan);
            font-family: Roboto;
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
                    <button onclick="exportLogs()" class="cyber-btn primary" style="margin-right:15px;">
                        <i class="fas fa-download"></i> Export Logs
                    </button>
                    <div class="biometric-orb" title="Live Monitoring"><i class="fas fa-satellite-dish"></i></div>
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

                <section class="orb-grid">
                    <div class="stat-orb">
                        <div class="stat-icon cyan"><i class="fas fa-list"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($stats['total_activities'] ?? 0); ?></div>
                            <div class="stat-label">Total Activities</div>
                            <div class="stat-trend up"><i class="fas fa-chart-line"></i><span>In Period</span></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon purple"><i class="fas fa-users"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($stats['unique_users'] ?? 0); ?></div>
                            <div class="stat-label">Active Users</div>
                            <div class="stat-trend up"><i class="fas fa-user-check"></i><span>Unique</span></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon green"><i class="fas fa-calendar-day"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($stats['active_days'] ?? 0); ?></div>
                            <div class="stat-label">Active Days</div>
                            <div class="stat-trend up"><i class="fas fa-clock"></i><span>Days</span></div>
                        </div>
                    </div>
                </section>

                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-filter"></i> <span>Filter Activity Logs</span></div>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="filter-bar">
                            <div class="filter-row">
                                <div>
                                    <label class="filter-label">USER</label>
                                    <select name="user_id" class="filter-input">
                                        <option value="">All Users</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['role'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="filter-label">ACTION TYPE</label>
                                    <select name="action_type" class="filter-input">
                                        <option value="">All Actions</option>
                                        <?php foreach ($action_types as $action): ?>
                                            <option value="<?php echo htmlspecialchars($action['action_type']); ?>" <?php echo $filter_action == $action['action_type'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $action['action_type']))); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="filter-label">FROM DATE</label>
                                    <input type="date" name="date_from" value="<?php echo $filter_date_from; ?>" class="filter-input">
                                </div>
                                <div>
                                    <label class="filter-label">TO DATE</label>
                                    <input type="date" name="date_to" value="<?php echo $filter_date_to; ?>" class="filter-input">
                                </div>
                            </div>
                            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:15px;">
                                <a href="activity-monitor.php" class="cyber-btn">Clear Filters</a>
                                <button type="submit" class="cyber-btn primary"><i class="fas fa-search"></i> Apply Filters</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-stream"></i> <span>Activity Stream (<?php echo count($activities); ?> records)</span></div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activities)): ?>
                            <div style="text-align:center;padding:40px;color:rgba(255,255,255,0.4);">
                                <i class="fas fa-inbox" style="font-size:3rem;margin-bottom:15px;"></i>
                                <div>No activities found for the selected filters</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($activities as $activity): ?>
                                <div class="activity-log">
                                    <div class="activity-header">
                                        <div>
                                            <span class="activity-user"><?php echo htmlspecialchars($activity['user_name']); ?></span>
                                            <span class="activity-action"><?php echo htmlspecialchars(str_replace('_', ' ', $activity['action_type'])); ?></span>
                                        </div>
                                        <div class="activity-time">
                                            <i class="fas fa-clock"></i> <?php echo date('M d, Y H:i:s', strtotime($activity['created_at'])); ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($activity['description'])): ?>
                                        <div class="activity-description">
                                            <?php echo htmlspecialchars($activity['description']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="activity-details">
                                        <div class="detail-item">
                                            <div class="detail-label">Role</div>
                                            <div class="detail-value"><?php echo strtoupper($activity['role']); ?></div>
                                        </div>
                                        <?php if (!empty($activity['table_name'])): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">Table</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($activity['table_name']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($activity['record_id'])): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">Record ID</div>
                                                <div class="detail-value">#<?php echo $activity['record_id']; ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($activity['ip_address'])): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">IP Address</div>
                                                <div class="detail-value"><?php echo htmlspecialchars($activity['ip_address']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.location.href = '?' + params.toString();
        }

        // Auto-refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>