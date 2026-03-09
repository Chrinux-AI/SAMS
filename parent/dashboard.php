<?php

/**
 * SAMS Parent Dashboard - Modern AI-Enhanced Interface
 * Professional dashboard with AI insights and modern UI
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_login('../login.php');
if (!has_role('parent')) {
    redirect('../login.php', 'Parent access required.', 'error');
}

$parent_id = $_SESSION['user_id'];
$tenantId = $_SESSION['tenant_id'] ?? 1;
$full_name = $_SESSION['full_name'];

// Get children
$children = db()->fetchAll("
    SELECT u.id, u.first_name, u.last_name, s.admission_number as student_id, c.grade_level, c.class_name
    FROM users u
    JOIN students s ON u.id = s.user_id
    JOIN parent_student_links psl ON s.user_id = psl.student_id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE psl.parent_id = ? AND u.status = 'active'
", [$parent_id]);

// Get today's attendance for all children
$today = date('Y-m-d');
$child_ids = array_column($children, 'id');
$today_attendance = [];

if (!empty($child_ids)) {
    $placeholders = implode(',', array_fill(0, count($child_ids), '?'));
    $params = array_merge($child_ids, [$today]);

    $today_attendance = db()->fetchAll("
        SELECT ar.*, u.first_name, u.last_name, c.class_name
        FROM attendance_records ar
        JOIN users u ON ar.student_id = u.id
        JOIN classes c ON ar.class_id = c.id
        WHERE ar.student_id IN ($placeholders) AND DATE(ar.check_in_time) = ?
    ", $params);
}

// Calculate stats
$total_present = count(array_filter($today_attendance, fn($r) => $r['status'] === 'present'));
$total_late = count(array_filter($today_attendance, fn($r) => $r['status'] === 'late'));
$total_absent = count(array_filter($today_attendance, fn($r) => $r['status'] === 'absent'));
$today_total = count($today_attendance);
$today_rate = $today_total > 0 ? round((($total_present + $total_late) / $today_total) * 100, 1) : 0;

// Unread messages
$unread_count = db()->fetchOne("
    SELECT COUNT(*) as count FROM message_recipients
    WHERE recipient_id = ? AND is_read = 0 AND deleted_at IS NULL
", [$parent_id])['count'] ?? 0;

// Recent communications
$recent_communications = db()->fetchAll("
    SELECT m.*, u.first_name as sender_first, u.last_name as sender_last,
           mr.is_read, mr.read_at
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    JOIN message_recipients mr ON m.id = mr.message_id
    WHERE mr.recipient_id = ?
    ORDER BY m.created_at DESC
    LIMIT 5
", [$parent_id]);

// Upcoming events/assignments for children
$upcoming_events = [];
if (!empty($child_ids)) {
    $placeholders = implode(',', array_fill(0, count($child_ids), '?'));
    $upcoming_events = db()->fetchAll("
        SELECT a.*, c.class_name, u.first_name, u.last_name
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        JOIN class_enrollments ce ON c.id = ce.class_id
        JOIN users u ON ce.student_id = u.id
        WHERE ce.student_id IN ($placeholders) AND a.due_date >= CURDATE()
        ORDER BY a.due_date ASC
        LIMIT 5
    ", $child_ids);
}

// AI Insights
$ai_insights = [];
try {
    require_once __DIR__ . '/../includes/sams-init.php';
    try {
        if (class_exists('SAMS_ParentBot')) {
            $parentBot = new SAMS_ParentBot();
            $ai_insights = $parentBot->getParentInsights($parent_id, $tenantId);
        }
    } catch (Throwable $e) {
        // Fallback insights
        $ai_insights = [
            'engagement_level' => $today_rate > 90 ? 'highly_engaged' : ($today_rate > 75 ? 'moderately_engaged' : 'needs_attention'),
            'recommendation' => 'Regular communication with teachers helps support your child\'s success',
            'children_status' => count($children) > 1 ? 'monitoring_multiple' : 'focused_support'
        ];
    }
} catch (Throwable $e) {
    $ai_insights = [
        'engagement_level' => 'moderately_engaged',
        'recommendation' => 'Stay involved in your child\'s education journey',
        'children_status' => 'active_monitoring'
    ];
}

$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/favicon-loader.php'; ?>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
    <style>
        .parent-header {
            background: linear-gradient(135deg, #7C3AED, #A78BFA);
            color: #fff;
            padding: 2rem;
            border-radius: var(--radius-xl, 16px);
            margin-bottom: 2rem;
        }

        .ai-family-assistant {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border: 1px solid #FCD34D;
            border-radius: var(--radius-lg, 12px);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .ai-family-assistant h3 {
            color: #92400E;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--color-surface, #fff);
            border: 1px solid var(--color-border, #e5e7eb);
            border-radius: var(--radius-lg, 12px);
            padding: 1.5rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #7C3AED;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--color-text-secondary, #6b7280);
            margin-top: 0.5rem;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: var(--color-surface, #fff);
            border: 1px solid var(--color-border, #e5e7eb);
            border-radius: var(--radius-lg, 12px);
            padding: 1.25rem;
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s;
        }

        .action-card:hover {
            border-color: #7C3AED;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.1);
        }

        .action-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md, 8px);
            background: #F3E8FF;
            color: #7C3AED;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .children-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .child-card {
            background: var(--color-surface, #fff);
            border: 1px solid var(--color-border, #e5e7eb);
            border-radius: var(--radius-lg, 12px);
            padding: 1.5rem;
        }

        .child-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .child-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #7C3AED, #A78BFA);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.25rem;
        }

        .child-info h4 {
            margin: 0 0 0.25rem 0;
            font-weight: 600;
        }

        .child-info p {
            margin: 0;
            font-size: 0.875rem;
            color: var(--color-text-secondary, #6b7280);
        }

        .attendance-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md, 8px);
            font-size: 0.875rem;
            font-weight: 600;
        }

        .attendance-status.present {
            background: #D1FAE5;
            color: #059669;
        }

        .attendance-status.absent {
            background: #FEE2E2;
            color: #DC2626;
        }

        .attendance-status.late {
            background: #FEF3C7;
            color: #D97706;
        }

        .attendance-status.unknown {
            background: #F3F4F6;
            color: #6B7280;
        }

        .engagement-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-md, 8px);
            font-size: 0.875rem;
            font-weight: 600;
        }

        .engagement-indicator.highly_engaged {
            background: #D1FAE5;
            color: #059669;
        }

        .engagement-indicator.moderately_engaged {
            background: #DBEAFE;
            color: #2563EB;
        }

        .engagement-indicator.needs_attention {
            background: #FEE2E2;
            color: #DC2626;
        }

        .recent-activity {
            background: var(--color-surface, #fff);
            border: 1px solid var(--color-border, #e5e7eb);
            border-radius: var(--radius-lg, 12px);
            padding: 1.5rem;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--color-border, #e5e7eb);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
        }

        .message-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .message-badge.unread {
            background: #FEE2E2;
            color: #DC2626;
        }

        .message-badge.read {
            background: #F3F4F6;
            color: #6B7280;
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <div class="cyber-header">
                <div class="page-icon-orb"><i class="fas fa-user-friends"></i></div>
                <div>
                    <h1>Parent Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($full_name); ?>!</p>
                </div>
            </div>

            <div class="cyber-content" style="max-width: 1400px; margin: 0 auto; padding: 24px;">

                <div class="parent-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h1><i class="fas fa-user-friends"></i> Parent Dashboard</h1>
                            <p>Monitor your children's progress and stay connected with their school</p>
                        </div>
                        <div style="text-align: right;">
                            <div class="engagement-indicator <?php echo htmlspecialchars($ai_insights['engagement_level'] ?? 'moderately_engaged'); ?>">
                                <i class="fas fa-heart"></i>
                                Engagement: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $ai_insights['engagement_level'] ?? 'moderately_engaged'))); ?>
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.875rem; opacity: 0.9;">
                                <?php echo count($children); ?> Children • <?php echo $unread_count; ?> Messages
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Family Assistant -->
                <div class="ai-family-assistant">
                    <h3><i class="fas fa-robot"></i> AI Family Assistant</h3>
                    <p><?php echo htmlspecialchars($ai_insights['recommendation'] ?? 'Regular communication with teachers helps support your children\'s success. Stay involved in their educational journey.'); ?></p>
                    <div style="margin-top: 1rem; font-size: 0.875rem; color: #92400E;">
                        <strong>Tip:</strong> Check your children's attendance daily and communicate regularly with their teachers.
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($children); ?></div>
                        <div class="stat-label">Children</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $today_rate; ?>%</div>
                        <div class="stat-label">Today's Attendance</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $total_present; ?></div>
                        <div class="stat-label">Present Today</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $unread_count; ?></div>
                        <div class="stat-label">Unread Messages</div>
                    </div>
                </div>

                <!-- Children Overview -->
                <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem;">
                    <i class="fas fa-users"></i> Your Children
                </h3>
                <div class="children-grid">
                    <?php foreach ($children as $child): ?>
                        <?php
                        $child_attendance = null;
                        foreach ($today_attendance as $att) {
                            if ($att['student_id'] == $child['id']) {
                                $child_attendance = $att;
                                break;
                            }
                        }
                        ?>
                        <div class="child-card">
                            <div class="child-header">
                                <div class="child-avatar">
                                    <?php echo strtoupper(substr($child['first_name'], 0, 1) . substr($child['last_name'], 0, 1)); ?>
                                </div>
                                <div class="child-info">
                                    <h4><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($child['class_name'] ?? 'Not assigned'); ?> • ID: <?php echo htmlspecialchars($child['student_id']); ?></p>
                                </div>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <strong>Today:</strong>
                                <?php if ($child_attendance): ?>
                                    <span class="attendance-status <?php echo htmlspecialchars($child_attendance['status']); ?>">
                                        <i class="fas fa-<?php echo $child_attendance['status'] === 'present' ? 'check' : ($child_attendance['status'] === 'late' ? 'clock' : 'times'); ?>"></i>
                                        <?php echo htmlspecialchars(ucfirst($child_attendance['status'])); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="attendance-status unknown">
                                        <i class="fas fa-clock"></i>
                                        Not marked yet
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="child-details.php?student_id=<?php echo $child['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                <a href="messages.php?compose=<?php echo $child['id']; ?>" class="btn btn-sm btn-outline">Message Teacher</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="attendance.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-calendar-check"></i></div>
                        <div>
                            <div style="font-weight: 600;">Attendance</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">View attendance history</div>
                        </div>
                    </a>
                    <a href="../messages.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-envelope"></i></div>
                        <div>
                            <div style="font-weight: 600;">Messages</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">
                                <?php echo $unread_count > 0 ? "$unread_count unread" : 'No new'; ?>
                            </div>
                        </div>
                    </a>
                    <a href="grades.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-chart-line"></i></div>
                        <div>
                            <div style="font-weight: 600;">Grades</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">View academic progress</div>
                        </div>
                    </a>
                    <a href="communication.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-comments"></i></div>
                        <div>
                            <div style="font-weight: 600;">Teachers</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Contact teachers</div>
                        </div>
                    </a>
                    <a href="../forum/index.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-comments"></i></div>
                        <div>
                            <div style="font-weight: 600;">Forum</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Parent discussions</div>
                        </div>
                    </a>
                    <a href="reports.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-file-alt"></i></div>
                        <div>
                            <div style="font-weight: 600;">Reports</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Generate reports</div>
                        </div>
                    </a>
                    <a href="settings.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-cog"></i></div>
                        <div>
                            <div style="font-weight: 600;">Settings</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Manage preferences</div>
                        </div>
                    </a>
                </div>

                <!-- Recent Communications -->
                <div class="recent-activity">
                    <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-comments"></i> Recent Communications
                    </h3>

                    <?php if (!empty($recent_communications)): ?>
                        <?php foreach (array_slice($recent_communications, 0, 4) as $comm): ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: #F3E8FF; color: #7C3AED;">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600;">
                                    <?php echo htmlspecialchars($comm['subject'] ?? 'No Subject'); ?>
                                    <span class="message-badge <?php echo $comm['is_read'] ? 'read' : 'unread'; ?>">
                                        <?php echo $comm['is_read'] ? 'Read' : 'New'; ?>
                                    </span>
                                </div>
                                <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">
                                    From: <?php echo htmlspecialchars($comm['sender_first'] . ' ' . $comm['sender_last']); ?>
                                </div>
                            </div>
                            <div style="font-size: 0.875rem; color: var(--color-text-muted, #9ca3af);">
                                <?php echo date('M j, g:i A', strtotime($comm['created_at'] ?? 'now')); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; color: var(--color-text-secondary, #6b7280);">
                            <i class="fas fa-envelope-open-text" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                            <p>No recent communications. Teachers may reach out with updates soon!</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
    <script src="../assets/js/main.js"></script>
</body>
</html>
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#4F46E5">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
</head>

<body>
    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <div class="page-title-area">
                    <button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active'); document.querySelector('.sidebar-overlay').classList.toggle('active');">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="page-icon"><i class="fas fa-home"></i></div>
                    <div>
                        <h1>Parent Dashboard</h1>
                        <p class="page-subtitle">Welcome, <?php echo htmlspecialchars($full_name); ?></p>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="../messages.php" class="btn-icon" title="Messages">
                        <i class="fas fa-envelope"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="datetime-display">
                        <div class="date-text"><?php echo date('l, M j, Y'); ?></div>
                        <div class="time-text" id="live-time"><?php echo date('h:i A'); ?></div>
                    </div>
                </div>
            </header>

            <div class="content-wrapper fade-in">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon purple"><i class="fas fa-child"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">My Children</div>
                            <div class="stat-value"><?php echo count($children); ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fas fa-check"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Present Today</div>
                            <div class="stat-value"><?php echo $total_present; ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Late Today</div>
                            <div class="stat-value"><?php echo $total_late; ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fas fa-times"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Absent Today</div>
                            <div class="stat-value"><?php echo $total_absent; ?></div>
                        </div>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-users"></i> My Children</h3>
                            <a href="children.php" class="btn btn-sm btn-outline">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($children)): ?>
                                <div class="empty-state"><i class="fas fa-user-slash"></i>
                                    <p>No children linked to your account</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($children as $child): ?>
                                        <div class="list-item">
                                            <div class="item-icon"><i class="fas fa-user-graduate"></i></div>
                                            <div class="item-content">
                                                <div class="item-title"><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></div>
                                                <div class="item-subtitle">ID: <?php echo htmlspecialchars($child['student_id']); ?> &middot; Grade: <?php echo htmlspecialchars($child['grade_level'] ?? 'N/A'); ?></div>
                                            </div>
                                            <a href="attendance.php?student=<?php echo $child['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-calendar-day"></i> Today's Attendance</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($today_attendance)): ?>
                                <div class="empty-state"><i class="fas fa-clipboard"></i>
                                    <p>No attendance records for today</p>
                                </div>
                            <?php else: ?>
                                <table class="data-table compact">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Class</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($today_attendance as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['first_name']); ?></td>
                                                <td><?php echo htmlspecialchars($record['class_name']); ?></td>
                                                <td><span class="status-badge <?php echo $record['status']; ?>"><?php echo ucfirst($record['status']); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        setInterval(() => {
            const el = document.getElementById('live-time');
            if (el) el.textContent = new Date().toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }, 1000);
    </script>
<script src="../assets/js/main.js"></script>
</body>

</html>
