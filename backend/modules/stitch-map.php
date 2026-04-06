<?php

/**
 * Stitch Screen -> SAMS Backend Route Map
 * Used for non-breaking integration and navigation normalization.
 */

return [
  // Auth
  'sams_secure_login' => '/attendance/modules/auth/login.php',
  'sams_forgot_password' => '/attendance/modules/auth/forgot-password.php',
  'sams_reset_password' => '/attendance/modules/auth/reset-password.php',
  'sams_confirm_account_otp' => '/attendance/modules/auth/confirm-account.php',
  'sams_register_institution' => '/attendance/modules/auth/register.php',

  // Dashboard
  'admin_dashboard_sams_overview' => '/attendance/modules/dashboard/admin.php',
  'admin_dashboard_adaptive_overview' => '/attendance/modules/dashboard/admin.php',
  'admin_overview_dashboard' => '/attendance/modules/dashboard/admin.php',
  'teacher_dashboard_adaptive_hub' => '/attendance/modules/dashboard/teacher.php',
  'teacher_dashboard_my_classes_tasks' => '/attendance/modules/dashboard/teacher.php',
  'student_portal_my_learning_hub' => '/attendance/modules/dashboard/student.php',
  'student_portal_adaptive_learning_hub' => '/attendance/modules/dashboard/student.php',
  'student_portal_ai_enhanced_academic_hub' => '/attendance/modules/dashboard/student.php',
  'parent_portal_family_overview' => '/attendance/modules/dashboard/parent.php',
  'parent_portal_academic_progress' => '/attendance/modules/dashboard/parent.php',

  // Modules
  'user_management_directory' => '/attendance/modules/users/index.php',
  'class_management_academic_structure' => '/attendance/modules/classes/index.php',
  'attendance_tracker_daily_records' => '/attendance/modules/attendance/index.php',
  'activity_details_and_attendance' => '/attendance/modules/attendance/index.php',
  'communication_hub_conversations' => '/attendance/modules/communication/index.php',
  'analytics_reports_system_insights' => '/attendance/modules/reports/index.php',
  'system_health_audit_logs' => '/attendance/admin/system-health.php',
  'account_settings_user_profile' => '/attendance/modules/settings/index.php',
  'ai_onboarding_bulk_user_creator' => '/attendance/admin/ai-user-creator.php',

  // Role dashboards
  'accountant_dashboard_fiscal_overview' => '/attendance/accountant/dashboard.php',
  'bursar_dashboard_financial_oversight' => '/attendance/bursar/dashboard.php',
  'finance_dashboard_bursar_accountant' => '/attendance/admin/financial-management.php',
  'librarian_dashboard_resource_hub' => '/attendance/librarian/dashboard.php',
  'library_management_librarian_hub' => '/attendance/librarian/inventory.php',
  'transport_manager_fleet_coordination' => '/attendance/transport/dashboard.php',
  'transport_logistics_fleet_overview' => '/attendance/transport/dashboard.php',
  'forum_moderator_community_controls' => '/attendance/forum-moderator/dashboard.php',

  // AI Copilot
  'sams_ai_copilot_backend_architecture_integration' => '/attendance/modules/ai-copilot/architecture.php',
  'neural_comm_center_active_streams' => '/attendance/communication/conversations.php',
  'theme_selection_os_mainframe' => '/attendance/developer/themes.php',
];
