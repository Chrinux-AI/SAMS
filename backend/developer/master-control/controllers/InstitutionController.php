<?php

/**
 * MCC — Institutional State Panel Controller
 * Live school state: academic mode, attendance, messaging, announcements.
 */
class InstitutionController
{
  public static function getStatus(): array
  {
    $data = [
      'academic_mode'      => 'UNKNOWN',
      'attendance_window'  => 'UNKNOWN',
      'messaging_status'   => 'UNKNOWN',
      'ai_services'        => 'UNKNOWN',
      'total_users'        => 0,
      'user_breakdown'     => [],
      'recent_announcements' => [],
    ];

    // Academic & Institutional state
    try {
      $state = InstitutionalState::snapshot();
      $data['academic_mode'] = $state['academic']['mode'] ?? 'ACTIVE';
      $data['attendance_window'] = $state['attendance']['status'] ?? 'OPEN';
    } catch (\Throwable $e) {
      $data['academic_mode'] = 'ACTIVE';
      $data['attendance_window'] = 'OPEN';
    }

    // Messaging status
    try {
      $commCheck = db()->fetchOne("SELECT COUNT(*) FROM comm_conversations");
      $data['messaging_status'] = $commCheck !== false ? 'ONLINE' : 'OFFLINE';
      $data['total_conversations'] = (int) $commCheck;
    } catch (\Throwable $e) {
      $data['messaging_status'] = 'ERROR';
    }

    // AI services
    try {
      $aiClasses = ['CoreAIService', 'AdminAIService', 'AIRouter'];
      $aiUp = 0;
      foreach ($aiClasses as $c) {
        if (class_exists($c)) $aiUp++;
      }
      $data['ai_services'] = $aiUp === count($aiClasses) ? 'HEALTHY' : 'PARTIAL';
    } catch (\Throwable $e) {
      $data['ai_services'] = 'ERROR';
    }

    // User counts
    try {
      $data['total_users'] = (int) db()->fetchOne("SELECT COUNT(*) FROM users WHERE status = 'active'");
      $breakdown = db()->fetchAll("SELECT role, COUNT(*) as cnt FROM users WHERE status = 'active' GROUP BY role ORDER BY cnt DESC") ?: [];
      foreach ($breakdown as $row) {
        $data['user_breakdown'][$row['role']] = (int) $row['cnt'];
      }
    } catch (\Throwable $e) {
    }

    // Recent announcements
    try {
      $data['recent_announcements'] = db()->fetchAll(
        "SELECT id, title, target_role, priority, created_at FROM announcements ORDER BY created_at DESC LIMIT 5"
      ) ?: [];
    } catch (\Throwable $e) {
    }

    // Maintenance mode
    $data['maintenance_mode'] = SecurityController::isMaintenanceMode();

    return $data;
  }

  public static function publishNotice(string $title, string $message, string $priority = 'normal'): array
  {
    try {
      db()->insert('announcements', [
        'title'       => $title,
        'content'     => $message,
        'target_role' => 'all',
        'priority'    => $priority,
        'created_by'  => $_SESSION['user_id'] ?? 0,
        'created_at'  => date('Y-m-d H:i:s'),
      ]);
      AuditLogger::log('mcc_announcement', 'announcements', "Published: $title (priority: $priority)", $_SESSION['user_id'] ?? null);
      return ['status' => 'published'];
    } catch (\Throwable $e) {
      return ['status' => 'error', 'message' => $e->getMessage()];
    }
  }
}
