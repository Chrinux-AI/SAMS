<?php

/**
 * Performance Analytics Dashboard
 * Charts for attendance vs grades, identify at-risk students, trend analysis, AI-powered recommendations
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_teacher('../login.php');

$teacherIds = array_values(array_unique(array_filter([
    (int)($_SESSION['assigned_id'] ?? 0),
    (int)($_SESSION['user_id'] ?? 0),
])));
$teacher_id = $teacherIds[0] ?? 0;
$full_name = $_SESSION['full_name'];
$attendanceDateField = table_has_column('attendance_records', 'attendance_date') ? 'attendance_date' : (table_has_column('attendance_records', 'date') ? 'date' : 'attendance_date');
$hasAssignments = table_exists('assignments');
$hasAssignmentSubmissions = table_exists('assignment_submissions');
$supportsGradeAnalytics = $hasAssignments && $hasAssignmentSubmissions;
$teacherPlaceholders = implode(',', array_fill(0, max(1, count($teacherIds)), '?'));

// Get time period from query params
$period = $_GET['period'] ?? '30'; // days
$class_filter = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;

// Get teacher's classes
$classFilterSql = $class_filter ? " AND c.id = ?" : "";
$classFilterParams = $class_filter ? [$class_filter] : [];

$classes = db()->fetchAll("
    SELECT c.*, COUNT(DISTINCT ce.student_id) as student_count
    FROM classes c
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE c.class_teacher_id IN ($teacherPlaceholders)$classFilterSql
    GROUP BY c.id
    ORDER BY c.class_name
", array_merge($teacherIds ?: [0], $classFilterParams));

$baseCorrelationSelect = "
    SELECT
        ce.student_id as student_id,
        CONCAT(u.first_name, ' ', u.last_name) as student_name,
        c.class_name,
        COUNT(DISTINCT CASE WHEN ar.status = 'present' THEN ar.id END) as present_count,
        COUNT(DISTINCT ar.id) as total_attendance,
        ROUND(COUNT(DISTINCT CASE WHEN ar.status = 'present' THEN ar.id END) * 100.0 / NULLIF(COUNT(DISTINCT ar.id), 0), 1) as attendance_rate";
$baseCorrelationFrom = "
    FROM class_enrollments ce
    JOIN users u ON ce.student_id = u.id
    LEFT JOIN attendance_records ar ON ce.student_id = ar.student_id
        AND ar.{$attendanceDateField} >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    JOIN classes c ON ce.class_id = c.id";
$baseCorrelationWhere = "
    WHERE c.class_teacher_id IN ($teacherPlaceholders)$classFilterSql
    GROUP BY ce.student_id, c.id";

// Get attendance vs grades correlation data
if ($supportsGradeAnalytics) {
    $correlation_data = db()->fetchAll(
        $baseCorrelationSelect . ",
        AVG((asub.grade / NULLIF(a.max_points, 0)) * 100) as avg_grade
        $baseCorrelationFrom
        LEFT JOIN assignment_submissions asub ON ce.student_id = asub.student_id AND asub.grade IS NOT NULL
        LEFT JOIN assignments a ON asub.assignment_id = a.id AND a.class_id = c.id
        $baseCorrelationWhere
        HAVING total_attendance > 0
        ORDER BY student_name",
        array_merge([$period], $teacherIds ?: [0], $classFilterParams)
    ) ?: [];
} else {
    $correlation_data = db()->fetchAll(
        $baseCorrelationSelect . ",
        NULL as avg_grade
        $baseCorrelationFrom
        $baseCorrelationWhere
        HAVING total_attendance > 0
        ORDER BY student_name",
        array_merge([$period], $teacherIds ?: [0], $classFilterParams)
    ) ?: [];
}

// Identify at-risk students (low attendance OR low grades)
if ($supportsGradeAnalytics) {
    $at_risk_students = db()->fetchAll("
        SELECT DISTINCT
            ce.student_id as id,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            c.class_name,
            ROUND(COUNT(CASE WHEN ar.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as attendance_rate,
            AVG((asub.grade / NULLIF(a.max_points, 0)) * 100) as avg_grade,
            COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
            MAX(ar.{$attendanceDateField}) as last_present_date
        FROM class_enrollments ce
        JOIN users u ON ce.student_id = u.id
        JOIN classes c ON ce.class_id = c.id
        LEFT JOIN attendance_records ar ON ce.student_id = ar.student_id
            AND ar.{$attendanceDateField} >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        LEFT JOIN assignment_submissions asub ON ce.student_id = asub.student_id AND asub.grade IS NOT NULL
        LEFT JOIN assignments a ON asub.assignment_id = a.id AND a.class_id = c.id
        WHERE c.class_teacher_id IN ($teacherPlaceholders)$classFilterSql
        GROUP BY ce.student_id, c.id
        HAVING attendance_rate < 75 OR avg_grade < 70 OR absent_count >= 5
        ORDER BY attendance_rate ASC, avg_grade ASC
        LIMIT 20
    ", array_merge([$period], $teacherIds ?: [0], $classFilterParams)) ?: [];
} else {
    $at_risk_students = db()->fetchAll("
        SELECT DISTINCT
            ce.student_id as id,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            c.class_name,
            ROUND(COUNT(CASE WHEN ar.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as attendance_rate,
            NULL as avg_grade,
            COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
            MAX(ar.{$attendanceDateField}) as last_present_date
        FROM class_enrollments ce
        JOIN users u ON ce.student_id = u.id
        JOIN classes c ON ce.class_id = c.id
        LEFT JOIN attendance_records ar ON ce.student_id = ar.student_id
            AND ar.{$attendanceDateField} >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        WHERE c.class_teacher_id IN ($teacherPlaceholders)$classFilterSql
        GROUP BY ce.student_id, c.id
        HAVING attendance_rate < 75 OR absent_count >= 5
        ORDER BY attendance_rate ASC, absent_count DESC
        LIMIT 20
    ", array_merge([$period], $teacherIds ?: [0], $classFilterParams)) ?: [];
}

// Get trend data (last 8 weeks)
if ($supportsGradeAnalytics) {
    $trend_data = db()->fetchAll("
        SELECT
            WEEK(ar.{$attendanceDateField}) as week_num,
            DATE_FORMAT(MIN(ar.{$attendanceDateField}), '%b %d') as week_start,
            ROUND(COUNT(CASE WHEN ar.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as attendance_rate,
            AVG((asub.grade / NULLIF(a.max_points, 0)) * 100) as avg_grade
        FROM attendance_records ar
        JOIN class_enrollments ce ON ar.student_id = ce.student_id
        JOIN classes c ON ce.class_id = c.id
        LEFT JOIN assignment_submissions asub ON ce.student_id = asub.student_id
            AND WEEK(asub.submitted_at) = WEEK(ar.{$attendanceDateField})
            AND asub.grade IS NOT NULL
        LEFT JOIN assignments a ON asub.assignment_id = a.id
        WHERE c.class_teacher_id IN ($teacherPlaceholders)
            AND ar.{$attendanceDateField} >= DATE_SUB(CURDATE(), INTERVAL 56 DAY)$classFilterSql
        GROUP BY WEEK(ar.{$attendanceDateField})
        ORDER BY week_num DESC
        LIMIT 8
    ", array_merge($teacherIds ?: [0], $classFilterParams)) ?: [];
} else {
    $trend_data = db()->fetchAll("
        SELECT
            WEEK(ar.{$attendanceDateField}) as week_num,
            DATE_FORMAT(MIN(ar.{$attendanceDateField}), '%b %d') as week_start,
            ROUND(COUNT(CASE WHEN ar.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 1) as attendance_rate,
            NULL as avg_grade
        FROM attendance_records ar
        JOIN class_enrollments ce ON ar.student_id = ce.student_id
        JOIN classes c ON ce.class_id = c.id
        WHERE c.class_teacher_id IN ($teacherPlaceholders)
            AND ar.{$attendanceDateField} >= DATE_SUB(CURDATE(), INTERVAL 56 DAY)$classFilterSql
        GROUP BY WEEK(ar.{$attendanceDateField})
        ORDER BY week_num DESC
        LIMIT 8
    ", array_merge($teacherIds ?: [0], $classFilterParams)) ?: [];
}
$trend_data = array_reverse($trend_data);

// Calculate overall statistics
$overall_stats = $supportsGradeAnalytics
    ? db()->fetchOne("
        SELECT
            COUNT(DISTINCT ce.student_id) as total_students,
            ROUND(AVG(CASE WHEN ar.status = 'present' THEN 100 ELSE 0 END), 1) as avg_attendance,
            ROUND(AVG((asub.grade / NULLIF(a.max_points, 0)) * 100), 1) as avg_grade,
            COUNT(DISTINCT CASE WHEN ar.status = 'absent' THEN ce.student_id END) as students_with_absences
        FROM class_enrollments ce
        JOIN classes c ON ce.class_id = c.id
        LEFT JOIN attendance_records ar ON ce.student_id = ar.student_id
            AND ar.{$attendanceDateField} >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        LEFT JOIN assignment_submissions asub ON ce.student_id = asub.student_id AND asub.grade IS NOT NULL
        LEFT JOIN assignments a ON asub.assignment_id = a.id AND a.class_id = c.id
        WHERE c.class_teacher_id IN ($teacherPlaceholders)$classFilterSql
    ", array_merge([$period], $teacherIds ?: [0], $classFilterParams))
    : db()->fetchOne("
        SELECT
            COUNT(DISTINCT ce.student_id) as total_students,
            ROUND(AVG(CASE WHEN ar.status = 'present' THEN 100 ELSE 0 END), 1) as avg_attendance,
            NULL as avg_grade,
            COUNT(DISTINCT CASE WHEN ar.status = 'absent' THEN ce.student_id END) as students_with_absences
        FROM class_enrollments ce
        JOIN classes c ON ce.class_id = c.id
        LEFT JOIN attendance_records ar ON ce.student_id = ar.student_id
            AND ar.{$attendanceDateField} >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        WHERE c.class_teacher_id IN ($teacherPlaceholders)$classFilterSql
    ", array_merge([$period], $teacherIds ?: [0], $classFilterParams));
$overall_stats = $overall_stats ?: [
    'total_students' => 0,
    'avg_attendance' => 0,
    'avg_grade' => null,
    'students_with_absences' => 0,
];

// AI-powered recommendations
$recommendations = [];
if ($overall_stats['avg_attendance'] < 85) {
    $recommendations[] = [
        'type' => 'warning',
        'icon' => 'exclamation-triangle',
        'title' => 'Low Overall Attendance',
        'description' => "Class attendance is at {$overall_stats['avg_attendance']}%. Consider implementing attendance incentives or investigating barriers.",
        'action' => 'Review attendance patterns and contact parents of frequently absent students.'
    ];
}
if ($overall_stats['avg_grade'] !== null && $overall_stats['avg_grade'] < 75) {
    $recommendations[] = [
        'type' => 'danger',
        'icon' => 'chart-line',
        'title' => 'Below Average Performance',
        'description' => "Class average grade is {$overall_stats['avg_grade']}%. Students may need additional support.",
        'action' => 'Consider review sessions, tutoring programs, or adjusting teaching methods.'
    ];
}
if (count($at_risk_students) > 5) {
    $recommendations[] = [
        'type' => 'warning',
        'icon' => 'user-shield',
        'title' => 'Multiple At-Risk Students',
        'description' => count($at_risk_students) . " students identified as at-risk based on attendance and grades.",
        'action' => 'Schedule individual meetings or create intervention plans for struggling students.'
    ];
}
if (empty($recommendations)) {
    $recommendations[] = [
        'type' => 'success',
        'icon' => 'check-circle',
        'title' => 'Excellent Performance',
        'description' => 'Class is performing well overall. Keep up the great work!',
        'action' => 'Continue current teaching strategies and maintain engagement levels.'
    ];
}

$page_title = 'Performance Analytics';
$page_icon = 'chart-line';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Rajdhana:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/sams-core.css" rel="stylesheet">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>


    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 350px;
            margin: 20px 0;
        }

        .risk-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .risk-high {
            background: rgba(255, 69, 0, 0.2);
            color: var(--danger-red);
        }

        .risk-medium {
            background: rgba(255, 140, 0, 0.2);
            color: #ff8c00;
        }

        .risk-low {
            background: rgba(255, 255, 0, 0.2);
            color: var(--golden-pulse);
        }

        .recommendation-card {
            background: linear-gradient(135deg, rgba(0, 191, 255, 0.05), rgba(138, 43, 226, 0.05));
            border-left: 4px solid;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
        }

        .recommendation-card.success {
            border-left-color: var(--neon-green);
        }

        .recommendation-card.warning {
            border-left-color: var(--golden-pulse);
        }

        .recommendation-card.danger {
            border-left-color: var(--danger-red);
        }
    </style>
</head>

<body>
    <div class="starfield"></div>

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <select onchange="window.location.href='?period='+this.value+'<?php echo $class_filter ? '&class_id=' . $class_filter : ''; ?>'" class="cyber-input" style="width:150px;">
                        <option value="7" <?php echo $period == '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="30" <?php echo $period == '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="60" <?php echo $period == '60' ? 'selected' : ''; ?>>Last 60 Days</option>
                        <option value="90" <?php echo $period == '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                    </select>
                    <select onchange="window.location.href='?class_id='+this.value+'&period=<?php echo $period; ?>'" class="cyber-input" style="width:200px;">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </header>

            <div class="app-layout">
                <!-- Overall Statistics -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:25px;">
                    <div class="stat-orb primary">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-value"><?php echo $overall_stats['total_students']; ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-orb <?php echo $overall_stats['avg_attendance'] >= 90 ? 'success' : ($overall_stats['avg_attendance'] >= 75 ? 'warning' : 'danger'); ?>">
                        <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
                        <div class="stat-value"><?php echo $overall_stats['avg_attendance'] ?? 'N/A'; ?>%</div>
                        <div class="stat-label">Avg Attendance</div>
                    </div>
                    <div class="stat-orb <?php echo $overall_stats['avg_grade'] >= 80 ? 'success' : ($overall_stats['avg_grade'] >= 70 ? 'warning' : 'danger'); ?>">
                        <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                        <div class="stat-value"><?php echo $overall_stats['avg_grade'] ?? 'N/A'; ?>%</div>
                        <div class="stat-label">Avg Grade</div>
                    </div>
                    <div class="stat-orb danger">
                        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="stat-value"><?php echo count($at_risk_students); ?></div>
                        <div class="stat-label">At-Risk Students</div>
                    </div>
                </div>

                <!-- AI Recommendations -->
                <div class="holo-card" style="margin-bottom:25px;">
                    <h3 style="margin-bottom:20px;"><i class="fas fa-robot"></i> AI-Powered Recommendations</h3>
                    <?php foreach ($recommendations as $rec): ?>
                        <div class="recommendation-card <?php echo $rec['type']; ?>">
                            <div style="display:flex;gap:15px;">
                                <div style="font-size:2rem;opacity:0.7;">
                                    <i class="fas fa-<?php echo $rec['icon']; ?>"></i>
                                </div>
                                <div style="flex:1;">
                                    <h4 style="margin-bottom:8px;color:var(--text-primary);"><?php echo $rec['title']; ?></h4>
                                    <p style="margin-bottom:10px;color:var(--text-muted);"><?php echo $rec['description']; ?></p>
                                    <div style="background:rgba(0,0,0,0.3);border-radius:8px;padding:10px;font-size:0.9rem;">
                                        <strong style="color:var(--cyber-cyan);">Recommended Action:</strong> <?php echo $rec['action']; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(500px,1fr));gap:25px;margin-bottom:25px;">
                    <!-- Attendance vs Grades Correlation -->
                    <div class="holo-card">
                        <h3 style="margin-bottom:20px;"><i class="fas fa-chart-scatter"></i> Attendance vs Grades Correlation</h3>
                        <div class="chart-container">
                            <canvas id="correlationChart"></canvas>
                        </div>
                        <div style="text-align:center;margin-top:15px;color:var(--text-muted);font-size:0.9rem;">
                            Each point represents a student. Hover for details.
                        </div>
                    </div>

                    <!-- Trend Analysis -->
                    <div class="holo-card">
                        <h3 style="margin-bottom:20px;"><i class="fas fa-chart-area"></i> 8-Week Trend Analysis</h3>
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- At-Risk Students -->
                <div class="holo-card">
                    <h3 style="margin-bottom:20px;"><i class="fas fa-user-shield"></i> At-Risk Students (Intervention Needed)</h3>
                    <?php if (empty($at_risk_students)): ?>
                        <div style="text-align:center;padding:40px;color:var(--text-muted);">
                            <i class="fas fa-check-circle" style="font-size:3rem;margin-bottom:15px;color:var(--neon-green);opacity:0.5;"></i>
                            <div>No at-risk students identified! Excellent work!</div>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="cyber-table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Class</th>
                                        <th>Attendance Rate</th>
                                        <th>Avg Grade</th>
                                        <th>Absences</th>
                                        <th>Last Present</th>
                                        <th>Risk Level</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($at_risk_students as $student):
                                        $risk_score = 0;
                                        if ($student['attendance_rate'] < 60) $risk_score += 3;
                                        elseif ($student['attendance_rate'] < 75) $risk_score += 2;
                                        if ($student['avg_grade'] < 60) $risk_score += 3;
                                        elseif ($student['avg_grade'] < 70) $risk_score += 2;
                                        if ($student['absent_count'] >= 10) $risk_score += 2;

                                        $risk_level = $risk_score >= 5 ? 'HIGH' : ($risk_score >= 3 ? 'MEDIUM' : 'LOW');
                                        $risk_class = $risk_score >= 5 ? 'risk-high' : ($risk_score >= 3 ? 'risk-medium' : 'risk-low');
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($student['student_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($student['class_name']); ?></td>
                                            <td>
                                                <span style="color:<?php echo $student['attendance_rate'] < 60 ? 'var(--danger-red)' : ($student['attendance_rate'] < 75 ? 'var(--golden-pulse)' : 'var(--neon-green)'); ?>">
                                                    <?php echo $student['attendance_rate'] ?? 'N/A'; ?>%
                                                </span>
                                            </td>
                                            <td>
                                                <span style="color:<?php echo $student['avg_grade'] < 60 ? 'var(--danger-red)' : ($student['avg_grade'] < 70 ? 'var(--golden-pulse)' : 'var(--neon-green)'); ?>">
                                                    <?php echo $student['avg_grade'] ? number_format($student['avg_grade'], 1) . '%' : 'N/A'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $student['absent_count']; ?></td>
                                            <td><?php echo $student['last_present_date'] ? date('M d', strtotime($student['last_present_date'])) : 'Never'; ?></td>
                                            <td><span class="risk-badge <?php echo $risk_class; ?>"><?php echo $risk_level; ?></span></td>
                                            <td>
                                                <button onclick="contactParent(<?php echo $student['id']; ?>)" class="cyber-btn primary" title="Contact Parent">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Attendance vs Grades Scatter Plot
        const correlationCtx = document.getElementById('correlationChart').getContext('2d');
        new Chart(correlationCtx, {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Students',
                    data: <?php echo json_encode(array_map(function ($s) {
                                return [
                                    'x' => $s['attendance_rate'] ?? 0,
                                    'y' => $s['avg_grade'] ?? 0,
                                    'label' => $s['student_name']
                                ];
                            }, $correlation_data)); ?>,
                    backgroundColor: 'rgba(0,191,255,0.6)',
                    borderColor: '#00bfff',
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw.label + ': ' + context.parsed.x + '% attendance, ' + context.parsed.y.toFixed(1) + '% grade';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Attendance Rate (%)',
                            color: '#fff'
                        },
                        min: 0,
                        max: 100,
                        ticks: {
                            color: '#fff'
                        },
                        grid: {
                            color: 'rgba(255,255,255,0.1)'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Average Grade (%)',
                            color: '#fff'
                        },
                        min: 0,
                        max: 100,
                        ticks: {
                            color: '#fff'
                        },
                        grid: {
                            color: 'rgba(255,255,255,0.1)'
                        }
                    }
                }
            }
        });

        // Trend Analysis Line Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($trend_data, 'week_start')); ?>,
                datasets: [{
                        label: 'Attendance Rate',
                        data: <?php echo json_encode(array_column($trend_data, 'attendance_rate')); ?>,
                        borderColor: '#00f3ff',
                        backgroundColor: 'rgba(0,243,255,0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Average Grade',
                        data: <?php echo json_encode(array_column($trend_data, 'avg_grade')); ?>,
                        borderColor: '#00ff7f',
                        backgroundColor: 'rgba(0,255,127,0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#fff'
                        }
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 100,
                        ticks: {
                            color: '#fff'
                        },
                        grid: {
                            color: 'rgba(255,255,255,0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#fff'
                        },
                        grid: {
                            color: 'rgba(255,255,255,0.1)'
                        }
                    }
                }
            }
        });

        function contactParent(studentId) {
            window.location.href = `parent-comms.php?student_id=${studentId}`;
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
