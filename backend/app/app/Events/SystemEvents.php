<?php

/**
 * System Event definitions — constants and factory for standard SAMS events.
 * All events dispatched through EventDispatcher should use these names.
 */
class SystemEvents
{
  // === User Events ===
  public const USER_CREATED       = 'UserCreated';
  public const USER_UPDATED       = 'UserUpdated';
  public const USER_DELETED       = 'UserDeleted';
  public const USER_APPROVED      = 'UserApproved';
  public const USER_SUSPENDED     = 'UserSuspended';
  public const ROLE_CHANGED       = 'RoleChanged';

  // === Authentication Events ===
  public const LOGIN_SUCCESS      = 'LoginSuccess';
  public const LOGIN_FAILED       = 'LoginFailed';
  public const LOGOUT             = 'Logout';
  public const SESSION_TIMEOUT    = 'SessionTimeout';
  public const ACCOUNT_LOCKED     = 'AccountLocked';
  public const PASSWORD_CHANGED   = 'PasswordChanged';

  // === Student Events ===
  public const STUDENT_UPDATED    = 'StudentUpdated';
  public const STUDENT_ENROLLED   = 'StudentEnrolled';
  public const STUDENT_UNENROLLED = 'StudentUnenrolled';

  // === Attendance Events ===
  public const ATTENDANCE_MARKED  = 'AttendanceMarked';
  public const ATTENDANCE_UPDATED = 'AttendanceUpdated';

  // === Notice & Communication Events ===
  public const NOTICE_POSTED      = 'NoticePosted';
  public const NOTICE_UPDATED     = 'NoticeUpdated';
  public const NOTICE_DELETED     = 'NoticeDeleted';

  // === Messaging Events ===
  public const MESSAGE_SENT       = 'MessageSent';
  public const MESSAGE_READ       = 'MessageRead';
  public const MESSAGE_DELETED    = 'MessageDeleted';

  // === Profile Events ===
  public const PROFILE_CHANGED    = 'ProfileChanged';
  public const AVATAR_UPDATED     = 'AvatarUpdated';

  // === Academic Events ===
  public const GRADE_POSTED       = 'GradePosted';
  public const EXAM_SCHEDULED     = 'ExamScheduled';

  // === System Events ===
  public const SETTINGS_CHANGED   = 'SettingsChanged';
  public const BACKUP_CREATED     = 'BackupCreated';
  public const SYSTEM_ERROR       = 'SystemError';

  // === Operational Flow Events ===
  public const ROUTE_FAILURE      = 'RouteFailure';
  public const PERFORMANCE_DROP   = 'PerformanceDrop';
  public const SECURITY_RISK      = 'SecurityRisk';
  public const CRITICAL_FAILURE   = 'CriticalFailure';
  public const FAILURE_CONTAINED  = 'FailureContained';
  public const CSRF_VIOLATION     = 'CsrfViolation';
  public const ROLE_VIOLATION     = 'RoleViolation';
  public const UNAUTHORIZED_ACCESS = 'UnauthorizedAccess';

  /**
   * Build a standard event data payload.
   */
  public static function payload(string $event, array $data = []): array
  {
    return array_merge($data, [
      'event'      => $event,
      'actor_id'   => $_SESSION['user_id'] ?? null,
      'actor_role' => $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'system',
      'timestamp'  => date('c'),
    ]);
  }
}
