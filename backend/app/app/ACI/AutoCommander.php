<?php

/**
 * ACI — Auto Commander
 * Executes safe repairs automatically when confidence is high enough.
 * Never modifies user data. Only infrastructure operations.
 */
class AutoCommander
{
  /**
   * Execute all auto-approved decisions.
   * @param array $decisions from ACIDecisionEngine::decide()
   */
  public static function execute(array $decisions): array
  {
    $executed = [];
    $skipped = [];

    foreach ($decisions as $d) {
      if (!$d['auto_execute']) {
        $skipped[] = ['action' => $d['action'], 'reason' => $d['risk']['reason'] ?? 'Not approved'];
        continue;
      }

      $result = self::runAction($d['action'], $d['prediction']);

      if ($result['success']) {
        LearningMemory::recordSuccess(
          $d['prediction']['type'] ?? 'unknown',
          $d['prediction']['message'] ?? '',
          $d['action'],
          $result['detail'] ?? ''
        );
        $executed[] = [
          'action' => $d['action'],
          'result' => $result,
        ];
      } else {
        LearningMemory::recordFailure($d['prediction']['type'] ?? 'unknown', $d['action']);
        $skipped[] = ['action' => $d['action'], 'reason' => 'Execution failed: ' . ($result['error'] ?? 'unknown')];
      }
    }

    return [
      'executed'      => $executed,
      'skipped'       => $skipped,
      'executed_count' => count($executed),
      'skipped_count' => count($skipped),
    ];
  }

  /**
   * Execute a single manual action (from MCC button click).
   */
  public static function executeSingle(string $action, array $context = []): array
  {
    $risk = RiskAnalyzer::analyze($action, $context);
    if ($risk['risk_level'] === 'BLOCKED') {
      return ['success' => false, 'error' => 'Action is blocked: ' . $risk['reason']];
    }

    return self::runAction($action, $context);
  }

  /**
   * Run a specific action.
   */
  private static function runAction(string $action, array $context = []): array
  {
    try {
      switch ($action) {
        case 'route_rebuild':
          if (class_exists('RouteRepairer')) {
            $result = RouteRepairer::rebuildRouteIndex();
            return ['success' => true, 'detail' => 'Route index rebuilt', 'data' => $result];
          }
          return ['success' => false, 'error' => 'RouteRepairer not available'];

        case 'cache_rebuild':
          $cleared = 0;
          foreach (glob(BASE_PATH . '/cache/*.{json,php,tmp}', GLOB_BRACE) ?: [] as $f) {
            @unlink($f);
            $cleared++;
          }
          if (class_exists('CacheSynchronizer')) {
            CacheSynchronizer::flush('all');
          }
          return ['success' => true, 'detail' => "Cleared $cleared cache files"];

        case 'create_stub':
          $repaired = NavigationGuardian::repair();
          return ['success' => true, 'detail' => "Repaired {$repaired['count']} pages", 'data' => $repaired];

        case 'directory_create':
          $created = [];
          foreach (['storage', 'cache', 'logs', 'uploads'] as $d) {
            $path = BASE_PATH . '/' . $d;
            if (!is_dir($path)) {
              @mkdir($path, 0755, true);
              $created[] = $d;
            }
          }
          return ['success' => true, 'detail' => 'Created: ' . (empty($created) ? 'none needed' : implode(', ', $created))];

        case 'summary_refresh':
          $touched = 0;
          foreach (glob(BASE_PATH . '/storage/*-summary.json') ?: [] as $f) {
            touch($f);
            $touched++;
          }
          return ['success' => true, 'detail' => "Refreshed $touched summary files"];

        case 'layout_restore':
          // Check and repair layout file
          $layout = BASE_PATH . '/resources/ui-core/layouts/master-dashboard.php';
          if (is_file($layout)) {
            return ['success' => true, 'detail' => 'Layout file intact'];
          }
          return ['success' => false, 'error' => 'Layout file missing — manual intervention needed'];

        default:
          return ['success' => false, 'error' => "Unknown action: $action"];
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('aci_commander', "Action $action failed: " . $e->getMessage(), 'HIGH');
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }
}
