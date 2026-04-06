<?php

/**
 * AI Copilot Action Registry
 * Central allow-list for intent -> endpoint/operation mapping.
 */
class AICopilotActionRegistry
{
  /**
   * @return array<string, array<string,mixed>>
   */
  public static function all(): array
  {
    return [
      'CREATE_CLASS' => [
        'endpoint' => '/api/class.php',
        'method' => 'POST',
        'permission' => 'manage_classes',
        'roles' => ['admin', 'principal', 'owner', 'super_admin'],
        'destructive' => false,
        'schema' => [
          'required' => ['name', 'grade_level'],
          'types' => [
            'name' => 'string',
            'grade_level' => 'string',
            'teacher_id' => 'int',
            'academic_year' => 'string'
          ]
        ]
      ],
      'UPDATE_CLASS' => [
        'endpoint' => '/api/class.php',
        'method' => 'PUT',
        'permission' => 'manage_classes',
        'roles' => ['admin', 'principal', 'owner', 'super_admin'],
        'destructive' => false,
        'schema' => [
          'required' => ['class_id'],
          'types' => [
            'class_id' => 'int',
            'name' => 'string',
            'grade_level' => 'string',
            'teacher_id' => 'int',
            'academic_year' => 'string'
          ]
        ]
      ],
      'DELETE_CLASS' => [
        'endpoint' => '/api/class.php',
        'method' => 'DELETE',
        'permission' => 'manage_classes',
        'roles' => ['admin', 'principal', 'owner', 'super_admin'],
        'destructive' => true,
        'schema' => [
          'required' => ['class_id'],
          'types' => [
            'class_id' => 'int'
          ]
        ]
      ],
      'MARK_ATTENDANCE' => [
        'endpoint' => '/api/attendance.php',
        'method' => 'POST',
        'permission' => 'mark_attendance',
        'roles' => ['admin', 'teacher', 'principal', 'owner', 'super_admin'],
        'destructive' => false,
        'schema' => [
          'required' => ['student_id', 'class_id', 'status'],
          'types' => [
            'student_id' => 'int',
            'class_id' => 'int',
            'status' => 'string',
            'date' => 'date'
          ]
        ]
      ],
      'SEND_NOTICE' => [
        'endpoint' => '/api/notifications.php',
        'method' => 'POST',
        'permission' => 'send_notifications',
        'roles' => ['admin', 'principal', 'owner', 'super_admin', 'teacher'],
        'destructive' => false,
        'schema' => [
          'required' => ['title', 'message'],
          'types' => [
            'title' => 'string',
            'message' => 'string',
            'audience' => 'string'
          ]
        ]
      ],
    ];
  }

  public static function get(string $intent): ?array
  {
    $all = self::all();
    return $all[$intent] ?? null;
  }

  public static function exists(string $intent): bool
  {
    return self::get($intent) !== null;
  }
}
