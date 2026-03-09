<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_parent();

$parent_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get children
$children = db()->fetchAll("
    SELECT u.id, u.first_name, u.last_name, s.admission_number as student_id, c.grade_level
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

// Unread messages
$unread_count = db()->fetchOne("
    SELECT COUNT(*) as count FROM message_recipients
    WHERE recipient_id = ? AND is_read = 0 AND deleted_at IS NULL
", [$parent_id])['count'] ?? 0;
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
