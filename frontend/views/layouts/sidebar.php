<?php

/**
 * SAMS Dynamic Sidebar System
 * Loads role-specific navigation menus
 */

// Prevent direct access
if (!defined('SAMS_BOOTSTRAP_LOADED')) {
    require_once __DIR__ . '/../../core/bootstrap.php';
}

/**
 * Load sidebar based on user role
 */
function loadSidebar($role)
{
    $sidebarItems = getSidebarItems($role);

    echo '<nav class="nav flex-column">';
    echo '<div class="p-3 border-bottom">';
    echo '<h6 class="text-muted text-uppercase mb-2">' . ucfirst($role) . ' Panel</h6>';
    echo '</div>';

    foreach ($sidebarItems as $item) {
        $activeClass = isset($_GET['page']) && $_GET['page'] === $item['page'] ? 'active' : '';
        $icon = isset($item['icon']) ? $item['icon'] : 'fas fa-circle';

        echo '<a href="' . $item['url'] . '" class="nav-link ' . $activeClass . '">';
        echo '<i class="' . $icon . '"></i>';
        echo $item['label'];

        if (isset($item['badge'])) {
            echo '<span class="badge bg-secondary ms-auto">' . $item['badge'] . '</span>';
        }

        echo '</a>';
    }

    echo '</nav>';
}

/**
 * Get sidebar items based on role
 */
