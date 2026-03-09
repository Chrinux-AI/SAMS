<?php

/**
 * Forum Report API
 */

require_once '../includes/session-handler.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? '';
$id = intval($input['id'] ?? 0);
$reason = trim($input['reason'] ?? '');

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Reason is required']);
    exit;
}

try {
    if ($type === 'thread') {
        db()->execute("
            INSERT INTO forum_reports (reporter_id, thread_id, reason)
            VALUES (?, ?, ?)
        ", [$_SESSION['user_id'], $id, $reason]);

        db()->execute("UPDATE forum_threads SET is_reported = 1 WHERE id = ?", [$id]);
    } elseif ($type === 'post') {
        db()->execute("
            INSERT INTO forum_reports (reporter_id, post_id, reason)
            VALUES (?, ?, ?)
        ", [$_SESSION['user_id'], $id, $reason]);

        db()->execute("UPDATE forum_posts SET is_reported = 1 WHERE id = ?", [$id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid type']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
