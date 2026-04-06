<?php

/**
 * ServiceRestarter — Automatically recovers failed subsystems.
 *
 * Monitors: session engine, AI services, cron workers, email queue, cache.
 * If failure detected: restart silently + log recovery.
 */
class ServiceRestarter
{
  /**
   * Check and restart all monitored services.
   */
  public static function checkAll(): array
  {
    $results = [];
    $results['session']  = self::checkSession();
    $results['cache']    = self::checkCache();
    $results['storage']  = self::checkStorage();
    $results['ai']       = self::checkAIServices();
    $results['eventbus'] = self::checkEventBus();
    return $results;
  }

  /**
   * Check and repair session engine.
   */
  private static function checkSession(): array
  {
    if (php_sapi_name() === 'cli') {
      return ['service' => 'session', 'status' => 'skipped', 'note' => 'CLI mode'];
    }

    $savePath = session_save_path();
    if ($savePath && !is_writable($savePath)) {
      @mkdir($savePath, 0755, true);
      if (is_writable($savePath)) {
        ErrorCollector::log('self_healing', 'Session path restored', 'INFO');
        return ['service' => 'session', 'status' => 'restarted'];
      }
      return ['service' => 'session', 'status' => 'failed', 'note' => 'Path not writable'];
    }
    return ['service' => 'session', 'status' => 'healthy'];
  }

  /**
   * Check and repair cache directory.
   */
  private static function checkCache(): array
  {
    $cacheDir = BASE_PATH . '/cache';
    if (!is_dir($cacheDir)) {
      @mkdir($cacheDir, 0755, true);
      if (is_dir($cacheDir)) {
        return ['service' => 'cache', 'status' => 'restarted', 'note' => 'Created cache dir'];
      }
      return ['service' => 'cache', 'status' => 'failed'];
    }
    if (!is_writable($cacheDir)) {
      @chmod($cacheDir, 0755);
      return ['service' => 'cache', 'status' => is_writable($cacheDir) ? 'restarted' : 'failed'];
    }
    return ['service' => 'cache', 'status' => 'healthy'];
  }

  /**
   * Check and repair storage directory.
   */
  private static function checkStorage(): array
  {
    $storageDir = BASE_PATH . '/storage';
    if (!is_dir($storageDir)) {
      @mkdir($storageDir, 0755, true);
      if (is_dir($storageDir)) {
        return ['service' => 'storage', 'status' => 'restarted'];
      }
      return ['service' => 'storage', 'status' => 'failed'];
    }
    return ['service' => 'storage', 'status' => 'healthy'];
  }

  /**
   * Check AI service classes are loadable.
   */
  private static function checkAIServices(): array
  {
    $services = ['ErrorCollector', 'CognitiveKernel', 'IntelligenceKernel', 'EcosystemKernel'];
    $failed = [];

    foreach ($services as $svc) {
      if (!class_exists($svc)) {
        $failed[] = $svc;
      }
    }

    if (!empty($failed)) {
      ErrorCollector::log('self_healing', 'AI services unavailable: ' . implode(', ', $failed), 'HIGH');
      return ['service' => 'ai', 'status' => 'degraded', 'missing' => $failed];
    }
    return ['service' => 'ai', 'status' => 'healthy'];
  }

  /**
   * Check EventBus state.
   */
  private static function checkEventBus(): array
  {
    if (!class_exists('EventBus')) {
      return ['service' => 'eventbus', 'status' => 'unavailable'];
    }

    try {
      EventBus::dispatch('health', 'heartbeat', ['source' => 'ServiceRestarter']);
      return ['service' => 'eventbus', 'status' => 'healthy'];
    } catch (\Throwable $e) {
      ErrorCollector::log('self_healing', 'EventBus failure: ' . $e->getMessage(), 'HIGH');
      return ['service' => 'eventbus', 'status' => 'failed', 'note' => $e->getMessage()];
    }
  }

  /**
   * Restart a specific service by name.
   */
  public static function restart(string $service): array
  {
    return match ($service) {
      'session'  => self::checkSession(),
      'cache'    => self::checkCache(),
      'storage'  => self::checkStorage(),
      'ai'       => self::checkAIServices(),
      'eventbus' => self::checkEventBus(),
      default    => ['service' => $service, 'status' => 'unknown'],
    };
  }

  public static function getSummary(): array
  {
    $all = self::checkAll();
    $healthy = 0;
    $total = count($all);
    foreach ($all as $svc) {
      if (($svc['status'] ?? '') === 'healthy' || ($svc['status'] ?? '') === 'skipped') $healthy++;
    }
    return [
      'total_services' => $total,
      'healthy'        => $healthy,
      'status'         => $healthy === $total ? 'all_healthy' : 'degraded',
      'details'        => $all,
    ];
  }
}
