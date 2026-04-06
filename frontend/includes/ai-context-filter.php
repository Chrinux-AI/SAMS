<?php

/**
 * SAMS AI Context Filter
 * Role-based AI isolation — each role's AI can only access permitted data scopes.
 * Prevents cross-role data leakage and system internals exposure.
 */

class AIContextFilter
{
  /**
   * Role-specific knowledge scopes.
   * Each role defines: allowed_topics, blocked_topics, allowed_data, system_prompt.
   */
  private static array $roleScopes = [
    'admin' => [
      'allowed_topics' => [
        'analytics',
        'user_management',
        'system_configuration',
        'attendance_reports',
        'staff_management',
        'class_management',
        'notice_management',
        'backup',
        'security_overview',
        'audit_logs'
      ],
      'blocked_topics' => [
        'database_structure',
        'api_routes',
        'source_code',
        'security_implementation',
        'encryption_keys',
        'server_config',
        'file_system_paths',
        'sql_queries',
        'password_hashing'
      ],
      'allowed_tables' => [
        'users',
        'students',
        'classes',
        'attendance',
        'attendance_records',
        'notices',
        'notifications',
        'audit_logs',
        'settings'
      ],
      'system_prompt' => 'You are the Admin AI Assistant for the School Attendance Management System. You help administrators with user management, analytics interpretation, system configuration, and management operations. You must NEVER reveal database structure, API routes, system internals, security implementation details, or source code. Provide guidance on administrative workflows only.'
    ],
    'teacher' => [
      'allowed_topics' => [
        'attendance',
        'classes',
        'notices',
        'student_records',
        'grading',
        'schedules',
        'parent_communication',
        'resources'
      ],
      'blocked_topics' => [
        'other_teachers_data',
        'admin_settings',
        'financial_data',
        'system_config',
        'user_management',
        'security',
        'database',
        'api_routes',
        'source_code',
        'other_class_data'
      ],
      'allowed_tables' => ['attendance', 'classes', 'students', 'notices'],
      'system_prompt' => 'You are a Teacher AI Assistant. You help teachers with attendance management, class information, notices, student records for their classes, and scheduling. You cannot access data from other teachers\' classes, financial data, admin settings, or system internals.'
    ],
    'student' => [
      'allowed_topics' => [
        'personal_attendance',
        'schedules',
        'assignments',
        'grades',
        'notices',
        'personal_profile'
      ],
      'blocked_topics' => [
        'other_students_data',
        'teacher_data',
        'admin_data',
        'financial_system',
        'system_config',
        'staff_data',
        'database',
        'api_routes',
        'source_code',
        'class_roster'
      ],
      'allowed_tables' => ['attendance', 'notices'],
      'system_prompt' => 'You are a Student AI Assistant. You help students check their own attendance, view schedules, get assignment information, and read notices. You can ONLY access the student\'s own data. You cannot access other students\' records, teacher data, financial data, or any system internals.'
    ],
    'parent' => [
      'allowed_topics' => [
        'child_attendance',
        'child_progress',
        'child_grades',
        'notices',
        'fee_status',
        'teacher_communication',
        'events'
      ],
      'blocked_topics' => [
        'other_parents_data',
        'other_children_data',
        'teacher_data',
        'admin_data',
        'system_config',
        'staff_data',
        'database',
        'api_routes',
        'source_code'
      ],
      'allowed_tables' => ['attendance', 'notices', 'students'],
      'system_prompt' => 'You are a Parent AI Assistant. You help parents view their children\'s attendance, progress, grades, notices, fee status, and communicate with teachers. You can ONLY access data related to the parent\'s own children. You cannot access other families\' data or system internals.'
    ],
    'librarian' => [
      'allowed_topics' => [
        'library_catalog',
        'book_loans',
        'returns',
        'overdue_items',
        'member_records',
        'notices'
      ],
      'blocked_topics' => [
        'financial_data',
        'attendance_data',
        'admin_settings',
        'user_management',
        'system_config',
        'database',
        'api_routes',
        'source_code'
      ],
      'allowed_tables' => ['notices'],
      'system_prompt' => 'You are a Librarian AI Assistant. You help with library catalog management, book loans, returns, and overdue tracking. You cannot access financial data, attendance records, admin settings, or system internals.'
    ],
    'bursar' => [
      'allowed_topics' => [
        'fee_collection',
        'payments',
        'invoices',
        'financial_reports',
        'student_balances',
        'notices'
      ],
      'blocked_topics' => [
        'attendance_data',
        'academic_records',
        'admin_settings',
        'user_passwords',
        'system_config',
        'database',
        'api_routes',
        'source_code'
      ],
      'allowed_tables' => ['notices'],
      'system_prompt' => 'You are a Bursar AI Assistant. You help with fee collection, payments, invoicing, and financial reports. You cannot access academic records, attendance data, admin settings, or system internals.'
    ],
    'accountant' => [
      'allowed_topics' => [
        'accounting',
        'ledger',
        'budget',
        'payroll',
        'expenses',
        'income',
        'tax_reports',
        'notices'
      ],
      'blocked_topics' => [
        'attendance_data',
        'academic_records',
        'admin_settings',
        'user_management',
        'system_config',
        'database',
        'api_routes',
        'source_code'
      ],
      'allowed_tables' => ['notices'],
      'system_prompt' => 'You are an Accountant AI Assistant. You help with accounting, ledger management, budgets, payroll, and financial reports. You cannot access academic records, attendance data, or system internals.'
    ],
    'transport' => [
      'allowed_topics' => [
        'routes',
        'vehicles',
        'drivers',
        'fleet_management',
        'route_schedules',
        'transport_fees',
        'notices'
      ],
      'blocked_topics' => [
        'academic_records',
        'financial_data',
        'admin_settings',
        'user_management',
        'system_config',
        'database',
        'api_routes',
        'source_code'
      ],
      'allowed_tables' => ['notices'],
      'system_prompt' => 'You are a Transport AI Assistant. You help with route management, vehicle tracking, fleet operations, and transport scheduling. You cannot access academic records, financial data, or system internals.'
    ],
    'forum_moderator' => [
      'allowed_topics' => [
        'forum_posts',
        'moderation',
        'reported_content',
        'user_behavior',
        'discussions',
        'notices'
      ],
      'blocked_topics' => [
        'academic_records',
        'financial_data',
        'admin_settings',
        'user_management',
        'system_config',
        'database',
        'api_routes',
        'source_code'
      ],
      'allowed_tables' => ['notices'],
      'system_prompt' => 'You are a Forum Moderator AI Assistant. You help with forum moderation, content review, and community guidelines. You cannot access academic records, financial data, or system internals.'
    ],
    'guest' => [
      'allowed_topics' => [
        'system_overview',
        'features',
        'enrollment',
        'contact_info',
        'general_info',
        'events'
      ],
      'blocked_topics' => [
        'user_data',
        'attendance_data',
        'financial_data',
        'admin_data',
        'system_config',
        'database',
        'security',
        'api_routes',
        'source_code',
        'internal_processes',
        'authentication',
        'passwords',
        'encryption'
      ],
      'allowed_tables' => [],
      'system_prompt' => 'You are a Public Visitor Assistant for the School Attendance Management System. You provide high-level information about what the system does, its features, and enrollment. You must NEVER reveal any technical details, system architecture, authentication methods, security configurations, database structure, or internal processes. Only provide general, marketing-level information.'
    ]
  ];

