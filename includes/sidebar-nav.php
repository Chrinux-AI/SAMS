<?php

/**
 * Professional Sidebar Navigation Component
 * Clean Modern Design with Role-Based Menus
 */

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Optional advanced AI modules are loaded lazily to avoid sidebar hard-fail.

$current_page = basename($_SERVER['PHP_SELF']);
$user_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? ($_SESSION['user_role'] ?? 'user');
$user_id = $_SESSION['user_id'] ?? 0;
$user_initials = strtoupper(substr($user_name, 0, 2));

// Get unread messages count
$unread_count = 0;
if ($user_id > 0) {
  try {
    $result = db()->fetchOne("
            SELECT COUNT(*) as count FROM message_recipients
            WHERE recipient_id = ? AND is_read = 0 AND deleted_at IS NULL
        ", [$user_id]);
    $unread_count = $result['count'] ?? 0;
  } catch (Exception $e) {
    $unread_count = 0;
  }
}

// Role-specific navigation
$nav_sections = [];

if ($user_role === 'admin') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'tachometer-alt', 'label' => 'Dashboard'],
      'overview.php' => ['icon' => 'chart-pie', 'label' => 'Overview'],
    ],
    'People' => [
      'students.php' => ['icon' => 'user-graduate', 'label' => 'Students'],
      'teachers.php' => ['icon' => 'chalkboard-teacher', 'label' => 'Teachers'],
      'parents.php' => ['icon' => 'users', 'label' => 'Parents'],
    ],
    'Academic' => [
      'classes.php' => ['icon' => 'door-open', 'label' => 'Classes'],
      'attendance.php' => ['icon' => 'check-circle', 'label' => 'Attendance'],
      'events.php' => ['icon' => 'calendar-alt', 'label' => 'Events'],
      'fee-management.php' => ['icon' => 'money-bill-wave', 'label' => 'Fees'],
      'class-enrollment.php' => ['icon' => 'user-graduate', 'label' => 'Enrollment'],
    ],
    'Communication' => [
      '../messages.php' => ['icon' => 'envelope', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      '../notices.php' => ['icon' => 'bullhorn', 'label' => 'Notices'],
      'notices.php' => ['icon' => 'edit', 'label' => 'Manage Notices'],
      'emergency-alerts.php' => ['icon' => 'exclamation-triangle', 'label' => 'Alerts'],
    ],
    'Analytics' => [
      'reports.php' => ['icon' => 'chart-line', 'label' => 'Reports'],
      'analytics.php' => ['icon' => 'chart-bar', 'label' => 'Analytics'],
      'activity-monitor.php' => ['icon' => 'desktop', 'label' => 'Activity Monitor'],
    ],
    'System' => [
      'users.php' => ['icon' => 'users-cog', 'label' => 'Users'],
      'registrations.php' => ['icon' => 'user-plus', 'label' => 'Registrations'],
      'approve-users.php' => ['icon' => 'user-check', 'label' => 'Approvals'],
      'manage-ids.php' => ['icon' => 'id-card', 'label' => 'Manage IDs'],
      'system-health.php' => ['icon' => 'heartbeat', 'label' => 'System Health'],
      'audit-logs.php' => ['icon' => 'clipboard-list', 'label' => 'Audit Logs'],
      'backup-export.php' => ['icon' => 'database', 'label' => 'Backup & Export'],
      'lms-settings.php' => ['icon' => 'graduation-cap', 'label' => 'LMS Settings'],
      'settings.php' => ['icon' => 'cog', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'teacher') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'tachometer-alt', 'label' => 'Dashboard'],
      'my-classes.php' => ['icon' => 'door-open', 'label' => 'My Classes'],
      'students.php' => ['icon' => 'user-graduate', 'label' => 'Students'],
      'attendance.php' => ['icon' => 'clipboard-check', 'label' => 'Attendance'],
    ],
    'Academic' => [
      'materials.php' => ['icon' => 'file-upload', 'label' => 'Materials'],
      'assignments.php' => ['icon' => 'tasks', 'label' => 'Assignments'],
      'grades.php' => ['icon' => 'graduation-cap', 'label' => 'Grades'],
      'class-enrollment.php' => ['icon' => 'user-graduate', 'label' => 'Enrollment'],
    ],
    'Communication' => [
      '../messages.php' => ['icon' => 'envelope', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      'parent-comms.php' => ['icon' => 'users', 'label' => 'Parent Comms'],
      '../notices.php' => ['icon' => 'bullhorn', 'label' => 'Notices'],
      'resources.php' => ['icon' => 'book', 'label' => 'Resources'],
      'behavior-logs.php' => ['icon' => 'clipboard-list', 'label' => 'Behavior Logs'],
      'meeting-hours.php' => ['icon' => 'calendar-alt', 'label' => 'Meeting Hours'],
    ],
    'Insights' => [
      'analytics.php' => ['icon' => 'chart-line', 'label' => 'Analytics'],
      'reports.php' => ['icon' => 'file-alt', 'label' => 'Reports'],
      'lms-sync.php' => ['icon' => 'sync-alt', 'label' => 'LMS Sync'],
      'settings.php' => ['icon' => 'cog', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'student') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'tachometer-alt', 'label' => 'Dashboard'],
      'schedule.php' => ['icon' => 'calendar-alt', 'label' => 'Schedule'],
      'attendance.php' => ['icon' => 'clipboard-list', 'label' => 'Attendance'],
      'checkin.php' => ['icon' => 'fingerprint', 'label' => 'Check-in'],
    ],
    'Academic' => [
      'class-registration.php' => ['icon' => 'user-plus', 'label' => 'Registration'],
      'assignments.php' => ['icon' => 'tasks', 'label' => 'Assignments'],
      'grades.php' => ['icon' => 'chart-line', 'label' => 'Grades'],
      'events.php' => ['icon' => 'calendar-check', 'label' => 'Events'],
      'lms-portal.php' => ['icon' => 'graduation-cap', 'label' => 'LMS Portal'],
    ],
    'Communication' => [
      '../messages.php' => ['icon' => 'envelope', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      'communication.php' => ['icon' => 'comment-dots', 'label' => 'Student Chat'],
      '../notices.php' => ['icon' => 'bullhorn', 'label' => 'Notices'],
      'study-groups.php' => ['icon' => 'users', 'label' => 'Study Groups'],
    ],
    'Account' => [
      'profile.php' => ['icon' => 'user', 'label' => 'Profile'],
      'id-card.php' => ['icon' => 'id-card', 'label' => 'ID Card'],
      'settings.php' => ['icon' => 'cog', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'parent') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'home', 'label' => 'Dashboard'],
      'children.php' => ['icon' => 'child', 'label' => 'Children'],
      'link-children.php' => ['icon' => 'link', 'label' => 'Link Children'],
      'attendance.php' => ['icon' => 'clipboard-list', 'label' => 'Attendance'],
    ],
    'Academic' => [
      'grades.php' => ['icon' => 'chart-bar', 'label' => 'Grades'],
      'fees.php' => ['icon' => 'wallet', 'label' => 'Fees'],
      'events.php' => ['icon' => 'calendar-alt', 'label' => 'Events'],
      'lms-overview.php' => ['icon' => 'graduation-cap', 'label' => 'LMS Overview'],
    ],
    'Communication' => [
      '../messages.php' => ['icon' => 'envelope', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      'communication.php' => ['icon' => 'user-tie', 'label' => 'Contact Teachers'],
      '../notices.php' => ['icon' => 'bullhorn', 'label' => 'Notices'],
      'book-meeting.php' => ['icon' => 'calendar-plus', 'label' => 'Book Meeting'],
      'my-meetings.php' => ['icon' => 'calendar-check', 'label' => 'My Meetings'],
    ],
    'Account' => [
      'analytics.php' => ['icon' => 'chart-line', 'label' => 'Analytics'],
      'reports.php' => ['icon' => 'file-alt', 'label' => 'Reports'],
      'settings.php' => ['icon' => 'cog', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'librarian') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'tachometer-alt', 'label' => 'Dashboard'],
    ],
    'Catalog' => [
      'books.php' => ['icon' => 'book', 'label' => 'Book Catalog'],
      'add-book.php' => ['icon' => 'plus-circle', 'label' => 'Add Book'],
      'categories.php' => ['icon' => 'tags', 'label' => 'Categories'],
      'digital-resources.php' => ['icon' => 'cloud-download-alt', 'label' => 'Digital Resources'],
    ],
    'Circulation' => [
      'issue-return.php' => ['icon' => 'exchange-alt', 'label' => 'Issue / Return'],
      'active-loans.php' => ['icon' => 'clipboard-list', 'label' => 'Active Loans'],
      'overdue.php' => ['icon' => 'exclamation-triangle', 'label' => 'Overdue Books'],
      'fines.php' => ['icon' => 'money-bill-wave', 'label' => 'Fines'],
      'reservations.php' => ['icon' => 'bookmark', 'label' => 'Reservations'],
    ],
    'Reports' => [
      'reports.php' => ['icon' => 'chart-bar', 'label' => 'Reports'],
      'inventory.php' => ['icon' => 'warehouse', 'label' => 'Inventory'],
    ],
    'Communication' => [
      '../messages.php' => ['icon' => 'envelope', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      '../notices.php' => ['icon' => 'bullhorn', 'label' => 'Notices'],
      '../general/settings.php' => ['icon' => 'cog', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'bursar') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'tachometer-alt', 'label' => 'Dashboard'],
    ],
    'Billing' => [
      'fee-collection.php' => ['icon' => 'cash-register', 'label' => 'Fee Collection'],
      'invoices.php' => ['icon' => 'file-invoice-dollar', 'label' => 'Invoices'],
      'payment-plans.php' => ['icon' => 'calendar-alt', 'label' => 'Payment Plans'],
      'receipts.php' => ['icon' => 'receipt', 'label' => 'Receipts'],
    ],
    'Management' => [
      'fee-structure.php' => ['icon' => 'list-alt', 'label' => 'Fee Structure'],
      'defaulters.php' => ['icon' => 'user-times', 'label' => 'Defaulters'],
      'scholarships.php' => ['icon' => 'award', 'label' => 'Scholarships'],
      'daily-summary.php' => ['icon' => 'chart-pie', 'label' => 'Daily Summary'],
    ],
    'Reports' => [
      'reports.php' => ['icon' => 'chart-bar', 'label' => 'Financial Reports'],
      'export.php' => ['icon' => 'file-export', 'label' => 'Export Data'],
    ],
    'Communication' => [
      '../messages.php' => ['icon' => 'envelope', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      '../notices.php' => ['icon' => 'bullhorn', 'label' => 'Notices'],
      '../general/settings.php' => ['icon' => 'cog', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'accountant') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'tachometer-alt', 'label' => 'Dashboard'],
    ],
    'Finance' => [
      'ledger.php' => ['icon' => 'book', 'label' => 'General Ledger'],
      'expenses.php' => ['icon' => 'receipt', 'label' => 'Expenses'],
      'income.php' => ['icon' => 'coins', 'label' => 'Income'],
      'payroll.php' => ['icon' => 'money-check-alt', 'label' => 'Payroll'],
    ],
    'Statements' => [
      'balance-sheet.php' => ['icon' => 'balance-scale', 'label' => 'Balance Sheet'],
      'profit-loss.php' => ['icon' => 'chart-line', 'label' => 'Profit & Loss'],
      'tax-reports.php' => ['icon' => 'file-alt', 'label' => 'Tax Reports'],
      'budget.php' => ['icon' => 'piggy-bank', 'label' => 'Budget'],
    ],
    'Reports' => [
      'reports.php' => ['icon' => 'chart-bar', 'label' => 'Reports'],
      'audit-trail.php' => ['icon' => 'clipboard-list', 'label' => 'Audit Trail'],
    ],
    'Communication' => [
      '../messages.php' => ['icon' => 'envelope', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      '../notices.php' => ['icon' => 'bullhorn', 'label' => 'Notices'],
      '../general/settings.php' => ['icon' => 'cog', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'transport') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'tachometer-alt', 'label' => 'Dashboard'],
    ],
    'Fleet' => [
      'routes.php' => ['icon' => 'route', 'label' => 'Routes'],
      'vehicles.php' => ['icon' => 'bus', 'label' => 'Vehicles'],
      'drivers.php' => ['icon' => 'id-card', 'label' => 'Drivers'],
    ],
    'Operations' => [
      'student-allocation.php' => ['icon' => 'user-graduate', 'label' => 'Student Allocation'],
      'trip-logs.php' => ['icon' => 'clipboard-list', 'label' => 'Trip Logs'],
      'maintenance.php' => ['icon' => 'wrench', 'label' => 'Maintenance'],
      'fuel-log.php' => ['icon' => 'gas-pump', 'label' => 'Fuel Log'],
    ],
    'Reports' => [
      'reports.php' => ['icon' => 'chart-bar', 'label' => 'Reports'],
    ],
    'Communication' => [
      '../messages.php' => ['icon' => 'envelope', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      '../notices.php' => ['icon' => 'bullhorn', 'label' => 'Notices'],
      '../general/settings.php' => ['icon' => 'cog', 'label' => 'Settings'],
    ],
  ];
} elseif ($user_role === 'forum_moderator') {
  $nav_sections = [
    'Main' => [
      'dashboard.php' => ['icon' => 'tachometer-alt', 'label' => 'Dashboard'],
    ],
    'Moderation' => [
      'threads.php' => ['icon' => 'comments', 'label' => 'All Threads'],
      'reported-posts.php' => ['icon' => 'flag', 'label' => 'Reported Posts'],
      'user-warnings.php' => ['icon' => 'exclamation-circle', 'label' => 'User Warnings'],
      'banned-users.php' => ['icon' => 'user-slash', 'label' => 'Banned Users'],
    ],
    'Forum' => [
      '../forum/index.php' => ['icon' => 'comment-dots', 'label' => 'Forum Home'],
      '../forum/create-thread.php' => ['icon' => 'plus-circle', 'label' => 'Create Thread'],
      'categories.php' => ['icon' => 'folder-open', 'label' => 'Categories'],
      'analytics.php' => ['icon' => 'chart-line', 'label' => 'Forum Analytics'],
    ],
    'Communication' => [
      '../messages.php' => ['icon' => 'envelope', 'label' => 'Messages', 'badge' => $unread_count > 0 ? $unread_count : null],
      '../notices.php' => ['icon' => 'bullhorn', 'label' => 'Notices'],
      '../general/settings.php' => ['icon' => 'cog', 'label' => 'Settings'],
    ],
  ];
}
?>

