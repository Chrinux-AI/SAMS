<?php

/**
 * Student Self Check-in Page - Nature Edition
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_student();


$message = '';
$message_type = '';
$student = null;
$classes = [];
$today_attendance = [];

// Auto-load student data from logged-in session
$student = db()->fetchOne("
    SELECT s.*, u.first_name, u.last_name, u.email, u.id as user_id
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ? AND s.is_active = 1 AND u.status = 'active'
", [$_SESSION['user_id']]);

if (!$student) {
    $message = 'Student profile not found. Please contact administration.';
    $message_type = 'error';
}

// Handle check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkin']) && $student) {
    $class_id = (int)$_POST['class_id'];

    // Check if already checked in today
    $existing = db()->fetchOne("
        SELECT id FROM attendance_records
        WHERE student_id = ? AND class_id = ? AND DATE(check_in_time) = CURDATE()
    ", [$student['user_id'], $class_id]);

    if (!$existing) {
        db()->insert('attendance_records', [
            'student_id' => $student['user_id'],
            'class_id' => $class_id,
            'check_in_time' => date('Y-m-d H:i:s'),
            'status' => 'present',
            'marked_by' => $student['user_id']
        ]);

        log_activity($student['user_id'], 'self_checkin', 'attendance_records', $class_id, "Self check-in for class ID: $class_id");
        $message = 'Attendance recorded successfully!';
        $message_type = 'success';

        // Refresh attendance data
        $today_attendance = db()->fetchAll("
            SELECT ar.*, c.class_name, c.class_code
            FROM attendance_records ar
            JOIN classes c ON ar.class_id = c.id
            WHERE ar.student_id = ? AND DATE(ar.check_in_time) = CURDATE()
            ORDER BY ar.check_in_time DESC
        ", [$student['user_id']]);
    } else {
        $message = 'You have already checked in for this class today!';
        $message_type = 'warning';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    header('Location: ' . site_url('logout.php'));
    exit;
}

// Load enrolled classes and today's attendance
if ($student) {

    // Get enrolled classes
    $classes = db()->fetchAll("
        SELECT c.*, COUNT(ar.id) as attendance_count
        FROM classes c
        JOIN class_enrollments ce ON c.id = ce.class_id
        LEFT JOIN attendance_records ar ON c.id = ar.class_id AND ar.student_id = ?
        WHERE ce.student_id = ?
        GROUP BY c.id
        ORDER BY c.class_name
    ", [$student['user_id'], $student['user_id']]);

    // Get today's attendance
    $today_attendance = db()->fetchAll("
        SELECT ar.*, c.class_name, c.class_code
        FROM attendance_records ar
        JOIN classes c ON ar.class_id = c.id
        WHERE ar.student_id = ? AND DATE(ar.check_in_time) = CURDATE()
        ORDER BY ar.check_in_time DESC
    ", [$student['user_id']]);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <script src="../assets/js/theme-loader.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/attendance/manifest.json">
    <meta name="theme-color" content="#00BFFF">
    <link rel="apple-touch-icon" href="/attendance/assets/images/icons/icon-192x192.png">
    <title>Student Check-in - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../assets/css/professional-ui.css" rel="stylesheet">
    <?php include '../includes/sams-head-bootstrap.php'; ?>


    <style>
        .checkin-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .checkin-box {
            max-width: 500px;
            width: 100%;
        }

        .cyber-clock {
            background: #fff;
            border: 1px solid #E5E7EB;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            margin-bottom: 24px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
        }

        .clock-time {
            font-family: 'Courier New', monospace;
            font-size: 48px;
            font-weight: 700;
            color: #4F46E5;
            margin-bottom: 8px;
        }

        .clock-date {
            font-size: 16px;
            color: #6B7280;
        }

        .class-grid {
            display: grid;
            gap: 12px;
            margin-top: 20px;
        }

        .class-card-checkin {
            background: #fff;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }

        .class-card-checkin:hover {
            border-color: #4F46E5;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(79, 70, 229, 0.1);
        }

        .class-card-checkin.checked-in {
            border-color: #10B981;
            background: #F0FDF4;
        }

        .class-info {
            margin-bottom: 15px;
        }

        .class-name {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 4px;
        }

        .class-code {
            font-size: 13px;
            color: #6B7280;
        }

        .checkin-btn-class {
            width: 100%;
            padding: 12px;
            background: #4F46E5;
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            font-size: 0.9rem;
        }

        .checkin-btn-class:hover {
            background: #4338CA;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .checkin-btn-class:disabled {
            background: #E5E7EB;
            color: #9CA3AF;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .student-id-input {
            font-family: 'Courier New', monospace;
            font-size: 24px;
            text-align: center;
            letter-spacing: 2px;
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar-nav.php'; ?>
    <div class="app-layout">
        <div class="app-layout">

            <div class="checkin-container">
                <div class="checkin-box">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $message_type; ?>" style="margin-bottom: 20px;">
                            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'times-circle'); ?>"></i>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$student): ?>
                        <!-- Login Form -->
                        <div class="holo-card">
                            <div class="card-header" style="text-align: center;">
                                <div style="margin: 0 auto 20px;">
                                    <div class="page-icon-orb" style="margin: 0 auto;">
                                        <i class="fas fa-fingerprint"></i>
                                    </div>
                                </div>
                                <h1 style="font-size: 32px; margin-bottom: 10px;">Student Check-in</h1>
                                <p class="page-subtitle">Enter your Student ID to access the system</p>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-id-badge"></i> Student ID
                                        </label>
                                        <input type="text" name="student_id" class="cyber-input student-id-input"
                                            placeholder="STU20250001"
                                            maxlength="11"
                                            pattern="STU\d{8}"
                                            required autofocus
                                            style="text-transform: uppercase;">
                                        <small style="color: #888; font-size: 13px; display: block; margin-top: 8px;">
                                            <i class="fas fa-info-circle"></i> Format: STU followed by 8 digits (e.g., STU20250001)
                                        </small>
                                    </div>

                                    <button type="submit" name="login" class="cyber-btn" style="width: 100%; padding: 15px; font-size: 18px; margin-bottom: 15px;">
                                        <i class="fas fa-sign-in-alt"></i> Check In
                                    </button>

                                    <a href="dashboard.php" class="cyber-btn btn-secondary" style="width: 100%; padding: 12px; text-align: center; display: block;">
                                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                                    </a>
                                </form>

                                <div style="margin-top: 25px; padding: 15px; background: #EEF2FF; border-left: 4px solid #4F46E5; border-radius: 8px;">
                                    <h4 style="margin: 0 0 10px 0; color: #4F46E5;">
                                        <i class="fas fa-question-circle"></i> Don't have a Student ID?
                                    </h4>
                                    <p style="margin: 0; font-size: 14px; color: #888;">
                                        Contact administration or <a href="../register.php" style="color: #4F46E5; text-decoration: none; font-weight: 600;">register a new account</a>.
                                    </p>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Student Dashboard -->
                        <div class="app-layout">
                            <div class="clock-time" id="current-time"><?php echo date('H:i:s'); ?></div>
                            <div class="clock-date"><?php echo date('l, F j, Y'); ?></div>
                        </div>

                        <div class="holo-card">
                            <div class="card-header">
                                <div class="card-title">
                                    <i class="fas fa-user-circle"></i>
                                    <span>Welcome, <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                                </div>
                                <a href="?logout=1" class="cyber-btn btn-sm">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                            <div class="card-body">
                                <p style="margin: 0; color: #4F46E5;">
                                    <strong>Student ID:</strong> <?php echo htmlspecialchars($student['student_id']); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Today's Check-ins -->
                        <?php if (!empty($today_attendance)): ?>
                            <div class="holo-card" style="margin-top: 20px;">
                                <div class="card-header">
                                    <div class="card-title">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Today's Check-ins</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($today_attendance as $record): ?>
                                        <div style="padding: 10px; background: #F0FDF4; border-left: 3px solid #10B981; margin-bottom: 10px; border-radius: 5px;">
                                            <strong><?php echo htmlspecialchars($record['class_name']); ?></strong>
                                            <span style="float: right; color: #10B981;">
                                                <i class="fas fa-check"></i> <?php echo date('h:i A', strtotime($record['check_in_time'])); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Available Classes -->
                        <div class="holo-card" style="margin-top: 20px;">
                            <div class="card-header">
                                <div class="card-title">
                                    <i class="fas fa-door-open"></i>
                                    <span>Available Classes</span>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($classes)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-calendar-times"></i>
                                        <p>No classes enrolled</p>
                                    </div>
                                <?php else: ?>
                                    <div class="class-grid">
                                        <?php foreach ($classes as $class):
                                            $is_checked_in = false;
                                            foreach ($today_attendance as $att) {
                                                if ($att['class_id'] == $class['id']) {
                                                    $is_checked_in = true;
                                                    break;
                                                }
                                            }
                                        ?>
                                            <div class="class-card-checkin <?php echo $is_checked_in ? 'checked-in' : ''; ?>">
                                                <div class="class-info">
                                                    <div class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                                    <div class="class-code">
                                                        <?php echo htmlspecialchars($class['class_code']); ?>
                                                        <?php if ($class['room_number']): ?>
                                                            • Room <?php echo htmlspecialchars($class['room_number']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <form method="POST" style="margin: 0;">
                                                    <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                                    <button type="submit" name="checkin" class="checkin-btn-class"
                                                        <?php echo $is_checked_in ? 'disabled' : ''; ?>>
                                                        <?php if ($is_checked_in): ?>
                                                            <i class="fas fa-check-double"></i> Already Checked In
                                                        <?php else: ?>
                                                            <i class="fas fa-fingerprint"></i> Check In Now
                                                        <?php endif; ?>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="margin-top: 20px; text-align: center;">
                            <a href="dashboard.php" class="cyber-btn btn-secondary">
                                <i class="fas fa-home"></i> Go to Dashboard
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <script>
                // Update clock every second
                function updateClock() {
                    const now = new Date();
                    const timeString = now.toLocaleTimeString('en-US', {
                        hour12: false
                    });
                    const clockElement = document.getElementById('current-time');
                    if (clockElement) {
                        clockElement.textContent = timeString;
                    }
                }
                setInterval(updateClock, 1000);

                // Auto-format student ID input
                const studentIdInput = document.querySelector('input[name="student_id"]');
                if (studentIdInput) {
                    studentIdInput.addEventListener('input', function(e) {
                        let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                        if (value.length > 0 && !value.startsWith('STU')) {
                            value = 'STU' + value;
                        }
                        e.target.value = value.substring(0, 11);
                    });
                }
            </script>

        </div>
    </div>
    <script src="../assets/js/main.js"></script>
</body>

</html>
