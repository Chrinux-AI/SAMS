<?php

/**
 * Audit Logger — Centralized audit trail for admin actions.
 * Tracks edits, deletions, role changes, notices, and security events.
 */
class AuditLogger
{
  /** Action categories */
  public const ACTION_CREATE = 'create';
  public const ACTION_UPDATE = 'update';
  public const ACTION_DELETE = 'delete';
  public const ACTION_LOGIN  = 'login';
  public const ACTION_LOGOUT = 'logout';
  public const ACTION_ROLE_CHANGE = 'role_change';
  public const ACTION_NOTICE = 'notice';
  public const ACTION_SECURITY = 'security';
  public const ACTION_SETTINGS = 'settings';
  public const ACTION_EXPORT = 'export';

  /**
   * Log an audit event.
   *
   * @param string      $action   Action type (use class constants)
   * @param string      $model    Affected model/table name
   * @param string      $details  Human-readable description
   * @param int|null    $userId   Acting user ID (null = system/cron)
   * @param int|null    $targetId ID of the affected record
   */
  public static function log(
    string $action,
    string $model = '',
    string $details = '',
    ?int $userId = null,
    ?int $targetId = null
  ): bool {
    if ($userId === null && isset($_SESSION['user_id'])) {
      $userId = (int) $_SESSION['user_id'];
    }

    $data = [
      'user_id'    => $userId,
      'action'     => $action,
      'model'      => $model,
      'target_id'  => $targetId,
      'details'    => mb_substr($details, 0, 1000),
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
      'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
      'created_at' => date('Y-m-d H:i:s'),
    ];

    try {
      db()->insert('audit_logs', $data);
      return true;
    } catch (\Throwable $e) {
      error_log("AuditLogger failed: " . $e->getMessage());
      // Fallback to file logging
      $line = json_encode($data);
      @file_put_contents(
        (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/logs/audit.log',
        date('[Y-m-d H:i:s] ') . $line . PHP_EOL,
        FILE_APPEND | LOCK_EX
      );
      return false;
    }
  }

  /**
   * Log a user action (create/update/delete on a model).
   */
  public static function logUserAction(string $action, string $model, int $targetId, string $details = ''): bool
  {
    return self::log($action, $model, $details, null, $targetId);
  }

  /**
   * Log an admin-specific action.
   */
  public static function logAdmin(string $action, string $details = '', ?int $targetId = null): bool
  {
    return self::log($action, 'admin', $details, null, $targetId);
  }

  /**
   * Log a security event (login, lockout, XSS attempt, etc.).
   */
  public static function logSecurity(string $details, ?int $userId = null): bool
  {
    return self::log(self::ACTION_SECURITY, 'security', $details, $userId);
  }

  /**
   * Query recent audit log entries.
   *
   * @param int         $limit    Max entries to return
   * @param string|null $action   Filter by action type
   * @param int|null    $userId   Filter by user
   * @return array
   */
  public static function getRecent(int $limit = 50, ?string $action = null, ?int $userId = null): array
  {
    $where = '1=1';
    $params = [];

    if ($action !== null) {
      $where .= ' AND action = :action';
      $params['action'] = $action;
    }
    if ($userId !== null) {
      $where .= ' AND user_id = :user_id';
      $params['user_id'] = $userId;
    }

    $limit = min($limit, 500);
    try {
      return db()->fetchAll(
        "SELECT al.*, u.full_name as user_name
                 FROM audit_logs al
                 LEFT JOIN users u ON u.id = al.user_id
                 WHERE {$where}
                 ORDER BY al.created_at DESC
                 LIMIT {$limit}",
        $params
      );
    } catch (\Throwable $e) {
      error_log("AuditLogger query failed: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Purge audit logs older than the given number of days.
   */
  public static function purge(int $days = 90): int
  {
    try {
      $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
      $stmt = db()->query("DELETE FROM audit_logs WHERE created_at < :cutoff", ['cutoff' => $cutoff]);
      return $stmt ? $stmt->rowCount() : 0;
    } catch (\Throwable $e) {
      error_log("AuditLogger purge failed: " . $e->getMessage());
      return 0;
    }
  }
}
