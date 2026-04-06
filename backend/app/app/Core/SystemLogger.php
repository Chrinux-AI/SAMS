<?php

/**
 * SystemLogger — Blueprint Logging Doctrine Enforcement
 *
 * Every action is recorded in the format:
 *   [TIME] ACTOR ACTION FILES_CHANGED RESULT HEALTH_SCORE
 *
 * Writes to storage/logs/system.log (human-readable)
 * and storage/system-log.json (structured for dashboards).
 *
 * Provides a unified interface that all system components should use
 * for governance-compliant logging.
 */
class SystemLogger
{
  private static string $logDir = '';

  private static function logDir(): string
  {
    if (!self::$logDir) {
      self::$logDir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/logs';
      if (!is_dir(self::$logDir)) {
        mkdir(self::$logDir, 0755, true);
      }
    }
    return self::$logDir;
  }

  /**
   * Log an action in blueprint-mandated format.
   *
   * @param string $actor         Who performed the action: user/ai/system/cron
   * @param string $action        What happened (verb/description)
   * @param array  $filesChanged  Relative paths of files affected
   * @param string $result        Outcome: success/failure/blocked/warning
   * @param ?int   $healthScore   Current health score (auto-fetched if null)
   */
  public static function log(
    string $actor,
    string $action,
    array  $filesChanged = [],
    string $result = 'success',
    ?int   $healthScore = null
  ): void {
    if ($healthScore === null) {
      try {
        $healthScore = class_exists('SystemHealthScore')
          ? SystemHealthScore::calculate()['overall']
          : 0;
      } catch (\Throwable $e) {
        $healthScore = 0;
      }
    }

    $time = date('Y-m-d H:i:s');
    $filesList = implode(',', $filesChanged) ?: 'none';

    // Blueprint-mandated line format
    $line = sprintf(
      "[%s] ACTOR=%s ACTION=%s FILES=%s RESULT=%s HEALTH=%d",
      $time,
      $actor,
      $action,
      $filesList,
      $result,
      $healthScore
    );

    // Write to system.log
    file_put_contents(
      self::logDir() . '/system.log',
      $line . PHP_EOL,
      FILE_APPEND | LOCK_EX
    );

    // Write structured entry to JSON store
    $entry = [
      'time'          => $time,
      'actor'         => $actor,
      'action'        => $action,
      'files_changed' => $filesChanged,
      'result'        => $result,
      'health_score'  => $healthScore,
    ];

    $jsonPath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/system-log.json';
    $existing = [];
    if (is_file($jsonPath)) {
      $raw = file_get_contents($jsonPath);
      $existing = json_decode($raw, true) ?: [];
    }
    $existing[] = $entry;
    $existing = array_slice($existing, -1000); // Keep last 1000
    file_put_contents($jsonPath, json_encode($existing, JSON_PRETTY_PRINT), LOCK_EX);
  }

  /**
   * Convenience: log a user action.
   */
  public static function logUser(string $action, array $files = [], string $result = 'success'): void
  {
    $actor = 'user';
    if (isset($_SESSION['user_id'])) {
      $actor = 'user:' . ($_SESSION['user_id'] ?? '?');
    }
    self::log($actor, $action, $files, $result);
  }

  /**
   * Convenience: log an AI action.
   */
  public static function logAI(string $action, array $files = [], string $result = 'success'): void
  {
    self::log('ai', $action, $files, $result);
  }

  /**
   * Convenience: log a system/cron action.
   */
  public static function logSystem(string $action, array $files = [], string $result = 'success'): void
  {
    self::log('system', $action, $files, $result);
  }

  /**
   * Get recent log entries (for dashboard).
   *
   * @param int    $limit  Max entries to return
   * @param string $actor  Filter by actor (empty = all)
   */
  public static function getRecent(int $limit = 50, string $actor = ''): array
  {
    $jsonPath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/system-log.json';
    if (!is_file($jsonPath)) {
      return [];
    }
    $all = json_decode(file_get_contents($jsonPath), true) ?: [];

    if ($actor) {
      $all = array_filter($all, fn($e) => str_starts_with($e['actor'] ?? '', $actor));
      $all = array_values($all);
    }

    return array_slice(array_reverse($all), 0, $limit);
  }

  /**
   * Get log statistics for dashboard.
   */
  public static function getStats(): array
  {
    $jsonPath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/system-log.json';
    if (!is_file($jsonPath)) {
      return ['total' => 0, 'by_actor' => [], 'by_result' => [], 'recent_health' => []];
    }

    $all = json_decode(file_get_contents($jsonPath), true) ?: [];

    $byActor = [];
    $byResult = [];
    $recentHealth = [];

    foreach ($all as $entry) {
      $actorBase = explode(':', $entry['actor'] ?? 'unknown')[0];
      $byActor[$actorBase] = ($byActor[$actorBase] ?? 0) + 1;
      $result = $entry['result'] ?? 'unknown';
      $byResult[$result] = ($byResult[$result] ?? 0) + 1;
    }

    // Last 20 health scores for trend
    $recentEntries = array_slice($all, -20);
    foreach ($recentEntries as $e) {
      if (isset($e['health_score']) && $e['health_score'] > 0) {
        $recentHealth[] = [
          'time'  => $e['time'] ?? '',
          'score' => $e['health_score'],
        ];
      }
    }

    return [
      'total'         => count($all),
      'by_actor'      => $byActor,
      'by_result'     => $byResult,
      'recent_health' => $recentHealth,
    ];
  }
}
