<?php

/**
 * Nature Attendance Marking Page
 */

session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once PROJECT_ROOT . '/backend/includes/merit-integration.php';

// Require admin access
require_admin('../login.php');

$message = '';
$message_type = '';
$currentTenantId = current_tenant_id();
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

if (!isset($_SESSION['tenant_id'])) {
    set_user_tenant_session($currentUserId);
    $currentTenantId = current_tenant_id();
}

if ($currentUserId <= 0 || !$currentTenantId || !user_in_current_tenant($currentUserId)) {
    http_response_code(403);
    exit('Tenant access denied');
}

function admin_attendance_scope(string $table, string $alias, int $tenantId): array
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

function admin_attendance_payload(string $table, int $tenantId): array
{
    if (table_has_column($table, 'tenant_id')) {
        return ['tenant_id' => $tenantId];
    }
    if (table_has_column($table, 'school_id')) {
        return ['school_id' => $tenantId];
    }

    return [];
}

function admin_attendance_normalize_status(string $status): string
{
    $status = strtolower(trim($status));
    return in_array($status, ['present', 'late', 'absent', 'excused'], true) ? $status : 'present';
}

function admin_attendance_class_exists(int $classId, int $tenantId): bool
{
    if ($classId <= 0) {
        return false;
    }

    $scope = admin_attendance_scope('classes', '', $tenantId);
    $row = db()->fetchOne(
        "SELECT id FROM classes WHERE id = ?{$scope['sql']} LIMIT 1",
        array_merge([$classId], $scope['params'])
    );

    return !empty($row);
}

function admin_attendance_class_student_ids(int $classId, int $tenantId): array
{
    if ($classId <= 0) {
        return [];
    }

    $classScope = admin_attendance_scope('classes', 'c', $tenantId);
    $studentScope = admin_attendance_scope('students', 's', $tenantId);
    $rows = db()->fetchAll(
        "SELECT ce.student_id
         FROM class_enrollments ce
         JOIN classes c ON c.id = ce.class_id
         JOIN students s ON s.id = ce.student_id
         WHERE ce.class_id = ?{$classScope['sql']}{$studentScope['sql']}",
        array_merge([$classId], $classScope['params'], $studentScope['params'])
    );

    return array_map(static fn(array $row): int => (int)$row['student_id'], $rows ?: []);
}

