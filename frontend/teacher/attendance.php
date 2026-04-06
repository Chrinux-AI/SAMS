<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_teacher();

$teacher_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$success_msg = '';
$error_msg = '';

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    $class_id = intval($_POST['class_id']);
    $attendance_date = sanitize($_POST['attendance_date']);
    $students = $_POST['students'] ?? [];

    try {
        foreach ($students as $student_id => $status) {
            // Check if attendance already exists
            $existing = db()->fetchOne("
                SELECT id FROM attendance_records
                WHERE student_id = ? AND class_id = ? AND DATE(check_in_time) = ?
            ", [$student_id, $class_id, $attendance_date]);

            if ($existing) {
                // Update existing record
                db()->update('attendance_records', [
                    'status' => $status,
                    'check_in_time' => $attendance_date . ' ' . date('H:i:s')
                ], 'id = ?', [$existing['id']]);
            } else {
                // Insert new record
                db()->insert('attendance_records', [
                    'student_id' => $student_id,
                    'class_id' => $class_id,
                    'check_in_time' => $attendance_date . ' ' . date('H:i:s'),
                    'status' => $status,
                    'marked_by' => $teacher_id
                ]);
            }
        }

        log_activity($teacher_id, 'mark_attendance', 'attendance_records', $class_id, "Marked attendance for class ID: $class_id on $attendance_date");
        $success_msg = "Attendance marked successfully for " . count($students) . " students!";
    } catch (Exception $e) {
        $error_msg = "Failed to mark attendance: " . $e->getMessage();
    }
}

