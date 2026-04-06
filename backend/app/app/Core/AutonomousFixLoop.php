<?php

/**
 * AutonomousFixLoop — Master Orchestrator
 *
 * Ties together: SystemScanner → AutoRepairEngine → ValidationRunner → HealthReporter
 *
 * Flow per cycle:
 *   1. Scan: detect issues
 *   2. Repair: fix what can be fixed
 *   3. Validate: run tests to confirm repairs
 *   4. Report: calculate health score and log results
 *   5. Repeat until health >= 98% or max iterations reached
 */
class AutonomousFixLoop
{
  /** Maximum repair iterations per run to prevent infinite loops */
  private const MAX_ITERATIONS = 5;

  /** Target health score */
  private const TARGET_SCORE = 98;

  /**
   * Execute a full autonomous fix cycle.
   *
   * @return array{iterations: int, final_score: int, final_grade: string, repairs_made: int, logs: array}
   */
  public static function run(): array
  {
    $logs = [];
    $totalRepairs = 0;
    $iteration = 0;
    $score = 0;

    // Ensure system_failures table exists before we start
    self::ensureFailuresTable();

    ErrorCollector::log('autofix', '=== Autonomous Fix Loop started ===', 'INFO');

    for ($iteration = 1; $iteration <= self::MAX_ITERATIONS; $iteration++) {
      ErrorCollector::log('autofix', "--- Iteration {$iteration} ---", 'INFO');

      // Step 1: Scan
      $issues = SystemScanner::scan();
      $issueCount = count($issues);
      ErrorCollector::log('autofix', "Scan found {$issueCount} issue(s)", 'INFO');
      $logs[] = ['phase' => 'scan', 'iteration' => $iteration, 'issues' => $issueCount];

      // Step 2: Repair
      $repairResults = AutoRepairEngine::repair($issues);
      $repairCount = count($repairResults);
      $totalRepairs += $repairCount;
      ErrorCollector::log('autofix', "Repair attempted {$repairCount} fix(es)", 'INFO');
      $logs[] = ['phase' => 'repair', 'iteration' => $iteration, 'repairs' => $repairCount, 'details' => $repairResults];

      // Record repairs in error collector
      foreach ($repairResults as $r) {
        ErrorCollector::recordFailure(
          $r['category'] ?? 'unknown',
          $r['module'] ?? 'unknown',
          $r['action'] ?? 'repair',
          $r['success'] ?? false
        );
      }

      // Step 3: Validate
      $testResults = ValidationRunner::runAll();
      ErrorCollector::log('autofix', "Validation: {$testResults['passed']}/{$testResults['total']} tests passed", 'INFO');
      $logs[] = ['phase' => 'validate', 'iteration' => $iteration, 'passed' => $testResults['passed'], 'total' => $testResults['total']];

      // Step 4: Report
      $rescanIssues = SystemScanner::scan();
      $score = HealthReporter::calculateScore($rescanIssues, $testResults);
      $grade = HealthReporter::scoreToGrade($score);
      ErrorCollector::log('autofix', "Health Score: {$score}/100 (Grade: {$grade})", 'INFO');
      $logs[] = ['phase' => 'report', 'iteration' => $iteration, 'score' => $score, 'grade' => $grade];

      // Check if target reached
      if ($score >= self::TARGET_SCORE) {
        ErrorCollector::log('autofix', "Target score reached ({$score} >= " . self::TARGET_SCORE . "). Loop complete.", 'INFO');
        break;
      }

      // If no repairs were made and no issues are repairable, stop
      if ($repairCount === 0 && $issueCount > 0) {
        ErrorCollector::log('autofix', "No repairable issues remain. Stopping at score {$score}.", 'INFO');
        break;
      }

      // If there were no issues at all, no point continuing
      if ($issueCount === 0) {
        ErrorCollector::log('autofix', "No issues detected. Loop complete at score {$score}.", 'INFO');
        break;
      }
    }

    // Prune old log entries
    ErrorCollector::pruneLog(5000);

    ErrorCollector::log('autofix', "=== Autonomous Fix Loop ended: Score={$score}, Iterations={$iteration}, Repairs={$totalRepairs} ===", 'INFO');

    // Persist last run summary
    self::persistSummary($score, $iteration, $totalRepairs);

    return [
      'iterations'   => $iteration,
      'final_score'  => $score,
      'final_grade'  => HealthReporter::scoreToGrade($score),
      'repairs_made' => $totalRepairs,
      'logs'         => $logs,
    ];
  }

  /**
   * Get the last run summary from the filesystem.
   *
   * @return array|null
   */
  public static function getLastRun(): ?array
  {
    $file = self::summaryPath();
    if (!is_file($file)) {
      return null;
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : null;
  }

  /**
   * Ensure system_failures table exists.
   */
  private static function ensureFailuresTable(): void
  {
    try {
      if (function_exists('table_exists') && !table_exists('system_failures')) {
        db()->query("CREATE TABLE IF NOT EXISTS system_failures (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    error_type VARCHAR(100) NOT NULL,
                    module VARCHAR(100) NOT NULL,
                    fix_applied TEXT,
                    success TINYINT(1) DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_module (module),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        ErrorCollector::log('autofix', 'Created system_failures table', 'INFO');
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('autofix', 'Failed to create system_failures table: ' . $e->getMessage(), 'ERROR');
    }
  }

  /**
   * Persist run summary to JSON file for dashboard consumption.
   */
  private static function persistSummary(int $score, int $iterations, int $repairs): void
  {
    $data = [
      'score'      => $score,
      'grade'      => HealthReporter::scoreToGrade($score),
      'iterations' => $iterations,
      'repairs'    => $repairs,
      'timestamp'  => date('Y-m-d H:i:s'),
    ];

    $dir = dirname(self::summaryPath());
    if (!is_dir($dir)) {
      mkdir($dir, 0755, true);
    }
    file_put_contents(self::summaryPath(), json_encode($data, JSON_PRETTY_PRINT));
  }

  private static function summaryPath(): string
  {
    $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    return $base . '/storage/autofix-summary.json';
  }
}
