<?php

/**
 * SAMS Teacher Dashboard - Modern AI-Enhanced Interface
 * Professional dashboard with AI insights and modern UI
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_login('../login.php');
if (!has_role('teacher')) {
    redirect('../login.php', 'Teacher access required.', 'error');
}

$teacher_id = $_SESSION['user_id'];
$tenantId = $_SESSION['tenant_id'] ?? 1;
$full_name = $_SESSION['full_name'];

// Get teacher data
$teacher = db()->fetchOne("SELECT * FROM teachers WHERE user_id = ?", [$teacher_id]);

// Get teacher's classes
$my_classes = db()->fetchAll("
    SELECT c.id, c.class_name, c.class_code, c.teacher_id, c.description, c.schedule, c.room, c.created_at,
           COUNT(DISTINCT ce.student_id) as student_count
    FROM classes c
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE c.teacher_id = ?
    GROUP BY c.id, c.class_name, c.class_code, c.teacher_id, c.description, c.schedule, c.room, c.created_at
", [$teacher_id]);

// Get student statistics
$total_students_result = db()->fetchOne("
    SELECT COUNT(DISTINCT ce.student_id) as total
    FROM class_enrollments ce JOIN classes c ON ce.class_id = c.id
    WHERE c.teacher_id = ?
", [$teacher_id]);
$total_students = $total_students_result['total'] ?? 0;

// Today's attendance
$today = date('Y-m-d');
$today_attendance = db()->fetchAll("
    SELECT ar.*, c.class_name
    FROM attendance_records ar JOIN classes c ON ar.class_id = c.id
    WHERE c.teacher_id = ? AND DATE(ar.check_in_time) = ?
    ORDER BY ar.check_in_time DESC
", [$teacher_id, $today]);

$today_present = count(array_filter($today_attendance, fn($r) => $r['status'] === 'present'));
$today_late = count(array_filter($today_attendance, fn($r) => $r['status'] === 'late'));
$today_absent = count(array_filter($today_attendance, fn($r) => $r['status'] === 'absent'));
$today_total = count($today_attendance);
$today_rate = $today_total > 0 ? round((($today_present + $today_late) / $today_total) * 100, 1) : 0;

// Recent messages
$unread_count = db()->fetchOne("
    SELECT COUNT(*) as count FROM message_recipients
    WHERE recipient_id = ? AND is_read = 0 AND deleted_at IS NULL
", [$teacher_id])['count'] ?? 0;

// Recent assignments
$recent_assignments = db()->fetchAll("
    SELECT a.*, c.class_name
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    WHERE c.teacher_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
", [$teacher_id]);

// AI Insights
$ai_insights = [];
try {
    require_once __DIR__ . '/../includes/sams-init.php';
    try {
        if (class_exists('SAMS_TeacherBot')) {
            $teacherBot = new SAMS_TeacherBot();
            $ai_insights = $teacherBot->getTeacherInsights($teacher_id, $tenantId);
        }
    } catch (Throwable $e) {
        $ai_insights = [
            'workload_status' => $total_students > 30 ? 'high' : ($total_students > 15 ? 'moderate' : 'low'),
            'attendance_trend' => $today_rate > 85 ? 'excellent' : ($today_rate > 70 ? 'good' : 'needs_attention'),
            'recommendation' => 'Continue monitoring attendance patterns'
        ];
    }
} catch (Throwable $e) {
    $ai_insights = [
        'workload_status' => 'moderate',
        'attendance_trend' => 'good',
        'recommendation' => 'Focus on student engagement'
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
    <title>Teacher Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
    <style>
        .teacher-header {
            background: linear-gradient(135deg, #059669, #10B981);
            color: #fff;
            padding: 2rem;
            border-radius: var(--radius-xl, 16px);
            margin-bottom: 2rem;
        }

        .ai-insight-card {
            background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
            border: 1px solid #93C5FD;
            border-radius: var(--radius-lg, 12px);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .ai-insight-card h3 {
            color: #1E40AF;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            color: #059669;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--color-text-secondary, #6b7280);
            margin-top: 0.5rem;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            border-color: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.1);
        }

        .action-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md, 8px);
            background: #D1FAE5;
            color: #059669;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
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

        .workload-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-md, 8px);
            font-size: 0.875rem;
            font-weight: 600;
        }

        .workload-indicator.low {
            background: #D1FAE5;
            color: #059669;
        }

        .workload-indicator.moderate {
            background: #FEF3C7;
            color: #D97706;
        }

        .workload-indicator.high {
            background: #FEE2E2;
            color: #DC2626;
        }

        .attendance-trend {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .attendance-trend.up {
            color: #059669;
        }

        .attendance-trend.down {
            color: #DC2626;
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <div class="cyber-header">
                <div class="page-icon-orb"><i class="fas fa-chalkboard-teacher"></i></div>
                <div>
                    <h1>Teacher Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($full_name); ?>!</p>
                </div>
            </div>

            <div class="cyber-content" style="max-width: 1400px; margin: 0 auto; padding: 24px;">

                <div class="teacher-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h1><i class="fas fa-chalkboard-teacher"></i> Teacher Dashboard</h1>
                            <p>Manage your classes, track attendance, and monitor student progress</p>
                        </div>
                        <div style="text-align: right;">
                            <div class="workload-indicator <?php echo htmlspecialchars($ai_insights['workload_status'] ?? 'moderate'); ?>">
                                <i class="fas fa-briefcase"></i>
                                Workload: <?php echo htmlspecialchars(ucfirst($ai_insights['workload_status'] ?? 'moderate')); ?>
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.875rem; opacity: 0.9;">
                                <?php echo count($my_classes); ?> Classes • <?php echo $total_students; ?> Students
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Insights -->
                <div class="ai-insight-card">
                    <h3><i class="fas fa-brain"></i> AI Teaching Assistant</h3>
                    <p><?php echo htmlspecialchars($ai_insights['recommendation'] ?? 'Continue monitoring student engagement and attendance patterns.'); ?></p>
                    <div style="display: flex; gap: 2rem; margin-top: 1rem; flex-wrap: wrap;">
                        <div>
                            <strong>Attendance Trend:</strong>
                            <span class="attendance-trend <?php echo ($ai_insights['attendance_trend'] ?? 'good') === 'excellent' ? 'up' : 'down'; ?>">
                                <i class="fas fa-<?php echo ($ai_insights['attendance_trend'] ?? 'good') === 'excellent' ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo htmlspecialchars(ucfirst($ai_insights['attendance_trend'] ?? 'good')); ?>
                            </span>
                        </div>
                        <div>
                            <strong>Today's Rate:</strong> <?php echo $today_rate; ?>%
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($my_classes); ?></div>
                        <div class="stat-label">My Classes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $total_students; ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $today_rate; ?>%</div>
                        <div class="stat-label">Today's Attendance</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $unread_count; ?></div>
                        <div class="stat-label">Unread Messages</div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="attendance.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-user-check"></i></div>
                        <div>
                            <div style="font-weight: 600;">Take Attendance</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Mark attendance for your classes</div>
                        </div>
                    </a>
                    <a href="grades.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-chart-line"></i></div>
                        <div>
                            <div style="font-weight: 600;">Manage Grades</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Enter and view student grades</div>
                        </div>
                    </a>
                    <a href="assignments.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-tasks"></i></div>
                        <div>
                            <div style="font-weight: 600;">Assignments</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Create and manage assignments</div>
                        </div>
                    </a>
                    <a href="students.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-users"></i></div>
                        <div>
                            <div style="font-weight: 600;">My Students</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">View student information</div>
                        </div>
                    </a>
                    <a href="../messages.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-envelope"></i></div>
                        <div>
                            <div style="font-weight: 600;">Messages</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">
                                <?php echo $unread_count > 0 ? "$unread_count unread" : 'No new messages'; ?>
                            </div>
                        </div>
                    </a>
                    <a href="../forum/index.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-comments"></i></div>
                        <div>
                            <div style="font-weight: 600;">Forum</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Join discussions</div>
                        </div>
                    </a>
                    <a href="reports.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-file-alt"></i></div>
                        <div>
                            <div style="font-weight: 600;">Reports</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Generate class reports</div>
                        </div>
                    </a>
                </div>

                <!-- Recent Activity -->
                <div class="recent-activity">
                    <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-clock"></i> Recent Activity
                    </h3>

                    <?php if (!empty($today_attendance)): ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: #D1FAE5; color: #059669;">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600;">Today's Attendance</div>
                                <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">
                                    <?php echo $today_present; ?> present, <?php echo $today_late; ?> late, <?php echo $today_absent; ?> absent
                                </div>
                            </div>
                            <div style="font-size: 0.875rem; color: var(--color-text-muted, #9ca3af);">
                                <?php echo $today_rate; ?>% rate
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($recent_assignments)): ?>
                        <?php foreach (array_slice($recent_assignments, 0, 3) as $assignment): ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: #DBEAFE; color: #2563EB;">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($assignment['title'] ?? 'Assignment'); ?></div>
                                <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">
                                    <?php echo htmlspecialchars($assignment['class_name'] ?? 'Class'); ?>
                                </div>
                            </div>
                            <div style="font-size: 0.875rem; color: var(--color-text-muted, #9ca3af);">
                                <?php echo date('M j', strtotime($assignment['created_at'] ?? 'now')); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (empty($today_attendance) && empty($recent_assignments)): ?>
                        <div style="text-align: center; padding: 2rem; color: var(--color-text-secondary, #6b7280);">
                            <i class="fas fa-calendar-check" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                            <p>No recent activity. Start by taking attendance or creating an assignment!</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
    <script src="../assets/js/main.js"></script>
</body>
</html>
