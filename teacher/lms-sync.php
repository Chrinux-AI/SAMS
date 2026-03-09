<?php

/**
 * Teacher LMS Sync Portal
 * Manual syncing and viewing LMS-linked data
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/lti.php';

require_teacher();

$teacher_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Handle manual sync request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'sync_class') {
        $class_id = $_POST['class_id'] ?? 0;
        $lti_config_id = $_POST['lti_config_id'] ?? 1;
        $lms_context_id = $_POST['lms_context_id'] ?? '';

        // Trigger bulk grade sync via API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, APP_URL . '/api/lti.php?action=bulk_grade_sync');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'class_id' => $class_id,
            'lms_context_id' => $lms_context_id,
            'lti_config_id' => $lti_config_id
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        curl_close($ch);

        $_SESSION['sync_result'] = json_decode($result, true);
        header('Location: lms-sync.php');
        exit;
    }
}

// Get teacher's classes
$classes = db()->fetchAll(
    "SELECT c.id, c.class_name, c.class_code, c.lms_course_id,
            COUNT(DISTINCT ce.student_id) as student_count
     FROM classes c
     LEFT JOIN class_enrollments ce ON c.id = ce.class_id
     WHERE c.teacher_id = ?
     GROUP BY c.id",
    [$teacher_id]
);

// Get active LTI configurations
$lti_configs = db()->fetchAll(
    "SELECT id, lms_name, lms_platform, is_active, last_sync_at
     FROM lti_configurations
     WHERE is_active = 1
     ORDER BY lms_name"
);

// Get recent sync history for this teacher's students
$sync_history = db()->fetchAll(
    "SELECT gsl.*, u.first_name, u.last_name, c.class_name, lc.lms_name
     FROM lti_grade_sync_log gsl
     JOIN users u ON gsl.user_id = u.id
     JOIN students s ON u.id = s.user_id
     JOIN class_enrollments ce ON s.user_id = ce.student_id
     JOIN classes c ON ce.class_id = c.id
     JOIN lti_configurations lc ON gsl.lti_config_id = lc.id
     WHERE c.teacher_id = ?
     ORDER BY gsl.synced_at DESC
     LIMIT 50",
    [$teacher_id]
);

$unread_messages = db()->fetchOne(
    "SELECT COUNT(*) as count FROM message_recipients WHERE recipient_id = ? AND is_read = 0",
    [$teacher_id]
)['count'] ?? 0;
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
    <title>LMS Sync - <?php echo APP_NAME; ?></title>
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
                    <div class="page-icon-orb"><i class="fas fa-sync-alt"></i></div>
                    <h1 class="page-title">LMS Sync</h1>
                </div>
            </header>

            <div class="app-layout">
                <?php if (isset($_SESSION['sync_result'])):
                    $result = $_SESSION['sync_result'];
                    unset($_SESSION['sync_result']);
                ?>
                    <div class="alert alert-<?php echo $result['success'] ? 'success' : 'error'; ?>" style="margin-bottom:20px;">
                        <i class="fas fa-<?php echo $result['success'] ? 'check-circle' : 'times-circle'; ?>"></i>
                        <?php if ($result['success']): ?>
                            Sync completed! Synced: <?php echo $result['synced']; ?>, Failed: <?php echo $result['failed']; ?>
                        <?php else: ?>
                            Sync failed: <?php echo $result['error'] ?? 'Unknown error'; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- LMS Status Cards -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px;">
                    <div class="stat-orb">
                        <div class="stat-orb"><i class="fas fa-server"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Active LMS Connections</div>
                            <div class="stat-value"><?php echo count($lti_configs); ?></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-orb" style="background:var(--cyber-purple);"><i class="fas fa-door-open"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">My Classes</div>
                            <div class="stat-value"><?php echo count($classes); ?></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-orb" style="background:var(--cyber-green);"><i class="fas fa-check"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Recent Syncs</div>
                            <div class="stat-value"><?php echo count(array_filter($sync_history, fn($s) => $s['status'] === 'success')); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Class Sync Controls -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-sync-alt"></i> Sync Classes to LMS</div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($lti_configs)): ?>
                            <div style="text-align:center;padding:40px;color:var(--text-muted);">
                                <i class="fas fa-plug" style="font-size:3rem;margin-bottom:15px;opacity:0.3;"></i>
                                <p>No active LMS connections. Contact admin to configure LMS integration.</p>
                            </div>
                        <?php elseif (empty($classes)): ?>
                            <div style="text-align:center;padding:40px;color:var(--text-muted);">
                                <i class="fas fa-door-open" style="font-size:3rem;margin-bottom:15px;opacity:0.3;"></i>
                                <p>No classes assigned. You need classes to sync attendance data.</p>
                            </div>
                        <?php else: ?>
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:20px;">
                                <?php foreach ($classes as $class): ?>
                                    <div class="holo-card">
                                        <div style="padding:20px;">
                                            <h3 style="margin:0 0 10px 0;color:var(--cyber-cyan);">
                                                <?php echo htmlspecialchars($class['class_name']); ?>
                                            </h3>
                                            <p style="margin:0 0 15px 0;color:var(--text-muted);font-size:0.9rem;">
                                                <i class="fas fa-code"></i> <?php echo htmlspecialchars($class['class_code']); ?>
                                                <span style="margin-left:15px;">
                                                    <i class="fas fa-users"></i> <?php echo $class['student_count']; ?> students
                                                </span>
                                            </p>

                                            <form method="POST" style="margin-top:15px;">
                                                <input type="hidden" name="action" value="sync_class">
                                                <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">

                                                <div class="form-group" style="margin-bottom:10px;">
                                                    <label class="form-label" style="font-size:0.85rem;">LMS Platform</label>
                                                    <select name="lti_config_id" class="cyber-input" style="font-size:0.9rem;" required>
                                                        <?php foreach ($lti_configs as $config): ?>
                                                            <option value="<?php echo $config['id']; ?>">
                                                                <?php echo htmlspecialchars($config['lms_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="form-group" style="margin-bottom:15px;">
                                                    <label class="form-label" style="font-size:0.85rem;">LMS Course ID</label>
                                                    <input type="text" name="lms_context_id" class="cyber-input"
                                                        value="<?php echo htmlspecialchars($class['lms_course_id'] ?? ''); ?>"
                                                        placeholder="e.g., course-123" required style="font-size:0.9rem;">
                                                </div>

                                                <button type="submit" class="cyber-btn" style="width:100%;">
                                                    <i class="fas fa-sync-alt"></i> Sync Attendance to LMS
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sync History -->
                <div class="holo-card" style="margin-top:30px;">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-history"></i> Recent Sync History</div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($sync_history)): ?>
                            <p style="text-align:center;color:var(--text-muted);padding:40px;">No sync history available</p>
                        <?php else: ?>
                            <div class="app-layout">
                                <table class="cyber-table">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Class</th>
                                            <th>LMS</th>
                                            <th>Attendance %</th>
                                            <th>Grade</th>
                                            <th>Status</th>
                                            <th>Synced At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sync_history as $sync): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($sync['first_name'] . ' ' . $sync['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($sync['class_name']); ?></td>
                                                <td><?php echo htmlspecialchars($sync['lms_name']); ?></td>
                                                <td><?php echo number_format($sync['attendance_percentage'], 1); ?>%</td>
                                                <td><strong><?php echo number_format($sync['grade_value'], 1); ?></strong></td>
                                                <td>
                                                    <?php if ($sync['status'] === 'success'): ?>
                                                        <span class="cyber-badge" style="background:var(--cyber-green);">
                                                            <i class="fas fa-check"></i> Success
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="cyber-badge" style="background:var(--cyber-red);">
                                                            <i class="fas fa-times"></i> Failed
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, H:i', strtotime($sync['synced_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
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