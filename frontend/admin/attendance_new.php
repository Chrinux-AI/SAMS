<?php

/**
 * Attendance Marking Page
 */

session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

// Require admin access
require_admin('../login.php');

$message = '';
$message_type = '';

// Handle attendance marking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $class_id = (int)$_POST['class_id'];
    $date = $_POST['attendance_date'];
    $attendance_data = $_POST['attendance'] ?? [];

    foreach ($attendance_data as $student_id => $status) {
        // Check if attendance already exists
        $existing = db()->fetch("
            SELECT id FROM attendance_records
            WHERE student_id = ? AND class_id = ? AND DATE(check_in_time) = ?
        ", [$student_id, $class_id, $date]);

        if (!$existing) {
            // Insert new attendance record
            db()->insert('attendance_records', [
                'student_id' => $student_id,
                'class_id' => $class_id,
                'check_in_time' => $date . ' ' . date('H:i:s'),
                'status' => $status,
                'marked_by' => $_SESSION['user_id']
            ]);
        } else {
            // Update existing record
            db()->update('attendance_records', [
                'status' => $status,
                'marked_by' => $_SESSION['user_id']
            ], 'id = :id', ['id' => $existing['id']]);
        }
    }

    log_activity($_SESSION['user_id'], 'mark_attendance', 'attendance_records', $class_id);
    $message = 'Attendance marked successfully!';
    $message_type = 'success';
}

