<?php

/**
 * IntegrityVerifier — Post-repair verification engine.
 *
 * After every repair: re-run failing check, confirm successful result.
 * Repair considered complete only if verification passes.
 */
class IntegrityVerifier
{
  /**
   * Verify a list of repair outcomes by re-checking the original faults.
   */
  public static function verify(array $repairOutcomes, array $originalFaults): array
  {
    $verified = [];

    foreach ($repairOutcomes as $idx => $outcome) {
      if (!($outcome['repaired'] ?? false)) {
        $verified[] = array_merge($outcome, ['verified' => false, 'reason' => 'not_repaired']);
        continue;
      }

      $fault = $originalFaults[$idx] ?? null;
      if (!$fault) {
        $verified[] = array_merge($outcome, ['verified' => true, 'reason' => 'no_fault_reference']);
        continue;
      }

      $check = self::recheck($fault);
      $verified[] = array_merge($outcome, [
        'verified' => $check['passed'],
        'reason'   => $check['reason'],
      ]);
    }

    return $verified;
  }

  /**
   * Re-check a specific fault to see if it's resolved.
   */
  private static function recheck(array $fault): array
  {
    $type = $fault['type'] ?? '';
    $ctx = $fault['context'] ?? [];

    return match ($type) {
      'missing_critical_file' => self::recheckFile($ctx),
      'empty_critical_file'   => self::recheckFile($ctx),
      'database_failure'      => self::recheckDatabase(),
      'session_failure'       => self::recheckSession(),
      'broken_route'          => self::recheckFile($ctx),
      'storage_not_writable'  => self::recheckStorage($ctx),
      'missing_table'         => self::recheckTable($ctx),
      default                 => ['passed' => true, 'reason' => 'no_recheck_handler'],
    };
  }

  private static function recheckFile(array $ctx): array
  {
    $file = $ctx['file'] ?? '';
    if (!$file) return ['passed' => false, 'reason' => 'no_file_context'];

    $path = BASE_PATH . '/' . $file;
    if (file_exists($path) && filesize($path) >= 10) {
      return ['passed' => true, 'reason' => 'file_exists_and_non_empty'];
    }
    return ['passed' => false, 'reason' => 'file_still_missing_or_empty'];
  }

  private static function recheckDatabase(): array
  {
    try {
      db()->getConnection()->query("SELECT 1");
      return ['passed' => true, 'reason' => 'db_connection_ok'];
    } catch (\Throwable $e) {
      return ['passed' => false, 'reason' => 'db_still_failing'];
    }
  }

  private static function recheckSession(): array
  {
    if (php_sapi_name() === 'cli') {
      return ['passed' => true, 'reason' => 'cli_mode_skip'];
    }
    $savePath = session_save_path();
    if (!$savePath || is_writable($savePath)) {
      return ['passed' => true, 'reason' => 'session_path_ok'];
    }
    return ['passed' => false, 'reason' => 'session_path_still_unwritable'];
  }

  private static function recheckStorage(array $ctx): array
  {
    $dir = $ctx['dir'] ?? '';
    $path = BASE_PATH . '/' . $dir;
    if (is_dir($path) && is_writable($path)) {
      return ['passed' => true, 'reason' => 'storage_writable'];
    }
    return ['passed' => false, 'reason' => 'storage_still_unwritable'];
  }

  private static function recheckTable(array $ctx): array
  {
    $table = $ctx['table'] ?? '';
    if (!$table) return ['passed' => false, 'reason' => 'no_table_context'];

    try {
      $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
      $pdo = db()->getConnection();
      $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($safe));
      return $stmt->rowCount() > 0
        ? ['passed' => true, 'reason' => 'table_now_exists']
        : ['passed' => false, 'reason' => 'table_still_missing'];
    } catch (\Throwable $e) {
      return ['passed' => false, 'reason' => 'db_error_during_table_check'];
    }
  }

  /**
   * Run a quick platform health verification.
   */
  public static function quickHealthCheck(): array
  {
    $checks = [
      'database'   => self::recheckDatabase(),
      'config'     => self::recheckFile(['file' => 'includes/config.php']),
      'bootstrap'  => self::recheckFile(['file' => 'app/bootstrap.php']),
      'functions'  => self::recheckFile(['file' => 'includes/functions.php']),
      'storage'    => self::recheckStorage(['dir' => 'storage']),
      'cache'      => self::recheckStorage(['dir' => 'cache']),
    ];

    $allPassed = true;
    foreach ($checks as $c) {
      if (!$c['passed']) $allPassed = false;
    }

    return [
      'all_passed' => $allPassed,
      'checks'     => $checks,
    ];
  }

  public static function getSummary(): array
  {
    $health = self::quickHealthCheck();
    return [
      'status' => $health['all_passed'] ? 'verified' : 'issues_found',
      'checks' => count($health['checks']),
      'passed' => count(array_filter($health['checks'], fn($c) => $c['passed'])),
    ];
  }
}
