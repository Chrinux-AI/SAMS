<?php

/**
 * SAMS Teacher Dashboard - Modern UI Enhanced
 * Extends the main layout with modern UI components
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/database.php';
require_login('../../login.php');
if (!has_role('teacher')) {
    redirect('../../login.php', 'Teacher access required.', 'error');
}

$teacher_id = $_SESSION['user_id'];
$tenantId = $_SESSION['tenant_id'] ?? 1;
$full_name = $_SESSION['full_name'];

// Get teacher data
$teacher = db()->fetchOne("SELECT * FROM teachers WHERE user_id = ?", [$teacher_id]);

// Get teacher's classes
$my_classes = db()->fetchAll("
    SELECT c.id, c.class_name, c.class_code, c.class_teacher_id, c.description, c.schedule, c.room_number, c.created_at,
           COUNT(DISTINCT ce.student_id) as student_count
    FROM classes c
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE c.class_teacher_id = ?
    GROUP BY c.id, c.class_name, c.class_code, c.class_teacher_id, c.description, c.schedule, c.room_number, c.created_at
", [$teacher_id]);

// Get student statistics
$total_students_result = db()->fetchOne("
    SELECT COUNT(DISTINCT ce.student_id) as total
    FROM class_enrollments ce JOIN classes c ON ce.class_id = c.id
    WHERE c.class_teacher_id = ?
", [$teacher_id]);
$total_students = $total_students_result['total'] ?? 0;

// Today's attendance
$today = date('Y-m-d');
$today_attendance = db()->fetchAll("
    SELECT ar.*, c.class_name
    FROM attendance_records ar JOIN classes c ON ar.class_id = c.id
    WHERE c.class_teacher_id = ? AND DATE(ar.check_in_time) = ?
    ORDER BY ar.check_in_time DESC
", [$teacher_id, $today]);

$today_present = count(array_filter($today_attendance, fn($r) => $r['status'] === 'present'));
$today_absent = count(array_filter($today_attendance, fn($r) => $r['status'] === 'absent'));
$today_total = count($today_attendance);
$today_rate = $today_total > 0 ? round(($today_present / $today_total) * 100, 1) : 0;

// Recent activities
$recent_activities = db()->fetchAll("
    SELECT al.*, u.first_name, u.last_name
    FROM audit_logs al
    JOIN users u ON al.actor_id = u.id
    WHERE al.actor_id = ?
    ORDER BY al.created_at DESC
    LIMIT 5
", [$teacher_id]);

// Set page variables for layout
$page_title = 'Teacher Dashboard';
$page_subtitle = 'Welcome back, ' . htmlspecialchars($full_name);
$breadcrumbs = [
    ['text' => 'Home', 'href' => '../index.php'],
    ['text' => 'Teacher Dashboard']
];

// Start content buffer
ob_start();
?>

<!-- Dashboard Stats -->
<div class="stats-grid">
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="avatar bg-primary text-white me-3">
                    <?php echo strtoupper(substr($full_name, 0, 2)); ?>
                </div>
                <div>
                    <h6 class="mb-0">Welcome back!</h6>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($full_name); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card hover-lift">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="icon bg-success text-white me-3">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h6 class="mb-0">Total Students</h6>
                    <p class="text-muted mb-0"><?php echo $total_students; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card hover-lift">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="icon bg-info text-white me-3">
                    <i class="fas fa-door-open"></i>
                </div>
                <div>
                    <h6 class="mb-0">My Classes</h6>
                    <p class="text-muted mb-0"><?php echo count($my_classes); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card hover-lift">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="icon bg-warning text-white me-3">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <h6 class="mb-0">Today's Attendance</h6>
                    <p class="text-muted mb-0"><?php echo $today_rate; ?>%</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Today's Attendance Summary -->
<div class="row mb-6">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-day me-2"></i>
                    Today's Attendance Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="text-success">
                            <h4><?php echo $today_present; ?></h4>
                            <small>Present</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-danger">
                            <h4><?php echo $today_absent; ?></h4>
                            <small>Absent</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-primary">
                            <h4><?php echo $today_total; ?></h4>
                            <small>Total</small>
                        </div>
                    </div>
                </div>
                <div class="progress mt-3">
                    <div class="progress-bar" style="width: <?php echo $today_rate; ?>%"></div>
                </div>
                <p class="text-muted mt-2 mb-0">Attendance Rate: <?php echo $today_rate; ?>%</p>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Recent Activities
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_activities)): ?>
                    <div class="list-group">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($activity['action']); ?></h6>
                                        <p class="text-muted mb-1"><?php echo htmlspecialchars($activity['entity_type']); ?></p>
                                        <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No recent activities.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- My Classes -->
<div class="row mb-6">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-door-open me-2"></i>
                    My Classes
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($my_classes)): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Class Name</th>
                                    <th>Code</th>
                                    <th>Students</th>
                                    <th>Room</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_classes as $class): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($class['class_name']); ?></strong>
                                            <?php if ($class['description']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($class['description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($class['class_code']); ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $class['student_count']; ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($class['room'] ?? 'N/A'); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="attendance.php?class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-check-circle"></i> Attendance
                                                </a>
                                                <a href="grades.php?class_id=<?php echo $class['id']; ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-graduation-cap"></i> Grades
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No classes assigned.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-bolt me-2"></i>
            Quick Actions
        </h5>
    </div>
    <div class="card-body">
        <div class="action-buttons">
            <a href="attendance.php" class="btn btn-primary">
                <i class="fas fa-check-circle me-2"></i>
                Mark Attendance
            </a>
            <a href="grades.php" class="btn btn-success">
                <i class="fas fa-graduation-cap me-2"></i>
                Submit Grades
            </a>
            <a href="assignments.php" class="btn btn-info">
                <i class="fas fa-book me-2"></i>
                Manage Assignments
            </a>
            <a href="classes.php" class="btn btn-warning">
                <i class="fas fa-door-open me-2"></i>
                Manage Classes
            </a>
            <a href="reports.php" class="btn btn-secondary">
                <i class="fas fa-chart-line me-2"></i>
                Reports
            </a>
        </div>
    </div>
</div>

<?php
// Get content and include layout
$content = ob_get_clean();
include __DIR__ . '/../../app/views/layouts/main.php';
?>
