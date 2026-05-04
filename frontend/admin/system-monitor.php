<?php

/**
 * System Monitoring Dashboard
 */

session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Require admin access
require_admin('../login.php');

// Get system health metrics
$health_metrics = [
    'database' => check_database_health(),
    'email' => check_email_system(),
    'storage' => check_storage_space(),
    'performance' => get_performance_metrics(),
    'security' => check_security_status()
];

function check_database_health()
{
    try {
        $start_time = microtime(true);

        // Test basic query
        $test_result = db()->fetchOne("SELECT 1 as test");
        $test = $test_result['test'] ?? 1;
        $basic_time = (microtime(true) - $start_time) * 1000;

        // Test table counts
        $users = db()->count('users');
        $students = db()->count('students');
        $teachers = db()->count('teachers');

        // Test recent activity
        $recent_activity = db()->count('activity_logs', 'created_at >= ?', [date('Y-m-d H:i:s', strtotime('-1 hour'))]);

        // Check for orphaned records
        $orphaned_students_result = db()->fetchOne("SELECT COUNT(*) as count FROM students s LEFT JOIN users u ON s.user_id = u.id WHERE u.id IS NULL");
        $orphaned_students = $orphaned_students_result['count'] ?? 0;
        $orphaned_teachers_result = db()->fetchOne("SELECT COUNT(*) as count FROM teachers t LEFT JOIN users u ON t.user_id = u.id WHERE u.id IS NULL");
        $orphaned_teachers = $orphaned_teachers_result['count'] ?? 0;

        $status = 'healthy';
        $issues = [];

        if ($basic_time > 100) {
            $status = 'warning';
            $issues[] = 'Slow query response time';
        }

        if ($orphaned_students > 0 || $orphaned_teachers > 0) {
            $status = 'warning';
            $issues[] = 'Orphaned records detected';
        }

        return [
            'status' => $status,
            'response_time' => round($basic_time, 2),
            'total_users' => $users,
            'students' => $students,
            'teachers' => $teachers,
            'recent_activity' => $recent_activity,
            'orphaned_records' => $orphaned_students + $orphaned_teachers,
            'issues' => $issues
        ];
    } catch (Exception $e) {
        return [
            'status' => 'critical',
            'error' => $e->getMessage(),
            'issues' => ['Database connection failed']
        ];
    }
}

function check_email_system()
{
    try {
        // Check recent email logs
        $recent_emails = db()->count('activity_logs', 'action LIKE ? AND created_at >= ?', ['%email%', date('Y-m-d H:i:s', strtotime('-24 hours'))]);
        $failed_emails = db()->count('activity_logs', 'action LIKE ? AND description LIKE ? AND created_at >= ?', ['%email%', '%failed%', date('Y-m-d H:i:s', strtotime('-24 hours'))]);

        $success_rate = $recent_emails > 0 ? (($recent_emails - $failed_emails) / $recent_emails) * 100 : 100;

        $status = 'healthy';
        $issues = [];

        if ($success_rate < 90) {
            $status = 'warning';
            $issues[] = 'High email failure rate';
        }

        if ($success_rate < 50) {
            $status = 'critical';
            $issues[] = 'Critical email system failure';
        }

        return [
            'status' => $status,
            'recent_emails' => $recent_emails,
            'failed_emails' => $failed_emails,
            'success_rate' => round($success_rate, 1),
            'issues' => $issues
        ];
    } catch (Exception $e) {
        return [
            'status' => 'unknown',
            'error' => $e->getMessage(),
            'issues' => ['Email system check failed']
        ];
    }
}

function check_storage_space()
{
    $path = defined('BASE_PATH') ? BASE_PATH : __DIR__;
    $total_space = @disk_total_space($path);
    $free_space = @disk_free_space($path);
    if (!$total_space || $total_space <= 0) {
        return ['status' => 'unknown', 'total' => 0, 'used' => 0, 'free' => 0, 'usage_percent' => 0, 'issues' => ['Cannot determine disk space']];
    }
    $used_space = $total_space - $free_space;
    $usage_percent = ($used_space / $total_space) * 100;

    $status = 'healthy';
    $issues = [];

    if ($usage_percent > 80) {
        $status = 'warning';
        $issues[] = 'High disk usage';
    }

    if ($usage_percent > 95) {
        $status = 'critical';
        $issues[] = 'Critical disk space low';
    }

    return [
        'status' => $status,
        'total_space' => format_bytes($total_space),
        'used_space' => format_bytes($used_space),
        'free_space' => format_bytes($free_space),
        'usage_percent' => round($usage_percent, 1),
        'issues' => $issues
    ];
}

