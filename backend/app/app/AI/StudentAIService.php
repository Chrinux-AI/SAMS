<?php

require_once __DIR__ . '/CoreAIService.php';

/**
 * Student AI Service — Self-data access only (personal records, attendance, grades).
 */
class StudentAIService extends CoreAIService
{
  public function __construct()
  {
    parent::__construct('student');

    $this->allowedTopics = [
      'attendance',
      'grades',
      'schedule',
      'assignments',
      'exams',
      'resources',
      'library',
      'study groups',
      'profile',
      'notifications',
      'class',
      'homework',
      'results',
      'timetable',
      'announcements',
    ];

    $this->blockedTopics = [
      'other students',
      'teacher salaries',
      'admin panel',
      'financial records',
      'system settings',
      'database',
      'security',
      'other users',
      'payroll',
      'source code',
      'api endpoints',
      'server',
    ];

    $this->systemPrompt = 'You are the SAMS Student AI Assistant. You help students view their '
      . 'own attendance, grades, class schedule, and resources. You can only access the '
      . 'requesting student\'s personal data. Never reveal other students\' information.';

    $this->maxTokens = 500;
    $this->rateLimitPerMinute = 20;
  }

  protected function buildContext(int $userId): array
  {
    $context = parent::buildContext($userId);

    try {
      $user = db()->fetchOne(
        "SELECT full_name, email, role FROM users WHERE id = :id",
        ['id' => $userId]
      );
      $context['student_name'] = $user['full_name'] ?? 'Student';

      $enrollment = db()->fetchAll(
        "SELECT c.class_name FROM class_enrollments ce
                 JOIN classes c ON c.id = ce.class_id
                 WHERE ce.student_id = :sid",
        ['sid' => $userId]
      );
      $context['enrolled_classes'] = $enrollment;
    } catch (\Throwable $e) {
      $context['student_name'] = 'Student';
      $context['enrolled_classes'] = [];
    }

    return $context;
  }

  protected function generateResponse(string $message, array $context): string
  {
    $lower = mb_strtolower($message);
    $name = $context['student_name'] ?? 'Student';

    if (str_contains($lower, 'attendance')) {
      return "Hi {$name}! To view your attendance:\n• Go to Student > Attendance\n• Filter by date range or class\n• Your attendance percentage is shown at the top\n• Contact your teacher if you see any discrepancies.";
    }

    if (str_contains($lower, 'grade') || str_contains($lower, 'result') || str_contains($lower, 'score')) {
      return "Your Grades & Results:\n• View current grades in Student > Grades\n• Past exam results in Student > Results\n• Report cards can be downloaded from Student > Reports\n• Talk to your teacher about any grade questions.";
    }

    if (str_contains($lower, 'class') || str_contains($lower, 'schedule') || str_contains($lower, 'timetable')) {
      $classes = array_column($context['enrolled_classes'] ?? [], 'class_name');
      $list = $classes ? implode(', ', $classes) : 'Check Student > Classes for enrollment';
      return "Your Enrolled Classes: {$list}\n\nView your timetable under Student > Schedule.";
    }

    if (str_contains($lower, 'library') || str_contains($lower, 'book')) {
      return "Library Services:\n• Browse books in Student > Library\n• Reserve books online\n• Check your borrowed books and due dates\n• Return books on time to avoid fines.";
    }

    return "Hi {$name}! I'm your Student AI Assistant. I can help with:\n• Your attendance records\n• Grades & exam results\n• Class schedule & timetable\n• Library & resources\n• Study groups & assignments\n\nWhat would you like to know?";
  }
}
