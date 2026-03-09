<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_student();

$student_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get date range from GET parameters or default to current month
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Get attendance records for the period
$attendance_records = db()->fetchAll(
    "SELECT a.*, c.name as class_name, c.start_time, c.end_time
    FROM attendance_records a
    LEFT JOIN classes c ON a.class_id = c.id
    WHERE a.student_id = ?
    AND a.attendance_date BETWEEN ? AND ?
    ORDER BY a.attendance_date DESC, c.start_time ASC",
    [$student_id, $start_date, $end_date]
);

// Get summary statistics
$total_records = count($attendance_records);
$present_count = count(array_filter($attendance_records, fn($r) => $r['status'] === 'present'));
$absent_count = count(array_filter($attendance_records, fn($r) => $r['status'] === 'absent'));
$late_count = count(array_filter($attendance_records, fn($r) => $r['status'] === 'late'));
$attendance_percentage = $total_records > 0 ? round((($present_count + $late_count) / $total_records) * 100, 1) : 0;

$page_title = 'My Attendance Records';
$page_icon = 'clipboard-check';
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

</head>

<body>
    <div class="starfield"></div>
    <div class="app-layout"></div>

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <div class="stat-badge" style="background:rgba(0,255,127,0.1);border:1px solid var(--neon-green);padding:8px 15px;border-radius:8px;">
                        <i class="fas fa-percentage"></i> <?php echo $attendance_percentage; ?>% Attendance
                    </div>
                </div>
            </header>
            <div class="app-layout">

                <!-- Statistics Orbs -->
                <section class="orb-grid" style="margin-bottom:30px;">
                    <div class="stat-orb">
                        <div class="stat-icon cyan">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $total_records; ?></div>
                            <div class="stat-label">Total Days</div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $present_count; ?></div>
                            <div class="stat-label">Days Present</div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon red">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $absent_count; ?></div>
                            <div class="stat-label">Days Absent</div>
                        </div>
                    </div>
                    <div class="stat-orb">
                        <div class="stat-icon purple">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?php echo $late_count; ?></div>
                            <div class="stat-label">Times Late</div>
                        </div>
                    </div>
                </section>

                <!-- Filter -->
                <div class="holo-card" style="margin-bottom:30px;">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-filter"></i> <span>Filter Records</span></div>
                    </div>
                    <div class="card-body">
                        <form method="GET" style="display:flex;gap:15px;flex-wrap:wrap;align-items:end;">
                            <div class="form-group" style="margin:0;">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" id="start_date" name="start_date" class="cyber-input"
                                    value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" id="end_date" name="end_date" class="cyber-input"
                                    value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            <button type="submit" class="cyber-btn">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Attendance Records Table -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-list"></i> <span>Attendance History</span></div>
                        <span class="cyber-badge"><?php echo count($attendance_records); ?> Records</span>
                    </div>
                    <div class="card-body">
                        <?php if (count($attendance_records) > 0): ?>
                            <div style="overflow-x:auto;">
                                <table class="holo-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Class</th>
                                            <th>Time</th>
                                            <th>Status</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance_records as $record): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y', strtotime($record['attendance_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($record['class_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($record['start_time'] ?? '') . ' - ' . htmlspecialchars($record['end_time'] ?? ''); ?></td>
                                                <td>
                                                    <span class="status-badge <?php
                                                                                echo $record['status'] === 'present' ? 'active' : ($record['status'] === 'late' ? 'warning' : 'inactive');
                                                                                ?>">
                                                        <?php echo ucfirst($record['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($record['remarks'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
                                <i class="fas fa-calendar-times" style="font-size:5rem;opacity:0.3;margin-bottom:20px;"></i>
                                <h3 style="margin-bottom:15px;">No Records Found</h3>
                                <p>No attendance records for the selected date range.</p>
                            </div>
                        <?php endif; ?>
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
