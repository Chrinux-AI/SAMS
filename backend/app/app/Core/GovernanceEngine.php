<?php

/**
 * GovernanceEngine — Central Execution Blueprint Enforcer
 *
 * Implements the SAMS Execution Blueprint governance layer:
 *  - Change Classification (SAFE / STRUCTURAL / CRITICAL / INTELLIGENCE)
 *  - Development Safety Zones (Green / Yellow / Red)
 *  - Folder Ownership validation
 *  - Health governance auto-trigger (ACI repair at < 70)
 *  - Emergency Recovery protocol orchestration
 *
 * This class is the single authority that determines whether a change
 * is allowed, how it should be classified, and what validations apply.
 */
class GovernanceEngine
{
  // ═══════════════════════════════════════════════════
  // CHANGE CLASSIFICATION SYSTEM
  // ═══════════════════════════════════════════════════

  public const CHANGE_SAFE         = 'SAFE';
  public const CHANGE_STRUCTURAL   = 'STRUCTURAL';
  public const CHANGE_CRITICAL     = 'CRITICAL';
  public const CHANGE_INTELLIGENCE = 'INTELLIGENCE';

  /** Maps file path patterns → change classification */
  private static array $classificationRules = [
    // SAFE — UI/style only
    '/\.(css|js|less|scss|svg|png|jpg|gif|ico)$/i'        => self::CHANGE_SAFE,
    '/^views\//i'                                          => self::CHANGE_SAFE,
    '/^public\/(css|js|images|fonts)\//i'                  => self::CHANGE_SAFE,
    '/^assets\//i'                                         => self::CHANGE_SAFE,
    '/^resources\/(css|js)\//i'                            => self::CHANGE_SAFE,
    '/^modules\/[^\/]+\/views?\//i'                        => self::CHANGE_SAFE,

    // INTELLIGENCE — AI layer
    '/^app\/AIC\//i'                                       => self::CHANGE_INTELLIGENCE,
    '/^app\/ACI\//i'                                       => self::CHANGE_INTELLIGENCE,
    '/^app\/AI\//i'                                        => self::CHANGE_INTELLIGENCE,
    '/^app\/Cognitive\//i'                                 => self::CHANGE_INTELLIGENCE,
    '/^app\/Platform\//i'                                  => self::CHANGE_INTELLIGENCE,
    '/^ai\//i'                                             => self::CHANGE_INTELLIGENCE,

    // CRITICAL — core engine
    '/^app\/middleware\//i'                                 => self::CHANGE_CRITICAL,
    '/^app\/Core\//i'                                      => self::CHANGE_CRITICAL,
    '/^app\/Security\//i'                                  => self::CHANGE_CRITICAL,
    '/^includes\/(config|database|session-guard|security-headers)\.php$/i' => self::CHANGE_CRITICAL,
    '/^app\/bootstrap\.php$/i'                             => self::CHANGE_CRITICAL,
    '/^config\/routes\.php$/i'                             => self::CHANGE_CRITICAL,

    // STRUCTURAL — routing/layout/controllers/services
    '/^app\/controllers?\//i'                              => self::CHANGE_STRUCTURAL,
    '/^app\/services?\//i'                                 => self::CHANGE_STRUCTURAL,
    '/^app\/repositories?\//i'                             => self::CHANGE_STRUCTURAL,
    '/^layouts?\//i'                                       => self::CHANGE_STRUCTURAL,
    '/^modules\/[^\/]+\/(?!views?\/)/i'                    => self::CHANGE_STRUCTURAL,
  ];

  /**
   * Classify a file change.
   *
   * @param  string $filePath Relative path from project root
   * @return string           SAFE|STRUCTURAL|CRITICAL|INTELLIGENCE
   */
  public static function classifyChange(string $filePath): string
  {
    $filePath = ltrim(str_replace('\\', '/', $filePath), '/');

    foreach (self::$classificationRules as $pattern => $classification) {
      if (preg_match($pattern, $filePath)) {
        return $classification;
      }
    }

    // Default: STRUCTURAL for PHP files, SAFE for everything else
    return str_ends_with(strtolower($filePath), '.php') ? self::CHANGE_STRUCTURAL : self::CHANGE_SAFE;
  }

