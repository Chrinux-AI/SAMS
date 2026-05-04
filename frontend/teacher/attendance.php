<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once PROJECT_ROOT . '/backend/includes/merit-integration.php';
require_teacher();

$teacher_id = (int)($_SESSION['user_id'] ?? 0);
$full_name = $_SESSION['full_name'];
$success_msg = '';
$error_msg = '';
$currentTenantId = current_tenant_id();

if (!isset($_SESSION['tenant_id'])) {
    set_user_tenant_session($teacher_id);
    $currentTenantId = current_tenant_id();
}

if ($teacher_id <= 0 || !$currentTenantId || !user_in_current_tenant($teacher_id)) {
    http_response_code(403);
    exit('Tenant access denied');
}

function teacher_attendance_scope(string $table, string $alias, int $tenantId): array
{
    $qualified = $alias !== '' ? $alias . '.' : '';
    if (table_has_column($table, 'tenant_id')) {
        return ['sql' => " AND {$qualified}tenant_id = ?", 'params' => [$tenantId]];
    }
    if (table_has_column($table, 'school_id')) {
        return ['sql' => " AND {$qualified}school_id = ?", 'params' => [$tenantId]];
    }

    return ['sql' => '', 'params' => []];
}

function teacher_attendance_payload(string $table, int $tenantId): array
{
    if (table_has_column($table, 'tenant_id')) {
        return ['tenant_id' => $tenantId];
    }
    if (table_has_column($table, 'school_id')) {
        return ['school_id' => $tenantId];
    }

    return [];
}

function teacher_attendance_status(string $status): string
{
    $status = strtolower(trim($status));
    return in_array($status, ['present', 'late', 'absent'], true) ? $status : 'present';
}

function teacher_attendance_datetime_field(string $table): string
{
    if (table_has_column($table, 'check_in_time')) {
        return 'check_in_time';
    }
    if (table_has_column($table, 'attendance_date')) {
        return 'attendance_date';
    }
    if (table_has_column($table, 'date')) {
        return 'date';
    }

    return 'check_in_time';
}

function teacher_attendance_class_row(int $classId, int $teacherId, int $tenantId): ?array
{
    if ($classId <= 0 || $teacherId <= 0) {
        return null;
    }

    $classScope = teacher_attendance_scope('classes', '', $tenantId);
    $row = db()->fetchOne(
        "SELECT * FROM classes WHERE id = ? AND class_teacher_id = ?{$classScope['sql']} LIMIT 1",
        array_merge([$classId, $teacherId], $classScope['params'])
    );

    return $row ?: null;
}

