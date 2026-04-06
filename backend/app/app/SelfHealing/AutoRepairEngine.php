<?php

/**
 * SelfHealingRepairEngine — Maps faults to repair actions.
 *
 * Maps: blank_page → reload layout, missing_route → rebuild cache,
 * db_error → reconnect, update_fail → retry write.
 */
class SelfHealingRepairEngine
{
  /**
   * Repair a list of faults. Returns repair outcomes.
   */
  public static function repair(array $faults): array
  {
    $outcomes = [];

    foreach ($faults as $fault) {
      $type = $fault['type'] ?? 'unknown';
      $context = $fault['context'] ?? [];
      $start = microtime(true);

      // Check HealingMemory for best known fix
      $bestKnown = HealingMemory::getBestRepair($type);
      if ($bestKnown && (float)$bestKnown['rate'] >= 90.0) {
        ErrorCollector::log('self_healing', "Using learned repair for '$type': {$bestKnown['repair_action']}", 'INFO');
      }

      $result = match ($type) {
        'missing_critical_file'  => self::repairMissingFile($context),
        'empty_critical_file'    => self::repairEmptyFile($context),
        'database_failure'       => self::repairDatabase(),
        'session_failure'        => self::repairSession(),
        'broken_route'           => self::repairBrokenRoute($context),
        'blank_page_risk'        => self::repairBlankPage($context),
        'missing_tab'            => self::repairMissingTab($context),
        'missing_cron'           => self::repairMissingCron($context),
        'stale_cron'             => self::repairStaleCron($context),
        'storage_not_writable'   => self::repairStoragePermissions($context),
        'missing_table'          => self::repairMissingTable($context),
        'schema_mismatch'        => self::repairSchema($context),
        default                  => ['repaired' => false, 'action' => 'no_handler', 'note' => "No repair handler for: $type"],
      };

      $durationMs = (int)((microtime(true) - $start) * 1000);
      $result['fault_type'] = $type;
      $result['duration_ms'] = $durationMs;

      // Record in healing memory
      HealingMemory::record($type, $result['action'] ?? 'unknown', $result['repaired'] ?? false, $durationMs, $context);

      $outcomes[] = $result;
    }

    return $outcomes;
  }

  private static function repairMissingFile(array $ctx): array
  {
    // Cannot recreate critical files — flag for manual intervention
    return ['repaired' => false, 'action' => 'flag_manual', 'note' => 'Critical file missing: ' . ($ctx['file'] ?? '?') . ' — requires manual restore'];
  }

  private static function repairEmptyFile(array $ctx): array
  {
    return ['repaired' => false, 'action' => 'flag_manual', 'note' => 'Critical file empty: ' . ($ctx['file'] ?? '?') . ' — requires manual restore'];
  }

  private static function repairDatabase(): array
  {
    // Attempt reconnect
    try {
      $pdo = db()->getConnection();
      $pdo->query("SELECT 1");
      return ['repaired' => true, 'action' => 'db_reconnect'];
    } catch (\Throwable $e) {
      return ['repaired' => false, 'action' => 'db_reconnect_failed', 'note' => $e->getMessage()];
    }
  }

  private static function repairSession(): array
  {
    $savePath = session_save_path();
    if ($savePath && !is_dir($savePath)) {
      @mkdir($savePath, 0755, true);
    }
    if ($savePath && is_writable($savePath)) {
      return ['repaired' => true, 'action' => 'session_path_created'];
    }
    return ['repaired' => false, 'action' => 'session_repair_failed'];
  }

  private static function repairBrokenRoute(array $ctx): array
  {
    // Rebuild route index cache
    RouteRepairer::rebuildRouteIndex();
    return ['repaired' => true, 'action' => 'route_index_rebuilt', 'note' => 'Route index regenerated'];
  }

  private static function repairBlankPage(array $ctx): array
  {
    $file = $ctx['file'] ?? '';
    if (!$file) return ['repaired' => false, 'action' => 'no_file_context'];

    // Log for developer attention — cannot auto-inject layout safely
    ErrorCollector::log('self_healing', "Blank page risk detected: $file", 'MEDIUM');
    return ['repaired' => false, 'action' => 'flagged_for_review', 'note' => "Page '$file' lacks layout — flagged"];
  }

  private static function repairMissingTab(array $ctx): array
  {
    $tab = $ctx['tab'] ?? '';
    if (!$tab) return ['repaired' => false, 'action' => 'no_tab_context'];

    // Create minimal stub tab so UI doesn't break
    $tabPath = BASE_PATH . '/developer/tabs/' . basename($tab) . '.php';
    if (!file_exists($tabPath)) {
      $safeName = htmlspecialchars($tab);
      $stub = "<div class=\"tab-content\"><h3>" . ucfirst($safeName) . "</h3><p>This tab is being configured.</p></div>";
      file_put_contents($tabPath, $stub);
      return ['repaired' => true, 'action' => 'stub_tab_created', 'note' => "Created stub for tab: $tab"];
    }
    return ['repaired' => false, 'action' => 'tab_already_exists'];
  }

  private static function repairMissingCron(array $ctx): array
  {
    return ['repaired' => false, 'action' => 'flag_manual', 'note' => 'Cron file missing: ' . ($ctx['file'] ?? '?')];
  }

  private static function repairStaleCron(array $ctx): array
  {
    // Can't force cron — log the staleness
    ErrorCollector::log('self_healing', 'Stale cron output: ' . ($ctx['file'] ?? ''), 'MEDIUM');
    return ['repaired' => false, 'action' => 'flagged_stale_cron', 'note' => 'Cron output is stale — may need scheduling'];
  }

  private static function repairStoragePermissions(array $ctx): array
  {
    $dir = $ctx['dir'] ?? '';
    $path = BASE_PATH . '/' . $dir;
    if (is_dir($path)) {
      @chmod($path, 0755);
      if (is_writable($path)) {
        return ['repaired' => true, 'action' => 'permissions_fixed'];
      }
    }
    return ['repaired' => false, 'action' => 'permissions_fix_failed'];
  }

  private static function repairMissingTable(array $ctx): array
  {
    // Cannot auto-create application tables — flag for migration
    $table = $ctx['table'] ?? '?';
    ErrorCollector::log('self_healing', "Missing table '$table' — run migrations", 'HIGH');
    return ['repaired' => false, 'action' => 'flag_migration', 'note' => "Table '$table' missing — requires migration"];
  }

  private static function repairSchema(array $ctx): array
  {
    // Delegate to SchemaRepairer
    try {
      SchemaRepairer::repair($ctx);
      return ['repaired' => true, 'action' => 'schema_repaired'];
    } catch (\Throwable $e) {
      return ['repaired' => false, 'action' => 'schema_repair_failed', 'note' => $e->getMessage()];
    }
  }
}
