<?php

/**
 * AutomationEngine — Rule-Based Automation
 *
 * Processes automation rules: absent notifications, disciplinary escalation,
 * fee reminders, report triggers, parent alerts.
 */
class AutomationEngine
{
  private static string $rulesPath = '';

  private static function init(): void
  {
    if (!self::$rulesPath) {
      self::$rulesPath = BASE_PATH . '/storage/automation-rules.json';
    }
  }

  /**
   * Process all active automation rules.
   */
  public static function process(): array
  {
    self::init();
    $rules     = self::loadRules();
    $processed = 0;
    $triggered = 0;
    $results   = [];

    foreach ($rules as &$rule) {
      if (!($rule['enabled'] ?? true)) continue;
      $processed++;

      try {
        $fired = self::evaluateRule($rule);
        if ($fired) {
          $triggered++;
          $rule['last_triggered'] = date('c');
          $rule['trigger_count']  = ($rule['trigger_count'] ?? 0) + 1;
          $results[] = ['rule' => $rule['name'], 'status' => 'triggered'];
        }
      } catch (\Throwable $e) {
        ErrorCollector::log('automation_engine', "Rule '{$rule['name']}' failed: " . $e->getMessage(), 'MEDIUM');
        $results[] = ['rule' => $rule['name'], 'status' => 'error', 'message' => $e->getMessage()];
      }
    }
    unset($rule);

    self::saveRules($rules);

    return [
      'processed' => $processed,
      'triggered' => $triggered,
      'results'   => $results,
    ];
  }

  /**
   * Evaluate a single rule.
   */
  private static function evaluateRule(array $rule): bool
  {
    $type = $rule['type'] ?? '';
    switch ($type) {
      case 'absent_alert':
        return self::checkAbsentAlert($rule);
      case 'chronic_absenteeism':
        return self::checkChronicAbsenteeism($rule);
      case 'late_pattern':
        return self::checkLatePattern($rule);
      case 'system_alert':
        return self::checkSystemAlert($rule);
      default:
        return false;
    }
  }

  /**
   * Check for absent students and trigger notifications.
   */
  private static function checkAbsentAlert(array $rule): bool
  {
    try {
      $today  = date('Y-m-d');
      $absent = db()->fetchAll(
        "SELECT a.student_id, u.first_name, u.last_name
         FROM attendance a
         JOIN users u ON u.id = a.student_id
         WHERE a.date = ? AND a.status = 'absent'
         LIMIT 100",
        [$today]
      );

      if (empty($absent)) return false;

      EventBus::dispatch('automation', 'absent_alert_fired', [
        'date'    => $today,
        'count'   => count($absent),
        'students' => array_map(fn($s) => $s['first_name'] . ' ' . $s['last_name'], $absent),
      ]);

      return true;
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Check for chronic absenteeism.
   */
  private static function checkChronicAbsenteeism(array $rule): bool
  {
    try {
      $threshold = $rule['config']['threshold'] ?? (defined('CHRONIC_ABSENTEEISM_THRESHOLD') ? CHRONIC_ABSENTEEISM_THRESHOLD : 10);
      $days = $rule['config']['days'] ?? 30;

      $startDate = date('Y-m-d', strtotime("-{$days} days"));
      $chronic = db()->fetchAll(
        "SELECT student_id,
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days
         FROM attendance
         WHERE date >= ?
         GROUP BY student_id
         HAVING (absent_days / total_days * 100) > ?
         LIMIT 50",
        [$startDate, $threshold]
      );

      if (empty($chronic)) return false;

      EventBus::dispatch('automation', 'chronic_absenteeism_detected', [
        'count'     => count($chronic),
        'threshold' => $threshold,
      ]);

      return true;
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Check for late arrival patterns.
   */
  private static function checkLatePattern(array $rule): bool
  {
    try {
      $days = $rule['config']['days'] ?? 14;
      $minLate = $rule['config']['min_late'] ?? 3;

      $startDate = date('Y-m-d', strtotime("-{$days} days"));
      $patterns = db()->fetchAll(
        "SELECT student_id, COUNT(*) as late_count
         FROM attendance
         WHERE date >= ? AND status = 'late'
         GROUP BY student_id
         HAVING late_count >= ?
         LIMIT 50",
        [$startDate, $minLate]
      );

      if (empty($patterns)) return false;

      EventBus::dispatch('automation', 'late_pattern_detected', [
        'count'    => count($patterns),
        'min_late' => $minLate,
        'days'     => $days,
      ]);

      return true;
    } catch (\Throwable $e) {
      return false;
    }
  }

  /**
   * Check system alerts.
   */
  private static function checkSystemAlert(array $rule): bool
  {
    $healthFile = BASE_PATH . '/storage/healing-summary.json';
    if (!is_file($healthFile)) return false;

    $health = json_decode(file_get_contents($healthFile), true);
    $score  = $health['stability_score'] ?? 100;
    $threshold = $rule['config']['threshold'] ?? 50;

    if ($score < $threshold) {
      EventBus::dispatch('automation', 'system_health_low', [
        'score'     => $score,
        'threshold' => $threshold,
      ]);
      return true;
    }

    return false;
  }

  /**
   * Register a new automation rule.
   */
  public static function register(string $name, string $type, array $config = []): void
  {
    self::init();
    $rules = self::loadRules();
    foreach ($rules as $r) {
      if ($r['name'] === $name) return;
    }

    $rules[] = [
      'name'           => $name,
      'type'           => $type,
      'config'         => $config,
      'enabled'        => true,
      'trigger_count'  => 0,
      'last_triggered' => null,
      'created'        => date('c'),
    ];

    self::saveRules($rules);
  }

  /**
   * Seed default automation rules.
   */
  public static function seedDefaults(): void
  {
    self::init();
    if (!empty(self::loadRules())) return;

    self::register('daily_absent_alert', 'absent_alert', []);
    self::register('chronic_absenteeism_check', 'chronic_absenteeism', [
      'threshold' => 10,
      'days' => 30,
    ]);
    self::register('late_pattern_alert', 'late_pattern', [
      'days' => 14,
      'min_late' => 3,
    ]);
    self::register('system_health_monitor', 'system_alert', [
      'threshold' => 50,
    ]);
  }

  /**
   * Get all rules.
   */
  public static function getRules(): array
  {
    self::init();
    return self::loadRules();
  }

  /**
   * Get automation stats.
   */
  public static function getStats(): array
  {
    $rules = self::getRules();
    return [
      'total_rules'    => count($rules),
      'enabled'        => count(array_filter($rules, fn($r) => $r['enabled'] ?? false)),
      'total_triggers' => array_sum(array_column($rules, 'trigger_count')),
    ];
  }

  private static function loadRules(): array
  {
    self::init();
    if (!is_file(self::$rulesPath)) return [];
    $data = json_decode(file_get_contents(self::$rulesPath), true);
    return is_array($data) ? $data : [];
  }

  private static function saveRules(array $rules): void
  {
    self::init();
    $dir = dirname(self::$rulesPath);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(self::$rulesPath, json_encode($rules, JSON_PRETTY_PRINT), LOCK_EX);
  }
}
