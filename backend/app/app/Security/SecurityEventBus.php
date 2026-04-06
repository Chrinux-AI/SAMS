<?php

/**
 * Security Event Bus — Central security communication channel.
 *
 * All security modules publish alerts through this bus:
 *   login anomaly, privilege change, suspicious messaging,
 *   AI abuse attempt, behavior risk elevation, threat detection.
 *
 * Flow: Event → Bus → Security AI → Response Engine
 */
class SecurityEventBus
{
  /** @var array<string, array<callable>> Security-specific listeners */
  private static array $handlers = [];

  /** @var bool Whether the bus has been initialized */
  private static bool $initialized = false;

  /**
   * Initialize the security event bus with default handlers.
   */
  public static function init(): void
  {
    if (self::$initialized) {
      return;
    }

    // Wire up the security pipeline: Event → AI Analysis → AutoDefense
    self::on('login_anomaly', [self::class, 'handleLoginAnomaly']);
    self::on('privilege_change', [self::class, 'handlePrivilegeChange']);
    self::on('suspicious_messaging', [self::class, 'handleSuspiciousMessaging']);
    self::on('ai_abuse', [self::class, 'handleAIAbuse']);
    self::on('risk_elevation', [self::class, 'handleRiskElevation']);
    self::on('threat_detected', [self::class, 'handleThreatDetected']);
    self::on('ip_anomaly', [self::class, 'handleIPAnomaly']);
    self::on('data_exfiltration', [self::class, 'handleDataExfiltration']);
    self::on('session_hijack', [self::class, 'handleSessionHijack']);
    self::on('prompt_attack', [self::class, 'handlePromptAttack']);

    self::$initialized = true;
  }

  /**
   * Register a handler for a security event type.
   */
  public static function on(string $eventType, callable $handler): void
  {
    self::$handlers[$eventType][] = $handler;
  }

  /**
   * Publish a security event to the bus.
   */
  public static function publish(string $eventType, array $data = []): array
  {
    $data['_sec_event'] = $eventType;
    $data['_sec_time'] = date('c');
    $data['_sec_ip'] = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $data['_sec_user'] = $_SESSION['user_id'] ?? null;

    $results = [];

    // Execute all registered handlers
    if (isset(self::$handlers[$eventType])) {
      foreach (self::$handlers[$eventType] as $handler) {
        try {
          $results[] = call_user_func($handler, $data);
        } catch (\Throwable $e) {
          error_log("SecurityEventBus handler error [{$eventType}]: " . $e->getMessage());
        }
      }
    }

    // Always log the security event
    self::logSecurityEvent($eventType, $data);

    return $results;
  }

  // ─────── Default Handlers ───────

  public static function handleLoginAnomaly(array $data): array
  {
    $userId = (int) ($data['user_id'] ?? 0);
    $result = ['action' => 'none'];

    if ($userId > 0 && class_exists('SecurityAI')) {
      $analysis = SecurityAI::analyze($userId);
      if ($analysis['threat'] !== 'normal' && class_exists('AutoDefense')) {
        $defense = AutoDefense::respond($userId, $analysis['score'], $analysis['threat'], $analysis['features']);
        $result = ['action' => 'defense_triggered', 'level' => $defense['level']];
      }
    }

    // Broadcast to admin dashboard
    if (class_exists('Broadcaster')) {
      Broadcaster::toRole('admin', 'security_event', [
        'type'    => 'login_anomaly',
        'user_id' => $userId,
        'detail'  => $data['detail'] ?? 'Login anomaly detected',
      ]);
    }

    return $result;
  }

  public static function handlePrivilegeChange(array $data): array
  {
    $userId = (int) ($data['user_id'] ?? 0);

    // Privilege changes always get forensic recording
    if ($userId > 0 && class_exists('AdminForensics')) {
      AdminForensics::record(
        (int) ($data['admin_id'] ?? $_SESSION['user_id'] ?? 0),
        'role_change',
        'users',
        $userId,
        $data['old_role'] ?? null,
        $data['new_role'] ?? null,
        'Privilege change via security event bus'
      );
    }

    if (class_exists('Broadcaster')) {
      Broadcaster::toRole('admin', 'security_event', [
        'type'    => 'privilege_change',
        'user_id' => $userId,
        'detail'  => "Role changed: " . ($data['old_role'] ?? '?') . " → " . ($data['new_role'] ?? '?'),
      ]);
    }

    return ['recorded' => true];
  }

