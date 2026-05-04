<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_parent();

$parent_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$tenantId = current_tenant_id();

// Get selected child
$selected_student = isset($_GET['student']) ? intval($_GET['student']) : null;
$start_date = isset($_GET['start']) ? sanitize($_GET['start']) : date('Y-m-01');
$end_date = isset($_GET['end']) ? sanitize($_GET['end']) : date('Y-m-d');

// Get children
$children = get_parent_linked_children((int)$parent_id, (int)$tenantId);
$allowedChildIds = array_map('intval', array_column($children, 'user_id'));
if ($selected_student && !in_array($selected_student, $allowedChildIds, true)) {
    $selected_student = null;
}
$selected_child = null;
foreach ($children as $child) {
    if ((int)($child['user_id'] ?? 0) === (int)$selected_student) {
        $selected_child = $child;
        break;
    }
}

// Get attendance records
$attendance = [];
$stats = ['total' => 0, 'present' => 0, 'late' => 0, 'absent' => 0];

if ($selected_child && table_exists('attendance_records')) {
    $studentIdentifiers = array_values(array_unique(array_filter([
        (int)($selected_child['user_id'] ?? 0),
        (int)($selected_child['student_profile_id'] ?? 0),
    ])));
    $dateField = table_has_column('attendance_records', 'check_in_time')
        ? 'check_in_time'
        : (table_has_column('attendance_records', 'attendance_date') ? 'attendance_date' : null);

    if (!empty($studentIdentifiers) && $dateField !== null) {
        $placeholders = implode(',', array_fill(0, count($studentIdentifiers), '?'));
        $classJoin = (table_exists('classes') && table_has_column('attendance_records', 'class_id'))
            ? ' LEFT JOIN classes c ON ar.class_id = c.id'
            : '';
        $classSelect = ($classJoin !== '' && table_has_column('classes', 'class_name'))
            ? ', c.class_name'
            : ", '' AS class_name";

        $attendance = db()->fetchAll("
            SELECT ar.*{$classSelect}
            FROM attendance_records ar{$classJoin}
            WHERE ar.student_id IN ({$placeholders}) AND DATE(ar.{$dateField}) BETWEEN ? AND ?
            ORDER BY ar.{$dateField} DESC
        ", array_merge($studentIdentifiers, [$start_date, $end_date])) ?: [];
    }

    $stats['total'] = count($attendance);
    $stats['present'] = count(array_filter($attendance, fn($r) => $r['status'] === 'present'));
    $stats['late'] = count(array_filter($attendance, fn($r) => $r['status'] === 'late'));
    $stats['absent'] = count(array_filter($attendance, fn($r) => $r['status'] === 'absent'));
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
    <title>Attendance - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>

    <link href="../assets/css/sams-core.css" rel="stylesheet">

</head>

<body>
    <div class="starfield"></div>

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
                    <a href="../communication/conversations.php" class="cyber-btn btn-icon">
                        <i class="fas fa-envelope"></i>
                        <?php if ($unread_count > 0): ?><span class="badge"><?php echo $unread_count; ?></span><?php endif; ?>
                    </a>
                </div>
            </header>
            <div style="display:grid; gap:24px;">
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
                                        <tr>
                                            <th>Date</th>
                                            <th>Class</th>
                                            <th>Time</th>
                                            <th>Status</th>
                                            <th>Remarks</th>
                                        </tr>
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
