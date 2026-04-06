<?php

/**
 * MCC — AIC Controller
 * Provides Institutional Consciousness status for the Master Control Center.
 */
class AICController
{
  public static function getStatus(): array
  {
    return InstitutionBrain::getStatus();
  }

  public static function runCycle(): array
  {
    return InstitutionBrain::cycle();
  }
}
