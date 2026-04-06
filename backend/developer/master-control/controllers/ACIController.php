<?php

/**
 * MCC — ACI Controller
 * Provides ACI status and actions for the Master Control Center.
 */
class ACIController
{
  public static function getStatus(): array
  {
    return CommandBrain::getStatus();
  }

  public static function runCycle(): array
  {
    return CommandBrain::cycle();
  }

  public static function executeAction(string $action, array $context = []): array
  {
    return CommandBrain::executeManual($action, $context);
  }
}
