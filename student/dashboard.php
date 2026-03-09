<?php

/**
 * SAMS Student Dashboard - Modern AI-Enhanced Interface
 * Professional dashboard with AI insights and modern UI
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_login('../login.php');
if (!has_role('student')) {
    redirect('../login.php', 'Student access required.', 'error');
}

$student_id = $_SESSION['user_id'];
$tenantId = $_SESSION['tenant_id'] ?? 1;
$full_name = $_SESSION['full_name'];

// Get student data
$student = db()->fetchOne("
    SELECT s.*, u.email, u.created_at
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ?
", [$student_id]);

// Attendance summary
$records = db()->fetchAll("SELECT * FROM attendance_records WHERE student_id = ?", [$student_id]);
$present = count(array_filter($records, fn($r) => $r['status'] === 'present'));
$late = count(array_filter($records, fn($r) => $r['status'] === 'late'));
$absent = count(array_filter($records, fn($r) => $r['status'] === 'absent'));
$total = count($records);
$attendance_rate = $total > 0 ? round((($present + $late) / $total) * 100, 1) : 0;

// Student's classes
$classes = db()->fetchAll("
    SELECT c.*, ce.enrollment_date
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ?
", [$student_id]);

// Today's attendance
$today = date('Y-m-d');
$today_record = db()->fetchOne("
    SELECT ar.*, c.class_name
    FROM attendance_records ar
    JOIN classes c ON ar.class_id = c.id
    WHERE ar.student_id = ? AND DATE(ar.check_in_time) = ?
    ORDER BY ar.check_in_time DESC
", [$student_id, $today]);

// Recent grades
$recent_grades = db()->fetchAll("
    SELECT g.*, a.title, c.class_name
    FROM grades g
    JOIN assignments a ON g.assignment_id = a.id
    JOIN classes c ON a.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ?
    ORDER BY g.created_at DESC
    LIMIT 5
", [$student_id]);

// Upcoming assignments
$upcoming_assignments = db()->fetchAll("
    SELECT a.*, c.class_name
    FROM assignments a
    JOIN classes c ON a.class_id = c.id
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ? AND a.due_date >= CURDATE()
    ORDER BY a.due_date ASC
    LIMIT 5
", [$student_id]);

// Unread messages
$unread_count = db()->fetchOne("
    SELECT COUNT(*) as count FROM message_recipients
    WHERE recipient_id = ? AND is_read = 0 AND deleted_at IS NULL
", [$student_id])['count'] ?? 0;

// AI Insights
$ai_insights = [];
try {
    require_once __DIR__ . '/../includes/sams-init.php';
    try {
        if (class_exists('SAMS_StudentBot')) {
            $studentBot = new SAMS_StudentBot();
            $ai_insights = $studentBot->getStudentInsights($student_id, $tenantId);
        }
    } catch (Throwable $e) {
        // Fallback insights
        $ai_insights = [
            'performance_trend' => $attendance_rate > 85 ? 'excellent' : ($attendance_rate > 70 ? 'good' : 'needs_improvement'),
            'recommendation' => 'Focus on maintaining consistent attendance',
            'study_suggestions' => ['Review daily notes', 'Complete assignments on time', 'Ask for help when needed']
        ];
    }
} catch (Throwable $e) {
    $ai_insights = [
        'performance_trend' => 'good',
        'recommendation' => 'Keep up the good work!',
        'study_suggestions' => ['Stay organized', 'Participate in class', 'Study regularly']
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
    <title>Student Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/professional-ui.css">
    <link rel="stylesheet" href="../assets/css/sidebar-nav.css">
    <style>
        .student-header {
            background: linear-gradient(135deg, #4F46E5, #6366F1);
            color: #fff;
            padding: 2rem;
            border-radius: var(--radius-xl, 16px);
            margin-bottom: 2rem;
        }

        .ai-assistant-card {
            background: linear-gradient(135deg, #F3E8FF, #E9D5FF);
            border: 1px solid #C084FC;
            border-radius: var(--radius-lg, 12px);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .ai-assistant-card h3 {
            color: #6B21A8;
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
            color: #4F46E5;
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
            border-color: #4F46E5;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.1);
        }

        .action-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md, 8px);
            background: #E0E7FF;
            color: #4F46E5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .today-status {
            background: var(--color-surface, #fff);
            border: 1px solid var(--color-border, #e5e7eb);
            border-radius: var(--radius-lg, 12px);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md, 8px);
            font-weight: 600;
            font-size: 1rem;
        }

        .status-badge.present {
            background: #D1FAE5;
            color: #059669;
        }

        .status-badge.absent {
            background: #FEE2E2;
            color: #DC2626;
        }

        .status-badge.late {
            background: #FEF3C7;
            color: #D97706;
        }

        .status-badge.not-marked {
            background: #F3F4F6;
            color: #6B7280;
        }

        .performance-trend {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-md, 8px);
            font-size: 0.875rem;
            font-weight: 600;
        }

        .performance-trend.excellent {
            background: #D1FAE5;
            color: #059669;
        }

        .performance-trend.good {
            background: #DBEAFE;
            color: #2563EB;
        }

        .performance-trend.needs_improvement {
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

        .study-suggestions {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
        }

        .study-suggestions li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
            color: #6B21A8;
            font-size: 0.9rem;
        }

        .grade-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .grade-badge.A { background: #D1FAE5; color: #059669; }
        .grade-badge.B { background: #DBEAFE; color: #2563EB; }
        .grade-badge.C { background: #FEF3C7; color: #D97706; }
        .grade-badge.D { background: #FED7AA; color: #EA580C; }
        .grade-badge.F { background: #FEE2E2; color: #DC2626; }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <div class="cyber-header">
                <div class="page-icon-orb"><i class="fas fa-user-graduate"></i></div>
                <div>
                    <h1>Student Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($full_name); ?>!</p>
                </div>
            </div>

            <div class="cyber-content" style="max-width: 1400px; margin: 0 auto; padding: 24px;">

                <div class="student-header">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h1><i class="fas fa-user-graduate"></i> Student Dashboard</h1>
                            <p>Track your attendance, grades, and assignments</p>
                        </div>
                        <div style="text-align: right;">
                            <div class="performance-trend <?php echo htmlspecialchars($ai_insights['performance_trend'] ?? 'good'); ?>">
                                <i class="fas fa-chart-line"></i>
                                Performance: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $ai_insights['performance_trend'] ?? 'good'))); ?>
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.875rem; opacity: 0.9;">
                                <?php echo count($classes); ?> Classes • <?php echo $attendance_rate; ?>% Attendance
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Student Assistant -->
                <div class="ai-assistant-card">
                    <h3><i class="fas fa-robot"></i> AI Study Assistant</h3>
                    <p><?php echo htmlspecialchars($ai_insights['recommendation'] ?? 'Keep up the good work! Continue maintaining consistent attendance and complete assignments on time.'); ?></p>
                    <?php if (!empty($ai_insights['study_suggestions'])): ?>
                    <div style="margin-top: 1rem;">
                        <strong>Study Tips:</strong>
                        <ul class="study-suggestions">
                            <?php foreach ($ai_insights['study_suggestions'] as $tip): ?>
                            <li><i class="fas fa-lightbulb"></i> <?php echo htmlspecialchars($tip); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Today's Status -->
                <div class="today-status">
                    <h3 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-calendar-day"></i> Today's Status
                    </h3>
                    <div style="display: flex; align-items: center; gap: 2rem; flex-wrap: wrap;">
                        <div>
                            <strong>Attendance:</strong>
                            <?php if ($today_record): ?>
                                <span class="status-badge <?php echo htmlspecialchars($today_record['status']); ?>">
                                    <i class="fas fa-<?php echo $today_record['status'] === 'present' ? 'check' : ($today_record['status'] === 'late' ? 'clock' : 'times'); ?>"></i>
                                    <?php echo htmlspecialchars(ucfirst($today_record['status'])); ?>
                                </span>
                            <?php else: ?>
                                <span class="status-badge not-marked">
                                    <i class="fas fa-clock"></i>
                                    Not marked yet
                                </span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <strong>Classes Today:</strong> <?php echo count($classes); ?>
                        </div>
                        <div>
                            <strong>Unread Messages:</strong> <?php echo $unread_count; ?>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $attendance_rate; ?>%</div>
                        <div class="stat-label">Attendance Rate</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($classes); ?></div>
                        <div class="stat-label">Enrolled Classes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($upcoming_assignments); ?></div>
                        <div class="stat-label">Pending Tasks</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $unread_count; ?></div>
                        <div class="stat-label">Unread Messages</div>
                    </div>
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
                    <a href="grades.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-chart-line"></i></div>
                        <div>
                            <div style="font-weight: 600;">Grades</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Check your grades</div>
                        </div>
                    </a>
                    <a href="assignments.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-tasks"></i></div>
                        <div>
                            <div style="font-weight: 600;">Assignments</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">View and submit work</div>
                        </div>
                    </a>
                    <a href="schedule.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div>
                            <div style="font-weight: 600;">Schedule</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">View class schedule</div>
                        </div>
                    </a>
                    <a href="../messages.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-envelope"></i></div>
                        <div>
                            <div style="font-weight: 600;">Messages</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">
                                <?php echo $unread_count > 0 ? "$unread_count new" : 'No new'; ?>
                            </div>
                        </div>
                    </a>
                    <a href="../forum/index.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-comments"></i></div>
                        <div>
                            <div style="font-weight: 600;">Forum</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Community discussions</div>
                        </div>
                    </a>
                    <a href="profile.php" class="action-card">
                        <div class="action-icon"><i class="fas fa-user"></i></div>
                        <div>
                            <div style="font-weight: 600;">Profile</div>
                            <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">Update your info</div>
                        </div>
                    </a>
                </div>

                <!-- Recent Activity -->
                <div class="recent-activity">
                    <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-clock"></i> Recent Activity
                    </h3>

                    <?php if (!empty($recent_grades)): ?>
                        <?php foreach (array_slice($recent_grades, 0, 3) as $grade): ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: #D1FAE5; color: #059669;">
                                <i class="fas fa-star"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600;">
                                    <?php echo htmlspecialchars($grade['title'] ?? 'Assignment'); ?>
                                    <span class="grade-badge <?php echo htmlspecialchars($grade['grade'] ?? 'A'); ?>">
                                        <?php echo htmlspecialchars($grade['grade'] ?? 'A'); ?>
                                    </span>
                                </div>
                                <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">
                                    <?php echo htmlspecialchars($grade['class_name'] ?? 'Class'); ?>
                                </div>
                            </div>
                            <div style="font-size: 0.875rem; color: var(--color-text-muted, #9ca3af);">
                                <?php echo date('M j', strtotime($grade['created_at'] ?? 'now')); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($upcoming_assignments)): ?>
                        <?php foreach (array_slice($upcoming_assignments, 0, 2) as $assignment): ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: #DBEAFE; color: #2563EB;">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($assignment['title'] ?? 'Assignment'); ?></div>
                                <div style="font-size: 0.875rem; color: var(--color-text-secondary, #6b7280);">
                                    Due: <?php echo date('M j', strtotime($assignment['due_date'] ?? 'now')); ?>
                                </div>
                            </div>
                            <div style="font-size: 0.875rem; color: var(--color-text-muted, #9ca3af);">
                                <?php echo htmlspecialchars($assignment['class_name'] ?? 'Class'); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (empty($recent_grades) && empty($upcoming_assignments)): ?>
                        <div style="text-align: center; padding: 2rem; color: var(--color-text-secondary, #6b7280);">
                            <i class="fas fa-calendar-check" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                            <p>No recent activity. Check back soon for updates!</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
    <script src="../assets/js/main.js"></script>
</body>
</html>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo APP_NAME; ?></title>
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
                    <div class="page-icon"><i class="fas fa-user-graduate"></i></div>
                    <div>
                        <h1>My Dashboard</h1>
                        <p class="page-subtitle">Welcome, <?php echo htmlspecialchars($full_name); ?></p>
                    </div>
                </div>
                <div class="header-actions">
                    <div class="datetime-display">
                        <div class="date-text"><?php echo date('l, M j, Y'); ?></div>
                        <div class="time-text"><?php echo date('h:i A'); ?></div>
                    </div>
                    <div class="header-user">
                        <div class="avatar"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
                    </div>
                </div>
            </header>

            <div class="content-wrapper fade-in">
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Days Present</div>
                            <div class="stat-value"><?php echo $present; ?></div>
                            <div class="stat-trend up"><i class="fas fa-check"></i> Good standing</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Times Late</div>
                            <div class="stat-value"><?php echo $late; ?></div>
                            <div class="stat-trend <?php echo $late > 5 ? 'down' : 'up'; ?>">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $late > 5 ? 'Needs improvement' : 'Good'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Days Absent</div>
                            <div class="stat-value"><?php echo $absent; ?></div>
                            <div class="stat-trend <?php echo $absent > 3 ? 'down' : 'up'; ?>">
                                <i class="fas fa-info-circle"></i> <?php echo $absent > 3 ? 'High' : 'Low'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fas fa-chart-pie"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Attendance Rate</div>
                            <div class="stat-value"><?php echo $attendance_rate; ?>%</div>
                            <div class="stat-trend <?php echo $attendance_rate >= 90 ? 'up' : 'down'; ?>">
                                <i class="fas fa-star"></i> <?php echo $attendance_rate >= 90 ? 'Excellent' : 'Improve'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="grid-3" style="margin-bottom: 28px;">
                    <a href="checkin.php" class="action-card">
                        <div class="action-icon" style="background: var(--primary-50); color: var(--primary);"><i class="fas fa-fingerprint"></i></div>
                        <span class="action-label">Check In Now</span>
                    </a>
                    <a href="attendance.php" class="action-card">
                        <div class="action-icon" style="background: var(--success-light); color: var(--success);"><i class="fas fa-clipboard-check"></i></div>
                        <span class="action-label">View Attendance</span>
                    </a>
                    <a href="schedule.php" class="action-card">
                        <div class="action-icon" style="background: var(--warning-light); color: var(--warning);"><i class="fas fa-calendar-alt"></i></div>
                        <span class="action-label">Class Schedule</span>
                    </a>
                </div>

                <!-- My Classes -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-book"></i> My Classes</h3>
                        <span class="badge badge-primary"><?php echo count($classes); ?> enrolled</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($classes)): ?>
                            <div class="list-group">
                                <?php foreach ($classes as $class): ?>
                                    <div class="list-item">
                                        <div class="item-icon"><i class="fas fa-book-open"></i></div>
                                        <div class="item-content">
                                            <div class="item-title"><?php echo htmlspecialchars($class['class_name'] ?? $class['name'] ?? 'Unnamed'); ?></div>
                                            <div class="item-subtitle"><?php echo htmlspecialchars($class['class_code']); ?> &middot; Room: <?php echo htmlspecialchars($class['room'] ?? $class['room_number'] ?? 'TBA'); ?></div>
                                        </div>
                                        <span class="badge badge-neutral"><?php echo htmlspecialchars((string)($class['grade_level'] ?? 'N/A')); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-book"></i>
                                <p>No classes enrolled yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
<script src="../assets/js/main.js"></script>
</body>

</html>
