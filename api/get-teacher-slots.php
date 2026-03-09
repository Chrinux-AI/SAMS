<?php

/**
 * Get Teacher Meeting Slots API
 */

require_once '../includes/session-handler.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$teacher_id = intval($_GET['teacher_id'] ?? 0);

if (!$teacher_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid teacher ID']);
    exit;
}

$slots = db()->fetchAll("
    SELECT id, day_of_week,
           DATE_FORMAT(start_time, '%h:%i %p') as start_time,
           DATE_FORMAT(end_time, '%h:%i %p') as end_time
    FROM meeting_slots
    WHERE teacher_id = ? AND is_active = 1
    ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), start_time
", [$teacher_id]);

echo json_encode(['success' => true, 'slots' => $slots]);
