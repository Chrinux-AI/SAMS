<?php

/**
 * ACI — Risk Analyzer
 * Evaluates risk before executing auto-repairs.
 * Blocks dangerous actions, requires approval for risky ones.
 */
class RiskAnalyzer
{
  // Actions always safe to auto-execute
  private static array $safeActions = [
    'cache_rebuild',
    'route_rebuild',
    'create_stub',
    'layout_restore',
    'directory_create',
    'summary_refresh',
  ];

  // Actions that need developer approval
  private static array $riskyActions = [
    'schema_alter',
    'table_repair',
    'service_restart',
    'user_data_modify',
    'session_clear',
    'db_optimize',
  ];

  // Actions never allowed automatically
  private static array $blockedActions = [
    'table_drop',
    'user_delete',
    'data_truncate',
    'file_delete_bulk',
  ];

  /**
   * Analyze risk of an action.
   * @return array{safe: bool, risk_level: string, reason: string}
   */
  public static function analyze(string $action, array $context = []): array
  {
    // Always blocked
    if (in_array($action, self::$blockedActions, true)) {
      return ['safe' => false, 'risk_level' => 'BLOCKED', 'reason' => 'Action is permanently blocked from auto-execution'];
    }

    // Always safe
    if (in_array($action, self::$safeActions, true)) {
      return ['safe' => true, 'risk_level' => 'NONE', 'reason' => 'Safe operation'];
    }

    // Risky — check conditions
    if (in_array($action, self::$riskyActions, true)) {
      // Check active sessions
      $sessionPath = session_save_path() ?: sys_get_temp_dir();
      $sessionCount = count(glob($sessionPath . '/sess_*') ?: []);
      if ($sessionCount > 10 && in_array($action, ['session_clear', 'service_restart'])) {
        return ['safe' => false, 'risk_level' => 'HIGH', 'reason' => "Too many active sessions ($sessionCount) — requires approval"];
      }

      return ['safe' => false, 'risk_level' => 'MEDIUM', 'reason' => 'Requires developer approval'];
    }

    // Unknown action — default to cautious
    return ['safe' => false, 'risk_level' => 'UNKNOWN', 'reason' => 'Action not classified — approval recommended'];
  }

  /**
   * Get confidence threshold for auto-execution.
   */
  public static function getAutoThreshold(): float
  {
    return 0.9;
  }
}
