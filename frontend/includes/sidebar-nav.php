<?php

/**
 * SAMS Academic Sentinel — Sidebar Navigation
 * Stitch-aligned minimalist sidebar with Material Symbols icons
 * and role-based navigation sections.
 */

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$in_subdir = !in_array($current_dir, ['admin', 'owner', 'principal', 'staff', 'nurse', 'teacher', 'student', 'parent', 'librarian', 'bursar', 'accountant', 'transport', 'forum-moderator', 'general', 'chatbots', 'developer', 'attendance']);
$subdir_prefix = $in_subdir ? '../' : '';
$user_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? ($_SESSION['user_role'] ?? 'user');
$user_id = $_SESSION['user_id'] ?? 0;
$user_initials = strtoupper(substr($user_name, 0, 2));

// PHP compatibility helpers (for environments below PHP 8)
$starts_with = function ($haystack, $needle) {
  $haystack = (string)$haystack;
  $needle = (string)$needle;
  if (function_exists('str_starts_with')) {
    return str_starts_with($haystack, $needle);
  }
  return $needle === '' || substr($haystack, 0, strlen($needle)) === $needle;
};

$contains = function ($haystack, $needle) {
  $haystack = (string)$haystack;
  $needle = (string)$needle;
  if (function_exists('str_contains')) {
    return str_contains($haystack, $needle);
  }
  return $needle === '' || strpos($haystack, $needle) !== false;
};

// Get user profile picture
$user_avatar_url = '';
if ($user_id > 0) {
  try {
    $avatarRow = db()->fetchOne("SELECT profile_picture FROM users WHERE id = ?", [$user_id]);
    if ($avatarRow && !empty($avatarRow['profile_picture'])) {
      $user_avatar_url = '/attendance/' . $avatarRow['profile_picture'];
    }
  } catch (Exception $e) { /* column may not exist yet */
  }
}