  /**
   * Get deployment rules for a classification.
   */
  public static function getDeploymentRule(string $classification): array
  {
    return match ($classification) {
      self::CHANGE_SAFE         => ['deploy' => 'immediate', 'validation' => false, 'backup' => false, 'monitoring' => false],
      self::CHANGE_STRUCTURAL   => ['deploy' => 'validated',  'validation' => true,  'backup' => false, 'monitoring' => false],
      self::CHANGE_CRITICAL     => ['deploy' => 'guarded',    'validation' => true,  'backup' => true,  'monitoring' => true],
      self::CHANGE_INTELLIGENCE => ['deploy' => 'monitored',  'validation' => true,  'backup' => true,  'monitoring' => true],
      default                   => ['deploy' => 'validated',  'validation' => true,  'backup' => false, 'monitoring' => false],
    };
  }

  // ═══════════════════════════════════════════════════
  // DEVELOPMENT SAFETY ZONES
  // ═══════════════════════════════════════════════════

  public const ZONE_GREEN  = 'GREEN';
  public const ZONE_YELLOW = 'YELLOW';
  public const ZONE_RED    = 'RED';

  private static array $zoneMap = [
    // GREEN — safe to edit
    self::ZONE_GREEN => [
      'modules/',
      'views/',
      'public/css/',
      'public/js/',
      'public/images/',
      'assets/',
      'resources/css/',
      'resources/js/',
      'student/',
      'teacher/',
      'parent/',
      'accountant/',
      'bursar/',
      'librarian/',
      'transport/',
      'forum/',
      'general/',
    ],
    // YELLOW — requires validation
    self::ZONE_YELLOW => [
      'app/controllers/',
      'app/Controllers/',
      'app/services/',
      'app/Services/',
      'app/repositories/',
      'app/Repositories/',
      'config/routes.php',
      'admin/',
      'layouts/',
      'includes/functions.php',
    ],
    // RED — restricted edits only
    self::ZONE_RED => [
      'app/middleware/',
      'app/Core/',
      'app/Security/',
      'app/AIC/',
      'app/ACI/',
      'app/AI/',
      'app/Cognitive/',
      'app/Platform/',
      'app/OS/',
      'app/DevOps/',
      'app/SelfHealing/',
      'app/Ecosystem/',
      'app/Events/',
      'includes/config.php',
      'includes/database.php',
      'includes/session-guard.php',
      'includes/security-headers.php',
      'app/bootstrap.php',
    ],
  ];

  /**
   * Determine the safety zone for a file.
   */
  public static function getZone(string $filePath): string
  {
    $filePath = ltrim(str_replace('\\', '/', $filePath), '/');

    // Check RED first (most restrictive)
    foreach (self::$zoneMap[self::ZONE_RED] as $prefix) {
      if (str_starts_with($filePath, $prefix) || $filePath === $prefix) {
        return self::ZONE_RED;
      }
    }

    // Check YELLOW
    foreach (self::$zoneMap[self::ZONE_YELLOW] as $prefix) {
      if (str_starts_with($filePath, $prefix) || $filePath === $prefix) {
        return self::ZONE_YELLOW;
      }
    }

    // Check GREEN
    foreach (self::$zoneMap[self::ZONE_GREEN] as $prefix) {
      if (str_starts_with($filePath, $prefix) || $filePath === $prefix) {
        return self::ZONE_GREEN;
      }
    }

    // Unclassified defaults to YELLOW
    return self::ZONE_YELLOW;
  }

