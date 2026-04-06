<?php

/**
 * MCC — OS Kernel Controller
 * Wraps OSKernel for Master Control Center integration.
 */
class OSController
{
  public static function getStatus(): array
  {
    try {
      $data = OSKernel::getDashboardData();
      $uptime = OSKernel::getUptime();

      // Phase summary
      $phases = $data['phases'] ?? [];
      $phaseCount = count($phases);
      $errorPhases = 0;
      foreach ($phases as $phase) {
        if (isset($phase['error'])) {
          $errorPhases++;
        }
      }

      return [
        'os_health'    => $data['os_health'] ?? 0,
        'boot_time'    => $data['phases']['boot']['boot_time'] ?? 0,
        'duration'     => $data['duration'] ?? 0,
        'phase_count'  => $phaseCount,
        'phase_errors' => $errorPhases,
        'last_cycle'   => $uptime['last_cycle'] ?? 'never',
        'phases'       => array_keys($phases),
        'timestamp'    => $data['timestamp'] ?? null,
      ];
    } catch (\Throwable $e) {
      return [
        'os_health'    => 0,
        'boot_time'    => 0,
        'duration'     => 0,
        'phase_count'  => 0,
        'phase_errors' => 0,
        'last_cycle'   => 'error',
        'phases'       => [],
        'timestamp'    => null,
        'error'        => $e->getMessage(),
      ];
    }
  }

  public static function runCycle(): array
  {
    try {
      return OSKernel::run();
    } catch (\Throwable $e) {
      return ['error' => $e->getMessage()];
    }
  }
}
