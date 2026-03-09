<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_parent();

$parent_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get selected child
$selected_student = isset($_GET['student']) ? intval($_GET['student']) : null;
$start_date = isset($_GET['start']) ? sanitize($_GET['start']) : date('Y-m-01');
$end_date = isset($_GET['end']) ? sanitize($_GET['end']) : date('Y-m-d');

// Get children
$children = db()->fetchAll("
    SELECT u.id, u.first_name, u.last_name, s.admission_number AS student_id
    FROM users u
    JOIN students s ON u.id = s.user_id
    JOIN parent_student_links psl ON s.user_id = psl.student_id
    WHERE psl.parent_id = ? AND u.status = 'active'
", [$parent_id]);

// Get attendance records
$attendance = [];
$stats = ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0];

if ($selected_student) {
    $attendance = db()->fetchAll("
        SELECT ar.*, c.class_name
        FROM attendance_records ar
        JOIN classes c ON ar.class_id = c.id
        WHERE ar.student_id = ? AND DATE(ar.check_in_time) BETWEEN ? AND ?
        ORDER BY ar.check_in_time DESC
    ", [$selected_student, $start_date, $end_date]);

    $stats['total'] = count($attendance);
    $stats['present'] = count(array_filter($attendance, fn($r) => $r['status'] === 'present'));
    $stats['late'] = count(array_filter($attendance, fn($r) => $r['status'] === 'late'));
    $stats['absent'] = count(array_filter($attendance, fn($r) => $r['status'] === 'absent'));
}

$unread_count = db()->fetchOne("SELECT COUNT(*) as count FROM message_recipients WHERE recipient_id = ? AND is_read = 0 AND deleted_at IS NULL", [$parent_id])['count'] ?? 0;
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
    <title>Attendance - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
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
                    <div class="page-icon-orb"><i class="fas fa-clipboard-list"></i></div>
                    <div>
                        <h1 class="page-title">Attendance Records</h1>
                        <p class="page-subtitle">View your children's attendance</p>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="../messages.php" class="cyber-btn btn-icon">
                        <i class="fas fa-envelope"></i>
                        <?php if ($unread_count > 0): ?><span class="badge"><?php echo $unread_count; ?></span><?php endif; ?>
                    </a>
                </div>
            </header>
            <div class="app-layout">
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-filter"></i><span>Filter</span></div>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="grid-3">
                            <div class="form-group">
                                <label class="form-label">Select Child</label>
                                <select name="student" class="cyber-input" required>
                                    <option value="">-- Choose --</option>
                                    <?php foreach ($children as $child): ?>
                                        <option value="<?php echo $child['id']; ?>" <?php echo $selected_student == $child['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
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
                                    <i class="fas fa-search"></i> View Records
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($selected_student): ?>
                    <div class="stats-grid">
                        <div class="stat-orb">
                            <div class="app-layout"><i class="fas fa-clipboard-list"></i></div>
                            <div class="stat-label">Total</div>
                            <div class="stat-value"><?php echo $stats['total']; ?></div>
                        </div>
                        <div class="stat-orb">
                            <div class="stat-icon green"><i class="fas fa-check"></i></div>
                            <div class="stat-label">Present</div>
                            <div class="stat-value"><?php echo $stats['present']; ?></div>
                        </div>
                        <div class="stat-orb">
                            <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                            <div class="stat-label">Late</div>
                            <div class="stat-value"><?php echo $stats['late']; ?></div>
                        </div>
                        <div class="stat-orb">
                            <div class="stat-icon red"><i class="fas fa-times"></i></div>
                            <div class="stat-label">Absent</div>
                            <div class="stat-value"><?php echo $stats['absent']; ?></div>
                        </div>
                    </div>

                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-list"></i><span>Attendance History</span></div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($attendance)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-clipboard"></i>
                                    <p>No attendance records found for the selected period</p>
                                </div>
                            <?php else: ?>
                                <table class="holo-table">
                                    <thead>
                                        <tr><th>Date</th><th>Class</th><th>Time</th><th>Status</th><th>Remarks</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance as $record): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($record['check_in_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($record['class_name']); ?></td>
                                                <td><?php echo date('h:i A', strtotime($record['check_in_time'])); ?></td>
                                                <td><span class="status-badge <?php echo $record['status']; ?>"><?php echo ucfirst($record['status']); ?></span></td>
                                                <td><?php echo htmlspecialchars($record['remarks'] ?? '-'); ?></td>
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

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>
</html>
