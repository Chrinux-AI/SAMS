<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$page_title = 'Security & Audit Logs';
$page_icon = 'shield-alt';
$full_name = $_SESSION['full_name'];

// Get filter parameters
$filter_type = $_GET['type'] ?? 'all';
$filter_user = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Build query
$where = "created_at BETWEEN ? AND ?";
$params = [$date_from . ' 00:00:00', $date_to . ' 23:59:59'];

if ($filter_type !== 'all') {
    if ($filter_type === 'permission_change') {
        $where .= " AND (action LIKE ? OR action LIKE ? OR action LIKE ? OR action LIKE ? OR action LIKE ? OR action LIKE ? OR action LIKE ? OR action LIKE ? OR action LIKE ? OR action LIKE ? OR action LIKE ?)";
        $params = array_merge($params, ['%permission%', '%role%', '%set_role%', '%role_update%', '%grant%', '%revoke%', '%hierarchy%', '%update_role%', '%create_role%', '%delete_role%', '%set_user_role%']);
    } elseif ($filter_type === 'password_reset') {
        $where .= " AND (action LIKE ? OR action LIKE ? OR action LIKE ? OR action LIKE ?)";
        $params = array_merge($params, ['%password%', '%reset%', '%forgot_password%', '%verify_otp%']);
    } else {
        $where .= " AND action = ?";
        $params[] = $filter_type;
    }
}

if ($filter_user) {
    $where .= " AND user_id = ?";
    $params[] = $filter_user;
}

// Get logs
$logs = db()->fetchAll("
    SELECT al.*, u.full_name, u.role, u.email
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE $where
    ORDER BY al.created_at DESC
    LIMIT 500
", $params);

// Get statistics
$stats = [
    'total_logs' => db()->count('activity_logs', $where, $params),
    'login_attempts' => db()->count('activity_logs', "$where AND action = 'login'", array_merge($params, ['login'])),
    'failed_logins' => db()->count('activity_logs', "$where AND action = 'failed_login'", array_merge($params, ['failed_login'])),
    'user_changes' => db()->count('activity_logs', "$where AND (action LIKE '%user%' OR action LIKE '%approve%' OR action LIKE '%delete%')", $params),
    'permission_changes' => db()->count('activity_logs', "$where AND (action LIKE '%permission%' OR action LIKE '%role%' OR action LIKE '%set_role%' OR action LIKE '%grant%' OR action LIKE '%revoke%')", $params),
    'password_resets' => db()->count('activity_logs', "$where AND (action LIKE '%password%' OR action LIKE '%reset%' OR action LIKE '%forgot_password%' OR action LIKE '%verify_otp%')", $params)
];

// Get all users for filter
$users = db()->fetchAll("SELECT id, full_name, role FROM users ORDER BY full_name");

// Action types for filter
$action_types = [
    'login' => 'Login',
    'logout' => 'Logout',
    'failed_login' => 'Failed Login',
    'approve_user' => 'User Approval',
    'disapprove_user' => 'User Disapproval',
    'delete_user' => 'User Deletion',
    'create_user' => 'User Creation',
    'update_user' => 'User Update',
    'resend_verification' => 'Resend Verification',
    'biometric_register' => 'Biometric Registration',
    'biometric_login' => 'Biometric Login',
    'biometric_failed' => 'Biometric Failed',
    'mark_attendance' => 'Mark Attendance',
    'system_change' => 'System Change',
    'permission_change' => 'Permission Changes',
    'password_reset' => 'Password Resets'
];
?>
<!DOCTYPE html>
<html>

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
            </header>
            <div class="cyber-content slide-in">

                <!-- Statistics -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px;">
                    <div class="stat-orb">
                        <div class="stat-icon" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);">
                            <i class="fas fa-list"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['total_logs']); ?></h3>
                            <p>Total Events</p>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon" style="background:linear-gradient(135deg,#10b981 0%,#059669 100%);">
                            <i class="fas fa-sign-in-alt"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['login_attempts']); ?></h3>
                            <p>Login Attempts</p>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon" style="background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['failed_logins']); ?></h3>
                            <p>Failed Logins</p>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);">
                            <i class="fas fa-user-edit"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['user_changes']); ?></h3>
                            <p>User Changes</p>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon" style="background:linear-gradient(135deg,#0ea5e9 0%,#0369a1 100%);">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['permission_changes']); ?></h3>
                            <p>Permission Changes</p>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon" style="background:linear-gradient(135deg,#8b5cf6 0%,#6d28d9 100%);">
                            <i class="fas fa-key"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['password_resets']); ?></h3>
                            <p>Password Reset Events</p>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="holo-card" style="margin-bottom:30px;">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-filter"></i> <span>Filter Logs</span></div>
                    </div>
                    <div class="card-body">
                        <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;">
                            <div>
                                <label style="color:var(--cyber-cyan);font-size:0.85rem;margin-bottom:8px;display:block;">Action Type</label>
                                <select name="type" style="width:100%;padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);">
                                    <option value="all">All Actions</option>
                                    <?php foreach ($action_types as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo $filter_type === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="color:var(--cyber-cyan);font-size:0.85rem;margin-bottom:8px;display:block;">User</label>
                                <select name="user_id" style="width:100%;padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo $user['role']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="color:var(--cyber-cyan);font-size:0.85rem;margin-bottom:8px;display:block;">From Date</label>
                                <input type="date" name="date_from" value="<?php echo $date_from; ?>" style="width:100%;padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);">
                            </div>
                            <div>
                                <label style="color:var(--cyber-cyan);font-size:0.85rem;margin-bottom:8px;display:block;">To Date</label>
                                <input type="date" name="date_to" value="<?php echo $date_to; ?>" style="width:100%;padding:10px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);">
                            </div>
                            <div style="display:flex;align-items:flex-end;gap:10px;">
                                <button type="submit" class="cyber-btn cyber-btn-primary" style="flex:1;">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="security-logs.php" class="cyber-btn cyber-btn-outline" style="flex:1;">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Logs Table -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-list"></i> <span>Activity Logs (<?php echo count($logs); ?>)</span></div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($logs)): ?>
                            <p style="text-align:center;color:var(--text-muted);padding:40px;">No logs found for the selected filters.</p>
                        <?php else: ?>
                            <div style="overflow-x:auto;">
                                <table class="holo-table">
                                    <thead>
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <td style="font-size:0.85rem;color:var(--text-muted);">
                                                    <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?></strong>
                                                        <?php if ($log['role']): ?>
                                                            <br><span style="font-size:0.75rem;color:var(--text-muted);"><?php echo ucfirst($log['role']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="cyber-badge <?php
                                                                                echo match (true) {
                                                                                    str_contains($log['action'], 'login') => 'cyan',
                                                                                    str_contains($log['action'], 'delete') || str_contains($log['action'], 'failed') => 'danger',
                                                                                    str_contains($log['action'], 'approve') || str_contains($log['action'], 'create') => 'success',
                                                                                    default => 'default'
                                                                                };
                                                                                ?>">
                                                        <?php echo ucwords(str_replace('_', ' ', $log['action'])); ?>
                                                    </span>
                                                </td>
                                                <td style="max-width:400px;font-size:0.85rem;">
                                                    <?php echo htmlspecialchars($log['details'] ?? 'N/A'); ?>
                                                </td>
                                                <td style="font-size:0.85rem;font-family:monospace;">
                                                    <?php echo $log['ip_address'] ?? 'N/A'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