function get_performance_metrics()
{
    $memory_usage = memory_get_usage(true);
    $memory_peak = memory_get_peak_usage(true);
    $memory_limit = ini_get('memory_limit');

    // Convert memory limit to bytes
    $memory_limit_bytes = convert_to_bytes($memory_limit);
    $memory_percent = ($memory_usage / $memory_limit_bytes) * 100;

    $status = 'healthy';
    $issues = [];

    if ($memory_percent > 70) {
        $status = 'warning';
        $issues[] = 'High memory usage';
    }

    if ($memory_percent > 90) {
        $status = 'critical';
        $issues[] = 'Critical memory usage';
    }

    return [
        'status' => $status,
        'memory_usage' => format_bytes($memory_usage),
        'memory_peak' => format_bytes($memory_peak),
        'memory_limit' => $memory_limit,
        'memory_percent' => round($memory_percent, 1),
        'php_version' => phpversion(),
        'issues' => $issues
    ];
}

function check_security_status()
{
    $issues = [];
    $warnings = [];

    // Check for default passwords
    $default_admins = db()->count('users', 'role = ? AND (password = ? OR password = ?)', ['admin', password_hash('admin', PASSWORD_DEFAULT), password_hash('password', PASSWORD_DEFAULT)]);

    if ($default_admins > 0) {
        $issues[] = 'Default admin passwords detected';
    }

    // Check for recent failed login attempts
    $failed_logins = db()->count('activity_logs', 'action = ? AND created_at >= ?', ['login_failed', date('Y-m-d H:i:s', strtotime('-1 hour'))]);

    if ($failed_logins > 10) {
        $warnings[] = 'High number of failed logins';
    }

    // Check session security
    if (!ini_get('session.cookie_secure') && isset($_SERVER['HTTPS'])) {
        $warnings[] = 'Session cookies not secure';
    }

    $status = count($issues) > 0 ? 'critical' : (count($warnings) > 0 ? 'warning' : 'healthy');

    return [
        'status' => $status,
        'failed_logins' => $failed_logins,
        'default_passwords' => $default_admins,
        'issues' => array_merge($issues, $warnings)
    ];
}

