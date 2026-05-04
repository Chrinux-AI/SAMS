<?php

/**
 * Parent LMS Overview
 * Consolidated child LMS data viewing
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

require_parent();

$parent_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$tenantId = current_tenant_id();

// Get linked children with LMS data
$children = db()->fetchAll("
    SELECT u.id, u.first_name, u.last_name, u.lms_user_id, s.admission_number AS student_id,
           COUNT(DISTINCT c.id) as course_count,
           COUNT(DISTINCT CASE WHEN c.lms_course_id IS NOT NULL THEN c.id END) as lms_linked_courses
    FROM parent_student_links psl
    JOIN users u ON psl.student_id = u.id
    JOIN students s ON u.id = s.user_id
    LEFT JOIN class_enrollments ce ON s.user_id = ce.student_id
    LEFT JOIN classes c ON ce.class_id = c.id
    WHERE psl.parent_id = ? AND psl.verified_at IS NOT NULL
    GROUP BY u.id
    ORDER BY u.first_name
", [$parent_id]);

// Get active LMS configurations
$lms_configs = db()->fetchAll(
    "SELECT * FROM lti_configurations WHERE is_active = 1 ORDER BY lms_name"
);

// Get consolidated sync data for all children
$sync_data = [];
foreach ($children as $child) {
    $syncs = db()->fetchAll(
        "SELECT gsl.*, lc.lms_name, lc.lms_platform, c.class_name
         FROM lti_grade_sync_log gsl
         JOIN lti_configurations lc ON gsl.lti_config_id = lc.id
         LEFT JOIN students s ON gsl.user_id = s.user_id
         LEFT JOIN class_enrollments ce ON s.user_id = ce.student_id
         LEFT JOIN classes c ON ce.class_id = c.id
         WHERE gsl.user_id = ?
         ORDER BY gsl.synced_at DESC
         LIMIT 10",
        [$child['id']]
    );
    $sync_data[$child['id']] = $syncs;
}

$unread_messages = get_unread_message_count((int)$parent_id, $tenantId ? (int)$tenantId : null);
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
    <title>LMS Overview - <?php echo APP_NAME; ?></title>
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
                    <div class="page-icon-orb"><i class="fas fa-graduation-cap"></i></div>
                    <h1 class="page-title">LMS Overview</h1>
                </div>
            </header>

            <div style="display:grid;gap:24px;">
                <?php if (empty($children)): ?>
                    <div class="holo-card">
                        <div class="card-body" style="text-align:center;padding:60px 20px;">
                            <i class="fas fa-link" style="font-size:4rem;margin-bottom:20px;opacity:0.3;color:var(--cyber-cyan);"></i>
                            <h3 style="margin-bottom:10px;">No Children Linked</h3>
                            <p style="color:var(--text-muted);margin-bottom:20px;">Link your children's accounts to view their LMS integration status.</p>
                            <a href="link-children.php" class="cyber-btn">
                                <i class="fas fa-plus"></i> Link Children
                            </a>
                        </div>
                    </div>
                <?php else: ?>

                    <!-- Summary Cards -->
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px;">
                        <div class="stat-orb">
                            <div class="stat-orb"><i class="fas fa-users"></i></div>
                            <div class="stat-content">
                                <div class="stat-label">Linked Children</div>
                                <div class="stat-value"><?php echo count($children); ?></div>
                            </div>
                        </div>
                        <div class="stat-orb">
                            <div class="stat-orb" style="background:var(--cyber-purple);"><i class="fas fa-server"></i></div>
                            <div class="stat-content">
                                <div class="stat-label">Active LMS Platforms</div>
                                <div class="stat-value"><?php echo count($lms_configs); ?></div>
                            </div>
                        </div>
                        <div class="stat-orb">
                            <div class="stat-orb" style="background:var(--cyber-cyan);"><i class="fas fa-link"></i></div>
                            <div class="stat-content">
                                <div class="stat-label">LMS-Linked Accounts</div>
                                <div class="stat-value"><?php echo count(array_filter($children, fn($c) => !empty($c['lms_user_id']))); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Children LMS Status -->
                    <?php foreach ($children as $child): ?>
                        <div class="holo-card" style="margin-bottom:25px;">
                            <div class="card-header">
                                <div class="card-title">
                                    <i class="fas fa-user-graduate"></i>
                                    <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                    <span style="color:var(--text-muted);font-size:0.85rem;margin-left:10px;">
                                        STU<?php echo htmlspecialchars($child['student_id']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- LMS Account Status -->
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:20px;">
                                    <div style="background:rgba(0,243,255,0.05);padding:15px;border-radius:8px;border-left:3px solid var(--cyber-cyan);">
                                        <div style="color:var(--text-muted);font-size:0.85rem;margin-bottom:5px;">LMS Account</div>
                                        <div style="color:var(--cyber-cyan);font-weight:bold;">
                                            <?php if ($child['lms_user_id']): ?>
                                                <i class="fas fa-check-circle" style="color:var(--cyber-green);"></i> Linked
                                                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:3px;">
                                                    ID: <?php echo htmlspecialchars($child['lms_user_id']); ?>
                                                </div>
                                            <?php else: ?>
                                                <i class="fas fa-times-circle" style="color:var(--cyber-yellow);"></i> Not Linked
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="background:rgba(138,43,226,0.05);padding:15px;border-radius:8px;border-left:3px solid var(--cyber-purple);">
                                        <div style="color:var(--text-muted);font-size:0.85rem;margin-bottom:5px;">Total Courses</div>
                                        <div style="color:var(--cyber-purple);font-weight:bold;font-size:1.5rem;">
                                            <?php echo $child['course_count']; ?>
                                        </div>
                                    </div>
                                    <div style="background:rgba(0,255,127,0.05);padding:15px;border-radius:8px;border-left:3px solid var(--cyber-green);">
                                        <div style="color:var(--text-muted);font-size:0.85rem;margin-bottom:5px;">LMS-Linked Courses</div>
                                        <div style="color:var(--cyber-green);font-weight:bold;font-size:1.5rem;">
                                            <?php echo $child['lms_linked_courses']; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Recent Sync History -->
                                <?php if (!empty($sync_data[$child['id']])): ?>
                                    <div style="border-top:1px solid var(--glass-border);padding-top:20px;">
                                        <h4 style="margin:0 0 15px 0;color:var(--cyber-cyan);">
                                            <i class="fas fa-sync-alt"></i> Recent LMS Grade Syncs
                                        </h4>
                                        <div style="overflow-x:auto;">
                                            <table class="cyber-table">
                                                <thead>
                                                    <tr>
                                                        <th>LMS Platform</th>
                                                        <th>Course</th>
                                                        <th>Attendance %</th>
                                                        <th>Grade Sent</th>
                                                        <th>Status</th>
                                                        <th>Synced</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($sync_data[$child['id']] as $sync): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($sync['lms_name']); ?></strong>
                                                                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">
                                                                    <?php echo ucfirst($sync['lms_platform']); ?>
                                                                </div>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($sync['class_name'] ?? 'N/A'); ?></td>
                                                            <td>
                                                                <span style="color:<?php echo $sync['attendance_percentage'] >= 80 ? 'var(--cyber-green)' : ($sync['attendance_percentage'] >= 60 ? 'var(--cyber-yellow)' : 'var(--cyber-red)'); ?>">
                                                                    <?php echo number_format($sync['attendance_percentage'], 1); ?>%
                                                                </span>
                                                            </td>
                                                            <td><strong><?php echo number_format($sync['grade_value'], 1); ?></strong></td>
                                                            <td>
                                                                <?php if ($sync['status'] === 'success'): ?>
                                                                    <span class="cyber-badge" style="background:var(--cyber-green);">
                                                                        <i class="fas fa-check"></i> Synced
                                                                    </span>
                                                                <?php elseif ($sync['status'] === 'failed'): ?>
                                                                    <span class="cyber-badge" style="background:var(--cyber-red);">
                                                                        <i class="fas fa-times"></i> Failed
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="cyber-badge" style="background:var(--cyber-yellow);">
                                                                        <i class="fas fa-clock"></i> Pending
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td style="font-size:0.85rem;color:var(--text-muted);">
                                                                <?php echo date('M d, H:i', strtotime($sync['synced_at'])); ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="text-align:center;padding:30px;color:var(--text-muted);border-top:1px solid var(--glass-border);">
                                        <i class="fas fa-history" style="font-size:2rem;margin-bottom:10px;opacity:0.3;"></i>
                                        <p>No LMS sync history available for this child</p>
                                    </div>
                                <?php endif; ?>

                                <!-- Quick Actions -->
                                <div style="display:flex;gap:10px;margin-top:20px;border-top:1px solid var(--glass-border);padding-top:20px;">
                                    <a href="attendance.php?child=<?php echo $child['id']; ?>" class="cyber-btn cyber-btn-outline">
                                        <i class="fas fa-clipboard-check"></i> View Attendance
                                    </a>
                                    <a href="grades.php?child=<?php echo $child['id']; ?>" class="cyber-btn cyber-btn-outline">
                                        <i class="fas fa-chart-bar"></i> View Grades
                                    </a>
                                    <a href="analytics.php?child=<?php echo $child['id']; ?>" class="cyber-btn cyber-btn-outline">
                                        <i class="fas fa-chart-line"></i> Analytics
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Available LMS Platforms -->
                    <?php if (!empty($lms_configs)): ?>
                        <div class="holo-card">
                            <div class="card-header">
                                <div class="card-title"><i class="fas fa-server"></i> Configured LMS Platforms</div>
                            </div>
                            <div class="card-body">
                                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:15px;">
                                    <?php foreach ($lms_configs as $lms): ?>
                                        <div style="background:rgba(0,243,255,0.05);padding:15px;border-radius:8px;border-left:3px solid var(--cyber-cyan);">
                                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                                                <div style="width:40px;height:40px;border-radius:8px;background:linear-gradient(135deg,var(--cyber-purple),var(--cyber-cyan));display:flex;align-items:center;justify-content:center;">
                                                    <i class="fas fa-graduation-cap" style="color:white;"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight:bold;color:var(--cyber-cyan);">
                                                        <?php echo htmlspecialchars($lms['lms_name']); ?>
                                                    </div>
                                                    <div style="font-size:0.75rem;color:var(--text-muted);text-transform:uppercase;">
                                                        <?php echo htmlspecialchars($lms['lms_platform']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div style="font-size:0.85rem;color:var(--text-muted);">
                                                <?php if ($lms['last_sync_at']): ?>
                                                    Last sync: <?php echo date('M d, H:i', strtotime($lms['last_sync_at'])); ?>
                                                <?php else: ?>
                                                    No syncs yet
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Information -->
                    <div class="holo-card" style="margin-top:30px;">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-info-circle"></i> About LMS Integration</div>
                        </div>
                        <div class="card-body">
                            <p style="color:var(--text-muted);line-height:1.8;">
                                The Learning Management System (LMS) integration allows your children's attendance data to be automatically
                                synced with their course grades in platforms like Moodle, Canvas, or Blackboard. This provides a unified
                                view of their academic performance across multiple systems.
                            </p>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-top:20px;">
                                <div style="background:rgba(0,243,255,0.1);padding:15px;border-radius:8px;">
                                    <h4 style="margin:0 0 10px 0;color:var(--cyber-cyan);">
                                        <i class="fas fa-check-circle"></i> Benefits
                                    </h4>
                                    <ul style="margin:0;padding-left:20px;color:var(--text-muted);font-size:0.9rem;">
                                        <li>Real-time grade updates</li>
                                        <li>Consolidated reports</li>
                                        <li>Single sign-on access</li>
                                        <li>Automated attendance tracking</li>
                                    </ul>
                                </div>
                                <div style="background:rgba(138,43,226,0.1);padding:15px;border-radius:8px;">
                                    <h4 style="margin:0 0 10px 0;color:var(--cyber-purple);">
                                        <i class="fas fa-shield-alt"></i> Security
                                    </h4>
                                    <ul style="margin:0;padding-left:20px;color:var(--text-muted);font-size:0.9rem;">
                                        <li>Encrypted data transfer</li>
                                        <li>LTI 1.3 compliance</li>
                                        <li>OAuth authentication</li>
                                        <li>Secure token validation</li>
                                    </ul>
                                </div>
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
