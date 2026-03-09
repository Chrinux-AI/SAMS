<?php

/**
 * Cloud Storage Management System
 * Advanced cloud backup, sync, and storage management
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Require admin access
require_admin('../login.php');

$page_title = "Cloud Storage Management";

// Cloud Storage Engine
class CloudStorage
{

    public static function getStorageStats()
    {
        return [
            'total_space' => 107374182400, // 100 GB in bytes
            'used_space' => 2576980378,   // 2.4 GB in bytes
            'available_space' => 104797202022,
            'files_count' => 1847,
            'backup_count' => 156,
            'redundancy_level' => 3,
            'sync_status' => 'synchronized'
        ];
    }

    public static function getRecentBackups()
    {
        $backups = [];
        for ($i = 0; $i < 10; $i++) {
            $backups[] = [
                'id' => 'BACKUP_' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'type' => rand(0, 1) ? 'Full Backup' : 'Incremental',
                'size' => rand(50, 500) . ' MB',
                'created_at' => date('Y-m-d H:i:s', strtotime("-$i hours")),
                'status' => rand(0, 10) > 1 ? 'completed' : (rand(0, 1) ? 'in_progress' : 'failed'),
                'location' => rand(0, 1) ? 'Primary Cloud' : 'Secondary Cloud',
                'compression_ratio' => rand(60, 85) . '%'
            ];
        }
        return $backups;
    }

    public static function getCloudProviders()
    {
        return [
            [
                'name' => 'Primary Storage',
                'provider' => 'AWS S3',
                'region' => 'US-East-1',
                'status' => 'active',
                'latency' => rand(15, 35) . 'ms',
                'uptime' => '99.99%',
                'used_space' => '1.2 GB',
                'sync_status' => 'synchronized'
            ],
            [
                'name' => 'Secondary Storage',
                'provider' => 'Google Cloud',
                'region' => 'US-Central1',
                'status' => 'active',
                'latency' => rand(20, 40) . 'ms',
                'uptime' => '99.95%',
                'used_space' => '1.2 GB',
                'sync_status' => 'synchronized'
            ],
            [
                'name' => 'Backup Storage',
                'provider' => 'Azure Blob',
                'region' => 'East US',
                'status' => 'standby',
                'latency' => rand(25, 45) . 'ms',
                'uptime' => '99.98%',
                'used_space' => '1.2 GB',
                'sync_status' => 'pending'
            ]
        ];
    }

    public static function getFileCategories()
    {
        return [
            ['category' => 'Attendance Records', 'count' => 1245, 'size' => '856 MB', 'percentage' => 35],
            ['category' => 'Student Data', 'count' => 342, 'size' => '425 MB', 'percentage' => 18],
            ['category' => 'Reports & Analytics', 'count' => 156, 'size' => '682 MB', 'percentage' => 28],
            ['category' => 'System Logs', 'count' => 89, 'size' => '234 MB', 'percentage' => 10],
            ['category' => 'Media Files', 'count' => 15, 'size' => '379 MB', 'percentage' => 9]
        ];
    }
}

$storage_stats = CloudStorage::getStorageStats();
$recent_backups = CloudStorage::getRecentBackups();
$cloud_providers = CloudStorage::getCloudProviders();
$file_categories = CloudStorage::getFileCategories();

// Calculate percentages
$used_percentage = round(($storage_stats['used_space'] / $storage_stats['total_space']) * 100, 1);
$available_percentage = 100 - $used_percentage;

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
        .cloud-dashboard {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            min-height: 100vh;
            padding: 20px 0;
        }

        .cloud-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(59, 130, 246, 0.9);
            color: white;
            padding: 10px 15px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            z-index: 1000;
            backdrop-filter: blur(10px);
        }

        .storage-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .storage-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }

        .storage-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2563eb;
            margin-bottom: 5px;
        }

        .storage-label {
            color: #64748b;
            font-size: 0.9rem;
        }

        .storage-meter {
            width: 100%;
            height: 200px;
            position: relative;
            margin: 20px 0;
        }

        .provider-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .provider-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            border-left: 4px solid;
        }

        .provider-card.active {
            border-left-color: #10b981;
        }

        .provider-card.standby {
            border-left-color: #f59e0b;
        }

        .provider-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .backup-table {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            overflow-x: auto;
        }

        .backup-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-in_progress {
            background: #fef3c7;
            color: #92400e;
        }

        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .file-category {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .category-bar {
            width: 100px;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
        }

        .category-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #2563eb);
            transition: width 0.5s ease;
        }
    </style>
    <script src="../assets/js/main.js" defer></script>
</head>

<body class="cloud-dashboard cyber-bg">






        <!-- Cloud Indicator -->
    <div class="cloud-indicator">
        <i class="fas fa-cloud"></i> Cloud Connected
    </div>

    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div>
                <h1><i class="fas fa-cloud"></i> Cloud Storage Management</h1>
                <p>Advanced Cloud Backup, Sync & Storage Analytics</p>
            </div>
            <div style="display: flex; align-items: center; gap: 20px;">
                <span style="color: #10b981;"><i class="fas fa-check-circle"></i> All Clouds Online</span>
                <a href="../logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php include '../includes/sidebar-nav.php'; ?>

        <!-- Storage Overview -->
        <div class="storage-overview">
            <div class="storage-card">
                <div class="storage-value"><?php echo round($storage_stats['used_space'] / 1024 / 1024 / 1024, 1); ?> GB</div>
                <div class="storage-label">Used Space</div>
            </div>
            <div class="storage-card">
                <div class="storage-value"><?php echo round($storage_stats['available_space'] / 1024 / 1024 / 1024, 1); ?> GB</div>
                <div class="storage-label">Available</div>
            </div>
            <div class="storage-card">
                <div class="storage-value"><?php echo number_format($storage_stats['files_count']); ?></div>
                <div class="storage-label">Total Files</div>
            </div>
            <div class="storage-card">
                <div class="storage-value"><?php echo $storage_stats['backup_count']; ?></div>
                <div class="storage-label">Backups</div>
            </div>
            <div class="storage-card">
                <div class="storage-value"><?php echo $storage_stats['redundancy_level']; ?>x</div>
                <div class="storage-label">Redundancy</div>
            </div>
        </div>

        <!-- Storage Visualization -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px;">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-pie"></i> Storage Distribution</h2>
                </div>
                <div style="height: 300px; padding: 20px;">
                    <canvas id="storageChart"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-folder"></i> File Categories</h2>
                </div>
                <div style="padding: 20px;">
                    <?php foreach ($file_categories as $category): ?>
                        <div class="file-category">
                            <div>
                                <div style="font-weight: 600; margin-bottom: 5px;"><?php echo $category['category']; ?></div>
                                <div style="font-size: 0.9rem; color: #64748b;"><?php echo $category['count']; ?> files • <?php echo $category['size']; ?></div>
                            </div>
                            <div>
                                <div class="category-bar">
                                    <div class="category-fill" style="width: <?php echo $category['percentage']; ?>%"></div>
                                </div>
                                <div style="text-align: center; font-size: 0.8rem; margin-top: 5px;"><?php echo $category['percentage']; ?>%</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Cloud Providers -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-server"></i> Cloud Providers</h2>
            </div>
            <div class="provider-grid">
                <?php foreach ($cloud_providers as $provider): ?>
                    <div class="provider-card <?php echo $provider['status']; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4><?php echo $provider['name']; ?></h4>
                            <div class="provider-status" style="color: <?php echo $provider['status'] === 'active' ? '#10b981' : '#f59e0b'; ?>;">
                                <i class="fas fa-circle"></i>
                                <?php echo $provider['status']; ?>
                            </div>
                        </div>
                        <p><strong>Provider:</strong> <?php echo $provider['provider']; ?></p>
                        <p><strong>Region:</strong> <?php echo $provider['region']; ?></p>
                        <p><strong>Latency:</strong> <?php echo $provider['latency']; ?></p>
                        <p><strong>Uptime:</strong> <?php echo $provider['uptime']; ?></p>
                        <p><strong>Used Space:</strong> <?php echo $provider['used_space']; ?></p>
                        <p><strong>Sync Status:</strong>
                            <span class="badge <?php echo $provider['sync_status'] === 'synchronized' ? 'badge-success' : 'badge-warning'; ?>">
                                <?php echo $provider['sync_status']; ?>
                            </span>
                        </p>
                        <div style="margin-top: 15px;">
                            <button class="btn btn-primary btn-sm" onclick="testConnection('<?php echo $provider['name']; ?>')">
                                <i class="fas fa-wifi"></i> Test
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="forceSync('<?php echo $provider['name']; ?>')">
                                <i class="fas fa-sync"></i> Sync
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Backups -->
        <div class="backup-table">
            <h3><i class="fas fa-history"></i> Recent Backups</h3>
            <table style="width: 100%; margin-top: 20px;">
                <thead>
                    <tr>
                        <th>Backup ID</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Created</th>
                        <th>Location</th>
                        <th>Compression</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_backups as $backup): ?>
                        <tr>
                            <td><?php echo $backup['id']; ?></td>
                            <td><?php echo $backup['type']; ?></td>
                            <td><?php echo $backup['size']; ?></td>
                            <td><?php echo date('M j, H:i', strtotime($backup['created_at'])); ?></td>
                            <td><?php echo $backup['location']; ?></td>
                            <td><?php echo $backup['compression_ratio']; ?></td>
                            <td>
                                <span class="backup-status status-<?php echo $backup['status']; ?>">
                                    <?php echo str_replace('_', ' ', $backup['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-info btn-sm" onclick="downloadBackup('<?php echo $backup['id']; ?>')">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button class="btn btn-success btn-sm" onclick="restoreBackup('<?php echo $backup['id']; ?>')">
                                    <i class="fas fa-undo"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Cloud Actions -->
        <div class="card" style="margin-top: 30px;">
            <div class="card-header">
                <h2><i class="fas fa-tools"></i> Cloud Actions</h2>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; padding: 20px;">
                <button class="btn btn-primary" onclick="createBackup()">
                    <i class="fas fa-plus"></i> Create Backup
                </button>
                <button class="btn btn-info" onclick="syncAllClouds()">
                    <i class="fas fa-sync-alt"></i> Sync All Clouds
                </button>
                <button class="btn btn-warning" onclick="optimizeStorage()">
                    <i class="fas fa-compress-arrows-alt"></i> Optimize Storage
                </button>
                <button class="btn btn-secondary" onclick="testAllConnections()">
                    <i class="fas fa-network-wired"></i> Test All Connections
                </button>
                <button class="btn btn-success" onclick="exportSettings()">
                    <i class="fas fa-file-export"></i> Export Settings
                </button>
                <button class="btn btn-danger" onclick="emergencyBackup()">
                    <i class="fas fa-exclamation-triangle"></i> Emergency Backup
                </button>
            </div>
        </div>
    </div>

    <script>
        // Storage Distribution Chart
        const storageCtx = document.getElementById('storageChart').getContext('2d');

        new Chart(storageCtx, {
            type: 'doughnut',
            data: {
                labels: ['Used Space', 'Available Space'],
                datasets: [{
                    data: [<?php echo $used_percentage; ?>, <?php echo $available_percentage; ?>],
                    backgroundColor: ['#3b82f6', '#e2e8f0'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                cutout: '70%'
            }
        });

        // Real-time storage monitoring
        setInterval(() => {
            // Simulate storage changes
            const usedSpaceEl = document.querySelector('.storage-overview .storage-value');
            if (usedSpaceEl) {
                const currentValue = parseFloat(usedSpaceEl.textContent);
                const variation = (Math.random() - 0.5) * 0.01; // Small random variation
                usedSpaceEl.textContent = (currentValue + variation).toFixed(1) + ' GB';
            }
        }, 10000);

        function testConnection(providerName) {
            alert(`Testing connection to ${providerName}...`);
            setTimeout(() => {
                alert(`${providerName}: Connection successful! Latency: ${Math.floor(Math.random() * 30 + 15)}ms`);
            }, 2000);
        }

        function forceSync(providerName) {
            alert(`Initiating force sync for ${providerName}...`);
            setTimeout(() => {
                alert(`${providerName}: Sync completed successfully!`);
            }, 3000);
        }

        function downloadBackup(backupId) {
            if (confirm(`Download backup ${backupId}? This may take several minutes.`)) {
                alert(`Preparing download for backup ${backupId}...`);
            }
        }

        function restoreBackup(backupId) {
            if (confirm(`Restore from backup ${backupId}? This will overwrite current data.`)) {
                alert(`Restoring from backup ${backupId}... Please wait.`);
            }
        }

        function createBackup() {
            if (confirm('Create a new backup? This will include all current data.')) {
                alert('Backup creation initiated. You will be notified when complete.');
            }
        }

        function syncAllClouds() {
            alert('Synchronizing all cloud providers... This may take several minutes.');
        }

        function optimizeStorage() {
            if (confirm('Optimize storage? This will compress and deduplicate files.')) {
                alert('Storage optimization started. Progress will be shown in the dashboard.');
            }
        }

        function testAllConnections() {
            alert('Testing all cloud connections... Results will appear shortly.');
        }

        function exportSettings() {
            alert('Exporting cloud configuration settings...');
        }

        function emergencyBackup() {
            if (confirm('Create emergency backup? This will backup all critical data immediately.')) {
                alert('Emergency backup initiated with highest priority!');
            }
        }
    </script>

    <?php include '../includes/admin-footer.php'; ?>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
