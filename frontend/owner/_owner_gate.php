<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

require_login('../login.php');

$_ownerRole = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
if ($_ownerRole !== 'owner') {
  header('Location: ' . get_role_dashboard_path($_ownerRole));
  exit;
}

function owner_load_admin_page(string $adminFile): void
{
  $safe = basename($adminFile);
  $target = '../admin/' . $safe;
  if (!is_file($target)) {
    http_response_code(404);
    echo 'Owner page target not found: ' . htmlspecialchars($safe);
    exit;
  }
  require $target;
  exit;
}
