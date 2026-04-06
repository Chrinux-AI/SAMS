<?php

require_once __DIR__ . '/CoreAIService.php';

/**
 * Admin AI Service — Full management access with workflow guidance.
 */
class AdminAIService extends CoreAIService
{
  public function __construct()
  {
    parent::__construct('admin');

    $this->allowedTopics = [
      'user management',
      'attendance',
      'reports',
      'analytics',
      'settings',
      'classes',
      'teachers',
      'students',
      'parents',
      'notices',
      'roles',
      'system configuration',
      'audit logs',
      'bulk operations',
      'enrollment',
      'dashboard',
      'communication',
      'backup',
      'security',
      'notifications',
    ];

    $this->blockedTopics = [
      'database structure',
      'sql queries',
      'api routes',
      'source code',
      'server configuration',
      'file system',
      'credentials',
      'passwords',
      'encryption keys',
      'environment variables',
    ];

    $this->systemPrompt = 'You are the SAMS Admin AI Assistant. You help school administrators '
      . 'manage users, classes, attendance, notices, and system settings. You have access to '
      . 'administrative workflows and analytics. Never reveal database schemas, API endpoints, '
      . 'or internal implementation details. Focus on operational guidance.';

    $this->maxTokens = 800;
    $this->rateLimitPerMinute = 30;
  }

  protected function buildContext(int $userId): array
  {
    $context = parent::buildContext($userId);

    try {
      $context['stats'] = [
        'total_users'    => db()->count('users'),
        'total_students' => db()->count('users', "role = 'student'"),
        'total_teachers' => db()->count('users', "role = 'teacher'"),
        'total_classes'  => db()->count('classes'),
        'pending_approvals' => db()->count('users', "status = 'pending'"),
      ];
    } catch (\Throwable $e) {
      $context['stats'] = [];
    }

    return $context;
  }

  protected function generateResponse(string $message, array $context): string
  {
    $lower = mb_strtolower($message);
    $stats = $context['stats'] ?? [];

    // Dashboard / overview
    if (str_contains($lower, 'dashboard') || str_contains($lower, 'overview') || str_contains($lower, 'summary')) {
      $total = $stats['total_users'] ?? 0;
      $students = $stats['total_students'] ?? 0;
      $teachers = $stats['total_teachers'] ?? 0;
      $classes = $stats['total_classes'] ?? 0;
      $pending = $stats['pending_approvals'] ?? 0;
      return "System Overview:\n• Total Users: {$total}\n• Students: {$students}\n• Teachers: {$teachers}\n• Classes: {$classes}\n• Pending Approvals: {$pending}\n\nUse the Admin Dashboard for detailed analytics.";
    }

    // User management
    if (str_contains($lower, 'add user') || str_contains($lower, 'create user')) {
      return "To add a new user:\n1. Go to Admin > User Management\n2. Click 'Add New User'\n3. Fill in name, email, role, and password\n4. The user will receive an activation email\n\nFor bulk imports, use Admin > Bulk Import with a CSV file.";
    }

    // Attendance
    if (str_contains($lower, 'attendance')) {
      return "Attendance Management:\n• View attendance records via Admin > Attendance\n• Generate reports in Admin > Reports\n• Set up biometric attendance in Admin > Biometric Settings\n• Configure chronic absenteeism thresholds in Admin > Settings";
    }

    // Notices
    if (str_contains($lower, 'notice') || str_contains($lower, 'announcement')) {
      return "Notice Management:\n• Create notices in Admin > Notices\n• Set visibility: public, authenticated, or role-specific\n• Schedule notices for future publication\n• Pin important notices to keep them at the top";
    }

    return "I'm your Admin AI Assistant. I can help with:\n• User & role management\n• Attendance tracking & reports\n• Class & enrollment management\n• Notice & communication\n• System settings & configuration\n\nWhat would you like help with?";
  }
}