  public static function handleSuspiciousMessaging(array $data): array
  {
    $userId = (int) ($data['user_id'] ?? 0);
    if ($userId > 0 && class_exists('BehaviorMonitor')) {
      return BehaviorMonitor::recordAction($userId, 'suspicious_messaging', $data);
    }
    return [];
  }

  public static function handleAIAbuse(array $data): array
  {
    $userId = (int) ($data['user_id'] ?? 0);

    // AI abuse escalates directly to medium defense
    if ($userId > 0 && class_exists('AutoDefense')) {
      return AutoDefense::respond($userId, 50, 'suspicious', ['ai_abuse' => $data]);
    }
    return [];
  }

  public static function handleRiskElevation(array $data): array
  {
    $userId = (int) ($data['user_id'] ?? 0);
    $riskScore = (int) ($data['risk_score'] ?? 0);

    // Auto-trigger defense if score is high enough
    if ($riskScore >= 61 && $userId > 0 && class_exists('AutoDefense')) {
      $level = $riskScore >= 81 ? 'critical' : 'high_risk';
      return AutoDefense::respond($userId, $riskScore, $level, $data['factors'] ?? []);
    }

    return ['monitored' => true];
  }

  public static function handleThreatDetected(array $data): array
  {
    $userId = (int) ($data['user_id'] ?? 0);
    $threat = $data['threat'] ?? 'suspicious';

    if (class_exists('AutoDefense') && $userId > 0) {
      $score = $data['score'] ?? ($threat === 'attack_likely' ? 85 : 50);
      return AutoDefense::respond($userId, $score, $threat, $data['features'] ?? []);
    }

    return [];
  }

  public static function handleIPAnomaly(array $data): array
  {
    $userId = (int) ($data['user_id'] ?? 0);
    if ($userId > 0 && class_exists('SessionIntelligence')) {
      SessionIntelligence::requireReauthentication('IP address anomaly detected');
    }
    return ['reauth_required' => true];
  }

  public static function handleDataExfiltration(array $data): array
  {
    $userId = (int) ($data['user_id'] ?? 0);
    // Data exfiltration → hard defense
    if ($userId > 0 && class_exists('AutoDefense')) {
      return AutoDefense::respond($userId, 75, 'high_risk', $data);
    }
    return [];
  }

  public static function handleSessionHijack(array $data): array
  {
    $userId = (int) ($data['user_id'] ?? 0);
    // Session hijack → critical defense
    if ($userId > 0 && class_exists('AutoDefense')) {
      return AutoDefense::respond($userId, 90, 'attack_likely', $data);
    }
    return [];
  }

  public static function handlePromptAttack(array $data): array
  {
    $userId = (int) ($data['user_id'] ?? 0);
    $severity = $data['severity'] ?? 'medium';

    if ($severity === 'critical' && $userId > 0 && class_exists('AutoDefense')) {
      return AutoDefense::respond($userId, 60, 'suspicious', $data);
    }

    return ['logged' => true];
  }

    // ─────── Logging ───────

  /**
   * Log every security event to the security_events table.
   */
  private static function logSecurityEvent(string $eventType, array $data): void
  {
    try {
      db()->insert('security_events', [
        'event_type'  => 'bus_' . $eventType,
        'severity'    => $data['severity'] ?? 'info',
        'user_id'     => $data['_sec_user'] ?? null,
        'ip_address'  => $data['_sec_ip'] ?? '0.0.0.0',
        'details'     => json_encode(array_diff_key($data, array_flip(['_sec_event', '_sec_time', '_sec_ip', '_sec_user'])), JSON_UNESCAPED_UNICODE),
        'resolved'    => 0,
        'created_at'  => date('Y-m-d H:i:s'),
      ]);
    } catch (\Throwable $e) {
      error_log("SecurityEventBus log failed: " . $e->getMessage());
    }
  }

  /**
   * Get recent security bus events for the dashboard.
   */
  public static function getRecentEvents(int $limit = 50, ?string $eventType = null): array
  {
    try {
      $where = "event_type LIKE 'bus_%'";
      $params = [];
      if ($eventType !== null) {
        $where .= " AND event_type = :etype";
        $params['etype'] = 'bus_' . $eventType;
      }
      return db()->fetchAll(
        "SELECT se.*, u.full_name FROM security_events se
                 LEFT JOIN users u ON u.id = se.user_id
                 WHERE {$where} ORDER BY se.created_at DESC LIMIT " . min($limit, 200),
        $params
      );
    } catch (\Throwable $e) {
      return [];
    }
  }
}
