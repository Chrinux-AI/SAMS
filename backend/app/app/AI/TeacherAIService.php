<?php

require_once __DIR__ . '/CoreAIService.php';

/**
 * Teacher AI Service — Access to assigned classes and attendance data.
 */
class TeacherAIService extends CoreAIService
{
  public function __construct()
  {
    parent::__construct('teacher');

    $this->allowedTopics = [
      'attendance',
      'classes',
      'students',
      'grades',
      'schedule',
      'assignments',
      'resources',
      'behavior',
      'reports',
      'meetings',
      'syllabus',
      'exams',
      'study groups',
      'announcements',
    ];

    $this->blockedTopics = [
      'other teachers',
      'salaries',
      'admin settings',
      'system config',
      'database',
      'financial records',
      'payroll details',
      'source code',
      'security settings',
      'api endpoints',
    ];

    $this->systemPrompt = 'You are the SAMS Teacher AI Assistant. You help teachers manage '
      . 'attendance, grades, class resources, and student interactions for their assigned '
      . 'classes only. Never reveal data about other teachers or administrative internals.';

    $this->maxTokens = 600;
    $this->rateLimitPerMinute = 25;
  }

  protected function buildContext(int $userId): array
  {
    $context = parent::buildContext($userId);

    try {
      $classes = db()->fetchAll(
        "SELECT c.id, c.class_name FROM classes c WHERE c.class_teacher_id = :tid",
        ['tid' => $userId]
      );
      $context['assigned_classes'] = $classes;
      $context['class_count'] = count($classes);
    } catch (\Throwable $e) {
      $context['assigned_classes'] = [];
    }

    return $context;
  }

  protected function generateResponse(string $message, array $context): string
  {
    $lower = mb_strtolower($message);
    $classCount = $context['class_count'] ?? 0;

    if (str_contains($lower, 'attendance') || str_contains($lower, 'mark')) {
      return "Attendance Management:\n• Go to Teacher > Attendance to mark daily attendance\n• You have {$classCount} assigned class(es)\n• Use 'present', 'absent', 'late', or 'excused' statuses\n• Attendance reports are available under Teacher > Reports";
    }

    if (str_contains($lower, 'class') || str_contains($lower, 'student')) {
      $names = array_column($context['assigned_classes'] ?? [], 'class_name');
      $list = $names ? implode(', ', $names) : 'No classes assigned';
      return "Your Classes: {$list}\n\nManage students, grades, and resources for each class from the Teacher Dashboard.";
    }

    if (str_contains($lower, 'grade') || str_contains($lower, 'exam')) {
      return "Grading & Exams:\n• Enter grades via Teacher > Grades\n• Schedule exams under Teacher > Exam Schedule\n• View grading schemes in Teacher > Settings\n• Generate report cards from Teacher > Reports";
    }

    if (str_contains($lower, 'resource') || str_contains($lower, 'material')) {
      return "Teaching Resources:\n• Upload materials in Teacher > Resources\n• Share with specific classes\n• Supported formats: PDF, DOC, images\n• Students can download from their dashboard";
    }

    return "I'm your Teacher AI Assistant. I can help with:\n• Attendance marking & reports\n• Class & student management\n• Grading & exam scheduling\n• Teaching resources\n• Behavior logs & meetings\n\nWhat do you need help with?";
  }
}
