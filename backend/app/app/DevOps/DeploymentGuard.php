<?php

/**
 * DeploymentGuard — Anti-Break System
 *
 * Before accepting code changes / after file modifications, validates:
 * - PHP syntax errors across critical files
 * - Missing include/require dependencies
 * - Broken route targets
 * - Database schema mismatches
 * - Permission leaks (world-writable files)
 *
 * If risk detected → deployment blocked / alert raised.
 */
class DeploymentGuard
{
  /**
   * Run full deployment validation.
   *
   * @return array{safe: bool, checks: array, blockers: array}
   */
  public static function validate(): array
  {
    $checks = [];
    $blockers = [];

    $checks[] = self::checkSyntax();
    $checks[] = self::checkIncludes();
    $checks[] = self::checkRoutes();
    $checks[] = self::checkDatabaseSchema();
    $checks[] = self::checkFilePermissions();
    $checks[] = self::checkBootstrapIntegrity();

    foreach ($checks as $check) {
      if (!$check['passed']) {
        $blockers[] = $check;
      }
    }

    $safe = empty($blockers);

    if (!$safe) {
      ErrorCollector::log('deployment', count($blockers) . ' deployment blocker(s) detected', 'CRITICAL');
    }

    return ['safe' => $safe, 'checks' => $checks, 'blockers' => $blockers];
  }

  /**
   * Validate PHP syntax of all critical files.
   */
  private static function checkSyntax(): array
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $criticalFiles = [
      'includes/config.php',
      'includes/database.php',
      'includes/functions.php',
      'app/bootstrap.php',
      'index.php',
      'login.php',
      'register.php',
      'admin/dashboard.php',
      'admin/classes.php',
      'admin/settings.php',
      'communication/conversations.php',
      'cron/autofix.php',
    ];

    $errors = [];
    foreach ($criticalFiles as $file) {
      $full = $basePath . '/' . $file;
      if (!is_file($full)) continue;

      $output = [];
      $exitCode = 0;
      exec('php -l ' . escapeshellarg($full) . ' 2>&1', $output, $exitCode);
      if ($exitCode !== 0) {
        $errors[] = $file . ': ' . implode(' ', $output);
      }
    }

    return [
      'name'   => 'PHP Syntax',
      'passed' => empty($errors),
      'detail' => empty($errors) ? 'All critical files pass syntax check' : implode('; ', $errors),
    ];
  }

  /**
   * Check that required include files exist.
   */
  private static function checkIncludes(): array
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $required = [
      'includes/config.php',
      'includes/database.php',
      'includes/functions.php',
      'includes/sidebar-nav.php',
      'app/bootstrap.php',
    ];

    $missing = [];
    foreach ($required as $file) {
      if (!is_file($basePath . '/' . $file)) {
        $missing[] = $file;
      }
    }

    return [
      'name'   => 'Required Includes',
      'passed' => empty($missing),
      'detail' => empty($missing) ? 'All required includes present' : 'Missing: ' . implode(', ', $missing),
    ];
  }

  /**
   * Validate navigation routes point to real files.
   */
  private static function checkRoutes(): array
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $sidebarFile = $basePath . '/includes/sidebar-nav.php';

    if (!is_file($sidebarFile)) {
      return ['name' => 'Route Validation', 'passed' => true, 'detail' => 'Sidebar file not found (skipped)'];
    }

    $content = file_get_contents($sidebarFile);
    preg_match_all('/href=["\']([^"\'#][^"\']*\.php[^"\']*)["\']/', $content, $matches);

    $broken = [];
    foreach ($matches[1] ?? [] as $href) {
      // Strip query strings and anchors
      $path = parse_url($href, PHP_URL_PATH);
      if (!$path) continue;

      // Resolve relative to base
      $resolved = $basePath . '/' . ltrim($path, '/');
      if (!is_file($resolved)) {
        // Try relative to includes
        $resolved2 = dirname($sidebarFile) . '/' . $path;
        if (!is_file($resolved2)) {
          $broken[] = $path;
        }
      }
    }

    return [
      'name'   => 'Route Validation',
      'passed' => empty($broken),
      'detail' => empty($broken) ? 'All sidebar routes resolve to valid files' : 'Broken routes: ' . implode(', ', array_unique($broken)),
    ];
  }

  /**
   * Verify database schema matches expected structure.
   */
  private static function checkDatabaseSchema(): array
  {
    $requiredTables = ['users', 'classes', 'attendance'];
    $missing = [];

    foreach ($requiredTables as $table) {
      if (function_exists('table_exists') && !table_exists($table)) {
        $missing[] = $table;
      }
    }

    return [
      'name'   => 'Database Schema',
      'passed' => empty($missing),
      'detail' => empty($missing) ? 'Core database tables present' : 'Missing tables: ' . implode(', ', $missing),
    ];
  }

  /**
   * Check for dangerous file permissions.
   */
  private static function checkFilePermissions(): array
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $issues = [];

    // Check config file is not world-readable (on non-Windows)
    if (PHP_OS_FAMILY !== 'Windows') {
      $configPerms = fileperms($basePath . '/includes/config.php');
      if ($configPerms && ($configPerms & 0x0004)) {
        $issues[] = 'config.php is world-readable';
      }
    }

    // Check sensitive dirs have .htaccess
    $sensitiveDirs = ['storage', 'logs', 'backups'];
    foreach ($sensitiveDirs as $dir) {
      $dirPath = $basePath . '/' . $dir;
      if (is_dir($dirPath) && !is_file($dirPath . '/.htaccess')) {
        $issues[] = "{$dir}/ missing .htaccess protection";
      }
    }

    return [
      'name'   => 'File Permissions',
      'passed' => empty($issues),
      'detail' => empty($issues) ? 'File permissions acceptable' : implode('; ', $issues),
    ];
  }

  /**
   * Verify bootstrap autoloader integrity.
   */
  private static function checkBootstrapIntegrity(): array
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $bootstrap = $basePath . '/app/bootstrap.php';

    if (!is_file($bootstrap)) {
      return ['name' => 'Bootstrap Integrity', 'passed' => false, 'detail' => 'bootstrap.php missing'];
    }

    $content = file_get_contents($bootstrap);
    $requiredClasses = ['ErrorHandler', 'ClassRepository', 'ClassService'];
    $missing = [];

    foreach ($requiredClasses as $class) {
      if (strpos($content, "'{$class}'") === false) {
        $missing[] = $class;
      }
    }

    return [
      'name'   => 'Bootstrap Integrity',
      'passed' => empty($missing),
      'detail' => empty($missing) ? 'Bootstrap contains all required class mappings' : 'Missing mappings: ' . implode(', ', $missing),
    ];
  }

  /**
   * Quick safety check — returns true if system is deployment-safe.
   */
  public static function isSafe(): bool
  {
    $result = self::validate();
    return $result['safe'];
  }
}
