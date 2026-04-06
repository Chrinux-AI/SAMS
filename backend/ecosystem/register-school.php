<?php

/**
 * Ecosystem — Register School Endpoint
 *
 * Self-provisioning endpoint for rapid school onboarding.
 * Creates: tenant namespace, admin account, default roles, AI config, branding.
 */
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/database.php';
require_once INCLUDES_PATH . '/functions.php';
require_once BASE_PATH . '/app/bootstrap.php';

header('Content-Type: application/json');

// Auth: admin session or API key
session_start();
$authenticated = false;

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
  $authenticated = true;
} elseif (isset($_SERVER['HTTP_X_ECOSYSTEM_KEY'])) {
  $key = $_SERVER['HTTP_X_ECOSYSTEM_KEY'];
  $expected = getenv('ECOSYSTEM_API_KEY') ?: '';
  if ($expected && hash_equals($expected, $key)) {
    $authenticated = true;
  }
}

if (!$authenticated) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'POST required']);
  exit;
}

$schoolName = trim($_POST['school_name'] ?? '');
$domain = trim($_POST['domain'] ?? '');

if (empty($schoolName)) {
  http_response_code(400);
  echo json_encode(['error' => 'school_name is required']);
  exit;
}

// Sanitize
$schoolName = htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8');
$domain = preg_replace('/[^a-zA-Z0-9.\-]/', '', $domain);

$options = [];
if (isset($_POST['primary_color'])) $options['primary_color'] = preg_replace('/[^#a-fA-F0-9]/', '', $_POST['primary_color']);
if (isset($_POST['tagline'])) $options['tagline'] = htmlspecialchars(trim($_POST['tagline']), ENT_QUOTES, 'UTF-8');

try {
  $result = DeploymentManager::deploy($schoolName, $domain, $options);
  echo json_encode($result);
} catch (\Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Deployment failed']);
}