  /**
   * Check if a file modification is allowed for a given actor.
   *
   * @param  string $filePath Relative from project root
   * @param  string $actor    'user', 'ai', 'system', 'cron'
   * @return array{allowed: bool, zone: string, reason: string}
   */
  public static function checkAccess(string $filePath, string $actor = 'ai'): array
  {
    $zone = self::getZone($filePath);

    // System/cron can modify anything
    if (in_array($actor, ['system', 'cron'], true)) {
      return ['allowed' => true, 'zone' => $zone, 'reason' => 'System-level access'];
    }

    // Users can modify GREEN and YELLOW
    if ($actor === 'user') {
      return ['allowed' => true, 'zone' => $zone, 'reason' => 'User access'];
    }

    // AI editors: GREEN = yes, YELLOW = with validation, RED = restricted
    if ($actor === 'ai') {
      return match ($zone) {
        self::ZONE_GREEN  => ['allowed' => true,  'zone' => $zone, 'reason' => 'Green zone — safe'],
        self::ZONE_YELLOW => ['allowed' => true,  'zone' => $zone, 'reason' => 'Yellow zone — validation required'],
        self::ZONE_RED    => ['allowed' => false, 'zone' => $zone, 'reason' => 'Red zone — restricted to authorized changes only'],
        default           => ['allowed' => true,  'zone' => $zone, 'reason' => 'Default permitted'],
      };
    }

    return ['allowed' => false, 'zone' => $zone, 'reason' => 'Unknown actor'];
  }

  // ═══════════════════════════════════════════════════
  // FOLDER OWNERSHIP PROTOCOL
  // ═══════════════════════════════════════════════════

  private static array $ownershipRules = [
    'controllers' => ['allowed_calls' => ['services', 'views'], 'forbidden' => ['repositories', 'database']],
    'services'    => ['allowed_calls' => ['repositories', 'helpers'], 'forbidden' => ['views', 'controllers']],
    'repositories' => ['allowed_calls' => ['database'], 'forbidden' => ['views', 'controllers', 'services']],
    'middleware'  => ['allowed_calls' => ['services', 'security'], 'forbidden' => ['views', 'repositories']],
    'helpers'     => ['allowed_calls' => ['services'], 'forbidden' => ['views', 'controllers', 'repositories']],
    'views'       => ['allowed_calls' => ['helpers'], 'forbidden' => ['services', 'repositories', 'database']],
  ];

  /**
   * Get ownership rules for a directory.
   */
  public static function getOwnership(string $directory): ?array
  {
    $dir = strtolower(basename(rtrim($directory, '/')));
    return self::$ownershipRules[$dir] ?? null;
  }

  /**
   * Validate whether a directory layer is allowed to call another.
   */
  public static function validateOwnership(string $callerLayer, string $calleeLayer): array
  {
    $rules = self::$ownershipRules[strtolower($callerLayer)] ?? null;
    if (!$rules) {
      return ['valid' => true, 'reason' => 'No ownership rules defined for layer'];
    }

    $calleeNorm = strtolower($calleeLayer);

    if (in_array($calleeNorm, $rules['forbidden'], true)) {
      return [
        'valid'  => false,
        'reason' => "{$callerLayer} must NOT call {$calleeLayer} directly",
      ];
    }

    if (in_array($calleeNorm, $rules['allowed_calls'], true)) {
      return ['valid' => true, 'reason' => 'Permitted call pattern'];
    }

    return ['valid' => true, 'reason' => 'Not explicitly forbidden'];
  }

  // ═══════════════════════════════════════════════════
  // HEALTH GOVERNANCE AUTO-TRIGGER
  // ═══════════════════════════════════════════════════

  private static string $healthStatePath = '';

