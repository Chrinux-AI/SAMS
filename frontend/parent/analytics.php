<?php

/**
 * Parent Progress Analytics Dashboard
 * Advanced features: Attendance vs grades correlation, AI recommendations,
 * sibling comparisons, trend analysis, at-risk alerts
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_parent();

$parent_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$tenantId = current_tenant_id();

// Get time period (default: last 90 days)
$days = isset($_GET['days']) ? intval($_GET['days']) : 90;
$start_date = date('Y-m-d', strtotime("-$days days"));
$end_date = date('Y-m-d');

// Get all linked children with comprehensive stats
$children = get_parent_linked_children((int)$parent_id, (int)$tenantId);
$attendanceDateField = table_exists('attendance_records')
    ? (table_has_column('attendance_records', 'check_in_time') ? 'check_in_time' : (table_has_column('attendance_records', 'attendance_date') ? 'attendance_date' : null))
    : null;
$gradeDateField = table_exists('grades')
    ? (table_has_column('grades', 'grade_date') ? 'grade_date' : (table_has_column('grades', 'created_at') ? 'created_at' : null))
    : null;

// Calculate family statistics
$family_stats = [
    'total_children' => count($children),
    'avg_attendance' => 0,
    'avg_grade' => 0,
    'at_risk_count' => 0,
    'excellent_count' => 0
];

$correlation_data = [];
foreach ($children as &$child) {
    $studentIdentifiers = array_values(array_unique(array_filter([
        (int)($child['user_id'] ?? 0),
        (int)($child['student_profile_id'] ?? 0),
    ])));
    $presentCount = 0;
    $totalAttendance = 0;
    $grade = 0.0;

    if (!empty($studentIdentifiers) && $attendanceDateField !== null) {
        $placeholders = implode(',', array_fill(0, count($studentIdentifiers), '?'));
        $presentCount = (int)(db()->fetchOne("
            SELECT COUNT(*) AS count
            FROM attendance_records ar
            WHERE ar.student_id IN ({$placeholders}) AND ar.status = 'present'
              AND DATE(ar.{$attendanceDateField}) >= ?
        ", array_merge($studentIdentifiers, [$start_date]))['count'] ?? 0);
        $totalAttendance = (int)(db()->fetchOne("
            SELECT COUNT(*) AS count
            FROM attendance_records ar
            WHERE ar.student_id IN ({$placeholders}) AND DATE(ar.{$attendanceDateField}) >= ?
        ", array_merge($studentIdentifiers, [$start_date]))['count'] ?? 0);
    }

    if (!empty($studentIdentifiers) && $gradeDateField !== null && table_has_column('grades', 'points_earned') && table_has_column('grades', 'max_points')) {
        $placeholders = implode(',', array_fill(0, count($studentIdentifiers), '?'));
        $grade = (float)(db()->fetchOne("
            SELECT AVG(g.points_earned / NULLIF(g.max_points, 0) * 100) AS avg_grade
            FROM grades g
            WHERE g.student_id IN ({$placeholders}) AND DATE(g.{$gradeDateField}) >= ?
        ", array_merge($studentIdentifiers, [$start_date]))['avg_grade'] ?? 0);
    }

    $child['present_count'] = $presentCount;
    $child['total_attendance'] = $totalAttendance;
    $child['avg_grade'] = $grade;

    $attendance_rate = $totalAttendance > 0 ? ($presentCount / $totalAttendance * 100) : 0;

    $child['attendance_rate'] = $attendance_rate;
    $child['avg_grade'] = $grade;

    $family_stats['avg_attendance'] += $attendance_rate;
    $family_stats['avg_grade'] += $grade;

    // At-risk identification (attendance < 80% OR grade < 70%)
    if ($attendance_rate < 80 || $grade < 70) {
        $family_stats['at_risk_count']++;
        $child['risk_level'] = 'HIGH';
    } elseif ($attendance_rate < 90 || $grade < 80) {
        $child['risk_level'] = 'MEDIUM';
    } else {
        $family_stats['excellent_count']++;
        $child['risk_level'] = 'LOW';
    }

    $correlation_data[] = [
        'name' => $child['first_name'],
        'attendance' => round($attendance_rate, 1),
        'grade' => round($grade, 1)
    ];
}
unset($child);

if ($family_stats['total_children'] > 0) {
    $family_stats['avg_attendance'] /= $family_stats['total_children'];
    $family_stats['avg_grade'] /= $family_stats['total_children'];
}

// Get trend data (last 8 weeks)
$trend_data = [];
$familyIdentifiers = [];
foreach ($children as $child) {
    foreach ([(int)($child['user_id'] ?? 0), (int)($child['student_profile_id'] ?? 0)] as $identifier) {
        if ($identifier > 0 && !in_array($identifier, $familyIdentifiers, true)) {
            $familyIdentifiers[] = $identifier;
        }
    }
}
for ($i = 7; $i >= 0; $i--) {
    $week_start = date('Y-m-d', strtotime("-$i weeks"));
    $week_end = date('Y-m-d', strtotime("-$i weeks + 6 days"));

    $present = 0;
    $total = 0;
    $avgGrade = 0.0;

    if (!empty($familyIdentifiers) && $attendanceDateField !== null) {
        $placeholders = implode(',', array_fill(0, count($familyIdentifiers), '?'));
        $present = (int)(db()->fetchOne("
            SELECT COUNT(*) AS count
            FROM attendance_records ar
            WHERE ar.student_id IN ({$placeholders}) AND ar.status = 'present'
              AND DATE(ar.{$attendanceDateField}) BETWEEN ? AND ?
        ", array_merge($familyIdentifiers, [$week_start, $week_end]))['count'] ?? 0);
        $total = (int)(db()->fetchOne("
            SELECT COUNT(*) AS count
            FROM attendance_records ar
            WHERE ar.student_id IN ({$placeholders}) AND DATE(ar.{$attendanceDateField}) BETWEEN ? AND ?
        ", array_merge($familyIdentifiers, [$week_start, $week_end]))['count'] ?? 0);
    }

    if (!empty($familyIdentifiers) && $gradeDateField !== null && table_has_column('grades', 'points_earned') && table_has_column('grades', 'max_points')) {
        $placeholders = implode(',', array_fill(0, count($familyIdentifiers), '?'));
        $avgGrade = (float)(db()->fetchOne("
            SELECT AVG(g.points_earned / NULLIF(g.max_points, 0) * 100) AS avg_grade
            FROM grades g
            WHERE g.student_id IN ({$placeholders}) AND DATE(g.{$gradeDateField}) BETWEEN ? AND ?
        ", array_merge($familyIdentifiers, [$week_start, $week_end]))['avg_grade'] ?? 0);
    }

    $attendance_rate = $total > 0 ? ($present / $total * 100) : 0;

    $trend_data[] = [
        'week' => date('M d', strtotime($week_start)),
        'attendance' => round($attendance_rate, 1),
        'grade' => round($avgGrade, 1)
    ];
}

// AI-powered recommendations
$recommendations = [];
if ($family_stats['avg_attendance'] < 90) {
    $recommendations[] = [
        'icon' => 'clock',
        'color' => '#ff4500',
        'title' => 'Improve Attendance',
        'message' => 'Family attendance is below optimal (90%). Consider setting morning routines and ensuring early bedtimes for better punctuality.'
    ];
}
if ($family_stats['avg_grade'] < 80) {
    $recommendations[] = [
        'icon' => 'book',
        'color' => '#ffff00',
        'title' => 'Academic Support Needed',
        'message' => 'Average grades could improve. Schedule dedicated homework time and consider tutoring for struggling subjects.'
    ];
}
if ($family_stats['at_risk_count'] > 0) {
    $recommendations[] = [
        'icon' => 'exclamation-triangle',
        'color' => '#ff0000',
        'title' => 'At-Risk Alert',
        'message' => "{$family_stats['at_risk_count']} child(ren) showing risk indicators. Meet with teachers to develop intervention plans."
    ];
}
if (empty($recommendations)) {
    $recommendations[] = [
        'icon' => 'trophy',
        'color' => '#00ff7f',
        'title' => 'Excellent Progress',
        'message' => 'Your children are performing well! Continue encouraging consistent study habits and attendance.'
    ];
}

$page_title = 'Family Analytics';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <link href="../assets/css/sams-core.css" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
                    <select onchange="window.location.href='?days='+this.value" class="cyber-input" style="width:auto;">
                        <option value="7" <?php echo $days == 7 ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="30" <?php echo $days == 30 ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="60" <?php echo $days == 60 ? 'selected' : ''; ?>>Last 60 Days</option>
                        <option value="90" <?php echo $days == 90 ? 'selected' : ''; ?>>Last 90 Days</option>
                    </select>
                </div>
            </header>

            <div style="display:grid; gap:24px;">
                <!-- Family Statistics -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:30px;">
                    <div class="stat-orb primary">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <div class="stat-value"><?php echo $family_stats['total_children']; ?></div>
                        <div class="stat-label">Children</div>
                    </div>
                    <div class="stat-orb <?php echo $family_stats['avg_attendance'] >= 90 ? 'success' : ($family_stats['avg_attendance'] >= 80 ? 'warning' : 'danger'); ?>">
                        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="stat-value"><?php echo number_format($family_stats['avg_attendance'], 1); ?>%</div>
                        <div class="stat-label">Family Attendance</div>
                    </div>
                    <div class="stat-orb <?php echo $family_stats['avg_grade'] >= 90 ? 'success' : ($family_stats['avg_grade'] >= 80 ? 'warning' : 'danger'); ?>">
                        <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
                        <div class="stat-value"><?php echo number_format($family_stats['avg_grade'], 1); ?>%</div>
                        <div class="stat-label">Average Grade</div>
                    </div>
                    <div class="stat-orb <?php echo $family_stats['at_risk_count'] > 0 ? 'danger' : 'success'; ?>">
                        <div class="stat-icon"><i class="fas fa-<?php echo $family_stats['at_risk_count'] > 0 ? 'exclamation-triangle' : 'trophy'; ?>"></i></div>
                        <div class="stat-value"><?php echo $family_stats['at_risk_count']; ?></div>
                        <div class="stat-label">At-Risk</div>
                    </div>
                </div>

                <div class="grid-2" style="margin-bottom:30px;">
                    <!-- Attendance vs Grades Correlation -->
                    <div class="holo-card">
                        <h3 style="margin-bottom:20px;"><i class="fas fa-chart-scatter"></i> Attendance vs Grade Correlation</h3>
                        <canvas id="correlationChart" style="max-height:400px;"></canvas>
                    </div>

                    <!-- 8-Week Trend Analysis -->
                    <div class="holo-card">
                        <h3 style="margin-bottom:20px;"><i class="fas fa-chart-line"></i> Family Trend (8 Weeks)</h3>
                        <canvas id="trendChart" style="max-height:400px;"></canvas>
                    </div>
                </div>

                <!-- AI Recommendations -->
                <div class="holo-card" style="margin-bottom:30px;">
                    <h3 style="margin-bottom:20px;"><i class="fas fa-robot"></i> AI-Powered Recommendations</h3>
                    <div style="display:grid;gap:15px;">
                        <?php foreach ($recommendations as $rec): ?>
                            <div style="padding:20px;background:linear-gradient(135deg,rgba(0,191,255,0.05),rgba(138,43,226,0.05));border-left:4px solid <?php echo $rec['color']; ?>;border-radius:12px;">
                                <div style="display:flex;align-items:start;gap:15px;">
                                    <div style="width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,<?php echo $rec['color']; ?>20,<?php echo $rec['color']; ?>40);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="fas fa-<?php echo $rec['icon']; ?>" style="font-size:1.5rem;color:<?php echo $rec['color']; ?>;"></i>
                                    </div>
                                    <div>
                                        <h4 style="color:<?php echo $rec['color']; ?>;margin-bottom:8px;font-size:1.1rem;"><?php echo $rec['title']; ?></h4>
                                        <p style="color:#475569;line-height:1.6;margin:0;"><?php echo $rec['message']; ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Individual Child Performance -->
                <div class="holo-card">
                    <h3 style="margin-bottom:20px;"><i class="fas fa-child"></i> Individual Performance</h3>
                    <div style="overflow-x:auto;">
                        <table class="cyber-table">
                            <thead>
                                <tr>
                                    <th>Child</th>
                                    <th>Attendance Rate</th>
                                    <th>Average Grade</th>
                                    <th>Risk Level</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($children as $child): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($child['child_name']); ?></strong></td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px;">
                                                <div style="flex:1;height:8px;background:rgba(255,255,255,0.1);border-radius:4px;overflow:hidden;">
                                                    <div style="height:100%;width:<?php echo $child['attendance_rate']; ?>%;background:<?php echo $child['attendance_rate'] >= 90 ? '#00ff7f' : ($child['attendance_rate'] >= 80 ? '#ffff00' : '#ff4500'); ?>;"></div>
                                                </div>
                                                <span style="font-weight:700;color:<?php echo $child['attendance_rate'] >= 90 ? '#00ff7f' : ($child['attendance_rate'] >= 80 ? '#ffff00' : '#ff4500'); ?>;"><?php echo number_format($child['attendance_rate'], 1); ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-size:1.2rem;font-weight:700;color:<?php echo $child['avg_grade'] >= 90 ? '#00ff7f' : ($child['avg_grade'] >= 80 ? '#00f3ff' : ($child['avg_grade'] >= 70 ? '#ffff00' : '#ff4500')); ?>;">
                                                <?php echo number_format($child['avg_grade'], 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <span class="cyber-badge <?php echo $child['risk_level'] === 'HIGH' ? 'danger' : ($child['risk_level'] === 'MEDIUM' ? 'warning' : 'success'); ?>">
                                                <?php echo $child['risk_level']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="attendance.php?student=<?php echo $child['id']; ?>" class="cyber-btn btn-sm"><i class="fas fa-calendar"></i> Attendance</a>
                                            <a href="grades.php?child=<?php echo $child['id']; ?>" class="cyber-btn btn-sm primary"><i class="fas fa-chart-bar"></i> Grades</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Correlation Scatter Chart
        const correlationCtx = document.getElementById('correlationChart').getContext('2d');
        new Chart(correlationCtx, {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Children',
                    data: <?php echo json_encode(array_map(fn($d) => ['x' => $d['attendance'], 'y' => $d['grade']], $correlation_data)); ?>,
                    backgroundColor: 'rgba(0, 243, 255, 0.6)',
                    borderColor: '#00f3ff',
                    borderWidth: 2,
                    pointRadius: 8,
                    pointHoverRadius: 12
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const child = <?php echo json_encode($correlation_data); ?>[context.dataIndex];
                                return `${child.name}: ${child.attendance}% attendance, ${child.grade}% grade`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Attendance Rate (%)',
                            color: '#00f3ff'
                        },
                        min: 0,
                        max: 100,
                        grid: {
                            color: 'rgba(0, 243, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Grade (%)',
                            color: '#00f3ff'
                        },
                        min: 0,
                        max: 100,
                        grid: {
                            color: 'rgba(0, 243, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                }
            }
        });

        // Trend Line Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($trend_data, 'week')); ?>,
                datasets: [{
                        label: 'Attendance %',
                        data: <?php echo json_encode(array_column($trend_data, 'attendance')); ?>,
                        borderColor: '#00ff7f',
                        backgroundColor: 'rgba(0, 255, 127, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Grade %',
                        data: <?php echo json_encode(array_column($trend_data, 'grade')); ?>,
                        borderColor: '#00f3ff',
                        backgroundColor: 'rgba(0, 243, 255, 0.1)',
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
                        display: true,
                        labels: {
                            color: 'rgba(255, 255, 255, 0.9)'
                        }
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 100,
                        grid: {
                            color: 'rgba(0, 243, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 243, 255, 0.1)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        }
                    }
                }
            }
        });
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
