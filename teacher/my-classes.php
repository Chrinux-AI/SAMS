<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_teacher();

$teacher_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get teacher's classes with student count
$classes = db()->fetchAll("
    SELECT c.*, COUNT(DISTINCT ce.student_id) as student_count
    FROM classes c
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE c.teacher_id = ?
    GROUP BY c.id
    ORDER BY c.class_name
", [$teacher_id]);

// Unread messages
$unread_count = db()->fetchOne("
    SELECT COUNT(*) as count FROM message_recipients
    WHERE recipient_id = ? AND is_read = 0 AND deleted_at IS NULL
", [$teacher_id])['count'] ?? 0;
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
    <title>My Classes - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/cyberpunk-ui.css" rel="stylesheet">

</head>
<body class="cyber-bg">
    <div class="starfield"></div>
    <div class="cyber-grid"></div>

<div class="cyber-layout">
        <?php include '../includes/sidebar-nav.php'; ?>

        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-door-open"></i></div>
                    <div>
                        <h1 class="page-title">My Classes</h1>
                        <p class="page-subtitle">Classes assigned to you</p>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="../messages.php" class="cyber-btn btn-icon">
                        <i class="fas fa-envelope"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </header>

            <div class="cyber-content slide-in">
                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="stat-orb">
                        <div class="stat-icon cyber">
                            <i class="fas fa-door-open"></i>
                        </div>
                        <div class="stat-label">Total Classes</div>
                        <div class="stat-value"><?php echo count($classes); ?></div>
                    </div>

                    <div class="stat-orb">
                        <div class="stat-icon green">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-label">Total Students</div>
                        <div class="stat-value"><?php echo array_sum(array_column($classes, 'student_count')); ?></div>
                    </div>
                </div>

                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-list"></i>
                            <span>All My Classes</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($classes)): ?>
                            <div class="empty-state">
                                <i class="fas fa-door-closed"></i>
                                <p>No classes assigned yet</p>
                            </div>
                        <?php else: ?>
                            <table class="holo-table">
                                <thead>
                                    <tr>
                                        <th>Class Code</th>
                                        <th>Class Name</th>
                                        <th>Grade Level</th>
                                        <th>Room</th>
                                        <th>Schedule</th>
                                        <th>Students</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classes as $class): ?>
                                        <tr>
                                            <td><span class="status-badge active"><?php echo htmlspecialchars($class['class_code']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($class['grade_level'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($class['room_number'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($class['schedule'] ?? 'N/A'); ?></td>
                                            <td><?php echo $class['student_count']; ?></td>
                                            <td>
                                                <a href="attendance.php?class=<?php echo $class['id']; ?>" class="cyber-btn btn-sm">
                                                    <i class="fas fa-clipboard-check"></i> Attendance
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>
</html>
