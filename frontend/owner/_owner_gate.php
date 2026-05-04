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
  $dashboardPath = get_role_dashboard_path($_ownerRole);
  if (!preg_match('#^https?://#i', $dashboardPath)) {
    $dashboardPath = site_url($dashboardPath);
  }
  header('Location: ' . $dashboardPath);
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
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
  }
  require $target;
  exit;
}
