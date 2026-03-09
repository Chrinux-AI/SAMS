<?php

/**
 * Real-time Notification System
 */

session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get recent notifications based on user role
$notifications = [];

if ($user_role === 'admin') {
    // Admin gets all system notifications
    $recent_registrations = db()->fetchAll("
        SELECT COUNT(*) as count FROM users
        WHERE status = 'pending' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");

    $recent_checkins = db()->fetchAll("
        SELECT COUNT(*) as count FROM attendance_records
        WHERE check_in_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");

    if ($recent_registrations[0]['count'] > 0) {
        $notifications[] = [
            'type' => 'info',
            'title' => 'New Registrations',
            'message' => $recent_registrations[0]['count'] . ' new user(s) awaiting approval',
            'icon' => 'user-plus',
            'time' => 'Just now',
            'action' => 'registrations.php'
        ];
    }

    if ($recent_checkins[0]['count'] > 0) {
        $notifications[] = [
            'type' => 'success',
            'title' => 'Recent Activity',
            'message' => $recent_checkins[0]['count'] . ' student(s) checked in recently',
            'icon' => 'check-circle',
            'time' => 'Last hour',
            'action' => 'attendance.php'
        ];
    }

    // System health notifications
    $total_users = db()->count('users', 'status = ?', ['active']);
    if ($total_users > 100) {
        $notifications[] = [
            'type' => 'warning',
            'title' => 'System Scale',
            'message' => 'High user count detected. Consider performance optimization.',
            'icon' => 'server',
            'time' => '5 min ago',
            'action' => 'analytics.php'
        ];
    }
} elseif ($user_role === 'teacher') {
    // Teachers get class-related notifications
    $my_classes = db()->fetchAll("
        SELECT c.name, COUNT(ar.id) as recent_checkins
        FROM classes c
        JOIN teachers t ON t.id = c.teacher_id
        LEFT JOIN attendance_records ar ON c.id = ar.class_id
            AND ar.check_in_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        WHERE t.user_id = ?
        GROUP BY c.id
    ", [$user_id]);

    foreach ($my_classes as $class) {
        if ($class['recent_checkins'] > 0) {
            $notifications[] = [
                'type' => 'info',
                'title' => $class['name'],
                'message' => $class['recent_checkins'] . ' student(s) checked in',
                'icon' => 'chalkboard-teacher',
                'time' => 'Recent',
                'action' => 'attendance.php'
            ];
        }
    }
} else {
    // Students get personal notifications
    $my_checkins = db()->fetchAll("
        SELECT c.name, ar.check_in_time
        FROM attendance_records ar
        JOIN classes c ON ar.class_id = c.id
        JOIN students s ON ar.student_id = s.id
        WHERE s.created_by = ? AND DATE(ar.check_in_time) = CURDATE()
        ORDER BY ar.check_in_time DESC
    ", [$user_id]);

    foreach ($my_checkins as $checkin) {
        $notifications[] = [
            'type' => 'success',
            'title' => 'Attendance Recorded',
            'message' => 'Checked into ' . $checkin['name'],
            'icon' => 'check',
            'time' => date('h:i A', strtotime($checkin['check_in_time'])),
            'action' => null
        ];
    }
}

// Add some AI-powered suggestions
if ($user_role === 'admin') {
    $notifications[] = [
        'type' => 'ai',
        'title' => 'AI Suggestion',
        'message' => 'Consider implementing automated attendance reminders for better engagement.',
        'icon' => 'robot',
        'time' => 'AI Insight',
        'action' => 'analytics.php'
    ];
}

echo json_encode([
    'notifications' => $notifications,
    'timestamp' => time(),
    'unread_count' => count($notifications)
]);
