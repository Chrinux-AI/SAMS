<?php

/**
 * ACI Cron Job
 * Runs CommandBrain::cycle() every minute.
 * Usage: php cron/aci.php
 * Or via scheduled task / cron: * * * * * php /path/to/attendance/cron/aci.php
 */
define('CRON_MODE', true);
require_once __DIR__ . '/../includes/config.php';
require_once INCLUDES_PATH . '/functions.php';
require_once BASE_PATH . '/app/bootstrap.php';

try {
  // Run health governance check — auto-triggers ACI repair if health < 70
  if (class_exists('GovernanceEngine')) {
    $governance = GovernanceEngine::checkHealthGovernance();
    if ($governance['triggered']) {
      echo '[' . date('Y-m-d H:i:s') . '] Health governance triggered: '
        . 'Health=' . ($governance['health']['overall'] ?? '?')
        . ', Action=' . $governance['action']
        . PHP_EOL;
    }
  }

  $result = CommandBrain::cycle();
  echo '[' . date('Y-m-d H:i:s') . '] ACI cycle complete — '
    . 'Signal: ' . ($result['signal_score'] ?? '?')
    . ', Risk: ' . ($result['risk_level'] ?? '?')
    . ', Predictions: ' . ($result['predictions'] ?? 0)
    . ', Executed: ' . ($result['auto_executed'] ?? 0)
    . ', Cycle: ' . ($result['cycle_ms'] ?? 0) . 'ms'
    . PHP_EOL;
} catch (\Throwable $e) {
  echo '[' . date('Y-m-d H:i:s') . '] ACI cycle FAILED: ' . $e->getMessage() . PHP_EOL;
  if (class_exists('ErrorCollector')) {
    ErrorCollector::log('aci_cron', $e->getMessage(), 'CRITICAL');
  }
}
