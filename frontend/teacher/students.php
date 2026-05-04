<?php
require_once '../core/bootstrap.php';
require_teacher();

$teacher_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get all students from teacher's classes
$students = db()->fetchAll("
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, s.admission_number as student_id, cl.grade_level,
           GROUP_CONCAT(DISTINCT c.class_name SEPARATOR ', ') as classes
    FROM users u
    JOIN students s ON u.id = s.user_id
    JOIN class_enrollments ce ON s.user_id = ce.student_id
    JOIN classes c ON ce.class_id = c.id
    LEFT JOIN classes cl ON s.class_id = cl.id
    WHERE c.class_teacher_id = ? AND u.status = 'active'
    GROUP BY u.id
    ORDER BY u.last_name, u.first_name
", [$teacher_id]);

// Unread messages
$unread_count = get_unread_message_count((int)$teacher_id, (int)current_tenant_id());
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
    <title>My Students - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/sams-core.css" rel="stylesheet">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>


</head>

<body>
    <div class="starfield"></div>

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>

        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-user-graduate"></i></div>
                    <div>
                        <h1 class="page-title">My Students</h1>
                        <p class="page-subtitle">Students enrolled in your classes</p>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="../communication/conversations.php" class="cyber-btn btn-icon">
                        <i class="fas fa-envelope"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </header>

            <div style="display:grid;gap:24px;">
                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="stat-orb">
                        <div class="stat-icon green">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-label">Total Students</div>
                        <div class="stat-value"><?php echo count($students); ?></div>
                    </div>
                </div>

                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-list"></i>
                            <span>Student Directory</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($students)): ?>
                            <div class="empty-state">
                                <i class="fas fa-user-slash"></i>
                                <p>No students found in your classes</p>
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
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><span class="status-badge active"><?php echo htmlspecialchars($student['student_id']); ?></span></td>
                                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td><?php echo htmlspecialchars($student['grade_level'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($student['classes']); ?></td>
                                            <td>
                                                <a href="../communication/conversations.php?to=<?php echo $student['id']; ?>" class="cyber-btn btn-sm">
                                                    <i class="fas fa-envelope"></i> Message
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