// Get all classes for dropdown
$classes = db()->fetchAll("
    SELECT c.*, COUNT(ce.student_id) as student_count
    FROM classes c
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id
    GROUP BY c.id
    ORDER BY c.class_name
");

// Get students for selected class
$selected_class_id = $_GET['class_id'] ?? ($_POST['class_id'] ?? null);
$selected_date = $_GET['date'] ?? ($_POST['attendance_date'] ?? date('Y-m-d'));
$students = [];
$attendance_records = [];

if ($selected_class_id) {
    $students = db()->fetchAll("
        SELECT s.*, u.first_name, u.last_name, u.email, ce.enrollment_date
        FROM students s
        LEFT JOIN users u ON s.user_id = u.id
        JOIN class_enrollments ce ON s.id = ce.student_id
        WHERE ce.class_id = ?
        ORDER BY u.last_name, u.first_name
    ", [$selected_class_id]);

    // Get existing attendance for the date
    foreach ($students as &$student) {
        $record = db()->fetch("
            SELECT * FROM attendance_records
            WHERE student_id = ? AND class_id = ? AND DATE(check_in_time) = ?
        ", [$student['id'], $selected_class_id, $selected_date]);

        $student['attendance_status'] = $record ? $record['status'] : 'present';
        $student['attendance_id'] = $record ? $record['id'] : null;
    }
}

// Page metadata
$page_title = 'Attendance Control';
$page_icon = 'clipboard-check';
$full_name = $_SESSION['full_name'];
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
    <?php include '../includes/sams-head-bootstrap.php'; ?>


</head>

<body>



    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>
        <main class="main-content">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb"><i class="fas fa-<?php echo $page_icon; ?>"></i></div>
                    <h1 class="page-title"><?php echo $page_title; ?></h1>
                </div>
                <div class="header-actions">
                    <div class="biometric-orb" title="Quick Scan"><i class="fas fa-fingerprint"></i></div>
                    <div class="user-card" style="padding: 8px 15px; margin: 0;">
                        <div class="user-avatar" style="width: 35px; height: 35px; font-size: 0.9rem;">
                            <?php echo strtoupper(substr($full_name, 0, 2)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name" style="font-size: 0.85rem;"><?php echo htmlspecialchars($full_name); ?></div>
                            <div class="user-role">Administrator</div>
                        </div>
                    </div>
                </div>
            </header>
            <div class="cyber-content slide-in">
                <?php if ($message): ?>
                    <div class="cyber-alert <?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-filter"></i> <span>Select Class & Date</span></div>
                    </div>
                    <div class="card-body">
                        <form method="GET" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                            <div>
                                <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;"><i class="fas fa-book"></i> SELECT CLASS</label>
                                <select name="class_id" required onchange="this.form.submit()" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                                    <option value="">Choose a class...</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>" <?php echo $selected_class_id == $class['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['name'] . ' (' . $class['class_code'] . ') - ' . $class['student_count'] . ' students'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="color:var(--cyber-cyan);font-size:0.85rem;font-weight:600;margin-bottom:8px;display:block;"><i class="fas fa-calendar"></i> ATTENDANCE DATE</label>
                                <input type="date" name="date" value="<?php echo $selected_date; ?>" required onchange="this.form.submit()" style="width:100%;padding:12px;background:rgba(0,191,255,0.05);border:1px solid var(--cyber-cyan);border-radius:8px;color:var(--cyber-cyan);font-family:Roboto;">
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($selected_class_id && !empty($students)): ?>
                    <div class="holo-card">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-check-double"></i> <span>Mark Attendance - <?php echo date('F d, Y', strtotime($selected_date)); ?></span></div>
                            <div class="card-badge cyan"><?php echo count($students); ?> Students</div>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                                <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">
                                <div style="display:grid;gap:15px;">
                                    <?php foreach ($students as $student): ?>
                                        <div style="background:linear-gradient(135deg,rgba(0,191,255,0.03),rgba(138,43,226,0.03));border:1px solid rgba(0,191,255,0.2);border-radius:12px;padding:20px;display:grid;grid-template-columns:2fr 3fr;gap:25px;align-items:center;">
                                            <div style="display:flex;align-items:center;gap:15px;">
                                                <div style="width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,var(--cyber-cyan),var(--hologram-purple));display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem;color:white;border:2px solid var(--cyber-cyan);box-shadow:0 0 15px rgba(0,191,255,0.5);">
                                                    <?php echo strtoupper(substr($student['first_name'] ?? '', 0, 1) . substr($student['last_name'] ?? '', 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <h4 style="color:var(--cyber-cyan);font-size:1.1rem;margin-bottom:5px;"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></h4>
                                                    <div style="color:rgba(0,191,255,0.6);font-size:0.85rem;">ID: <?php echo htmlspecialchars($student['admission_number'] ?? $student['id']); ?></div>
                                                </div>
                                            </div>
                                            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;">
                                                <div>
                                                    <input type="radio" id="present_<?php echo $student['id']; ?>" name="attendance[<?php echo $student['id']; ?>]" value="present" <?php echo $student['attendance_status'] === 'present' ? 'checked' : ''; ?> style="display:none;">
                                                    <label for="present_<?php echo $student['id']; ?>" style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:12px;background:rgba(10,10,10,0.6);border:1px solid rgba(100,100,100,0.3);border-radius:8px;cursor:pointer;color:var(--neon-green);"><i class="fas fa-check" style="font-size:1.3rem;"></i> <span style="font-size:0.8rem;font-weight:600;">PRESENT</span></label>
                                                </div>
                                                <div>
                                                    <input type="radio" id="absent_<?php echo $student['id']; ?>" name="attendance[<?php echo $student['id']; ?>]" value="absent" <?php echo $student['attendance_status'] === 'absent' ? 'checked' : ''; ?> style="display:none;">
                                                    <label for="absent_<?php echo $student['id']; ?>" style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:12px;background:rgba(10,10,10,0.6);border:1px solid rgba(100,100,100,0.3);border-radius:8px;cursor:pointer;color:var(--cyber-red);"><i class="fas fa-times" style="font-size:1.3rem;"></i> <span style="font-size:0.8rem;font-weight:600;">ABSENT</span></label>
                                                </div>
                                                <div>
                                                    <input type="radio" id="late_<?php echo $student['id']; ?>" name="attendance[<?php echo $student['id']; ?>]" value="late" <?php echo $student['attendance_status'] === 'late' ? 'checked' : ''; ?> style="display:none;">
                                                    <label for="late_<?php echo $student['id']; ?>" style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:12px;background:rgba(10,10,10,0.6);border:1px solid rgba(100,100,100,0.3);border-radius:8px;cursor:pointer;color:var(--warning-yellow);"><i class="fas fa-clock" style="font-size:1.3rem;"></i> <span style="font-size:0.8rem;font-weight:600;">LATE</span></label>
                                                </div>
                                                <div>
                                                    <input type="radio" id="excused_<?php echo $student['id']; ?>" name="attendance[<?php echo $student['id']; ?>]" value="excused" <?php echo $student['attendance_status'] === 'excused' ? 'checked' : ''; ?> style="display:none;">
                                                    <label for="excused_<?php echo $student['id']; ?>" style="display:flex;flex-direction:column;align-items:center;gap:8px;padding:12px;background:rgba(10,10,10,0.6);border:1px solid rgba(100,100,100,0.3);border-radius:8px;cursor:pointer;color:var(--cyber-cyan);"><i class="fas fa-check-circle" style="font-size:1.3rem;"></i> <span style="font-size:0.8rem;font-weight:600;">EXCUSED</span></label>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div style="margin-top:30px;display:flex;gap:15px;justify-content:flex-end;">
                                    <button type="button" onclick="markAll('present')" class="cyber-btn success"><i class="fas fa-check-double"></i> Mark All Present</button>
                                    <button type="submit" name="mark_attendance" class="cyber-btn primary"><i class="fas fa-save"></i> Save Attendance</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php elseif ($selected_class_id && empty($students)): ?>
                    <div class="cyber-alert warning"><i class="fas fa-exclamation-triangle"></i> <span>No students enrolled in this class.</span></div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
        function markAll(status) {
            const radios = document.querySelectorAll(`input[type="radio"][value="${status}"]`);
            radios.forEach(radio => radio.checked = true);
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
