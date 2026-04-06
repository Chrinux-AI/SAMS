<?php

/**
 * Project Link Repair Script
 * Automatically detects and fixes common link issues across the codebase.
 *
 * Usage: php tools/link_repair.php [--dry-run]
 *
 * Repairs:
 *   - Relative ../includes paths → constant-based paths (INCLUDES_PATH, BASE_PATH)
 *   - Inconsistent sidebar includes → unified include
 *   - Common href patterns → route() calls where possible
 */

define('BASE_PATH', dirname(__DIR__));

$dryRun = in_array('--dry-run', $argv ?? []);

echo "═══════════════════════════════════════\n";
echo " SAMS Link Repair Script\n";
echo " Mode: " . ($dryRun ? 'DRY RUN (no changes)' : 'LIVE — Applying fixes') . "\n";
echo "═══════════════════════════════════════\n\n";

$fixes = 0;
$filesModified = 0;
$report = [];

/**
 * Scan PHP files, excluding vendor/node_modules/cache.
 */
function getPhpFiles(string $dir): array
{
  $files = [];
  $exclude = ['vendor', 'node_modules', 'cache', 'backups', 'storage', 'uploads', 'logs', '.git'];
  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
  );
  foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = $file->getPathname();
    foreach ($exclude as $ex) {
      if (str_contains(str_replace('\\', '/', $path), "/$ex/")) continue 2;
    }
    $files[] = $path;
  }
  return $files;
}

// ── Repair Rules ──

$rules = [
  // Fix: include '../includes/sidebar-nav.php' → include BASE_PATH . '/includes/sidebar-nav.php'
  [
    'name' => 'Sidebar include normalization',
    'pattern' => "/(?:require_once|require|include_once|include)\s+['\"]\.\.\/includes\/sidebar-nav\.php['\"]/",
    'replacement' => "include BASE_PATH . '/includes/sidebar-nav.php'",
  ],
  // Fix: include '../../includes/sidebar-nav.php' (from subdirectories)
  [
    'name' => 'Sidebar include from subdirectory',
    'pattern' => "/(?:require_once|require|include_once|include)\s+['\"]\.\.\/\.\.\/includes\/sidebar-nav\.php['\"]/",
    'replacement' => "include BASE_PATH . '/includes/sidebar-nav.php'",
  ],
  // Fix: include '../includes/functions.php' → require_once INCLUDES_PATH . '/functions.php'
  [
    'name' => 'Functions include normalization',
    'pattern' => "/(?:require_once|require|include_once|include)\s+['\"]\.\.\/includes\/functions\.php['\"]/",
    'replacement' => "require_once INCLUDES_PATH . '/functions.php'",
  ],
  // Fix: include '../../includes/functions.php'
  [
    'name' => 'Functions include from subdirectory',
    'pattern' => "/(?:require_once|require|include_once|include)\s+['\"]\.\.\/\.\.\/includes\/functions\.php['\"]/",
    'replacement' => "require_once INCLUDES_PATH . '/functions.php'",
  ],
  // Fix: require_once '../includes/config.php' → require_once __DIR__ . '/../includes/config.php'
  // (This one we leave alone since __DIR__ based is already correct and context-dependent)
];

$files = getPhpFiles(BASE_PATH);
echo "Scanning " . count($files) . " PHP files...\n\n";

foreach ($files as $file) {
  $content = file_get_contents($file);
  $original = $content;
  $rel = str_replace(BASE_PATH . DIRECTORY_SEPARATOR, '', $file);
  $rel = str_replace('\\', '/', $rel);
  $fileFixed = false;

  foreach ($rules as $rule) {
    $count = 0;
    $content = preg_replace($rule['pattern'], $rule['replacement'], $content, -1, $count);
    if ($count > 0) {
      $fixes += $count;
      $fileFixed = true;
      $report[] = [
        'file' => $rel,
        'rule' => $rule['name'],
        'count' => $count,
      ];
      echo "  ✓ {$rel} — {$rule['name']} ({$count} fix" . ($count > 1 ? 'es' : '') . ")\n";
    }
  }

  if ($fileFixed) {
    $filesModified++;
    if (!$dryRun) {
      file_put_contents($file, $content);
    }
  }
}

echo "\n═══════════════════════════════════════\n";
echo "Total fixes: $fixes across $filesModified files\n";
if ($dryRun) {
  echo "DRY RUN — No files were modified.\n";
  echo "Run without --dry-run to apply changes.\n";
}
echo "═══════════════════════════════════════\n";

// Save report
$reportData = [
  'timestamp'      => date('Y-m-d H:i:s'),
  'mode'           => $dryRun ? 'dry-run' : 'live',
  'files_scanned'  => count($files),
  'files_modified' => $filesModified,
  'total_fixes'    => $fixes,
  'details'        => $report,
];
@mkdir(BASE_PATH . '/storage', 0755, true);
@file_put_contents(
  BASE_PATH . '/storage/link-repair-report.json',
  json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);
echo "Report saved to storage/link-repair-report.json\n";
