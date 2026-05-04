<?php

/**
 * View Student
 */

session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Require admin access
require_admin('../login.php');

$student_id = $_GET['id'] ?? 0;

// Get student details
$student = db()->fetchOne("
    SELECT s.*, u.email, u.first_name, u.last_name, u.full_name, u.phone, u.status, u.status as account_status, u.created_at as account_created,
           c.grade_level, c.class_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.id = ?
", [$student_id]);

if (!$student) {
    header('Location: students.php');
    exit;
}

// Get attendance statistics
$attendance_stats = db()->fetchOne("
    SELECT
        COUNT(*) as total_records,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused
    FROM attendance_records
    WHERE student_id = ?
", [$student_id]);

// Get enrolled classes
$classes = db()->fetchAll("
    SELECT c.*, t.first_name as teacher_first, t.last_name as teacher_last,
           ce.enrolled_at
    FROM class_enrollments ce
    JOIN classes c ON ce.class_id = c.id
    LEFT JOIN teachers t ON c.class_teacher_id = t.id
    WHERE ce.student_id = ?
    ORDER BY ce.enrolled_at DESC
", [$student_id]);

// Get recent attendance
$recent_attendance = db()->fetchAll("
    SELECT ar.*, c.class_name as class_name, c.class_code as class_code
    FROM attendance_records ar
    JOIN classes c ON ar.class_id = c.id
    WHERE ar.student_id = ?
    ORDER BY ar.date DESC, ar.created_at DESC
    LIMIT 20
", [$student_id]);

$page_title = $student['first_name'] . ' ' . $student['last_name'];
$page_icon = 'user-graduate';

// Calculate attendance rate
$attendance_rate = $attendance_stats['total_records'] > 0
    ? round((($attendance_stats['present'] + $attendance_stats['late']) / $attendance_stats['total_records']) * 100, 1)
    : 0;
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>


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
                    <h1 class="page-title"><?php echo htmlspecialchars($page_title); ?></h1>
                </div>
                <div class="header-actions">
                    <a href="student-edit.php?id=<?php echo $student_id; ?>" class="cyber-btn cyber-btn-primary">
                        <i class="fas fa-edit"></i>
                        <span>Edit Student</span>
                    </a>
                    <a href="students.php" class="cyber-btn cyber-btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Students</span>
                    </a>
                </div>
            </header>

            <div class="cyber-content">
                <!-- Student Info Cards -->
                <div class="stats-grid">
                    <div class="stat-orb stat-primary">
                        <div class="stat-icon">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo htmlspecialchars($student['assigned_student_id'] ?? 'N/A'); ?></div>
                            <div class="stat-label">Student ID</div>
                        </div>
                    </div>

                    <div class="stat-orb stat-success">
                        <div class="stat-icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo $attendance_rate; ?>%</div>
                            <div class="stat-label">Attendance Rate</div>
                        </div>
                    </div>

                    <div class="stat-orb stat-info">
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo count($classes); ?></div>
                            <div class="stat-label">Enrolled Classes</div>
                        </div>
                    </div>

                    <div class="stat-orb stat-warning">
                        <div class="stat-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo $attendance_stats['total_records']; ?></div>
                            <div class="stat-label">Total Records</div>
                        </div>
                    </div>
                </div>

                <!-- Personal Information -->
                <section class="cyber-panel">
                    <div class="cyber-panel-header">
                        <h2><i class="fas fa-user"></i> Personal Information</h2>
                    </div>
                    <div class="cyber-panel-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-user"></i> Full Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                                <span class="info-value"><?php echo htmlspecialchars($student['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-calendar"></i> Date of Birth</span>
                                <span class="info-value"><?php echo $student['date_of_birth'] ? date('M d, Y', strtotime($student['date_of_birth'])) : 'N/A'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-venus-mars"></i> Gender</span>
                                <span class="info-value"><?php echo ucfirst($student['gender'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-phone"></i> Phone</span>
                                <span class="info-value"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-layer-group"></i> Grade Level</span>
                                <span class="info-value">Level <?php echo $student['grade_level'] ?? 'N/A'; ?></span>
                            </div>
                            <div class="info-item full-width">
                                <span class="info-label"><i class="fas fa-map-marker-alt"></i> Address</span>
                                <span class="info-value"><?php echo htmlspecialchars($student['address'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Parent Information -->
                <?php if (isset($student['parent_name']) || isset($student['parent_email']) || isset($student['parent_phone'])): ?>
                    <section class="cyber-panel">
                        <div class="cyber-panel-header">
                            <h2><i class="fas fa-users"></i> Parent/Guardian Information</h2>
                        </div>
                        <div class="cyber-panel-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-user-tie"></i> Name</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['parent_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['parent_email'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label"><i class="fas fa-phone"></i> Phone</span>
                                    <span class="info-value"><?php echo htmlspecialchars($student['parent_phone'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Attendance Statistics -->
                <section class="cyber-panel">
                    <div class="cyber-panel-header">
                        <h2><i class="fas fa-chart-bar"></i> Attendance Statistics</h2>
                    </div>
                    <div class="cyber-panel-body">
                        <div class="stats-grid">
                            <div class="stat-orb stat-success">
                                <div class="stat-icon"><i class="fas fa-check"></i></div>
                                <div class="stat-info">
                                    <div class="stat-value"><?php echo $attendance_stats['present']; ?></div>
                                    <div class="stat-label">Present</div>
                                </div>
                            </div>
                            <div class="stat-orb stat-danger">
                                <div class="stat-icon"><i class="fas fa-times"></i></div>
                                <div class="stat-info">
                                    <div class="stat-value"><?php echo $attendance_stats['absent']; ?></div>
                                    <div class="stat-label">Absent</div>
                                </div>
                            </div>
                            <div class="stat-orb stat-warning">
                                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                                <div class="stat-info">
                                    <div class="stat-value"><?php echo $attendance_stats['late']; ?></div>
                                    <div class="stat-label">Late</div>
                                </div>
                            </div>
                            <div class="stat-orb stat-info">
                                <div class="stat-icon"><i class="fas fa-file-medical"></i></div>
                                <div class="stat-info">
                                    <div class="stat-value"><?php echo $attendance_stats['excused']; ?></div>
                                    <div class="stat-label">Excused</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Enrolled Classes -->
                <section class="cyber-panel">
                    <div class="cyber-panel-header">
                        <h2><i class="fas fa-book-open"></i> Enrolled Classes (<?php echo count($classes); ?>)</h2>
                    </div>
                    <div class="cyber-panel-body">
                        <?php if (count($classes) > 0): ?>
                            <div class="holo-table-wrapper">
                                <table class="holo-table">
                                    <thead>
                                        <tr>
                                            <th>Class Code</th>
                                            <th>Class Name</th>
                                            <th>Teacher</th>
                                            <th>Schedule</th>
                                            <th>Enrolled Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($classes as $class): ?>
                                            <tr>
                                                <td><span class="badge badge-primary"><?php echo htmlspecialchars($class['code']); ?></span></td>
                                                <td><?php echo htmlspecialchars($class['name']); ?></td>
                                                <td><?php echo htmlspecialchars($class['teacher_first'] . ' ' . $class['teacher_last']); ?></td>
                                                <td><?php echo htmlspecialchars($class['schedule'] ?? 'N/A'); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($class['enrolled_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-book-open" style="font-size: 4rem; color: var(--cyber-cyan); opacity: 0.3;"></i>
                                <p style="margin-top: 20px; color: var(--text-muted);">Not enrolled in any classes yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Recent Attendance -->
                <section class="cyber-panel">
                    <div class="cyber-panel-header">
                        <h2><i class="fas fa-history"></i> Recent Attendance</h2>
                    </div>
                    <div class="cyber-panel-body">
                        <?php if (count($recent_attendance) > 0): ?>
                            <div class="holo-table-wrapper">
                                <table class="holo-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Class</th>
                                            <th>Status</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_attendance as $record): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                                <td>
                                                    <span class="badge badge-primary"><?php echo htmlspecialchars($record['class_code']); ?></span>
                                                    <?php echo htmlspecialchars($record['class_name']); ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $badge_class = 'badge-info';
                                                    if ($record['status'] === 'present') $badge_class = 'badge-success';
                                                    elseif ($record['status'] === 'absent') $badge_class = 'badge-danger';
                                                    elseif ($record['status'] === 'late') $badge_class = 'badge-warning';
                                                    ?>
                                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($record['status']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['notes'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-clipboard-list" style="font-size: 4rem; color: var(--cyber-cyan); opacity: 0.3;"></i>
                                <p style="margin-top: 20px; color: var(--text-muted);">No attendance records yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Account Information -->
                <section class="cyber-panel">
                    <div class="cyber-panel-header">
                        <h2><i class="fas fa-cog"></i> Account Information</h2>
                    </div>
                    <div class="cyber-panel-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-toggle-on"></i> Status</span>
                                <span class="info-value">
                                    <span class="badge <?php echo $student['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-calendar-plus"></i> Account Created</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($student['account_created'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-calendar-check"></i> Student Record Created</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($student['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
