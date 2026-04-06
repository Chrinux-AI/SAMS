<?php

/**
 * Class AJAX API — Returns class data for edit modal population.
 *
 * GET /api/class.php?id=6
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';

header('Content-Type: application/json');

require_admin('../login.php');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid class ID']);
  exit;
}

try {
  ClassController::getJson($id);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Failed to fetch class data']);
}
