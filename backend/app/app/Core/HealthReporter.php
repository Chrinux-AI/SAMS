<?php

/**
 * HealthReporter — Calculates system health score and produces reports.
 *
 * Combines data from SystemScanner, AutoRepairEngine, ValidationRunner,
 * and ErrorCollector into a single unified health report with a 0-100 score.
 */
class HealthReporter
{
  /**
   * Calculate the current system health score.
   *
   * Formula: 100 - (issue_deductions) - (test_deductions) - (failure_deductions)
   *
   * @param array $scanIssues   Issues from SystemScanner::scan()
   * @param array $testResults  Results from ValidationRunner::runAll()
   * @return int                Score 0-100
   */
  public static function calculateScore(array $scanIssues, array $testResults): int
  {
    $score = 100;

    // Deduct for unresolved issues by severity
    $weights = ['critical' => 10, 'high' => 5, 'medium' => 2, 'low' => 1];
    foreach ($scanIssues as $issue) {
      $sev = $issue['severity'] ?? 'medium';
      $score -= $weights[$sev] ?? 2;
    }

    // Deduct for failed tests
    $failed = $testResults['failed'] ?? 0;
    $score -= $failed * 5;

    // Deduct for recent failures (last 24h) that weren't resolved
    try {
      $recentFails = ErrorCollector::getRecentFailures(50);
      $unresolvedCount = 0;
      $cutoff = date('Y-m-d H:i:s', strtotime('-24 hours'));
      foreach ($recentFails as $f) {
        if (($f['created_at'] ?? '') >= $cutoff && empty($f['success'])) {
          $unresolvedCount++;
        }
      }
      $score -= $unresolvedCount * 2;
    } catch (\Throwable $e) {
      // If error collector is unavailable, small penalty
      $score -= 1;
    }

    return max(0, min(100, $score));
  }

  /**
   * Generate a full health report.
   *
   * @return array{score: int, grade: string, scan_issues: array, test_results: array, recent_failures: array, timestamp: string}
   */
  public static function getReport(): array
  {
    $issues = SystemScanner::scan();
    $tests = ValidationRunner::runAll();
    $score = self::calculateScore($issues, $tests);

    $failures = [];
    try {
      $failures = ErrorCollector::getRecentFailures(20);
    } catch (\Throwable $e) {
      // Gracefully degrade
    }

    return [
      'score'           => $score,
      'grade'           => self::scoreToGrade($score),
      'scan_issues'     => $issues,
      'test_results'    => $tests,
      'recent_failures' => $failures,
      'timestamp'       => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Get a compact summary suitable for dashboards and logs.
   *
   * @return array{score: int, grade: string, issues: int, tests_passed: int, tests_total: int, timestamp: string}
   */
  public static function getSummary(): array
  {
    $report = self::getReport();
    return [
      'score'        => $report['score'],
      'grade'        => $report['grade'],
      'issues'       => count($report['scan_issues']),
      'tests_passed' => $report['test_results']['passed'],
      'tests_total'  => $report['test_results']['total'],
      'timestamp'    => $report['timestamp'],
    ];
  }

  /**
   * Convert numeric score to letter grade.
   */
  public static function scoreToGrade(int $score): string
  {
    if ($score >= 98) return 'A+';
    if ($score >= 90) return 'A';
    if ($score >= 80) return 'B';
    if ($score >= 70) return 'C';
    if ($score >= 60) return 'D';
    return 'F';
  }

  /**
   * Check if system is considered healthy.
   */
  public static function isHealthy(): bool
  {
    $issues = SystemScanner::scan();
    $tests = ValidationRunner::runAll();
    return self::calculateScore($issues, $tests) >= 98;
  }
}
