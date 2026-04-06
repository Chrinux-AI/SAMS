<?php

/**
 * AIC — Policy Advisor
 * Generates data-driven policy recommendations based on institutional metrics.
 * Assesses current operational efficiency and suggests improvements.
 */
class PolicyAdvisor
{
  /**
   * Run a full policy assessment.
   */
  public static function assess(): array
  {
    $recommendations = [];

    $recommendations = array_merge(
      $recommendations,
      self::assessAttendancePolicy(),
      self::assessStaffingPolicy(),
      self::assessOperationalEfficiency()
    );

    // Sort by priority
    usort($recommendations, function ($a, $b) {
      $order = ['HIGH' => 0, 'MEDIUM' => 1, 'LOW' => 2];
      return ($order[$a['priority'] ?? 'LOW'] ?? 3) - ($order[$b['priority'] ?? 'LOW'] ?? 3);
    });

    return [
      'recommendations'  => $recommendations,
      'count'            => count($recommendations),
      'efficiency_score' => self::calculateEfficiency(),
    ];
  }

  /**
   * Assess attendance-related policies.
   */
  private static function assessAttendancePolicy(): array
  {
    $recs = [];
    try {
      $pdo = db()->getConnection();

      // Check if attendance is being marked consistently
      $stmt = $pdo->query("
                SELECT COUNT(DISTINCT date) as days_marked
                FROM attendance
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND date <= CURDATE()
            ");
      $daysMarked = (int) $stmt->fetchColumn();
      $expectedDays = min(10, (int) ((time() - strtotime('-14 days')) / 86400)); // Approximate school days

      if ($daysMarked < $expectedDays * 0.7) {
        $recs[] = [
          'area'     => 'Attendance',
          'priority' => 'HIGH',
          'title'    => 'Inconsistent attendance marking',
          'detail'   => "Only $daysMarked of ~$expectedDays expected school days have attendance records. Consider enforcing daily attendance marking.",
        ];
      }

      // Check late arrival patterns
      $lateRate = $pdo->query("
                SELECT
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) * 100 as late_pct
                FROM attendance
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ")->fetchColumn();

      if ($lateRate > 15) {
        $recs[] = [
          'area'     => 'Attendance',
          'priority' => 'MEDIUM',
          'title'    => 'High late arrival rate',
          'detail'   => "Late arrivals at " . round($lateRate, 1) . "%. Consider reviewing school start time or transportation arrangements.",
        ];
      }
    } catch (\Throwable $e) {
      // Skip on DB error
    }

    return $recs;
  }

  /**
   * Assess staffing-related policies.
   */
  private static function assessStaffingPolicy(): array
  {
    $recs = [];
    try {
      $pdo = db()->getConnection();

      $teachers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'active'")->fetchColumn();
      $students = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn();

      if ($teachers > 0) {
        $ratio = round($students / $teachers);
        if ($ratio > 30) {
          $recs[] = [
            'area'     => 'Staffing',
            'priority' => 'HIGH',
            'title'    => 'High student-teacher ratio',
            'detail'   => "Student-teacher ratio is $ratio:1. Consider hiring additional staff for optimal learning outcomes.",
          ];
        }
      }

      // Check for teachers without classes
      $unassigned = (int) $pdo->query("
                SELECT COUNT(*) FROM users u
                WHERE u.role = 'teacher' AND u.status = 'active'
                AND u.id NOT IN (SELECT DISTINCT class_teacher_id FROM classes WHERE class_teacher_id IS NOT NULL)
            ")->fetchColumn();

      if ($unassigned > 0) {
        $recs[] = [
          'area'     => 'Staffing',
          'priority' => 'MEDIUM',
          'title'    => 'Unassigned teachers',
          'detail'   => "$unassigned teacher(s) have no classes assigned. Review class assignments.",
        ];
      }
    } catch (\Throwable $e) {
      // Skip on DB error
    }

    return $recs;
  }

  /**
   * Assess operational efficiency.
   */
  private static function assessOperationalEfficiency(): array
  {
    $recs = [];
    try {
      $pdo = db()->getConnection();

      // Check for pending user approvals
      $pending = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
      if ($pending > 5) {
        $recs[] = [
          'area'     => 'Operations',
          'priority' => 'MEDIUM',
          'title'    => 'Pending user approvals',
          'detail'   => "$pending user registrations awaiting approval. Review and process promptly.",
        ];
      }

      // Check for empty classes
      $emptyClasses = (int) $pdo->query("
                SELECT COUNT(*) FROM classes c
                LEFT JOIN class_enrollments ce ON ce.class_id = c.id
                WHERE ce.id IS NULL
            ")->fetchColumn();

      if ($emptyClasses > 0) {
        $recs[] = [
          'area'     => 'Operations',
          'priority' => 'LOW',
          'title'    => 'Empty classes',
          'detail'   => "$emptyClasses class(es) have no enrolled students. Consider removing or filling them.",
        ];
      }
    } catch (\Throwable $e) {
      // Skip on DB error
    }

    return $recs;
  }

  /**
   * Calculate overall operational efficiency score.
   */
  private static function calculateEfficiency(): int
  {
    try {
      $pdo = db()->getConnection();
      $score = 100;

      // Deduct for pending approvals
      $pending = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
      $score -= min(20, $pending * 2);

      // Deduct for inconsistent attendance marking
      $daysMarked = (int) $pdo->query("
                SELECT COUNT(DISTINCT date) FROM attendance
                WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ")->fetchColumn();
      if ($daysMarked < 5) {
        $score -= (5 - $daysMarked) * 5;
      }

      return max(0, min(100, $score));
    } catch (\Throwable $e) {
      return 50;
    }
  }
}
