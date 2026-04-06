<?php

/**
 * SAMS Admin Dashboard - Modern UI Enhanced
 * Extends the main layout with modern UI components
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_login('../login.php');
if (!has_role('admin')) {
    redirect('../login.php', 'Admin access required.', 'error');
}

$admin_id = $_SESSION['user_id'];
$tenantId = $_SESSION['tenant_id'] ?? 1;
$full_name = $_SESSION['full_name'];

// System statistics
$total_students = db()->count('students', 'is_active = 1');
$total_teachers = db()->count('users', "role = 'teacher' AND is_active = 1");
$total_classes = db()->count('classes', 'is_active = 1');
$total_parents = db()->count('users', "role = 'parent' AND is_active = 1");

// Today's attendance
$today = date('Y-m-d');
$today_attendance = db()->fetchOne("
    SELECT
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
        COUNT(CASE WHEN status = 'late' THEN 1 END) as late,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent
    FROM attendance
    WHERE date = ?
", [$today]) ?: ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0];

$attendance_rate = $today_attendance['total'] > 0 ?
    round((($today_attendance['present'] + $today_attendance['late']) / $today_attendance['total']) * 100, 1) : 0;

// Recent activity
$recent_activity = [];
try {
    $recent_activity = db()->fetchAll("
        SELECT al.action, al.created_at, u.first_name, u.last_name, u.role
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10
    ") ?: [];
} catch (Throwable $e) {
    $recent_activity = [];
}

// Risk students
$risk_students = [];
try {
    $risk_students = db()->fetchAll("
        SELECT s.id, s.admission_number, u.first_name, u.last_name,
               COUNT(a.id) as total_days,
               SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
               ROUND((SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 1) as absenteeism_rate
        FROM students s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN attendance a ON a.student_id = s.id
        WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY s.id, s.admission_number, u.first_name, u.last_name
        HAVING (SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100 > 20
        ORDER BY absenteeism_rate DESC
        LIMIT 5
    ") ?: [];
} catch (Throwable $e) {
    $risk_students = [];
}

// AI statistics
$ai_stats = [
    'anomalies_detected' => 0,
    'predictions_made' => 0,
    'security_events' => 0,
    'reports_generated' => 0
];
try {
    if (table_exists('anomaly_reports')) $ai_stats['anomalies_detected'] = db()->count('anomaly_reports', "status = 'new'");
    if (table_exists('security_logs')) $ai_stats['security_events'] = db()->count('security_logs', '1=1');
} catch (Throwable $e) {
}

// Set page variables for layout
$page_title = 'Admin Dashboard';
$page_subtitle = 'System Overview & Management';
$breadcrumbs = [
    ['text' => 'Home', 'href' => '../index.php'],
    ['text' => 'Admin Dashboard']
];

// Start content buffer
ob_start();
?>

<!-- System Overview Stats -->
<div class="stats-grid">
    <div class="card hover-lift">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="icon bg-primary text-white me-3">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div>
                    <h6 class="mb-0">Total Students</h6>
                    <p class="text-muted mb-0"><?php echo number_format($total_students); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card hover-lift">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="icon bg-success text-white me-3">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div>
                    <h6 class="mb-0">Total Teachers</h6>
                    <p class="text-muted mb-0"><?php echo number_format($total_teachers); ?></p>
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
                    <h6 class="mb-0">Total Classes</h6>
                    <p class="text-muted mb-0"><?php echo number_format($total_classes); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card hover-lift">
        <div class="card-body">
            <div class="d-flex align-items-center">
                <div class="icon bg-warning text-white me-3">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h6 class="mb-0">Total Parents</h6>
                    <p class="text-muted mb-0"><?php echo number_format($total_parents); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- AI & System Stats -->
<div class="row mb-6">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-brain me-2"></i>
                    AI System Status
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-3">
                        <div class="text-danger">
                            <h4><?php echo $ai_stats['anomalies_detected']; ?></h4>
                            <small>Anomalies</small>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="text-warning">
                            <h4><?php echo $ai_stats['predictions_made']; ?></h4>
                            <small>Predictions</small>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="text-info">
                            <h4><?php echo $ai_stats['security_events']; ?></h4>
                            <small>Security</small>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="text-success">
                            <h4><?php echo $ai_stats['reports_generated']; ?></h4>
                            <small>Reports</small>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="ai-center/index.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-brain me-2"></i> AI Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle me-2"></i>
                    Today's Attendance
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-3">
                        <div class="text-success">
                            <h4><?php echo $today_attendance['present']; ?></h4>
                            <small>Present</small>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="text-warning">
                            <h4><?php echo $today_attendance['late']; ?></h4>
                            <small>Late</small>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="text-danger">
                            <h4><?php echo $today_attendance['absent']; ?></h4>
                            <small>Absent</small>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="text-primary">
                            <h4><?php echo $today_attendance['total']; ?></h4>
                            <small>Total</small>
                        </div>
                    </div>
                </div>
                <div class="progress mt-3">
                    <div class="progress-bar" style="width: <?php echo $attendance_rate; ?>%"></div>
                </div>
                <p class="text-muted mt-2 mb-0">Attendance Rate: <?php echo $attendance_rate; ?>%</p>
            </div>
        </div>
    </div>
</div>

<!-- Risk Students & Recent Activity -->
<div class="row mb-6">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    At-Risk Students
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($risk_students)): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Admission No.</th>
                                    <th>Absenteeism</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($risk_students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['admission_number']); ?></td>
                                        <td>
                                            <span class="badge badge-danger"><?php echo $student['absenteeism_rate']; ?>%</span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewStudent(<?php echo $student['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No at-risk students identified.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Recent Activity
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_activity)): ?>
                    <div class="list-group">
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($activity['action']); ?></h6>
                                        <p class="text-muted mb-1">
                                            <span class="badge bg-secondary"><?php echo ucfirst($activity['role']); ?></span>
                                        </p>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?> •
                                            <?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No recent activity.</p>
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
        <div class="row">
            <div class="col-md-3 mb-2">
                <a href="ai-center/index.php" class="btn btn-primary w-100">
                    <i class="fas fa-brain me-2"></i> AI Dashboard
                </a>
            </div>
            <div class="col-md-3 mb-2">
                <a href="students.php?action=add" class="btn btn-success w-100">
                    <i class="fas fa-user-plus me-2"></i> Add Student
                </a>
            </div>
            <div class="col-md-3 mb-2">
                <a href="classes.php?action=add" class="btn btn-info w-100">
                    <i class="fas fa-plus-circle me-2"></i> Add Class
                </a>
            </div>
            <div class="col-md-3 mb-2">
                <a href="reports.php" class="btn btn-warning w-100">
                    <i class="fas fa-chart-bar me-2"></i> Generate Report
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    function viewStudent(studentId) {
        window.open('students.php?action=view&id=' + studentId, '_blank');
    }
</script>

<?php
// Get content and include layout
$content = ob_get_clean();
include __DIR__ . '/../../app/views/layouts/main.php';
?>
