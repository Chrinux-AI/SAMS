<?php
/**
 * SAMS Core Role Engine
 * Centralized role-based permission system
 */

require_once __DIR__ . '/auth.php';

class RoleEngine {
    private $permissions;
    
    public function __construct() {
        $this->permissions = $this->definePermissions();
    }
    
    /**
     * Define all system permissions
     */
    private function definePermissions() {
        return [
            "admin" => ["*"], // Full access
            
            "teacher" => [
                "view_classes",
                "mark_attendance",
                "submit_grades",
                "upload_assignments",
                "view_students",
                "view_attendance_reports",
                "generate_class_reports",
                "manage_class_materials"
            ],
            
            "student" => [
                "view_attendance",
                "view_grades",
                "submit_assignments",
                "view_assignments",
                "view_class_schedule",
                "download_reports",
                "participate_forum"
            ],
            
            "parent" => [
                "view_child_attendance",
                "view_child_grades",
                "view_child_assignments",
                "receive_notifications",
                "view_child_reports",
                "communicate_teachers"
            ],
            
            "accountant" => [
                "manage_invoices",
                "track_payments",
                "financial_reports",
                "manage_fees",
                "view_payment_history",
                "generate_financial_reports"
            ],
            
            "librarian" => [
                "manage_books",
                "lend_books",
                "return_books",
                "view_library_reports",
                "manage_library_inventory",
                "send_overdue_notices"
            ],
            
            "transport" => [
                "manage_routes",
                "assign_students",
                "view_transport_reports",
                "manage_vehicles",
                "track_transport_usage"
            ],
            
            "moderator" => [
                "review_posts",
                "delete_posts",
                "manage_forum",
                "moderate_content",
                "view_moderation_reports"
            ]
        ];
    }
    
    /**
     * Get current user role
     */
    public function getUserRole() {
        return $_SESSION['role'] ?? null;
    }
    
