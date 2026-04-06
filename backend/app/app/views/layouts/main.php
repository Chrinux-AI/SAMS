<?php
/**
 * SAMS Main Layout with Theme Support
 * Centralized theme management integrated
 */

// Include theme service
require_once __DIR__ . '/../services/ThemeService.php';

// Initialize theme service
$themeService = new ThemeService();

// Get user theme preference
$userId = $_SESSION['user_id'] ?? null;
$themeData = $themeService->getThemeData($userId);

// Apply theme to page
$themeApplication = $themeService->applyThemeToPage($userId);

// Set user ID for JavaScript
$jsUserId = $userId ? $userId : 'null';
?><!DOCTYPE html>
<html lang="en" class="<?php echo $themeApplication['body_class']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'SAMS - School Attendance Management System'; ?></title>

    <!-- Favicon and Mobile -->
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/favicon.ico">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>/apple-touch-icon.png">
    <meta name="theme-color" content="#4F46E5">

    <!-- CSS Framework -->
    <link href="<?php echo BASE_URL; ?>/public/assets/css/ui-upgrade-new.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Theme CSS -->
    <?php echo $themeApplication['custom_css']; ?>

    <!-- Custom Styles -->
    <style>
        /* Theme transitions */
        .theme-transitioning {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        /* Additional styles for specific components */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1020;
            display: none;
        }

        .sidebar-overlay.active {
            display: block;
        }

        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            color: var(--text-primary);
        }

        @media (max-width: 640px) {
            .sidebar-toggle {
                display: block;
            }
        }

        .content-header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-primary);
            padding: var(--spacing-6) 0;
            margin-bottom: var(--spacing-6);
        }

        .content-header h1 {
            font-size: var(--font-size-2xl);
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: var(--spacing-2);
        }

        .content-header p {
            color: var(--text-secondary);
            margin: 0;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            margin-bottom: var(--spacing-4);
        }

        .breadcrumb a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition-base);
        }

        .breadcrumb a:hover {
            color: var(--primary);
        }

        .breadcrumb .separator {
            color: var(--text-muted);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-6);
            margin-bottom: var(--spacing-8);
        }

        .action-buttons {
            display: flex;
            gap: var(--spacing-3);
            margin-bottom: var(--spacing-6);
        }

        .table-container {
            background: var(--bg-primary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: var(--spacing-6);
        }

        .table-header {
            padding: var(--spacing-6);
            border-bottom: 1px solid var(--border-primary);
            background: var(--bg-tertiary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-search {
            display: flex;
            gap: var(--spacing-3);
            align-items: center;
        }

        .table-search input {
            min-width: 200px;
        }

        .table-pagination {
            padding: var(--spacing-4) var(--spacing-6);
            border-top: 1px solid var(--border-primary);
            background: var(--bg-tertiary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pagination-buttons {
            display: flex;
            gap: var(--spacing-2);
        }

        .pagination-button {
            padding: var(--spacing-2) var(--spacing-3);
            border: 1px solid var(--border-primary);
            background: var(--bg-primary);
            color: var(--text-primary);
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition-base);
        }

        .pagination-button:hover {
            background: var(--bg-tertiary);
        }

        .pagination-button.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .loading {
            text-align: center;
            padding: var(--spacing-8);
            color: var(--text-secondary);
        }

        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid var(--border-primary);
            border-radius: var(--radius-full);
            border-top-color: var(--primary);
            animation: spin 1s linear infinite;
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-4);
        }

        .form-group {
            margin-bottom: var(--spacing-4);
        }

        .form-actions {
            display: flex;
            gap: var(--spacing-3);
            justify-content: flex-end;
            margin-top: var(--spacing-6);
        }

        .alert-dismissible {
            position: relative;
        }

        .alert-close {
            position: absolute;
            top: var(--spacing-3);
            right: var(--spacing-3);
            background: none;
            border: none;
            font-size: var(--font-size-lg);
            cursor: pointer;
            color: inherit;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: var(--spacing-4);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            max-width: 300px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
            display: none;
        }

        .modal.show {
            display: block;
        }

        .modal-dialog {
            position: relative;
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            max-width: 500px;
            margin: 5% auto;
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-header {
            padding: var(--spacing-6);
            border-bottom: 1px solid var(--border-primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: var(--spacing-6);
        }

        .modal-footer {
            padding: var(--spacing-6);
            border-top: 1px solid var(--border-primary);
            display: flex;
            justify-content: flex-end;
            gap: var(--spacing-3);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: var(--font-size-xl);
            cursor: pointer;
            color: var(--text-secondary);
        }

        .tooltip {
            position: absolute;
            background: #333;
            color: #fff;
            padding: 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            z-index: 1000;
            white-space: nowrap;
        }

        .dropdown-menu {
            position: absolute;
            background: var(--bg-primary);
            border: 1px solid var(--border-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            min-width: 200px;
            display: none;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-item {
            display: block;
            padding: var(--spacing-3) var(--spacing-4);
            color: var(--text-primary);
            text-decoration: none;
            transition: var(--transition-base);
        }

        .dropdown-item:hover {
            background: var(--bg-tertiary);
        }

        .dropdown-divider {
            height: 1px;
            background: var(--border-primary);
            margin: var(--spacing-2) 0;
        }

        .dark-theme {
            --bg-primary: #111827;
            --bg-secondary: #1a202A2;
            --bg-tertiary: #374151;
            --text-primary: #E2E8F0;
            --text-secondary: #A0AEC0;
            --border-primary: #4B5563;
            --shadow: rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2);
        }

        .theme-toggle {
            background: none;
            border: none;
            padding: var(--spacing-2);
            cursor: pointer;
            color: var(--text-secondary);
            border-radius: var(--radius);
            transition: var(--transition-base);
        }

        .theme-toggle:hover {
            background: var(--bg-tertiary);
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <button class="sidebar-toggle" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <a href="<?php echo BASE_URL; ?>/index.php" class="navbar-brand">
                        <i class="fas fa-graduation-cap"></i> SAMS
                    </a>
                </div>

                <div class="d-flex align-items-center">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a href="#" class="nav-link" data-tooltip="Dashboard">
                                <i class="fas fa-tachometer-alt"></i>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" data-tooltip="Notifications">
                                <i class="fas fa-bell"></i>
                                <span class="badge badge-danger">3</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" data-tooltip="Messages">
                                <i class="fas fa-envelope"></i>
                                <span class="badge badge-warning">5</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#" class="nav-link" data-tooltip="Settings">
                                <i class="fas fa-cog"></i>
                            </a>
                        </li>
                        <li class="nav-item">
                            <button class="theme-toggle" data-tooltip="Toggle theme">
                                <i class="fas fa-moon"></i>
                            </button>
                        </li>
                        <li class="nav-item dropdown">
                            <a href="#" class="nav-link dropdown-toggle" data-tooltip="User menu">
                                <i class="fas fa-user"></i>
                                <span class="ms-2"><?php echo $_SESSION['full_name'] ?? 'User'; ?></span>
                            </a>
                            <div class="dropdown-menu">
                                <a href="#" class="dropdown-item">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                                <a href="#" class="dropdown-item">
                                    <i class="fas fa-cog"></i> Settings
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="<?php echo BASE_URL; ?>/logout.php" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-sticky">
            <div class="p-4">
                <h5 class="text-white mb-4">Navigation</h5>
                <ul class="sidebar-nav">
                    <?php
                    // Generate sidebar based on user role
                    $user_role = $_SESSION['role'] ?? 'student';

                    $sidebar_items = [
                        'admin' => [
                            ['icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'href' => 'dashboard.php'],
                            ['icon' => 'fas fa-users', 'text' => 'Users', 'href' => 'users.php'],
                            ['icon' => 'fas fa-door-open', 'text' => 'Classes', 'href' => 'classes.php'],
                            ['icon' => 'fas fa-check-circle', 'text' => 'Attendance', 'href' => 'attendance.php'],
                            ['icon' => 'fas fa-chart-line', 'text' => 'Reports', 'href' => 'reports.php'],
                            ['icon' => 'fas fa-brain', 'text' => 'AI Center', 'href' => 'ai-center/index.php'],
                            ['icon' => 'fas fa-cog', 'text' => 'Settings', 'href' => 'settings.php']
                        ],
                        'teacher' => [
                            ['icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'href' => 'dashboard.php'],
                            ['icon' => 'fas fa-door-open', 'text' => 'My Classes', 'href' => 'classes.php'],
                            ['icon' => 'fas fa-check-circle', 'text' => 'Attendance', 'href' => 'attendance.php'],
                            ['icon' => 'fas fa-graduation-cap', 'text' => 'Grades', 'href' => 'grades.php'],
                            ['icon' => 'fas fa-book', 'text' => 'Assignments', 'href' => 'assignments.php'],
                            ['icon' => 'fas fa-chart-line', 'text' => 'Reports', 'href' => 'reports.php']
                        ],
                        'student' => [
                            ['icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'href' => 'dashboard.php'],
                            ['icon' => 'fas fa-check-circle', 'text' => 'Attendance', 'href' => 'attendance.php'],
                            ['icon' => 'fas fa-graduation-cap', 'text' => 'Grades', 'href' => 'grades.php'],
                            ['icon' => 'fas fa-book', 'text' => 'Assignments', 'href' => 'assignments.php'],
                            ['icon' => 'fas fa-calendar', 'text' => 'Schedule', 'href' => 'schedule.php'],
                            ['icon' => 'fas fa-chart-line', 'text' => 'Reports', 'href' => 'reports.php']
                        ],
                        'parent' => [
                            ['icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'href' => 'dashboard.php'],
                            ['icon' => 'fas fa-check-circle', 'text' => 'Child Attendance', 'href' => 'attendance.php'],
                            ['icon' => 'fas fa-graduation-cap', 'text' => 'Child Grades', 'href' => 'grades.php'],
                            ['icon' => 'fas fa-book', 'text' => 'Assignments', 'href' => 'assignments.php'],
                            ['icon' => 'fas fa-chart-line', 'text' => 'Reports', 'href' => 'reports.php']
                        ]
                    ];

                    $items = $sidebar_items[$user_role] ?? $sidebar_items['student'];

                    foreach ($items as $item): ?>
                        <li class="nav-item">
                            <a href="<?php echo $item['href']; ?>" class="nav-link">
                                <i class="<?php echo $item['icon']; ?> me-2"></i>
                                <?php echo $item['text']; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </aside>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Content Header -->
        <?php if (isset($page_title) || isset($page_subtitle)): ?>
        <div class="content-header">
            <div class="container-fluid">
                <?php if (isset($page_title)): ?>
                    <h1><?php echo $page_title; ?></h1>
                <?php endif; ?>
                <?php if (isset($page_subtitle)): ?>
                    <p><?php echo $page_subtitle; ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Breadcrumb -->
        <?php if (isset($breadcrumbs) && !empty($breadcrumbs)): ?>
        <div class="container-fluid">
            <nav class="breadcrumb">
                <?php foreach ($breadcrumbs as $index => $crumb): ?>
                    <?php if ($index > 0): ?>
                        <span class="separator">/</span>
                    <?php endif; ?>
                    <?php if (isset($crumb['href'])): ?>
                        <a href="<?php echo $crumb['href']; ?>"><?php echo $crumb['text']; ?></a>
                    <?php else: ?>
                        <span><?php echo $crumb['text']; ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        </div>
        <?php endif; ?>

        <!-- Page Content -->
        <div class="container-fluid">
            <!-- Flash Messages -->
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible fade show">
                    <?php echo $_SESSION['flash_message']; ?>
                    <button type="button" class="alert-close" data-dismiss="alert">&times;</button>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>

            <!-- Main Content Area -->
            <?php echo $content ?? ''; ?>
        </div>
    </main>

    <!-- JavaScript -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/ui-enhancements.js"></script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/theme-manager.js"></script>
    <script>
        // Set user ID for theme manager
        window.SAMS_USER_ID = <?php echo $jsUserId; ?>;

        // Theme data for JavaScript
        window.SAMS_THEME_DATA = <?php echo json_encode($themeData); ?>;

        // Initialize SAMS UI
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide notifications
            const notifications = document.querySelectorAll('.alert');
            notifications.forEach(function(notification) {
                setTimeout(function() {
                    notification.style.transition = 'opacity 0.3s';
                    notification.style.opacity = '0';
                    setTimeout(function() {
                        notification.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
    <?php echo $themeApplication['theme_script']; ?>
</body>
</html>
