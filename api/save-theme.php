<?php

/**
 * Save Theme Preference API
 * Stores the user's selected theme in the database
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

if (!is_logged_in()) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Not authenticated']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$input = is_array($input) ? $input : [];

$csrf = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token((string)$csrf)) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'CSRF validation failed']);
  exit;
}

$theme = $input['theme'] ?? '';

$allowed_themes = ['light', 'dark'];
if (!in_array($theme, $allowed_themes, true)) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Invalid theme']);
  exit;
}

try {
  $user_id = $_SESSION['user_id'];
  if (table_has_column('users', 'theme')) {
    db()->query("UPDATE users SET theme = ? WHERE id = ?", [$theme, $user_id]);
  }
  echo json_encode(['success' => true, 'theme' => $theme]);
} catch (Exception $e) {
  error_log("Save theme error: " . $e->getMessage());
  echo json_encode(['success' => true, 'theme' => $theme]);
}
