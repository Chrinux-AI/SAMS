<?php

require_once __DIR__ . '/CoreAIService.php';

/**
 * Parent AI Service — Child-only data access with limited analytics.
 */
class ParentAIService extends CoreAIService
{
  public function __construct()
  {
    parent::__construct('parent');

    $this->allowedTopics = [
      'child attendance',
      'grades',
      'behavior',
      'schedule',
      'meetings',
      'notices',
      'fees',
      'reports',
      'teacher contact',
      'performance',
      'parent portal',
      'activities',
      'announcements',
      'results',
    ];

    $this->blockedTopics = [
      'other children',
      'other parents',
      'teacher salaries',
      'admin settings',
      'system config',
      'database',
      'source code',
      'financial internals',
      'security',
      'api endpoints',
      'other families',
    ];

    $this->systemPrompt = 'You are the SAMS Parent AI Assistant. You help parents monitor '
      . 'their linked children\'s attendance, grades, behavior, and school activities. '
      . 'You can only access data for the parent\'s own children. Never reveal information '
      . 'about other families or internal school operations.';

    $this->maxTokens = 500;
    $this->rateLimitPerMinute = 20;
  }

  protected function buildContext(int $userId): array
  {
    $context = parent::buildContext($userId);

    try {
      $children = db()->fetchAll(
        "SELECT u.id, u.full_name FROM parent_student ps
                 JOIN users u ON u.id = ps.student_id
                 WHERE ps.parent_id = :pid",
        ['pid' => $userId]
      );
      $context['children'] = $children;
    } catch (\Throwable $e) {
      $context['children'] = [];
    }

    return $context;
  }

  protected function generateResponse(string $message, array $context): string
  {
    $lower = mb_strtolower($message);
    $children = $context['children'] ?? [];
    $names = array_column($children, 'full_name');
    $childList = $names ? implode(', ', $names) : 'No children linked yet';

    if (str_contains($lower, 'attendance')) {
      return "Your Children's Attendance:\n• Linked children: {$childList}\n• View attendance in Parent > Attendance\n• Daily and monthly reports available\n• You'll be notified of any absences.";
    }

    if (str_contains($lower, 'grade') || str_contains($lower, 'result') || str_contains($lower, 'performance')) {
      return "Academic Performance:\n• View grades in Parent > Grades\n• Download report cards from Parent > Reports\n• Track progress over time with charts\n• Contact teachers for detailed discussions.";
    }

    if (str_contains($lower, 'meeting') || str_contains($lower, 'teacher')) {
      return "Teacher Meetings:\n• Book a meeting slot in Parent > Book Meeting\n• View available teacher hours\n• Receive confirmation via email\n• Cancel or reschedule in advance.";
    }

    if (str_contains($lower, 'fee') || str_contains($lower, 'payment')) {
      return "Fee Information:\n• View fee invoices in Parent > Fees\n• Check payment status and history\n• Download receipts\n• Contact the Bursar for payment queries.";
    }

    return "Welcome! I'm your Parent AI Assistant.\nLinked Children: {$childList}\n\nI can help with:\n• Attendance monitoring\n• Grade & performance tracking\n• Teacher meeting booking\n• Fee & payment information\n• School notices & events\n\nWhat would you like to know?";
  }
}