// Get unread communication count
$unread_count = 0;
if ($user_id > 0) {
  try {
    $result = db()->fetchOne("
            SELECT COUNT(*) as count FROM comm_messages m
            JOIN comm_participants cp ON m.conversation_id = cp.conversation_id
            LEFT JOIN comm_reads cr ON m.id = cr.message_id AND cr.user_id = ?
            WHERE cp.user_id = ? AND m.sender_id != ? AND cr.id IS NULL
        ", [$user_id, $user_id, $user_id]);
    $unread_count = $result['count'] ?? 0;
  } catch (Exception $e) {
    $unread_count = 0;
  }
}

// ──── Role-Specific Navigation ────
// Icons now use Material Symbols names (not Font Awesome)
$nav_sections = [];

if ($user_role === 'admin') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'dashboard', 'label' => 'Dashboard'],
      'overview.php' => ['icon' => 'pie_chart', 'label' => 'Overview'],
      'team-selection.php' => ['icon' => 'groups', 'label' => 'Team Selection'],
    ],
    'People' => [
      'students.php' => ['icon' => 'school', 'label' => 'Students'],
      'teachers.php' => ['icon' => 'person', 'label' => 'Teachers'],
      'parents.php' => ['icon' => 'family_restroom', 'label' => 'Parents'],
    ],
    'Academic' => [
      'classes.php' => ['icon' => 'meeting_room', 'label' => 'Classes'],
      'attendance.php' => ['icon' => 'fact_check', 'label' => 'Attendance'],
      'events.php' => ['icon' => 'event', 'label' => 'Events'],
      'fee-management.php' => ['icon' => 'payments', 'label' => 'Fees'],
      'class-enrollment.php' => ['icon' => 'how_to_reg', 'label' => 'Enrollment'],
    ],
    'Communication' => [
      '../communication/conversations.php' => ['icon' => 'chat', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      '../notices.php' => ['icon' => 'campaign', 'label' => 'Notices'],
      '../forum/index.php' => ['icon' => 'forum', 'label' => 'Forum'],
      'notices.php' => ['icon' => 'edit_note', 'label' => 'Manage Notices'],
      'emergency-alerts.php' => ['icon' => 'warning', 'label' => 'Alerts'],
    ],
    'Analytics' => [
      'reports.php' => ['icon' => 'assessment', 'label' => 'Reports'],
      'analytics.php' => ['icon' => 'bar_chart', 'label' => 'Analytics'],
      'activity-monitor.php' => ['icon' => 'monitor_heart', 'label' => 'Activity Monitor'],
    ],
    'System' => [
      'users.php' => ['icon' => 'manage_accounts', 'label' => 'Users'],
      'registrations.php' => ['icon' => 'person_add', 'label' => 'Registrations'],
      'approve-users.php' => ['icon' => 'how_to_reg', 'label' => 'Approvals'],
      'manage-ids.php' => ['icon' => 'badge', 'label' => 'Manage IDs'],
      'system-health.php' => ['icon' => 'health_and_safety', 'label' => 'System Health'],
      'audit-logs.php' => ['icon' => 'description', 'label' => 'Audit Logs'],
      'backup-export.php' => ['icon' => 'cloud_upload', 'label' => 'Backup & Export'],
      'lms-settings.php' => ['icon' => 'school', 'label' => 'LMS Settings'],
      'settings.php' => ['icon' => 'settings', 'label' => 'Settings'],
    ],
    'AI Center' => [
      'ai-center/index.php' => ['icon' => 'psychology', 'label' => 'AI Dashboard'],
      'ai-center/anomaly-detection.php' => ['icon' => 'report', 'label' => 'Anomaly Detection'],
      'ai-center/security-monitor.php' => ['icon' => 'shield', 'label' => 'Security Monitor'],
      'ai-center/automation.php' => ['icon' => 'precision_manufacturing', 'label' => 'Automation'],
      'ai-center/documentation-engine.php' => ['icon' => 'auto_stories', 'label' => 'Documentation'],
    ],
  ];
} elseif ($user_role === 'owner') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'dashboard', 'label' => 'Dashboard'],
      'overview.php' => ['icon' => 'pie_chart', 'label' => 'Overview'],
      'team-selection.php' => ['icon' => 'groups', 'label' => 'Team Selection'],
    ],
    'People' => [
      'students.php' => ['icon' => 'school', 'label' => 'Students'],
      'teachers.php' => ['icon' => 'person', 'label' => 'Teachers'],
      'parents.php' => ['icon' => 'family_restroom', 'label' => 'Parents'],
      'users.php' => ['icon' => 'manage_accounts', 'label' => 'Users'],
      'approve-users.php' => ['icon' => 'how_to_reg', 'label' => 'Approvals'],
    ],
    'Academic' => [
      'classes.php' => ['icon' => 'meeting_room', 'label' => 'Classes'],
      'class-enrollment.php' => ['icon' => 'how_to_reg', 'label' => 'Enrollment'],
      'attendance.php' => ['icon' => 'fact_check', 'label' => 'Attendance'],
      'reports.php' => ['icon' => 'assessment', 'label' => 'Reports'],
    ],
    'Operations' => [
      'events.php' => ['icon' => 'event', 'label' => 'Events'],
      'notices.php' => ['icon' => 'edit_note', 'label' => 'Manage Notices'],
      'financial-management.php' => ['icon' => 'payments', 'label' => 'Financial Management'],
      'library-management.php' => ['icon' => 'menu_book', 'label' => 'Library Management'],
      'transport-management.php' => ['icon' => 'directions_bus', 'label' => 'Transport Management'],
      'activity-monitor.php' => ['icon' => 'monitor_heart', 'label' => 'Activity Monitor'],
      'backup-export.php' => ['icon' => 'cloud_upload', 'label' => 'Backup & Export'],
    ],
    'System' => [
      'analytics.php' => ['icon' => 'bar_chart', 'label' => 'Analytics'],
      'system-health.php' => ['icon' => 'health_and_safety', 'label' => 'System Health'],
      'role-management.php' => ['icon' => 'shield', 'label' => 'Role Management'],
      'settings.php' => ['icon' => 'settings', 'label' => 'Settings'],
    ],
  ];
} elseif (in_array($user_role, ['principal', 'vice_principal'], true)) {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'dashboard', 'label' => 'Dashboard'],
      'overview.php' => ['icon' => 'pie_chart', 'label' => 'Overview'],
      'team-selection.php' => ['icon' => 'groups', 'label' => 'Team Selection'],
    ],
    'People' => [
      'students.php' => ['icon' => 'school', 'label' => 'Students'],
      'teachers.php' => ['icon' => 'person', 'label' => 'Teachers'],
      'parents.php' => ['icon' => 'family_restroom', 'label' => 'Parents'],
      'users.php' => ['icon' => 'manage_accounts', 'label' => 'Users'],
      'approve-users.php' => ['icon' => 'how_to_reg', 'label' => 'Approvals'],
    ],
    'Academic' => [
      'classes.php' => ['icon' => 'meeting_room', 'label' => 'Classes'],
      'class-enrollment.php' => ['icon' => 'how_to_reg', 'label' => 'Enrollment'],
      'attendance.php' => ['icon' => 'fact_check', 'label' => 'Attendance'],
      'reports.php' => ['icon' => 'assessment', 'label' => 'Reports'],
    ],
    'Operations' => [
      'events.php' => ['icon' => 'event', 'label' => 'Events'],
      'notices.php' => ['icon' => 'edit_note', 'label' => 'Manage Notices'],
      'analytics.php' => ['icon' => 'bar_chart', 'label' => 'Analytics'],
      'activity-monitor.php' => ['icon' => 'monitor_heart', 'label' => 'Activity Monitor'],
    ],
    'System' => [
      'system-health.php' => ['icon' => 'health_and_safety', 'label' => 'System Health'],
      'role-management.php' => ['icon' => 'shield', 'label' => 'Role Management'],
      'settings.php' => ['icon' => 'settings', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'staff') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'dashboard', 'label' => 'Dashboard'],
      'team-selection.php' => ['icon' => 'groups', 'label' => 'Team Selection'],
    ],
    'Operations' => [
      'tasks.php' => ['icon' => 'task_alt', 'label' => 'Tasks'],
      'student-support.php' => ['icon' => 'support_agent', 'label' => 'Student Support'],
      'reports.php' => ['icon' => 'summarize', 'label' => 'Reports'],
    ],
    'Communication' => [
      '../communication/conversations.php' => ['icon' => 'chat', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      '../notices.php' => ['icon' => 'campaign', 'label' => 'Notices'],
      '../forum/index.php' => ['icon' => 'forum', 'label' => 'Forum'],
      'settings.php' => ['icon' => 'settings', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'nurse') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'dashboard', 'label' => 'Dashboard'],
      'team-selection.php' => ['icon' => 'groups', 'label' => 'Team Selection'],
    ],
    'Health' => [
      'health-records.php' => ['icon' => 'folder_shared', 'label' => 'Health Records'],
      'first-aid.php' => ['icon' => 'healing', 'label' => 'First Aid'],
      'medications.php' => ['icon' => 'medication', 'label' => 'Medications'],
      'wellness.php' => ['icon' => 'favorite', 'label' => 'Wellness'],
      'reports.php' => ['icon' => 'summarize', 'label' => 'Reports'],
    ],
    'Communication' => [
      '../communication/conversations.php' => ['icon' => 'chat', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      '../notices.php' => ['icon' => 'campaign', 'label' => 'Notices'],
      '../forum/index.php' => ['icon' => 'forum', 'label' => 'Forum'],
      'settings.php' => ['icon' => 'settings', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'teacher') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'dashboard', 'label' => 'Dashboard'],
      'my-classes.php' => ['icon' => 'meeting_room', 'label' => 'My Classes'],
      'students.php' => ['icon' => 'school', 'label' => 'Students'],
      'attendance.php' => ['icon' => 'fact_check', 'label' => 'Attendance'],
      'team-selection.php' => ['icon' => 'groups', 'label' => 'Team Selection'],
    ],
    'Academic' => [
      'materials.php' => ['icon' => 'upload_file', 'label' => 'Materials'],
      'assignments.php' => ['icon' => 'assignment', 'label' => 'Assignments'],
      'grades.php' => ['icon' => 'grading', 'label' => 'Grades'],
      'class-enrollment.php' => ['icon' => 'how_to_reg', 'label' => 'Enrollment'],
    ],
    'Communication' => [
      '../communication/conversations.php' => ['icon' => 'chat', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      'parent-comms.php' => ['icon' => 'family_restroom', 'label' => 'Parent Comms'],
      '../notices.php' => ['icon' => 'campaign', 'label' => 'Notices'],
      '../forum/index.php' => ['icon' => 'forum', 'label' => 'Forum'],
      'resources.php' => ['icon' => 'library_books', 'label' => 'Resources'],
      'behavior-logs.php' => ['icon' => 'psychology_alt', 'label' => 'Behavior Logs'],
      'meeting-hours.php' => ['icon' => 'schedule', 'label' => 'Meeting Hours'],
    ],
    'Insights' => [
      'analytics.php' => ['icon' => 'trending_up', 'label' => 'Analytics'],
      'reports.php' => ['icon' => 'summarize', 'label' => 'Reports'],
      'lms-sync.php' => ['icon' => 'sync', 'label' => 'LMS Sync'],
      'settings.php' => ['icon' => 'settings', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'student') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'dashboard', 'label' => 'Dashboard'],
      'schedule.php' => ['icon' => 'calendar_month', 'label' => 'Schedule'],
      'attendance.php' => ['icon' => 'fact_check', 'label' => 'Attendance'],
      'checkin.php' => ['icon' => 'fingerprint', 'label' => 'Check-in'],
      'team-selection.php' => ['icon' => 'groups', 'label' => 'Team Selection'],
    ],
    'Academic' => [
      'class-registration.php' => ['icon' => 'app_registration', 'label' => 'Registration'],
      'assignments.php' => ['icon' => 'assignment', 'label' => 'Assignments'],
      'grades.php' => ['icon' => 'trending_up', 'label' => 'Grades'],
      'events.php' => ['icon' => 'event', 'label' => 'Events'],
      'lms-portal.php' => ['icon' => 'school', 'label' => 'LMS Portal'],
    ],
    'Communication' => [
      '../communication/conversations.php' => ['icon' => 'chat', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      '../notices.php' => ['icon' => 'campaign', 'label' => 'Notices'],
      '../forum/index.php' => ['icon' => 'forum', 'label' => 'Forum'],
      'study-groups.php' => ['icon' => 'groups', 'label' => 'Study Groups'],
    ],
    'Account' => [
      'profile.php' => ['icon' => 'person', 'label' => 'Profile'],
      'id-card.php' => ['icon' => 'badge', 'label' => 'ID Card'],
      'settings.php' => ['icon' => 'settings', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'parent') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'dashboard', 'label' => 'Dashboard'],
      'children.php' => ['icon' => 'child_care', 'label' => 'Children'],
      'link-children.php' => ['icon' => 'link', 'label' => 'Link Children'],
      'attendance.php' => ['icon' => 'fact_check', 'label' => 'Attendance'],
      'team-selection.php' => ['icon' => 'groups', 'label' => 'Team Selection'],
    ],
    'Academic' => [
      'grades.php' => ['icon' => 'bar_chart', 'label' => 'Grades'],
      'fees.php' => ['icon' => 'account_balance_wallet', 'label' => 'Fees'],
      'events.php' => ['icon' => 'event', 'label' => 'Events'],
      'lms-overview.php' => ['icon' => 'school', 'label' => 'LMS Overview'],
    ],
    'Communication' => [
      '../communication/conversations.php' => ['icon' => 'chat', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      '../notices.php' => ['icon' => 'campaign', 'label' => 'Notices'],
      '../forum/index.php' => ['icon' => 'forum', 'label' => 'Forum'],
      'book-meeting.php' => ['icon' => 'event_available', 'label' => 'Book Meeting'],
      'my-meetings.php' => ['icon' => 'calendar_month', 'label' => 'My Meetings'],
    ],
    'Account' => [
      'analytics.php' => ['icon' => 'trending_up', 'label' => 'Analytics'],
      'reports.php' => ['icon' => 'summarize', 'label' => 'Reports'],
      'settings.php' => ['icon' => 'settings', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'librarian') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'dashboard', 'label' => 'Dashboard'],
      'team-selection.php' => ['icon' => 'groups', 'label' => 'Team Selection'],
    ],
    'Catalog' => [
      'books.php' => ['icon' => 'menu_book', 'label' => 'Book Catalog'],
      'add-book.php' => ['icon' => 'add_circle', 'label' => 'Add Book'],
      'categories.php' => ['icon' => 'label', 'label' => 'Categories'],
      'digital-resources.php' => ['icon' => 'cloud_download', 'label' => 'Digital Resources'],
    ],
    'Circulation' => [
      'issue-return.php' => ['icon' => 'swap_horiz', 'label' => 'Issue / Return'],
      'active-loans.php' => ['icon' => 'list_alt', 'label' => 'Active Loans'],
      'overdue.php' => ['icon' => 'warning', 'label' => 'Overdue Books'],
      'fines.php' => ['icon' => 'payments', 'label' => 'Fines'],
      'reservations.php' => ['icon' => 'bookmark', 'label' => 'Reservations'],
    ],
    'Reports' => [
      'reports.php' => ['icon' => 'bar_chart', 'label' => 'Reports'],
      'inventory.php' => ['icon' => 'inventory_2', 'label' => 'Inventory'],
    ],
    'Communication' => [
      '../communication/conversations.php' => ['icon' => 'chat', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      '../notices.php' => ['icon' => 'campaign', 'label' => 'Notices'],
      'settings.php' => ['icon' => 'settings', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'bursar') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'dashboard', 'label' => 'Dashboard'],
      'team-selection.php' => ['icon' => 'groups', 'label' => 'Team Selection'],
    ],
    'Billing' => [
      'fee-collection.php' => ['icon' => 'point_of_sale', 'label' => 'Fee Collection'],
      'invoices.php' => ['icon' => 'receipt_long', 'label' => 'Invoices'],
      'payment-plans.php' => ['icon' => 'calendar_month', 'label' => 'Payment Plans'],
      'receipts.php' => ['icon' => 'receipt', 'label' => 'Receipts'],
    ],
    'Management' => [
      'fee-structure.php' => ['icon' => 'list_alt', 'label' => 'Fee Structure'],
      'defaulters.php' => ['icon' => 'person_off', 'label' => 'Defaulters'],
      'scholarships.php' => ['icon' => 'emoji_events', 'label' => 'Scholarships'],
      'daily-summary.php' => ['icon' => 'pie_chart', 'label' => 'Daily Summary'],
    ],
    'Reports' => [
      'reports.php' => ['icon' => 'bar_chart', 'label' => 'Financial Reports'],
      'export.php' => ['icon' => 'download', 'label' => 'Export Data'],
    ],
    'Communication' => [
      '../communication/conversations.php' => ['icon' => 'chat', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      '../notices.php' => ['icon' => 'campaign', 'label' => 'Notices'],
      'settings.php' => ['icon' => 'settings', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'accountant') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'dashboard', 'label' => 'Dashboard'],
      'team-selection.php' => ['icon' => 'groups', 'label' => 'Team Selection'],
    ],
    'Finance' => [
      'ledger.php' => ['icon' => 'menu_book', 'label' => 'General Ledger'],
      'expenses.php' => ['icon' => 'receipt', 'label' => 'Expenses'],
      'income.php' => ['icon' => 'savings', 'label' => 'Income'],
      'payroll.php' => ['icon' => 'account_balance', 'label' => 'Payroll'],
    ],
    'Statements' => [
      'balance-sheet.php' => ['icon' => 'balance', 'label' => 'Balance Sheet'],
      'profit-loss.php' => ['icon' => 'trending_up', 'label' => 'Profit & Loss'],
      'tax-reports.php' => ['icon' => 'description', 'label' => 'Tax Reports'],
      'budget.php' => ['icon' => 'savings', 'label' => 'Budget'],
    ],
    'Reports' => [
      'reports.php' => ['icon' => 'bar_chart', 'label' => 'Reports'],
      'audit-trail.php' => ['icon' => 'history', 'label' => 'Audit Trail'],
      'project-goals.php' => ['icon' => 'flag', 'label' => 'Project Goals'],
    ],
    'Communication' => [
      '../communication/conversations.php' => ['icon' => 'chat', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      '../notices.php' => ['icon' => 'campaign', 'label' => 'Notices'],
      'settings.php' => ['icon' => 'settings', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'transport') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'dashboard', 'label' => 'Dashboard'],
      'team-selection.php' => ['icon' => 'groups', 'label' => 'Team Selection'],
    ],
    'Fleet' => [
      'routes.php' => ['icon' => 'route', 'label' => 'Routes'],
      'vehicles.php' => ['icon' => 'directions_bus', 'label' => 'Vehicles'],
      'drivers.php' => ['icon' => 'badge', 'label' => 'Drivers'],
    ],
    'Operations' => [
      'student-allocation.php' => ['icon' => 'school', 'label' => 'Student Allocation'],
      'trip-logs.php' => ['icon' => 'list_alt', 'label' => 'Trip Logs'],
      'maintenance.php' => ['icon' => 'build', 'label' => 'Maintenance'],
      'fuel-log.php' => ['icon' => 'local_gas_station', 'label' => 'Fuel Log'],
    ],
    'Reports' => [
      'reports.php' => ['icon' => 'bar_chart', 'label' => 'Reports'],
    ],
    'Communication' => [
      '../communication/conversations.php' => ['icon' => 'chat', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      '../notices.php' => ['icon' => 'campaign', 'label' => 'Notices'],
      'settings.php' => ['icon' => 'settings', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'forum_moderator') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'dashboard', 'label' => 'Dashboard'],
      'team-selection.php' => ['icon' => 'groups', 'label' => 'Team Selection'],
    ],
    'Moderation' => [
      'threads.php' => ['icon' => 'forum', 'label' => 'All Threads'],
      'reported-posts.php' => ['icon' => 'flag', 'label' => 'Reported Posts'],
      'user-warnings.php' => ['icon' => 'report', 'label' => 'User Warnings'],
      'banned-users.php' => ['icon' => 'person_off', 'label' => 'Banned Users'],
    ],
    'Forum' => [
      '../forum/index.php' => ['icon' => 'chat', 'label' => 'Forum Home'],
      '../forum/create-thread.php' => ['icon' => 'add_circle', 'label' => 'Create Thread'],
      'categories.php' => ['icon' => 'folder_open', 'label' => 'Categories'],
      'analytics.php' => ['icon' => 'trending_up', 'label' => 'Forum Analytics'],
    ],
    'Communication' => [
      '../communication/conversations.php' => ['icon' => 'chat', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      '../notices.php' => ['icon' => 'campaign', 'label' => 'Notices'],
      'settings.php' => ['icon' => 'settings', 'label' => 'Settings'],
    ],
  ];
}
?>

