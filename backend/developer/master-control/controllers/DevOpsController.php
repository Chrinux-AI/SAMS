<?php

/**
 * MCC — DevOps Command Center Controller
 * Deployment, cron, workers, backups, fix loop.
 */
class DevOpsController
{
  public static function getStatus(): array
  {
    $data = [
      'last_deployment' => 'N/A',
      'cron_health'     => 'unknown',
      'backup_status'   => 'unknown',
      'fix_loop_last'   => 'N/A',
      'workers'         => [],
      'devops_score'    => 0,
    ];

    // DevOps kernel data
    try {
      $devops = DevOpsKernel::getDashboardData();
      $lastRun = $devops['last_run'] ?? [];
      $data['devops_score'] = $lastRun['system_score'] ?? 0;
      $data['last_deployment'] = $lastRun['timestamp'] ?? 'N/A';
      $data['metrics'] = $devops['metrics'] ?? [];
      $data['drift'] = $devops['drift'] ?? [];
      $data['incidents'] = array_slice($devops['incidents'] ?? [], 0, 5);
      $data['deployment_safe'] = $devops['deployment_safe'] ?? false;
    } catch (\Throwable $e) {
    }

    // Cron health — check cron log
    try {
      $cronLog = BASE_PATH . '/logs/cron.log';
      if (is_file($cronLog)) {
        $lastMod = filemtime($cronLog);
        $age = time() - $lastMod;
        $data['cron_health'] = $age < 3600 ? 'healthy' : ($age < 86400 ? 'stale' : 'dead');
        $data['cron_last_run'] = date('Y-m-d H:i:s', $lastMod);
      }
    } catch (\Throwable $e) {
    }

    // Backup status
    try {
      $backups = glob(BASE_PATH . '/backups/*.sql') ?: glob(BASE_PATH . '/backups/*.sql.gz') ?: [];
      $data['backup_count'] = count($backups);
      if (!empty($backups)) {
        usort($backups, fn($a, $b) => filemtime($b) - filemtime($a));
        $data['backup_status'] = 'available';
        $data['backup_latest'] = date('Y-m-d H:i:s', filemtime($backups[0]));
      }
    } catch (\Throwable $e) {
    }

    // Fix loop last run
    try {
      $fixFile = BASE_PATH . '/storage/fix-loop-summary.json';
      if (is_file($fixFile)) {
        $fix = json_decode(file_get_contents($fixFile), true);
        $data['fix_loop_last'] = $fix['timestamp'] ?? 'N/A';
        $data['fix_loop_score'] = $fix['score'] ?? 0;
      }
    } catch (\Throwable $e) {
    }

    return $data;
  }

  public static function runAutoFix(): array
  {
    try {
      AuditLogger::log('manual_autofix', 'system', 'Manual auto-fix triggered from MCC', $_SESSION['user_id'] ?? null);
    } catch (\Throwable $e) {
    }

    try {
      $result = AutonomousFixLoop::run();
      return ['status' => 'completed', 'result' => $result];
    } catch (\Throwable $e) {
      return ['status' => 'error', 'message' => $e->getMessage()];
    }
  }

  public static function rebuildRoutes(): array
  {
    try {
      $result = RouteRepairer::repair();
      return ['status' => 'completed', 'result' => $result];
    } catch (\Throwable $e) {
      return ['status' => 'error', 'message' => $e->getMessage()];
    }
  }
}
