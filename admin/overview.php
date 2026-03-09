<?php

/**
 * Complete Admin Overview Dashboard
 * Comprehensive system monitoring and management
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// System Statistics
$stats = [
    'total_users' => db()->count('users'),
    'active_users' => db()->count('users', 'status = :status', ['status' => 'active']),
    'pending_users' => db()->count('users', 'status = :status', ['status' => 'pending']),
    'total_students' => db()->count('students'),
    'total_teachers' => db()->count('teachers'),
    'total_admins' => db()->count('users', 'role = :role', ['role' => 'admin']),
    'total_classes' => db()->count('classes'),
    'total_attendance_today' => db()->count('attendance_records', 'DATE(attendance_date) = CURDATE()'),
    'present_today' => db()->count('attendance_records', 'DATE(attendance_date) = CURDATE() AND status = :status', ['status' => 'present']),
    'absent_today' => db()->count('attendance_records', 'DATE(attendance_date) = CURDATE() AND status = :status', ['status' => 'absent']),
    'late_today' => db()->count('attendance_records', 'DATE(attendance_date) = CURDATE() AND status = :status', ['status' => 'late']),
];

// Calculate attendance rate
$stats['attendance_rate'] = $stats['total_attendance_today'] > 0
    ? round(($stats['present_today'] + $stats['late_today']) / $stats['total_attendance_today'] * 100, 1)
    : 0;

// Pending registrations
$pending_registrations = db()->fetchAll("
    SELECT * FROM users
    WHERE status = 'pending'
    ORDER BY created_at DESC
    LIMIT 10
");

// Recent activity (last 15 activities)
$recent_activities = db()->fetchAll("
    SELECT * FROM audit_logs
    ORDER BY created_at DESC
    LIMIT 15
");

// System health metrics
$db_size_result = db()->fetchOne("
    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
");
$db_size = $db_size_result['size'] ?? 0;

// Biometric statistics
$biometric_stats = [
    'total_devices' => db()->count('biometric_credentials'),
    'total_logins' => db()->count('biometric_auth_logs', 'auth_type = :type', ['type' => 'login']),
    'total_registrations' => db()->count('biometric_auth_logs', 'auth_type = :type', ['type' => 'registration']),
];

// Top users by attendance
$top_students = db()->fetchAll("
    SELECT s.id, s.admission_number as student_id, u.first_name, u.last_name,
           COUNT(ar.id) as total_records,
           SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count
    FROM students s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN attendance_records ar ON s.id = ar.student_id
    WHERE ar.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY s.id
    HAVING total_records > 0
    ORDER BY (present_count / total_records) DESC
    LIMIT 5
");

// At-risk students (low attendance)
$risk_students = db()->fetchAll("
    SELECT s.id, s.admission_number as student_id, u.first_name, u.last_name,
           COUNT(ar.id) as total_records,
           SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count
    FROM students s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN attendance_records ar ON s.id = ar.student_id
    WHERE ar.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY s.id
    HAVING total_records > 0 AND (absent_count / total_records) > 0.15
    ORDER BY (absent_count / total_records) DESC
    LIMIT 5
");

$page_title = 'Admin Overview';
$page_icon = 'tachometer-alt';
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
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">


    <style>
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .metric-card {
            background: rgba(0, 191, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }

        .metric-card:hover {
            background: rgba(0, 191, 255, 0.1);
            border-color: var(--cyber-cyan);
            box-shadow: 0 0 30px rgba(0, 191, 255, 0.3);
            transform: translateY(-5px);
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--cyber-cyan), var(--hologram-purple));
        }

        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .metric-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .metric-icon.cyan {
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.2), rgba(0, 191, 255, 0.4));
            color: var(--cyber-cyan);
        }

        .metric-icon.green {
            background: linear-gradient(135deg, rgba(0, 255, 127, 0.2), rgba(0, 255, 127, 0.4));
            color: var(--neon-green);
        }

        .metric-icon.purple {
            background: linear-gradient(135deg, rgba(138, 43, 226, 0.2), rgba(138, 43, 226, 0.4));
            color: var(--hologram-purple);
        }

        .metric-icon.red {
            background: linear-gradient(135deg, rgba(255, 69, 0, 0.2), rgba(255, 69, 0, 0.4));
            color: var(--cyber-red);
        }

        .metric-icon.gold {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.2), rgba(255, 215, 0, 0.4));
            color: var(--golden-pulse);
        }

        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            font-family: 'Orbitron', sans-serif;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .metric-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .metric-trend {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 10px;
        }

        .metric-trend.up {
            background: rgba(0, 255, 127, 0.2);
            color: var(--neon-green);
        }

        .metric-trend.down {
            background: rgba(255, 69, 0, 0.2);
            color: var(--cyber-red);
        }

        .activity-item {
            display: flex;
            align-items: start;
            gap: 15px;
            padding: 15px;
            background: rgba(0, 191, 255, 0.03);
            border-left: 3px solid var(--cyber-cyan);
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }

        .activity-item:hover {
            background: rgba(0, 191, 255, 0.08);
            border-left-width: 5px;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--cyber-cyan), var(--hologram-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-action {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 3px;
        }

        .activity-meta {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .pending-badge {
            background: var(--golden-pulse);
            color: black;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .health-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            background: rgba(0, 255, 127, 0.1);
            border-left: 4px solid var(--neon-green);
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .health-indicator.warning {
            background: rgba(255, 215, 0, 0.1);
            border-left-color: var(--golden-pulse);
        }

        .health-indicator.critical {
            background: rgba(255, 69, 0, 0.1);
            border-left-color: var(--cyber-red);
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(0, 191, 255, 0.2);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--cyber-cyan), var(--hologram-purple));
            border-radius: 10px;
            transition: width 0.5s ease;
        }
    </style>
</head>
<body>
    
    

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>

        <main class="main-content">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb">
                        <i class="fas fa-<?php echo $page_icon; ?>"></i>
                    </div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>

                <div class="header-actions">
                    <div class="biometric-orb" title="Quick Scan">
                        <i class="fas fa-fingerprint"></i>
                    </div>

                    <div class="user-card" style="padding: 8px 15px; margin: 0;">
                        <div class="user-avatar" style="width: 35px; height: 35px; font-size: 0.9rem;">
                            <?php echo strtoupper(substr($full_name, 0, 2)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name" style="font-size: 0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="user-role">Administrator</div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="cyber-content slide-in">
                <!-- Main Statistics -->
                <div class="overview-grid">
                    <!-- Total Users -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <div>
                                <div class="metric-value"><?php echo number_format($stats['total_users']); ?></div>
                                <div class="metric-label">Total Users</div>
                            </div>
                            <div class="metric-icon cyan">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="metric-trend up">
                            <i class="fas fa-arrow-up"></i>
                            <span><?php echo $stats['active_users']; ?> Active</span>
                        </div>
                        <?php if ($stats['pending_users'] > 0): ?>
                            <div style="margin-top: 10px;">
                                <span class="pending-badge"><?php echo $stats['pending_users']; ?> Pending</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Students -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <div>
                                <div class="metric-value"><?php echo number_format($stats['total_students']); ?></div>
                                <div class="metric-label">Total Students</div>
                            </div>
                            <div class="metric-icon green">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, ($stats['total_students'] / 1000) * 100); ?>%;"></div>
                        </div>
                    </div>

                    <!-- Teachers -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <div>
                                <div class="metric-value"><?php echo number_format($stats['total_teachers']); ?></div>
                                <div class="metric-label">Total Teachers</div>
                            </div>
                            <div class="metric-icon purple">
                                <i class="fas fa-chalkboard-teacher"></i>
                            </div>
                        </div>
                        <div class="metric-trend up">
                            <i class="fas fa-check-circle"></i>
                            <span>All Active</span>
                        </div>
                    </div>

                    <!-- Today's Attendance -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <div>
                                <div class="metric-value"><?php echo $stats['attendance_rate']; ?>%</div>
                                <div class="metric-label">Attendance Rate</div>
                            </div>
                            <div class="metric-icon <?php echo $stats['attendance_rate'] >= 90 ? 'green' : ($stats['attendance_rate'] >= 75 ? 'gold' : 'red'); ?>">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px; margin-top: 10px; font-size: 0.85rem;">
                            <span style="color: var(--neon-green);">
                                <i class="fas fa-check"></i> <?php echo $stats['present_today']; ?> Present
                            </span>
                            <span style="color: var(--cyber-red);">
                                <i class="fas fa-times"></i> <?php echo $stats['absent_today']; ?> Absent
                            </span>
                        </div>
                    </div>

                    <!-- Classes -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <div>
                                <div class="metric-value"><?php echo number_format($stats['total_classes']); ?></div>
                                <div class="metric-label">Active Classes</div>
                            </div>
                            <div class="metric-icon gold">
                                <i class="fas fa-door-open"></i>
                            </div>
                        </div>
                        <div class="metric-trend up">
                            <i class="fas fa-bolt"></i>
                            <span>All Operational</span>
                        </div>
                    </div>

                    <!-- Biometric Devices -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <div>
                                <div class="metric-value"><?php echo number_format($biometric_stats['total_devices']); ?></div>
                                <div class="metric-label">Biometric Devices</div>
                            </div>
                            <div class="metric-icon purple">
                                <i class="fas fa-fingerprint"></i>
                            </div>
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 10px;">
                            <?php echo $biometric_stats['total_logins']; ?> logins • <?php echo $biometric_stats['total_registrations']; ?> registrations
                        </div>
                    </div>

                    <!-- At-Risk Students -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <div>
                                <div class="metric-value"><?php echo count($risk_students); ?></div>
                                <div class="metric-label">At-Risk Students</div>
                            </div>
                            <div class="metric-icon red">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                        <div class="metric-trend down">
                            <i class="fas fa-heartbeat"></i>
                            <span>Need Attention</span>
                        </div>
                    </div>

                    <!-- Database Size -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <div>
                                <div class="metric-value"><?php echo $db_size; ?></div>
                                <div class="metric-label">Database (MB)</div>
                            </div>
                            <div class="metric-icon cyan">
                                <i class="fas fa-database"></i>
                            </div>
                        </div>
                        <div class="metric-trend up">
                            <i class="fas fa-server"></i>
                            <span>Healthy</span>
                        </div>
                    </div>
                </div>

                <!-- Two Column Layout -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <!-- Pending Registrations -->
                    <div class="holo-card">
                        <h3 style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                            <span>
                                <i class="fas fa-user-clock" style="color: var(--golden-pulse);"></i>
                                Pending Registrations
                            </span>
                            <?php if (count($pending_registrations) > 0): ?>
                                <span class="pending-badge"><?php echo count($pending_registrations); ?></span>
                            <?php endif; ?>
                        </h3>

                        <?php if (empty($pending_registrations)): ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <i class="fas fa-check-circle" style="font-size: 3rem; opacity: 0.3; margin-bottom: 10px;"></i>
                                <p>No pending registrations</p>
                            </div>
                        <?php else: ?>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($pending_registrations as $pending): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon" style="background: linear-gradient(135deg, var(--golden-pulse), var(--cyber-cyan));">
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-action">
                                                <?php echo htmlspecialchars($pending['first_name'] . ' ' . $pending['last_name']); ?>
                                            </div>
                                            <div class="activity-meta">
                                                <?php echo ucfirst($pending['role']); ?> • <?php echo htmlspecialchars($pending['email']); ?>
                                                <br>
                                                Registered: <?php echo format_datetime($pending['created_at']); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <a href="registrations.php" class="cyber-btn cyber-btn-primary" style="padding: 6px 12px; font-size: 0.85rem;">
                                                Review
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div style="text-align: center; margin-top: 15px;">
                                <a href="registrations.php" class="cyber-btn cyber-btn-outline">
                                    View All Pending →
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Activity -->
                    <div class="holo-card">
                        <h3 style="margin-bottom: 20px;">
                            <i class="fas fa-history" style="color: var(--cyber-cyan);"></i>
                            Recent Activity
                        </h3>

                        <?php if (empty($recent_activities)): ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                                <i class="fas fa-history" style="font-size: 3rem; opacity: 0.3; margin-bottom: 10px;"></i>
                                <p>No recent activity</p>
                            </div>
                        <?php else: ?>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-<?php
                                                                echo match ($activity['action']) {
                                                                    'login' => 'sign-in-alt',
                                                                    'logout' => 'sign-out-alt',
                                                                    'create' => 'plus-circle',
                                                                    'update' => 'edit',
                                                                    'delete' => 'trash',
                                                                    'approve_user' => 'user-check',
                                                                    default => 'circle'
                                                                };
                                                                ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-action"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $activity['action']))); ?></div>
                                            <div class="activity-meta">
                                                User ID: <?php echo $activity['user_id']; ?> •
                                                <?php echo format_datetime($activity['created_at']); ?>
                                                <?php if ($activity['ip_address']): ?>
                                                    <br>IP: <?php echo htmlspecialchars($activity['ip_address']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top & Risk Students -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <!-- Top Students -->
                    <div class="holo-card">
                        <h3 style="margin-bottom: 20px;">
                            <i class="fas fa-trophy" style="color: var(--golden-pulse);"></i>
                            Top Attendance (Last 30 Days)
                        </h3>

                        <?php if (empty($top_students)): ?>
                            <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                                <p>No data available</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($top_students as $index => $student): ?>
                                <?php $rate = round(($student['present_count'] / $student['total_records']) * 100, 1); ?>
                                <div style="display: flex; align-items: center; gap: 15px; padding: 12px; background: rgba(0, 255, 127, 0.05); border-radius: 8px; margin-bottom: 10px;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--golden-pulse), var(--neon-green)); display: flex; align-items: center; justify-content: center; font-weight: 700; color: black;">
                                        #<?php echo $index + 1; ?>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--text-primary);">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                                            <?php echo $student['student_id']; ?>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 1.2rem; font-weight: 700; color: var(--neon-green);">
                                            <?php echo $rate; ?>%
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                                            <?php echo $student['present_count']; ?>/<?php echo $student['total_records']; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- At-Risk Students -->
                    <div class="holo-card">
                        <h3 style="margin-bottom: 20px;">
                            <i class="fas fa-exclamation-triangle" style="color: var(--cyber-red);"></i>
                            Students Needing Attention
                        </h3>

                        <?php if (empty($risk_students)): ?>
                            <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                                <i class="fas fa-smile" style="font-size: 2rem; color: var(--neon-green); opacity: 0.5; margin-bottom: 10px;"></i>
                                <p>All students have good attendance!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($risk_students as $student): ?>
                                <?php $absence_rate = round(($student['absent_count'] / $student['total_records']) * 100, 1); ?>
                                <div style="display: flex; align-items: center; gap: 15px; padding: 12px; background: rgba(255, 69, 0, 0.05); border-left: 3px solid var(--cyber-red); border-radius: 8px; margin-bottom: 10px;">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--cyber-red), var(--golden-pulse)); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-exclamation" style="color: white;"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--text-primary);">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                                            <?php echo $student['student_id']; ?> • <?php echo $student['absent_count']; ?> absences
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 1.2rem; font-weight: 700; color: var(--cyber-red);">
                                            <?php echo $absence_rate; ?>%
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                                            Absent
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- System Health -->
                <div class="holo-card" style="margin-top: 20px;">
                    <h3 style="margin-bottom: 20px;">
                        <i class="fas fa-heartbeat" style="color: var(--neon-green);"></i>
                        System Health
                    </h3>

                    <div style="display: grid; gap: 10px;">
                        <div class="health-indicator">
                            <i class="fas fa-database"></i>
                            <div style="flex: 1;">
                                <strong>Database</strong>
                                <div style="font-size: 0.85rem; color: var(--text-muted);">
                                    <?php echo $db_size; ?> MB • All tables operational
                                </div>
                            </div>
                            <span class="cyber-badge success">Healthy</span>
                        </div>

                        <div class="health-indicator">
                            <i class="fas fa-fingerprint"></i>
                            <div style="flex: 1;">
                                <strong>Biometric System</strong>
                                <div style="font-size: 0.85rem; color: var(--text-muted);">
                                    <?php echo $biometric_stats['total_devices']; ?> devices registered
                                </div>
                            </div>
                            <span class="cyber-badge success">Active</span>
                        </div>

                        <div class="health-indicator">
                            <i class="fas fa-server"></i>
                            <div style="flex: 1;">
                                <strong>Application Server</strong>
                                <div style="font-size: 0.85rem; color: var(--text-muted);">
                                    PHP <?php echo phpversion(); ?> • All services running
                                </div>
                            </div>
                            <span class="cyber-badge success">Running</span>
                        </div>

                        <?php if ($stats['pending_users'] > 5): ?>
                            <div class="health-indicator warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div style="flex: 1;">
                                    <strong>Pending Approvals</strong>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">
                                        <?php echo $stats['pending_users']; ?> users waiting for approval
                                    </div>
                                </div>
                                <a href="registrations.php" class="cyber-btn cyber-btn-primary" style="padding: 6px 15px; font-size: 0.85rem;">
                                    Review
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="holo-card" style="margin-top: 20px;">
                    <h3 style="margin-bottom: 20px;">
                        <i class="fas fa-bolt" style="color: var(--golden-pulse);"></i>
                        Quick Actions
                    </h3>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <a href="users.php" class="cyber-btn cyber-btn-primary">
                            <i class="fas fa-users"></i>
                            <span>Manage Users</span>
                        </a>
                        <a href="registrations.php" class="cyber-btn cyber-btn-outline">
                            <i class="fas fa-user-check"></i>
                            <span>Approve Registrations</span>
                        </a>
                        <a href="attendance.php" class="cyber-btn cyber-btn-outline">
                            <i class="fas fa-clipboard-check"></i>
                            <span>Mark Attendance</span>
                        </a>
                        <a href="classes.php" class="cyber-btn cyber-btn-outline">
                            <i class="fas fa-door-open"></i>
                            <span>Manage Classes</span>
                        </a>
                        <a href="reports.php" class="cyber-btn cyber-btn-outline">
                            <i class="fas fa-chart-bar"></i>
                            <span>View Reports</span>
                        </a>
                        <a href="settings.php" class="cyber-btn cyber-btn-outline">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
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