  private static function healthStatePath(): string
  {
    if (!self::$healthStatePath) {
      self::$healthStatePath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2))
        . '/storage/health-governance.json';
    }
    return self::$healthStatePath;
  }

  /**
   * Run health check and auto-trigger ACI if score < 70.
   * Called from cron or real-time polling.
   *
   * @return array{health: array, triggered: bool, action: string}
   */
  public static function checkHealthGovernance(): array
  {
    $health = SystemHealthScore::calculate();
    $triggered = false;
    $action = 'none';

    // Persist health state
    $state = [
      'timestamp'    => date('Y-m-d H:i:s'),
      'overall'      => $health['overall'],
      'performance'  => $health['performance'],
      'security'     => $health['security'],
      'stability'    => $health['stability'],
      'integrity'    => $health['integrity'],
      'grade'        => SystemHealthScore::grade($health['overall']),
    ];

    // Health < 70 → ACI enters repair mode
    if ($health['overall'] < 70) {
      $triggered = true;
      $action = 'aci_repair_mode';

      // Dispatch health governance event
      if (class_exists('EventBus')) {
        EventBus::dispatch('governance', 'health_threshold_breach', [
          'overall'  => $health['overall'],
          'grade'    => $state['grade'],
          'domains'  => $health['details'],
        ]);
      }

      // Enter repair mode via state flag. The ACI cron cycle will pick this up.
      $state['aci_repair_mode'] = true;

      // Log the governance action
      self::logGovernanceAction('system', 'health_auto_repair', [], 'triggered', $health['overall']);
    }

    // Health < 40 → Emergency mode
    if ($health['overall'] < 40) {
      $action = 'emergency_recovery';
      $state['aci_repair_mode'] = true;
      if (class_exists('IncidentResponder')) {
        try {
          IncidentResponder::assess();
          $state['emergency_assessed'] = true;
        } catch (\Throwable $e) {
          $state['emergency_error'] = $e->getMessage();
        }
      }
    }

    // Persist health state
    $dir = dirname(self::healthStatePath());
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }
    file_put_contents(self::healthStatePath(), json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);

    return ['health' => $health, 'triggered' => $triggered, 'action' => $action];
  }

  /**
   * Get the last health governance state.
   */
  public static function getHealthState(): array
  {
    if (!is_file(self::healthStatePath())) {
      return ['overall' => 100, 'grade' => 'Unknown', 'timestamp' => null];
    }
    return json_decode(file_get_contents(self::healthStatePath()), true) ?: [];
  }

  // ═══════════════════════════════════════════════════
  // EMERGENCY RECOVERY PROTOCOL
  // ═══════════════════════════════════════════════════

  /**
   * Execute the emergency recovery sequence:
   * Freeze → Heal → Restore → Revalidate → Resume
   *
   * @return array Execution results for each phase
   */
  public static function emergencyRecovery(): array
  {
    $results = [];

    // Phase 1: FREEZE — block new deployments
    $results['freeze'] = self::freeze();

    // Phase 2: HEAL — activate healing engine
    $results['heal'] = self::heal();

    // Phase 3: RESTORE — restore last stable backup if healing fails
    if (!$results['heal']['success']) {
      $results['restore'] = self::restore();
    } else {
      $results['restore'] = ['skipped' => true, 'reason' => 'Healing succeeded'];
    }

    // Phase 4: REVALIDATE — run full validation pipeline
    $results['revalidate'] = self::revalidate();

    // Phase 5: RESUME — resume operations
    if ($results['revalidate']['passed']) {
      $results['resume'] = self::resume();
    } else {
      $results['resume'] = ['success' => false, 'reason' => 'Revalidation failed — manual intervention required'];
    }

    // Log the entire recovery
    self::logGovernanceAction(
      'system',
      'emergency_recovery',
      array_keys($results),
      (($results['resume']['success'] ?? false) ? 'recovered' : 'needs_intervention'),
      SystemHealthScore::calculate()['overall'] ?? 0
    );

    // Dispatch event
    if (class_exists('EventBus')) {
      EventBus::dispatch('governance', 'emergency_recovery', $results);
    }

    return $results;
  }

  private static function freeze(): array
  {
    $statePath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/deployment-frozen.flag';
    $dir = dirname($statePath);
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }
    file_put_contents($statePath, json_encode([
      'frozen_at' => date('Y-m-d H:i:s'),
      'reason'    => 'Emergency recovery protocol',
    ]), LOCK_EX);
    return ['success' => true, 'frozen_at' => date('Y-m-d H:i:s')];
  }

  private static function heal(): array
  {
    try {
      if (class_exists('HealingKernel')) {
        $result = HealingKernel::run();
        return ['success' => true, 'healing' => $result];
      }
      if (class_exists('AutoRepairEngine')) {
        $issues = class_exists('SystemScanner') ? SystemScanner::scan() : [];
        $result = AutoRepairEngine::repair($issues);
        return ['success' => true, 'repair' => $result];
      }
      return ['success' => false, 'reason' => 'No healing engine available'];
    } catch (\Throwable $e) {
      return ['success' => false, 'reason' => $e->getMessage()];
    }
  }

  private static function restore(): array
  {
    try {
      // Check for backup service
      if (class_exists('BackupService')) {
        return ['success' => true, 'message' => 'Backup service available for manual restore'];
      }
      return ['success' => false, 'reason' => 'No backup service available'];
    } catch (\Throwable $e) {
      return ['success' => false, 'reason' => $e->getMessage()];
    }
  }

  private static function revalidate(): array
  {
    try {
      if (class_exists('DeploymentGuard')) {
        $result = DeploymentGuard::validate();
        return ['passed' => $result['safe'], 'checks' => count($result['checks']), 'blockers' => count($result['blockers'])];
      }
      return ['passed' => true, 'reason' => 'No validation guard available — assuming safe'];
    } catch (\Throwable $e) {
      return ['passed' => false, 'reason' => $e->getMessage()];
    }
  }

  private static function resume(): array
  {
    $statePath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/deployment-frozen.flag';
    if (is_file($statePath)) {
      unlink($statePath);
    }
    return ['success' => true, 'resumed_at' => date('Y-m-d H:i:s')];
  }

  /**
   * Check if deployments are currently frozen.
   */
  public static function isDeploymentFrozen(): bool
  {
    $statePath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/deployment-frozen.flag';
    return is_file($statePath);
  }

  // ═══════════════════════════════════════════════════
  // GOVERNANCE LOGGING (Blueprint Doctrine Format)
  // ═══════════════════════════════════════════════════

  /**
   * Log a governance action in blueprint-mandated format:
   * [TIME] ACTOR ACTION FILES_CHANGED RESULT HEALTH_SCORE
   *
   * @param string $actor         user/ai/system/cron
   * @param string $action        What happened
   * @param array  $filesChanged  List of changed file paths
   * @param string $result        success/failure/blocked/triggered
   * @param int    $healthScore   Current health score
   */
  public static function logGovernanceAction(
    string $actor,
    string $action,
    array  $filesChanged = [],
    string $result = 'success',
    int    $healthScore = 0
  ): void {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $logDir = $basePath . '/storage/logs';
    if (!is_dir($logDir)) {
      mkdir($logDir, 0755, true);
    }

    $entry = [
      'time'          => date('Y-m-d H:i:s'),
      'actor'         => $actor,
      'action'        => $action,
      'files_changed' => $filesChanged,
      'result'        => $result,
      'health_score'  => $healthScore ?: (class_exists('SystemHealthScore') ? SystemHealthScore::calculate()['overall'] : 0),
    ];

    // Structured line format per blueprint
    $line = sprintf(
      "[%s] ACTOR=%s ACTION=%s FILES=%s RESULT=%s HEALTH=%d",
      $entry['time'],
      $entry['actor'],
      $entry['action'],
      implode(',', $entry['files_changed']) ?: 'none',
      $entry['result'],
      $entry['health_score']
    );

    file_put_contents($logDir . '/governance.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);

    // Also store structured JSON for dashboard consumption
    $jsonPath = $basePath . '/storage/governance-log.json';
    $existing = [];
    if (is_file($jsonPath)) {
      $existing = json_decode(file_get_contents($jsonPath), true) ?: [];
    }
    $existing[] = $entry;
    $existing = array_slice($existing, -500); // Keep last 500
    file_put_contents($jsonPath, json_encode($existing, JSON_PRETTY_PRINT), LOCK_EX);
  }

  /**
   * Get recent governance log entries.
   */
  public static function getRecentLog(int $limit = 50): array
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $jsonPath = $basePath . '/storage/governance-log.json';
    if (!is_file($jsonPath)) {
      return [];
    }
    $entries = json_decode(file_get_contents($jsonPath), true) ?: [];
    return array_slice(array_reverse($entries), 0, $limit);
  }

  // ═══════════════════════════════════════════════════
  // FULL STATUS (for MCC Dashboard)
  // ═══════════════════════════════════════════════════

  /**
   * Get full governance status for dashboard display.
   */
  public static function getStatus(): array
  {
    return [
      'health_state'       => self::getHealthState(),
      'deployment_frozen'  => self::isDeploymentFrozen(),
      'recent_log'         => self::getRecentLog(10),
      'zone_map'           => [
        'green'  => count(self::$zoneMap[self::ZONE_GREEN]),
        'yellow' => count(self::$zoneMap[self::ZONE_YELLOW]),
        'red'    => count(self::$zoneMap[self::ZONE_RED]),
      ],
    ];
  }
}
