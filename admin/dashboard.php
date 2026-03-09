<?php

/**
 * Admin Dashboard - Professional UI
 */
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Statistics
$total_students = db()->count('students');
$total_classes = db()->count('classes');
$total_teachers = db()->count('users', 'role = :role', ['role' => 'teacher']);

$today = date('Y-m-d');
$today_present = db()->count('attendance_records', 'attendance_date = :date AND status IN ("present", "late")', ['date' => $today]);
$today_total = db()->count('attendance_records', 'attendance_date = :date', ['date' => $today]);
$today_rate = $today_total > 0 ? round(($today_present / $today_total) * 100, 1) : 0;

// Risk students
$risk_students = db()->fetchAll("
    SELECT s.id, s.admission_number, u.first_name, u.last_name,
           COUNT(ar.id) as total_days,
           SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_days
    FROM students s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN attendance_records ar ON s.id = ar.student_id
    WHERE ar.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY s.id, s.admission_number, u.first_name, u.last_name
    HAVING (absent_days / total_days) > 0.1
    ORDER BY absent_days DESC LIMIT 5
");

// Recent activity
$recent_records = db()->fetchAll("
    SELECT ar.*, u.first_name, u.last_name, c.class_name as class_name,
           COALESCE(u.full_name, CONCAT(u.first_name, ' ', u.last_name)) as student_name
    FROM attendance_records ar
    LEFT JOIN students s ON ar.student_id = s.id
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON ar.class_id = c.id
    ORDER BY ar.created_at DESC LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
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
                    <div class="page-icon"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <h1>Dashboard</h1>
                        <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($full_name); ?></p>
                    </div>
                </div>
                <div class="header-actions">
                    <div class="datetime-display">
                        <div class="date-text"><?php echo date('l, M j, Y'); ?></div>
                        <div class="time-text" id="live-time"><?php echo date('h:i A'); ?></div>
                    </div>
                    <div class="header-user">
                        <div class="avatar"><?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
                        <div>
                            <div class="user-name"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="user-role">Administrator</div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="content-wrapper fade-in">
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Total Students</div>
                            <div class="stat-value"><?php echo number_format($total_students); ?></div>
                            <div class="stat-trend up"><i class="fas fa-circle"></i> Active</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fas fa-chart-pie"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Today's Attendance</div>
                            <div class="stat-value"><?php echo $today_rate; ?>%</div>
                            <div class="stat-trend <?php echo $today_rate >= 90 ? 'up' : 'down'; ?>">
                                <i class="fas fa-<?php echo $today_rate >= 90 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo $today_present; ?> of <?php echo $today_total; ?>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">At Risk</div>
                            <div class="stat-value"><?php echo count($risk_students); ?></div>
                            <div class="stat-trend <?php echo count($risk_students) > 0 ? 'down' : 'up'; ?>">
                                <i class="fas fa-user-clock"></i> Last 30 days
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple"><i class="fas fa-door-open"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Active Classes</div>
                            <div class="stat-value"><?php echo number_format($total_classes); ?></div>
                            <div class="stat-trend up"><i class="fas fa-check-circle"></i> Operational</div>
                        </div>
                    </div>
                </div>

                <!-- Main Grid -->
                <div class="grid-2">
                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-clock"></i> Recent Activity</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_records)): ?>
                                <div class="activity-feed">
                                    <?php foreach (array_slice($recent_records, 0, 6) as $record): ?>
                                        <div class="activity-item">
                                            <div class="activity-avatar">
                                                <?php echo strtoupper(substr($record['first_name'] ?? 'U', 0, 1) . substr($record['last_name'] ?? 'N', 0, 1)); ?>
                                            </div>
                                            <div class="activity-content">
                                                <div class="activity-name"><?php echo htmlspecialchars($record['student_name'] ?? 'Unknown'); ?></div>
                                                <div class="activity-desc"><?php echo htmlspecialchars($record['class_name'] ?? 'N/A'); ?></div>
                                            </div>
                                            <span class="status-badge <?php echo $record['status'] ?? ''; ?>">
                                                <?php echo ucfirst($record['status'] ?? 'N/A'); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No recent activity</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- At Risk Students -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-exclamation-triangle"></i> Students at Risk</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($risk_students)): ?>
                                <div class="list-group">
                                    <?php foreach ($risk_students as $student): ?>
                                        <div class="list-item">
                                            <div class="item-icon" style="background: var(--danger-light); color: var(--danger);">
                                                <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
                                            </div>
                                            <div class="item-content">
                                                <div class="item-title"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                <div class="item-subtitle">ID: <?php echo htmlspecialchars($student['admission_number']); ?> &middot; <?php echo $student['absent_days']; ?>/<?php echo $student['total_days']; ?> days absent</div>
                                            </div>
                                            <span class="badge badge-danger"><?php echo round(($student['absent_days'] / $student['total_days']) * 100, 1); ?>%</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                    <p>All students are in good standing</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="action-grid">
                            <a href="students.php" class="action-card">
                                <div class="action-icon"><i class="fas fa-user-graduate"></i></div>
                                <span class="action-label">Manage Students</span>
                            </a>
                            <a href="attendance.php" class="action-card">
                                <div class="action-icon" style="background: var(--success-light); color: var(--success);"><i class="fas fa-check-circle"></i></div>
                                <span class="action-label">Mark Attendance</span>
                            </a>
                            <a href="reports.php" class="action-card">
                                <div class="action-icon" style="background: var(--info-light); color: var(--info);"><i class="fas fa-chart-line"></i></div>
                                <span class="action-label">View Reports</span>
                            </a>
                            <a href="classes.php" class="action-card">
                                <div class="action-icon" style="background: var(--warning-light); color: var(--warning);"><i class="fas fa-door-open"></i></div>
                                <span class="action-label">Manage Classes</span>
                            </a>
                            <a href="analytics.php" class="action-card">
                                <div class="action-icon" style="background: #F3E8FF; color: #7C3AED;"><i class="fas fa-chart-bar"></i></div>
                                <span class="action-label">Analytics</span>
                            </a>
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
