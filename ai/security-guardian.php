<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

class SecurityGuardianEngine
{
  /**
   * Combine login anomalies, OTP failures, and off-hours access into threat analysis
   */
  public function analyzeThreats($tenantId)
  {
    try {
      $failedLogins = $this->getFailedLogins($tenantId, 24);
      $otpFailures = $this->getOTPFailures($tenantId);

      // Off-hours access (outside 6am-10pm)
      $offHours = db()->fetchAll(
        "SELECT user_id, ip_address, created_at
                 FROM audit_logs
                 WHERE tenant_id = ?
                   AND action LIKE '%login%success%'
                   AND (HOUR(created_at) < 6 OR HOUR(created_at) > 22)
                   AND created_at >= NOW() - INTERVAL 7 DAY
                 ORDER BY created_at DESC
                 LIMIT 50",
        [$tenantId]
      );

      return [
        'failed_logins' => $failedLogins,
        'otp_failures' => $otpFailures,
        'off_hours_access' => $offHours,
        'threat_level' => $this->calculateThreatLevel($failedLogins, $otpFailures, $offHours),
        'analyzed_at' => date('Y-m-d H:i:s')
      ];
    } catch (Exception $e) {
      error_log("SecurityGuardianEngine::analyzeThreats error: " . $e->getMessage());
      return ['error' => $e->getMessage()];
    }
  }

  /**
   * Query failed login attempts in the last N hours
   */
  public function getFailedLogins($tenantId, $hours = 24)
  {
    try {
      return db()->fetchAll(
        "SELECT ip_address, user_id, COUNT(*) as attempts, MAX(created_at) as last_attempt
                 FROM audit_logs
                 WHERE tenant_id = ?
                   AND action LIKE '%failed%login%'
                   AND created_at >= NOW() - INTERVAL ? HOUR
                 GROUP BY ip_address, user_id
                 ORDER BY attempts DESC
                 LIMIT 50",
        [$tenantId, $hours]
      );
    } catch (Exception $e) {
      error_log("SecurityGuardianEngine::getFailedLogins error: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Query unused OTP codes grouped by user
   */
  public function getOTPFailures($tenantId)
  {
    try {
      return db()->fetchAll(
        "SELECT o.user_id, u.full_name, COUNT(*) as unused_codes, MAX(o.created_at) as last_generated
                 FROM otp_codes o
                 JOIN users u ON o.user_id = u.id AND u.tenant_id = ?
                 WHERE o.is_used = 0
                   AND o.created_at >= NOW() - INTERVAL 48 HOUR
                 GROUP BY o.user_id, u.full_name
                 HAVING unused_codes >= 3
                 ORDER BY unused_codes DESC",
        [$tenantId]
      );
    } catch (Exception $e) {
      error_log("SecurityGuardianEngine::getOTPFailures error: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Block an IP address by storing it in json_store
   */
  public function blockIP($ip, $reason, $duration)
  {
    try {
      $data = json_encode([
        'ip' => $ip,
        'reason' => $reason,
        'duration' => $duration,
        'blocked_at' => date('Y-m-d H:i:s'),
        'expires_at' => date('Y-m-d H:i:s', strtotime("+{$duration} hours"))
      ]);

      db()->fetchOne(
        "INSERT INTO json_store (category, key_name, value, created_at)
                 VALUES ('blocked_ips', ?, ?, NOW())",
        [$ip, $data]
      );

      return ['success' => true, 'ip' => $ip, 'reason' => $reason];
    } catch (Exception $e) {
      error_log("SecurityGuardianEngine::blockIP error: " . $e->getMessage());
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  /**
   * Unblock an IP address
   */
  public function unblockIP($ip)
  {
    try {
      db()->fetchOne(
        "DELETE FROM json_store WHERE category = 'blocked_ips' AND key_name = ?",
        [$ip]
      );
      return ['success' => true, 'ip' => $ip];
    } catch (Exception $e) {
      error_log("SecurityGuardianEngine::unblockIP error: " . $e->getMessage());
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  /**
   * Get all currently blocked IPs
   */
  public function getBlockedIPs()
  {
    try {
      $rows = db()->fetchAll(
        "SELECT key_name, value, created_at FROM json_store WHERE category = 'blocked_ips'"
      );

      $blocked = [];
      foreach ($rows as $row) {
        $data = json_decode($row['value'], true);
        $data['stored_at'] = $row['created_at'];
        $blocked[] = $data;
      }
      return $blocked;
    } catch (Exception $e) {
      error_log("SecurityGuardianEngine::getBlockedIPs error: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Calculate overall security risk score 0-100
   */
  public function calculateRiskScore()
  {
    try {
      $score = 0;

      // Failed logins in last 24h
      $failedCount = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM audit_logs
                 WHERE action LIKE '%failed%login%' AND created_at >= NOW() - INTERVAL 24 HOUR"
      );
      if ($failedCount) {
        $score += min(30, (int)$failedCount['cnt'] * 2);
      }

      // Blocked IPs count
      $blockedCount = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM json_store WHERE category = 'blocked_ips'"
      );
      if ($blockedCount) {
        $score += min(20, (int)$blockedCount['cnt'] * 5);
      }

      // Unused OTPs
      $otpCount = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM otp_codes WHERE is_used = 0 AND created_at >= NOW() - INTERVAL 24 HOUR"
      );
      if ($otpCount) {
        $score += min(20, (int)$otpCount['cnt']);
      }

      // Off-hours access
      $offHoursCount = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM audit_logs
                 WHERE action LIKE '%login%success%'
                   AND (HOUR(created_at) < 6 OR HOUR(created_at) > 22)
                   AND created_at >= NOW() - INTERVAL 24 HOUR"
      );
      if ($offHoursCount) {
        $score += min(30, (int)$offHoursCount['cnt'] * 3);
      }

      return [
        'risk_score' => min(100, max(0, $score)),
        'risk_level' => $score >= 70 ? 'critical' : ($score >= 40 ? 'elevated' : 'normal'),
        'calculated_at' => date('Y-m-d H:i:s')
      ];
    } catch (Exception $e) {
      error_log("SecurityGuardianEngine::calculateRiskScore error: " . $e->getMessage());
      return ['risk_score' => 0, 'risk_level' => 'unknown'];
    }
  }

  private function calculateThreatLevel($failedLogins, $otpFailures, $offHours)
  {
    $total = count($failedLogins) + count($otpFailures) + count($offHours);
    if ($total > 20) return 'critical';
    if ($total > 10) return 'elevated';
    if ($total > 3) return 'moderate';
    return 'low';
  }
}
