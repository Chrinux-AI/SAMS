<?php

/**
 * Centralized Route Map
 * Maps logical route names to file paths relative to project root.
 * Usage: named_route('admin.dashboard') => '/attendance/admin/dashboard.php'
 */

if (!defined('BASE_PATH')) {
  define('BASE_PATH', dirname(__DIR__));
}

$GLOBALS['_ROUTE_MAP'] = [

  // ── Authentication ──
  'login'              => 'login.php',
  'logout'             => 'logout.php',
  'register'           => 'register.php',
  'forgot_password'    => 'forgot-password.php',
  'reset_password'     => 'reset-password.php',
  'verify_email'       => 'verify-email.php',
  'verify_otp'         => 'verify-otp.php',
  'activate_account'   => 'activate-account.php',
  'confirm_account'    => 'confirm-account.php',

  // ── Admin ──
  'admin.dashboard'    => 'admin/dashboard.php',
  'admin.students'     => 'admin/students.php',
  'admin.teachers'     => 'admin/teachers.php',
  'admin.attendance'   => 'admin/attendance.php',
  'admin.classes'      => 'admin/classes.php',
  'admin.reports'      => 'admin/reports.php',
  'admin.analytics'    => 'admin/analytics.php',
  'admin.settings'     => 'admin/settings.php',
  'admin.users'        => 'admin/users.php',
  'admin.approve_users' => 'admin/approve-users.php',
  'admin.announcements' => 'admin/announcements.php',
  'admin.bulk_import'  => 'admin/bulk-import.php',
  'admin.audit_logs'   => 'admin/audit-logs.php',
  'admin.backup'       => 'admin/backup-export.php',
  'admin.notices'      => 'admin/notices.php',
  'admin.payroll'      => 'admin/payroll.php',
  'admin.grades'       => 'admin/grades.php',
  'admin.timetable'    => 'admin/timetable.php',
  'admin.departments'  => 'admin/departments.php',
  'admin.system_health' => 'admin/system-health.php',

  // ── Teacher ──
  'teacher.dashboard'  => 'teacher/dashboard.php',
  'teacher.attendance' => 'teacher/attendance.php',
  'teacher.grades'     => 'teacher/grades.php',
  'teacher.students'   => 'teacher/students.php',
  'teacher.assignments' => 'teacher/assignments.php',
  'teacher.analytics'  => 'teacher/analytics.php',
  'teacher.schedule'   => 'teacher/schedule.php',
  'teacher.reports'    => 'teacher/reports.php',
  'teacher.profile'    => 'teacher/profile.php',

  // ── Student ──
  'student.dashboard'  => 'student/dashboard.php',
  'student.attendance' => 'student/attendance.php',
  'student.grades'     => 'student/grades.php',
  'student.assignments' => 'student/assignments.php',
  'student.schedule'   => 'student/schedule.php',
  'student.profile'    => 'student/profile.php',
  'student.checkin'    => 'student/checkin.php',
  'student.id_card'    => 'student/id-card.php',
  'student.study_groups' => 'student/study-groups.php',
  'student.study_group_view' => 'student/study-group-view.php',

  // ── Parent ──
  'parent.dashboard'   => 'parent/dashboard.php',
  'parent.children'    => 'parent/children.php',
  'parent.attendance'  => 'parent/attendance.php',
  'parent.grades'      => 'parent/grades.php',
  'parent.fees'        => 'parent/fees.php',
  'parent.meetings'    => 'parent/book-meeting.php',
  'parent.profile'     => 'parent/profile.php',

  // ── Owner ──
  'owner.dashboard'    => 'owner/dashboard.php',
  'owner.overview'     => 'owner/overview.php',
  'owner.students'     => 'owner/students.php',
  'owner.teachers'     => 'owner/teachers.php',
  'owner.parents'      => 'owner/parents.php',
  'owner.users'        => 'owner/users.php',
  'owner.approvals'    => 'owner/approve-users.php',
  'owner.classes'      => 'owner/classes.php',
  'owner.enrollment'   => 'owner/class-enrollment.php',
  'owner.attendance'   => 'owner/attendance.php',
  'owner.reports'      => 'owner/reports.php',
  'owner.notices'      => 'owner/notices.php',
  'owner.events'       => 'owner/events.php',
  'owner.financial'    => 'owner/financial-management.php',
  'owner.library'      => 'owner/library-management.php',
  'owner.transport'    => 'owner/transport-management.php',
  'owner.activity'     => 'owner/activity-monitor.php',
  'owner.backup'       => 'owner/backup-export.php',
  'owner.analytics'    => 'owner/analytics.php',
  'owner.system_health' => 'owner/system-health.php',
  'owner.roles'        => 'owner/role-management.php',
  'owner.settings'     => 'owner/settings.php',

  // ── Principal ──
  'principal.dashboard' => 'principal/dashboard.php',
  'principal.overview'  => 'principal/overview.php',
  'principal.students'  => 'principal/students.php',
  'principal.teachers'  => 'principal/teachers.php',
  'principal.parents'   => 'principal/parents.php',
  'principal.users'     => 'principal/users.php',
  'principal.approvals' => 'principal/approve-users.php',
  'principal.classes'   => 'principal/classes.php',
  'principal.enrollment' => 'principal/class-enrollment.php',
  'principal.attendance' => 'principal/attendance.php',
  'principal.reports'   => 'principal/reports.php',
  'principal.events'    => 'principal/events.php',
  'principal.notices'   => 'principal/notices.php',
  'principal.analytics' => 'principal/analytics.php',
  'principal.system_health' => 'principal/system-health.php',
  'principal.activity'  => 'principal/activity-monitor.php',
  'principal.roles'     => 'principal/role-management.php',
  'principal.settings'  => 'principal/settings.php',

  // ── Staff ──
  'staff.dashboard'    => 'staff/dashboard.php',
  'staff.tasks'        => 'staff/tasks.php',
  'staff.support'      => 'staff/student-support.php',
  'staff.reports'      => 'staff/reports.php',
  'staff.settings'     => 'staff/settings.php',

  // ── Nurse ──
  'nurse.dashboard'    => 'nurse/dashboard.php',
  'nurse.health_records' => 'nurse/health-records.php',
  'nurse.first_aid'    => 'nurse/first-aid.php',
  'nurse.medications'  => 'nurse/medications.php',
  'nurse.wellness'     => 'nurse/wellness.php',
  'nurse.reports'      => 'nurse/reports.php',
  'nurse.settings'     => 'nurse/settings.php',

  // ── Communication ──
  'comm.conversations' => 'communication/conversations.php',
  'comm.compose'       => 'communication/compose.php',
  'comm.notices'       => 'communication/notices.php',

  // ── Forum ──
  'forum.index'        => 'forum/index.php',
  'forum.topics'       => 'forum/topics.php',

  // ── Accountant ──
  'accountant.dashboard' => 'accountant/dashboard.php',
  'accountant.income'  => 'accountant/income.php',
  'accountant.expenses' => 'accountant/expenses.php',
  'accountant.payroll' => 'accountant/payroll.php',
  'accountant.reports' => 'accountant/reports.php',
  'accountant.ledger'  => 'accountant/ledger.php',
  'accountant.budget'  => 'accountant/budget.php',

  // ── Bursar ──
  'bursar.dashboard'   => 'bursar/dashboard.php',

  // ── Librarian ──
  'librarian.dashboard' => 'librarian/dashboard.php',

  // ── Transport ──
  'transport.dashboard' => 'transport/dashboard.php',

  // ── Developer ──
  'dev.portal'         => 'developer/index.php',
  'dev.mcc'            => 'developer/master-control/index.php',
  'dev.aci'            => 'developer/aci-center.php',
  'dev.aic'            => 'developer/aic-center.php',
  'dev.health'         => 'developer/system-health.php',
  'dev.monitor'        => 'developer/system-monitor.php',
  'dev.devops'         => 'developer/devops-center.php',
  'dev.healing'        => 'developer/healing-center.php',
  'dev.os'             => 'developer/os-center.php',
  'dev.autofix'        => 'developer/autofix-center.php',
  'dev.settings'       => 'developer/settings.php',
  'dev.logs'           => 'developer/logs.php',
  'dev.ai'             => 'developer/ai-center.php',
  'dev.debug'          => 'developer/debug-overlay.php',
  'dev.ecosystem'      => 'developer/ecosystem-center.php',
  'dev.intelligence'   => 'developer/intelligence-center.php',
  'dev.modules'        => 'developer/modules.php',
  'dev.themes'         => 'developer/themes.php',
  'dev.database'       => 'developer/database-monitor.php',
  'dev.security'       => 'developer/security-center.php',
  'dev.performance'    => 'developer/performance.php',
  'dev.ai_training'    => 'developer/ai-training.php',

  // ── API ──
  'api.aci.status'     => 'api/aci/status.php',
  'api.aci.execute'    => 'api/aci/execute.php',
  'api.aci.cycle'      => 'api/aci/cycle.php',
  'api.aci.recommendations' => 'api/aci/recommendations.php',
  'api.aic.status'     => 'api/aic/status.php',
  'api.aic.insights'   => 'api/aic/insights.php',
  'api.intelligence'   => 'api/intelligence.php',
  'api.health'         => 'api/health.php',
  'api.sync'           => 'api/sync.php',
  // MCC internal APIs
  'mcc.system_status'  => 'developer/master-control/api/system-status.php',
  'mcc.repair_trigger' => 'developer/master-control/api/repair-trigger.php',
  'mcc.cache_clear'    => 'developer/master-control/api/cache-clear.php',
  'mcc.restart_service' => 'developer/master-control/api/restart-service.php',
  'mcc.emergency'      => 'developer/master-control/api/emergency-lockdown.php',

  // Governance Engine APIs
  'governance.status'    => 'api/governance/status.php',
  'governance.validate'  => 'api/governance/validate.php',
  'governance.health'    => 'api/governance/health.php',
  'governance.classify'  => 'api/governance/classify.php',
  'governance.emergency' => 'api/governance/emergency.php',
  'governance.log'       => 'api/governance/log.php',

  // ── Module aliases (non-breaking reorganization) ──
  'module.auth.login'       => 'modules/auth/login.php',
  'module.auth.register'    => 'modules/auth/register.php',
  'module.auth.forgot'      => 'modules/auth/forgot-password.php',
  'module.auth.reset'       => 'modules/auth/reset-password.php',
  'module.auth.confirm'     => 'modules/auth/confirm-account.php',
  'module.dashboard.admin'  => 'modules/dashboard/admin.php',
  'module.dashboard.teacher' => 'modules/dashboard/teacher.php',
  'module.dashboard.student' => 'modules/dashboard/student.php',
  'module.dashboard.parent' => 'modules/dashboard/parent.php',
  'module.users.index'      => 'modules/users/index.php',
  'module.classes.index'    => 'modules/classes/index.php',
  'module.attendance.index' => 'modules/attendance/index.php',
  'module.communication.index' => 'modules/communication/index.php',
  'module.reports.index'    => 'modules/reports/index.php',
  'module.settings.index'   => 'modules/settings/index.php',
  'module.ai.execute'       => 'modules/ai-copilot/execute.php',
  'module.ai.architecture'  => 'modules/ai-copilot/architecture.php',
  'module.stitch.router'    => 'modules/stitch-router.php',
];

/**
 * Resolve a named route to a full URL.
 * Falls back to route() for unknown names.
 *
 * @param string $name Route name (e.g. 'admin.dashboard')
 * @return string Absolute URL path
 */
function named_route(string $name): string
{
  if (isset($GLOBALS['_ROUTE_MAP'][$name])) {
    return route($GLOBALS['_ROUTE_MAP'][$name]);
  }
  // Fall back to treating name as direct path
  return route($name);
}

/**
 * Check if a named route's file actually exists on disk.
 */
function route_exists(string $name): bool
{
  $path = $GLOBALS['_ROUTE_MAP'][$name] ?? $name;
  return is_file(BASE_PATH . '/' . ltrim($path, '/'));
}

/**
 * Get all registered route names.
 */
function get_route_names(): array
{
  return array_keys($GLOBALS['_ROUTE_MAP']);
}

/**
 * Get routes filtered by prefix (e.g. 'admin.' for all admin routes).
 */
function get_routes_by_prefix(string $prefix): array
{
  $result = [];
  foreach ($GLOBALS['_ROUTE_MAP'] as $name => $path) {
    if (str_starts_with($name, $prefix)) {
      $result[$name] = $path;
    }
  }
  return $result;
}
