<?php

/**
 * Accountant Module Router
 * Acts as the single entry point for accountant views.
 *
 * Usage: /attendance/frontend/accountant/index.php?page=filename
 */

$page = $_GET['page'] ?? 'dashboard';

// Allowed accountant views to prevent directory traversal
$allowed_pages = [
  'dashboard',
  'team-selection',
  'ledger',
  'expenses',
  'income',
  'payroll',
  'budget',
  'project-goals',
  'balance-sheet',
  'profit-loss',
  'tax-reports',
  'audit-trail',
  'reports',
  'settings'
  ,
  'wallets'
];

if (!in_array($page, $allowed_pages)) {
  $page = 'dashboard';
}

require_once __DIR__ . '/' . $page . '.php';
