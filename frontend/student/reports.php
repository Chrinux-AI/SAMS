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

// Get attendance statistics
$attendance_stats = db()->fetch("
    SELECT
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
        ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as attendance_percentage
    FROM attendance_records
    WHERE student_id IN ($studentPlaceholders)
", $studentRecordIds ?: [0]) ?? ['total_days' => 0, 'present_days' => 0, 'absent_days' => 0, 'late_days' => 0, 'attendance_percentage' => 0];

// Get recent attendance records
$recent_attendance = db()->fetchAll("
    SELECT {$attendanceDateField} as attendance_date, status, remarks
    FROM attendance_records
    WHERE student_id IN ($studentPlaceholders)
    ORDER BY {$attendanceDateField} DESC
    LIMIT 10
", $studentRecordIds ?: [0]);

// Get behavior logs
$behaviorLogsAvailable = table_exists('behavior_logs');
$behavior_logs = $behaviorLogsAvailable ? db()->fetchAll("
    SELECT bl.*, CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM behavior_logs bl
    JOIN users u ON bl.teacher_id = u.id
    WHERE bl.student_id IN ($studentPlaceholders)
    ORDER BY bl.created_at DESC
    LIMIT 5
") : [];

// Calculate behavior score
$behavior_result = $behaviorLogsAvailable ? db()->fetch("
    SELECT AVG(behavior_score) as avg_score
    FROM behavior_logs
    WHERE student_id IN ($studentPlaceholders)
", $studentRecordIds ?: [0]) : ['avg_score' => 0];
$behavior_score = $behavior_result['avg_score'] ?? 0;

$page_title = "My Reports";
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
    <style>
        .reports-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .report-section {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .report-section h2 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .reports-container .stat-orb {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            color: white;
            border: none;
        }

        .reports-container .stat-orb h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #fff;
        }

        .reports-container .stat-orb p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .attendance-table th,
        .attendance-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .attendance-table th {
            background: var(--bg-secondary);
            color: var(--primary-color);
            font-weight: 600;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-present {
            background: #10b981;
            color: white;
        }

        .status-absent {
            background: #ef4444;
            color: white;
        }

        .status-late {
            background: #f59e0b;
            color: white;
        }

        .progress-bar {
            width: 100%;
            height: 30px;
            background: var(--bg-secondary);
            border-radius: 15px;
            overflow: hidden;
            margin-top: 1rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            transition: width 0.3s ease;
        }

        .behavior-item {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            border-left: 3px solid var(--primary-color);
        }

        .behavior-item h4 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .behavior-item p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0.25rem 0;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-tertiary);
        }

        @media print {

            .cyber-nav,
            .action-buttons,
            .sams-bot-container {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    <div class="starfield"></div>
    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="cyber-main">
            <div class="reports-container">
        <h1><i class="fas fa-chart-line"></i> My Academic Reports</h1>

        <!-- Attendance Overview -->
        <div class="report-section">
            <h2><i class="fas fa-calendar-check"></i> Attendance Overview</h2>

            <div class="stats-grid">
                <div class="stat-orb">
                    <h3><?php echo $attendance_stats['total_days'] ?? 0; ?></h3>
                    <p>Total Days Recorded</p>
                </div>
                <div class="stat-orb">
                    <h3><?php echo $attendance_stats['present_days'] ?? 0; ?></h3>
                    <p>Days Present</p>
                </div>
                <div class="stat-orb">
                    <h3><?php echo $attendance_stats['absent_days'] ?? 0; ?></h3>
                    <p>Days Absent</p>
                </div>
                <div class="stat-orb">
                    <h3><?php echo $attendance_stats['late_days'] ?? 0; ?></h3>
                    <p>Days Late</p>
                </div>
            </div>

            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $attendance_stats['attendance_percentage'] ?? 0; ?>%">
                    <?php echo number_format($attendance_stats['attendance_percentage'] ?? 0, 2); ?>% Attendance
                </div>
            </div>

            <h3 style="margin-top: 2rem; color: var(--text-primary);">Recent Attendance Records</h3>
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_attendance) > 0): ?>
                        <?php foreach ($recent_attendance as $record): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($record['status']); ?>">
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($record['remarks'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: var(--text-secondary);">
                                No attendance records found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Behavior Report -->
        <div class="report-section">
            <h2><i class="fas fa-star"></i> Behavior Report</h2>

            <div class="stats-grid">
                <div class="stat-orb">
                    <h3><?php echo number_format($behavior_score, 1); ?>/10</h3>
                    <p>Average Behavior Score</p>
                </div>
            </div>

            <h3 style="margin-top: 2rem; color: var(--text-primary);">Recent Behavior Logs</h3>
            <?php if (count($behavior_logs) > 0): ?>
                <?php foreach ($behavior_logs as $log): ?>
                    <div class="behavior-item">
                        <h4><?php echo ucfirst($log['type']); ?> - Score: <?php echo $log['behavior_score']; ?>/10</h4>
                        <p><strong>Teacher:</strong> <?php echo htmlspecialchars($log['teacher_name']); ?></p>
                        <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($log['created_at'])); ?></p>
                        <p><strong>Notes:</strong> <?php echo htmlspecialchars($log['notes']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: var(--text-secondary); text-align: center; padding: 2rem;">
                    No behavior logs recorded yet
                </p>
            <?php endif; ?>
        </div>

        <!-- Student Information -->
        <div class="report-section">
            <h2><i class="fas fa-user"></i> Student Information</h2>
            <table class="attendance-table">
                <tr>
                    <th>Name</th>
                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                </tr>
                <tr>
                    <th>Student ID</th>
                    <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                </tr>
                <tr>
                    <th>Class</th>
                    <td><?php echo htmlspecialchars($student['class_name'] ?? 'Not assigned'); ?></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                </tr>
            </table>
        </div>

        <div class="action-buttons">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button class="btn btn-secondary" onclick="exportToPDF()">
                <i class="fas fa-file-pdf"></i> Export to PDF
            </button>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
            </div>
        </main>
    </div>

    <?php include '../includes/sams-bot.php'; ?>

    <script>
        function exportToPDF() {
            alert('PDF export functionality coming soon! For now, please use the Print button and save as PDF.');
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