function teacher_attendance_student_ids(int $classId, int $tenantId): array
{
    if ($classId <= 0) {
        return [];
    }

    $classScope = teacher_attendance_scope('classes', 'c', $tenantId);
    $studentScope = teacher_attendance_scope('students', 's', $tenantId);
    $rows = db()->fetchAll(
        "SELECT ce.student_id
         FROM class_enrollments ce
         JOIN classes c ON c.id = ce.class_id
         JOIN students s ON s.user_id = ce.student_id
         WHERE ce.class_id = ?{$classScope['sql']}{$studentScope['sql']}",
        array_merge([$classId], $classScope['params'], $studentScope['params'])
    );

    return array_map(static fn(array $row): int => (int)$row['student_id'], $rows ?: []);
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    $class_id = intval($_POST['class_id']);
    $attendance_date = sanitize($_POST['attendance_date']);
    $students = $_POST['students'] ?? [];
    $parsedDate = date_create($attendance_date);

    try {
        if (!$parsedDate) {
            throw new RuntimeException('Invalid attendance date.');
        }

        if (!teacher_attendance_class_row($class_id, $teacher_id, $currentTenantId)) {
            throw new RuntimeException('Selected class is not available in the active tenant.');
        }

        $validStudentIds = array_flip(teacher_attendance_student_ids($class_id, $currentTenantId));
        $attendanceScope = teacher_attendance_scope('attendance_records', '', $currentTenantId);
        $attendanceDateField = teacher_attendance_datetime_field('attendance_records');
        $attendanceTimestamp = $parsedDate->format('Y-m-d') . ' ' . date('H:i:s');
        $processedCount = 0;

        foreach ($students as $student_id => $status) {
            $student_id = (int)$student_id;
            if ($student_id <= 0 || !isset($validStudentIds[$student_id])) {
                continue;
            }

            $status = teacher_attendance_status((string)$status);
            $existing = db()->fetchOne(
                "SELECT id FROM attendance_records
                WHERE student_id = ? AND class_id = ? AND DATE({$attendanceDateField}) = ?{$attendanceScope['sql']}",
                array_merge([$student_id, $class_id, $parsedDate->format('Y-m-d')], $attendanceScope['params'])
            );

            if ($existing) {
                update_flexible('attendance_records', [
                    'status' => $status,
                    $attendanceDateField => $attendanceTimestamp,
                    'marked_by' => $teacher_id,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [(int)$existing['id']]);
                $attendanceId = (int)$existing['id'];
            } else {
                $attendanceId = insert_flexible('attendance_records', array_merge([
                    'student_id' => $student_id,
                    'class_id' => $class_id,
                    $attendanceDateField => $attendanceTimestamp,
                    'status' => $status,
                    'marked_by' => $teacher_id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ], teacher_attendance_payload('attendance_records', $currentTenantId)));
            }

            if (!$attendanceId) {
                continue;
            }

            $processedCount++;

            try {
                sams_sync_attendance_merit((int) $attendanceId, (int) $student_id, (int) $class_id, (string) $status, (int) $teacher_id, (string) $attendance_date, 'teacher_attendance');
            } catch (Throwable $e) {
                error_log('Attendance merit sync failed (teacher): ' . $e->getMessage());
            }
        }

        log_activity($teacher_id, 'mark_attendance', 'attendance_records', $class_id, "Marked attendance for class ID: $class_id on $attendance_date");
        if ($processedCount === 0) {
            throw new RuntimeException('No valid attendance records were saved.');
        }

        $success_msg = "Attendance marked successfully for {$processedCount} students!";
    } catch (Exception $e) {
        $error_msg = "Failed to mark attendance: " . $e->getMessage();
    }
}

// Get teacher's classes
$classScope = teacher_attendance_scope('classes', 'c', $currentTenantId);
$my_classes = db()->fetchAll("
    SELECT c.id, c.class_name, c.class_code, c.class_teacher_id, c.description, c.schedule, c.room_number, c.created_at,
           COUNT(DISTINCT ce.student_id) as student_count
    FROM classes c
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE c.class_teacher_id = ?{$classScope['sql']}
    GROUP BY c.id, c.class_name, c.class_code, c.class_teacher_id, c.description, c.schedule, c.room_number, c.created_at
", array_merge([$teacher_id], $classScope['params']));

// Selected class for attendance
$selected_class = isset($_GET['class']) ? intval($_GET['class']) : null;
$selected_date = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');

// Get students in selected class
$students = [];
$class_info = null;
if ($selected_class) {
    $class_info = teacher_attendance_class_row((int)$selected_class, $teacher_id, $currentTenantId);

    if ($class_info) {
        $studentScope = teacher_attendance_scope('students', 's', $currentTenantId);
        $userScope = teacher_attendance_scope('users', 'u', $currentTenantId);
        $attendanceScope = teacher_attendance_scope('attendance_records', 'ar', $currentTenantId);
        $attendanceDateField = teacher_attendance_datetime_field('attendance_records');
        $students = db()->fetchAll("
            SELECT u.id, u.first_name, u.last_name, s.admission_number AS student_id,
                   ar.status as current_status, ar.id as attendance_id
            FROM users u
            JOIN students s ON u.id = s.user_id
            JOIN class_enrollments ce ON s.user_id = ce.student_id
            JOIN classes c ON c.id = ce.class_id
            LEFT JOIN attendance_records ar ON u.id = ar.student_id
                AND ar.class_id = ?
                AND DATE(ar.{$attendanceDateField}) = ?{$attendanceScope['sql']}
            WHERE ce.class_id = ? AND u.status = 'active'
            {$classScope['sql']}
            {$studentScope['sql']}
            {$userScope['sql']}
            ORDER BY u.last_name, u.first_name
        ", array_merge([$selected_class, $selected_date], $attendanceScope['params'], [$selected_class], $classScope['params'], $studentScope['params'], $userScope['params']));
    }
}

// Unread messages
$unread_count = get_unread_message_count($teacher_id, $currentTenantId);
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
    <?php include '../includes/sams-head-bootstrap.php'; ?>

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
