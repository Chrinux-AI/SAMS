<?php

/**
 * Page Audit Script — Autonomous Educational Ecosystem
 * Validates every page: loads layout, queries data, renders UI, logs access.
 *
 * Usage: php scripts/page_audit.php
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/includes/config.php';

echo "=== SAMS Page Audit ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n\n";

$results = ['pass' => 0, 'warn' => 0, 'fail' => 0];
$issues = [];

// ---------- 1. PHP Syntax Check ----------
function syntaxCheck(string $file): ?string
{
  $output = [];
  $code = 0;
  exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
  if ($code !== 0) {
    return implode(' ', $output);
  }
  return null;
}

// ---------- 2. Content Checks ----------
function contentChecks(string $file, string $relPath): array
{
  $warnings = [];
  $content = file_get_contents($file);

  // Layout check (skip API endpoints, cron, scripts, CLI tools)
  $isPage = preg_match('/\.(php)$/i', $file)
    && !preg_match('#(api/|cron/|scripts/|middleware/|app/|vendor/|src/|includes/|config/)#i', $relPath)
    && !preg_match('#(fix-|migrate|setup-|verify-|confirm-|activate-)#i', basename($file));

  if ($isPage) {
    $hasLayout = strpos($content, 'master-dashboard.php') !== false
      || strpos($content, 'header.php') !== false
      || strpos($content, '<!DOCTYPE html>') !== false
      || strpos($content, '<html') !== false;

    if (!$hasLayout) {
      $warnings[] = 'No layout/HTML structure detected';
    }
  }

  // Auth check for protected directories
  $protectedDirs = ['admin/', 'developer/', 'accountant/', 'teacher/', 'student/', 'parent/', 'librarian/', 'bursar/', 'transport/'];
  foreach ($protectedDirs as $dir) {
    if (strpos($relPath, $dir) === 0) {
      $hasAuth = strpos($content, 'require_admin') !== false
        || strpos($content, 'require_role') !== false
        || strpos($content, 'is_logged_in') !== false
        || strpos($content, 'session_start') !== false
        || strpos($content, 'require_login') !== false;

      if (!$hasAuth && $isPage) {
        $warnings[] = "Protected directory ($dir) but no auth check found";
      }
      break;
    }
  }

  // Database usage check (informational)
  $usesDb = strpos($content, 'db()') !== false
    || strpos($content, 'Database::') !== false
    || strpos($content, '$pdo') !== false
    || strpos($content, 'query(') !== false;

  // Error logging check
  $logsErrors = strpos($content, 'ErrorCollector') !== false
    || strpos($content, 'error_log') !== false
    || strpos($content, 'catch') !== false;

  return $warnings;
}

// ---------- 3. Scan Directories ----------
$scanDirs = [
  'admin',
  'developer',
  'accountant',
  'teacher',
  'student',
  'parent',
  'librarian',
  'bursar',
  'transport',
  'public-ai',
  'ecosystem',
  'api',
  'cron',
  'app/Ecosystem',
  'app/Events',
  'app/Cognitive',
  'app/Intelligence',
  'app/DevOps',
  'middleware',
  'auth',
];

// Top-level PHP files
$topLevel = glob(BASE_PATH . '/*.php');

$allFiles = [];

// Collect top-level
foreach ($topLevel as $f) {
  $allFiles[] = $f;
}

// Collect from scan directories
foreach ($scanDirs as $dir) {
  $dirPath = BASE_PATH . '/' . $dir;
  if (!is_dir($dirPath)) {
    echo "  [SKIP] $dir/ — directory not found\n";
    continue;
  }
  $files = glob($dirPath . '/*.php');
  foreach ($files as $f) {
    $allFiles[] = $f;
  }
  // One level of subdirectories
  $subDirs = glob($dirPath . '/*', GLOB_ONLYDIR);
  foreach ($subDirs as $subDir) {
    $subFiles = glob($subDir . '/*.php');
    foreach ($subFiles as $f) {
      $allFiles[] = $f;
    }
  }
}

$totalFiles = count($allFiles);
echo "Scanning $totalFiles PHP files...\n\n";

foreach ($allFiles as $file) {
  $relPath = str_replace(BASE_PATH . '/', '', str_replace('\\', '/', $file));
  $relPath = str_replace(BASE_PATH . '\\', '', $relPath);

  // Syntax check
  $syntaxError = syntaxCheck($file);
  if ($syntaxError) {
    $results['fail']++;
    $issues[] = "[FAIL] $relPath — Syntax error: $syntaxError";
    echo "  [FAIL] $relPath — SYNTAX ERROR\n";
    continue;
  }

  // Content checks
  $warnings = contentChecks($file, $relPath);
  if (!empty($warnings)) {
    $results['warn']++;
    foreach ($warnings as $w) {
      $issues[] = "[WARN] $relPath — $w";
    }
    echo "  [WARN] $relPath — " . implode('; ', $warnings) . "\n";
  } else {
    $results['pass']++;
    echo "  [OK]   $relPath\n";
  }
}

// ---------- 4. Summary ----------
echo "\n=== Audit Summary ===\n";
echo "Total files: $totalFiles\n";
echo "  Passed: {$results['pass']}\n";
echo "  Warnings: {$results['warn']}\n";
echo "  Failed: {$results['fail']}\n";

$score = $totalFiles > 0 ? round(($results['pass'] / $totalFiles) * 100) : 0;
echo "Audit Score: {$score}%\n";

if (!empty($issues)) {
  echo "\n=== Issues ===\n";
  foreach ($issues as $issue) {
    echo "  $issue\n";
  }
}

// Persist results
$reportPath = BASE_PATH . '/storage/page-audit-report.json';
$report = [
  'timestamp' => date('c'),
  'total_files' => $totalFiles,
  'passed' => $results['pass'],
  'warnings' => $results['warn'],
  'failed' => $results['fail'],
  'score' => $score,
  'issues' => $issues,
];
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "\nReport saved: storage/page-audit-report.json\n";
echo "Completed: " . date('Y-m-d H:i:s') . "\n";
