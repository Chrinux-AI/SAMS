<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

class PredictiveEngine
{
  /**
   * Students with attendance < 75% in last 30 days
   */
  public function predictAtRiskStudents($tenantId)
  {
    try {
      $results = db()->fetchAll(
        "SELECT a.user_id,
                        u.full_name,
                        COUNT(*) as total,
                        SUM(CASE WHEN a.status != 'present' THEN 1 ELSE 0 END) as absent
                 FROM attendance a
                 JOIN users u ON a.user_id = u.id
                 WHERE u.tenant_id = ?
                   AND a.date >= NOW() - INTERVAL 30 DAY
                 GROUP BY a.user_id, u.full_name
                 HAVING (absent / total) > 0.25",
        [$tenantId]
      );

      $atRisk = [];
      foreach ($results as $row) {
        $attendanceRate = round((1 - ($row['absent'] / $row['total'])) * 100, 1);
        $atRisk[] = [
          'user_id' => $row['user_id'],
          'name' => $row['full_name'],
          'total_days' => $row['total'],
          'absent_days' => $row['absent'],
          'attendance_rate' => $attendanceRate,
          'risk_level' => $attendanceRate < 50 ? 'high' : 'medium'
        ];
      }
      return $atRisk;
    } catch (Exception $e) {
      error_log("PredictiveEngine::predictAtRiskStudents error: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Teachers with workload imbalance (>5 or <2 classes)
   */
  public function predictTeacherWorkload($tenantId)
  {
    try {
      $results = db()->fetchAll(
        "SELECT u.id as teacher_id, u.full_name, COUNT(DISTINCT c.id) as class_count
                 FROM users u
                 LEFT JOIN classes c ON c.teacher_id = u.id
                 WHERE u.tenant_id = ? AND u.role = 'teacher'
                 GROUP BY u.id, u.full_name",
        [$tenantId]
      );

      $workload = [];
      foreach ($results as $row) {
        $status = 'balanced';
        if ($row['class_count'] > 5) {
          $status = 'overloaded';
        } elseif ($row['class_count'] < 2) {
          $status = 'underutilized';
        }
        $workload[] = [
          'teacher_id' => $row['teacher_id'],
          'name' => $row['full_name'],
          'class_count' => $row['class_count'],
          'status' => $status
        ];
      }
      return $workload;
    } catch (Exception $e) {
      error_log("PredictiveEngine::predictTeacherWorkload error: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Daily absence rate over the last 30 days
   */
  public function predictAbsenteeismTrends($tenantId)
  {
    try {
      $results = db()->fetchAll(
        "SELECT a.date,
                        COUNT(*) as total,
                        SUM(CASE WHEN a.status != 'present' THEN 1 ELSE 0 END) as absent
                 FROM attendance a
                 JOIN users u ON a.user_id = u.id AND u.tenant_id = ?
                 WHERE a.date >= NOW() - INTERVAL 30 DAY
                 GROUP BY a.date
                 ORDER BY a.date ASC",
        [$tenantId]
      );

      $trends = [];
      foreach ($results as $row) {
        $rate = $row['total'] > 0 ? round(($row['absent'] / $row['total']) * 100, 1) : 0;
        $trends[] = [
          'date' => $row['date'],
          'total_records' => $row['total'],
          'absent_count' => $row['absent'],
          'absence_rate' => $rate
        ];
      }
      return $trends;
    } catch (Exception $e) {
      error_log("PredictiveEngine::predictAbsenteeismTrends error: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Calculate a 0-100 risk score for an individual student
   */
  public function getStudentRiskScore($studentId)
  {
    try {
      $score = 0;

      // Attendance component (0-40 points)
      $attendance = db()->fetchOne(
        "SELECT COUNT(*) as total,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
                 FROM attendance
                 WHERE user_id = ? AND date >= NOW() - INTERVAL 90 DAY",
        [$studentId]
      );

      if ($attendance && $attendance['total'] > 0) {
        $attendanceRate = $attendance['present'] / $attendance['total'];
        // Lower attendance = higher risk
        $score += (int)((1 - $attendanceRate) * 40);
        // Late arrivals add minor risk
        $lateRate = $attendance['late'] / $attendance['total'];
        $score += (int)($lateRate * 10);
      }

      // Grade component (0-30 points)
      $grades = db()->fetchOne(
        "SELECT AVG(score) as avg_score FROM grades WHERE student_id = ?",
        [$studentId]
      );
      if ($grades && $grades['avg_score'] !== null) {
        $avgScore = (float)$grades['avg_score'];
        if ($avgScore < 40) {
          $score += 30;
        } elseif ($avgScore < 60) {
          $score += 20;
        } elseif ($avgScore < 75) {
          $score += 10;
        }
      }

      // Behavior/notes component (0-20 points)
      $behaviorCount = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM audit_logs
                 WHERE user_id = ? AND action LIKE '%behavior%' AND created_at >= NOW() - INTERVAL 90 DAY",
        [$studentId]
      );
      if ($behaviorCount) {
        $score += min(20, (int)$behaviorCount['cnt'] * 5);
      }

      return [
        'student_id' => $studentId,
        'risk_score' => min(100, max(0, $score)),
        'risk_level' => $score >= 70 ? 'high' : ($score >= 40 ? 'medium' : 'low'),
        'calculated_at' => date('Y-m-d H:i:s')
      ];
    } catch (Exception $e) {
      error_log("PredictiveEngine::getStudentRiskScore error: " . $e->getMessage());
      return ['student_id' => $studentId, 'risk_score' => 0, 'risk_level' => 'unknown', 'error' => true];
    }
  }
}