// Get teacher's classes
$my_classes = db()->fetchAll("
    SELECT c.id, c.class_name, c.class_code, c.class_teacher_id, c.description, c.schedule, c.room_number, c.created_at,
           COUNT(DISTINCT ce.student_id) as student_count
    FROM classes c
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE c.class_teacher_id = ?
    GROUP BY c.id, c.class_name, c.class_code, c.class_teacher_id, c.description, c.schedule, c.room_number, c.created_at
", [$teacher_id]);

// Selected class for attendance
$selected_class = isset($_GET['class']) ? intval($_GET['class']) : null;
$selected_date = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');

// Get students in selected class
$students = [];
$class_info = null;
if ($selected_class) {
    $class_info = db()->fetchOne("SELECT * FROM classes WHERE id = ? AND class_teacher_id = ?", [$selected_class, $teacher_id]);

    if ($class_info) {
        $students = db()->fetchAll("
            SELECT u.id, u.first_name, u.last_name, s.admission_number AS student_id,
                   ar.status as current_status, ar.id as attendance_id
            FROM users u
            JOIN students s ON u.id = s.user_id
            JOIN class_enrollments ce ON s.user_id = ce.student_id
            LEFT JOIN attendance_records ar ON u.id = ar.student_id
                AND ar.class_id = ?
                AND DATE(ar.check_in_time) = ?
            WHERE ce.class_id = ? AND u.status = 'active'
            ORDER BY u.last_name, u.first_name
        ", [$selected_class, $selected_date, $selected_class]);
    }
}

// Unread messages
$unread_count = db()->fetchOne("
    SELECT COUNT(*) as count FROM message_recipients
    WHERE recipient_id = ? AND is_read = 0 AND deleted_at IS NULL
", [$teacher_id])['count'] ?? 0;
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
    <title>Mark Attendance - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">

    <style>
        .attendance-grid {
            display: grid;
            gap: 15px;
        }

        .student-row {
            background: rgba(0, 191, 255, 0.05);
            border: 1px solid rgba(0, 191, 255, 0.2);
            border-radius: 10px;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
        }

        .student-row:hover {
            background: rgba(0, 191, 255, 0.1);
            border-color: #00BFFF;
        }

        .student-info {
            flex: 1;
        }

        .student-name {
            font-size: 16px;
            font-weight: 600;
            color: #E0E0E0;
            margin-bottom: 5px;
        }

        .student-id {
            font-size: 13px;
            color: #00BFFF;
        }

        .status-buttons {
            display: flex;
            gap: 10px;
        }

        .status-btn {
            padding: 8px 20px;
            border: 2px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            background: rgba(0, 0, 0, 0.3);
            color: #888;
        }

        .status-btn input[type="radio"] {
            display: none;
        }

        .status-btn.present {
            border-color: #00FF7F;
            background: rgba(0, 255, 127, 0.1);
            color: #00FF7F;
        }

        .status-btn.late {
            border-color: #FFD700;
            background: rgba(255, 215, 0, 0.1);
            color: #FFD700;
        }

        .status-btn.absent {
            border-color: #FF4444;
            background: rgba(255, 68, 68, 0.1);
            color: #FF4444;
        }

        .status-btn:hover {
            transform: translateY(-2px);
        }

        .quick-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="starfield"></div>
    <div class="app-layout"></div>

    <div class="app-layout">
        <?php include '../includes/sidebar-nav.php'; ?>

        <main class="cyber-main">
            <header class="cyber-header">
                <div class="page-title-section">
                    <div class="page-icon-orb">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div>
                        <h1 class="page-title">Mark Attendance</h1>
                        <p class="page-subtitle">Record student attendance for your classes</p>
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

            <div class="app-layout">
                <?php if ($success_msg): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success_msg); ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_msg): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error_msg); ?>
                    </div>
                <?php endif; ?>

                <!-- Class Selection -->
                <div class="holo-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-door-open"></i>
                            <span>Select Class & Date</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="grid-2">
                            <div class="form-group">
                                <label class="form-label">Select Class</label>
                                <select name="class" class="cyber-input" onchange="this.form.submit()" required>
                                    <option value="">-- Choose a Class --</option>
                                    <?php foreach ($my_classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>"
                                            <?php echo $selected_class == $class['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                            (<?php echo $class['student_count']; ?> students)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Select Date</label>
                                <input type="date" name="date" class="cyber-input"
                                    value="<?php echo htmlspecialchars($selected_date); ?>"
                                    max="<?php echo date('Y-m-d'); ?>"
                                    onchange="this.form.submit()" required>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($selected_class && $class_info): ?>
                    <form method="POST">
                        <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                        <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selected_date); ?>">

                        <div class="holo-card">
                            <div class="card-header">
                                <div class="card-title">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo htmlspecialchars($class_info['class_name']); ?> - Student List</span>
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <button type="button" onclick="markAll('present')" class="cyber-btn btn-sm" style="background: rgba(0,255,127,0.1); border-color: #00FF7F;">
                                        <i class="fas fa-check-double"></i> All Present
                                    </button>
                                    <button type="submit" name="submit_attendance" class="cyber-btn btn-sm">
                                        <i class="fas fa-save"></i> Save Attendance
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($students)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-user-slash"></i>
                                        <p>No students enrolled in this class</p>
                                    </div>
                                <?php else: ?>
                                    <div class="attendance-grid">
                                        <?php foreach ($students as $student): ?>
                                            <div class="student-row">
                                                <div class="student-info">
                                                    <div class="student-name">
                                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                    </div>
                                                    <div class="student-id">
                                                        ID: <?php echo htmlspecialchars($student['student_id']); ?>
                                                    </div>
                                                </div>
                                                <div class="status-buttons">
                                                    <label class="status-btn <?php echo ($student['current_status'] ?? '') === 'present' ? 'present' : ''; ?>">
                                                        <input type="radio" name="students[<?php echo $student['id']; ?>]"
                                                            value="present" <?php echo ($student['current_status'] ?? '') === 'present' ? 'checked' : ''; ?>>
                                                        <i class="fas fa-check"></i> Present
                                                    </label>
                                                    <label class="status-btn <?php echo ($student['current_status'] ?? '') === 'late' ? 'late' : ''; ?>">
                                                        <input type="radio" name="students[<?php echo $student['id']; ?>]"
                                                            value="late" <?php echo ($student['current_status'] ?? '') === 'late' ? 'checked' : ''; ?>>
                                                        <i class="fas fa-clock"></i> Late
                                                    </label>
                                                    <label class="status-btn <?php echo ($student['current_status'] ?? '') === 'absent' ? 'absent' : ''; ?>">
                                                        <input type="radio" name="students[<?php echo $student['id']; ?>]"
                                                            value="absent" <?php echo ($student['current_status'] ?? '') === 'absent' ? 'checked' : ''; ?>>
                                                        <i class="fas fa-times"></i> Absent
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Handle radio button styling
        document.querySelectorAll('.status-btn').forEach(label => {
            label.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                const row = this.closest('.student-row');
                const buttons = row.querySelectorAll('.status-btn');

                buttons.forEach(btn => {
                    btn.classList.remove('present', 'late', 'absent');
                });

                if (radio.value === 'present') {
                    this.classList.add('present');
                } else if (radio.value === 'late') {
                    this.classList.add('late');
                } else if (radio.value === 'absent') {
                    this.classList.add('absent');
                }
            });
        });

        // Mark all students with a specific status
        function markAll(status) {
            document.querySelectorAll(`input[type="radio"][value="${status}"]`).forEach(radio => {
                radio.checked = true;
                const label = radio.closest('.status-btn');
                const row = label.closest('.student-row');
                const buttons = row.querySelectorAll('.status-btn');

                buttons.forEach(btn => {
                    btn.classList.remove('present', 'late', 'absent');
                });

                label.classList.add(status);
            });
        }
    </script>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pwa-manager.js"></script>
    <script src="../assets/js/pwa-analytics.js"></script>
</body>

</html>