function getSidebarItems($role)
{
    $baseUrl = function_exists('base_url') ? rtrim(base_url(''), '/') : rtrim(APP_URL, '/');

    $sidebars = [
        'admin' => [
            [
                'page' => 'dashboard',
                'label' => 'Dashboard',
                'url' => $baseUrl . '/admin/dashboard.php',
                'icon' => 'fas fa-tachometer-alt'
            ],
            [
                'page' => 'students',
                'label' => 'Students',
                'url' => $baseUrl . '/admin/students.php',
                'icon' => 'fas fa-user-graduate'
            ],
            [
                'page' => 'teachers',
                'label' => 'Teachers',
                'url' => $baseUrl . '/admin/teachers.php',
                'icon' => 'fas fa-chalkboard-teacher'
            ],
            [
                'page' => 'classes',
                'label' => 'Classes',
                'url' => $baseUrl . '/admin/classes.php',
                'icon' => 'fas fa-school'
            ],
            [
                'page' => 'attendance',
                'label' => 'Attendance',
                'url' => $baseUrl . '/admin/attendance.php',
                'icon' => 'fas fa-calendar-check'
            ],
            [
                'page' => 'reports',
                'label' => 'Reports',
                'url' => $baseUrl . '/admin/reports.php',
                'icon' => 'fas fa-chart-bar'
            ],
            [
                'page' => 'settings',
                'label' => 'Settings',
                'url' => $baseUrl . '/admin/settings.php',
                'icon' => 'fas fa-cog'
            ],
            [
                'page' => 'ai_documentation',
                'label' => 'AI Documentation',
                'url' => $baseUrl . '/admin/ai/documentation.php',
                'icon' => 'fas fa-robot'
            ],
            [
                'page' => 'backup',
                'label' => 'Backup System',
                'url' => $baseUrl . '/admin/backup.php',
                'icon' => 'fas fa-database'
            ]
        ],

        'teacher' => [
            [
                'page' => 'dashboard',
                'label' => 'Dashboard',
                'url' => $baseUrl . '/teacher/dashboard.php',
                'icon' => 'fas fa-tachometer-alt'
            ],
            [
                'page' => 'my_classes',
                'label' => 'My Classes',
                'url' => $baseUrl . '/teacher/classes.php',
                'icon' => 'fas fa-school'
            ],
            [
                'page' => 'attendance',
                'label' => 'Attendance',
                'url' => $baseUrl . '/teacher/attendance.php',
                'icon' => 'fas fa-calendar-check'
            ],
            [
                'page' => 'students',
                'label' => 'My Students',
                'url' => $baseUrl . '/teacher/students.php',
                'icon' => 'fas fa-user-graduate'
            ],
            [
                'page' => 'assignments',
                'label' => 'Assignments',
                'url' => $baseUrl . '/teacher/assignments.php',
                'icon' => 'fas fa-tasks'
            ],
            [
                'page' => 'grades',
                'label' => 'Grades',
                'url' => $baseUrl . '/teacher/grades.php',
                'icon' => 'fas fa-chart-line'
            ]
        ],

        'student' => [
            [
                'page' => 'dashboard',
                'label' => 'Dashboard',
                'url' => $baseUrl . '/student/dashboard.php',
                'icon' => 'fas fa-tachometer-alt'
            ],
            [
                'page' => 'attendance',
                'label' => 'My Attendance',
                'url' => $baseUrl . '/student/attendance.php',
                'icon' => 'fas fa-calendar-check'
            ],
            [
                'page' => 'grades',
                'label' => 'My Grades',
                'url' => $baseUrl . '/student/grades.php',
                'icon' => 'fas fa-chart-line'
            ],
            [
                'page' => 'assignments',
                'label' => 'Assignments',
                'url' => $baseUrl . '/student/assignments.php',
                'icon' => 'fas fa-tasks'
            ],
            [
                'page' => 'schedule',
                'label' => 'Schedule',
                'url' => $baseUrl . '/student/schedule.php',
                'icon' => 'fas fa-clock'
            ],
            [
                'page' => 'profile',
                'label' => 'Profile',
                'url' => $baseUrl . '/student/profile.php',
                'icon' => 'fas fa-user'
            ]
        ],

        'parent' => [
            [
                'page' => 'dashboard',
                'label' => 'Dashboard',
                'url' => $baseUrl . '/parent/dashboard.php',
                'icon' => 'fas fa-tachometer-alt'
            ],
            [
                'page' => 'children',
                'label' => 'My Children',
                'url' => $baseUrl . '/parent/children.php',
                'icon' => 'fas fa-users'
            ],
            [
                'page' => 'attendance',
                'label' => 'Attendance',
                'url' => $baseUrl . '/parent/attendance.php',
                'icon' => 'fas fa-calendar-check'
            ],
            [
                'page' => 'grades',
                'label' => 'Grades',
                'url' => $baseUrl . '/parent/grades.php',
                'icon' => 'fas fa-chart-line'
            ],
            [
                'page' => 'fees',
                'label' => 'Fees',
                'url' => $baseUrl . '/parent/fees.php',
                'icon' => 'fas fa-dollar-sign'
            ],
            [
                'page' => 'notifications',
                'label' => 'Notifications',
                'url' => $baseUrl . '/parent/notifications.php',
                'icon' => 'fas fa-bell'
            ]
        ],

        'accountant' => [
            [
                'page' => 'dashboard',
                'label' => 'Dashboard',
                'url' => $baseUrl . '/accountant/dashboard.php',
                'icon' => 'fas fa-tachometer-alt'
            ],
            [
                'page' => 'fees',
                'label' => 'Fee Management',
                'url' => $baseUrl . '/accountant/fees.php',
                'icon' => 'fas fa-dollar-sign'
            ],
            [
                'page' => 'payments',
                'label' => 'Payments',
                'url' => $baseUrl . '/accountant/payments.php',
                'icon' => 'fas fa-credit-card'
            ],
            [
                'page' => 'invoices',
                'label' => 'Invoices',
                'url' => $baseUrl . '/accountant/invoices.php',
                'icon' => 'fas fa-file-invoice'
            ],
            [
                'page' => 'reports',
                'label' => 'Financial Reports',
                'url' => $baseUrl . '/accountant/reports.php',
                'icon' => 'fas fa-chart-bar'
            ],
            [
                'page' => 'transactions',
                'label' => 'Transactions',
                'url' => $baseUrl . '/accountant/transactions.php',
                'icon' => 'fas fa-exchange-alt'
            ]
        ],

        'librarian' => [
            [
                'page' => 'dashboard',
                'label' => 'Dashboard',
                'url' => $baseUrl . '/librarian/dashboard.php',
                'icon' => 'fas fa-tachometer-alt'
            ],
            [
                'page' => 'books',
                'label' => 'Books',
                'url' => $baseUrl . '/librarian/books.php',
                'icon' => 'fas fa-book'
            ],
            [
                'page' => 'circulation',
                'label' => 'Circulation',
                'url' => $baseUrl . '/librarian/circulation.php',
                'icon' => 'fas fa-exchange-alt'
            ],
            [
                'page' => 'members',
                'label' => 'Members',
                'url' => $baseUrl . '/librarian/members.php',
                'icon' => 'fas fa-users'
            ],
            [
                'page' => 'catalog',
                'label' => 'Catalog',
                'url' => $baseUrl . '/librarian/catalog.php',
                'icon' => 'fas fa-list'
            ],
            [
                'page' => 'reports',
                'label' => 'Reports',
                'url' => $baseUrl . '/librarian/reports.php',
                'icon' => 'fas fa-chart-bar'
            ]
        ],

        'transport' => [
            [
                'page' => 'dashboard',
                'label' => 'Dashboard',
                'url' => $baseUrl . '/transport/dashboard.php',
                'icon' => 'fas fa-tachometer-alt'
            ],
            [
                'page' => 'vehicles',
                'label' => 'Vehicles',
                'url' => $baseUrl . '/transport/vehicles.php',
                'icon' => 'fas fa-bus'
            ],
            [
                'page' => 'routes',
                'label' => 'Routes',
                'url' => $baseUrl . '/transport/routes.php',
                'icon' => 'fas fa-route'
            ],
            [
                'page' => 'drivers',
                'label' => 'Drivers',
                'url' => $baseUrl . '/transport/drivers.php',
                'icon' => 'fas fa-id-card'
            ],
            [
                'page' => 'schedules',
                'label' => 'Schedules',
                'url' => $baseUrl . '/transport/schedules.php',
                'icon' => 'fas fa-clock'
            ],
            [
                'page' => 'tracking',
                'label' => 'Tracking',
                'url' => $baseUrl . '/transport/tracking.php',
                'icon' => 'fas fa-map-marked-alt'
            ]
        ],

        'moderator' => [
            [
                'page' => 'dashboard',
                'label' => 'Dashboard',
                'url' => $baseUrl . '/moderator/dashboard.php',
                'icon' => 'fas fa-tachometer-alt'
            ],
            [
                'page' => 'content',
                'label' => 'Content',
                'url' => $baseUrl . '/moderator/content.php',
                'icon' => 'fas fa-file-alt'
            ],
            [
                'page' => 'users',
                'label' => 'Users',
                'url' => $baseUrl . '/moderator/users.php',
                'icon' => 'fas fa-users'
            ],
            [
                'page' => 'reports',
                'label' => 'Reports',
                'url' => $baseUrl . '/moderator/reports.php',
                'icon' => 'fas fa-flag'
            ],
            [
                'page' => 'moderation',
                'label' => 'Moderation',
                'url' => $baseUrl . '/moderator/moderation.php',
                'icon' => 'fas fa-shield-alt'
            ],
            [
                'page' => 'logs',
                'label' => 'Activity Logs',
                'url' => $baseUrl . '/moderator/logs.php',
                'icon' => 'fas fa-history'
            ]
        ]
    ];

    return isset($sidebars[$role]) ? $sidebars[$role] : [];
}

// Load sidebar if called directly
if (basename(__FILE__) === 'sidebar.php') {
    $role = isset($_GET['role']) ? $_GET['role'] : getCurrentRole();
    loadSidebar($role);
}