    /**
     * Check if user has permission
     */
    public function hasPermission($permission) {
        $role = $this->getUserRole();
        
        if (!$role || !isset($this->permissions[$role])) {
            return false;
        }
        
        // Admin has all permissions
        if (in_array("*", $this->permissions[$role])) {
            return true;
        }
        
        return in_array($permission, $this->permissions[$role]);
    }
    
    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission($permissions) {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if user has all given permissions
     */
    public function hasAllPermissions($permissions) {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Require permission (redirect if not authorized)
     */
    public function requirePermission($permission, $redirect = 'unauthorized.php') {
        if (!$this->hasPermission($permission)) {
            header("Location: $redirect");
            exit;
        }
    }
    
    /**
     * Require any of the given permissions
     */
    public function requireAnyPermission($permissions, $redirect = 'unauthorized.php') {
        if (!$this->hasAnyPermission($permissions)) {
            header("Location: $redirect");
            exit;
        }
    }
    
    /**
     * Get all permissions for a role
     */
    public function getRolePermissions($role) {
        return $this->permissions[$role] ?? [];
    }
    
    /**
     * Get all available permissions
     */
    public function getAllPermissions() {
        $allPermissions = [];
        
        foreach ($this->permissions as $role => $perms) {
            if (!in_array("*", $perms)) {
                $allPermissions = array_merge($allPermissions, $perms);
            }
        }
        
        return array_unique($allPermissions);
    }
    
    /**
     * Check if user can access specific module
     */
    public function canAccessModule($module) {
        $modulePermissions = [
            'admin' => ['*'],
            'users' => ['manage_users'],
            'classes' => ['manage_classes'],
            'attendance' => ['view_attendance', 'mark_attendance'],
            'grades' => ['view_grades', 'submit_grades'],
            'assignments' => ['view_assignments', 'upload_assignments'],
            'reports' => ['view_reports'],
            'finance' => ['manage_invoices', 'track_payments'],
            'library' => ['manage_books'],
            'transport' => ['manage_routes'],
            'forum' => ['participate_forum', 'manage_forum'],
            'ai-center' => ['view_ai_dashboard']
        ];
        
        $requiredPermissions = $modulePermissions[$module] ?? [];
        
        if (empty($requiredPermissions)) {
            return true; // Public module
        }
        
        return $this->hasAnyPermission($requiredPermissions);
    }
    
    /**
     * Get user menu items based on role
     */
    public function getUserMenu() {
        $role = $this->getUserRole();
        
        $menus = [
            "admin" => [
                'Dashboard' => 'dashboard.php',
                'Users' => 'users/',
                'Classes' => 'classes/',
                'Attendance' => 'attendance.php',
                'Reports' => 'reports/',
                'AI Center' => 'ai-center/',
                'Settings' => 'settings/'
            ],
            
            "teacher" => [
                'Dashboard' => 'dashboard.php',
                'My Classes' => 'classes.php',
                'Attendance' => 'attendance.php',
                'Grades' => 'grades.php',
                'Assignments' => 'assignments.php',
                'Reports' => 'reports.php'
            ],
            
            "student" => [
                'Dashboard' => 'dashboard.php',
                'Attendance' => 'attendance.php',
                'Grades' => 'grades.php',
                'Assignments' => 'assignments.php',
                'Reports' => 'reports.php'
            ],
            
            "parent" => [
                'Dashboard' => 'dashboard.php',
                'Child Attendance' => 'attendance.php',
                'Child Grades' => 'grades.php',
                'Reports' => 'reports.php'
            ],
            
            "accountant" => [
                'Dashboard' => 'dashboard.php',
                'Invoices' => 'invoices.php',
                'Payments' => 'payments.php',
                'Reports' => 'reports.php'
            ],
            
            "librarian" => [
                'Dashboard' => 'dashboard.php',
                'Books' => 'books.php',
                'Lending' => 'lending.php',
                'Reports' => 'reports.php'
            ],
            
            "transport" => [
                'Dashboard' => 'dashboard.php',
                'Routes' => 'routes.php',
                'Assignments' => 'assignments.php',
                'Reports' => 'reports.php'
            ],
            
            "moderator" => [
                'Dashboard' => 'dashboard.php',
                'Forum Posts' => 'forum-posts.php',
                'Reports' => 'reports.php'
            ]
        ];
        
        return $menus[$role] ?? [];
    }
    
    /**
     * Check if role exists
     */
    public function roleExists($role) {
        return isset($this->permissions[$role]);
    }
    
    /**
     * Add custom permission to role
     */
    public function addPermission($role, $permission) {
        if (!$this->roleExists($role)) {
            return false;
        }
        
        if (!in_array("*", $this->permissions[$role]) && 
            !in_array($permission, $this->permissions[$role])) {
            $this->permissions[$role][] = $permission;
        }
        
        return true;
    }
    
    /**
     * Remove permission from role
     */
    public function removePermission($role, $permission) {
        if (!$this->roleExists($role)) {
            return false;
        }
        
        $key = array_search($permission, $this->permissions[$role]);
        if ($key !== false) {
            unset($this->permissions[$role][$key]);
            $this->permissions[$role] = array_values($this->permissions[$role]);
        }
        
        return true;
    }
    
    /**
     * Get role hierarchy for permissions
     */
    public function getRoleHierarchy() {
        return [
            'admin' => 8,
            'accountant' => 7,
            'teacher' => 6,
            'librarian' => 5,
            'transport' => 4,
            'moderator' => 3,
            'parent' => 2,
            'student' => 1
        ];
    }
    
    /**
     * Check if user can access resource based on role hierarchy
     */
    public function canAccessResource($requiredRole) {
        $userRole = $this->getUserRole();
        $hierarchy = $this->getRoleHierarchy();
        
        if (!isset($hierarchy[$userRole]) || !isset($hierarchy[$requiredRole])) {
            return false;
        }
        
        return $hierarchy[$userRole] >= $hierarchy[$requiredRole];
    }
}

// Global role engine instance
function role_engine() {
    static $instance = null;
    if ($instance === null) {
        $instance = new RoleEngine();
    }
    return $instance;
}

// Convenience functions
function get_user_role() {
    return role_engine()->getUserRole();
}

function has_permission($permission) {
    return role_engine()->hasPermission($permission);
}

function require_permission($permission, $redirect = 'unauthorized.php') {
    return role_engine()->requirePermission($permission, $redirect);
}

function can_access_module($module) {
    return role_engine()->canAccessModule($module);
}

function get_user_menu() {
    return role_engine()->getUserMenu();
}

// Auto-load role engine
$GLOBALS['role_engine'] = role_engine();
?>
