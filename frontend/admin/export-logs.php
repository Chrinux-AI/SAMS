<?php

/**
 * Export Audit Logs (CSV)
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_admin('../login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  exit;
}

$csrf = (string)($_GET['csrf_token'] ?? '');
if (!verify_csrf_token($csrf)) {
  http_response_code(403);
  exit('Invalid CSRF token');
}

if (!table_exists('audit_logs')) {
  header('Location: audit-logs.php?error=missing_audit_logs');
  exit;
}

$action_filter = trim((string)($_GET['action'] ?? ''));
$user_filter = trim((string)($_GET['user_id'] ?? ''));
$date_from = trim((string)($_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'))));
$date_to = trim((string)($_GET['date_to'] ?? date('Y-m-d')));
$search = trim((string)($_GET['search'] ?? ''));

if (($date_from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) || ($date_to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))) {
  http_response_code(422);
  exit('Invalid date filter format');
}

$where = [];
$params = [];

if ($action_filter !== '') {
  $where[] = 'al.action = ?';
  $params[] = $action_filter;
}

if ($user_filter !== '' && ctype_digit($user_filter)) {
  $where[] = 'al.user_id = ?';
  $params[] = (int)$user_filter;
}

if ($date_from !== '' && $date_to !== '') {
  $where[] = 'DATE(al.created_at) BETWEEN ? AND ?';
  $params[] = $date_from;
  $params[] = $date_to;
}

if ($search !== '') {
  $where[] = '(al.description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR al.ip_address LIKE ?)';
  $searchTerm = '%' . $search . '%';
  $params[] = $searchTerm;
  $params[] = $searchTerm;
  $params[] = $searchTerm;
  $params[] = $searchTerm;
  $params[] = $searchTerm;
}

$where_clause = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$logs = db()->fetchAll(
  "SELECT al.id, al.user_id, al.action, al.description, al.ip_address, al.created_at,
            CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS user_name,
            u.email, u.role
     FROM audit_logs al
     LEFT JOIN users u ON al.user_id = u.id
     {$where_clause}
     ORDER BY al.created_at DESC",
  $params
) ?: [];

$filename = 'audit_logs_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$output = fopen('php://output', 'w');
if ($output === false) {
  exit;
}

fputcsv($output, ['ID', 'User ID', 'User Name', 'Email', 'Role', 'Action', 'Description', 'IP Address', 'Created At']);

foreach ($logs as $log) {
  fputcsv($output, [
    (int)($log['id'] ?? 0),
    (int)($log['user_id'] ?? 0),
    trim((string)($log['user_name'] ?? '')),
    (string)($log['email'] ?? ''),
    (string)($log['role'] ?? ''),
    (string)($log['action'] ?? ''),
    (string)($log['description'] ?? ''),
    (string)($log['ip_address'] ?? ''),
    (string)($log['created_at'] ?? ''),
  ]);
}

fclose($output);
exit;
