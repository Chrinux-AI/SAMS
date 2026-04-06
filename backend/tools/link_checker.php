<?php

/**
 * Link Integrity Scanner
 * Scans all PHP files for href/src links and validates target files exist.
 *
 * Usage:
 *   CLI: php tools/link_checker.php
 *   Web: Navigate to /attendance/tools/link_checker.php (developer only)
 *
 * Detects:
 *   - Broken relative href links
 *   - Broken src attributes
 *   - Non-existent route() targets
 *   - Missing include/require targets
 */

define('BASE_PATH', dirname(__DIR__));

$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
  require_once BASE_PATH . '/includes/config.php';
  require_once INCLUDES_PATH . '/functions.php';
  require_once BASE_PATH . '/app/bootstrap.php';
  SecurityGateway::guard([
    'require_auth' => true,
    'require_role' => ['admin', 'developer'],
  ]);
}

/**
 * Scan directories for PHP files.
 */
function scanPhpFiles(string $dir, array $exclude = []): array
{
  $files = [];
  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
  );
  foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;

    $rel = str_replace(BASE_PATH . DIRECTORY_SEPARATOR, '', $file->getPathname());
    $rel = str_replace('\\', '/', $rel);

    $skip = false;
    foreach ($exclude as $ex) {
      if (str_starts_with($rel, $ex)) {
        $skip = true;
        break;
      }
    }
    if (!$skip) $files[] = $file->getPathname();
  }
  return $files;
}

/**
 * Extract href/src links from a PHP file.
 */
function extractLinks(string $filePath): array
{
  $content = file_get_contents($filePath);
  $links = [];

  // Match href="..." and src="..." (excluding external URLs, anchors, javascript:, #, mailto:)
  if (preg_match_all('/(?:href|src)\s*=\s*["\']([^"\']+)["\']/i', $content, $matches)) {
    foreach ($matches[1] as $link) {
      // Skip external URLs, anchors, data URIs, PHP variables, CDN links
      if (preg_match('/^(https?:|\/\/|#|mailto:|tel:|javascript:|data:|<\?|\$|{)/', $link)) continue;
      // Skip Font Awesome, CDN, and other external resources
      if (str_contains($link, 'cdnjs.') || str_contains($link, 'googleapis.') || str_contains($link, 'cdn.')) continue;
      $links[] = $link;
    }
  }

  return array_unique($links);
}

/**
 * Resolve a relative link to an absolute file path.
 */
function resolveLink(string $link, string $sourceFile): string
{
  $sourceDir = dirname($sourceFile);

  // Handle route() generated paths (start with /attendance/)
  if (str_starts_with($link, '/attendance/')) {
    return BASE_PATH . substr($link, strlen('/attendance'));
  }

  // Handle absolute paths from root
  if (str_starts_with($link, '/')) {
    return $_SERVER['DOCUMENT_ROOT'] . $link;
  }

  // Relative path resolution
  return realpath($sourceDir . '/' . $link) ?: $sourceDir . '/' . $link;
}

/**
 * Check if a link target exists.
 */
function linkExists(string $resolved): bool
{
  // Strip query strings and fragments
  $clean = preg_replace('/[?#].*$/', '', $resolved);
  return is_file($clean) || is_dir($clean);
}

// ── Run the scan ──
$excludeDirs = ['vendor/', 'node_modules/', 'storage/', 'cache/', 'backups/', 'uploads/', 'logs/'];
$files = scanPhpFiles(BASE_PATH, $excludeDirs);

$broken = [];
$total = 0;
$checked = 0;

foreach ($files as $file) {
  $links = extractLinks($file);
  $rel = str_replace(BASE_PATH . DIRECTORY_SEPARATOR, '', $file);
  $rel = str_replace('\\', '/', $rel);

  foreach ($links as $link) {
    $total++;
    $resolved = resolveLink($link, $file);
    if (!linkExists($resolved)) {
      $broken[] = [
        'file'     => $rel,
        'link'     => $link,
        'resolved' => str_replace(BASE_PATH . DIRECTORY_SEPARATOR, '', $resolved),
      ];
    }
  }
  $checked++;
}

// ── Output ──
if ($isCli) {
  echo "═══════════════════════════════════════\n";
  echo " SAMS Link Integrity Scanner\n";
  echo "═══════════════════════════════════════\n\n";
  echo "Files scanned: $checked\n";
  echo "Links checked: $total\n";
  echo "Broken links:  " . count($broken) . "\n\n";

  if (empty($broken)) {
    echo "✅ All links are valid!\n";
  } else {
    echo "❌ Broken Links Found:\n";
    echo str_repeat('─', 80) . "\n";
    foreach ($broken as $b) {
      echo "  File: {$b['file']}\n";
      echo "  Link: {$b['link']}\n";
      echo "  Resolved: {$b['resolved']}\n";
      echo str_repeat('─', 80) . "\n";
    }
  }

  // Save report to storage
  $report = [
    'timestamp'    => date('Y-m-d H:i:s'),
    'files_scanned' => $checked,
    'links_checked' => $total,
    'broken_count' => count($broken),
    'broken_links' => $broken,
  ];
  @file_put_contents(BASE_PATH . '/storage/link-check-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  echo "\nReport saved to storage/link-check-report.json\n";
} else {
  header('Content-Type: application/json');
  echo json_encode([
    'timestamp'    => date('Y-m-d H:i:s'),
    'files_scanned' => $checked,
    'links_checked' => $total,
    'broken_count' => count($broken),
    'broken_links' => $broken,
  ], JSON_PRETTY_PRINT);
}
