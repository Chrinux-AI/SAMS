<?php

/**
 * ProcessScheduler — School Task Engine
 *
 * Manages scheduled tasks: attendance windows, reminders, report generation,
 * backup triggers, AI training cycles. Tracks execution history.
 */
class ProcessScheduler
{
  private static string $schedulePath = '';

  private static function init(): void
  {
    if (!self::$schedulePath) {
      self::$schedulePath = BASE_PATH . '/storage/process-schedule.json';
    }
  }

  /**
   * Execute one scheduler tick — runs due tasks.
   */
  public static function tick(): array
  {
    self::init();
    $schedule = self::loadSchedule();
    $now      = time();
    $executed = [];
    $skipped  = 0;

    foreach ($schedule as &$task) {
      if (!isset($task['next_run']) || $task['next_run'] > $now) {
        $skipped++;
        continue;
      }
      if (!isset($task['enabled']) || !$task['enabled']) {
        $skipped++;
        continue;
      }

      $result = self::executeTask($task);
      $task['last_run']    = $now;
      $task['last_result'] = $result['status'];
      $task['run_count']   = ($task['run_count'] ?? 0) + 1;

      // Calculate next run
      $task['next_run'] = $now + ($task['interval'] ?? 3600);

      $executed[] = [
        'name'   => $task['name'],
        'status' => $result['status'],
      ];
    }
    unset($task);

    self::saveSchedule($schedule);

    return [
      'executed' => count($executed),
      'skipped'  => $skipped,
      'tasks'    => $executed,
    ];
  }

  /**
   * Execute a single task by type.
   */
  private static function executeTask(array $task): array
  {
    $type = $task['type'] ?? 'unknown';
    try {
      switch ($type) {
        case 'attendance_window':
          return self::checkAttendanceWindow($task);
        case 'report_generation':
          return self::generateScheduledReport($task);
        case 'backup':
          return ['status' => 'ok', 'message' => 'Backup trigger dispatched'];
        case 'cleanup':
          return self::runCleanup($task);
        case 'notification':
          return self::sendScheduledNotification($task);
        default:
          return ['status' => 'skipped', 'message' => "Unknown type: {$type}"];
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('process_scheduler', "Task '{$task['name']}' failed: " . $e->getMessage(), 'MEDIUM');
      return ['status' => 'error', 'message' => $e->getMessage()];
    }
  }

  /**
   * Check if attendance window is active.
   */
  private static function checkAttendanceWindow(array $task): array
  {
    $hour = (int) date('H');
    $start = $task['config']['start_hour'] ?? 7;
    $end   = $task['config']['end_hour'] ?? 16;

    $active = ($hour >= $start && $hour <= $end && date('N') <= 5);

    if ($active) {
      EventBus::dispatch('academic', 'attendance_window_active', [
        'hour'  => $hour,
        'start' => $start,
        'end'   => $end,
      ]);
    }

    return ['status' => 'ok', 'active' => $active];
  }

  /**
   * Generate a scheduled report.
   */
  private static function generateScheduledReport(array $task): array
  {
    EventBus::dispatch('reports', 'scheduled_generation', [
      'report_type' => $task['config']['report_type'] ?? 'daily_attendance',
    ]);
    return ['status' => 'ok', 'message' => 'Report generation dispatched'];
  }

  /**
   * Cleanup old data.
   */
  private static function runCleanup(array $task): array
  {
    $cleaned = 0;
    $pdo = db()->getConnection();

    // Clean old activity logs (>90 days)
    if (table_exists('activity_log')) {
      $stmt = $pdo->prepare("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
      $stmt->execute();
      $cleaned += $stmt->rowCount();
    }

    return ['status' => 'ok', 'cleaned_rows' => $cleaned];
  }

  /**
   * Send scheduled notification.
   */
  private static function sendScheduledNotification(array $task): array
  {
    EventBus::dispatch('notifications', 'scheduled_send', [
      'target' => $task['config']['target'] ?? 'all',
      'message' => $task['config']['message'] ?? '',
    ]);
    return ['status' => 'ok'];
  }

  /**
   * Register a new scheduled task.
   */
  public static function register(string $name, string $type, int $intervalSeconds, array $config = []): void
  {
    self::init();
    $schedule = self::loadSchedule();

    // Prevent duplicates
    foreach ($schedule as $t) {
      if ($t['name'] === $name) return;
    }

    $schedule[] = [
      'name'       => $name,
      'type'       => $type,
      'interval'   => $intervalSeconds,
      'config'     => $config,
      'enabled'    => true,
      'next_run'   => time(),
      'last_run'   => null,
      'last_result' => null,
      'run_count'  => 0,
      'created'    => date('c'),
    ];

    self::saveSchedule($schedule);
  }

  /**
   * Get all scheduled tasks.
   */
  public static function getAll(): array
  {
    self::init();
    return self::loadSchedule();
  }

  /**
   * Get scheduler stats.
   */
  public static function getStats(): array
  {
    $tasks = self::getAll();
    $total   = count($tasks);
    $enabled = count(array_filter($tasks, fn($t) => $t['enabled'] ?? false));
    $totalRuns = array_sum(array_column($tasks, 'run_count'));

    return [
      'total_tasks'  => $total,
      'enabled'      => $enabled,
      'disabled'     => $total - $enabled,
      'total_runs'   => $totalRuns,
    ];
  }

  /**
   * Seed default tasks if schedule is empty.
   */
  public static function seedDefaults(): void
  {
    self::init();
    if (!empty(self::loadSchedule())) return;

    self::register('attendance_window_check', 'attendance_window', 300, [
      'start_hour' => 7,
      'end_hour' => 16,
    ]);
    self::register('daily_cleanup', 'cleanup', 86400, []);
    self::register('daily_report', 'report_generation', 86400, [
      'report_type' => 'daily_attendance',
    ]);
  }

  private static function loadSchedule(): array
  {
    self::init();
    if (!is_file(self::$schedulePath)) return [];
    $data = json_decode(file_get_contents(self::$schedulePath), true);
    return is_array($data) ? $data : [];
  }

  private static function saveSchedule(array $schedule): void
  {
    self::init();
    $dir = dirname(self::$schedulePath);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(self::$schedulePath, json_encode($schedule, JSON_PRETTY_PRINT), LOCK_EX);
  }
}
