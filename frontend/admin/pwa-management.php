<?php

/**
 * PWA Management Admin Panel
 * Manage PWA features, analytics, and push notifications
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_admin();

// Admin only

$database = db();
$db = $database->getConnection();
$pageTitle = 'PWA Management';

// Get PWA statistics
$stats = getPWAStats($db);
$installations = getRecentInstallations($db);
$pushLogs = getRecentPushNotifications($db);
$featureFlags = getFeatureFlags($db);
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
    <title><?php echo $pageTitle; ?> - SAMS Admin</title>
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <link href="../assets/css/pwa-styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(26, 26, 46, 0.6);
            border: 1px solid rgba(0, 191, 255, 0.2);
            border-radius: 12px;
            padding: 25px;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #00BFFF, #0080FF);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: rgba(0, 191, 255, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .stat-icon i {
            font-size: 28px;
            color: #00BFFF;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #00BFFF;
            margin-bottom: 5px;
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.95rem;
        }

        .feature-toggle {
            background: rgba(26, 26, 46, 0.6);
            border: 1px solid rgba(0, 191, 255, 0.2);
            border-radius: 8px;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.1);
            transition: 0.4s;
            border-radius: 30px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked+.toggle-slider {
            background-color: #00BFFF;
        }

        input:checked+.toggle-slider:before {
            transform: translateX(30px);
        }

        .notifications-section {
            background: rgba(26, 26, 46, 0.6);
            border: 1px solid rgba(0, 191, 255, 0.2);
            border-radius: 12px;
            padding: 25px;
            margin-top: 30px;
        }

        .send-notification-form {
            display: grid;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(0, 191, 255, 0.3);
            border-radius: 8px;
            padding: 12px;
            color: white;
            font-size: 0.95rem;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .btn-send {
            background: linear-gradient(135deg, #00BFFF, #0080FF);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-send:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 191, 255, 0.4);
        }

        .table-container {
            background: rgba(26, 26, 46, 0.6);
            border: 1px solid rgba(0, 191, 255, 0.2);
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: rgba(0, 191, 255, 0.1);
            color: #00BFFF;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }

        .data-table td {
            padding: 12px;
            border-bottom: 1px solid rgba(0, 191, 255, 0.1);
            color: rgba(255, 255, 255, 0.8);
        }

        .data-table tr:hover {
            background: rgba(0, 191, 255, 0.05);
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-sent {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }

        .status-failed {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
        }

        .status-pending {
            background: rgba(255, 152, 0, 0.2);
            color: #FF9800;
        }
    </style>
</head>

<body>



    <?php include '../includes/sidebar-nav.php'; ?>

    <div class="container" style="padding: 40px 20px;">
        <div class="content-header">
            <h1><i class="fas fa-mobile-alt"></i> PWA Management</h1>
            <p>Manage Progressive Web App features, installations, and push notifications</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-download"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_installations']; ?></div>
                <div class="stat-label">Total Installations</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $stats['active_users']; ?></div>
                <div class="stat-label">Active PWA Users</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="stat-value"><?php echo $stats['push_subscribers']; ?></div>
                <div class="stat-label">Push Subscribers</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="stat-value"><?php echo $stats['notifications_sent_today']; ?></div>
                <div class="stat-label">Notifications Today</div>
            </div>
        </div>

        <!-- Feature Flags -->
        <div class="table-container">
            <h2 style="margin-bottom: 20px;"><i class="fas fa-toggle-on"></i> Feature Flags</h2>

            <?php foreach ($featureFlags as $feature): ?>
                <div class="feature-toggle">
                    <div>
                        <strong><?php echo ucwords(str_replace('_', ' ', $feature['feature_name'])); ?></strong>
                        <p style="margin: 5px 0 0 0; color: rgba(255,255,255,0.6); font-size: 0.9rem;">
                            <?php echo $feature['description']; ?>
                        </p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox"
                            <?php echo $feature['is_enabled'] ? 'checked' : ''; ?>
                            onchange="toggleFeature('<?php echo $feature['feature_name']; ?>', this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Send Push Notification -->
        <div class="notifications-section">
            <h2 style="margin-bottom: 20px;"><i class="fas fa-paper-plane"></i> Send Push Notification</h2>

            <form class="send-notification-form" onsubmit="sendPushNotification(event)">
                <div class="form-group">
                    <label>Target Audience</label>
                    <select name="target" id="targetAudience" required>
                        <option value="all">All Users</option>
                        <option value="students">All Students</option>
                        <option value="teachers">All Teachers</option>
                        <option value="parents">All Parents</option>
                        <option value="custom">Custom User IDs</option>
                    </select>
                </div>

                <div class="form-group" id="customUsersGroup" style="display: none;">
                    <label>User IDs (comma separated)</label>
                    <input type="text" name="user_ids" placeholder="1,2,3,4...">
                </div>

                <div class="form-group">
                    <label>Notification Type</label>
                    <select name="type" required>
                        <option value="general">General</option>
                        <option value="attendance">Attendance</option>
                        <option value="message">Message</option>
                        <option value="assignment">Assignment</option>
                        <option value="announcement">Announcement</option>
                        <option value="grade">Grade</option>
                        <option value="event">Event</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" placeholder="Notification title" required>
                </div>

                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" placeholder="Notification message" required></textarea>
                </div>

                <div class="form-group">
                    <label>URL (optional)</label>
                    <input type="text" name="url" placeholder="/attendance/student/dashboard.php">
                </div>

                <button type="submit" class="btn-send">
                    <i class="fas fa-paper-plane"></i> Send Notification
                </button>
            </form>
        </div>

        <!-- Recent Installations -->
        <div class="table-container">
            <h2 style="margin-bottom: 20px;"><i class="fas fa-history"></i> Recent Installations</h2>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Device Type</th>
                        <th>Browser</th>
                        <th>OS</th>
                        <th>Installed</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($installations as $install): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($install['username'] ?? 'Anonymous'); ?></td>
                            <td><i class="fas fa-<?php echo getDeviceIcon($install['device_type']); ?>"></i> <?php echo ucfirst($install['device_type']); ?></td>
                            <td><?php echo htmlspecialchars($install['browser'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($install['os'] ?? 'Unknown'); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($install['installed_at'])); ?></td>
                            <td>
                                <span class="status-badge <?php echo $install['is_active'] ? 'status-sent' : 'status-failed'; ?>">
                                    <?php echo $install['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Push Notifications -->
        <div class="table-container">
            <h2 style="margin-bottom: 20px;"><i class="fas fa-bell"></i> Recent Push Notifications</h2>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Sent</th>
                        <th>Clicked</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pushLogs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['username']); ?></td>
                            <td><?php echo htmlspecialchars($log['title']); ?></td>
                            <td><?php echo ucfirst($log['type']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $log['status']; ?>">
                                    <?php echo ucfirst($log['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, H:i', strtotime($log['sent_at'])); ?></td>
                            <td><?php echo $log['clicked'] ? '✓' : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="../assets/js/pwa-manager.js"></script>
    <script>
        const pwaAdminCsrfToken = <?php echo json_encode(generate_csrf_token()); ?>;

        // Show/hide custom users input
        document.getElementById('targetAudience').addEventListener('change', function() {
            const customGroup = document.getElementById('customUsersGroup');
            customGroup.style.display = this.value === 'custom' ? 'block' : 'none';
        });

        // Toggle feature flag
        async function toggleFeature(featureName, enabled) {
            try {
                const response = await fetch('../api/pwa-admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': pwaAdminCsrfToken
                    },
                    body: JSON.stringify({
                        action: 'toggle_feature',
                        feature_name: featureName,
                        enabled: enabled
                    })
                });

                const data = await response.json();

                if (data.success) {
                    pwaManager.showToast(`Feature ${enabled ? 'enabled' : 'disabled'} successfully`, 'success');
                } else {
                    pwaManager.showToast('Failed to toggle feature', 'error');
                }
            } catch (error) {
                console.error('Toggle feature error:', error);
                pwaManager.showToast('An error occurred', 'error');
            }
        }

        // Send push notification
        async function sendPushNotification(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const data = {
                action: 'send_bulk',
                title: formData.get('title'),
                message: formData.get('message'),
                type: formData.get('type'),
                url: formData.get('url') || '/attendance/'
            };

            const target = formData.get('target');
            if (target === 'custom') {
                data.user_ids = formData.get('user_ids').split(',').map(id => id.trim());
            } else if (target !== 'all') {
                data.role = target.replace('s', ''); // students -> student
            }

            try {
                const response = await fetch('../api/push.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    pwaManager.showToast(`Notification sent to ${result.data.count} devices`, 'success');
                    form.reset();
                } else {
                    pwaManager.showToast('Failed to send notification', 'error');
                }
            } catch (error) {
                console.error('Send notification error:', error);
                pwaManager.showToast('An error occurred', 'error');
            }
        }
    </script>
    <script src="../assets/js/main.js"></script>
</body>

</html>

<?php
function getPWAStats($db)
{
    $stats = ['total_installations' => 0, 'active_users' => 0, 'push_subscribers' => 0, 'notifications_sent_today' => 0];
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM pwa_installations");
        $stmt->execute();
        $stats['total_installations'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $db->prepare("SELECT COUNT(*) as count FROM pwa_installations WHERE is_active = 1");
        $stmt->execute();
        $stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) as count FROM push_subscriptions");
        $stmt->execute();
        $stats['push_subscribers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $db->prepare("SELECT COUNT(*) as count FROM push_notification_logs WHERE DATE(sent_at) = CURDATE() AND status = 'sent'");
        $stmt->execute();
        $stats['notifications_sent_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (Throwable $e) {
    }
    return $stats;
}

function getRecentInstallations($db)
{
    try {
        $stmt = $db->prepare("SELECT pi.*, CONCAT(u.first_name, ' ', u.last_name) as username FROM pwa_installations pi LEFT JOIN users u ON pi.user_id = u.id ORDER BY pi.installed_at DESC LIMIT 10");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function getRecentPushNotifications($db)
{
    try {
        $stmt = $db->prepare("SELECT pnl.*, CONCAT(u.first_name, ' ', u.last_name) as username FROM push_notification_logs pnl JOIN users u ON pnl.user_id = u.id ORDER BY pnl.sent_at DESC LIMIT 15");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function getFeatureFlags($db)
{
    try {
        $stmt = $db->prepare("SELECT * FROM pwa_feature_flags ORDER BY feature_name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function getDeviceIcon($deviceType)
{
    $icons = [
        'android' => 'android',
        'ios' => 'apple',
        'desktop' => 'desktop',
        'other' => 'mobile-alt'
    ];
    return $icons[$deviceType] ?? 'mobile-alt';
}
?>
