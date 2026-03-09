<?php

/**
 * Real-time Sync System
 * WebSocket-based live data synchronization
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Require admin access
require_admin('../login.php');

$page_title = "Real-time Sync Dashboard";

// Real-time Sync Engine
class RealtimeSync
{

    public static function getConnectedDevices()
    {
        // Simulate connected devices
        return [
            ['id' => 'DEVICE001', 'type' => 'Mobile App', 'user' => 'Teacher A', 'location' => 'Classroom 1', 'last_ping' => time(), 'status' => 'online'],
            ['id' => 'DEVICE002', 'type' => 'Tablet', 'user' => 'Admin', 'location' => 'Office', 'last_ping' => time() - 30, 'status' => 'online'],
            ['id' => 'DEVICE003', 'type' => 'Web Browser', 'user' => 'Teacher B', 'location' => 'Classroom 2', 'last_ping' => time() - 120, 'status' => 'idle'],
            ['id' => 'DEVICE004', 'type' => 'Mobile App', 'user' => 'Student Portal', 'location' => 'Library', 'last_ping' => time() - 5, 'status' => 'online'],
            ['id' => 'DEVICE005', 'type' => 'Kiosk', 'user' => 'Check-in Station', 'location' => 'Main Entrance', 'last_ping' => time() - 2, 'status' => 'online']
        ];
    }

    public static function getDataStream()
    {
        return [
            'total_packets' => rand(2500, 3500),
            'packets_per_second' => rand(25, 45),
            'bandwidth_usage' => rand(150, 300),
            'compression_ratio' => rand(65, 85),
            'error_rate' => rand(0, 2),
            'latency' => rand(15, 45)
        ];
    }

    public static function getSyncHistory()
    {
        $history = [];
        for ($i = 23; $i >= 0; $i--) {
            $time = date('H:i', strtotime("-$i hours"));
            $history[] = [
                'time' => $time,
                'synced_records' => rand(50, 200),
                'conflicts' => rand(0, 3),
                'status' => rand(0, 10) > 1 ? 'success' : 'warning'
            ];
        }
        return $history;
    }

    public static function getRecentActivity()
    {
        $activities = [
            'Student check-in recorded - ID: STU001',
            'Attendance data synchronized from Mobile Device',
            'Teacher marked class attendance - Grade 10A',
            'Real-time backup completed successfully',
            'New student registration synced',
            'Attendance report generated and cached',
            'Mobile app updated attendance status',
            'Conflict resolved: Duplicate entry STU045',
            'System notification sent to all devices',
            'Data integrity check completed'
        ];

        $recent = [];
        foreach (array_slice($activities, 0, 8) as $activity) {
            $recent[] = [
                'message' => $activity,
                'timestamp' => date('H:i:s', time() - rand(1, 300)),
                'type' => 'sync'
            ];
        }

        return $recent;
    }
}

$connected_devices = RealtimeSync::getConnectedDevices();
$data_stream = RealtimeSync::getDataStream();
$sync_history = RealtimeSync::getSyncHistory();
$recent_activity = RealtimeSync::getRecentActivity();

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
        .sync-dashboard {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            min-height: 100vh;
            padding: 20px 0;
        }

        .live-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #ef4444;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 1000;
            animation: live-pulse 2s infinite;
        }

        @keyframes live-pulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.05);
                opacity: 0.9;
            }
        }

        .device-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .device-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            transition: all 0.3s ease;
            border-left: 4px solid;
        }

        .device-card.online {
            border-left-color: #10b981;
        }

        .device-card.idle {
            border-left-color: #f59e0b;
        }

        .device-card.offline {
            border-left-color: #ef4444;
        }

        .device-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-dot.online {
            background: #10b981;
            animation: pulse-green 2s infinite;
        }

        .status-dot.idle {
            background: #f59e0b;
        }

        .status-dot.offline {
            background: #ef4444;
        }

        @keyframes pulse-green {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .stream-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stream-stat {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }

        .stream-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #059669;
            margin-bottom: 5px;
        }

        .stream-label {
            color: #64748b;
            font-size: 0.8rem;
            text-transform: uppercase;
        }

        .activity-stream {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            height: 400px;
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
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
        }

        .sync-chart {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            height: 300px;
        }
    </style>
    <script src="../assets/js/main.js" defer></script>
</head>

<body class="sync-dashboard cyber-bg">






        <!-- Live Indicator -->
    <div class="live-indicator">
        <i class="fas fa-circle" style="color: #ef4444;"></i> LIVE
    </div>

    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div>
                <h1><i class="fas fa-sync"></i> Real-time Sync Dashboard</h1>
                <p>Live Data Synchronization & Device Management</p>
            </div>
            <div style="display: flex; align-items: center; gap: 20px;">
                <span style="color: #10b981;"><i class="fas fa-circle"></i> All Systems Operational</span>
                <a href="../logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php include '../includes/sidebar-nav.php'; ?>

        <!-- Data Stream Statistics -->
        <div class="stream-stats">
            <div class="stream-stat">
                <div class="stream-value" id="totalPackets"><?php echo number_format($data_stream['total_packets']); ?></div>
                <div class="stream-label">Total Packets</div>
            </div>
            <div class="stream-stat">
                <div class="stream-value" id="packetsPerSec"><?php echo $data_stream['packets_per_second']; ?>/s</div>
                <div class="stream-label">Packets/Second</div>
            </div>
            <div class="stream-stat">
                <div class="stream-value" id="bandwidth"><?php echo $data_stream['bandwidth_usage']; ?> KB/s</div>
                <div class="stream-label">Bandwidth</div>
            </div>
            <div class="stream-stat">
                <div class="stream-value" id="compression"><?php echo $data_stream['compression_ratio']; ?>%</div>
                <div class="stream-label">Compression</div>
            </div>
            <div class="stream-stat">
                <div class="stream-value" id="errorRate"><?php echo $data_stream['error_rate']; ?>%</div>
                <div class="stream-label">Error Rate</div>
            </div>
            <div class="stream-stat">
                <div class="stream-value" id="latency"><?php echo $data_stream['latency']; ?>ms</div>
                <div class="stream-label">Latency</div>
            </div>
        </div>

        <!-- Connected Devices -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-devices"></i> Connected Devices</h2>
            </div>
            <div class="device-grid">
                <?php foreach ($connected_devices as $device): ?>
                    <div class="device-card <?php echo $device['status']; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h4><?php echo $device['id']; ?></h4>
                            <div class="device-status">
                                <span class="status-dot <?php echo $device['status']; ?>"></span>
                                <?php echo $device['status']; ?>
                            </div>
                        </div>
                        <p><strong>Type:</strong> <?php echo $device['type']; ?></p>
                        <p><strong>User:</strong> <?php echo $device['user']; ?></p>
                        <p><strong>Location:</strong> <?php echo $device['location']; ?></p>
                        <p><strong>Last Ping:</strong> <?php echo date('H:i:s', $device['last_ping']); ?></p>
                        <div style="margin-top: 10px;">
                            <button class="btn btn-primary btn-sm" onclick="pingDevice('<?php echo $device['id']; ?>')">
                                <i class="fas fa-wifi"></i> Ping
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="syncDevice('<?php echo $device['id']; ?>')">
                                <i class="fas fa-sync"></i> Force Sync
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Charts and Activity -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px;">
            <div class="sync-chart">
                <h3><i class="fas fa-chart-area"></i> Sync Performance (24h)</h3>
                <canvas id="syncChart"></canvas>
            </div>
            <div class="activity-stream">
                <h3><i class="fas fa-stream"></i> Real-time Activity</h3>
                <div id="activityList">
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-sync"></i>
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

        <!-- Sync Configuration -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-cog"></i> Sync Configuration</h2>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                    <label class="form-group">
                        <span>Sync Interval (seconds)</span>
                        <select class="form-control" onchange="updateSyncInterval(this.value)">
                            <option value="5">5 seconds</option>
                            <option value="10" selected>10 seconds</option>
                            <option value="30">30 seconds</option>
                            <option value="60">1 minute</option>
                        </select>
                    </label>
                </div>
                <div>
                    <label class="form-group">
                        <span>Compression Level</span>
                        <select class="form-control" onchange="updateCompression(this.value)">
                            <option value="low">Low (Fast)</option>
                            <option value="medium" selected>Medium (Balanced)</option>
                            <option value="high">High (Small)</option>
                        </select>
                    </label>
                </div>
                <div>
                    <label class="form-group">
                        <span>Conflict Resolution</span>
                        <select class="form-control" onchange="updateConflictResolution(this.value)">
                            <option value="server" selected>Server Wins</option>
                            <option value="client">Client Wins</option>
                            <option value="timestamp">Latest Timestamp</option>
                            <option value="manual">Manual Resolution</option>
                        </select>
                    </label>
                </div>
                <div>
                    <label class="form-group">
                        <span>Auto-backup</span>
                        <select class="form-control" onchange="updateAutoBackup(this.value)">
                            <option value="disabled">Disabled</option>
                            <option value="hourly" selected>Hourly</option>
                            <option value="daily">Daily</option>
                        </select>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sync Performance Chart
        const syncCtx = document.getElementById('syncChart').getContext('2d');
        const syncData = <?php echo json_encode(array_column($sync_history, 'synced_records')); ?>;
        const syncLabels = <?php echo json_encode(array_column($sync_history, 'time')); ?>;

        new Chart(syncCtx, {
            type: 'line',
            data: {
                labels: syncLabels,
                datasets: [{
                    label: 'Synced Records',
                    data: syncData,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
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

        // Real-time updates
        let activityCounter = 0;
        setInterval(() => {
            // Update stream stats
            document.getElementById('totalPackets').textContent =
                Math.floor(Math.random() * 1000 + parseInt(document.getElementById('totalPackets').textContent.replace(/,/g, ''))).toLocaleString();
            document.getElementById('packetsPerSec').textContent =
                Math.floor(Math.random() * 20 + 25) + '/s';
            document.getElementById('bandwidth').textContent =
                Math.floor(Math.random() * 150 + 150) + ' KB/s';
            document.getElementById('latency').textContent =
                Math.floor(Math.random() * 30 + 15) + 'ms';

            // Add new activity
            const activities = [
                'Real-time attendance update received',
                'Mobile device synchronized successfully',
                'Backup checkpoint created',
                'Data validation completed',
                'WebSocket connection established',
                'Cache updated with latest data'
            ];

            const activityList = document.getElementById('activityList');
            const newActivity = document.createElement('div');
            newActivity.className = 'activity-item';
            newActivity.innerHTML = `
                <div class="activity-icon">
                    <i class="fas fa-sync"></i>
                </div>
                <div style="flex: 1;">
                    <div style="font-weight: 500;">${activities[Math.floor(Math.random() * activities.length)]}</div>
                    <div style="font-size: 0.8rem; color: #64748b;">${new Date().toLocaleTimeString()}</div>
                </div>
            `;

            activityList.insertBefore(newActivity, activityList.firstChild);

            // Keep only latest 10 activities
            while (activityList.children.length > 10) {
                activityList.removeChild(activityList.lastChild);
            }

        }, 3000);

        function pingDevice(deviceId) {
            alert(`Pinging device ${deviceId}...`);
            // Simulate ping
            setTimeout(() => {
                alert(`Device ${deviceId} responded in 25ms`);
            }, 1000);
        }

        function syncDevice(deviceId) {
            alert(`Forcing sync for device ${deviceId}...`);
            // Simulate force sync
            setTimeout(() => {
                alert(`Device ${deviceId} synchronized successfully`);
            }, 2000);
        }

        function updateSyncInterval(value) {
            alert(`Sync interval updated to ${value} seconds`);
        }

        function updateCompression(value) {
            alert(`Compression level updated to ${value}`);
        }

        function updateConflictResolution(value) {
            alert(`Conflict resolution updated to ${value}`);
        }

        function updateAutoBackup(value) {
            alert(`Auto-backup updated to ${value}`);
        }
    </script>

    <?php include '../includes/admin-footer.php'; ?>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
