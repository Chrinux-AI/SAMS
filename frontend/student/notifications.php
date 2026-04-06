<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_student();

$student_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get all notifications with category
$filter = $_GET['filter'] ?? 'all';
$category = $_GET['category'] ?? 'all';

$sql = "SELECT * FROM notifications WHERE user_id = ?";
$params = [$student_id];

if ($filter === 'unread') {
    $sql .= " AND is_read = 0";
}

if ($category !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $category;
}

$sql .= " ORDER BY created_at DESC LIMIT 100";

$notifications = db()->fetchAll($sql, $params);

// Get notification stats
$stats = [
    'total' => db()->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE user_id = ?", [$student_id])['c'],
    'unread' => db()->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0", [$student_id])['c'],
    'today' => db()->fetchOne("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND DATE(created_at) = CURDATE()", [$student_id])['c']
];

$page_title = 'Notification Center';
$page_icon = 'bell';
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

    <style>
        .notification-header {
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.1), rgba(138, 43, 226, 0.1));
            border: 1px solid rgba(0, 191, 255, 0.2);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .notification-stats {
            display: flex;
            gap: 30px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            color: var(--cyber-cyan);
            font-family: 'Orbitron', sans-serif;
            font-weight: 900;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
        }

        .notification-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .filter-btn {
            padding: 8px 16px;
            background: rgba(0, 191, 255, 0.1);
            border: 1px solid rgba(0, 191, 255, 0.3);
            border-radius: 8px;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .filter-btn:hover {
            background: rgba(0, 191, 255, 0.2);
            border-color: var(--cyber-cyan);
        }

        .filter-btn.active {
            background: var(--cyber-cyan);
            color: #0a0e27;
            font-weight: 600;
        }

        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .notification-card {
            background: rgba(0, 191, 255, 0.03);
            border: 1px solid rgba(0, 191, 255, 0.2);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            gap: 15px;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
        }

        .notification-card:hover {
            background: rgba(0, 191, 255, 0.08);
            border-color: var(--cyber-cyan);
            transform: translateX(5px);
        }

        .notification-card.unread {
            border-left: 4px solid var(--cyber-cyan);
            background: rgba(0, 191, 255, 0.08);
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.3rem;
        }

        .notification-icon.urgent {
            background: linear-gradient(135deg, #ff0055, #ff4488);
            color: #fff;
            animation: pulse-urgent 1.5s infinite;
        }

        .notification-icon.info {
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.2), rgba(138, 43, 226, 0.2));
            color: var(--cyber-cyan);
        }

        .notification-icon.success {
            background: linear-gradient(135deg, rgba(0, 255, 0, 0.2), rgba(0, 200, 0, 0.2));
            color: var(--neon-green);
        }

        .notification-icon.warning {
            background: linear-gradient(135deg, rgba(255, 200, 0, 0.2), rgba(255, 150, 0, 0.2));
            color: var(--golden-pulse);
        }

        @keyframes pulse-urgent {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(255, 0, 85, 0.7);
            }

            50% {
                box-shadow: 0 0 0 10px rgba(255, 0, 85, 0);
            }
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 1.05rem;
        }

        .notification-message {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .notification-meta {
            display: flex;
            gap: 15px;
            align-items: center;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .notification-time {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .notification-category {
            display: inline-block;
            padding: 4px 10px;
            background: rgba(0, 191, 255, 0.2);
            border-radius: 20px;
            font-size: 0.75rem;
            color: var(--cyber-cyan);
        }

        .notification-actions-btn {
            display: flex;
            flex-direction: column;
            gap: 8px;
            justify-content: center;
        }

        .action-icon {
            width: 35px;
            height: 35px;
            background: rgba(0, 191, 255, 0.1);
            border: 1px solid rgba(0, 191, 255, 0.3);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--cyber-cyan);
        }

        .action-icon:hover {
            background: rgba(0, 191, 255, 0.2);
            border-color: var(--cyber-cyan);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .notification-settings {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background: rgba(10, 14, 39, 0.98);
            border-left: 1px solid var(--cyber-cyan);
            padding: 30px;
            transition: right 0.3s;
            z-index: 1000;
            overflow-y: auto;
        }

        .notification-settings.active {
            right: 0;
        }

        .settings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0, 191, 255, 0.2);
        }

        .settings-section {
            margin-bottom: 25px;
        }

        .settings-section h4 {
            color: var(--cyber-cyan);
            margin-bottom: 15px;
        }

        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: rgba(0, 191, 255, 0.05);
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 26px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .toggle-switch.active {
            background: var(--cyber-cyan);
        }

        .toggle-switch::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background: #fff;
            border-radius: 50%;
            top: 3px;
            left: 3px;
            transition: all 0.3s;
        }

        .toggle-switch.active::after {
            left: 27px;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar-nav.php'; ?>

    <div class="app-layout">
        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <button class="cyber-btn cyan" onclick="openSettings()">
                        <i class="fas fa-cog"></i> Settings
                    </button>
                </div>
            </header>

            <div class="app-layout">
                <!-- Notification Header Stats -->
                <div class="notification-header">
                    <div class="notification-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['total']; ?></div>
                            <div class="stat-label">Total</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['unread']; ?></div>
                            <div class="stat-label">Unread</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['today']; ?></div>
                            <div class="stat-label">Today</div>
                        </div>
                    </div>
                    <div class="notification-actions">
                        <button class="cyber-btn green" onclick="markAllRead()">
                            <i class="fas fa-check-double"></i> Mark All Read
                        </button>
                        <button class="cyber-btn orange" onclick="clearAll()">
                            <i class="fas fa-trash"></i> Clear All
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="notification-filters">
                    <div class="filter-group">
                        <span style="color:var(--text-muted);font-size:0.9rem;">Filter:</span>
                        <button class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>" onclick="setFilter('all')">
                            All
                        </button>
                        <button class="filter-btn <?php echo $filter === 'unread' ? 'active' : ''; ?>" onclick="setFilter('unread')">
                            Unread
                        </button>
                    </div>
                    <div class="filter-group">
                        <span style="color:var(--text-muted);font-size:0.9rem;">Category:</span>
                        <button class="filter-btn <?php echo $category === 'all' ? 'active' : ''; ?>" onclick="setCategory('all')">
                            All
                        </button>
                        <button class="filter-btn <?php echo $category === 'urgent' ? 'active' : ''; ?>" onclick="setCategory('urgent')">
                            Urgent
                        </button>
                        <button class="filter-btn <?php echo $category === 'attendance' ? 'active' : ''; ?>" onclick="setCategory('attendance')">
                            Attendance
                        </button>
                        <button class="filter-btn <?php echo $category === 'assignments' ? 'active' : ''; ?>" onclick="setCategory('assignments')">
                            Assignments
                        </button>
                        <button class="filter-btn <?php echo $category === 'grades' ? 'active' : ''; ?>" onclick="setCategory('grades')">
                            Grades
                        </button>
                    </div>
                </div>

                <!-- Notification List -->
                <div class="notification-list">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3 style="margin-bottom:10px;">All caught up!</h3>
                            <p>You have no notifications at the moment.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                            <div class="notification-card <?php echo $notif['is_read'] ? '' : 'unread'; ?>"
                                data-id="<?php echo $notif['id']; ?>"
                                onclick="handleNotificationClick(<?php echo $notif['id']; ?>, '<?php echo htmlspecialchars($notif['link'] ?? '#'); ?>')">
                                <div class="notification-icon <?php echo $notif['category'] ?? 'info'; ?>">
                                    <i class="fas fa-<?php echo $notif['icon'] ?? 'bell'; ?>"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">
                                        <?php echo htmlspecialchars($notif['title'] ?? 'Notification'); ?>
                                    </div>
                                    <div class="notification-message">
                                        <?php echo htmlspecialchars($notif['message']); ?>
                                    </div>
                                    <div class="notification-meta">
                                        <div class="notification-time">
                                            <i class="fas fa-clock"></i>
                                            <?php echo timeAgo($notif['created_at']); ?>
                                        </div>
                                        <?php if (isset($notif['category'])): ?>
                                            <span class="notification-category"><?php echo ucfirst($notif['category']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="notification-actions-btn">
                                    <div class="action-icon" onclick="event.stopPropagation(); markAsRead(<?php echo $notif['id']; ?>)" title="Mark as read">
                                        <i class="fas fa-check"></i>
                                    </div>
                                    <div class="action-icon" onclick="event.stopPropagation(); deleteNotification(<?php echo $notif['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Settings Panel -->
    <div class="notification-settings" id="settingsPanel">
        <div class="settings-header">
            <h3 style="color:var(--cyber-cyan);margin:0;">Notification Settings</h3>
            <button class="cyber-btn" onclick="closeSettings()" style="padding:8px 12px;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="settings-section">
            <h4>Push Notifications</h4>
            <div class="setting-item">
                <span>Enable Push Notifications</span>
                <div class="toggle-switch active" data-setting="push_enabled"></div>
            </div>
            <div class="setting-item">
                <span>Sound Alerts</span>
                <div class="toggle-switch active" data-setting="sound_enabled"></div>
            </div>
            <div class="setting-item">
                <span>Vibration (Mobile)</span>
                <div class="toggle-switch active" data-setting="vibration_enabled"></div>
            </div>
        </div>

        <div class="settings-section">
            <h4>Email Notifications</h4>
            <div class="setting-item">
                <span>Urgent Alerts</span>
                <div class="toggle-switch active" data-setting="email_urgent"></div>
            </div>
            <div class="setting-item">
                <span>Daily Digest</span>
                <div class="toggle-switch" data-setting="email_digest"></div>
            </div>
            <div class="setting-item">
                <span>Assignment Reminders</span>
                <div class="toggle-switch active" data-setting="email_assignments"></div>
            </div>
        </div>

        <div class="settings-section">
            <h4>Categories</h4>
            <div class="setting-item">
                <span>Attendance Alerts</span>
                <div class="toggle-switch active" data-setting="cat_attendance"></div>
            </div>
            <div class="setting-item">
                <span>Assignment Deadlines</span>
                <div class="toggle-switch active" data-setting="cat_assignments"></div>
            </div>
            <div class="setting-item">
                <span>Grade Updates</span>
                <div class="toggle-switch active" data-setting="cat_grades"></div>
            </div>
            <div class="setting-item">
                <span>Messages</span>
                <div class="toggle-switch active" data-setting="cat_messages"></div>
            </div>
            <div class="setting-item">
                <span>Events</span>
                <div class="toggle-switch active" data-setting="cat_events"></div>
            </div>
        </div>

        <button class="cyber-btn cyan" style="width:100%;" onclick="saveSettings()">
            <i class="fas fa-save"></i> Save Settings
        </button>
    </div>

    <script>
        function setFilter(filter) {
            window.location.href = `?filter=${filter}&category=<?php echo $category; ?>`;
        }

        function setCategory(cat) {
            window.location.href = `?filter=<?php echo $filter; ?>&category=${cat}`;
        }

        function handleNotificationClick(id, link) {
            markAsRead(id);
            if (link && link !== '#') {
                setTimeout(() => window.location.href = link, 300);
            }
        }

        function markAsRead(id) {
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    id: id
                })
            }).then(() => {
                const card = document.querySelector(`[data-id="${id}"]`);
                if (card) card.classList.remove('unread');
            });
        }

        function markAllRead() {
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'mark_all_read'
                })
            }).then(() => location.reload());
        }

        function deleteNotification(id) {
            if (confirm('Delete this notification?')) {
                fetch('../api/notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        id: id
                    })
                }).then(() => {
                    const card = document.querySelector(`[data-id="${id}"]`);
                    if (card) card.remove();
                });
            }
        }

        function clearAll() {
            if (confirm('Clear all notifications?')) {
                fetch('../api/notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'clear_all'
                    })
                }).then(() => location.reload());
            }
        }

        function openSettings() {
            document.getElementById('settingsPanel').classList.add('active');
        }

        function closeSettings() {
            document.getElementById('settingsPanel').classList.remove('active');
        }

        function saveSettings() {
            const settings = {};
            document.querySelectorAll('.toggle-switch').forEach(toggle => {
                const setting = toggle.dataset.setting;
                settings[setting] = toggle.classList.contains('active');
            });

            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'save_settings',
                    settings: settings
                })
            }).then(() => {
                alert('Settings saved!');
                closeSettings();
            });
        }

        // Toggle switches
        document.querySelectorAll('.toggle-switch').forEach(toggle => {
            toggle.addEventListener('click', function() {
                this.classList.toggle('active');
            });
        });

        // Load settings from localStorage
        const savedSettings = JSON.parse(localStorage.getItem('notificationSettings') || '{}');
        document.querySelectorAll('.toggle-switch').forEach(toggle => {
            const setting = toggle.dataset.setting;
            if (savedSettings[setting] !== undefined) {
                if (savedSettings[setting]) {
                    toggle.classList.add('active');
                } else {
                    toggle.classList.remove('active');
                }
            }
        });
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>