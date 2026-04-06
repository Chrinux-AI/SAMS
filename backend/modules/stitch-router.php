<?php

/**
 * Stitch Router
 * Resolves a stitch screen slug to a real SAMS backend route.
 * URL: /attendance/modules/stitch-router.php?screen=<slug>
 */

$map = require __DIR__ . '/stitch-map.php';
$screen = trim((string)($_GET['screen'] ?? ''));

if ($screen === '' || !isset($map[$screen])) {
  http_response_code(404);
  header('Content-Type: application/json');
  echo json_encode([
    'success' => false,
    'message' => 'Unknown Stitch screen route.',
    'screen' => $screen
  ]);
  exit;
}

header('Location: ' . $map[$screen]);
exit;
