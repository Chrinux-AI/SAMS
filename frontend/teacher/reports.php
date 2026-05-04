<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_teacher();

$teacher_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get filter parameters
$class_filter = isset($_GET['class']) ? intval($_GET['class']) : null;
$start_date = isset($_GET['start']) ? sanitize($_GET['start']) : date('Y-m-01');
$end_date = isset($_GET['end']) ? sanitize($_GET['end']) : date('Y-m-d');

// Get teacher's classes
$my_classes = db()->fetchAll("SELECT * FROM classes WHERE class_teacher_id = ?", [$teacher_id]);

// Build query based on filters
$where_conditions = ["c.class_teacher_id = ?"];
$params = [$teacher_id];

if ($class_filter) {
    $where_conditions[] = "ar.class_id = ?";
    $params[] = $class_filter;
}

$where_conditions[] = "DATE(ar.check_in_time) BETWEEN ? AND ?";
$params[] = $start_date;
$params[] = $end_date;

$where_clause = implode(' AND ', $where_conditions);

// Get attendance statistics
$attendance_stats = db()->fetchAll("
    SELECT ar.status, COUNT(*) as count
    FROM attendance_records ar
    JOIN classes c ON ar.class_id = c.id
    WHERE $where_clause
    GROUP BY ar.status
", $params);

$total_records = array_sum(array_column($attendance_stats, 'count'));
$present_count = 0;
$late_count = 0;
$absent_count = 0;

foreach ($attendance_stats as $stat) {
    if ($stat['status'] === 'present') $present_count = $stat['count'];
    if ($stat['status'] === 'late') $late_count = $stat['count'];
    if ($stat['status'] === 'absent') $absent_count = $stat['count'];
}

$attendance_rate = $total_records > 0 ? round((($present_count + $late_count) / $total_records) * 100, 1) : 0;

// Unread messages
$unread_count = get_unread_message_count((int)$teacher_id, (int)current_tenant_id());
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/sams-core.css" rel="stylesheet">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>


</head>

<body>
    <div class="starfield"></div>

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>

        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <h1 class="page-title">Attendance Reports</h1>
                        <p class="page-subtitle">Analyze attendance trends and patterns</p>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="../communication/conversations.php" class="cyber-btn btn-icon">
                        <i class="fas fa-envelope"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </header>

            <div style="display:grid;gap:24px;">
                <!-- Filters -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-filter"></i>
                            <span>Filter Reports</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="grid-3">
                            <div class="form-group">
                                <label class="form-label">Class</label>
                                <select name="class" class="cyber-input">
                                    <option value="">All Classes</option>
                                    <?php foreach ($my_classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>"
                                            <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start" class="cyber-input" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end" class="cyber-input" value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group" style="display: flex; align-items: flex-end;">
                                <button type="submit" class="cyber-btn" style="width: 100%;">
                                    <i class="fas fa-search"></i> Generate Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-orb">
                        <div class="stat-icon cyan">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-label">Total Records</div>
                        <div class="stat-value"><?php echo $total_records; ?></div>
                    </div>

                    <div class="stat-orb">
                        <div class="stat-icon green">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="stat-label">Present</div>
                        <div class="stat-value"><?php echo $present_count; ?></div>
                    </div>

                    <div class="stat-orb">
                        <div class="stat-icon yellow">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-label">Late</div>
                        <div class="stat-value"><?php echo $late_count; ?></div>
                    </div>

                    <div class="stat-orb">
                        <div class="stat-icon red">
                            <i class="fas fa-times"></i>
                        </div>
                        <div class="stat-label">Absent</div>
                        <div class="stat-value"><?php echo $absent_count; ?></div>
                    </div>

                    <div class="stat-orb">
                        <div class="stat-icon purple">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-label">Attendance Rate</div>
                        <div class="stat-value"><?php echo $attendance_rate; ?>%</div>
                    </div>
                </div>

                <!-- Summary -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-file-alt"></i>
                            <span>Report Summary</span>
                        </div>
                        <button onclick="window.print()" class="cyber-btn btn-sm">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                    </div>
                    <div class="card-body">
                        <div style="background: rgba(0,191,255,0.05); padding: 20px; border-radius: 10px;">
                            <p><strong>Period:</strong> <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></p>
                            <p><strong>Class:</strong> <?php echo $class_filter ? htmlspecialchars(array_filter($my_classes, fn($c) => $c['id'] == $class_filter)[0]['class_name'] ?? 'All') : 'All Classes'; ?></p>
                            <p><strong>Generated:</strong> <?php echo date('F d, Y h:i A'); ?></p>
                            <hr style="border-color: rgba(0,191,255,0.2); margin: 15px 0;">
                            <p style="font-size: 16px; color: #00BFFF;">
                                <strong>Overall Attendance Rate:</strong>
                                <span style="font-size: 24px; color: <?php echo $attendance_rate >= 90 ? '#00FF7F' : ($attendance_rate >= 75 ? '#FFD700' : '#FF4444'); ?>">
                                    <?php echo $attendance_rate; ?>%
                                </span>
                            </p>
                        </div>
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
