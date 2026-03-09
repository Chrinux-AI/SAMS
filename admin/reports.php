<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

$page_title = 'Reports';
$page_icon = 'chart-line';
$full_name = $_SESSION['full_name'];

// Get filter parameters
$report_type = $_GET['type'] ?? 'attendance';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$class_id = $_GET['class_id'] ?? '';
$student_id = $_GET['student_id'] ?? '';

$total_students = db()->count('students');
$total_classes = db()->count('classes');
$total_attendance = db()->count('attendance_records');

// Get classes for dropdown
$classes = db()->fetchAll("SELECT id, class_name, class_code FROM classes ORDER BY class_name");

// Get students for dropdown
$students = db()->fetchAll("SELECT s.id, s.admission_number, u.first_name, u.last_name FROM students s LEFT JOIN users u ON s.user_id = u.id ORDER BY u.first_name, u.last_name");

// Generate report data based on type
$report_data = [];
if ($_GET['generate'] ?? false) {
    switch ($report_type) {
        case 'attendance':
            $where = "DATE(ar.attendance_date) BETWEEN ? AND ?";
            $params = [$date_from, $date_to];

            if ($class_id) {
                $where .= " AND ar.class_id = ?";
                $params[] = $class_id;
            }
            if ($student_id) {
                $where .= " AND ar.student_id = ?";
                $params[] = $student_id;
            }

            $report_data = db()->fetchAll(
                "SELECT ar.*, s.admission_number as student_code, u.first_name, u.last_name,
                        c.class_name, c.class_code
                FROM attendance_records ar
                JOIN students s ON ar.student_id = s.id
                LEFT JOIN users u ON s.user_id = u.id
                JOIN classes c ON ar.class_id = c.id
                WHERE {$where}
                ORDER BY ar.attendance_date DESC, u.last_name, u.first_name",
                $params
            );
            break;

        case 'summary':
            $where = "DATE(ar.attendance_date) BETWEEN ? AND ?";
            $params = [$date_from, $date_to];

            if ($class_id) {
                $where .= " AND ar.class_id = ?";
                $params[] = $class_id;
            }

            $report_data = db()->fetchAll(
                "SELECT s.id, s.admission_number, u.first_name, u.last_name,
                        COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
                        COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
                        COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_count,
                        COUNT(*) as total_records,
                        ROUND((COUNT(CASE WHEN ar.status = 'present' THEN 1 END) / COUNT(*)) * 100, 2) as attendance_rate
                FROM students s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN attendance_records ar ON s.id = ar.student_id AND {$where}
                GROUP BY s.id, s.admission_number, u.first_name, u.last_name
                ORDER BY u.last_name, u.first_name",
                $params
            );
            break;
    }
}
?>
<!DOCTYPE html>
<html>

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
        .report-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-group label {
            display: block;
            color: var(--cyber-cyan);
            font-size: 0.85rem;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px;
            background: rgba(0, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 0.9rem;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .report-table th {
            background: linear-gradient(135deg, rgba(0, 255, 255, 0.1), rgba(255, 0, 255, 0.1));
            padding: 12px;
            text-align: left;
            color: var(--cyber-cyan);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            border-bottom: 2px solid var(--cyber-cyan);
        }

        .report-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--glass-border);
            color: var(--text-primary);
        }

        .report-table tr:hover {
            background: rgba(0, 255, 255, 0.05);
        }

        .export-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    
    

    <div class="app-layout"><?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <div class="biometric-orb" title="Quick Scan" onclick="openQuickScan()"><i class="fas fa-fingerprint"></i></div>
                    <div class="user-card" style="padding:8px 15px;margin:0;">
                        <div class="user-avatar" style="width:35px;height:35px;font-size:0.9rem;">
                            <?php echo strtoupper(substr($full_name, 0, 2)); ?></div>
                        <div class="user-info">
                            <div class="user-name" style="font-size:0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="user-role">Administrator</div>
                        </div>
                    </div>
                </div>
            </header>
            <div class="cyber-content slide-in">
                <section class="orb-grid">
                    <div class="stat-orb">
                        <div class="stat-icon cyan"><i class="fas fa-user-graduate"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($total_students); ?></div>
                            <div class="stat-label">Total Students</div>
                            <div class="stat-trend up"><i class="fas fa-arrow-up"></i><span>Active</span></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon green"><i class="fas fa-door-open"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($total_classes); ?></div>
                            <div class="stat-label">Total Classes</div>
                            <div class="stat-trend up"><i class="fas fa-check"></i><span>All Levels</span></div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon purple"><i class="fas fa-clipboard-check"></i></div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo number_format($total_attendance); ?></div>
                            <div class="stat-label">Total Records</div>
                            <div class="stat-trend up"><i class="fas fa-database"></i><span>Recorded</span></div>
                        </div>
                    </div>
                </section>

                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-filter"></i> <span>Report Filters</span></div>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <input type="hidden" name="generate" value="1">
                            <div class="report-filters">
                                <div class="filter-group">
                                    <label>Report Type</label>
                                    <select name="type" required>
                                        <option value="attendance" <?php echo $report_type === 'attendance' ? 'selected' : ''; ?>>Detailed Attendance</option>
                                        <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>Attendance Summary</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Date From</label>
                                    <input type="date" name="date_from" value="<?php echo $date_from; ?>" required>
                                </div>
                                <div class="filter-group">
                                    <label>Date To</label>
                                    <input type="date" name="date_to" value="<?php echo $date_to; ?>" required>
                                </div>
                                <div class="filter-group">
                                    <label>Class (Optional)</label>
                                    <select name="class_id">
                                        <option value="">All Classes</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($class['class_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php if ($report_type === 'attendance'): ?>
                                    <div class="filter-group">
                                        <label>Student (Optional)</label>
                                        <select name="student_id">
                                            <option value="">All Students</option>
                                            <?php foreach ($students as $student): ?>
                                                <option value="<?php echo $student['id']; ?>" <?php echo $student_id == $student['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="submit" class="cyber-btn primary">
                                <i class="fas fa-chart-bar"></i> Generate Report
                            </button>
                        </form>
                    </div>
                </div>

                <?php if (!empty($report_data)): ?>
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-table"></i>
                                <span><?php echo ucfirst($report_type); ?> Report (<?php echo count($report_data); ?> records)</span>
                            </div>
                            <div class="export-buttons">
                                <button class="cyber-btn success" onclick="exportToCSV()">
                                    <i class="fas fa-file-csv"></i> Export CSV
                                </button>
                                <button class="cyber-btn primary" onclick="printReport()">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($report_type === 'attendance'): ?>
                                <table class="report-table" id="reportTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Student ID</th>
                                            <th>Student Name</th>
                                            <th>Class</th>
                                            <th>Status</th>
                                            <th>Check-in Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $record): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($record['student_code']); ?></td>
                                                <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($record['class_name']); ?></td>
                                                <td><span class="status-badge <?php echo $record['status']; ?>"><?php echo ucfirst($record['status']); ?></span></td>
                                                <td><?php echo $record['check_in_time'] ? date('h:i A', strtotime($record['check_in_time'])) : '-'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <table class="report-table" id="reportTable">
                                    <thead>
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Student Name</th>
                                            <th>Present</th>
                                            <th>Absent</th>
                                            <th>Late</th>
                                            <th>Total Records</th>
                                            <th>Attendance Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['admission_number']); ?></td>
                                                <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                                <td><?php echo $record['present_count']; ?></td>
                                                <td><?php echo $record['absent_count']; ?></td>
                                                <td><?php echo $record['late_count']; ?></td>
                                                <td><?php echo $record['total_records']; ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $record['attendance_rate'] >= 80 ? 'present' : ($record['attendance_rate'] >= 60 ? 'late' : 'absent'); ?>">
                                                        <?php echo $record['attendance_rate']; ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <script>
        function exportToCSV() {
            const table = document.getElementById('reportTable');
            let csv = [];

            for (let row of table.rows) {
                let cols = [];
                for (let cell of row.cells) {
                    cols.push('"' + cell.textContent.replace(/"/g, '""') + '"');
                }
                csv.push(cols.join(','));
            }

            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], {
                type: 'text/csv'
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'report_' + new Date().toISOString().slice(0, 10) + '.csv';
            a.click();
        }

        function printReport() {
            window.print();
        }

        function openQuickScan() {
            window.location.href = 'biometric-scan.php';
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>