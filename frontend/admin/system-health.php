<?php

/**
 * System Health & Performance Monitor
 * Real-time system metrics, resource monitoring, and optimization suggestions
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$full_name = $_SESSION['full_name'];

// Get System Information
function getSystemMetrics()
{
    $metrics = [];

    $rootPath = DIRECTORY_SEPARATOR;
    if (PHP_OS_FAMILY === 'Windows') {
        $systemDrive = $_SERVER['SystemDrive'] ?? getenv('SystemDrive') ?: 'C:';
        $rootPath = rtrim((string)$systemDrive, '\\/') . DIRECTORY_SEPARATOR;
    }

    // PHP Version & Memory
    $metrics['php_version'] = PHP_VERSION;
    $metrics['memory_limit'] = ini_get('memory_limit');
    $memory_usage_mb = round(memory_get_usage(true) / 1024 / 1024, 2);
    $memory_peak_mb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
    $metrics['memory_usage'] = $memory_usage_mb . ' MB';
    $metrics['memory_peak'] = $memory_peak_mb . ' MB';

    $memory_limit_raw = trim((string)ini_get('memory_limit'));
    $memory_limit_mb = 0;
    if ($memory_limit_raw !== '' && $memory_limit_raw !== '-1') {
        $suffix = strtolower(substr($memory_limit_raw, -1));
        $num = (float)$memory_limit_raw;
        if ($suffix === 'g') {
            $memory_limit_mb = $num * 1024;
        } elseif ($suffix === 'm') {
            $memory_limit_mb = $num;
        } elseif ($suffix === 'k') {
            $memory_limit_mb = $num / 1024;
        } else {
            $memory_limit_mb = $num / 1024 / 1024;
        }
    }
    $metrics['memory_usage_percent'] = $memory_limit_mb > 0 ? round(($memory_usage_mb / $memory_limit_mb) * 100, 2) : 0;

    // Server Load (Linux only)
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        $metrics['cpu_load_1min'] = round($load[0], 2);
        $metrics['cpu_load_5min'] = round($load[1], 2);
        $metrics['cpu_load_15min'] = round($load[2], 2);
        $metrics['cpu_usage_percent'] = min(100, max(0, round(($load[0] / max(1, (int)($_SERVER['NUMBER_OF_PROCESSORS'] ?? 1))) * 100, 2)));
    } else {
        $metrics['cpu_usage_percent'] = 0;
    }

    // Disk Space
    $diskFree = @disk_free_space($rootPath);
    $diskTotal = @disk_total_space($rootPath);
    if ($diskFree !== false && $diskTotal !== false && $diskTotal > 0) {
        $metrics['disk_free'] = round($diskFree / 1024 / 1024 / 1024, 2) . ' GB';
        $metrics['disk_total'] = round($diskTotal / 1024 / 1024 / 1024, 2) . ' GB';
        $metrics['disk_usage_percent'] = round((1 - ($diskFree / $diskTotal)) * 100, 2);
    } else {
        $metrics['disk_free'] = 'N/A';
        $metrics['disk_total'] = 'N/A';
        $metrics['disk_usage_percent'] = 0;
    }

    return $metrics;
}

// Database Metrics
function getDatabaseMetrics()
{
    $metrics = [];

    try {
        // Database Size
        $result = db()->fetchOne("
            SELECT
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.TABLES
            WHERE table_schema = DATABASE()
        ");
        $metrics['db_size'] = $result['size_mb'] ?? 0;

        // Table Count
        $metrics['table_count'] = db()->fetchOne("
            SELECT COUNT(*) as count
            FROM information_schema.TABLES
            WHERE table_schema = DATABASE()
        ")['count'];

        // Connection Count
        $metrics['connections'] = db()->fetchOne("
            SHOW STATUS LIKE 'Threads_connected'
        ")['Value'] ?? 0;

        // Slow Queries
        $metrics['slow_queries'] = db()->fetchOne("
            SHOW STATUS LIKE 'Slow_queries'
        ")['Value'] ?? 0;

        // Uptime
        $uptime_seconds = db()->fetchOne("SHOW STATUS LIKE 'Uptime'")['Value'] ?? 0;
        $metrics['uptime_days'] = round($uptime_seconds / 86400, 1);
    } catch (Exception $e) {
        $metrics['error'] = $e->getMessage();
    }

    return $metrics;
}

// Application Metrics
function getApplicationMetrics()
{
    $metrics = [];

    // User Counts
    $metrics['total_users'] = db()->count('users');
    $metrics['active_users'] = db()->count('users', 'status = ?', ['active']);
    $metrics['pending_users'] = db()->count('users', 'status = ?', ['pending']);

    // Session Metrics
    $metrics['active_sessions'] = table_exists('user_sessions')
        ? (db()->count('user_sessions', 'last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)') ?? 0)
        : 0;

    // Activity Today
    $metrics['logins_today'] = table_exists('audit_logs')
        ? (db()->count('audit_logs', 'action = ? AND DATE(created_at) = CURDATE()', ['login']) ?? 0)
        : 0;
    $metrics['attendance_records_today'] = table_exists('attendance_records')
        ? (db()->count('attendance_records', 'DATE(attendance_date) = CURDATE()') ?? 0)
        : 0;

    // Messages
    $metrics['messages_sent_today'] = table_exists('messages')
        ? (db()->count('messages', 'DATE(created_at) = CURDATE()') ?? 0)
        : 0;
    $metrics['unread_messages'] = table_exists('message_recipients')
        ? (db()->count('message_recipients', 'is_read = 0 AND deleted_at IS NULL') ?? 0)
        : 0;

    // Error Rate (today)
    $metrics['total_events_today'] = table_exists('activity_logs')
        ? (db()->count('activity_logs', 'DATE(created_at) = CURDATE()') ?? 0)
        : 0;
    $metrics['failed_events_today'] = table_exists('activity_logs')
        ? (db()->count('activity_logs', "DATE(created_at) = CURDATE() AND (action LIKE ? OR action LIKE ? OR action LIKE ?)", ['%failed%', '%error%', '%reject%']) ?? 0)
        : 0;
    $metrics['error_rate_today'] = ($metrics['total_events_today'] ?? 0) > 0
        ? round((($metrics['failed_events_today'] ?? 0) / $metrics['total_events_today']) * 100, 2)
        : 0;

    return $metrics;
}

// Performance Optimization Suggestions
function getOptimizationSuggestions($system, $db, $app)
{
    $suggestions = [];

    // Memory Suggestions
    if (memory_get_usage(true) / 1024 / 1024 > 128) {
        $suggestions[] = [
            'type' => 'warning',
            'category' => 'Memory',
            'title' => 'High Memory Usage',
            'description' => 'Current memory usage is high. Consider enabling caching or optimizing queries.',
            'action' => 'Review memory-intensive operations'
        ];
    }

    // Disk Space
    if ($system['disk_usage_percent'] > 80) {
        $suggestions[] = [
            'type' => 'danger',
            'category' => 'Disk',
            'title' => 'Low Disk Space',
            'description' => 'Disk usage is above 80%. Clean up old logs and backups.',
            'action' => 'Run cleanup script or expand storage'
        ];
    }

    // Database Size
    if ($db['db_size'] > 500) {
        $suggestions[] = [
            'type' => 'info',
            'category' => 'Database',
            'title' => 'Large Database',
            'description' => 'Database is over 500MB. Consider archiving old records.',
            'action' => 'Archive attendance records older than 2 years'
        ];
    }

    // Slow Queries
    if (($db['slow_queries'] ?? 0) > 100) {
        $suggestions[] = [
            'type' => 'warning',
            'category' => 'Performance',
            'title' => 'Slow Queries Detected',
            'description' => 'Multiple slow queries found. Review and optimize database indexes.',
            'action' => 'Check slow query log and add indexes'
        ];
    }

    // Pending Users
    if ($app['pending_users'] > 10) {
        $suggestions[] = [
            'type' => 'info',
            'category' => 'Admin',
            'title' => 'Pending User Approvals',
            'description' => "There are {$app['pending_users']} users waiting for approval.",
            'action' => 'Review pending registrations'
        ];
    }

    if (($app['error_rate_today'] ?? 0) > 5) {
        $suggestions[] = [
            'type' => 'warning',
            'category' => 'Reliability',
            'title' => 'Elevated Error Rate',
            'description' => 'Application error-rate today is above 5%. Investigate authentication failures and rejected operations.',
            'action' => 'Review Security Logs and failed activity events'
        ];
    }

    // Add success if everything is good
    if (empty($suggestions)) {
        $suggestions[] = [
            'type' => 'success',
            'category' => 'System',
            'title' => 'System Running Optimally',
            'description' => 'No optimization issues detected. System performance is good.',
            'action' => 'Continue monitoring'
        ];
    }

    return $suggestions;
}

$system_metrics = getSystemMetrics();
$db_metrics = getDatabaseMetrics();
$app_metrics = getApplicationMetrics();
$suggestions = getOptimizationSuggestions($system_metrics, $db_metrics, $app_metrics);

// Calculate health score (0-100)
$health_score = 100;
foreach ($suggestions as $sugg) {
    if ($sugg['type'] === 'danger') $health_score -= 20;
    elseif ($sugg['type'] === 'warning') $health_score -= 10;
    elseif ($sugg['type'] === 'info') $health_score -= 5;
}
$health_score = max(0, $health_score);

$page_title = 'System Health & Performance';
$page_icon = 'favorite'; // Material Symbols icon for heart

// Start output buffering for master layout
ob_start();
?>

<!-- System Health Monitor Interface -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    .health-gauge {
        position: relative;
        width: 200px;
        height: 200px;
        margin: 0 auto 20px;
    }

    .metric-card {
        background: linear-gradient(135deg, rgba(0, 191, 255, 0.05), rgba(138, 43, 226, 0.05));
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 20px;
        transition: all 0.3s;
    }

    .metric-card:hover {
        border-color: var(--cyber-cyan);
        box-shadow: 0 0 20px rgba(0, 191, 255, 0.3);
        transform: translateY(-2px);
    }

    .metric-value {
        font-size: 2rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--cyber-cyan), var(--hologram-purple));
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin: 10px 0;
    }

    .metric-label {
        color: var(--text-muted);
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .suggestion-card {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 15px;
        border-left: 4px solid;
        display: flex;
        align-items: flex-start;
        gap: 15px;
    }

    .suggestion-card.success {
        background: rgba(0, 255, 127, 0.1);
        border-color: var(--neon-green);
    }

    .suggestion-card.info {
        background: rgba(0, 191, 255, 0.1);
        border-color: var(--cyber-cyan);
    }

    .suggestion-card.warning {
        background: rgba(255, 165, 0, 0.1);
        border-color: var(--golden-pulse);
    }

    .suggestion-card.danger {
        background: rgba(255, 69, 0, 0.1);
        border-color: var(--cyber-red);
    }
</style>

<!-- System Health Monitor Interface -->

<?php // Continue with metrics display from above
?>
<div class="header-actions">
    <button onclick="location.reload()" class="cyber-btn" title="Refresh">
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
    <!-- System Health Score -->
    <div class="holo-card" style="text-align:center;margin-bottom:30px;">
        <h2 style="margin-bottom:20px;">System Health Score</h2>
        <div class="health-gauge">
            <canvas id="healthGauge"></canvas>
        </div>
        <div style="font-size:3rem;font-weight:900;background:linear-gradient(135deg,var(--neon-green),var(--cyber-cyan));background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:20px 0;">
            <?php echo $health_score; ?>/100
        </div>
        <div style="color:var(--text-muted);font-size:1.1rem;">
            <?php
            if ($health_score >= 90) echo '🟢 Excellent - System running optimally';
            elseif ($health_score >= 70) echo '🟡 Good - Minor optimizations recommended';
            elseif ($health_score >= 50) echo '🟠 Fair - Attention required';
            else echo '🔴 Poor - Immediate action needed';
            ?>
        </div>
    </div>

    <!-- System Metrics Grid -->
    <h3 style="margin-bottom:20px;"><i class="fas fa-server"></i> System Metrics</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px;">
        <div class="metric-card">
            <i class="fas fa-microchip" style="font-size:2rem;color:var(--cyber-cyan);"></i>
            <div class="metric-value"><?php echo $system_metrics['cpu_load_1min'] ?? 'N/A'; ?></div>
            <div class="metric-label">CPU Load (1min)</div>
        </div>
        <div class="metric-card">
            <i class="fas fa-memory" style="font-size:2rem;color:var(--hologram-purple);"></i>
            <div class="metric-value"><?php echo $system_metrics['memory_usage']; ?></div>
            <div class="metric-label">Memory Usage</div>
            <div style="color:var(--text-muted);font-size:0.85rem;margin-top:5px;">Peak: <?php echo $system_metrics['memory_peak']; ?></div>
            <div style="margin-top:10px;">
                <div style="height:8px;background:rgba(255,255,255,0.08);border-radius:999px;overflow:hidden;">
                    <div style="height:100%;width:<?php echo min(100, (float)($system_metrics['memory_usage_percent'] ?? 0)); ?>%;background:linear-gradient(90deg,#8B5CF6,#00BFFF);"></div>
                </div>
                <div style="color:var(--text-muted);font-size:0.8rem;margin-top:5px;">Memory Gauge: <?php echo number_format((float)($system_metrics['memory_usage_percent'] ?? 0), 2); ?>%</div>
            </div>
        </div>
        <div class="metric-card">
            <i class="fas fa-tachometer-alt" style="font-size:2rem;color:var(--cyber-cyan);"></i>
            <div class="metric-value"><?php echo number_format((float)($system_metrics['cpu_usage_percent'] ?? 0), 2); ?>%</div>
            <div class="metric-label">CPU Utilization Gauge</div>
            <div style="margin-top:10px;">
                <div style="height:8px;background:rgba(255,255,255,0.08);border-radius:999px;overflow:hidden;">
                    <div style="height:100%;width:<?php echo min(100, (float)($system_metrics['cpu_usage_percent'] ?? 0)); ?>%;background:linear-gradient(90deg,#00BFFF,#00FF7F);"></div>
                </div>
                <div style="color:var(--text-muted);font-size:0.8rem;margin-top:5px;">Estimated from 1-min load average</div>
            </div>
        </div>
        <div class="metric-card">
            <i class="fas fa-hdd" style="font-size:2rem;color:var(--golden-pulse);"></i>
            <div class="metric-value"><?php echo $system_metrics['disk_usage_percent']; ?>%</div>
            <div class="metric-label">Disk Usage</div>
            <div style="color:var(--text-muted);font-size:0.85rem;margin-top:5px;"><?php echo $system_metrics['disk_free']; ?> free</div>
        </div>
        <div class="metric-card">
            <i class="fas fa-code" style="font-size:2rem;color:var(--neon-green);"></i>
            <div class="metric-value"><?php echo $system_metrics['php_version']; ?></div>
            <div class="metric-label">PHP Version</div>
        </div>
    </div>

    <!-- Database Metrics -->
    <h3 style="margin-bottom:20px;"><i class="fas fa-database"></i> Database Metrics</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px;">
        <div class="metric-card">
            <i class="fas fa-database" style="font-size:2rem;color:var(--cyber-cyan);"></i>
            <div class="metric-value"><?php echo $db_metrics['db_size']; ?> MB</div>
            <div class="metric-label">Database Size</div>
        </div>
        <div class="metric-card">
            <i class="fas fa-table" style="font-size:2rem;color:var(--hologram-purple);"></i>
            <div class="metric-value"><?php echo $db_metrics['table_count']; ?></div>
            <div class="metric-label">Tables</div>
        </div>
        <div class="metric-card">
            <i class="fas fa-plug" style="font-size:2rem;color:var(--golden-pulse);"></i>
            <div class="metric-value"><?php echo $db_metrics['connections']; ?></div>
            <div class="metric-label">Active Connections</div>
        </div>
        <div class="metric-card">
            <i class="fas fa-clock" style="font-size:2rem;color:var(--neon-green);"></i>
            <div class="metric-value"><?php echo $db_metrics['uptime_days']; ?> days</div>
            <div class="metric-label">Database Uptime</div>
        </div>
    </div>

    <!-- Application Metrics -->
    <h3 style="margin-bottom:20px;"><i class="fas fa-chart-line"></i> Application Metrics</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px;">
        <div class="metric-card">
            <i class="fas fa-users" style="font-size:2rem;color:var(--cyber-cyan);"></i>
            <div class="metric-value"><?php echo number_format($app_metrics['total_users']); ?></div>
            <div class="metric-label">Total Users</div>
            <div style="color:var(--text-muted);font-size:0.85rem;margin-top:5px;"><?php echo $app_metrics['active_users']; ?> active</div>
        </div>
        <div class="metric-card">
            <i class="fas fa-user-clock" style="font-size:2rem;color:var(--hologram-purple);"></i>
            <div class="metric-value"><?php echo $app_metrics['active_sessions']; ?></div>
            <div class="metric-label">Active Sessions</div>
            <div style="color:var(--text-muted);font-size:0.85rem;margin-top:5px;">Last 30 minutes</div>
        </div>
        <div class="metric-card">
            <i class="fas fa-sign-in-alt" style="font-size:2rem;color:var(--golden-pulse);"></i>
            <div class="metric-value"><?php echo $app_metrics['logins_today']; ?></div>
            <div class="metric-label">Logins Today</div>
        </div>
        <div class="metric-card">
            <i class="fas fa-clipboard-check" style="font-size:2rem;color:var(--neon-green);"></i>
            <div class="metric-value"><?php echo $app_metrics['attendance_records_today']; ?></div>
            <div class="metric-label">Attendance Records Today</div>
        </div>
        <div class="metric-card">
            <i class="fas fa-heartbeat" style="font-size:2rem;color:var(--cyber-red);"></i>
            <div class="metric-value"><?php echo number_format((float)($app_metrics['error_rate_today'] ?? 0), 2); ?>%</div>
            <div class="metric-label">Error Rate (Today)</div>
            <div style="color:var(--text-muted);font-size:0.85rem;margin-top:5px;"><?php echo (int)($app_metrics['failed_events_today'] ?? 0); ?> failed / <?php echo (int)($app_metrics['total_events_today'] ?? 0); ?> events</div>
        </div>
    </div>

    <!-- Optimization Suggestions -->
    <div class="holo-card">
        <h3 style="margin-bottom:20px;"><i class="fas fa-lightbulb"></i> Optimization Suggestions</h3>
        <?php foreach ($suggestions as $suggestion): ?>
            <div class="suggestion-card <?php echo $suggestion['type']; ?>">
                <div style="flex-shrink:0;">
                    <i class="fas fa-<?php
                                        echo $suggestion['type'] === 'success' ? 'check-circle' : ($suggestion['type'] === 'danger' ? 'exclamation-triangle' : ($suggestion['type'] === 'warning' ? 'exclamation-circle' : 'info-circle'));
                                        ?>" style="font-size:1.5rem;color:<?php
                                                                            echo $suggestion['type'] === 'success' ? 'var(--neon-green)' : ($suggestion['type'] === 'danger' ? 'var(--cyber-red)' : ($suggestion['type'] === 'warning' ? 'var(--golden-pulse)' : 'var(--cyber-cyan)'));
                                                                            ?>;"></i>
                </div>
                <div style="flex:1;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <strong style="color:var(--text-primary);font-size:1.1rem;"><?php echo $suggestion['title']; ?></strong>
                        <span class="cyber-badge" style="background:rgba(0,191,255,0.2);"><?php echo $suggestion['category']; ?></span>
                    </div>
                    <p style="color:var(--text-muted);margin-bottom:8px;"><?php echo $suggestion['description']; ?></p>
                    <div style="color:var(--cyber-cyan);font-size:0.9rem;">
                        <i class="fas fa-arrow-right"></i> <?php echo $suggestion['action']; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</main>
</div>

<script>
    // Health Gauge Chart
    const ctx = document.getElementById('healthGauge').getContext('2d');
    const healthScore = <?php echo $health_score; ?>;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [healthScore, 100 - healthScore],
                backgroundColor: [
                    healthScore >= 90 ? '#00ff7f' : healthScore >= 70 ? '#00bfff' : healthScore >= 50 ? '#ffa500' : '#ff4500',
                    'rgba(255, 255, 255, 0.1)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            cutout: '75%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: false
                }
            }
        }
    });

    // Auto-refresh every 30 seconds
    setTimeout(() => location.reload(), 30000);
</script>

<script src="../assets/js/main.js"></script>
<script src="../assets/js/pwa-manager.js"></script>
<script src="../assets/js/pwa-analytics.js"></script>
</div><!-- End app-layout -->
<?php
// Capture output and use master layout
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
?>
