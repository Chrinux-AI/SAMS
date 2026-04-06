<?php

/**
 * CommunicationOS — Communication Coordinator
 *
 * Bridges all communication channels: in-app chat, notifications, announcements,
 * email, WhatsApp. Coordinates with modules/chat/ for real-time messaging.
 */
class CommunicationOS
{
  /**
   * Health check for all communication channels.
   */
  public static function healthCheck(): array
  {
    return [
      'channels' => [
        'in_app'        => self::checkInApp(),
        'notifications' => self::checkNotifications(),
        'email'         => self::checkEmail(),
        'whatsapp'      => self::checkWhatsApp(),
        'chat'          => self::checkChatModule(),
      ],
      'timestamp' => date('c'),
    ];
  }

  /**
   * Send a message through the appropriate channel.
   */
  public static function send(string $channel, array $recipients, string $subject, string $body, array $options = []): array
  {
    switch ($channel) {
      case 'notification':
        return self::sendNotification($recipients, $subject, $body);
      case 'announcement':
        return self::sendAnnouncement($subject, $body, $options);
      case 'event':
        EventBus::dispatch('communication', 'message_sent', [
          'channel'    => $channel,
          'recipients' => count($recipients),
          'subject'    => $subject,
        ]);
        return ['success' => true, 'channel' => 'event_bus'];
      default:
        return ['success' => false, 'error' => "Unknown channel: {$channel}"];
    }
  }

  /**
   * Send in-app notification.
   */
  private static function sendNotification(array $userIds, string $title, string $message): array
  {
    $sent = 0;
    try {
      if (!table_exists('notifications')) {
        self::ensureNotificationsTable();
      }
      foreach ($userIds as $userId) {
        db()->insert('notifications', [
          'user_id'    => (int) $userId,
          'title'      => $title,
          'message'    => $message,
          'is_read'    => 0,
          'created_at' => date('Y-m-d H:i:s'),
        ]);
        $sent++;
      }
    } catch (\Throwable $e) {
      ErrorCollector::log('communication_os', 'Notification send failed: ' . $e->getMessage(), 'MEDIUM');
    }

    return ['success' => $sent > 0, 'sent' => $sent];
  }

  /**
   * Create an announcement.
   */
  private static function sendAnnouncement(string $title, string $body, array $options): array
  {
    try {
      if (!table_exists('announcements')) {
        self::ensureAnnouncementsTable();
      }
      db()->insert('announcements', [
        'title'       => $title,
        'content'     => $body,
        'audience'    => $options['audience'] ?? 'all',
        'priority'    => $options['priority'] ?? 'normal',
        'created_by'  => $_SESSION['user_id'] ?? 0,
        'created_at'  => date('Y-m-d H:i:s'),
        'expires_at'  => $options['expires_at'] ?? null,
      ]);

      EventBus::dispatch('communication', 'announcement_created', [
        'title'    => $title,
        'audience' => $options['audience'] ?? 'all',
      ]);

      return ['success' => true];
    } catch (\Throwable $e) {
      ErrorCollector::log('communication_os', 'Announcement failed: ' . $e->getMessage(), 'MEDIUM');
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  /**
   * Get recent announcements.
   */
  public static function getAnnouncements(int $limit = 10, string $audience = ''): array
  {
    try {
      if (!table_exists('announcements')) return [];
      $sql = "SELECT * FROM announcements WHERE (expires_at IS NULL OR expires_at > NOW())";
      $params = [];
      if ($audience) {
        $sql .= " AND (audience = ? OR audience = 'all')";
        $params[] = $audience;
      }
      $sql .= " ORDER BY created_at DESC LIMIT " . max(1, min(50, $limit));
      return db()->fetchAll($sql, $params);
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Get unread notification count for a user.
   */
  public static function getUnreadCount(int $userId): int
  {
    try {
      if (!table_exists('notifications')) return 0;
      return db()->count('notifications', 'user_id = ? AND is_read = 0', [$userId]);
    } catch (\Throwable $e) {
      return 0;
    }
  }

  private static function checkInApp(): array
  {
    return ['status' => 'ok', 'type' => 'in_app'];
  }

  private static function checkNotifications(): array
  {
    $ok = table_exists('notifications');
    return ['status' => $ok ? 'ok' : 'no_table', 'type' => 'notifications'];
  }

  private static function checkEmail(): array
  {
    $configured = defined('SMTP_HOST') && SMTP_HOST !== '';
    return ['status' => $configured ? 'configured' : 'not_configured', 'type' => 'email'];
  }

  private static function checkWhatsApp(): array
  {
    $configured = defined('TWILIO_ACCOUNT_SID') && TWILIO_ACCOUNT_SID !== '';
    return ['status' => $configured ? 'configured' : 'not_configured', 'type' => 'whatsapp'];
  }

  private static function checkChatModule(): array
  {
    $chatDir = BASE_PATH . '/modules/chat';
    return ['status' => is_dir($chatDir) ? 'ok' : 'not_installed', 'type' => 'chat'];
  }

  private static function ensureNotificationsTable(): void
  {
    $pdo = db()->getConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      title VARCHAR(255) NOT NULL,
      message TEXT,
      is_read TINYINT(1) DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_notif_user (user_id),
      INDEX idx_notif_read (user_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }

  private static function ensureAnnouncementsTable(): void
  {
    $pdo = db()->getConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
      id INT AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      content TEXT,
      audience VARCHAR(50) DEFAULT 'all',
      priority VARCHAR(20) DEFAULT 'normal',
      created_by INT DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      expires_at DATETIME DEFAULT NULL,
      INDEX idx_ann_audience (audience),
      INDEX idx_ann_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  }
}