<!-- Mobile Menu Toggle -->
<button class="mobile-menu-toggle" onclick="window.toggleMobileSidebar()">
  <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">
      <i class="fas fa-graduation-cap"></i>
    </div>
    <div>
      <div class="brand-name"><?php echo APP_NAME ?? 'SAMS'; ?></div>
      <div class="brand-subtitle">Attendance System</div>
    </div>
    <button type="button" class="sidebar-collapse-btn" onclick="window.toggleSidebarCollapse()" title="Collapse sidebar">
      <i class="fas fa-angles-left"></i>
    </button>
  </div>

  <nav class="sidebar-menu">
    <?php foreach ($nav_sections as $section_name => $items): ?>
      <div class="menu-section">
        <div class="menu-section-title"><?php echo $section_name; ?></div>
        <?php foreach ($items as $page => $item): ?>
          <a href="<?php echo $page; ?>" class="menu-item <?php echo $current_page === basename($page) ? 'active' : ''; ?>">
            <span class="menu-icon"><i class="fas fa-<?php echo $item['icon']; ?>"></i></span>
            <span class="menu-label"><?php echo $item['label']; ?></span>
            <?php if (!empty($item['badge'])): ?>
              <span class="menu-badge"><?php echo $item['badge']; ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <div class="menu-section" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.08);">
      <a href="../logout.php" class="menu-item">
        <span class="menu-icon"><i class="fas fa-sign-out-alt"></i></span>
        <span class="menu-label">Logout</span>
      </a>
    </div>
  </nav>

  <div class="sidebar-user">
    <div class="user-avatar"><?php echo $user_initials; ?></div>
    <div class="user-info">
      <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
      <div class="user-role"><?php echo ucfirst($user_role); ?></div>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" onclick="window.closeMobileSidebar()"></div>

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