<!-- Sidebar — SAMS Academic Sentinel -->
<aside class="sams-sidebar" id="sams-sidebar">
  <!-- Brand -->
  <div class="sidebar-brand">
    <div class="brand-logo">
      <span class="material-symbols-outlined" style="font-size:1.125rem">security</span>
    </div>
    <div>
      <div class="brand-name"><?php echo APP_NAME ?? 'SAMS'; ?></div>
      <div class="brand-subtitle">Academic Sentinel</div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">
    <?php foreach ($nav_sections as $section_name => $items): ?>
      <div class="nav-section-title"><?php echo $section_name; ?></div>
      <?php foreach ($items as $page => $item):
        $href = $page;
        if ($in_subdir && !$starts_with($page, '../') && !$starts_with($page, 'http')) {
          $href = $subdir_prefix . $page;
        }
        $is_active = $current_page === basename($page) || ($in_subdir && $contains($page, $current_dir . '/' . $current_page));
      ?>
        <a href="<?php echo $href; ?>" class="nav-item <?php echo $is_active ? 'active' : ''; ?>">
          <span class="nav-icon material-symbols-outlined"><?php echo $item['icon']; ?></span>
          <span><?php echo $item['label']; ?></span>
          <?php if (!empty($item['badge'])): ?>
            <span class="nav-badge"><?php echo $item['badge']; ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>

    <!-- Logout -->
    <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #e2e8f0;">
      <a href="<?php echo $in_subdir ? '../../logout.php' : '../logout.php'; ?>" class="nav-item">
        <span class="nav-icon material-symbols-outlined">logout</span>
        <span>Logout</span>
      </a>
    </div>
  </nav>

  <!-- User Profile -->
  <div class="sidebar-user">
    <div class="user-avatar">
      <?php if ($user_avatar_url): ?>
        <img src="<?php echo htmlspecialchars($user_avatar_url); ?>" alt="">
      <?php else:
        echo $user_initials;
      endif; ?>
    </div>
    <div>
      <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
      <div class="user-role"><?php echo ucfirst($user_role); ?></div>
    </div>
  </div>
</aside>

<?php
// Include Attendance AI Bot widget on all pages with sidebar
include_once __DIR__ . '/sams-bot.php';

// Include Advanced AI System
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && class_exists('SAMS_AI_Manager')) {
  try {
    $ai_manager = new SAMS_AI_Manager($_SESSION['user_id'], $_SESSION['role']);
    $page_context = [
      'current_page' => basename($_SERVER['PHP_SELF']),
      'user_role' => $_SESSION['role'],
      'timestamp' => time()
    ];
    $aiInterface = $ai_manager->getAIInterface($page_context);
    if (is_array($aiInterface) && isset($aiInterface['chatbot'])) {
      echo $aiInterface['chatbot'];
    }
  } catch (Throwable $e) {
    error_log('Sidebar AI rendering failed: ' . $e->getMessage());
  }
}
?>
