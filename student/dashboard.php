<?php

/**
 * Student Dashboard - Professional UI
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_student();

$student_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Attendance summary
$records = db()->fetchAll("SELECT * FROM attendance_records WHERE student_id = ?", [$student_id]);
$present = count(array_filter($records, fn($r) => $r['status'] === 'present'));
$late = count(array_filter($records, fn($r) => $r['status'] === 'late'));
$absent = count(array_filter($records, fn($r) => $r['status'] === 'absent'));
$total = count($records);
$attendance_rate = $total > 0 ? round((($present + $late) / $total) * 100, 1) : 0;

// Student's classes
$classes = db()->fetchAll("
    SELECT c.* FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE ce.student_id = ?
", [$student_id]);
?>
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