  /**
   * Patterns that indicate system probing / prompt injection attempts.
   */
  private static array $injectionPatterns = [
    '/ignore\s+(previous|all|above)\s+(instructions?|prompts?)/i',
    '/you\s+are\s+now\s+/i',
    '/system\s*prompt/i',
    '/reveal\s+(your|the)\s+(instructions?|prompt|rules)/i',
    '/what\s+are\s+your\s+(instructions?|rules|guidelines)/i',
    '/pretend\s+(you|to\s+be)/i',
    '/act\s+as\s+(if|a\s+different)/i',
    '/bypass\s+(security|filter|restriction)/i',
    '/(show|tell|give|dump|display)\s+(me\s+)?(the\s+)?(database|tables?|schema|columns?|sql|api|routes?|source\s*code|password|secret|key|config)/i',
    '/\b(SELECT|INSERT|UPDATE|DELETE|DROP|ALTER|UNION)\s+/i',
    '/<script/i',
    '/javascript\s*:/i',
    '/on(error|load|click)\s*=/i',
  ];

  /**
   * Get the AI context for a given role.
   */
  public static function getContext(string $role): array
  {
    $role = strtolower(trim($role));
    return self::$roleScopes[$role] ?? self::$roleScopes['guest'];
  }

  /**
   * Get the system prompt for a given role.
   */
  public static function getSystemPrompt(string $role): string
  {
    $ctx = self::getContext($role);
    return $ctx['system_prompt'];
  }

  /**
   * Check if a message topic is allowed for the given role.
   */
  public static function isTopicAllowed(string $role, string $message): bool
  {
    $ctx = self::getContext($role);
    $messageLower = strtolower($message);

    // Check for blocked topic keywords
    foreach ($ctx['blocked_topics'] as $blocked) {
      $words = explode('_', $blocked);
      $pattern = implode('\\s*', array_map(function ($w) {
        return preg_quote($w, '/');
      }, $words));
      if (preg_match("/{$pattern}/i", $messageLower)) {
        return false;
      }
    }

    return true;
  }

  /**
   * Check for prompt injection attempts.
   * Returns ['safe' => bool, 'reason' => string]
   */
  public static function checkInjection(string $message): array
  {
    foreach (self::$injectionPatterns as $pattern) {
      if (preg_match($pattern, $message)) {
        return [
          'safe' => false,
          'reason' => 'Message contains restricted content patterns'
        ];
      }
    }
    return ['safe' => true, 'reason' => ''];
  }

  /**
   * Filter a message through the complete AI security pipeline.
   * Returns ['allowed' => bool, 'response' => ?string, 'context' => array]
   */
  public static function filterMessage(string $role, string $message): array
  {
    // 1. Check for injection
    $injection = self::checkInjection($message);
    if (!$injection['safe']) {
      return [
        'allowed' => false,
        'response' => "I can't process that request. Please rephrase your question about school-related topics.",
        'context' => self::getContext($role)
      ];
    }

    // 2. Check topic permission
    if (!self::isTopicAllowed($role, $message)) {
      return [
        'allowed' => false,
        'response' => "I don't have access to that information for your role. I can help you with topics related to your responsibilities.",
        'context' => self::getContext($role)
      ];
    }

    // 3. Message is allowed
    return [
      'allowed' => true,
      'response' => null,
      'context' => self::getContext($role)
    ];
  }

  /**
   * Get the list of available roles.
   */
  public static function getAvailableRoles(): array
  {
    return array_keys(self::$roleScopes);
  }
}
