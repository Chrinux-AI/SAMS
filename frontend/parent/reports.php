<?php

/**
 * Parent Reports & Analytics - Comprehensive Reporting System
 * Generate, download, and share customized reports for children
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_parent();

$parent_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$tenantId = current_tenant_id();

// Get all linked children
$children = get_parent_linked_children((int)$parent_id, (int)$tenantId);

// Handle report generation
$generated_report = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $report_type = sanitize($_POST['report_type']);
    $child_id = (int)$_POST['child_id'];
    $start_date = sanitize($_POST['start_date']);
    $end_date = sanitize($_POST['end_date']);
    $format = sanitize($_POST['format']);

    $child = null;
    foreach ($children as $candidate) {
        if ((int)($candidate['user_id'] ?? 0) === $child_id || (int)($candidate['student_profile_id'] ?? 0) === $child_id) {
            $child = $candidate;
            break;
        }
    }

    if ($child) {
        $studentIdentifiers = array_values(array_unique(array_filter([
            (int)($child['user_id'] ?? 0),
            (int)($child['student_profile_id'] ?? 0),
        ])));
        $generated_report = [
            'type' => $report_type,
            'child' => [
                'id' => (int)($child['user_id'] ?? 0),
                'child_name' => $child['child_name'] ?? trim(($child['first_name'] ?? '') . ' ' . ($child['last_name'] ?? '')),
                'student_id' => $child['student_id'] ?? '',
                'grade_level' => $child['grade_level'] ?? null,
            ],
            'start_date' => $start_date,
            'end_date' => $end_date,
            'generated_at' => date('Y-m-d H:i:s')
        ];

        // Generate report data based on type
        switch ($report_type) {
            case 'attendance':
                $generated_report['data'] = [];
                if (!empty($studentIdentifiers) && table_exists('attendance_records')) {
                    $attendanceDateField = table_has_column('attendance_records', 'check_in_time')
                        ? 'check_in_time'
                        : (table_has_column('attendance_records', 'attendance_date') ? 'attendance_date' : null);
                    if ($attendanceDateField !== null) {
                        $placeholders = implode(',', array_fill(0, count($studentIdentifiers), '?'));
                        $classJoin = (table_exists('classes') && table_has_column('attendance_records', 'class_id'))
                            ? ' LEFT JOIN classes c ON ar.class_id = c.id'
                            : '';
                        $classSelect = ($classJoin !== '' && table_has_column('classes', 'class_name'))
                            ? ', c.class_name'
                            : ", '' AS class_name";
                        $generated_report['data'] = db()->fetchAll("
                            SELECT ar.*{$classSelect},
                                   DATE(ar.{$attendanceDateField}) as date,
                                   TIME(ar.{$attendanceDateField}) as time
                            FROM attendance_records ar{$classJoin}
                            WHERE ar.student_id IN ({$placeholders})
                              AND DATE(ar.{$attendanceDateField}) BETWEEN ? AND ?
                            ORDER BY ar.{$attendanceDateField} DESC
                        ", array_merge($studentIdentifiers, [$start_date, $end_date])) ?: [];
                    }
                }

                // Calculate statistics
                $total = count($generated_report['data']);
                $present = count(array_filter($generated_report['data'], fn($r) => $r['status'] === 'present'));
                $late = count(array_filter($generated_report['data'], fn($r) => $r['status'] === 'late'));
                $absent = count(array_filter($generated_report['data'], fn($r) => $r['status'] === 'absent'));
                $generated_report['stats'] = [
                    'total' => $total,
                    'present' => $present,
                    'late' => $late,
                    'absent' => $absent,
                    'rate' => $total > 0 ? round(($present / $total) * 100, 1) : 0
                ];
                break;

            case 'grades':
                $generated_report['data'] = [];
                if (!empty($studentIdentifiers) && table_exists('grades')) {
                    $gradeDateField = table_has_column('grades', 'grade_date')
                        ? 'grade_date'
                        : (table_has_column('grades', 'created_at') ? 'created_at' : null);
                    if ($gradeDateField !== null) {
                        $placeholders = implode(',', array_fill(0, count($studentIdentifiers), '?'));
                        $assignmentJoin = (table_exists('assignments') && table_has_column('grades', 'assignment_id'))
                            ? ' LEFT JOIN assignments a ON g.assignment_id = a.id'
                            : '';
                        $classJoin = '';
                        if (table_exists('classes')) {
                            if (table_has_column('grades', 'class_id')) {
                                $classJoin = ' LEFT JOIN classes c ON g.class_id = c.id';
                            } elseif ($assignmentJoin !== '' && table_has_column('assignments', 'class_id')) {
                                $classJoin = ' LEFT JOIN classes c ON a.class_id = c.id';
                            }
                        }
                        $assignmentTitleSelect = $assignmentJoin !== '' ? 'a.title as assignment_title' : "'' as assignment_title";
                        $classNameSelect = ($classJoin !== '' && table_has_column('classes', 'class_name')) ? 'c.class_name' : "'' as class_name";
                        $percentageSelect = (table_has_column('grades', 'points_earned') && table_has_column('grades', 'max_points'))
                            ? '(g.points_earned / NULLIF(g.max_points, 0) * 100) as percentage'
                            : 'NULL as percentage';
                        $generated_report['data'] = db()->fetchAll("
                            SELECT g.*, {$assignmentTitleSelect}, {$classNameSelect}, {$percentageSelect}
                            FROM grades g{$assignmentJoin}{$classJoin}
                            WHERE g.student_id IN ({$placeholders}) AND DATE(g.{$gradeDateField}) BETWEEN ? AND ?
                            ORDER BY g.{$gradeDateField} DESC
                        ", array_merge($studentIdentifiers, [$start_date, $end_date])) ?: [];
                    }
                }

                $total_points = array_sum(array_column($generated_report['data'], 'max_points'));
                $earned_points = array_sum(array_column($generated_report['data'], 'points_earned'));
                $generated_report['stats'] = [
                    'total_assignments' => count($generated_report['data']),
                    'average' => $total_points > 0 ? round(($earned_points / $total_points) * 100, 1) : 0
                ];
                break;

            case 'progress':
                // Combined attendance and grades
                $attendance_data = [];
                if (!empty($studentIdentifiers) && table_exists('attendance_records')) {
                    $attendanceDateField = table_has_column('attendance_records', 'check_in_time')
                        ? 'check_in_time'
                        : (table_has_column('attendance_records', 'attendance_date') ? 'attendance_date' : null);
                    if ($attendanceDateField !== null) {
                        $placeholders = implode(',', array_fill(0, count($studentIdentifiers), '?'));
                        $attendance_data = db()->fetchAll("
                            SELECT DATE(ar.{$attendanceDateField}) as date, ar.status
                            FROM attendance_records ar
                            WHERE ar.student_id IN ({$placeholders}) AND DATE(ar.{$attendanceDateField}) BETWEEN ? AND ?
                            ORDER BY ar.{$attendanceDateField}
                        ", array_merge($studentIdentifiers, [$start_date, $end_date])) ?: [];
                    }
                }

                $grade_data = [];
                if (!empty($studentIdentifiers) && table_exists('grades')) {
                    $gradeDateField = table_has_column('grades', 'grade_date')
                        ? 'grade_date'
                        : (table_has_column('grades', 'created_at') ? 'created_at' : null);
                    if ($gradeDateField !== null && table_has_column('grades', 'points_earned') && table_has_column('grades', 'max_points')) {
                        $placeholders = implode(',', array_fill(0, count($studentIdentifiers), '?'));
                        $grade_data = db()->fetchAll("
                            SELECT DATE(g.{$gradeDateField}) as grade_date, (g.points_earned / NULLIF(g.max_points, 0) * 100) as percentage
                            FROM grades g
                            WHERE g.student_id IN ({$placeholders}) AND DATE(g.{$gradeDateField}) BETWEEN ? AND ?
                            ORDER BY g.{$gradeDateField}
                        ", array_merge($studentIdentifiers, [$start_date, $end_date])) ?: [];
                    }
                }

                $generated_report['data'] = [
                    'attendance' => $attendance_data,
                    'grades' => $grade_data
                ];
                break;
        }
    }
}

$unread_count = get_unread_message_count((int)$parent_id, (int)$tenantId);
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
    <title>Reports - <?php echo APP_NAME; ?></title>
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
                    <div class="page-icon-orb"><i class="fas fa-file-alt"></i></div>
                    <h1 class="page-title">Reports & Analytics</h1>
                </div>
                <div class="header-actions">
                    <a href="../communication/conversations.php" class="cyber-btn btn-icon">
                        <i class="fas fa-envelope"></i>
                        <?php if ($unread_count > 0): ?><span class="badge"><?php echo $unread_count; ?></span><?php endif; ?>
                    </a>
                </div>
            </header>
            <div style="display:grid; gap:24px;">
                <?php if (empty($children)): ?>
                    <div class="holo-card">
                        <div style="text-align:center;padding:60px;">
                            <i class="fas fa-users" style="font-size:4rem;color:#cbd5e1;margin-bottom:20px;"></i>
                            <h3 style="color:#0f172a;">No Children Linked</h3>
                            <p style="color:#64748b;">Contact the administrator to link your children</p>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Report Generator -->
                    <div class="holo-card" style="margin-bottom:30px;">
                        <h3 style="margin-bottom:20px;"><i class="fas fa-magic"></i> Generate Custom Report</h3>
                        <form method="POST">
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:20px;">
                                <div class="form-group">
                                    <label class="cyber-label">Select Child</label>
                                    <select name="child_id" class="cyber-input" required>
                                        <option value="">-- Choose Child --</option>
                                        <?php foreach ($children as $child): ?>
                                            <option value="<?php echo $child['id']; ?>"><?php echo htmlspecialchars($child['child_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="cyber-label">Report Type</label>
                                    <select name="report_type" class="cyber-input" required>
                                        <option value="attendance">Attendance Report</option>
                                        <option value="grades">Grades Report</option>
                                        <option value="progress">Progress Summary</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="cyber-label">Start Date</label>
                                    <input type="date" name="start_date" class="cyber-input" value="<?php echo date('Y-m-01'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="cyber-label">End Date</label>
                                    <input type="date" name="end_date" class="cyber-input" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div style="display:flex;gap:10px;">
                                <input type="hidden" name="format" value="html">
                                <button type="submit" name="generate_report" class="cyber-btn primary">
                                    <i class="fas fa-play"></i> Generate Report
                                </button>
                                <button type="button" onclick="downloadPDF()" class="cyber-btn" <?php echo !$generated_report ? 'disabled' : ''; ?>>
                                    <i class="fas fa-file-pdf"></i> Export PDF
                                </button>
                                <button type="button" onclick="downloadCSV()" class="cyber-btn" <?php echo !$generated_report ? 'disabled' : ''; ?>>
                                    <i class="fas fa-file-csv"></i> Export CSV
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if ($generated_report): ?>
                        <!-- Generated Report -->
                        <div class="holo-card" id="reportContent">
                            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:25px;padding-bottom:20px;border-bottom:1px solid var(--glass-border);">
                                <div>
                                    <h2 style="color:var(--cyber-cyan);margin-bottom:10px;">
                                        <?php echo ucfirst($generated_report['type']); ?> Report
                                    </h2>
                                    <div style="color:rgba(255,255,255,0.7);">
                                        <strong><?php echo htmlspecialchars($generated_report['child']['child_name']); ?></strong>
                                        (<?php echo $generated_report['child']['student_id']; ?>)
                                    </div>
                                    <div style="color:rgba(255,255,255,0.5);font-size:0.9rem;margin-top:5px;">
                                        Period: <?php echo date('M d, Y', strtotime($generated_report['start_date'])); ?> -
                                        <?php echo date('M d, Y', strtotime($generated_report['end_date'])); ?>
                                    </div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="color:rgba(255,255,255,0.5);font-size:0.85rem;">Generated:</div>
                                    <div style="color:var(--cyber-cyan);"><?php echo date('M d, Y g:i A', strtotime($generated_report['generated_at'])); ?></div>
                                </div>
                            </div>

                            <?php if ($generated_report['type'] === 'attendance'): ?>
                                <!-- Attendance Report -->
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:30px;">
                                    <div class="stat-orb">
                                        <div class="stat-label">Total Days</div>
                                        <div class="stat-value"><?php echo $generated_report['stats']['total']; ?></div>
                                    </div>
                                    <div class="stat-orb success">
                                        <div class="stat-label">Present</div>
                                        <div class="stat-value"><?php echo $generated_report['stats']['present']; ?></div>
                                    </div>
                                    <div class="stat-orb warning">
                                        <div class="stat-label">Late</div>
                                        <div class="stat-value"><?php echo $generated_report['stats']['late']; ?></div>
                                    </div>
                                    <div class="stat-orb danger">
                                        <div class="stat-label">Absent</div>
                                        <div class="stat-value"><?php echo $generated_report['stats']['absent']; ?></div>
                                    </div>
                                    <div class="stat-orb primary">
                                        <div class="stat-label">Attendance Rate</div>
                                        <div class="stat-value"><?php echo $generated_report['stats']['rate']; ?>%</div>
                                    </div>
                                </div>
                                <table class="cyber-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Class</th>
                                            <th>Time</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($generated_report['data'] as $record): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                                <td><?php echo htmlspecialchars($record['class_name']); ?></td>
                                                <td><?php echo date('g:i A', strtotime($record['time'])); ?></td>
                                                <td>
                                                    <span class="cyber-badge <?php echo $record['status'] === 'present' ? 'success' : ($record['status'] === 'late' ? 'warning' : 'danger'); ?>">
                                                        <?php echo ucfirst($record['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php elseif ($generated_report['type'] === 'grades'): ?>
                                <!-- Grades Report -->
                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:15px;margin-bottom:30px;">
                                    <div class="stat-orb primary">
                                        <div class="stat-label">Total Assignments</div>
                                        <div class="stat-value"><?php echo $generated_report['stats']['total_assignments']; ?></div>
                                    </div>
                                    <div class="stat-orb <?php echo $generated_report['stats']['average'] >= 90 ? 'success' : ($generated_report['stats']['average'] >= 70 ? 'warning' : 'danger'); ?>">
                                        <div class="stat-label">Average Grade</div>
                                        <div class="stat-value"><?php echo $generated_report['stats']['average']; ?>%</div>
                                    </div>
                                </div>
                                <table class="cyber-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Class</th>
                                            <th>Assignment</th>
                                            <th>Score</th>
                                            <th>Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($generated_report['data'] as $grade): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($grade['grade_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($grade['class_name']); ?></td>
                                                <td><?php echo htmlspecialchars($grade['assignment_title'] ?? 'Manual Entry'); ?></td>
                                                <td><?php echo $grade['points_earned']; ?> / <?php echo $grade['max_points']; ?></td>
                                                <td>
                                                    <strong style="color:<?php echo $grade['percentage'] >= 90 ? '#00ff7f' : ($grade['percentage'] >= 70 ? '#ffff00' : '#ff4500'); ?>;">
                                                        <?php echo number_format($grade['percentage'], 1); ?>%
                                                    </strong>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Quick Access Reports -->
                    <div class="holo-card">
                        <h3 style="margin-bottom:20px;"><i class="fas fa-bolt"></i> Quick Reports</h3>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:15px;">
                            <a href="analytics.php" class="cyber-btn" style="text-decoration:none;padding:20px;">
                                <i class="fas fa-chart-line"></i> View Family Analytics
                            </a>
                            <a href="attendance.php" class="cyber-btn" style="text-decoration:none;padding:20px;">
                                <i class="fas fa-calendar-check"></i> Attendance History
                            </a>
                            <a href="grades.php" class="cyber-btn" style="text-decoration:none;padding:20px;">
                                <i class="fas fa-graduation-cap"></i> Grade Reports
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function downloadPDF() {
            alert('PDF export feature - integrate with jsPDF or server-side PDF generation library');
            // Implementation: Use jsPDF or TCPDF to generate PDF from report content
        }

        function downloadCSV() {
            alert('CSV export feature - generating downloadable CSV file');
            // Implementation: Convert table data to CSV format and trigger download
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
