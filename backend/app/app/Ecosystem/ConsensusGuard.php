<?php

/**
 * ConsensusGuard — Prevents Harmful Ecosystem Learning
 *
 * Rules:
 *   - Pattern must appear across multiple institutions
 *   - Anomaly check required
 *   - Ethical validation enforced
 *   - Single-school behavior ≠ ecosystem truth
 */
class ConsensusGuard
{
  private static int $minimumConsensus = 3; // min institutions for pattern validity

  /**
   * Validate a pattern for ecosystem-wide adoption.
   */
  public static function validate(array $pattern): array
  {
    $checks = [];
    $allowed = true;

    // 1. Multi-institution consensus
    $sourceCount = $pattern['source_count'] ?? 1;
    if ($sourceCount < self::$minimumConsensus) {
      $checks[] = [
        'check'  => 'consensus',
        'passed' => false,
        'reason' => "Pattern only observed in {$sourceCount} institution(s), minimum " . self::$minimumConsensus . " required",
      ];
      $allowed = false;
    } else {
      $checks[] = ['check' => 'consensus', 'passed' => true, 'reason' => "Observed across {$sourceCount} institutions"];
    }

    // 2. Anomaly detection
    $anomalyResult = self::detectAnomaly($pattern);
    $checks[] = $anomalyResult;
    if (!$anomalyResult['passed']) $allowed = false;

    // 3. Ethical validation
    $ethicalResult = self::ethicalCheck($pattern);
    $checks[] = $ethicalResult;
    if (!$ethicalResult['passed']) $allowed = false;

    // 4. Confidence threshold
    $confidence = $pattern['confidence'] ?? 0;
    if ($confidence < 0.6) {
      $checks[] = ['check' => 'confidence', 'passed' => false, 'reason' => "Confidence {$confidence} below threshold 0.6"];
      $allowed = false;
    } else {
      $checks[] = ['check' => 'confidence', 'passed' => true, 'reason' => "Confidence {$confidence} meets threshold"];
    }

    // 5. Age check — pattern must persist over time
    $ageHours = $pattern['age_hours'] ?? 0;
    if ($ageHours < 168) { // 7 days minimum
      $checks[] = ['check' => 'persistence', 'passed' => false, 'reason' => 'Pattern less than 7 days old'];
      $allowed = false;
    } else {
      $checks[] = ['check' => 'persistence', 'passed' => true, 'reason' => "Pattern persisted {$ageHours} hours"];
    }

    if ($allowed) {
      ErrorCollector::log('ecosystem', 'Pattern approved by ConsensusGuard: ' . ($pattern['name'] ?? 'unnamed'), 'INFO');
    } else {
      ErrorCollector::log('ecosystem', 'Pattern rejected by ConsensusGuard: ' . ($pattern['name'] ?? 'unnamed'), 'MEDIUM');
    }

    return [
      'allowed' => $allowed,
      'checks'  => $checks,
      'pattern' => $pattern['name'] ?? 'unnamed',
    ];
  }

  /**
   * Detect statistical anomalies in pattern data.
   */
  private static function detectAnomaly(array $pattern): array
  {
    // Check for extreme values that suggest data corruption or manipulation
    $value = $pattern['value'] ?? null;
    $baseline = $pattern['baseline'] ?? null;

    if ($value !== null && $baseline !== null && $baseline > 0) {
      $deviation = abs($value - $baseline) / $baseline;
      if ($deviation > 3.0) { // >300% deviation
        return [
          'check'  => 'anomaly',
          'passed' => false,
          'reason' => "Value deviates {$deviation}x from baseline — likely anomalous",
        ];
      }
    }

    // Check for implausible patterns
    if (isset($pattern['improvement_percent']) && $pattern['improvement_percent'] > 50) {
      return [
        'check'  => 'anomaly',
        'passed' => false,
        'reason' => 'Improvement >50% is implausible — requires manual review',
      ];
    }

    return ['check' => 'anomaly', 'passed' => true, 'reason' => 'No anomalies detected'];
  }

  /**
   * Ethical validation of pattern content.
   */
  private static function ethicalCheck(array $pattern): array
  {
    $category = $pattern['category'] ?? '';

    // Block patterns that could harm students
    $harmful = [
      'punishment_optimization',
      'exclusion_pattern',
      'bias_amplification',
      'surveillance_expansion',
      'grade_manipulation',
      'discriminatory_grouping'
    ];
    if (in_array($category, $harmful, true)) {
      return [
        'check'  => 'ethical',
        'passed' => false,
        'reason' => "Category '{$category}' is ethically blocked",
      ];
    }

    // Block patterns targeting vulnerable populations
    if (isset($pattern['target_demographic']) && in_array($pattern['target_demographic'], ['disabled', 'minority', 'at_risk'], true)) {
      if (($pattern['action_type'] ?? '') === 'restrictive') {
        return [
          'check'  => 'ethical',
          'passed' => false,
          'reason' => 'Restrictive actions targeting vulnerable populations are blocked',
        ];
      }
    }

    return ['check' => 'ethical', 'passed' => true, 'reason' => 'No ethical concerns detected'];
  }

  /**
   * Evaluate multiple patterns in batch.
   */
  public static function evaluateBatch(array $patterns): array
  {
    $results = [];
    $approved = 0;
    $rejected = 0;

    foreach ($patterns as $p) {
      $result = self::validate($p);
      $results[] = $result;
      if ($result['allowed']) $approved++;
      else $rejected++;
    }

    return [
      'total'    => count($patterns),
      'approved' => $approved,
      'rejected' => $rejected,
      'results'  => $results,
    ];
  }

  /**
   * Get summary for dashboard.
   */
  public static function getSummary(): array
  {
    return [
      'minimum_consensus' => self::$minimumConsensus,
      'checks'            => ['consensus', 'anomaly', 'ethical', 'confidence', 'persistence'],
      'blocked_categories' => 6,
      'status'            => 'active',
    ];
  }
}
