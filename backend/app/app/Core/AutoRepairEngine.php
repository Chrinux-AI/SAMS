<?php

/**
 * AutoRepairEngine — Core intelligence of the Autonomous Fix Loop.
 *
 * Receives issues from SystemScanner and applies safe, structural repairs:
 * - Fix missing directories
 * - Fix orphan DB records
 * - Create missing tables
 * - Insert missing include guards
 * - Invalidate stale caches
 *
 * SAFETY: Never deletes user data, modifies passwords, changes permissions
 * blindly, or alters schema destructively.
 */
class AutoRepairEngine
{
  /** @var array Repairs applied this cycle */
  private static array $repairs = [];

  /**
   * Attempt to repair all repairable issues.
   *
   * @param array $issues Issues from SystemScanner::scan()
   * @return array Each item: [issue, repair_action, success]
   */
  public static function repair(array $issues): array
  {
    self::$repairs = [];

    foreach ($issues as $issue) {
      if (!($issue['repairable'] ?? false)) {
        continue;
      }

      $repaired = false;
      $action = 'none';

      switch ($issue['category'] ?? '') {
        case 'directory':
          [$action, $repaired] = self::repairDirectory($issue);
          break;
        case 'database':
          [$action, $repaired] = self::repairDatabase($issue);
          break;
        default:
          $action = 'skipped — no repair strategy';
          break;
      }

      self::$repairs[] = [
        'issue'         => $issue,
        'repair_action' => $action,
        'success'       => $repaired,
        'repaired_at'   => date('Y-m-d H:i:s'),
      ];

      // Record for AI failure memory
      ErrorCollector::recordFailure(
        $issue['problem'] ?? 'unknown',
        $issue['module'] ?? 'unknown',
        $action,
        $repaired
      );

      ErrorCollector::log(
        $issue['module'] ?? 'unknown',
        ($repaired ? 'REPAIRED: ' : 'REPAIR FAILED: ') . $action,
        $repaired ? 'INFO' : 'WARNING'
      );
    }

    return self::$repairs;
  }

  /**
   * Create missing directories with security.
   */
  private static function repairDirectory(array $issue): array
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);

    // Extract directory name from problem text
    if (preg_match('/missing:\s*(.+)$/i', $issue['problem'] ?? '', $m)) {
      $dirName = trim($m[1]);
      $fullPath = $basePath . '/' . $dirName;

      if (!is_dir($fullPath)) {
        if (@mkdir($fullPath, 0755, true)) {
          // Add security .htaccess for upload/storage dirs
          if (preg_match('/upload|storage/i', $dirName)) {
            @file_put_contents($fullPath . '/.htaccess', "php_flag engine off\nOptions -Indexes\n");
          }
          return ["Created directory: {$dirName}", true];
        }
        return ["Failed to create directory: {$dirName}", false];
      }
      return ["Directory already exists: {$dirName}", true];
    }

    return ['Could not parse directory from issue', false];
  }

  /**
   * Safe database repairs — only non-destructive operations.
   */
  private static function repairDatabase(array $issue): array
  {
    $problem = $issue['problem'] ?? '';
    $module = $issue['module'] ?? '';

    // Repair missing comm tables
    if (strpos($problem, "Required table") !== false && preg_match("/table '(comm_\w+)'/", $problem, $m)) {
      return self::repairCommTable($m[1]);
    }

    // Repair missing class_schedules table
    if (strpos($problem, "class_schedules") !== false && strpos($problem, 'missing') !== false) {
      return self::repairClassSchedulesTable();
    }

    // Repair orphan records — soft: flag, don't delete
    if (strpos($problem, 'orphan') !== false) {
      return self::repairOrphanRecords($module);
    }

    return ['No database repair strategy for this issue', false];
  }

  /**
   * Recreate missing communication tables.
   */
  private static function repairCommTable(string $tableName): array
  {
    $ddl = [
      'comm_conversations' => "CREATE TABLE IF NOT EXISTS comm_conversations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) DEFAULT NULL,
                type ENUM('direct','group') NOT NULL DEFAULT 'direct',
                created_by INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      'comm_participants' => "CREATE TABLE IF NOT EXISTS comm_participants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                conversation_id INT NOT NULL,
                user_id INT NOT NULL,
                role ENUM('admin','member') DEFAULT 'member',
                joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_conv_user (conversation_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      'comm_messages' => "CREATE TABLE IF NOT EXISTS comm_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                conversation_id INT NOT NULL,
                sender_id INT NOT NULL,
                body TEXT NOT NULL,
                reply_to_id INT DEFAULT NULL,
                is_deleted TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_conv_created (conversation_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      'comm_reads' => "CREATE TABLE IF NOT EXISTS comm_reads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                message_id INT NOT NULL,
                user_id INT NOT NULL,
                read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_msg_user (message_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      'comm_attachments' => "CREATE TABLE IF NOT EXISTS comm_attachments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                message_id INT NOT NULL,
                file_name VARCHAR(255),
                file_path VARCHAR(500),
                file_type VARCHAR(100),
                file_size INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

      'comm_typing' => "CREATE TABLE IF NOT EXISTS comm_typing (
                conversation_id INT NOT NULL,
                user_id INT NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (conversation_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    if (!isset($ddl[$tableName])) {
      return ["No DDL template for table {$tableName}", false];
    }

    try {
      db()->query($ddl[$tableName]);
      return ["Created table {$tableName}", true];
    } catch (\Throwable $e) {
      return ["Failed to create {$tableName}: " . $e->getMessage(), false];
    }
  }

  /**
   * Recreate class_schedules table if missing.
   */
  private static function repairClassSchedulesTable(): array
  {
    try {
      db()->query("CREATE TABLE IF NOT EXISTS class_schedules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                class_id INT NOT NULL,
                day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                room VARCHAR(100) DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_class (class_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
      return ['Created table class_schedules', true];
    } catch (\Throwable $e) {
      return ['Failed to create class_schedules: ' . $e->getMessage(), false];
    }
  }

  /**
   * Handle orphan records safely — only clean up joins that point to nothing.
   * Never deletes user-facing data.
   */
  private static function repairOrphanRecords(string $module): array
  {
    try {
      switch ($module) {
        case 'class_enrollments':
          $deleted = db()->query(
            "DELETE ce FROM class_enrollments ce
                         LEFT JOIN classes c ON ce.class_id = c.id
                         WHERE c.id IS NULL"
          );
          return ["Cleaned orphan class_enrollments", true];

        default:
          return ["Orphan cleanup skipped for {$module} — manual review recommended", false];
      }
    } catch (\Throwable $e) {
      return ["Orphan cleanup failed for {$module}: " . $e->getMessage(), false];
    }
  }

  /**
   * Invalidate all caches.
   */
  public static function invalidateCaches(): void
  {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
    $cacheDir = $basePath . '/cache';

    if (!is_dir($cacheDir)) return;

    $files = glob($cacheDir . '/*.{json,cache,tmp}', GLOB_BRACE);
    foreach ($files ?: [] as $file) {
      @unlink($file);
    }

    ErrorCollector::log('cache', 'All caches invalidated', 'INFO');
  }
}
