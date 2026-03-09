<?php

/**
 * Enhanced Attendance API - Student Panel
 * Handles QR check-in, geolocation, corrections, analytics, calendar sync
 * Version: 2.1.0
 */

session_start();
header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$student_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Auto-create tables if they don't exist
createTablesIfNotExist();

/**
 * Create required database tables
 */
function createTablesIfNotExist()
{
    global $pdo;

    // Attendance QR codes table
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_qr_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        qr_code VARCHAR(255) UNIQUE NOT NULL,
        location_lat DECIMAL(10, 8) NULL,
        location_lng DECIMAL(11, 8) NULL,
        valid_from DATETIME NOT NULL,
        valid_until DATETIME NOT NULL,
        max_uses INT DEFAULT 0,
        current_uses INT DEFAULT 0,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        INDEX idx_qr_code (qr_code),
        INDEX idx_valid_period (valid_from, valid_until)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Attendance correction requests table
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_corrections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        attendance_record_id INT NULL,
        class_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        current_status ENUM('present', 'absent', 'late', 'excused') NULL,
        requested_status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
        reason TEXT NOT NULL,
        supporting_documents VARCHAR(500) NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        reviewed_by INT NULL,
        review_notes TEXT NULL,
        reviewed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (attendance_record_id) REFERENCES attendance_records(id) ON DELETE SET NULL,
        INDEX idx_student (student_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Class reminders table
    $pdo->exec("CREATE TABLE IF NOT EXISTS class_reminders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        reminder_time INT NOT NULL COMMENT 'Minutes before class',
        is_enabled TINYINT(1) DEFAULT 1,
        notify_email TINYINT(1) DEFAULT 0,
        notify_sms TINYINT(1) DEFAULT 0,
        notify_push TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        UNIQUE KEY unique_reminder (student_id, class_id, reminder_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Attendance geolocation logs
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_geolocation_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        recorded_lat DECIMAL(10, 8) NOT NULL,
        recorded_lng DECIMAL(11, 8) NOT NULL,
        expected_lat DECIMAL(10, 8) NULL,
        expected_lng DECIMAL(11, 8) NULL,
        distance_meters DECIMAL(10, 2) NULL,
        is_valid TINYINT(1) DEFAULT 0,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        INDEX idx_student_date (student_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Route actions
switch ($action) {
    case 'qr_checkin':
        handleQRCheckIn();
        break;
    case 'verify_location':
        handleLocationVerification();
        break;
    case 'submit_correction':
        handleCorrectionRequest();
        break;
    case 'get_corrections':
        getCorrectionRequests();
        break;
    case 'cancel_correction':
        cancelCorrectionRequest();
        break;
    case 'get_analytics':
        getAttendanceAnalytics();
        break;
    case 'export_calendar':
        exportToCalendar();
        break;
    case 'set_reminder':
        setClassReminder();
        break;
    case 'get_reminders':
        getClassReminders();
        break;
    case 'delete_reminder':
        deleteReminder();
        break;
    case 'get_streak':
        getAttendanceStreak();
        break;
    case 'get_today_classes':
        getTodayClasses();
        break;
    case 'get_attendance_history':
        getAttendanceHistory();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

/**
 * Handle QR code check-in
 */
function handleQRCheckIn()
{
    global $pdo, $student_id;

    $qr_code = $_POST['qr_code'] ?? '';
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    if (empty($qr_code)) {
        echo json_encode(['success' => false, 'error' => 'QR code is required']);
        return;
    }

    try {
        // Validate QR code
        $stmt = $pdo->prepare("
            SELECT qc.*, c.name as class_name, c.class_code
            FROM attendance_qr_codes qc
            JOIN classes c ON qc.class_id = c.id
            WHERE qc.qr_code = ?
            AND NOW() BETWEEN qc.valid_from AND qc.valid_until
            AND (qc.max_uses = 0 OR qc.current_uses < qc.max_uses)
        ");
        $stmt->execute([$qr_code]);
        $qr_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$qr_data) {
            echo json_encode(['success' => false, 'error' => 'Invalid or expired QR code']);
            return;
        }

        // Check if already checked in today
        $stmt = $pdo->prepare("
            SELECT id FROM attendance_records
            WHERE student_id = ? AND class_id = ? AND DATE(check_in_time) = CURDATE()
        ");
        $stmt->execute([$student_id, $qr_data['class_id']]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Already checked in for this class today']);
            return;
        }

        // Verify geolocation if required
        $location_valid = true;
        $distance = null;
        if ($qr_data['location_lat'] && $qr_data['location_lng'] && $latitude && $longitude) {
            $distance = calculateDistance(
                $latitude,
                $longitude,
                $qr_data['location_lat'],
                $qr_data['location_lng']
            );
            $location_valid = $distance <= 100; // 100 meters radius

            // Log geolocation attempt
            $stmt = $pdo->prepare("
                INSERT INTO attendance_geolocation_logs
                (student_id, class_id, recorded_lat, recorded_lng, expected_lat, expected_lng, distance_meters, is_valid, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $student_id,
                $qr_data['class_id'],
                $latitude,
                $longitude,
                $qr_data['location_lat'],
                $qr_data['location_lng'],
                $distance,
                $location_valid ? 1 : 0,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        }

        if (!$location_valid) {
            echo json_encode([
                'success' => false,
                'error' => 'You are too far from the class location',
                'distance' => round($distance, 2)
            ]);
            return;
        }

        // Record attendance
        $stmt = $pdo->prepare("
            INSERT INTO attendance_records
            (student_id, class_id, check_in_time, status, marked_by, method)
            VALUES (?, ?, NOW(), 'present', ?, 'qr_code')
        ");
        $stmt->execute([$student_id, $qr_data['class_id'], $student_id]);

        // Update QR code usage
        $stmt = $pdo->prepare("UPDATE attendance_qr_codes SET current_uses = current_uses + 1 WHERE id = ?");
        $stmt->execute([$qr_data['id']]);

        // Log activity
        log_activity(
            $student_id,
            'qr_checkin',
            'attendance_records',
            $qr_data['class_id'],
            "QR check-in for {$qr_data['class_name']}"
        );

        echo json_encode([
            'success' => true,
            'message' => "Successfully checked in to {$qr_data['class_name']}",
            'class_name' => $qr_data['class_name'],
            'time' => date('h:i A')
        ]);
    } catch (Exception $e) {
        error_log("QR Check-in Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to process check-in']);
    }
}

/**
 * Handle location verification (manual check-in with geolocation)
 */
function handleLocationVerification()
{
    global $pdo, $student_id;

    $class_id = $_POST['class_id'] ?? 0;
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    if (!$class_id || !$latitude || !$longitude) {
        echo json_encode(['success' => false, 'error' => 'Class ID and location are required']);
        return;
    }

    try {
        // Get class location
        $stmt = $pdo->prepare("
            SELECT c.*, r.latitude, r.longitude, r.name as room_name
            FROM classes c
            LEFT JOIN rooms r ON c.room_id = r.id
            WHERE c.id = ?
        ");
        $stmt->execute([$class_id]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$class) {
            echo json_encode(['success' => false, 'error' => 'Class not found']);
            return;
        }

        $location_valid = true;
        $distance = null;

        if ($class['latitude'] && $class['longitude']) {
            $distance = calculateDistance(
                $latitude,
                $longitude,
                $class['latitude'],
                $class['longitude']
            );
            $location_valid = $distance <= 150; // 150 meters for manual check-in

            // Log attempt
            $stmt = $pdo->prepare("
                INSERT INTO attendance_geolocation_logs
                (student_id, class_id, recorded_lat, recorded_lng, expected_lat, expected_lng, distance_meters, is_valid, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $student_id,
                $class_id,
                $latitude,
                $longitude,
                $class['latitude'],
                $class['longitude'],
                $distance,
                $location_valid ? 1 : 0,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        }

        echo json_encode([
            'success' => true,
            'valid' => $location_valid,
            'distance' => $distance ? round($distance, 2) : null,
            'message' => $location_valid
                ? 'Location verified successfully'
                : 'You are outside the allowed area'
        ]);
    } catch (Exception $e) {
        error_log("Location Verification Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to verify location']);
    }
}

/**
 * Calculate distance between two coordinates (Haversine formula)
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    $earth_radius = 6371000; // meters

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earth_radius * $c;
}

/**
 * Handle attendance correction request submission
 */
function handleCorrectionRequest()
{
    global $pdo, $student_id;

    $class_id = $_POST['class_id'] ?? 0;
    $attendance_date = $_POST['attendance_date'] ?? '';
    $requested_status = $_POST['requested_status'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $attendance_record_id = $_POST['attendance_record_id'] ?? null;

    if (!$class_id || !$attendance_date || !$requested_status || empty($reason)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        return;
    }

    // Validate date is not in the future
    if (strtotime($attendance_date) > time()) {
        echo json_encode(['success' => false, 'error' => 'Cannot request correction for future dates']);
        return;
    }

    try {
        // Get current status if record exists
        $current_status = null;
        if ($attendance_record_id) {
            $stmt = $pdo->prepare("SELECT status FROM attendance_records WHERE id = ? AND student_id = ?");
            $stmt->execute([$attendance_record_id, $student_id]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_status = $record['status'] ?? null;
        }

        // Handle file upload
        $document_path = null;
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/attendance_corrections/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

            if (in_array($file_ext, $allowed_exts) && $_FILES['document']['size'] <= 5242880) { // 5MB
                $filename = 'correction_' . $student_id . '_' . time() . '.' . $file_ext;
                $filepath = $upload_dir . $filename;

                if (move_uploaded_file($_FILES['document']['tmp_name'], $filepath)) {
                    $document_path = 'uploads/attendance_corrections/' . $filename;
                }
            }
        }

        // Check for existing pending request
        $stmt = $pdo->prepare("
            SELECT id FROM attendance_corrections
            WHERE student_id = ? AND class_id = ? AND attendance_date = ? AND status = 'pending'
        ");
        $stmt->execute([$student_id, $class_id, $attendance_date]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'You already have a pending correction for this date']);
            return;
        }

        // Insert correction request
        $stmt = $pdo->prepare("
            INSERT INTO attendance_corrections
            (student_id, attendance_record_id, class_id, attendance_date, current_status, requested_status, reason, supporting_documents)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $student_id,
            $attendance_record_id,
            $class_id,
            $attendance_date,
            $current_status,
            $requested_status,
            $reason,
            $document_path
        ]);

        $correction_id = $pdo->lastInsertId();

        // Log activity
        log_activity(
            $student_id,
            'correction_request',
            'attendance_corrections',
            $correction_id,
            "Requested correction for $attendance_date to $requested_status"
        );

        // Notify admin/teacher (would integrate with notification system)

        echo json_encode([
            'success' => true,
            'message' => 'Correction request submitted successfully',
            'correction_id' => $correction_id
        ]);
    } catch (Exception $e) {
        error_log("Correction Request Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to submit correction request']);
    }
}

/**
 * Get student's correction requests
 */
function getCorrectionRequests()
{
    global $pdo, $student_id;

    $status = $_GET['status'] ?? 'all';

    try {
        $sql = "
            SELECT ac.*, c.name as class_name, c.class_code,
                   u.first_name as reviewer_first, u.last_name as reviewer_last
            FROM attendance_corrections ac
            JOIN classes c ON ac.class_id = c.id
            LEFT JOIN users u ON ac.reviewed_by = u.id
            WHERE ac.student_id = ?
        ";

        $params = [$student_id];

        if ($status !== 'all') {
            $sql .= " AND ac.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY ac.created_at DESC LIMIT 50";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $corrections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'corrections' => $corrections]);
    } catch (Exception $e) {
        error_log("Get Corrections Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch corrections']);
    }
}

/**
 * Cancel a pending correction request
 */
function cancelCorrectionRequest()
{
    global $pdo, $student_id;

    $correction_id = $_POST['correction_id'] ?? 0;

    try {
        $stmt = $pdo->prepare("
            DELETE FROM attendance_corrections
            WHERE id = ? AND student_id = ? AND status = 'pending'
        ");
        $stmt->execute([$correction_id, $student_id]);

        if ($stmt->rowCount() > 0) {
            log_activity(
                $student_id,
                'cancel_correction',
                'attendance_corrections',
                $correction_id,
                "Cancelled correction request #$correction_id"
            );

            echo json_encode(['success' => true, 'message' => 'Correction request cancelled']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Cannot cancel this request']);
        }
    } catch (Exception $e) {
        error_log("Cancel Correction Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to cancel request']);
    }
}

/**
 * Get attendance analytics
 */
function getAttendanceAnalytics()
{
    global $pdo, $student_id;

    $period = $_GET['period'] ?? 'month'; // month, semester, year
    $class_id = $_GET['class_id'] ?? null;

    try {
        $date_filter = match ($period) {
            'week' => "DATE(ar.check_in_time) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            'month' => "DATE(ar.check_in_time) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            'semester' => "DATE(ar.check_in_time) >= DATE_SUB(CURDATE(), INTERVAL 120 DAY)",
            'year' => "DATE(ar.check_in_time) >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)",
            default => "DATE(ar.check_in_time) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
        };

        $class_filter = $class_id ? "AND ar.class_id = " . (int)$class_id : "";

        // Overall stats
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_records,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_count
            FROM attendance_records ar
            WHERE ar.student_id = ? AND $date_filter $class_filter
        ");
        $stmt->execute([$student_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Daily breakdown
        $stmt = $pdo->prepare("
            SELECT
                DATE(ar.check_in_time) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
            FROM attendance_records ar
            WHERE ar.student_id = ? AND $date_filter $class_filter
            GROUP BY DATE(ar.check_in_time)
            ORDER BY date DESC
            LIMIT 30
        ");
        $stmt->execute([$student_id]);
        $daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Class-wise breakdown
        $stmt = $pdo->prepare("
            SELECT
                c.id, c.name as class_name, c.class_code,
                COUNT(*) as total,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent,
                ROUND((SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as percentage
            FROM attendance_records ar
            JOIN classes c ON ar.class_id = c.id
            WHERE ar.student_id = ? AND $date_filter
            GROUP BY c.id
            ORDER BY percentage DESC
        ");
        $stmt->execute([$student_id]);
        $class_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = $stats['total_records'] ?? 0;
        $attendance_rate = $total > 0
            ? round((($stats['present_count'] + $stats['late_count']) / $total) * 100, 1)
            : 0;

        echo json_encode([
            'success' => true,
            'analytics' => [
                'period' => $period,
                'overall' => [
                    'total_records' => $total,
                    'present' => $stats['present_count'] ?? 0,
                    'absent' => $stats['absent_count'] ?? 0,
                    'late' => $stats['late_count'] ?? 0,
                    'excused' => $stats['excused_count'] ?? 0,
                    'attendance_rate' => $attendance_rate
                ],
                'daily_breakdown' => $daily_data,
                'class_breakdown' => $class_breakdown
            ]
        ]);
    } catch (Exception $e) {
        error_log("Analytics Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to generate analytics']);
    }
}

/**
 * Export attendance to calendar format (.ics)
 */
function exportToCalendar()
{
    global $pdo, $student_id;

    $class_id = $_GET['class_id'] ?? null;

    try {
        $class_filter = $class_id ? "AND c.id = " . (int)$class_id : "";

        $stmt = $pdo->prepare("
            SELECT c.*, ce.student_id
            FROM classes c
            JOIN class_enrollments ce ON c.id = ce.class_id
            WHERE ce.student_id = ? $class_filter
            ORDER BY c.day_of_week, c.start_time
        ");
        $stmt->execute([$student_id]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate iCal content
        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//Student Attendance System//EN\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";

        foreach ($classes as $class) {
            // Generate recurring events for each class
            $day_map = [1 => 'MO', 2 => 'TU', 3 => 'WE', 4 => 'TH', 5 => 'FR', 6 => 'SA', 7 => 'SU'];
            $byday = $day_map[$class['day_of_week']] ?? 'MO';

            $uid = md5($class['id'] . $student_id . time());
            $dtstart = date('Ymd\THis', strtotime($class['start_time']));
            $dtend = date('Ymd\THis', strtotime($class['end_time']));

            $ical .= "BEGIN:VEVENT\r\n";
            $ical .= "UID:$uid\r\n";
            $ical .= "DTSTART:$dtstart\r\n";
            $ical .= "DTEND:$dtend\r\n";
            $ical .= "RRULE:FREQ=WEEKLY;BYDAY=$byday\r\n";
            $ical .= "SUMMARY:" . $class['name'] . "\r\n";
            $ical .= "DESCRIPTION:Class Code: " . $class['class_code'] . "\r\n";
            $ical .= "LOCATION:" . ($class['room'] ?? 'TBA') . "\r\n";
            $ical .= "STATUS:CONFIRMED\r\n";
            $ical .= "END:VEVENT\r\n";
        }

        $ical .= "END:VCALENDAR\r\n";

        echo json_encode([
            'success' => true,
            'ical_data' => base64_encode($ical),
            'filename' => 'my_schedule_' . date('Y-m-d') . '.ics'
        ]);
    } catch (Exception $e) {
        error_log("Calendar Export Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to export calendar']);
    }
}

/**
 * Set class reminder
 */
function setClassReminder()
{
    global $pdo, $student_id;

    $class_id = $_POST['class_id'] ?? 0;
    $reminder_time = $_POST['reminder_time'] ?? 15; // Minutes before
    $notify_email = $_POST['notify_email'] ?? 0;
    $notify_push = $_POST['notify_push'] ?? 1;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO class_reminders (student_id, class_id, reminder_time, notify_email, notify_push)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                is_enabled = 1,
                notify_email = VALUES(notify_email),
                notify_push = VALUES(notify_push)
        ");
        $stmt->execute([$student_id, $class_id, $reminder_time, $notify_email, $notify_push]);

        echo json_encode(['success' => true, 'message' => 'Reminder set successfully']);
    } catch (Exception $e) {
        error_log("Set Reminder Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to set reminder']);
    }
}

/**
 * Get class reminders
 */
function getClassReminders()
{
    global $pdo, $student_id;

    try {
        $stmt = $pdo->prepare("
            SELECT cr.*, c.name as class_name, c.class_code, c.start_time
            FROM class_reminders cr
            JOIN classes c ON cr.class_id = c.id
            WHERE cr.student_id = ? AND cr.is_enabled = 1
            ORDER BY c.day_of_week, c.start_time
        ");
        $stmt->execute([$student_id]);
        $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'reminders' => $reminders]);
    } catch (Exception $e) {
        error_log("Get Reminders Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch reminders']);
    }
}

/**
 * Delete reminder
 */
function deleteReminder()
{
    global $pdo, $student_id;

    $reminder_id = $_POST['reminder_id'] ?? 0;

    try {
        $stmt = $pdo->prepare("
            DELETE FROM class_reminders WHERE id = ? AND student_id = ?
        ");
        $stmt->execute([$reminder_id, $student_id]);

        echo json_encode(['success' => true, 'message' => 'Reminder deleted']);
    } catch (Exception $e) {
        error_log("Delete Reminder Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to delete reminder']);
    }
}

/**
 * Get attendance streak
 */
function getAttendanceStreak()
{
    global $pdo, $student_id;

    try {
        $stmt = $pdo->prepare("
            SELECT
                DATE(check_in_time) as attendance_date,
                COUNT(*) as classes_attended
            FROM attendance_records
            WHERE student_id = ? AND status IN ('present', 'late')
            GROUP BY DATE(check_in_time)
            ORDER BY attendance_date DESC
            LIMIT 365
        ");
        $stmt->execute([$student_id]);
        $attendance_days = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $current_streak = 0;
        $longest_streak = 0;
        $temp_streak = 0;
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        foreach ($attendance_days as $index => $day) {
            if ($index === 0) {
                $temp_streak = 1;
                if ($day['attendance_date'] === date('Y-m-d') || $day['attendance_date'] === $yesterday) {
                    $current_streak = 1;
                }
            } else {
                $prev_date = $attendance_days[$index - 1]['attendance_date'];
                $expected_date = date('Y-m-d', strtotime($prev_date . ' -1 day'));

                if ($day['attendance_date'] === $expected_date) {
                    $temp_streak++;
                    if ($index < 2) $current_streak = $temp_streak;
                } else {
                    $longest_streak = max($longest_streak, $temp_streak);
                    $temp_streak = 1;
                }
            }
        }

        $longest_streak = max($longest_streak, $temp_streak);

        echo json_encode([
            'success' => true,
            'streak' => [
                'current' => $current_streak,
                'longest' => $longest_streak,
                'total_days' => count($attendance_days)
            ]
        ]);
    } catch (Exception $e) {
        error_log("Streak Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to calculate streak']);
    }
}

/**
 * Get today's classes
 */
function getTodayClasses()
{
    global $pdo, $student_id;

    try {
        $today_dow = date('N'); // 1 (Monday) to 7 (Sunday)

        $stmt = $pdo->prepare("
            SELECT c.*, t.first_name as teacher_first, t.last_name as teacher_last,
                   ar.id as attendance_id, ar.status as attendance_status, ar.check_in_time
            FROM classes c
            JOIN class_enrollments ce ON c.id = ce.class_id
            LEFT JOIN teachers t ON c.teacher_id = t.id
            LEFT JOIN attendance_records ar ON c.id = ar.class_id
                AND ar.student_id = ce.student_id
                AND DATE(ar.check_in_time) = CURDATE()
            WHERE ce.student_id = ? AND c.day_of_week = ?
            ORDER BY c.start_time
        ");
        $stmt->execute([$student_id, $today_dow]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'classes' => $classes]);
    } catch (Exception $e) {
        error_log("Today Classes Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch classes']);
    }
}

/**
 * Get attendance history with filters
 */
function getAttendanceHistory()
{
    global $pdo, $student_id;

    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-t');
    $class_id = $_GET['class_id'] ?? null;
    $status = $_GET['status'] ?? null;

    try {
        $sql = "
            SELECT ar.*, c.name as class_name, c.class_code, c.room,
                   u.first_name as marked_by_first, u.last_name as marked_by_last
            FROM attendance_records ar
            JOIN classes c ON ar.class_id = c.id
            LEFT JOIN users u ON ar.marked_by = u.id
            WHERE ar.student_id = ?
            AND DATE(ar.check_in_time) BETWEEN ? AND ?
        ";

        $params = [$student_id, $start_date, $end_date];

        if ($class_id) {
            $sql .= " AND ar.class_id = ?";
            $params[] = $class_id;
        }

        if ($status) {
            $sql .= " AND ar.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY ar.check_in_time DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'records' => $records, 'count' => count($records)]);
    } catch (Exception $e) {
        error_log("Attendance History Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to fetch attendance history']);
    }
}
