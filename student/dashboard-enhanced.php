<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_student();

$student_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get comprehensive student data
$records = db()->fetchAll("SELECT * FROM attendance_records WHERE student_id = ? ORDER BY check_in_time DESC", [$student_id]);
$present = count(array_filter($records, fn($r) => $r['status'] === 'present'));
$late = count(array_filter($records, fn($r) => $r['status'] === 'late'));
$absent = count(array_filter($records, fn($r) => $r['status'] === 'absent'));
$total = count($records);
$attendance_rate = $total > 0 ? round((($present + $late) / $total) * 100, 1) : 0;

// Get student's classes
$classes = db()->fetchAll("
    SELECT c.* FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ?
", [$student_id]);

// Get pending assignments
$assignments = db()->fetchAll("
    SELECT a.*, c.name as class_name
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ? AND a.due_date >= CURDATE()
    ORDER BY a.due_date ASC
    LIMIT 5
", [$student_id]);

// Get upcoming events
$events = db()->fetchAll("
    SELECT * FROM events
    WHERE event_date >= CURDATE()
    ORDER BY event_date ASC
    LIMIT 3
");

// Get unread notifications
$notifications = db()->fetchAll("
    SELECT * FROM notifications
    WHERE user_id = ? AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 5
", [$student_id]);

// Get gamification stats
$badges = db()->fetchAll("SELECT * FROM student_badges WHERE student_id = ?", [$student_id]);
$current_streak = db()->fetchOne("SELECT current_streak, longest_streak FROM attendance_streaks WHERE student_id = ?", [$student_id]);

// Get grade summary
$grade_avg = db()->fetchOne("
    SELECT AVG(grade) as avg_grade
    FROM student_grades
    WHERE student_id = ?
", [$student_id])['avg_grade'] ?? 0;

$page_title = 'Student Dashboard';
$page_icon = 'brain';
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
        .dashboard-widgets {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .widget {
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.03), rgba(138, 43, 226, 0.03));
            border: 1px solid rgba(0, 191, 255, 0.2);
            border-radius: 12px;
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: grab;
        }

        .widget:hover {
            border-color: var(--cyber-cyan);
            box-shadow: 0 0 20px rgba(0, 191, 255, 0.3);
            transform: translateY(-2px);
        }

        .widget.dragging {
            opacity: 0.5;
            cursor: grabbing;
        }

        .widget-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0, 191, 255, 0.1);
        }

        .widget-title {
            color: var(--cyber-cyan);
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .widget-actions {
            display: flex;
            gap: 8px;
        }

        .widget-btn {
            width: 28px;
            height: 28px;
            background: rgba(0, 191, 255, 0.1);
            border: 1px solid rgba(0, 191, 255, 0.3);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--cyber-cyan);
        }

        .widget-btn:hover {
            background: rgba(0, 191, 255, 0.2);
            border-color: var(--cyber-cyan);
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .quick-stat {
            background: rgba(0, 0, 0, 0.3);
            padding: 12px;
            border-radius: 8px;
            text-align: center;
        }

        .quick-stat-value {
            font-size: 1.5rem;
            color: var(--cyber-cyan);
            font-weight: 700;
            font-family: 'Orbitron', sans-serif;
        }

        .quick-stat-label {
            color: var(--text-muted);
            font-size: 0.75rem;
            margin-top: 5px;
        }

        .assignment-item {
            background: rgba(0, 0, 0, 0.2);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 3px solid var(--cyber-cyan);
        }

        .assignment-item:last-child {
            margin-bottom: 0;
        }

        .assignment-title {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 5px;
        }

        .assignment-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .badge-showcase {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .achievement-badge {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--golden-pulse), var(--neon-green));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: pulse-glow 2s infinite;
        }

        .achievement-badge i {
            font-size: 1.5rem;
            color: #fff;
        }

        .achievement-badge::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 2px solid var(--golden-pulse);
            animation: ripple 1.5s infinite;
        }

        @keyframes ripple {
            0% {
                transform: scale(1);
                opacity: 1;
            }

            100% {
                transform: scale(1.4);
                opacity: 0;
            }
        }

        .notification-item {
            background: rgba(0, 0, 0, 0.2);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
            display: flex;
            align-items: start;
            gap: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .notification-item:hover {
            background: rgba(0, 191, 255, 0.1);
        }

        .notification-icon {
            width: 35px;
            height: 35px;
            background: rgba(0, 191, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--cyber-cyan);
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
        }

        .notification-text {
            color: var(--text-primary);
            font-size: 0.9rem;
            margin-bottom: 3px;
        }

        .notification-time {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .progress-ring {
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }

        .progress-ring circle {
            transition: stroke-dashoffset 0.5s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }

        .event-card {
            background: rgba(0, 0, 0, 0.2);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 12px;
            border-left: 4px solid var(--hologram-purple);
        }

        .event-date {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--hologram-purple);
            font-weight: 600;
            margin-bottom: 8px;
        }

        .event-title {
            color: var(--text-primary);
            font-size: 1rem;
            margin-bottom: 5px;
        }

        .event-location {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .streak-display {
            text-align: center;
            padding: 20px;
        }

        .streak-number {
            font-size: 3rem;
            color: var(--neon-green);
            font-family: 'Orbitron', sans-serif;
            font-weight: 900;
            text-shadow: 0 0 20px rgba(0, 255, 0, 0.5);
        }

        .streak-label {
            color: var(--text-muted);
            margin-top: 10px;
        }

        .grade-chart {
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            position: relative;
        }

        .grade-display {
            font-size: 4rem;
            color: var(--golden-pulse);
            font-family: 'Orbitron', sans-serif;
            font-weight: 900;
            text-shadow: 0 0 30px rgba(255, 215, 0, 0.5);
        }

        .widget-empty {
            text-align: center;
            padding: 30px;
            color: var(--text-muted);
        }

        .widget-empty i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.3;
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
                    <span class="cyber-badge" style="margin-left:15px;">Enhanced Mode</span>
                </div>
                <div class="header-actions">
                    <button class="cyber-btn cyan" id="customizeWidgets" style="margin-right:15px;">
                        <i class="fas fa-th"></i> Customize
                    </button>
                    <div class="biometric-orb" title="Quick Check-In" onclick="window.location.href='checkin.php'">
                        <i class="fas fa-fingerprint"></i>
                    </div>
                    <div class="notification-bell" id="notificationBell">
                        <i class="fas fa-bell"></i>
                        <?php if (count($notifications) > 0): ?>
                            <span class="notification-count"><?php echo count($notifications); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <div class="app-layout">
                <?php include '../includes/notice-board.php'; ?>

                <!-- Quick Stats Bar -->
                <section class="orb-grid" style="margin-bottom:30px;">
                    <div class="stat-orb">
                        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $present; ?></div>
                            <div class="stat-label">Days Present</div>
                            <div class="stat-trend up"><i class="fas fa-check"></i><span>Excellent</span></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $late; ?></div>
                            <div class="stat-label">Times Late</div>
                            <div class="stat-trend <?php echo $late > 5 ? 'down' : 'up'; ?>">
                                <i class="fas fa-exclamation-circle"></i>
                                <span><?php echo $late > 5 ? 'Improve' : 'Good'; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon cyan"><i class="fas fa-percentage"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $attendance_rate; ?>%</div>
                            <div class="stat-label">Attendance Rate</div>
                            <div class="stat-trend <?php echo $attendance_rate >= 90 ? 'up' : 'down'; ?>">
                                <i class="fas fa-star"></i>
                                <span><?php echo $attendance_rate >= 90 ? 'Excellent' : 'Improve'; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon purple"><i class="fas fa-graduation-cap"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo round($grade_avg, 1); ?>%</div>
                            <div class="stat-label">Grade Average</div>
                            <div class="stat-trend <?php echo $grade_avg >= 85 ? 'up' : 'down'; ?>">
                                <i class="fas fa-chart-line"></i>
                                <span><?php echo $grade_avg >= 85 ? 'Great' : 'Keep Going'; ?></span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Draggable Widgets Grid -->
                <div class="dashboard-widgets" id="widgetsContainer">
                    <!-- Attendance Widget -->
                    <div class="widget" draggable="true" data-widget="attendance">
                        <div class="widget-header">
                            <div class="widget-title">
                                <i class="fas fa-chart-pie"></i>
                                Attendance Overview
                            </div>
                            <div class="widget-actions">
                                <div class="widget-btn" title="Refresh"><i class="fas fa-sync-alt"></i></div>
                                <div class="widget-btn" title="Settings"><i class="fas fa-cog"></i></div>
                            </div>
                        </div>
                        <div class="quick-stats">
                            <div class="quick-stat">
                                <div class="quick-stat-value"><?php echo $present; ?></div>
                                <div class="quick-stat-label">Present</div>
                            </div>
                            <div class="quick-stat">
                                <div class="quick-stat-value"><?php echo $late; ?></div>
                                <div class="quick-stat-label">Late</div>
                            </div>
                            <div class="quick-stat">
                                <div class="quick-stat-value"><?php echo $absent; ?></div>
                                <div class="quick-stat-label">Absent</div>
                            </div>
                            <div class="quick-stat">
                                <div class="quick-stat-value"><?php echo $attendance_rate; ?>%</div>
                                <div class="quick-stat-label">Rate</div>
                            </div>
                        </div>
                    </div>

                    <!-- Assignments Widget -->
                    <div class="widget" draggable="true" data-widget="assignments">
                        <div class="widget-header">
                            <div class="widget-title">
                                <i class="fas fa-tasks"></i>
                                Pending Assignments
                            </div>
                            <div class="widget-actions">
                                <div class="widget-btn" onclick="window.location.href='assignments.php'">
                                    <i class="fas fa-external-link-alt"></i>
                                </div>
                            </div>
                        </div>
                        <?php if (count($assignments) > 0): ?>
                            <?php foreach ($assignments as $assignment): ?>
                                <div class="assignment-item">
                                    <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                                    <div class="assignment-meta">
                                        <span><?php echo htmlspecialchars($assignment['class_name']); ?></span>
                                        <span class="cyber-badge orange" style="font-size:0.75rem;">
                                            Due: <?php echo date('M j', strtotime($assignment['due_date'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="widget-empty">
                                <i class="fas fa-clipboard-check"></i>
                                <div>No pending assignments</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Achievements Widget -->
                    <div class="widget" draggable="true" data-widget="achievements">
                        <div class="widget-header">
                            <div class="widget-title">
                                <i class="fas fa-trophy"></i>
                                Achievements
                            </div>
                            <div class="widget-actions">
                                <div class="widget-btn"><i class="fas fa-award"></i></div>
                            </div>
                        </div>
                        <?php if (count($badges) > 0): ?>
                            <div class="badge-showcase">
                                <?php foreach (array_slice($badges, 0, 6) as $badge): ?>
                                    <div class="achievement-badge" title="<?php echo htmlspecialchars($badge['name']); ?>">
                                        <i class="fas fa-<?php echo $badge['icon'] ?? 'star'; ?>"></i>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="text-align:center;margin-top:15px;color:var(--text-muted);font-size:0.85rem;">
                                <?php echo count($badges); ?> badges earned
                            </div>
                        <?php else: ?>
                            <div class="widget-empty">
                                <i class="fas fa-medal"></i>
                                <div>Start earning badges!</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Streak Widget -->
                    <div class="widget" draggable="true" data-widget="streak">
                        <div class="widget-header">
                            <div class="widget-title">
                                <i class="fas fa-fire"></i>
                                Attendance Streak
                            </div>
                            <div class="widget-actions">
                                <div class="widget-btn"><i class="fas fa-history"></i></div>
                            </div>
                        </div>
                        <div class="streak-display">
                            <div class="streak-number"><?php echo $current_streak['current_streak'] ?? 0; ?></div>
                            <div class="streak-label">Days in a row!</div>
                            <?php if (isset($current_streak['longest_streak'])): ?>
                                <div style="margin-top:15px;color:var(--text-muted);font-size:0.85rem;">
                                    Best: <?php echo $current_streak['longest_streak']; ?> days
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Notifications Widget -->
                    <div class="widget" draggable="true" data-widget="notifications">
                        <div class="widget-header">
                            <div class="widget-title">
                                <i class="fas fa-bell"></i>
                                Recent Notifications
                            </div>
                            <div class="widget-actions">
                                <div class="widget-btn" onclick="markAllRead()"><i class="fas fa-check-double"></i></div>
                            </div>
                        </div>
                        <?php if (count($notifications) > 0): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                    <div class="notification-icon">
                                        <i class="fas fa-<?php echo $notification['icon'] ?? 'info-circle'; ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-text"><?php echo htmlspecialchars($notification['message']); ?></div>
                                        <div class="notification-time"><?php echo timeAgo($notification['created_at']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="widget-empty">
                                <i class="fas fa-inbox"></i>
                                <div>You're all caught up!</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Upcoming Events Widget -->
                    <div class="widget" draggable="true" data-widget="events">
                        <div class="widget-header">
                            <div class="widget-title">
                                <i class="fas fa-calendar-star"></i>
                                Upcoming Events
                            </div>
                            <div class="widget-actions">
                                <div class="widget-btn" onclick="window.location.href='events.php'">
                                    <i class="fas fa-external-link-alt"></i>
                                </div>
                            </div>
                        </div>
                        <?php if (count($events) > 0): ?>
                            <?php foreach ($events as $event): ?>
                                <div class="event-card">
                                    <div class="event-date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                                    </div>
                                    <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                    <div class="event-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($event['location'] ?? 'TBA'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="widget-empty">
                                <i class="fas fa-calendar-times"></i>
                                <div>No upcoming events</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Grade Average Widget -->
                    <div class="widget" draggable="true" data-widget="grades">
                        <div class="widget-header">
                            <div class="widget-title">
                                <i class="fas fa-chart-line"></i>
                                Grade Average
                            </div>
                            <div class="widget-actions">
                                <div class="widget-btn" onclick="window.location.href='grades.php'">
                                    <i class="fas fa-external-link-alt"></i>
                                </div>
                            </div>
                        </div>
                        <div class="grade-chart">
                            <div class="grade-display"><?php echo round($grade_avg, 1); ?>%</div>
                        </div>
                        <div style="text-align:center;margin-top:15px;color:var(--text-muted);font-size:0.85rem;">
                            <?php
                            if ($grade_avg >= 90) echo "Outstanding performance! 🌟";
                            elseif ($grade_avg >= 80) echo "Great work! Keep it up! 👏";
                            elseif ($grade_avg >= 70) echo "Good progress! 📈";
                            else echo "Let's improve together! 💪";
                            ?>
                        </div>
                    </div>

                    <!-- Quick Actions Widget -->
                    <div class="widget" draggable="true" data-widget="quickactions">
                        <div class="widget-header">
                            <div class="widget-title">
                                <i class="fas fa-bolt"></i>
                                Quick Actions
                            </div>
                        </div>
                        <div style="display:grid;gap:10px;">
                            <button class="cyber-btn cyan" onclick="window.location.href='checkin.php'" style="width:100%;">
                                <i class="fas fa-fingerprint"></i> Check In Now
                            </button>
                            <button class="cyber-btn green" onclick="window.location.href='messages.php'" style="width:100%;">
                                <i class="fas fa-comments"></i> View Messages
                            </button>
                            <button class="cyber-btn orange" onclick="window.location.href='schedule.php'" style="width:100%;">
                                <i class="fas fa-calendar"></i> Today's Schedule
                            </button>
                            <button class="cyber-btn purple" onclick="window.location.href='assignments.php'" style="width:100%;">
                                <i class="fas fa-tasks"></i> Submit Work
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Widget drag and drop functionality
        const widgets = document.querySelectorAll('.widget');
        const container = document.getElementById('widgetsContainer');
        let draggedElement = null;

        widgets.forEach(widget => {
            widget.addEventListener('dragstart', handleDragStart);
            widget.addEventListener('dragend', handleDragEnd);
            widget.addEventListener('dragover', handleDragOver);
            widget.addEventListener('drop', handleDrop);
        });

        function handleDragStart(e) {
            draggedElement = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        }

        function handleDragEnd(e) {
            this.classList.remove('dragging');
            saveWidgetLayout();
        }

        function handleDragOver(e) {
            if (e.preventDefault) {
                e.preventDefault();
            }
            e.dataTransfer.dropEffect = 'move';
            return false;
        }

        function handleDrop(e) {
            if (e.stopPropagation) {
                e.stopPropagation();
            }

            if (draggedElement !== this) {
                let allWidgets = Array.from(container.children);
                let draggedIndex = allWidgets.indexOf(draggedElement);
                let targetIndex = allWidgets.indexOf(this);

                if (draggedIndex < targetIndex) {
                    this.parentNode.insertBefore(draggedElement, this.nextSibling);
                } else {
                    this.parentNode.insertBefore(draggedElement, this);
                }
            }

            return false;
        }

        function saveWidgetLayout() {
            const layout = Array.from(widgets).map(w => w.dataset.widget);
            localStorage.setItem('studentDashboardLayout', JSON.stringify(layout));
        }

        function loadWidgetLayout() {
            const saved = localStorage.getItem('studentDashboardLayout');
            if (saved) {
                const layout = JSON.parse(saved);
                layout.forEach(widgetId => {
                    const widget = document.querySelector(`[data-widget="${widgetId}"]`);
                    if (widget) {
                        container.appendChild(widget);
                    }
                });
            }
        }

        // Notification functions
        function markAsRead(notificationId) {
            fetch('../api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    id: notificationId
                })
            }).then(() => location.reload());
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

        // Load saved layout on page load
        document.addEventListener('DOMContentLoaded', loadWidgetLayout);

        // Notification bell click
        document.getElementById('notificationBell')?.addEventListener('click', function() {
            window.location.href = 'notifications.php';
        });

        // Customize button
        document.getElementById('customizeWidgets')?.addEventListener('click', function() {
            alert('Drag and drop widgets to rearrange them! Your layout will be saved automatically.');
        });
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>