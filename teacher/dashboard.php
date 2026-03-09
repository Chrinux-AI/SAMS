<?php

/**
 * Teacher Dashboard - Professional UI
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_teacher();

$teacher_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

$teacher = db()->fetchOne("SELECT * FROM teachers WHERE user_id = ?", [$teacher_id]);

$my_classes = db()->fetchAll("
    SELECT c.id, c.class_name, c.class_code, c.teacher_id, c.description, c.schedule, c.room, c.created_at,
           COUNT(DISTINCT ce.student_id) as student_count
    FROM classes c
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE c.teacher_id = ?
    GROUP BY c.id, c.class_name, c.class_code, c.teacher_id, c.description, c.schedule, c.room, c.created_at
", [$teacher_id]);

$total_students_result = db()->fetchOne("
    SELECT COUNT(DISTINCT ce.student_id) as total
    FROM class_enrollments ce JOIN classes c ON ce.class_id = c.id
    WHERE c.teacher_id = ?
", [$teacher_id]);
$total_students = $total_students_result['total'] ?? 0;

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

$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$week_attendance = db()->fetchAll("
    SELECT DATE(ar.check_in_time) as date, ar.status, COUNT(*) as count
    FROM attendance_records ar JOIN classes c ON ar.class_id = c.id
    WHERE c.teacher_id = ? AND DATE(ar.check_in_time) BETWEEN ? AND ?
    GROUP BY DATE(ar.check_in_time), ar.status
", [$teacher_id, $week_start, $week_end]);

$unread_count = db()->fetchOne("
    SELECT COUNT(*) as count FROM message_recipients mr
    WHERE mr.recipient_id = ? AND mr.is_read = 0 AND mr.deleted_at IS NULL
", [$teacher_id])['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - <?php echo APP_NAME; ?></title>
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
                    <div class="page-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div>
                        <h1>Teacher Dashboard</h1>
                        <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($full_name); ?></p>
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
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fas fa-door-open"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">My Classes</div>
                            <div class="stat-value"><?php echo count($my_classes); ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fas fa-user-graduate"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Total Students</div>
                            <div class="stat-value"><?php echo $total_students; ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple"><i class="fas fa-clipboard-check"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Today's Rate</div>
                            <div class="stat-value"><?php echo $today_rate; ?>%</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fas fa-user-times"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Today Absent</div>
                            <div class="stat-value"><?php echo $today_absent; ?></div>
                        </div>
                    </div>
                </div>

                <div class="grid-2">
                    <!-- My Classes -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-door-open"></i> My Classes</h3>
                            <a href="my-classes.php" class="btn btn-sm btn-outline">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($my_classes)): ?>
                                <div class="empty-state"><i class="fas fa-door-closed"></i>
                                    <p>No classes assigned yet</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach (array_slice($my_classes, 0, 5) as $class): ?>
                                        <div class="list-item">
                                            <div class="item-icon"><i class="fas fa-book"></i></div>
                                            <div class="item-content">
                                                <div class="item-title"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                                <div class="item-subtitle"><?php echo htmlspecialchars($class['class_code']); ?> &middot; <?php echo $class['student_count']; ?> students</div>
                                            </div>
                                            <a href="attendance.php?class=<?php echo $class['id']; ?>" class="btn btn-sm btn-primary">Mark</a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Today's Log -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-calendar-day"></i> Today's Attendance</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($today_attendance)): ?>
                                <div class="empty-state"><i class="fas fa-clipboard"></i>
                                    <p>No records for today</p>
                                </div>
                            <?php else: ?>
                                <table class="data-table compact">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Class</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($today_attendance, 0, 8) as $record): ?>
                                            <tr>
                                                <td><?php echo date('h:i A', strtotime($record['check_in_time'])); ?></td>
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

                <!-- Weekly Trend -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-chart-line"></i> Weekly Attendance Trend</h3>
                        <a href="reports.php" class="btn btn-sm btn-outline">Full Report</a>
                    </div>
                    <div class="card-body">
                        <div class="trend-chart">
                            <?php
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                            foreach ($days as $day):
                                $day_date = date('Y-m-d', strtotime($day . ' this week'));
                                $day_records = array_filter($week_attendance, fn($r) => $r['date'] === $day_date);
                                $day_total = array_sum(array_column($day_records, 'count'));
                                $day_present = array_sum(array_column(array_filter($day_records, fn($r) => $r['status'] === 'present'), 'count'));
                                $day_percentage = $day_total > 0 ? round(($day_present / $day_total) * 100) : 0;
                            ?>
                                <div class="day-bar">
                                    <div class="day-label"><?php echo $day; ?></div>
                                    <div class="bar-container">
                                        <div class="bar-fill" style="width: <?php echo $day_percentage; ?>%"></div>
                                        <div class="bar-value"><?php echo $day_percentage; ?>%</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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
