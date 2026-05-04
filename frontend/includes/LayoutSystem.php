<?php

/**
 * SAMS Global Layout System
 * Unified layout templates for consistent UI across all roles
 */

class SAMS_LayoutSystem
{
    private static $instance = null;
    private $role;
    private $user;
    private $tenant;
    private $pageTitle;
    private $pageContext;

    private function __construct()
    {
        $this->role = $_SESSION['role'] ?? 'guest';
        $this->user = [
            'id' => $_SESSION['user_id'] ?? null,
            'name' => $_SESSION['user_name'] ?? 'Guest',
            'email' => $_SESSION['email'] ?? '',
            'role' => $this->role
        ];
        $this->tenant = [
            'id' => $_SESSION['tenant_id'] ?? 1,
            'name' => $_SESSION['tenant_name'] ?? 'Default School'
        ];
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Render complete page with all components
     */
    public function renderPage($content, $options = [])
    {
        $this->pageTitle = $options['title'] ?? 'SAMS';
        $this->pageContext = $options['context'] ?? [];

        $html = $this->renderHeader();
        $html .= $this->renderBody($content);
        $html .= $this->renderFooter();

        return $html;
    }

    /**
     * Render HTML head section
     */
    private function renderHeader()
    {
        $themeColor = '#4F46E5';
        $version = defined('SAMS_VERSION') ? SAMS_VERSION : '2.0.0';

        return "<!DOCTYPE html>
<html lang='en' data-theme='light'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta name='theme-color' content='$themeColor'>
    <title>" . htmlspecialchars($this->pageTitle) . " - SAMS</title>

    <!-- Favicon -->
    <link rel='icon' type='image/png' href='/attendance/assets/images/icons/logo3.png'>
    <link rel='alternate icon' href='/assets/logo/favicon.ico'>
    <link rel='apple-touch-icon' href='/assets/logo/apple-touch-icon.png'>

    <!-- PWA -->
    <link rel='manifest' href='/manifest.json'>

    <!-- Global CSS -->
    <link rel='stylesheet' href='/assets/theme/sams-global.css?v=$version'>
    <link rel='stylesheet' href='/assets/theme/sams-layout.css?v=$version'>
    <link rel='stylesheet' href='/assets/theme/sams-components.css?v=$version'>

    <!-- Role-specific CSS -->
    <link rel='stylesheet' href='/assets/theme/role-{$this->role}.css?v=$version'>

    <!-- Page-specific CSS -->
    " . $this->getPageCSS() . "
    <script src='/attendance/frontend/assets/js/session-monitor.js'></script>
</head>";
    }

    /**
     * Render body with sidebar and content
     */
    private function renderBody($content)
    {
        $sidebar = $this->renderSidebar();
        $topbar = $this->renderTopbar();

        return "
<body class='sams-layout role-{$this->role}'>
    <div class='sams-wrapper'>
        $sidebar

        <main class='sams-main'>
            $topbar

            <div class='sams-content'>
                " . $this->renderBreadcrumbs() . "
                " . $this->renderPageHeader() . "

                <div class='content-area'>
                    $content
                </div>
            </div>

            " . $this->renderFooterBar() . "
        </main>
    </div>

    " . $this->renderScripts() . "
</body>";
    }

    /**
     * Render sidebar navigation
     */
    private function renderSidebar()
    {
        $menuItems = $this->getMenuItems();
        $tenantName = htmlspecialchars($this->tenant['name']);
        $userName = htmlspecialchars($this->user['name']);
        $userRole = ucfirst(str_replace('_', ' ', $this->role));

        $menuHtml = '';
        foreach ($menuItems as $section => $items) {
            $menuHtml .= "<div class='menu-section'>
                <div class='section-title'>" . htmlspecialchars($section) . "</div>
                <ul class='menu-items'>";

            foreach ($items as $item) {
                $active = $this->isActivePage($item['url']) ? 'active' : '';
                $icon = $item['icon'] ?? 'circle';
                $url = htmlspecialchars($item['url']);
                $label = htmlspecialchars($item['label']);

                $menuHtml .= "
                    <li class='menu-item $active'>
                        <a href='$url' class='menu-link'>
                            <span class='icon icon-$icon'></span>
                            <span class='label'>$label</span>
                        </a>
                    </li>";
            }

            $menuHtml .= "</ul></div>";
        }

        return "
<aside class='sams-sidebar' id='sidebar'>
    <div class='sidebar-header'>
        <a href='/dashboard.php' class='logo'>
            <img src='/assets/logo/logo-icon.svg' alt='SAMS' class='logo-icon'>
            <span class='logo-text'>SAMS</span>
        </a>
        <div class='tenant-name'>$tenantName</div>
    </div>

    <nav class='sidebar-nav'>
        $menuHtml
    </nav>

    <div class='sidebar-footer'>
        <div class='user-info'>
            <div class='user-name'>$userName</div>
            <div class='user-role'>$userRole</div>
        </div>
        <a href='" . rtrim(APP_URL, '/') . "/logout.php' class='logout-link'>
            <span class='icon icon-logout'></span>
            <span>Logout</span>
        </a>
    </div>
</aside>";
    }

    /**
     * Get menu items based on role
     */
    private function getMenuItems()
    {
        $menus = [
            'admin' => [
                'Main' => [
                    ['url' => 'admin/index.php', 'label' => 'Dashboard', 'icon' => 'dashboard'],
                    ['url' => 'admin/analytics.php', 'label' => 'Analytics', 'icon' => 'chart']
                ],
                'Management' => [
                    ['url' => 'admin/teachers.php', 'label' => 'Teachers', 'icon' => 'teacher'],
                    ['url' => 'admin/students.php', 'label' => 'Students', 'icon' => 'student'],
                    ['url' => 'admin/classes.php', 'label' => 'Classes', 'icon' => 'class'],
                    ['url' => 'admin/parents.php', 'label' => 'Parents', 'icon' => 'parent']
                ],
                'Operations' => [
                    ['url' => 'admin/attendance.php', 'label' => 'Attendance', 'icon' => 'check'],
                    ['url' => 'admin/reports.php', 'label' => 'Reports', 'icon' => 'report'],
                    ['url' => 'admin/messages.php', 'label' => 'Messages', 'icon' => 'message']
                ],
                'System' => [
                    ['url' => 'admin/settings.php', 'label' => 'Settings', 'icon' => 'settings'],
                    ['url' => 'admin/ai-management.php', 'label' => 'AI System', 'icon' => 'ai'],
                    ['url' => 'admin/tenants.php', 'label' => 'Tenants', 'icon' => 'building']
                ]
            ],
            'teacher' => [
                'Main' => [
                    ['url' => 'teacher/index.php', 'label' => 'Dashboard', 'icon' => 'dashboard'],
                    ['url' => 'teacher/classes.php', 'label' => 'My Classes', 'icon' => 'class'],
                    ['url' => 'teacher/attendance.php', 'label' => 'Attendance', 'icon' => 'check']
                ],
                'Students' => [
                    ['url' => 'teacher/students.php', 'label' => 'My Students', 'icon' => 'student'],
                    ['url' => 'teacher/grades.php', 'label' => 'Grades', 'icon' => 'grade'],
                    ['url' => 'teacher/behavior.php', 'label' => 'Behavior', 'icon' => 'behavior']
                ],
                'Communication' => [
                    ['url' => 'teacher/messages.php', 'label' => 'Messages', 'icon' => 'message'],
                    ['url' => 'teacher/announcements.php', 'label' => 'Announcements', 'icon' => 'announce']
                ]
            ],
            'student' => [
                'Main' => [
                    ['url' => 'student/index.php', 'label' => 'Dashboard', 'icon' => 'dashboard'],
                    ['url' => 'student/classes.php', 'label' => 'My Classes', 'icon' => 'class'],
                    ['url' => 'student/attendance.php', 'label' => 'Attendance', 'icon' => 'check']
                ],
                'Academic' => [
                    ['url' => 'student/grades.php', 'label' => 'Grades', 'icon' => 'grade'],
                    ['url' => 'student/assignments.php', 'label' => 'Assignments', 'icon' => 'assignment'],
                    ['url' => 'student/timetable.php', 'label' => 'Timetable', 'icon' => 'calendar']
                ],
                'Resources' => [
                    ['url' => 'student/materials.php', 'label' => 'Materials', 'icon' => 'book'],
                    ['url' => 'student/messages.php', 'label' => 'Messages', 'icon' => 'message']
                ]
            ],
            'parent' => [
                'Main' => [
                    ['url' => 'parent/index.php', 'label' => 'Dashboard', 'icon' => 'dashboard'],
                    ['url' => 'parent/children.php', 'label' => 'My Children', 'icon' => 'children']
                ],
                'Academic' => [
                    ['url' => 'parent/grades.php', 'label' => 'Grades', 'icon' => 'grade'],
                    ['url' => 'parent/attendance.php', 'label' => 'Attendance', 'icon' => 'check'],
                    ['url' => 'parent/timetable.php', 'label' => 'Timetable', 'icon' => 'calendar']
                ],
                'Communication' => [
                    ['url' => 'parent/messages.php', 'label' => 'Messages', 'icon' => 'message'],
                    ['url' => 'parent/teachers.php', 'label' => 'Teachers', 'icon' => 'teacher']
                ]
            ]
        ];

        return $menus[$this->role] ?? $menus['admin'];
    }

    /**
     * Render top navigation bar
     */
    private function renderTopbar()
    {
        $pageTitle = htmlspecialchars($this->pageTitle);
        $notificationCount = $this->getNotificationCount();

        return "
<header class='sams-topbar'>
    <div class='topbar-left'>
        <button class='sidebar-toggle' id='sidebarToggle' aria-label='Toggle sidebar'>
            <span class='icon icon-menu'></span>
        </button>
        <h1 class='page-title'>$pageTitle</h1>
    </div>

    <div class='topbar-right'>
        <button class='icon-btn search-btn' aria-label='Search'>
            <span class='icon icon-search'></span>
        </button>

        <button class='icon-btn notification-btn' aria-label='Notifications' data-count='$notificationCount'>
            <span class='icon icon-bell'></span>
        </button>

        <button class='icon-btn chatbot-btn' id='chatbotToggle' aria-label='Help'>
            <span class='icon icon-help'></span>
        </button>
    </div>
</header>";
    }

    /**
     * Render breadcrumbs
     */
    private function renderBreadcrumbs()
    {
        $crumbs = $this->pageContext['breadcrumbs'] ?? [];

        if (empty($crumbs)) {
            return '';
        }

        $html = "<nav class='breadcrumbs' aria-label='Breadcrumb'><ol>";

        foreach ($crumbs as $index => $crumb) {
            $isLast = $index === count($crumbs) - 1;
            $label = htmlspecialchars($crumb['label']);

            if ($isLast) {
                $html .= "<li class='current' aria-current='page'>$label</li>";
            } else {
                $url = htmlspecialchars($crumb['url']);
                $html .= "<li><a href='$url'>$label</a></li>";
            }
        }

        $html .= "</ol></nav>";

        return $html;
    }

    /**
     * Render page header with actions
     */
    private function renderPageHeader()
    {
        $actions = $this->pageContext['actions'] ?? [];

        if (empty($actions)) {
            return '';
        }

        $html = "<div class='page-header-actions'>";

        foreach ($actions as $action) {
            $url = htmlspecialchars($action['url']);
            $label = htmlspecialchars($action['label']);
            $class = $action['primary'] ? 'btn-primary' : 'btn-secondary';
            $icon = $action['icon'] ?? '';

            $html .= "<a href='$url' class='btn $class'>";
            if ($icon) {
                $html .= "<span class='icon icon-$icon'></span>";
            }
            $html .= "$label</a>";
        }

        $html .= "</div>";

        return $html;
    }

    /**
     * Render footer bar
     */
    private function renderFooterBar()
    {
        $version = defined('SAMS_VERSION') ? SAMS_VERSION : '2.0.0';
        $year = date('Y');

        return "
<footer class='sams-footer-bar'>
    <div class='footer-left'>
        <span class='copyright'>&copy; $year SAMS</span>
        <span class='version'>v$version</span>
    </div>
    <div class='footer-right'>
        <a href='/help.php'>Help</a>
        <a href='/privacy.php'>Privacy</a>
        <a href='/terms.php'>Terms</a>
    </div>
</footer>";
    }

    /**
     * Render footer HTML
     */
    private function renderFooter()
    {
        return "
</html>";
    }

    /**
     * Render scripts
     */
    private function renderScripts()
    {
        $version = defined('SAMS_VERSION') ? SAMS_VERSION : '2.0.0';
        $page = $this->pageContext['page'] ?? '';

        return "
<!-- Global Scripts -->
<script src='/assets/js/sams-core.js?v=$version'></script>
<script src='/assets/js/sams-layout.js?v=$version'></script>
<script src='/assets/js/sams-chatbot.js?v=$version'></script>

<!-- Initialize -->
<script>
    SAMS.init({
        role: '{$this->role}',
        userId: '{$this->user['id']}',
        tenantId: '{$this->tenant['id']}',
        page: '{$page}'
    });
</script>";
    }

    /**
     * Get page-specific CSS
     */
    private function getPageCSS()
    {
        $page = $this->pageContext['page'] ?? '';
        if ($page && file_exists(__DIR__ . "/../../assets/css/pages/$page.css")) {
            return "<link rel='stylesheet' href='/assets/css/pages/$page.css'>";
        }
        return '';
    }

    private function isActivePage($url)
    {
        $currentPage = basename($_SERVER['PHP_SELF']);
        $menuPage = basename($url);
        return $currentPage === $menuPage;
    }

    /**
     * Get notification count
     */
    private function getNotificationCount()
    {
        // This would query the database for unread notifications
        return 0;
    }

    /**
     * Static method to quickly render a page
     */
    public static function render($content, $options = [])
    {
        return self::getInstance()->renderPage($content, $options);
    }
}
