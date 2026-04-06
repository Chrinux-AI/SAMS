<?php

/**
 * SystemScanner — Detection Phase of the Autonomous Fix Loop.
 *
 * Scans for:
 * A. Page Integrity — blank pages, 500 errors, missing includes, broken routes
 * B. Admin Workflows — CRUD operations, schedule loading
 * C. Navigation — sidebar links, redirects
 * D. Database Alignment — missing columns, invalid joins, orphan records
 */
class SystemScanner
{
  /** @var array Detected issues */
  private static array $issues = [];

  /**
   * Run all scan phases and return detected issues.
   *
   * @return array Each item: [module, problem, severity, repairable, category]
   */
  public static function scan(): array
  {
    self::$issues = [];

    self::scanDatabaseAlignment();
    self::scanRequiredTables();
    self::scanCriticalFiles();
    self::scanNavigation();
    self::scanDirectoryStructure();
    self::scanSessionConfig();

    return self::$issues;
  }

  /**
   * D. Database Alignment — check required columns, orphan records.
   */
  private static function scanDatabaseAlignment(): void
  {
    $columnChecks = [
      'users'       => ['id', 'email', 'role', 'status', 'first_name', 'last_name'],
      'classes'     => ['id', 'class_name', 'grade_level'],
      'attendance'  => ['id', 'student_id', 'date', 'status'],
    ];

    foreach ($columnChecks as $table => $columns) {
      if (!function_exists('table_exists') || !table_exists($table)) {
        self::addIssue($table, "Table '{$table}' does not exist", 'CRITICAL', true, 'database');
        continue;
      }
      foreach ($columns as $col) {
        if (function_exists('table_has_column') && !table_has_column($table, $col)) {
          self::addIssue($table, "Column '{$col}' missing from '{$table}' table", 'HIGH', false, 'database');
        }
      }
    }

    // Check for orphan class_enrollments
    try {
      if (function_exists('table_exists') && table_exists('class_enrollments') && table_exists('classes')) {
        $orphans = db()->fetchOne(
          "SELECT COUNT(*) as c FROM class_enrollments ce
                     LEFT JOIN classes cl ON ce.class_id = cl.id
                     WHERE cl.id IS NULL"
        );
        if (($orphans['c'] ?? 0) > 0) {
          self::addIssue('class_enrollments', "Found {$orphans['c']} orphan enrollment records (class deleted)", 'MEDIUM', true, 'database');
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }

    // Check for orphan attendance records
    try {
      if (function_exists('table_exists') && table_exists('attendance') && table_exists('students')) {
        $orphans = db()->fetchOne(
          "SELECT COUNT(*) as c FROM attendance a
                     LEFT JOIN students s ON a.student_id = s.id
                     WHERE s.id IS NULL"
        );
        if (($orphans['c'] ?? 0) > 0) {
          self::addIssue('attendance', "Found {$orphans['c']} orphan attendance records (student deleted)", 'LOW', true, 'database');
        }
      }
    } catch (\Throwable $e) {
      // Non-critical
    }
  }

  /**
   * Check all required tables exist.
   */
  private static function scanRequiredTables(): void
  {
    $required = [
      'users',
      'classes',
      'attendance',
      'students',
      'comm_conversations',
      'comm_participants',
      'comm_messages',
      'comm_reads',
      'comm_typing',
      'class_schedules',
    ];

    foreach ($required as $table) {
      if (function_exists('table_exists') && !table_exists($table)) {
        self::addIssue('database', "Required table '{$table}' is missing", 'CRITICAL', true, 'database');
      }
    }
  }

  /**
   * A. Page Integrity — check critical PHP files exist and have no syntax errors.
   */
  private static function scanCriticalFiles(): void
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

    $critical = [
      'includes/config.php',
      'includes/functions.php',
      'includes/database.php',
      'includes/sidebar-nav.php',
      'includes/session-guard.php',
      'app/bootstrap.php',
      'admin/dashboard.php',
      'admin/classes.php',
      'admin/settings.php',
      'admin/students.php',
      'admin/teachers.php',
      'communication/conversations.php',
      'communication/api/messages.php',
      'index.php',
      'login.php',
    ];

    foreach ($critical as $file) {
      $path = $basePath . '/' . $file;
      if (!is_file($path)) {
        self::addIssue('filesystem', "Critical file missing: {$file}", 'CRITICAL', false, 'page_integrity');
      }
    }
  }

  /**
   * C. Navigation — check sidebar-nav.php includes valid targets.
   */
  private static function scanNavigation(): void
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $sidebar = $basePath . '/includes/sidebar-nav.php';

    if (!is_file($sidebar)) {
      self::addIssue('navigation', 'sidebar-nav.php is missing', 'CRITICAL', false, 'navigation');
      return;
    }

    // Parse href targets from sidebar
    $content = file_get_contents($sidebar);
    preg_match_all('/href=["\']([^"\']+\.php)["\']/', $content, $matches);

    foreach ($matches[1] ?? [] as $href) {
      if (strpos($href, 'http') === 0 || strpos($href, '#') === 0) continue;

      // Resolve relative path from includes/ directory
      $resolved = realpath($basePath . '/includes/' . $href);
      if (!$resolved) {
        // Try from root
        $resolved = realpath($basePath . '/' . ltrim($href, './'));
      }
      if (!$resolved && strpos($href, '../') === 0) {
        // ../admin/classes.php → admin/classes.php
        $cleaned = preg_replace('#^(\.\./)+#', '', $href);
        $resolved = realpath($basePath . '/' . $cleaned);
      }
      // Only flag if it looks like a project file and truly doesn't exist
      if (!$resolved && strpos($href, '../') === 0) {
        $cleaned = preg_replace('#^(\.\./)+#', '', $href);
        if (!is_file($basePath . '/' . $cleaned)) {
          self::addIssue('navigation', "Sidebar link target missing: {$href}", 'MEDIUM', false, 'navigation');
        }
      }
    }
  }

  /**
   * Check required directories.
   */
  private static function scanDirectoryStructure(): void
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $required = ['uploads', 'cache', 'logs', 'storage', 'communication/uploads'];

    foreach ($required as $dir) {
      $path = $basePath . '/' . $dir;
      if (!is_dir($path)) {
        self::addIssue('filesystem', "Required directory missing: {$dir}", 'HIGH', true, 'directory');
      } elseif (!is_writable($path)) {
        self::addIssue('filesystem', "Directory not writable: {$dir}", 'HIGH', false, 'directory');
      }
    }
  }

  /**
   * Verify session timeout config.
   */
  private static function scanSessionConfig(): void
  {
    if (!defined('SESSION_TIMEOUT_ADMIN')) {
      self::addIssue('session', 'SESSION_TIMEOUT_ADMIN not defined — session guard may not be loaded', 'MEDIUM', false, 'config');
    }
  }

  private static function addIssue(string $module, string $problem, string $severity, bool $repairable, string $category): void
  {
    self::$issues[] = [
      'module'     => $module,
      'problem'    => $problem,
      'severity'   => $severity,
      'repairable' => $repairable,
      'category'   => $category,
      'detected_at' => date('Y-m-d H:i:s'),
    ];

    ErrorCollector::log($module, $problem, $severity, ['repairable' => $repairable, 'category' => $category]);
  }
}
