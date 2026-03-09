<?php

/**
 * Student LMS Portal
 * Embedded LMS views and SSO integration
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

require_student();

$student_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get student's LMS user ID
$student_data = db()->fetchOne(
    "SELECT u.lms_user_id, s.admission_number AS student_id FROM users u
     JOIN students s ON u.id = s.user_id
     WHERE u.id = ?",
    [$student_id]
);

// Get active LMS configurations
$lms_configs = db()->fetchAll(
    "SELECT * FROM lti_configurations WHERE is_active = 1 ORDER BY lms_name"
);

// Get student's enrolled classes with LMS mapping
$classes = db()->fetchAll(
    "SELECT c.id, c.class_name, c.class_code, c.lms_course_id, c.description,
            u.first_name as teacher_first, u.last_name as teacher_last
     FROM class_enrollments ce
     JOIN classes c ON ce.class_id = c.id
     LEFT JOIN teachers t ON c.teacher_id = t.id
     LEFT JOIN users u ON t.user_id = u.id
     WHERE ce.student_id = ?
     ORDER BY c.class_name",
    [$student_id]
);

// Get recent LMS sessions
$lms_sessions = db()->fetchAll(
    "SELECT ls.*, lc.lms_name, lc.lms_platform
     FROM lti_sessions ls
     JOIN lti_configurations lc ON ls.lti_config_id = lc.id
     WHERE ls.user_id = ?
     ORDER BY ls.created_at DESC
     LIMIT 10",
    [$student_id]
);

$unread_messages = db()->fetchOne(
    "SELECT COUNT(*) as count FROM message_recipients WHERE recipient_id = ? AND is_read = 0",
    [$student_id]
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
    <title>LMS Portal - <?php echo APP_NAME; ?></title>
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
                    <div class="page-icon-orb"><i class="fas fa-graduation-cap"></i></div>
                    <h1 class="page-title">LMS Portal</h1>
                </div>
            </header>

            <div class="app-layout">
                <!-- LMS Account Status -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px;">
                    <div class="stat-orb">
                        <div class="stat-orb"><i class="fas fa-link"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">LMS Account Status</div>
                            <div class="stat-value" style="font-size:1rem;">
                                <?php echo $student_data['lms_user_id'] ? '<span style="color:var(--cyber-green);">Linked</span>' : '<span style="color:var(--cyber-yellow);">Not Linked</span>'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-orb" style="background:var(--cyber-purple);"><i class="fas fa-server"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">Available LMS Platforms</div>
                            <div class="stat-value"><?php echo count($lms_configs); ?></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-orb" style="background:var(--cyber-cyan);"><i class="fas fa-door-open"></i></div>
                        <div class="stat-content">
                            <div class="stat-label">LMS-Linked Courses</div>
                            <div class="stat-value"><?php echo count(array_filter($classes, fn($c) => !empty($c['lms_course_id']))); ?></div>
                        </div>
                    </div>
                </div>

                <?php if (empty($lms_configs)): ?>
                    <div class="holo-card">
                        <div class="card-body" style="text-align:center;padding:60px 20px;">
                            <i class="fas fa-plug" style="font-size:4rem;margin-bottom:20px;opacity:0.3;color:var(--cyber-cyan);"></i>
                            <h3 style="margin-bottom:10px;">LMS Integration Not Configured</h3>
                            <p style="color:var(--text-muted);">Your institution hasn't set up LMS integration yet. Contact your administrator for more information.</p>
                        </div>
                    </div>
                <?php else: ?>

                    <!-- Available LMS Platforms -->
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-server"></i> Available LMS Platforms</div>
                        </div>
                        <div class="card-body">
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">
                                <?php foreach ($lms_configs as $lms): ?>
                                    <div class="holo-card">
                                        <div style="padding:20px;">
                                            <div style="display:flex;align-items:center;gap:15px;margin-bottom:15px;">
                                                <div style="width:50px;height:50px;border-radius:10px;background:linear-gradient(135deg,var(--cyber-purple),var(--cyber-cyan));display:flex;align-items:center;justify-content:center;">
                                                    <i class="fas fa-graduation-cap" style="font-size:1.5rem;color:white;"></i>
                                                </div>
                                                <div>
                                                    <h3 style="margin:0;color:var(--cyber-cyan);"><?php echo htmlspecialchars($lms['lms_name']); ?></h3>
                                                    <p style="margin:0;color:var(--text-muted);font-size:0.85rem;text-transform:uppercase;">
                                                        <?php echo htmlspecialchars($lms['lms_platform']); ?>
                                                    </p>
                                                </div>
                                            </div>

                                            <?php if ($student_data['lms_user_id']): ?>
                                                <div style="background:rgba(0,255,127,0.1);padding:12px;border-radius:6px;margin-bottom:15px;">
                                                    <p style="margin:0;font-size:0.9rem;color:var(--cyber-green);">
                                                        <i class="fas fa-check-circle"></i> Account Linked
                                                    </p>
                                                    <small style="color:var(--text-muted);">LMS ID: <?php echo htmlspecialchars($student_data['lms_user_id']); ?></small>
                                                </div>
                                            <?php else: ?>
                                                <div style="background:rgba(255,255,0,0.1);padding:12px;border-radius:6px;margin-bottom:15px;">
                                                    <p style="margin:0;font-size:0.9rem;color:var(--cyber-yellow);">
                                                        <i class="fas fa-exclamation-triangle"></i> Not Linked
                                                    </p>
                                                    <small style="color:var(--text-muted);">Launch from LMS to link</small>
                                                </div>
                                            <?php endif; ?>

                                            <p style="margin:0;font-size:0.85rem;color:var(--text-muted);">
                                                Launch Attendance AI from your <?php echo htmlspecialchars($lms['lms_platform']); ?> course to access attendance in LMS context.
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- LMS-Linked Courses -->
                    <?php if (!empty($classes)): ?>
                        <div class="holo-card" style="margin-top:30px;">
                            <div class="card-header">
                                <div class="card-title"><i class="fas fa-book"></i> My Courses</div>
                            </div>
                            <div class="card-body">
                                <div class="app-layout">
                                    <table class="cyber-table">
                                        <thead>
                                            <tr>
                                                <th>Course</th>
                                                <th>Code</th>
                                                <th>Teacher</th>
                                                <th>LMS Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($classes as $class): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                                                    <td><code><?php echo htmlspecialchars($class['class_code']); ?></code></td>
                                                    <td><?php echo htmlspecialchars($class['teacher_first'] . ' ' . $class['teacher_last']); ?></td>
                                                    <td>
                                                        <?php if ($class['lms_course_id']): ?>
                                                            <span class="cyber-badge" style="background:var(--cyber-green);">
                                                                <i class="fas fa-link"></i> Synced
                                                            </span>
                                                            <small style="color:var(--text-muted);display:block;margin-top:5px;">
                                                                LMS: <?php echo htmlspecialchars($class['lms_course_id']); ?>
                                                            </small>
                                                        <?php else: ?>
                                                            <span class="cyber-badge" style="background:var(--text-muted);">
                                                                <i class="fas fa-minus"></i> Not Synced
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Recent LMS Sessions -->
                    <?php if (!empty($lms_sessions)): ?>
                        <div class="holo-card" style="margin-top:30px;">
                            <div class="card-header">
                                <div class="card-title"><i class="fas fa-history"></i> Recent LMS Access</div>
                            </div>
                            <div class="card-body">
                                <div style="display:grid;gap:10px;">
                                    <?php foreach ($lms_sessions as $session): ?>
                                        <div style="background:rgba(0,243,255,0.05);padding:15px;border-radius:8px;border-left:3px solid var(--cyber-cyan);">
                                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                                <div>
                                                    <strong style="color:var(--cyber-cyan);"><?php echo htmlspecialchars($session['lms_name']); ?></strong>
                                                    <span style="color:var(--text-muted);margin-left:10px;font-size:0.85rem;">
                                                        <?php echo ucfirst($session['lms_platform']); ?>
                                                    </span>
                                                </div>
                                                <span style="color:var(--text-muted);font-size:0.85rem;">
                                                    <?php echo date('M d, Y H:i', strtotime($session['created_at'])); ?>
                                                </span>
                                            </div>
                                            <?php if ($session['lms_context_id']): ?>
                                                <small style="color:var(--text-muted);display:block;margin-top:5px;">
                                                    Course Context: <?php echo htmlspecialchars($session['lms_context_id']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- How to Use -->
                    <div class="holo-card" style="margin-top:30px;">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-question-circle"></i> How to Access Attendance AI via LMS</div>
                        </div>
                        <div class="card-body">
                            <ol style="line-height:2;color:var(--text-muted);">
                                <li>Log in to your institution's <strong>Learning Management System</strong> (Moodle, Canvas, etc.)</li>
                                <li>Navigate to any <strong>course that has Attendance AI integrated</strong></li>
                                <li>Click on the <strong>Attendance AI Attendance Tool</strong> link in your course</li>
                                <li>You'll be automatically authenticated via <strong>Single Sign-On (SSO)</strong></li>
                                <li>View your <strong>attendance within the LMS interface</strong></li>
                                <li>Your attendance data is <strong>synced as grades</strong> in the LMS gradebook</li>
                            </ol>
                            <div style="background:rgba(0,243,255,0.1);padding:15px;border-radius:8px;margin-top:15px;border-left:4px solid var(--cyber-cyan);">
                                <p style="margin:0;color:var(--cyber-cyan);">
                                    <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> You can also access Attendance AI directly at
                                    <code style="background:rgba(0,0,0,0.3);padding:2px 8px;border-radius:4px;"><?php echo APP_URL; ?></code>
                                </p>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>