<?php

/**
 * Nature Attendance Marking Page
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
            WHERE student_id = ? AND class_id = ? AND DATE(attendance_date) = ?
        ", [$student_id, $class_id, $date]);

        if (!$existing) {
            // Insert new attendance record
            db()->insert('attendance_records', [
                'student_id' => $student_id,
                'class_id' => $class_id,
                'attendance_date' => $date,
                'status' => $status,
                'marked_by' => $_SESSION['user_id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } else {
            // Update existing record
            db()->update('attendance_records', [
                'status' => $status,
                'marked_by' => $_SESSION['user_id']
            ], 'id = :id', ['id' => $existing['id']]);
        }
    }

    try {
        AuditLogger::log('mark_attendance', 'attendance', "Marked attendance for class #{$class_id} on {$date}, " . count($attendance_data) . " students", $_SESSION['user_id'] ?? null);
    } catch (\Throwable $e) {
    }

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
            WHERE student_id = ? AND class_id = ? AND DATE(attendance_date) = ?
        ", [$student['id'], $selected_class_id, $selected_date]);

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
<div class="grid grid-cols-12 gap-6">
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
    </head>

    <body>



        <div class="app-layout">
            <?php include '../includes/sidebar-nav.php'; ?>

            <main class="main-content">
                <header class="cyber-header">
                    <div class="page-title-section">
                        <div class="page-icon-orb">
                            <i class="fas fa-<?php echo $page_icon; ?>"></i>
                        </div>
                        <h1 class="page-title"><?php echo $page_title; ?></h1>
                    </div>

                    <div class="header-actions">
                        <div class="biometric-orb" title="Biometric Scan" onclick="showBiometricScan()">
                            <i class="fas fa-fingerprint"></i>
                        </div>

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
                        <div class="cyber-alert cyber-alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>" style="margin-bottom: 20px;">
                            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                            <span><?php echo htmlspecialchars($message); ?></span>
                        </div>
                    <?php endif; ?>

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
                                            <?php echo htmlspecialchars($class['name']); ?> (<?php echo $class['student_count']; ?> students)
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
                </div>
            </main>
        </div>

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
                        // Mark as present
                        const radio = document.querySelector(`input[name="attendance[${studentId}]"][value="present"]`);
                        if (radio) {
                            radio.checked = true;
                        }

                        // Visual feedback
                        const row = radio.closest('.student-row');
                        row.style.background = 'rgba(0, 255, 127, 0.2)';
                        row.style.borderColor = 'var(--neon-green)';
                        setTimeout(() => {
                            row.style.background = '';
                            row.style.borderColor = '';
                        }, 1000);
                    }
                } catch (error) {
                    console.error('Biometric scan failed:', error);
                }
            }
        </script>

        <script src="../assets/js/main.js"></script>
        <script src="../assets/js/pwa-manager.js"></script>
        <script src="../assets/js/pwa-analytics.js"></script>
</div><!-- End grid -->
<?php
// Capture output and use master layout
$page_content = ob_get_clean();
require BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
?>
