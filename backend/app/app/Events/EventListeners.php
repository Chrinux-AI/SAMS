<?php

/**
 * Event Listeners — Registers default system event handlers.
 * Call EventListeners::register() during bootstrap to wire up all default listeners.
 */

require_once __DIR__ . '/EventDispatcher.php';
require_once __DIR__ . '/SystemEvents.php';

class EventListeners
{
  /**
   * Register all default event listeners.
   */
  public static function register(): void
  {
    // === Audit logging for user actions ===
    EventDispatcher::listen(SystemEvents::USER_CREATED, function ($data) {
      self::audit('create', 'users', "User created: " . ($data['email'] ?? 'unknown'), $data['target_id'] ?? null);
    });

    EventDispatcher::listen(SystemEvents::USER_UPDATED, function ($data) {
      self::audit('update', 'users', "User updated: " . ($data['changes'] ?? ''), $data['target_id'] ?? null);
    });

    EventDispatcher::listen(SystemEvents::USER_DELETED, function ($data) {
      self::audit('delete', 'users', "User deleted", $data['target_id'] ?? null);
    });

    EventDispatcher::listen(SystemEvents::ROLE_CHANGED, function ($data) {
      self::audit('role_change', 'users', "Role changed from {$data['old_role']} to {$data['new_role']}", $data['target_id'] ?? null);
    });

    // === Authentication events ===
    EventDispatcher::listen(SystemEvents::LOGIN_SUCCESS, function ($data) {
      self::audit('login', 'auth', "Successful login for user #{$data['user_id']}");
    });

    EventDispatcher::listen(SystemEvents::LOGIN_FAILED, function ($data) {
      self::audit('login_failed', 'auth', "Failed login for: " . ($data['email'] ?? 'unknown'));
    });

    EventDispatcher::listen(SystemEvents::ACCOUNT_LOCKED, function ($data) {
      self::audit('account_locked', 'auth', "Account locked: " . ($data['email'] ?? ''));
    });

    // === Notice events ===
    EventDispatcher::listen(SystemEvents::NOTICE_POSTED, function ($data) {
      self::audit('create', 'notices', "Notice posted: " . ($data['title'] ?? ''), $data['notice_id'] ?? null);
      // Queue notification for targeted users
      self::queueNotifications('New Notice', $data['title'] ?? 'New notice posted', $data);
    });

    // === Messaging events ===
    EventDispatcher::listen(SystemEvents::MESSAGE_SENT, function ($data) {
      // Update unread counts for conversation participants
      self::updateUnreadCounts($data);
    });

    // === Profile events ===
    EventDispatcher::listen(SystemEvents::PROFILE_CHANGED, function ($data) {
      self::audit('update', 'profiles', "Profile updated", $data['user_id'] ?? null);
    });

    EventDispatcher::listen(SystemEvents::AVATAR_UPDATED, function ($data) {
      self::audit('update', 'profiles', "Avatar changed", $data['user_id'] ?? null);
    });

    // === Attendance events ===
    EventDispatcher::listen(SystemEvents::ATTENDANCE_MARKED, function ($data) {
      self::audit('create', 'attendance', "Attendance marked for class #{$data['class_id']}", $data['class_id'] ?? null);
    });

    // === Settings events ===
    EventDispatcher::listen(SystemEvents::SETTINGS_CHANGED, function ($data) {
      self::audit('settings', 'settings', "Settings changed: " . ($data['section'] ?? 'unknown'));
    });
  }

  /**
   * Record an audit log entry.
   */
  private static function audit(string $action, string $model, string $details, ?int $targetId = null): void
  {
    try {
      // Use AuditLogger if available, otherwise direct insert
      if (class_exists('AuditLogger')) {
        AuditLogger::log($action, $model, $details, null, $targetId);
      } else {
        db()->insert('audit_logs', [
          'user_id'    => $_SESSION['user_id'] ?? null,
          'action'     => $action,
          'model'      => $model,
          'target_id'  => $targetId,
          'details'    => mb_substr($details, 0, 1000),
          'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
          'created_at' => date('Y-m-d H:i:s'),
        ]);
      }
    } catch (\Throwable $e) {
      error_log("EventListener audit failed: " . $e->getMessage());
    }
  }

  /**
   * Queue notifications for a notice event.
   */
  private static function queueNotifications(string $title, string $body, array $data): void
  {
    try {
      $visibility = $data['visibility'] ?? 'authenticated';
      $createdBy = $data['_user_id'] ?? null;

      if ($visibility === 'public') {
        // No user-specific notifications for public notices
        return;
      }

      // Get target users based on visibility
      if ($visibility === 'role_specific' && !empty($data['target_role'])) {
        $users = db()->fetchAll(
          "SELECT id FROM users WHERE role = :role AND status = 'active'",
          ['role' => $data['target_role']]
        );
      } else {
        // All active users
        $users = db()->fetchAll(
          "SELECT id FROM users WHERE status = 'active'"
        );
      }

      foreach ($users as $user) {
        if ($user['id'] == $createdBy) {
          continue; // Skip the creator
        }
        db()->insert('user_notifications', [
          'user_id'    => $user['id'],
          'title'      => mb_substr($title, 0, 255),
          'body'       => mb_substr($body, 0, 1000),
          'type'       => 'notice',
          'reference_id' => $data['notice_id'] ?? null,
          'is_read'    => 0,
          'created_at' => date('Y-m-d H:i:s'),
        ]);
      }
    } catch (\Throwable $e) {
      error_log("Notification queue failed: " . $e->getMessage());
    }
  }

  /**
   * Update unread counts when a message is sent.
   */
  private static function updateUnreadCounts(array $data): void
  {
    // The SSE endpoint handles real-time delivery — this is for persistence
    try {
      if (isset($data['conversation_id']) && isset($data['sender_id'])) {
        db()->query(
          "UPDATE conversation_participants
                     SET unread_count = unread_count + 1
                     WHERE conversation_id = :cid AND user_id != :sid",
          ['cid' => $data['conversation_id'], 'sid' => $data['sender_id']]
        );
      }
    } catch (\Throwable $e) {
      error_log("Unread count update failed: " . $e->getMessage());
    }
  }
}
