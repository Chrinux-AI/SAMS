<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$class_id = (int)($_GET['class_id'] ?? 0);
if (!$class_id) {
  echo json_encode(['error' => 'Missing class_id', 'schedules' => []]);
  exit;
}

$schedules = ClassRepository::getSchedules($class_id);
echo json_encode(['schedules' => $schedules]);
