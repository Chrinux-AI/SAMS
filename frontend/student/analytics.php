<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_student();


$user_id = $_SESSION['user_id'];
$attendanceDateField = table_has_column('attendance_records', 'attendance_date') ? 'attendance_date' : (table_has_column('attendance_records', 'date') ? 'date' : 'attendance_date');
$student = db()->fetch("
    SELECT s.*, u.first_name, u.last_name, u.email, c.class_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.user_id = ?
", [$user_id]) ?: [
    'id' => 0,
    'user_id' => $user_id,
    'first_name' => $_SESSION['full_name'] ?? 'Student',
    'last_name' => '',
    'email' => $_SESSION['email'] ?? '',
    'student_id' => '',
    'class_name' => null,
];
$studentRecordIds = array_values(array_unique(array_filter([
    (int)($student['user_id'] ?? $user_id),
    (int)($student['id'] ?? 0),
])));
$studentPlaceholders = implode(',', array_fill(0, max(1, count($studentRecordIds)), '?'));

// Get attendance trends (last 30 days)
$attendance_trend = db()->fetchAll("
    SELECT
        DATE_FORMAT({$attendanceDateField}, '%b %d') as day,
        status,
        {$attendanceDateField} as attendance_date
    FROM attendance_records
    WHERE student_id IN ($studentPlaceholders) AND {$attendanceDateField} >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY {$attendanceDateField} ASC
", $studentRecordIds ?: [0]);

// Get monthly attendance stats
$monthly_stats = db()->fetchAll("
    SELECT
        DATE_FORMAT({$attendanceDateField}, '%Y-%m') as month,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as percentage
    FROM attendance_records
    WHERE student_id IN ($studentPlaceholders) AND {$attendanceDateField} >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT({$attendanceDateField}, '%Y-%m')
    ORDER BY month DESC
", $studentRecordIds ?: [0]);

// Get behavior score trends
$behaviorLogsAvailable = table_exists('behavior_logs');
$behavior_trend = $behaviorLogsAvailable ? db()->fetchAll("
    SELECT
        DATE_FORMAT(created_at, '%b %d') as day,
        AVG(behavior_score) as score,
        DATE(created_at) as date
    FROM behavior_logs
    WHERE student_id IN ($studentPlaceholders) AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
", $studentRecordIds ?: [0]) : [];

$page_title = "AI Analytics";
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
    <title><?php echo $page_title; ?> - Attendance AI</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/sams-core.css" rel="stylesheet">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <link href="../assets/css/pwa-styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .analytics-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .chart-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .chart-card h2 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .insight-card {
            background: linear-gradient(135deg, rgba(0, 123, 255, 0.1), rgba(102, 16, 242, 0.1));
            border: 1px solid var(--primary-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .insight-card h3 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .insight-card p {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .stat-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-box h3 {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-box p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .ai-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
    </style>
</head>

<body>
    <div class="starfield"></div>
    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="cyber-main">
            <div class="analytics-container">
        <h1>
            <i class="fas fa-brain"></i> AI-Powered Analytics
            <span class="ai-badge">AI</span>
        </h1>

        <!-- Summary Stats -->
        <div class="stat-summary">
            <?php
            // Calculate overall stats
            $overall = db()->fetch("
                SELECT
                    COUNT(*) as total_days,
                    ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as attendance_rate,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count
                FROM attendance_records
                WHERE student_id IN ($studentPlaceholders)
            ", $studentRecordIds ?: [0]) ?? ['total_days' => 0, 'attendance_rate' => 0, 'late_count' => 0];

            $behavior_avg = $behaviorLogsAvailable ? db()->fetch("
                SELECT AVG(behavior_score) as avg_score
                FROM behavior_logs
                WHERE student_id IN ($studentPlaceholders)
            ", $studentRecordIds ?: [0]) : ['avg_score' => 0];
            ?>

            <div class="stat-box">
                <h3><?php echo $overall['attendance_rate'] ?? 0; ?>%</h3>
                <p>Attendance Rate</p>
            </div>
            <div class="stat-box">
                <h3><?php echo $overall['total_days'] ?? 0; ?></h3>
                <p>Total Days</p>
            </div>
            <div class="stat-box">
                <h3><?php echo $overall['late_count'] ?? 0; ?></h3>
                <p>Late Arrivals</p>
            </div>
            <div class="stat-box">
                <h3><?php echo number_format($behavior_avg['avg_score'] ?? 0, 1); ?>/10</h3>
                <p>Behavior Score</p>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="analytics-grid">
            <!-- Attendance Trend Chart -->
            <div class="chart-card">
                <h2><i class="fas fa-chart-line"></i> 30-Day Attendance Trend</h2>
                <canvas id="attendanceTrendChart"></canvas>
            </div>

            <!-- Monthly Comparison Chart -->
            <div class="chart-card">
                <h2><i class="fas fa-chart-bar"></i> Monthly Attendance Comparison</h2>
                <canvas id="monthlyComparisonChart"></canvas>
            </div>

            <!-- Behavior Trend Chart -->
            <div class="chart-card">
                <h2><i class="fas fa-star"></i> Behavior Score Trend</h2>
                <canvas id="behaviorTrendChart"></canvas>
            </div>

            <!-- Attendance Distribution -->
            <div class="chart-card">
                <h2><i class="fas fa-chart-pie"></i> Attendance Distribution</h2>
                <canvas id="attendanceDistributionChart"></canvas>
            </div>
        </div>

        <!-- AI Insights -->
        <div class="insight-card">
            <h3><i class="fas fa-lightbulb"></i> AI Insights</h3>
            <p>
                <?php
                $attendance_rate = $overall['attendance_rate'] ?? 0;
                $behavior_score = $behavior_avg['avg_score'] ?? 0;

                if ($attendance_rate >= 95) {
                    echo "🎉 Excellent attendance! You're maintaining a {$attendance_rate}% attendance rate, which is outstanding. ";
                } elseif ($attendance_rate >= 85) {
                    echo "👍 Good attendance! Your {$attendance_rate}% attendance rate is solid. ";
                } else {
                    echo "⚠️ Your attendance rate of {$attendance_rate}% needs improvement. ";
                }

                if ($behavior_score >= 8) {
                    echo "Your behavior score of " . number_format($behavior_score, 1) . "/10 shows excellent classroom conduct. ";
                } elseif ($behavior_score >= 6) {
                    echo "Your behavior score of " . number_format($behavior_score, 1) . "/10 is acceptable, but there's room for growth. ";
                } elseif ($behavior_score > 0) {
                    echo "Focus on improving your behavior score of " . number_format($behavior_score, 1) . "/10. ";
                }

                if ($overall['late_count'] > 5) {
                    echo "Try to reduce late arrivals ({$overall['late_count']} times) to improve punctuality. ";
                } elseif ($overall['late_count'] == 0) {
                    echo "Perfect punctuality with zero late arrivals! ";
                }

                echo "Keep up the great work and continue focusing on consistent attendance and positive behavior.";
                ?>
            </p>
        </div>
            </div>
        </main>
    </div>

    <?php include '../includes/sams-bot.php'; ?>

    <script>
        // Prepare data for charts
        <?php
        // Attendance trend data
        $trend_labels = [];
        $trend_present = [];
        $trend_absent = [];
        $trend_late = [];

        foreach ($attendance_trend as $row) {
            $trend_labels[] = $row['day'];
            if ($row['status'] == 'present') {
                $trend_present[] = 1;
                $trend_absent[] = 0;
                $trend_late[] = 0;
            } elseif ($row['status'] == 'absent') {
                $trend_present[] = 0;
                $trend_absent[] = 1;
                $trend_late[] = 0;
            } else {
                $trend_present[] = 0;
                $trend_absent[] = 0;
                $trend_late[] = 1;
            }
        }

        // Monthly stats data
        $monthly_labels = [];
        $monthly_percentages = [];

        foreach ($monthly_stats as $row) {
            $monthly_labels[] = date('M Y', strtotime($row['month'] . '-01'));
            $monthly_percentages[] = $row['percentage'];
        }

        // Behavior trend data
        $behavior_labels = [];
        $behavior_scores = [];

        foreach ($behavior_trend as $row) {
            $behavior_labels[] = $row['day'];
            $behavior_scores[] = round($row['score'], 1);
        }

        // Distribution data
        $dist_data = db()->fetchAll("
            SELECT status, COUNT(*) as count
            FROM attendance_records
            WHERE student_id = ?
            GROUP BY status
        ", [$student['student_id']]);

        $distribution = ['present' => 0, 'absent' => 0, 'late' => 0];
        foreach ($dist_data as $row) {
            $distribution[$row['status']] = $row['count'];
        }
        ?>

        const trendLabels = <?php echo json_encode($trend_labels); ?>;
        const monthlyLabels = <?php echo json_encode(array_reverse($monthly_labels)); ?>;
        const monthlyPercentages = <?php echo json_encode(array_reverse($monthly_percentages)); ?>;
        const behaviorLabels = <?php echo json_encode($behavior_labels); ?>;
        const behaviorScores = <?php echo json_encode($behavior_scores); ?>;

        // Attendance Trend Chart
        new Chart(document.getElementById('attendanceTrendChart'), {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Attendance Status',
                    data: <?php echo json_encode($trend_present); ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 1
                    }
                }
            }
        });

        // Monthly Comparison Chart
        new Chart(document.getElementById('monthlyComparisonChart'), {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Attendance %',
                    data: monthlyPercentages,
                    backgroundColor: 'rgba(0, 123, 255, 0.7)',
                    borderColor: '#007bff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });

        // Behavior Trend Chart
        new Chart(document.getElementById('behaviorTrendChart'), {
            type: 'line',
            data: {
                labels: behaviorLabels,
                datasets: [{
                    label: 'Behavior Score',
                    data: behaviorScores,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 10
                    }
                }
            }
        });

        // Distribution Chart
        new Chart(document.getElementById('attendanceDistributionChart'), {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late'],
                datasets: [{
                    data: [
                        <?php echo $distribution['present']; ?>,
                        <?php echo $distribution['absent']; ?>,
                        <?php echo $distribution['late']; ?>
                    ],
                    backgroundColor: ['#10b981', '#ef4444', '#f59e0b']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
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
