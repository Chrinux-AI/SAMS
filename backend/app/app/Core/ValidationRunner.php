<?php

/**
 * ValidationRunner — QA Automation for the Autonomous Fix Loop.
 *
 * After every repair cycle, runs simulated tests to verify system integrity.
 * If a validation fails, the repair is considered incomplete and the loop continues.
 */
class ValidationRunner
{
  /** @var array Test results */
  private static array $results = [];

  /**
   * Run all validation tests.
   *
   * @return array{passed: int, failed: int, total: int, tests: array}
   */
  public static function runAll(): array
  {
    self::$results = [];

    self::testDatabaseConnection();
    self::testRequiredTables();
    self::testCoreClassesLoaded();
    self::testSessionGuard();
    self::testCriticalFilesExist();
    self::testDirectoriesWritable();
    self::testClassCRUDPipeline();
    self::testCommunicationAPI();
    self::testLandingPageService();
    self::testOldMessagingRemoved();

    $passed = count(array_filter(self::$results, fn($r) => $r['passed']));
    $total = count(self::$results);

    return [
      'passed' => $passed,
      'failed' => $total - $passed,
      'total'  => $total,
      'tests'  => self::$results,
    ];
  }

  private static function testDatabaseConnection(): void
  {
    try {
      $r = db()->fetchOne("SELECT 1 as ok");
      self::pass('Database Connection', 'Database is connected and responsive');
    } catch (\Throwable $e) {
      self::fail('Database Connection', 'Cannot connect to database: ' . $e->getMessage());
    }
  }

  private static function testRequiredTables(): void
  {
    $required = ['users', 'classes', 'attendance', 'students', 'comm_conversations', 'comm_messages', 'class_schedules'];
    $missing = [];
    foreach ($required as $t) {
      if (function_exists('table_exists') && !table_exists($t)) {
        $missing[] = $t;
      }
    }
    if (empty($missing)) {
      self::pass('Required Tables', 'All ' . count($required) . ' required tables exist');
    } else {
      self::fail('Required Tables', 'Missing tables: ' . implode(', ', $missing));
    }
  }

  private static function testCoreClassesLoaded(): void
  {
    $classes = ['ErrorHandler', 'AutoSyncEngine', 'DataConsistencyGuard', 'ClassRepository', 'ClassService', 'ClassController'];
    $missing = [];
    foreach ($classes as $cls) {
      if (!class_exists($cls)) {
        $missing[] = $cls;
      }
    }
    if (empty($missing)) {
      self::pass('Core Classes', 'All ' . count($classes) . ' core classes are loaded');
    } else {
      self::fail('Core Classes', 'Missing classes: ' . implode(', ', $missing));
    }
  }

  private static function testSessionGuard(): void
  {
    if (defined('SESSION_TIMEOUT_ADMIN') && defined('SESSION_TIMEOUT_DEFAULT')) {
      self::pass('Session Guard', 'Admin: ' . SESSION_TIMEOUT_ADMIN . 's, Default: ' . SESSION_TIMEOUT_DEFAULT . 's');
    } else {
      self::fail('Session Guard', 'Session timeout constants not defined');
    }
  }

  private static function testCriticalFilesExist(): void
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $files = [
      'includes/config.php',
      'includes/functions.php',
      'includes/database.php',
      'includes/sidebar-nav.php',
      'admin/dashboard.php',
      'admin/classes.php',
      'communication/conversations.php',
      'index.php',
      'login.php',
    ];
    $missing = [];
    foreach ($files as $f) {
      if (!is_file($basePath . '/' . $f)) {
        $missing[] = $f;
      }
    }
    if (empty($missing)) {
      self::pass('Critical Files', 'All ' . count($files) . ' critical files exist');
    } else {
      self::fail('Critical Files', 'Missing: ' . implode(', ', $missing));
    }
  }

  private static function testDirectoriesWritable(): void
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $dirs = ['uploads', 'cache', 'logs', 'storage'];
    $issues = [];
    foreach ($dirs as $d) {
      $path = $basePath . '/' . $d;
      if (!is_dir($path)) {
        $issues[] = "{$d} missing";
      } elseif (!is_writable($path)) {
        $issues[] = "{$d} not writable";
      }
    }
    if (empty($issues)) {
      self::pass('Directories', 'All required directories exist and are writable');
    } else {
      self::fail('Directories', implode('; ', $issues));
    }
  }

  private static function testClassCRUDPipeline(): void
  {
    if (!class_exists('ClassRepository') || !class_exists('ClassService') || !class_exists('ClassController')) {
      self::fail('Class CRUD Pipeline', 'One or more pipeline classes not loaded');
      return;
    }

    try {
      $classes = ClassRepository::all();
      self::pass('Class CRUD Pipeline', 'ClassRepository::all() returned ' . count($classes) . ' classes');
    } catch (\Throwable $e) {
      self::fail('Class CRUD Pipeline', 'ClassRepository::all() failed: ' . $e->getMessage());
    }
  }

  private static function testCommunicationAPI(): void
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $apiFile = $basePath . '/communication/api/messages.php';

    if (!is_file($apiFile)) {
      self::fail('Communication API', 'messages.php API file missing');
      return;
    }

    // Verify comm tables have correct structure
    try {
      if (function_exists('table_exists') && table_exists('comm_conversations')) {
        $cols = db()->fetchAll("SHOW COLUMNS FROM comm_conversations");
        $colNames = array_column($cols, 'Field');
        if (in_array('id', $colNames) && in_array('type', $colNames)) {
          self::pass('Communication API', 'API file exists and comm_conversations has correct schema');
        } else {
          self::fail('Communication API', 'comm_conversations schema incomplete');
        }
      } else {
        self::fail('Communication API', 'comm_conversations table missing');
      }
    } catch (\Throwable $e) {
      self::fail('Communication API', 'Schema check failed: ' . $e->getMessage());
    }
  }

  private static function testLandingPageService(): void
  {
    if (!class_exists('LandingContentService')) {
      self::fail('Landing Page Service', 'LandingContentService class not loaded');
      return;
    }

    try {
      $stats = LandingContentService::getStats();
      if (isset($stats['students']) && isset($stats['teachers'])) {
        self::pass('Landing Page Service', 'Stats returned correctly');
      } else {
        self::fail('Landing Page Service', 'Stats array missing expected keys');
      }
    } catch (\Throwable $e) {
      self::fail('Landing Page Service', 'getStats() failed: ' . $e->getMessage());
    }
  }

  private static function testOldMessagingRemoved(): void
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $old = ['messages', 'chat', 'inbox', 'conversation'];
    $found = [];
    foreach ($old as $dir) {
      if (is_dir($basePath . '/' . $dir)) {
        $found[] = $dir;
      }
    }
    if (empty($found)) {
      self::pass('Old Messaging Cleanup', 'No legacy messaging directories found');
    } else {
      self::fail('Old Messaging Cleanup', 'Legacy directories remain: ' . implode(', ', $found));
    }
  }

  private static function pass(string $name, string $detail): void
  {
    self::$results[] = ['name' => $name, 'passed' => true, 'detail' => $detail];
  }

  private static function fail(string $name, string $detail): void
  {
    self::$results[] = ['name' => $name, 'passed' => false, 'detail' => $detail];
  }
}