function format_bytes($size, $precision = 2)
{
    if ($size == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = floor(log($size, 1024));
    return round($size / (1024 ** $power), $precision) . ' ' . $units[$power];
}

function convert_to_bytes($val)
{
    $val = trim($val);
    $last = strtolower($val[strlen($val) - 1]);
    $val = (int) $val;
    switch ($last) {
        case 'g':
            $val *= 1024; // Fall through
        case 'm':
            $val *= 1024; // Fall through
        case 'k':
            $val *= 1024;
    }
    return $val;
}

// Calculate overall system health
$overall_status = 'healthy';
$total_issues = 0;

foreach ($health_metrics as $metric) {
    if (isset($metric['issues'])) {
        $total_issues += count($metric['issues']);
    }

    if ($metric['status'] === 'critical') {
        $overall_status = 'critical';
    } elseif ($metric['status'] === 'warning' && $overall_status !== 'critical') {
        $overall_status = 'warning';
    }
}

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
    <title>System Monitor - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <style>
        .health-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin: 25px 0;
        }

        .health-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            border-left: 5px solid;
            transition: transform 0.3s ease;
        }

        .health-card:hover {
            transform: translateY(-5px);
        }

        .health-card.healthy {
            border-left-color: #10b981;
        }

        .health-card.warning {
            border-left-color: #f59e0b;
        }

        .health-card.critical {
            border-left-color: #ef4444;
        }

        .health-card.unknown {
            border-left-color: #6b7280;
        }

        .health-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .health-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .health-icon.healthy {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .health-icon.warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .health-icon.critical {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .health-icon.unknown {
            background: linear-gradient(135deg, #6b7280, #4b5563);
        }

        .metric-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .metric-item:last-child {
            border-bottom: none;
        }

        .issues-list {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            margin-top: 15px;
            border-radius: 0 8px 8px 0;
        }

        .overall-health {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin: 25px 0;
        }

        .real-time-updates {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #3b82f6;
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar-nav.php'; ?>
    <div class="app-layout">
        <div class="cyber-main">

            <div class="container">
                <div class="cyber-header" style="padding: 24px 32px; border-bottom: 1px solid var(--border-color);">
                    <div style="display:flex;align-items:center;gap:16px;">
                        <div style="width:44px;height:44px;background:var(--primary-50);color:var(--primary);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                            <i class="fas fa-heartbeat"></i>
                        </div>
                        <div>
                            <h1 style="font-size:1.375rem;font-weight:700;color:var(--text-primary);">System Monitor</h1>
                            <p style="color:var(--text-secondary);font-size:0.85rem;">System health monitoring</p>
                        </div>
                    </div>
                </div>

                <!-- Overall Health Status -->
                <div class="overall-health">
                    <h2 style="margin-bottom: 15px;">
                        <i class="fas fa-<?php echo $overall_status === 'healthy' ? 'check-circle' : ($overall_status === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?>"></i>
                        System Status: <?php echo strtoupper($overall_status); ?>
                    </h2>
                    <p style="font-size: 1.1rem; opacity: 0.9;">
                        <?php echo $total_issues; ?> issue<?php echo $total_issues !== 1 ? 's' : ''; ?> detected
                        • Last updated: <?php echo date('Y-m-d H:i:s'); ?>
                    </p>
                </div>

                <!-- Health Metrics Grid -->
                <div class="health-grid">
                    <!-- Database Health -->
                    <div class="health-card <?php echo $health_metrics['database']['status']; ?>">
                        <div class="health-header">
                            <div class="health-icon <?php echo $health_metrics['database']['status']; ?>">
                                <i class="fas fa-database"></i>
                            </div>
                            <div>
                                <h3>Database Health</h3>
                                <span class="status-badge <?php echo $health_metrics['database']['status']; ?>">
                                    <?php echo strtoupper($health_metrics['database']['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="metrics">
                            <?php if (isset($health_metrics['database']['response_time'])): ?>
                                <div class="metric-item">
                                    <span>Response Time</span>
                                    <strong><?php echo $health_metrics['database']['response_time']; ?>ms</strong>
                                </div>
                                <div class="metric-item">
                                    <span>Total Users</span>
                                    <strong><?php echo $health_metrics['database']['total_users']; ?></strong>
                                </div>
                                <div class="metric-item">
                                    <span>Recent Activity</span>
                                    <strong><?php echo $health_metrics['database']['recent_activity']; ?></strong>
                                </div>
                                <?php if ($health_metrics['database']['orphaned_records'] > 0): ?>
                                    <div class="metric-item">
                                        <span>Orphaned Records</span>
                                        <strong style="color: #ef4444;"><?php echo $health_metrics['database']['orphaned_records']; ?></strong>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($health_metrics['database']['issues'])): ?>
                            <div class="issues-list">
                                <strong><i class="fas fa-exclamation-triangle"></i> Issues:</strong>
                                <ul style="margin: 5px 0 0 20px;">
                                    <?php foreach ($health_metrics['database']['issues'] as $issue): ?>
                                        <li><?php echo $issue; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Email System Health -->
                    <div class="health-card <?php echo $health_metrics['email']['status']; ?>">
                        <div class="health-header">
                            <div class="health-icon <?php echo $health_metrics['email']['status']; ?>">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <h3>Email System</h3>
                                <span class="status-badge <?php echo $health_metrics['email']['status']; ?>">
                                    <?php echo strtoupper($health_metrics['email']['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="metrics">
                            <?php if (isset($health_metrics['email']['recent_emails'])): ?>
                                <div class="metric-item">
                                    <span>Recent Emails (24h)</span>
                                    <strong><?php echo $health_metrics['email']['recent_emails']; ?></strong>
                                </div>
                                <div class="metric-item">
                                    <span>Failed Emails</span>
                                    <strong><?php echo $health_metrics['email']['failed_emails']; ?></strong>
                                </div>
                                <div class="metric-item">
                                    <span>Success Rate</span>
                                    <strong style="color: <?php echo $health_metrics['email']['success_rate'] > 90 ? '#10b981' : ($health_metrics['email']['success_rate'] > 70 ? '#f59e0b' : '#ef4444'); ?>">
                                        <?php echo $health_metrics['email']['success_rate']; ?>%
                                    </strong>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($health_metrics['email']['issues'])): ?>
                            <div class="issues-list">
                                <strong><i class="fas fa-exclamation-triangle"></i> Issues:</strong>
                                <ul style="margin: 5px 0 0 20px;">
                                    <?php foreach ($health_metrics['email']['issues'] as $issue): ?>
                                        <li><?php echo $issue; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Storage Health -->
                    <div class="health-card <?php echo $health_metrics['storage']['status']; ?>">
                        <div class="health-header">
                            <div class="health-icon <?php echo $health_metrics['storage']['status']; ?>">
                                <i class="fas fa-hdd"></i>
                            </div>
                            <div>
                                <h3>Storage Space</h3>
                                <span class="status-badge <?php echo $health_metrics['storage']['status']; ?>">
                                    <?php echo strtoupper($health_metrics['storage']['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="metrics">
                            <div class="metric-item">
                                <span>Total Space</span>
                                <strong><?php echo $health_metrics['storage']['total_space']; ?></strong>
                            </div>
                            <div class="metric-item">
                                <span>Used Space</span>
                                <strong><?php echo $health_metrics['storage']['used_space']; ?></strong>
                            </div>
                            <div class="metric-item">
                                <span>Free Space</span>
                                <strong><?php echo $health_metrics['storage']['free_space']; ?></strong>
                            </div>
                            <div class="metric-item">
                                <span>Usage</span>
                                <strong style="color: <?php echo $health_metrics['storage']['usage_percent'] > 80 ? '#ef4444' : '#10b981'; ?>">
                                    <?php echo $health_metrics['storage']['usage_percent']; ?>%
                                </strong>
                            </div>
                        </div>

                        <?php if (!empty($health_metrics['storage']['issues'])): ?>
                            <div class="issues-list">
                                <strong><i class="fas fa-exclamation-triangle"></i> Issues:</strong>
                                <ul style="margin: 5px 0 0 20px;">
                                    <?php foreach ($health_metrics['storage']['issues'] as $issue): ?>
                                        <li><?php echo $issue; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Performance Metrics -->
                    <div class="health-card <?php echo $health_metrics['performance']['status']; ?>">
                        <div class="health-header">
                            <div class="health-icon <?php echo $health_metrics['performance']['status']; ?>">
                                <i class="fas fa-tachometer-alt"></i>
                            </div>
                            <div>
                                <h3>Performance</h3>
                                <span class="status-badge <?php echo $health_metrics['performance']['status']; ?>">
                                    <?php echo strtoupper($health_metrics['performance']['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="metrics">
                            <div class="metric-item">
                                <span>Memory Usage</span>
                                <strong><?php echo $health_metrics['performance']['memory_usage']; ?></strong>
                            </div>
                            <div class="metric-item">
                                <span>Memory Peak</span>
                                <strong><?php echo $health_metrics['performance']['memory_peak']; ?></strong>
                            </div>
                            <div class="metric-item">
                                <span>Memory Limit</span>
                                <strong><?php echo $health_metrics['performance']['memory_limit']; ?></strong>
                            </div>
                            <div class="metric-item">
                                <span>Usage %</span>
                                <strong style="color: <?php echo $health_metrics['performance']['memory_percent'] > 70 ? '#ef4444' : '#10b981'; ?>">
                                    <?php echo $health_metrics['performance']['memory_percent']; ?>%
                                </strong>
                            </div>
                            <div class="metric-item">
                                <span>PHP Version</span>
                                <strong><?php echo $health_metrics['performance']['php_version']; ?></strong>
                            </div>
                        </div>

                        <?php if (!empty($health_metrics['performance']['issues'])): ?>
                            <div class="issues-list">
                                <strong><i class="fas fa-exclamation-triangle"></i> Issues:</strong>
                                <ul style="margin: 5px 0 0 20px;">
                                    <?php foreach ($health_metrics['performance']['issues'] as $issue): ?>
                                        <li><?php echo $issue; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Security Status -->
                    <div class="health-card <?php echo $health_metrics['security']['status']; ?>">
                        <div class="health-header">
                            <div class="health-icon <?php echo $health_metrics['security']['status']; ?>">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div>
                                <h3>Security</h3>
                                <span class="status-badge <?php echo $health_metrics['security']['status']; ?>">
                                    <?php echo strtoupper($health_metrics['security']['status']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="metrics">
                            <div class="metric-item">
                                <span>Failed Logins (1h)</span>
                                <strong style="color: <?php echo $health_metrics['security']['failed_logins'] > 10 ? '#ef4444' : '#10b981'; ?>">
                                    <?php echo $health_metrics['security']['failed_logins']; ?>
                                </strong>
                            </div>
                            <div class="metric-item">
                                <span>Default Passwords</span>
                                <strong style="color: <?php echo $health_metrics['security']['default_passwords'] > 0 ? '#ef4444' : '#10b981'; ?>">
                                    <?php echo $health_metrics['security']['default_passwords']; ?>
                                </strong>
                            </div>
                        </div>

                        <?php if (!empty($health_metrics['security']['issues'])): ?>
                            <div class="issues-list">
                                <strong><i class="fas fa-exclamation-triangle"></i> Issues:</strong>
                                <ul style="margin: 5px 0 0 20px;">
                                    <?php foreach ($health_metrics['security']['issues'] as $issue): ?>
                                        <li><?php echo $issue; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div style="background: white; border-radius: 15px; padding: 25px; margin-top: 25px;">
                    <h3 style="margin-bottom: 20px;"><i class="fas fa-tools"></i> Quick Actions</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <button onclick="refreshMetrics()" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Refresh Metrics
                        </button>
                        <a href="system-management.php" class="btn btn-warning">
                            <i class="fas fa-tools"></i> System Management
                        </a>
                        <button onclick="exportHealthReport()" class="btn btn-info">
                            <i class="fas fa-file-export"></i> Export Report
                        </button>
                        <button onclick="toggleAutoRefresh()" class="btn btn-secondary" id="autoRefreshBtn">
                            <i class="fas fa-play"></i> Start Auto-Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Real-time Updates Indicator -->
            <div class="real-time-updates" id="updateIndicator" style="display: none;">
                <i class="fas fa-sync-alt fa-spin"></i> Auto-refreshing...
            </div>

            <script>
                let autoRefreshInterval = null;
                let isAutoRefreshEnabled = false;

                function refreshMetrics() {
                    window.location.reload();
                }

                function toggleAutoRefresh() {
                    const btn = document.getElementById('autoRefreshBtn');
                    const indicator = document.getElementById('updateIndicator');

                    if (!isAutoRefreshEnabled) {
                        autoRefreshInterval = setInterval(() => {
                            refreshMetrics();
                        }, 30000); // Refresh every 30 seconds

                        btn.innerHTML = '<i class="fas fa-stop"></i> Stop Auto-Refresh';
                        indicator.style.display = 'block';
                        isAutoRefreshEnabled = true;
                    } else {
                        clearInterval(autoRefreshInterval);
                        btn.innerHTML = '<i class="fas fa-play"></i> Start Auto-Refresh';
                        indicator.style.display = 'none';
                        isAutoRefreshEnabled = false;
                    }
                }

                function exportHealthReport() {
                    const data = {
                        timestamp: new Date().toISOString(),
                        overall_status: '<?php echo $overall_status; ?>',
                        total_issues: <?php echo $total_issues; ?>,
                        metrics: <?php echo json_encode($health_metrics); ?>
                    };

                    const blob = new Blob([JSON.stringify(data, null, 2)], {
                        type: 'application/json'
                    });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'system_health_report_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.json';
                    a.click();
                    URL.revokeObjectURL(url);
                }
            </script>

            <script src="../assets/js/main.js"></script>
            <script src="../assets/js/pwa-manager.js"></script>
            <script src="../assets/js/pwa-analytics.js"></script>
        </div>
    </div>
</body>

</html>
