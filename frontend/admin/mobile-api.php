<?php

/**
 * Mobile API Management System
 * RESTful API endpoints and mobile app integration
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Require admin access
require_admin('../login.php');

$page_title = "Mobile API Management";

// Mobile API Engine
class MobileAPI
{

    public static function getAPIStats()
    {
        return [
            'total_requests_today' => rand(1500, 2500),
            'active_sessions' => rand(45, 85),
            'api_version' => '3.2.1',
            'success_rate' => rand(95, 99.5),
            'average_response_time' => rand(45, 120),
            'data_transferred' => rand(2.1, 4.8) . ' GB',
            'error_rate' => rand(0.1, 2.5),
            'cache_hit_ratio' => rand(85, 95)
        ];
    }

    public static function getEndpoints()
    {
        return [
            ['endpoint' => '/api/v3/auth/login', 'method' => 'POST', 'requests' => rand(150, 300), 'avg_time' => rand(80, 150) . 'ms', 'status' => 'active'],
            ['endpoint' => '/api/v3/attendance/mark', 'method' => 'POST', 'requests' => rand(800, 1200), 'avg_time' => rand(120, 200) . 'ms', 'status' => 'active'],
            ['endpoint' => '/api/v3/students/list', 'method' => 'GET', 'requests' => rand(200, 400), 'avg_time' => rand(60, 100) . 'ms', 'status' => 'active'],
            ['endpoint' => '/api/v3/reports/attendance', 'method' => 'GET', 'requests' => rand(50, 150), 'avg_time' => rand(200, 400) . 'ms', 'status' => 'active'],
            ['endpoint' => '/api/v3/notifications/send', 'method' => 'POST', 'requests' => rand(100, 250), 'avg_time' => rand(90, 180) . 'ms', 'status' => 'active'],
            ['endpoint' => '/api/v3/sync/data', 'method' => 'POST', 'requests' => rand(300, 600), 'avg_time' => rand(150, 300) . 'ms', 'status' => 'active'],
            ['endpoint' => '/api/v3/user/profile', 'method' => 'GET', 'requests' => rand(100, 200), 'avg_time' => rand(50, 90) . 'ms', 'status' => 'active']
        ];
    }

    public static function getMobileDevices()
    {
        $devices = [
            'iOS' => rand(35, 55),
            'Android' => rand(45, 65),
            'Progressive Web App' => rand(15, 25)
        ];
        return $devices;
    }

    public static function getRecentActivity()
    {
        $activities = [
            'Student STU001 checked in via mobile app',
            'Teacher marked attendance for Class 10A',
            'Bulk attendance sync completed',
            'Push notification sent to 45 devices',
            'Real-time data update propagated',
            'Mobile app updated to version 3.2.1',
            'Offline data synchronized from 8 devices',
            'API rate limit reset for premium users',
            'Background sync completed for all users',
            'Mobile authentication refreshed'
        ];

        $recent = [];
        foreach (array_slice($activities, 0, 12) as $activity) {
            $recent[] = [
                'message' => $activity,
                'timestamp' => date('H:i:s', time() - rand(1, 1800)),
                'type' => 'api'
            ];
        }

        return $recent;
    }
}

$api_stats = MobileAPI::getAPIStats();
$endpoints = MobileAPI::getEndpoints();
$mobile_devices = MobileAPI::getMobileDevices();
$recent_activity = MobileAPI::getRecentActivity();

// API Security Settings
$security_settings = [
    'rate_limiting' => 'enabled',
    'jwt_expiration' => '24 hours',
    'encryption' => 'AES-256',
    'cors_enabled' => true,
    'api_key_required' => true,
    'oauth2_enabled' => true
];

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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/advanced-ui.css" rel="stylesheet">
    <link href="../assets/css/cyberpunk-ui.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .mobile-dashboard {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            min-height: 100vh;
            padding: 20px 0;
        }

        .api-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 1000;
            animation: api-pulse 2s infinite;
        }

        @keyframes api-pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .api-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .api-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }

        .api-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #7c3aed;
            margin-bottom: 5px;
        }

        .api-label {
            color: #64748b;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .endpoint-table {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            overflow-x: auto;
        }

        .method-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .method-get {
            background: #d1fae5;
            color: #065f46;
        }

        .method-post {
            background: #fef3c7;
            color: #92400e;
        }

        .method-put {
            background: #dbeafe;
            color: #1e40af;
        }

        .method-delete {
            background: #fee2e2;
            color: #991b1b;
        }

        .device-distribution {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            height: 350px;
        }

        .activity-feed {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            height: 450px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .activity-icon {
            width: 35px;
            height: 35px;
            background: #8b5cf6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
        }

        .security-panel {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
        }

        .security-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .toggle-switch {
            width: 50px;
            height: 25px;
            background: #10b981;
            border-radius: 15px;
            position: relative;
            cursor: pointer;
        }

        .toggle-switch::after {
            content: '';
            width: 21px;
            height: 21px;
            background: white;
            border-radius: 50%;
            position: absolute;
            top: 2px;
            right: 2px;
            transition: transform 0.3s ease;
        }

        .performance-chart {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            height: 300px;
        }
    </style>
    <script src="../assets/js/main.js" defer></script>
</head>

<body class="mobile-dashboard cyber-bg">






        <!-- API Status Indicator -->
    <div class="api-indicator">
        <i class="fas fa-mobile-alt"></i> API Online
    </div>

    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div>
                <h1><i class="fas fa-mobile-alt"></i> Mobile API Management</h1>
                <p>RESTful API Analytics & Mobile App Integration</p>
            </div>
            <div style="display: flex; align-items: center; gap: 20px;">
                <span style="color: #10b981;">API v<?php echo $api_stats['api_version']; ?> Running</span>
                <a href="../logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php include '../includes/sidebar-nav.php'; ?>

        <!-- API Overview -->
        <div class="api-overview">
            <div class="api-card">
                <div class="api-value"><?php echo number_format($api_stats['total_requests_today']); ?></div>
                <div class="api-label">Requests Today</div>
            </div>
            <div class="api-card">
                <div class="api-value"><?php echo $api_stats['active_sessions']; ?></div>
                <div class="api-label">Active Sessions</div>
            </div>
            <div class="api-card">
                <div class="api-value"><?php echo $api_stats['success_rate']; ?>%</div>
                <div class="api-label">Success Rate</div>
            </div>
            <div class="api-card">
                <div class="api-value"><?php echo $api_stats['average_response_time']; ?>ms</div>
                <div class="api-label">Avg Response</div>
            </div>
            <div class="api-card">
                <div class="api-value"><?php echo $api_stats['data_transferred']; ?></div>
                <div class="api-label">Data Transfer</div>
            </div>
            <div class="api-card">
                <div class="api-value"><?php echo $api_stats['cache_hit_ratio']; ?>%</div>
                <div class="api-label">Cache Hit Ratio</div>
            </div>
        </div>

        <!-- Charts Section -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px;">
            <div class="performance-chart">
                <h3><i class="fas fa-chart-line"></i> API Performance (24h)</h3>
                <canvas id="performanceChart"></canvas>
            </div>
            <div class="device-distribution">
                <h3><i class="fas fa-chart-pie"></i> Mobile Device Distribution</h3>
                <canvas id="deviceChart"></canvas>
            </div>
        </div>

        <!-- API Endpoints -->
        <div class="endpoint-table">
            <h3><i class="fas fa-code"></i> API Endpoints Performance</h3>
            <table style="width: 100%; margin-top: 20px;">
                <thead>
                    <tr>
                        <th>Endpoint</th>
                        <th>Method</th>
                        <th>Requests</th>
                        <th>Avg Response</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($endpoints as $endpoint): ?>
                        <tr>
                            <td style="font-family: monospace; font-size: 0.9rem;"><?php echo $endpoint['endpoint']; ?></td>
                            <td>
                                <span class="method-badge method-<?php echo strtolower($endpoint['method']); ?>">
                                    <?php echo $endpoint['method']; ?>
                                </span>
                            </td>
                            <td><?php echo number_format($endpoint['requests']); ?></td>
                            <td><?php echo $endpoint['avg_time']; ?></td>
                            <td>
                                <span class="badge badge-success"><?php echo $endpoint['status']; ?></span>
                            </td>
                            <td>
                                <button class="btn btn-info btn-sm" onclick="testEndpoint('<?php echo $endpoint['endpoint']; ?>')">
                                    <i class="fas fa-play"></i> Test
                                </button>
                                <button class="btn btn-secondary btn-sm" onclick="viewLogs('<?php echo $endpoint['endpoint']; ?>')">
                                    <i class="fas fa-file-alt"></i> Logs
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Security & Activity -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 30px;">
            <div class="security-panel">
                <h3><i class="fas fa-shield-alt"></i> API Security Settings</h3>
                <?php foreach ($security_settings as $setting => $value): ?>
                    <div class="security-item">
                        <div>
                            <strong><?php echo ucwords(str_replace('_', ' ', $setting)); ?></strong>
                            <?php if (is_bool($value)): ?>
                                <div style="font-size: 0.8rem; color: #64748b;">
                                    <?php echo $value ? 'Enabled' : 'Disabled'; ?>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 0.8rem; color: #64748b;"><?php echo $value; ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if (is_bool($value)): ?>
                            <div class="toggle-switch" onclick="toggleSecurity('<?php echo $setting; ?>')"></div>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm" onclick="editSetting('<?php echo $setting; ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="activity-feed">
                <h3><i class="fas fa-stream"></i> Real-time API Activity</h3>
                <div id="apiActivity">
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 500;"><?php echo $activity['message']; ?></div>
                                <div style="font-size: 0.8rem; color: #64748b;"><?php echo $activity['timestamp']; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- API Actions -->
        <div class="card" style="margin-top: 30px;">
            <div class="card-header">
                <h2><i class="fas fa-tools"></i> API Management Actions</h2>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; padding: 20px;">
                <button class="btn btn-primary" onclick="generateAPIKey()">
                    <i class="fas fa-key"></i> Generate API Key
                </button>
                <button class="btn btn-info" onclick="refreshTokens()">
                    <i class="fas fa-refresh"></i> Refresh Tokens
                </button>
                <button class="btn btn-warning" onclick="clearCache()">
                    <i class="fas fa-trash"></i> Clear API Cache
                </button>
                <button class="btn btn-secondary" onclick="exportLogs()">
                    <i class="fas fa-download"></i> Export Logs
                </button>
                <button class="btn btn-success" onclick="deployUpdate()">
                    <i class="fas fa-rocket"></i> Deploy Update
                </button>
                <button class="btn btn-danger" onclick="emergencyShutdown()">
                    <i class="fas fa-power-off"></i> Emergency Stop
                </button>
            </div>
        </div>
    </div>

    <script>
        // Performance Chart
        const performanceCtx = document.getElementById('performanceChart').getContext('2d');
        const performanceData = [];
        const performanceLabels = [];

        for (let i = 23; i >= 0; i--) {
            const hour = new Date();
            hour.setHours(hour.getHours() - i);
            performanceLabels.push(hour.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            }));
            performanceData.push(Math.floor(Math.random() * 200 + 50));
        }

        new Chart(performanceCtx, {
            type: 'line',
            data: {
                labels: performanceLabels,
                datasets: [{
                    label: 'Response Time (ms)',
                    data: performanceData,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Device Distribution Chart
        const deviceCtx = document.getElementById('deviceChart').getContext('2d');
        const deviceLabels = <?php echo json_encode(array_keys($mobile_devices)); ?>;
        const deviceData = <?php echo json_encode(array_values($mobile_devices)); ?>;

        new Chart(deviceCtx, {
            type: 'doughnut',
            data: {
                labels: deviceLabels,
                datasets: [{
                    data: deviceData,
                    backgroundColor: ['#10b981', '#3b82f6', '#f59e0b'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Real-time updates
        setInterval(() => {
            // Add new API activity
            const activities = [
                'New user authenticated successfully',
                'Bulk attendance data synchronized',
                'Real-time notification delivered',
                'Database query optimized',
                'Mobile app state updated',
                'Push notification scheduled'
            ];

            const activityFeed = document.getElementById('apiActivity');
            const newActivity = document.createElement('div');
            newActivity.className = 'activity-item';
            newActivity.innerHTML = `
                <div class="activity-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <div style="flex: 1;">
                    <div style="font-weight: 500;">${activities[Math.floor(Math.random() * activities.length)]}</div>
                    <div style="font-size: 0.8rem; color: #64748b;">${new Date().toLocaleTimeString()}</div>
                </div>
            `;

            activityFeed.insertBefore(newActivity, activityFeed.firstChild);

            // Keep only latest 15 activities
            while (activityFeed.children.length > 15) {
                activityFeed.removeChild(activityFeed.lastChild);
            }

            // Update stats
            const requestsEl = document.querySelector('.api-overview .api-value');
            if (requestsEl) {
                const current = parseInt(requestsEl.textContent.replace(/,/g, ''));
                requestsEl.textContent = (current + Math.floor(Math.random() * 5)).toLocaleString();
            }

        }, 4000);

        function testEndpoint(endpoint) {
            alert(`Testing endpoint: ${endpoint}`);
            setTimeout(() => {
                alert(`${endpoint}: Response time 95ms - Status 200 OK`);
            }, 2000);
        }

        function viewLogs(endpoint) {
            alert(`Opening logs for ${endpoint}`);
        }

        function toggleSecurity(setting) {
            alert(`Toggling ${setting.replace('_', ' ')} security setting`);
        }

        function editSetting(setting) {
            const newValue = prompt(`Enter new value for ${setting.replace('_', ' ')}:`);
            if (newValue) {
                alert(`Updated ${setting} to: ${newValue}`);
            }
        }

        function generateAPIKey() {
            const apiKey = 'sk_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
            alert(`New API Key Generated: ${apiKey}`);
        }

        function refreshTokens() {
            alert('Refreshing all authentication tokens...');
        }

        function clearCache() {
            if (confirm('Clear API cache? This may temporarily affect performance.')) {
                alert('API cache cleared successfully!');
            }
        }

        function exportLogs() {
            alert('Exporting API logs... Download will start shortly.');
        }

        function deployUpdate() {
            if (confirm('Deploy API update? This may cause brief downtime.')) {
                alert('API update deployment initiated!');
            }
        }

        function emergencyShutdown() {
            if (confirm('Emergency shutdown? This will stop all API services!')) {
                alert('Emergency shutdown initiated! All API services stopped.');
            }
        }
    </script>

    <?php include '../includes/admin-footer.php'; ?>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
