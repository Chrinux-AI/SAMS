<?php

/**
 * OSKernel — Autonomous School Operating System Core
 *
 * Central brain: boots the system, coordinates all subsystems, runs system cycles.
 * Architecture chain:
 *   OSKernel → HealingKernel → EcosystemKernel → CognitiveKernel → IntelligenceKernel → DevOpsKernel
 */
class OSKernel
{
  private static string $summaryPath = '';
  private static string $lockPath    = '';
  private static bool   $booted      = false;

  private static function init(): void
  {
    if (!self::$summaryPath) {
      self::$summaryPath = BASE_PATH . '/storage/os-summary.json';
      self::$lockPath    = BASE_PATH . '/storage/os-kernel.lock';
    }
  }

  /**
   * Boot the operating system — called once per request or cron cycle.
   */
  public static function boot(): array
  {
    self::init();
    if (self::$booted) {
      return ['status' => 'already_booted'];
    }

    $start   = microtime(true);
    $results = ['timestamp' => date('c'), 'phases' => []];

    // Phase 1: Policy Runtime (security first)
    try {
      $results['phases']['policy'] = PolicyRuntime::enforce();
    } catch (\Throwable $e) {
      $results['phases']['policy'] = ['error' => $e->getMessage()];
      ErrorCollector::log('os_kernel', 'Policy boot failed: ' . $e->getMessage(), 'HIGH');
    }

    // Phase 2: Identity Core
    try {
      $results['phases']['identity'] = IdentityCore::validate();
    } catch (\Throwable $e) {
      $results['phases']['identity'] = ['error' => $e->getMessage()];
    }

    // Phase 3: Institutional State snapshot
    try {
      $results['phases']['state'] = InstitutionalState::snapshot();
    } catch (\Throwable $e) {
      $results['phases']['state'] = ['error' => $e->getMessage()];
    }

    // Phase 4: Academic Runtime
    try {
      $results['phases']['academic'] = AcademicRuntime::status();
    } catch (\Throwable $e) {
      $results['phases']['academic'] = ['error' => $e->getMessage()];
    }

    self::$booted = true;
    $results['boot_time'] = round(microtime(true) - $start, 4);

    return $results;
  }

  /**
   * Full system cycle — called by cron/os.php.
   */
  public static function run(): array
  {
    self::init();
    $start   = microtime(true);
    $results = ['timestamp' => date('c'), 'phases' => []];

    ErrorCollector::log('os_kernel', '═══ OS Kernel cycle started ═══', 'INFO');

    // Phase 1: Boot
    $results['phases']['boot'] = self::boot();

    // Phase 2: Process Scheduler
    try {
      $results['phases']['scheduler'] = ProcessScheduler::tick();
    } catch (\Throwable $e) {
      $results['phases']['scheduler'] = ['error' => $e->getMessage()];
      ErrorCollector::log('os_kernel', 'Scheduler failed: ' . $e->getMessage(), 'HIGH');
    }

    // Phase 3: Automation Engine
    try {
      $results['phases']['automation'] = AutomationEngine::process();
    } catch (\Throwable $e) {
      $results['phases']['automation'] = ['error' => $e->getMessage()];
      ErrorCollector::log('os_kernel', 'Automation failed: ' . $e->getMessage(), 'HIGH');
    }

    // Phase 4: Communication OS
    try {
      $results['phases']['communication'] = CommunicationOS::healthCheck();
    } catch (\Throwable $e) {
      $results['phases']['communication'] = ['error' => $e->getMessage()];
    }

    // Phase 5: Resource Manager
    try {
      $results['phases']['resources'] = ResourceManager::audit();
    } catch (\Throwable $e) {
      $results['phases']['resources'] = ['error' => $e->getMessage()];
    }

    // Phase 6: Device Integration
    try {
      $results['phases']['devices'] = DeviceIntegration::syncStatus();
    } catch (\Throwable $e) {
      $results['phases']['devices'] = ['error' => $e->getMessage()];
    }

    // Phase 7: Healing Kernel (self-repair)
    try {
      $results['phases']['healing'] = HealingKernel::run();
    } catch (\Throwable $e) {
      $results['phases']['healing'] = ['error' => $e->getMessage()];
      ErrorCollector::log('os_kernel', 'Healing failed: ' . $e->getMessage(), 'HIGH');
    }

    // Calculate OS health score
    $phaseCount  = count($results['phases']);
    $errorCount  = 0;
    foreach ($results['phases'] as $phase) {
      if (isset($phase['error'])) {
        $errorCount++;
      }
    }
    $results['os_health']  = $phaseCount > 0 ? round((($phaseCount - $errorCount) / $phaseCount) * 100) : 0;
    $results['duration']   = round(microtime(true) - $start, 4);
    $results['phase_count'] = $phaseCount;

    // Persist summary
    $dir = dirname(self::$summaryPath);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(self::$summaryPath, json_encode($results, JSON_PRETTY_PRINT), LOCK_EX);

    EventBus::dispatch('os', 'cycle_complete', [
      'health'   => $results['os_health'],
      'duration' => $results['duration'],
    ]);

    ErrorCollector::log('os_kernel', "OS cycle complete — health: {$results['os_health']}/100, {$results['duration']}s", 'INFO');

    return $results;
  }

  /**
   * Get dashboard data.
   */
  public static function getDashboardData(): array
  {
    self::init();
    if (!is_file(self::$summaryPath)) {
      return ['os_health' => 0, 'phases' => [], 'timestamp' => null];
    }
    return json_decode(file_get_contents(self::$summaryPath), true) ?: [];
  }

  /**
   * Get OS uptime information.
   */
  public static function getUptime(): array
  {
    self::init();
    $data = self::getDashboardData();
    return [
      'last_cycle'  => $data['timestamp'] ?? 'never',
      'health'      => $data['os_health'] ?? 0,
      'phase_count' => $data['phase_count'] ?? 0,
      'duration'    => $data['duration'] ?? 0,
    ];
  }
}