// Handle attendance marking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $class_id = (int)$_POST['class_id'];
    $date = (string)($_POST['attendance_date'] ?? '');
    $attendance_data = $_POST['attendance'] ?? [];
    $parsedDate = date_create($date);

    if (!$parsedDate) {
        $message = 'Invalid attendance date.';
        $message_type = 'error';
    } elseif (!admin_attendance_class_exists($class_id, $currentTenantId)) {
        $message = 'Selected class is not available in the active tenant.';
        $message_type = 'error';
    } else {
        $validStudentIds = array_flip(admin_attendance_class_student_ids($class_id, $currentTenantId));
        $attendanceScope = admin_attendance_scope('attendance_records', '', $currentTenantId);
        $processedCount = 0;

        foreach ($attendance_data as $student_id => $status) {
            $student_id = (int)$student_id;
            if ($student_id <= 0 || !isset($validStudentIds[$student_id])) {
                continue;
            }

            $status = admin_attendance_normalize_status((string)$status);
            $existing = db()->fetch("
                SELECT id FROM attendance_records
                WHERE student_id = ? AND class_id = ? AND DATE(attendance_date) = ?{$attendanceScope['sql']}
            ", array_merge([$student_id, $class_id, $parsedDate->format('Y-m-d')], $attendanceScope['params']));

            if (!$existing) {
                $attendanceId = insert_flexible('attendance_records', array_merge([
                    'student_id' => $student_id,
                    'class_id' => $class_id,
                    'attendance_date' => $parsedDate->format('Y-m-d'),
                    'status' => $status,
                    'marked_by' => $currentUserId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ], admin_attendance_payload('attendance_records', $currentTenantId)));
            } else {
                update_flexible('attendance_records', [
                    'status' => $status,
                    'marked_by' => $currentUserId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [(int)$existing['id']]);
                $attendanceId = (int)$existing['id'];
            }

            if (!$attendanceId) {
                continue;
            }

            $processedCount++;

            try {
                sams_sync_attendance_merit((int)$attendanceId, $student_id, $class_id, $status, $currentUserId, $parsedDate->format('Y-m-d'), 'admin_attendance');
            } catch (Throwable $e) {
                error_log('Attendance merit sync failed (admin): ' . $e->getMessage());
            }
        }

        try {
            AuditLogger::log('mark_attendance', 'attendance', "Marked attendance for class #{$class_id} on {$date}, {$processedCount} students", $_SESSION['user_id'] ?? null);
        } catch (\Throwable $e) {
        }

        $message = $processedCount > 0 ? 'Attendance marked successfully!' : 'No valid attendance records were saved.';
        $message_type = $processedCount > 0 ? 'success' : 'error';
    }
}

// Get all classes for dropdown
$classScope = admin_attendance_scope('classes', 'c', $currentTenantId);
$classes = db()->fetchAll("
    SELECT c.*, COUNT(ce.student_id) as student_count
    FROM classes c
    LEFT JOIN class_enrollments ce ON c.id = ce.class_id
    WHERE 1 = 1{$classScope['sql']}
    GROUP BY c.id
    ORDER BY c.class_name
", $classScope['params']);

// Get students for selected class
$selected_class_id = $_GET['class_id'] ?? ($_POST['class_id'] ?? null);
$selected_date = $_GET['date'] ?? ($_POST['attendance_date'] ?? date('Y-m-d'));
$students = [];

if ($selected_class_id) {
    $studentScope = admin_attendance_scope('students', 's', $currentTenantId);
    $userScope = admin_attendance_scope('users', 'u', $currentTenantId);
    $students = db()->fetchAll("
        SELECT s.*, u.first_name, u.last_name, u.email, ce.enrollment_date
        FROM students s
        LEFT JOIN users u ON s.user_id = u.id
        JOIN class_enrollments ce ON s.id = ce.student_id
        JOIN classes c ON c.id = ce.class_id
        WHERE ce.class_id = ?
        {$classScope['sql']}
        {$studentScope['sql']}
        {$userScope['sql']}
        ORDER BY u.last_name, u.first_name
    ", array_merge([(int)$selected_class_id], $classScope['params'], $studentScope['params'], $userScope['params']));

    // Get existing attendance for the date
    $attendanceScope = admin_attendance_scope('attendance_records', '', $currentTenantId);
    foreach ($students as &$student) {
        $record = db()->fetch("
            SELECT * FROM attendance_records
            WHERE student_id = ? AND class_id = ? AND DATE(attendance_date) = ?{$attendanceScope['sql']}
        ", array_merge([(int)$student['id'], (int)$selected_class_id, $selected_date], $attendanceScope['params']));

        $student['attendance_status'] = $record ? $record['status'] : 'present';
        $student['attendance_id'] = $record ? $record['id'] : null;
    }
}

// Page metadata
$page_title = 'Mark Attendance';
$page_icon = 'assignment_turned_in';
$page_subtitle = 'Record student attendance for classes';
$full_name = $_SESSION['full_name'];

// Start output buffering for master layout
ob_start();
?>

<!-- Attendance Marking Interface -->
<style>
    .student-row {
        background: rgba(0, 191, 255, 0.05);
        border: 1px solid rgba(0, 191, 255, 0.2);
        border-radius: 10px;
        padding: 15px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.3s;
        margin-bottom: 12px;
    }

    .student-row:hover {
        background: rgba(0, 191, 255, 0.09);
        border-color: #00BFFF;
    }

    .student-avatar {
        width: 44px;
        height: 44px;
        border-radius: 999px;
        background: linear-gradient(135deg, var(--cyber-cyan), var(--hologram-purple));
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 700;
        letter-spacing: 0.04em;
        flex-shrink: 0;
    }

    .student-info {
        flex: 1;
    }

    .student-name {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 3px;
    }

    .student-id {
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    .status-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .status-btn {
        padding: 8px 16px;
        border: 2px solid;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
        font-family: 'Inter', sans-serif;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-size: 0.85rem;
    }

    .status-btn input[type="radio"] {
        display: none;
    }

    .status-btn.present {
        border-color: var(--neon-green);
        color: var(--neon-green);
        background: rgba(0, 255, 127, 0.05);
    }

    .status-btn.present:has(input:checked) {
        background: var(--neon-green);
        color: black;
        box-shadow: 0 0 20px rgba(0, 255, 127, 0.5);
    }

    .status-btn.late {
        border-color: var(--golden-pulse);
        color: var(--golden-pulse);
        background: rgba(255, 215, 0, 0.05);
    }

    .status-btn.late:has(input:checked) {
        background: var(--golden-pulse);
        color: black;
        box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
    }

    .status-btn.absent {
        border-color: var(--cyber-red);
        color: var(--cyber-red);
        background: rgba(255, 69, 0, 0.05);
    }

    .status-btn.absent:has(input:checked) {
        background: var(--cyber-red);
        color: white;
        box-shadow: 0 0 20px rgba(255, 69, 0, 0.5);
    }

    .status-btn.excused {
        border-color: var(--hologram-purple);
        color: var(--hologram-purple);
        background: rgba(138, 43, 226, 0.05);
    }

    .status-btn.excused:has(input:checked) {
        background: var(--hologram-purple);
        color: white;
        box-shadow: 0 0 20px rgba(138, 43, 226, 0.5);
    }

    .biometric-quick-scan {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--golden-pulse), var(--cyber-cyan));
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 0 20px rgba(255, 215, 0, 0.4);
        flex-shrink: 0;
    }

    .biometric-quick-scan:hover {
        transform: scale(1.1) rotate(10deg);
        box-shadow: 0 0 30px rgba(255, 215, 0, 0.7);
    }
</style>

<?php if ($message): ?>
    <div class="cyber-alert cyber-alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>" style="margin-bottom: 20px;">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
        <span><?php echo htmlspecialchars($message); ?></span>
    </div>
<?php endif; ?>

<div class="flex justify-end mb-4">
    <button type="button" class="cyber-btn cyber-btn-outline" onclick="showBiometricScan()">
        <i class="fas fa-fingerprint"></i>
        <span>Biometric Scan</span>
    </button>
</div>

                    <!-- Class Selection -->
                    <div class="holo-card" style="margin-bottom: 25px;">
                        <h3 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-filter" style="color: var(--cyber-cyan);"></i>
                            <span>Select Class & Date</span>
                        </h3>

                        <form method="GET" style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 15px; align-items: end;">
                            <div>
                                <label class="cyber-label" for="class_id">Class</label>
                                <select id="class_id" name="class_id" class="cyber-input" required onchange="this.form.submit()">
                                    <option value="">Select a class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>" <?php echo $selected_class_id == $class['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string)($class['class_name'] ?? $class['name'] ?? 'Unnamed Class')); ?> (<?php echo $class['student_count']; ?> students)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="cyber-label" for="date">Date</label>
                                <input type="date" id="date" name="date" class="cyber-input" value="<?php echo $selected_date; ?>" onchange="this.form.submit()">
                            </div>

                            <button type="submit" class="cyber-btn cyber-btn-primary">
                                <i class="fas fa-sync"></i>
                                <span>Refresh</span>
                            </button>
                        </form>
                    </div>

                    <?php if ($selected_class_id && !empty($students)): ?>
                        <!-- Attendance Form -->
                        <form method="POST">
                            <input type="hidden" name="class_id" value="<?php echo $selected_class_id; ?>">
                            <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">

                            <div class="holo-card">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                    <h3 style="margin: 0;">
                                        <i class="fas fa-users" style="color: var(--neon-green);"></i>
                                        Students (<?php echo count($students); ?>)
                                    </h3>

                                    <div style="display: flex; gap: 10px;">
                                        <button type="button" onclick="markAll('present')" class="cyber-btn cyber-btn-success">
                                            <i class="fas fa-check-circle"></i>
                                            <span>All Present</span>
                                        </button>
                                        <button type="button" onclick="markAll('absent')" class="cyber-btn cyber-btn-outline">
                                            <i class="fas fa-times-circle"></i>
                                            <span>All Absent</span>
                                        </button>
                                    </div>
                                </div>

                                <div style="max-height: 500px; overflow-y: auto; padding-right: 10px;">
                                    <?php foreach ($students as $student): ?>
                                        <div class="student-row">
                                            <div class="student-avatar">
                                                <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                            </div>

                                            <div class="student-info">
                                                <div class="student-name">
                                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                </div>
                                                <div class="student-id">
                                                    ID: <?php echo htmlspecialchars($student['admission_number'] ?? 'N/A'); ?>
                                                </div>
                                            </div>

                                            <div class="biometric-quick-scan" title="Biometric Scan" onclick="scanStudent(<?php echo $student['id']; ?>)">
                                                <i class="fas fa-fingerprint"></i>
                                            </div>

                                            <div class="status-buttons">
                                                <label class="status-btn present">
                                                    <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="present" <?php echo $student['attendance_status'] === 'present' ? 'checked' : ''; ?>>
                                                    <span>Present</span>
                                                </label>

                                                <label class="status-btn late">
                                                    <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="late" <?php echo $student['attendance_status'] === 'late' ? 'checked' : ''; ?>>
                                                    <span>Late</span>
                                                </label>

                                                <label class="status-btn absent">
                                                    <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="absent" <?php echo $student['attendance_status'] === 'absent' ? 'checked' : ''; ?>>
                                                    <span>Absent</span>
                                                </label>

                                                <label class="status-btn excused">
                                                    <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="excused" <?php echo $student['attendance_status'] === 'excused' ? 'checked' : ''; ?>>
                                                    <span>Excused</span>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div style="margin-top: 20px; text-align: right;">
                                    <button type="submit" name="mark_attendance" class="cyber-btn cyber-btn-primary" style="padding: 15px 40px; font-size: 1.1rem;">
                                        <i class="fas fa-save"></i>
                                        <span>Save Attendance</span>
                                    </button>
                                </div>
                            </div>
                        </form>

                    <?php elseif ($selected_class_id && empty($students)): ?>
                        <div class="holo-card" style="text-align: center; padding: 60px;">
                            <i class="fas fa-user-slash" style="font-size: 4rem; color: var(--text-muted); opacity: 0.5; margin-bottom: 20px;"></i>
                            <p style="color: var(--text-muted); font-size: 1.1rem;">No students enrolled in this class</p>
                        </div>

                    <?php else: ?>
                        <div class="holo-card" style="text-align: center; padding: 60px;">
                            <i class="fas fa-hand-pointer" style="font-size: 4rem; color: var(--cyber-cyan); opacity: 0.5; margin-bottom: 20px;"></i>
                            <p style="color: var(--text-muted); font-size: 1.1rem;">Select a class to mark attendance</p>
                        </div>
                    <?php endif; ?>
<script>
    function markAll(status) {
        const radios = document.querySelectorAll(`input[type="radio"][value="${status}"]`);
        radios.forEach(radio => {
            radio.checked = true;
        });
    }

    function showBiometricScan() {
        alert('Biometric Authentication\n\nThis feature allows instant login and attendance marking via fingerprint/face recognition.\n\nTo enable:\n1. Login with credentials\n2. Go to Settings\n3. Register Biometric\n4. Scan your fingerprint/face');
    }

    async function scanStudent(studentId) {
        if (!window.biometricAuth || !window.biometricAuth.supported) {
            alert('Biometric authentication not supported on this device');
            return;
        }

        try {
            const result = await window.biometricAuth.quickScan();
            if (result.success) {
                const radio = document.querySelector(`input[name="attendance[${studentId}]"][value="present"]`);
                if (radio) {
                    radio.checked = true;
                }

                const row = radio ? radio.closest('.student-row') : null;
                if (row) {
                    row.style.background = 'rgba(0, 255, 127, 0.2)';
                    row.style.borderColor = 'var(--neon-green)';
                    setTimeout(() => {
                        row.style.background = '';
                        row.style.borderColor = '';
                    }, 1000);
                }
            }
        } catch (error) {
            console.error('Biometric scan failed:', error);
        }
    }
</script>
<?php
// Capture output and use master layout
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
?>
