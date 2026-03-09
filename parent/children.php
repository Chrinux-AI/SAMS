<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_parent();

$parent_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get all children with their details
$children = db()->fetchAll("
    SELECT u.id, u.first_name, u.last_name, u.email, s.admission_number as student_id, c.grade_level,
           COUNT(DISTINCT ce.class_id) as class_count
    FROM users u
    JOIN students s ON u.id = s.user_id
    JOIN parent_student_links psl ON s.user_id = psl.student_id
    LEFT JOIN class_enrollments ce ON s.user_id = ce.student_id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE psl.parent_id = ? AND u.status = 'active'
    GROUP BY u.id
", [$parent_id]);

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
        <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Children - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">

</head>
<body>
    <div class="starfield"></div>
    <div class="app-layout"></div>

<div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>

        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-users"></i></div>
                    <div>
                        <h1 class="page-title">My Children</h1>
                        <p class="page-subtitle">View your children's information</p>
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

            <div class="app-layout">
                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="stat-orb">
                        <div class="stat-icon purple">
                            <i class="fas fa-child"></i>
                        </div>
                        <div class="stat-label">Total Children</div>
                        <div class="stat-value"><?php echo count($children); ?></div>
                    </div>
                </div>

                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-list"></i>
                            <span>Children List</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($children)): ?>
                            <div class="empty-state">
                                <i class="fas fa-user-slash"></i>
                                <p>No children linked to your account</p>
                                <small>Contact the administrator to link your children</small>
                            </div>
                        <?php else: ?>
                            <table class="holo-table">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Grade</th>
                                        <th>Classes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($children as $child): ?>
                                        <tr>
                                            <td><span class="status-badge active"><?php echo htmlspecialchars($child['student_id']); ?></span></td>
                                            <td><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($child['email']); ?></td>
                                            <td><?php echo htmlspecialchars($child['grade_level'] ?? 'N/A'); ?></td>
                                            <td><?php echo $child['class_count']; ?> classes</td>
                                            <td>
                                                <a href="attendance.php?student=<?php echo $child['id']; ?>" class="cyber-btn btn-sm">
                                                    <i class="fas fa-clipboard-list"></i> Attendance
                                                </a>
                                                <a href="reports.php?student=<?php echo $child['id']; ?>" class="cyber-btn btn-sm">
                                                    <i class="fas fa-chart-bar"></i> Reports
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
