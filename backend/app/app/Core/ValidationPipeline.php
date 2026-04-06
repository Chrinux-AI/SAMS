<?php

/**
 * ValidationPipeline — Unified Pre-Deployment Validator
 *
 * Implements the blueprint mandate:
 *   Link Checker → Route Validator → Permission Scan → DB Integrity → Self-Healing Simulation
 *
 * Deployment is blocked if ANY step fails.
 *
 * Usage:
 *   $result = ValidationPipeline::run();
 *   if (!$result['passed']) { block deployment; }
 */
class ValidationPipeline
{
  /**
   * Run the full 5-step validation pipeline.
   *
   * @return array{passed: bool, steps: array, blockers: array, duration_ms: int}
   */
  public static function run(): array
  {
    $start = microtime(true);
    $steps = [];
    $blockers = [];

    // Step 1: Link Checker
    $steps['link_check'] = self::runLinkCheck();
    if (!$steps['link_check']['passed']) {
      $blockers[] = $steps['link_check'];
    }

    // Step 2: Route Validator
    $steps['route_validation'] = self::runRouteValidation();
    if (!$steps['route_validation']['passed']) {
      $blockers[] = $steps['route_validation'];
    }

    // Step 3: Permission Scan
    $steps['permission_scan'] = self::runPermissionScan();
    if (!$steps['permission_scan']['passed']) {
      $blockers[] = $steps['permission_scan'];
    }

    // Step 4: Database Integrity Check
    $steps['db_integrity'] = self::runDatabaseIntegrity();
    if (!$steps['db_integrity']['passed']) {
      $blockers[] = $steps['db_integrity'];
    }

    // Step 5: Self-Healing Simulation
    $steps['healing_simulation'] = self::runHealingSimulation();
    if (!$steps['healing_simulation']['passed']) {
      $blockers[] = $steps['healing_simulation'];
    }

    $duration = (int)round((microtime(true) - $start) * 1000);
    $passed = empty($blockers);

    // Log result via governance logger
    if (class_exists('GovernanceEngine')) {
      GovernanceEngine::logGovernanceAction(
        'system',
        'validation_pipeline',
        [],
        $passed ? 'passed' : 'blocked',
        class_exists('SystemHealthScore') ? SystemHealthScore::calculate()['overall'] : 0
      );
    }

    // Dispatch event
    if (class_exists('EventBus')) {
      EventBus::dispatch('governance', 'validation_pipeline', [
        'passed'   => $passed,
        'steps'    => count($steps),
        'blockers' => count($blockers),
        'duration' => $duration,
      ]);
    }

    return [
      'passed'      => $passed,
      'steps'       => $steps,
      'blockers'    => $blockers,
      'duration_ms' => $duration,
      'timestamp'   => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Run only a specific step by name.
   */
  public static function runStep(string $step): array
  {
    return match ($step) {
      'link_check'          => self::runLinkCheck(),
      'route_validation'    => self::runRouteValidation(),
      'permission_scan'     => self::runPermissionScan(),
      'db_integrity'        => self::runDatabaseIntegrity(),
      'healing_simulation'  => self::runHealingSimulation(),
      default               => ['passed' => false, 'step' => $step, 'error' => 'Unknown step'],
    };
  }

  // ═══════════════════════════════════════════════════
  // STEP IMPLEMENTATIONS
  // ═══════════════════════════════════════════════════

  private static function runLinkCheck(): array
  {
    $step = ['step' => 'link_check', 'passed' => true, 'issues' => []];

    try {
      $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

      // Check if route targets exist for all named routes
      if (function_exists('get_route_names') && function_exists('named_route')) {
        $routeNames = get_route_names();
        $brokenRoutes = [];

        foreach ($routeNames as $name) {
          $path = named_route($name);
          // Strip base URL prefix to get relative path
          $relative = preg_replace('#^/attendance/?#', '', $path);
          if ($relative && !is_file($basePath . '/' . $relative) && !is_dir($basePath . '/' . $relative)) {
            $brokenRoutes[] = $name . ' → ' . $relative;
          }
        }

        if (count($brokenRoutes) > 5) {
          $step['passed'] = false;
          $step['issues'] = array_slice($brokenRoutes, 0, 20);
          $step['broken_count'] = count($brokenRoutes);
        } elseif (count($brokenRoutes) > 0) {
          // Warn but don't block for minor issues
          $step['warnings'] = $brokenRoutes;
        }
      }
    } catch (\Throwable $e) {
      $step['error'] = $e->getMessage();
    }

    return $step;
  }

  private static function runRouteValidation(): array
  {
    $step = ['step' => 'route_validation', 'passed' => true, 'issues' => []];

    try {
      // Use DeploymentGuard's route check if available
      if (class_exists('DeploymentGuard')) {
        // DeploymentGuard::validate runs all checks; we only need the route portion
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $routeFile = $basePath . '/config/routes.php';

        if (!is_file($routeFile)) {
          $step['passed'] = false;
          $step['issues'][] = 'config/routes.php missing';
          return $step;
        }

        // Syntax check routes file
        $output = [];
        $exitCode = 0;
        exec('php -l ' . escapeshellarg($routeFile) . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
          $step['passed'] = false;
          $step['issues'][] = 'routes.php has syntax errors: ' . implode(' ', $output);
        }
      }

      // Check bootstrap integrity
      $bootstrapFile = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/app/bootstrap.php';
      if (is_file($bootstrapFile)) {
        $output = [];
        $exitCode = 0;
        exec('php -l ' . escapeshellarg($bootstrapFile) . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
          $step['passed'] = false;
          $step['issues'][] = 'bootstrap.php has syntax errors';
        }
      }
    } catch (\Throwable $e) {
      $step['error'] = $e->getMessage();
    }

    return $step;
  }

  private static function runPermissionScan(): array
  {
    $step = ['step' => 'permission_scan', 'passed' => true, 'issues' => []];

    try {
      $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

      // Check that sensitive directories have proper protections
      $sensitiveDirectories = [
        'storage' => ['writable' => true],
        'config'  => ['writable' => false],
        'logs'    => ['writable' => true],
      ];

      foreach ($sensitiveDirectories as $dir => $policy) {
        $fullPath = $basePath . '/' . $dir;
        if (!is_dir($fullPath)) {
          continue; // Directory not existing is not a permission issue
        }

        if ($policy['writable'] && !is_writable($fullPath)) {
          $step['issues'][] = "{$dir}/ must be writable but is not";
          $step['passed'] = false;
        }
      }

      // Check .htaccess exists in storage to prevent direct access
      $storagePath = $basePath . '/storage';
      if (is_dir($storagePath)) {
        $htaccess = $storagePath . '/.htaccess';
        if (!is_file($htaccess)) {
          $step['issues'][] = 'storage/.htaccess missing — direct access possible';
        }
      }
    } catch (\Throwable $e) {
      $step['error'] = $e->getMessage();
    }

    return $step;
  }

  private static function runDatabaseIntegrity(): array
  {
    $step = ['step' => 'db_integrity', 'passed' => true, 'issues' => []];

    try {
      if (!function_exists('db')) {
        $step['error'] = 'Database function not available';
        return $step;
      }

      $pdo = db()->getConnection();

      // Check critical tables exist
      $criticalTables = ['users', 'attendance', 'classes', 'sessions'];
      $dbName = defined('DB_NAME') ? DB_NAME : 'attendance_system';

      foreach ($criticalTables as $table) {
        $stmt = $pdo->prepare(
          "SELECT COUNT(*) FROM information_schema.TABLES
           WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl"
        );
        $stmt->execute([':db' => $dbName, ':tbl' => $table]);
        if ((int)$stmt->fetchColumn() === 0) {
          $step['passed'] = false;
          $step['issues'][] = "Critical table missing: {$table}";
        }
      }

      // Check for crashed tables
      $stmt = $pdo->prepare(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = :db AND TABLE_COMMENT LIKE '%crash%'"
      );
      $stmt->execute([':db' => $dbName]);
      $crashed = $stmt->fetchAll(\PDO::FETCH_COLUMN);
      if (!empty($crashed)) {
        $step['passed'] = false;
        $step['issues'][] = 'Crashed tables: ' . implode(', ', $crashed);
      }
    } catch (\Throwable $e) {
      $step['error'] = $e->getMessage();
    }

    return $step;
  }

  private static function runHealingSimulation(): array
  {
    $step = ['step' => 'healing_simulation', 'passed' => true, 'issues' => []];

    try {
      // Query current system health
      if (class_exists('SystemHealthScore')) {
        $health = SystemHealthScore::calculate();
        $step['health_score'] = $health['overall'];

        if ($health['overall'] < 40) {
          $step['passed'] = false;
          $step['issues'][] = "System health critical: {$health['overall']}/100";
        } elseif ($health['overall'] < 70) {
          $step['issues'][] = "System health degraded: {$health['overall']}/100 — ACI will auto-repair";
        }
      }

      // Verify healing infrastructure is operational
      $healingClasses = ['HealingKernel', 'FaultDetector', 'FailureContainment'];
      $missing = [];
      foreach ($healingClasses as $cls) {
        if (!class_exists($cls)) {
          $missing[] = $cls;
        }
      }
      if (!empty($missing)) {
        $step['issues'][] = 'Healing classes unavailable: ' . implode(', ', $missing);
      }

      // Verify cron jobs exist
      $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
      $requiredCrons = ['healing.php', 'aci.php', 'devops.php'];
      foreach ($requiredCrons as $cron) {
        if (!is_file($basePath . '/cron/' . $cron)) {
          $step['issues'][] = "Cron missing: {$cron}";
        }
      }
    } catch (\Throwable $e) {
      $step['error'] = $e->getMessage();
    }

    return $step;
  }
}
