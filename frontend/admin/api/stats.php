<?php

/**
 * Real-time Stats API
 */

session_start();

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    // Calculate online users (users with activity in last 15 minutes)
    $online_users = db()->count('users', 'last_login >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND status = ?', ['active']);

    // Today's check-ins
    $today_checkins = db()->count('attendance_records', 'DATE(check_in_time) = CURDATE()');

    // Yesterday's check-ins for comparison
    $yesterday_checkins = db()->count('attendance_records', 'DATE(check_in_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)');

    // Calculate percentage change
    $checkin_change = 0;
    if ($yesterday_checkins > 0) {
        $checkin_change = round((($today_checkins - $yesterday_checkins) / $yesterday_checkins) * 100, 1);
    } elseif ($today_checkins > 0) {
        $checkin_change = 100;
    }

    // System health calculation (simple version)
    $total_users = db()->count('users');
    $active_users = db()->count('users', 'status = ?', ['active']);
    $system_health = $total_users > 0 ? round(($active_users / $total_users) * 100) : 100;

    // Additional metrics for advanced analytics
    $total_classes = db()->count('classes', 'status = ?', ['active']);
    $total_students = db()->count('students', 'status = ?', ['active']);
    $total_teachers = db()->count('teachers', 'status = ?', ['active']);

    // Peak hours analysis
    $current_hour = date('H');
    $current_hour_checkins = db()->count('attendance_records', 'HOUR(check_in_time) = ? AND DATE(check_in_time) = CURDATE()', [$current_hour]);

    // Weekly trend
    $weekly_checkins = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $count = db()->count('attendance_records', 'DATE(check_in_time) = ?', [$date]);
        $weekly_checkins[] = [
            'date' => $date,
            'count' => $count,
            'day' => date('D', strtotime($date))
        ];
    }

    // Performance metrics
    $avg_daily_checkins = array_sum(array_column($weekly_checkins, 'count')) / 7;
    $performance_score = $today_checkins > $avg_daily_checkins ? 'above_average' : 'below_average';

    echo json_encode([
        'success' => true,
        'timestamp' => time(),
        'online_users' => $online_users,
        'today_checkins' => $today_checkins,
        'checkin_change' => $checkin_change,
        'system_health' => $system_health,
        'advanced_metrics' => [
            'total_classes' => $total_classes,
            'total_students' => $total_students,
            'total_teachers' => $total_teachers,
            'current_hour_checkins' => $current_hour_checkins,
            'avg_daily_checkins' => round($avg_daily_checkins, 1),
            'performance_score' => $performance_score,
            'weekly_trend' => $weekly_checkins
        ],
        'system_info' => [
            'php_version' => PHP_VERSION,
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB'
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch stats: ' . $e->getMessage()
    ]);
}
