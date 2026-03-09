<?php

/**
 * Download Resource API
 */

require_once '../includes/session-handler.php';
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$resource_id = intval($_GET['id'] ?? 0);

$resource = db()->fetchOne("
    SELECT * FROM teacher_resources
    WHERE id = ? AND (is_public = 1 OR teacher_id = ?)
", [$resource_id, $_SESSION['user_id']]);

if (!$resource || !file_exists($resource['file_path'])) {
    die('Resource not found');
}

// Increment download count
db()->execute("UPDATE teacher_resources SET download_count = download_count + 1 WHERE id = ?", [$resource_id]);

// Force download
header('Content-Type: ' . $resource['file_type']);
header('Content-Disposition: attachment; filename="' . $resource['file_name'] . '"');
header('Content-Length: ' . filesize($resource['file_path']));
readfile($resource['file_path']);
exit;
