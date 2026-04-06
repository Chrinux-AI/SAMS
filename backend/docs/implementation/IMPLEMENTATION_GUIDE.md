# SAMS Upgrade Implementation Guide

## Phase 1: Infrastructure Enhancement

### 1.1 Enhanced Composer Setup
```bash
# Replace existing composer.json with enhanced version
mv composer.json composer.legacy.json
mv composer.enhanced.json composer.json

# Install new dependencies
composer install --no-dev
composer install --dev
```

### 1.2 Autoloader Implementation
```php
<?php
// Create: src/bootstrap.php
<?php
require_once __DIR__ . '/../../vendor/autoload.php';

// Legacy compatibility - keep existing includes
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Modern autoloading for new classes
use SAMS\Core\Database;
use SAMS\Core\Auth;
use SAMS\Core\RoleEngine;

// Initialize modern components
$modernDb = new Database();
$modernAuth = new Auth();
$modernRoleEngine = new RoleEngine();

// Bridge legacy and modern systems
if (!function_exists('db')) {
    function db() {
        global $db;
        if ($db === null) {
            $db = Database::getInstance();
        }
        return $db->getConnection();
    }
}
```

### 1.3 Redis Cache Integration
```php
<?php
// Create: src/Cache/RedisCache.php
<?php
namespace SAMS\Cache;

use Predis\Client;

class RedisCache
{
    private static $client = null;
    private static $connected = false;
    
    public static function connect()
    {
        if (self::$connected) {
            return true;
        }
        
        try {
            self::$client = new Client([
                'scheme' => 'tcp',
                'host' => '127.0.0.1',
                'port' => 6379,
                'database' => 0,
            ]);
            
            // Test connection
            self::$client->ping();
            self::$connected = true;
            
            return true;
        } catch (Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            self::$connected = false;
            return false;
        }
    }
    
    public static function get($key)
    {
        if (!self::connect()) {
            return null;
        }
        
        try {
            $value = self::$client->get($key);
            return $value ? json_decode($value, true) : null;
        } catch (Exception $e) {
            error_log("Redis get error: " . $e->getMessage());
            return null;
        }
    }
    
    public static function set($key, $value, $ttl = 3600)
    {
        if (!self::connect()) {
            return false;
        }
        
        try {
            return self::$client->setex($key, $ttl, json_encode($value));
        } catch (Exception $e) {
            error_log("Redis set error: " . $e->getMessage());
            return false;
        }
    }
    
    public static function delete($key)
    {
        if (!self::connect()) {
            return false;
        }
        
        try {
            return self::$client->del($key) > 0;
        } catch (Exception $e) {
            error_log("Redis delete error: " . $e->getMessage());
            return false;
        }
    }
    
    public static function exists($key)
    {
        if (!self::connect()) {
            return false;
        }
        
        try {
            return self::$client->exists($key) > 0;
        } catch (Exception $e) {
            error_log("Redis exists error: " . $e->getMessage());
            return false;
        }
    }
}
```

### 1.4 Enhanced Database Class
```php
<?php
// Create: src/Core/Database.php (extends existing)
<?php
namespace SAMS\Core;

use SAMS\Cache\RedisCache;

class Database
{
    private static $instance = null;
    private $connection;
    private $cacheEnabled = true;
    
    private function __construct()
    {
        try {
            // Use existing connection logic
            if (strpos(DB_HOST, '/') !== false) {
                $dsn = "mysql:unix_socket=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            } else {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            }

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true,
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Enable caching if Redis is available
            $this->cacheEnabled = RedisCache::connect();
            
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed. Please contact administrator.');
        }
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection()
    {
        return $this->connection;
    }
    
    public function query($sql, $params = [], $cacheKey = null, $cacheTtl = 300)
    {
        // Try cache first for SELECT queries
        if ($this->cacheEnabled && $cacheKey && strtoupper(substr($sql, 0, 6)) === 'SELECT') {
            $cached = RedisCache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            if ($this->cacheEnabled && $cacheKey && strtoupper(substr($sql, 0, 6)) === 'SELECT') {
                $result = $stmt->fetchAll();
                RedisCache::set($cacheKey, $result, $cacheTtl);
                return $result;
            }
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            throw new RuntimeException("Database query failed: " . $e->getMessage());
        }
    }
    
    public function fetchOne($sql, $params = [], $cacheKey = null, $cacheTtl = 300)
    {
        // Try cache first
        if ($this->cacheEnabled && $cacheKey && strtoupper(substr($sql, 0, 6)) === 'SELECT') {
            $cached = RedisCache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            if ($this->cacheEnabled && $cacheKey && strtoupper(substr($sql, 0, 6)) === 'SELECT') {
                RedisCache::set($cacheKey, $result, $cacheTtl);
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Database fetchOne error: " . $e->getMessage());
            throw new RuntimeException("Database fetchOne failed: " . $e->getMessage());
        }
    }
    
    public function insert($table, $data)
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(array_values($data));
            
            $lastId = $this->connection->lastInsertId();
            
            // Invalidate relevant cache
            if ($this->cacheEnabled) {
                RedisCache::delete("table:$table");
                RedisCache::delete("query:*");
            }
            
            return $lastId;
        } catch (PDOException $e) {
            error_log("Database insert error: " . $e->getMessage());
            throw new RuntimeException("Database insert failed: " . $e->getMessage());
        }
    }
    
    public function update($table, $data, $where, $whereParams = [])
    {
        $setClause = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $setClause[] = "`$column` = ?";
            $params[] = $value;
        }
        
        $sql = "UPDATE `$table` SET " . implode(', ', $setClause) . " WHERE $where";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(array_merge($params, $whereParams));
            
            $rowCount = $stmt->rowCount();
            
            // Invalidate relevant cache
            if ($this->cacheEnabled) {
                RedisCache::delete("table:$table");
                RedisCache::delete("query:*");
            }
            
            return $rowCount;
        } catch (PDOException $e) {
            error_log("Database update error: " . $e->getMessage());
            throw new RuntimeException("Database update failed: " . $e->getMessage());
        }
    }
    
    public function delete($table, $where, $params = [])
    {
        $sql = "DELETE FROM `$table` WHERE $where";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            
            $rowCount = $stmt->rowCount();
            
            // Invalidate relevant cache
            if ($this->cacheEnabled) {
                RedisCache::delete("table:$table");
                RedisCache::delete("query:*");
            }
            
            return $rowCount;
        } catch (PDOException $e) {
            error_log("Database delete error: " . $e->getMessage());
            throw new RuntimeException("Database delete failed: " . $e->getMessage());
        }
    }
    
    public function count($table, $where = '', $params = [])
    {
        $sql = "SELECT COUNT(*) as count FROM `$table`";
        if ($where) {
            $sql .= " WHERE $where";
        }
        
        $cacheKey = "count:$table:" . md5($sql . serialize($params));
        
        $result = $this->fetchOne($sql, $params, $cacheKey);
        return $result['count'] ?? 0;
    }
}
```

## Phase 2: Code Quality Enhancement

### 2.1 Enhanced Authentication
```php
<?php
// Create: src/Core/Auth.php (extends existing)
<?php
namespace SAMS\Core;

use SAMS\Cache\RedisCache;
use SAMS\Security\PasswordValidator;
use SAMS\Security\SessionManager;

class Auth
{
    private $db;
    private $sessionManager;
    private $passwordValidator;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->sessionManager = new SessionManager();
        $this->passwordValidator = new PasswordValidator();
    }
    
    public function login($email, $password, $remember = false)
    {
        // Check rate limiting
        if ($this->isRateLimited($email)) {
            return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
        }
        
        // Get user by email
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE email = ? AND is_active = 1",
            [$email],
            "user:email:$email"
        );
        
        if (!$user) {
            $this->recordFailedLogin($email);
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        // Verify password with enhanced validation
        if (!$this->passwordValidator->verify($password, $user['password_hash'])) {
            $this->recordFailedLogin($email);
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
        
        // Check if session expired
        if ($this->isSessionExpired($user)) {
            return ['success' => false, 'message' => 'Session expired. Please contact administrator'];
        }
        
        // Successful login
        $this->recordSuccessfulLogin($user);
        $this->createSession($user, $remember);
        
        return ['success' => true, 'message' => 'Login successful', 'user' => $user];
    }
    
    private function isRateLimited($email)
    {
        $cacheKey = "login_attempts:$email";
        $attempts = RedisCache::get($cacheKey) ?: 0;
        
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            return true;
        }
        
        return false;
    }
    
    private function recordFailedLogin($email)
    {
        $cacheKey = "login_attempts:$email";
        $attempts = RedisCache::get($cacheKey) ?: 0;
        RedisCache::set($cacheKey, $attempts + 1, LOCKOUT_DURATION);
        
        // Log to database
        $this->db->insert('login_logs', [
            'email' => $email,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'success' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function createSession($user, $remember = false)
    {
        $sessionId = $this->sessionManager->createSession($user);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['session_id'] = $sessionId;
        
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + (30 * 24 * 60 * 60); // 30 days
            
            $this->db->insert('user_tokens', [
                'user_id' => $user['id'],
                'token' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', $expiry),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            setcookie('remember_token', $token, $expiry, '/', '', false, true);
        }
        
        // Clear failed login attempts
        RedisCache::delete("login_attempts:$email");
    }
    
    public function logout()
    {
        $sessionId = $_SESSION['session_id'] ?? null;
        
        if ($sessionId) {
            $this->sessionManager->destroySession($sessionId);
        }
        
        // Clear session
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
        
        // Clear remember token
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    public function getCurrentUser()
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $cacheKey = "user:{$_SESSION['user_id']}";
        $user = RedisCache::get($cacheKey);
        
        if ($user === null) {
            $user = $this->db->fetchOne(
                "SELECT * FROM users WHERE id = ? AND is_active = 1",
                [$_SESSION['user_id']],
                $cacheKey
            );
        }
        
        return $user;
    }
    
    public function requireLogin($redirect = 'login.php')
    {
        if (!$this->isLoggedIn()) {
            header("Location: $redirect");
            exit;
        }
    }
    
    public function requireRole($role, $redirect = 'unauthorized.php')
    {
        $this->requireLogin();
        
        if (!$this->hasRole($role)) {
            header("Location: $redirect");
            exit;
        }
    }
    
    public function hasRole($role)
    {
        $user = $this->getCurrentUser();
        return $user && $user['role'] === $role;
    }
    
    private function isSessionExpired($user)
    {
        $sessionTimeout = SESSION_TIMEOUT;
        
        // Check if session has timed out
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $sessionTimeout) {
            return true;
        }
        
        // Check if IP address changed
        if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
            return true;
        }
        
        return false;
    }
}
```

### 2.2 Enhanced Role Engine
```php
<?php
// Create: src/Core/RoleEngine.php (extends existing)
<?php
namespace SAMS\Core;

use SAMS\Cache\RedisCache;

class RoleEngine
{
    private $permissions;
    private $cache;
    
    public function __construct()
    {
        $this->permissions = $this->definePermissions();
        $this->cache = new RedisCache();
    }
    
    public function getUserRole()
    {
        return $_SESSION['role'] ?? null;
    }
    
    public function hasPermission($permission)
    {
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
    
    public function hasAnyPermission($permissions)
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }
    
    public function hasAllPermissions($permissions)
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }
    
    public function requirePermission($permission, $redirect = 'unauthorized.php')
    {
        if (!$this->hasPermission($permission)) {
            header("Location: $redirect");
            exit;
        }
    }
    
    public function getRolePermissions($role)
    {
        return $this->permissions[$role] ?? [];
    }
    
    public function getAllPermissions()
    {
        $allPermissions = [];
        
        foreach ($this->permissions as $role => $perms) {
            if (!in_array("*", $perms)) {
                $allPermissions = array_merge($allPermissions, $perms);
            }
        }
        
        return array_unique($allPermissions);
    }
    
    public function canAccessModule($module)
    {
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
    
    public function getUserMenu()
    {
        $role = $this->getUserRole();
        
        $menus = [
            "admin" => [
                'Dashboard' => 'dashboard.php',
                'Users' => 'users/',
                'Classes' => 'classes/',
                'Attendance' => 'attendance.php',
                'Reports' => 'reports/',
                'AI Center' => 'ai-center/',
                'Settings' => 'settings.php'
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
            "forum_moderator" => [
                'Dashboard' => 'dashboard.php',
                'Forum Posts' => 'forum-posts.php',
                'Reports' => 'reports.php'
            ]
        ];
        
        return $menus[$role] ?? [];
    }
    
    public function checkPermissionCache($userId, $permission)
    {
        $cacheKey = "user_permission:$userId:$permission";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $hasPermission = $this->hasPermission($permission);
        $this->cache->set($cacheKey, $hasPermission, 300); // Cache for 5 minutes
        
        return $hasPermission;
    }
    
    public function clearPermissionCache($userId)
    {
        $cacheKey = "user_permission:$userId:*";
        // Note: Redis pattern matching would require Redis keys command
        // For now, we'll clear specific keys or use a different strategy
        $this->cache->delete("user_permission:$userId:view_attendance");
        $this->cache->delete("user_permission:$userId:mark_attendance");
        // Add more specific keys as needed
    }
    
    private function definePermissions()
    {
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
}
```

## Phase 3: Testing Implementation

### 3.1 Unit Tests
```php
<?php
// Create: tests/Unit/RoleEngineTest.php
<?php
namespace SAMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SAMS\Core\RoleEngine;

class RoleEngineTest extends TestCase
{
    private $roleEngine;
    
    protected function setUp(): void
    {
        $this->roleEngine = new RoleEngine();
        
        // Mock session for testing
        $_SESSION['role'] = 'admin';
    }
    
    public function testAdminHasAllPermissions()
    {
        $_SESSION['role'] = 'admin';
        
        $this->assertTrue($this->roleEngine->hasPermission('any_permission'));
        $this->assertTrue($this->roleEngine->hasPermission('manage_users'));
        $this->assertTrue($this->roleEngine->hasPermission('mark_attendance'));
    }
    
    public function testTeacherHasExpectedPermissions()
    {
        $_SESSION['role'] = 'teacher';
        
        $this->assertTrue($this->roleEngine->hasPermission('mark_attendance'));
        $this->assertTrue($this->roleEngine->hasPermission('submit_grades'));
        $this->assertFalse($this->roleEngine->hasPermission('manage_users'));
        $this->assertFalse($this->roleEngine->hasPermission('manage_invoices'));
    }
    
    public function testStudentHasExpectedPermissions()
    {
        $_SESSION['role'] = 'student';
        
        $this->assertTrue($this->roleEngine->hasPermission('view_attendance'));
        $this->assertTrue($this->roleEngine->hasPermission('view_grades'));
        $this->assertFalse($this->roleEngine->hasPermission('mark_attendance'));
        $this->assertFalse($this->roleEngine->hasPermission('manage_users'));
    }
    
    public function testParentHasExpectedPermissions()
    {
        $_SESSION['role'] = 'parent';
        
        $this->assertTrue($this->roleEngine->hasPermission('view_child_attendance'));
        $this->assertTrue($this->roleEngine->hasPermission('view_child_grades'));
        $this->assertFalse($this->roleEngine->hasPermission('mark_attendance'));
        $this->assertFalse($this->roleEngine->hasPermission('manage_users'));
    }
    
    public function testHasAnyPermission()
    {
        $_SESSION['role'] = 'teacher';
        
        $this->assertTrue($this->roleEngine->hasAnyPermission(['mark_attendance', 'manage_users']));
        $this->assertFalse($this->roleEngine->hasAnyPermission(['manage_users', 'manage_invoices']));
    }
    
    public function testHasAllPermissions()
    {
        $_SESSION['role'] = 'teacher';
        
        $this->assertTrue($this->roleEngine->hasAllPermissions(['mark_attendance', 'submit_grades']));
        $this->assertFalse($this->roleEngine->hasAllPermissions(['mark_attendance', 'manage_users']));
    }
    
    public function testCanAccessModule()
    {
        $_SESSION['role'] = 'admin';
        $this->assertTrue($this->roleEngine->canAccessModule('admin'));
        $this->assertTrue($this->roleEngine->canAccessModule('users'));
        
        $_SESSION['role'] = 'teacher';
        $this->assertTrue($this->roleEngine->canAccessModule('attendance'));
        $this->assertTrue($this->roleEngine->canAccessModule('grades'));
        $this->assertFalse($this->roleEngine->canAccessModule('admin'));
    }
    
    public function testGetUserMenu()
    {
        $_SESSION['role'] = 'admin';
        $menu = $this->roleEngine->getUserMenu();
        $this->assertArrayHasKey('Dashboard', $menu);
        $this->assertArrayHasKey('Users', $menu);
        $this->assertArrayHasKey('AI Center', $menu);
        
        $_SESSION['role'] = 'teacher';
        $menu = $this->roleEngine->getUserMenu();
        $this->assertArrayHasKey('Dashboard', $menu);
        $this->assertArrayHasKey('My Classes', $menu);
        $this->assertArrayHasKey('Attendance', $menu);
        $this->assertArrayNotHasKey('Users', $menu);
    }
    
    public function testGetRolePermissions()
    {
        $permissions = $this->roleEngine->getRolePermissions('admin');
        $this->assertContains('*', $permissions);
        
        $permissions = $this->roleEngine->getRolePermissions('teacher');
        $this->assertContains('mark_attendance', $permissions);
        $this->assertContains('submit_grades', $permissions);
        $this->assertNotContains('*', $permissions);
    }
    
    public function testGetAllPermissions()
    {
        $allPermissions = $this->roleEngine->getAllPermissions();
        $this->assertIsArray($allPermissions);
        $this->assertContains('mark_attendance', $allPermissions);
        $this->assertContains('view_grades', $allPermissions);
        $this->assertContains('manage_users', $allPermissions);
    }
}
```

### 3.2 Integration Tests
```php
<?php
// Create: tests/Integration/AuthenticationTest.php
<?php
namespace SAMS\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SAMS\Core\Auth;
use SAMS\Core\Database;

class AuthenticationTest extends TestCase
{
    private $auth;
    private $db;
    
    protected function setUp(): void
    {
        $this->auth = new Auth();
        $this->db = Database::getInstance();
        
        // Start session for testing
        session_start();
    }
    
    public function testSuccessfulLogin()
    {
        // Create test user
        $userId = $this->db->insert('users', [
            'email' => 'test@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'User',
            'role' => 'teacher',
            'is_active' => 1
        ]);
        
        // Test login
        $result = $this->auth->login('test@example.com', 'password123');
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Test User', $result['user']['first_name'] . ' ' . $result['user']['last_name']);
        $this->assertEquals('teacher', $result['user']['role']);
        
        // Clean up
        $this->db->delete('users', 'id = ?', [$userId]);
    }
    
    public function testFailedLogin()
    {
        $result = $this->auth->login('nonexistent@example.com', 'password');
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid email or password', $result['message']);
    }
    
    public function testIsLoggedIn()
    {
        $this->assertFalse($this->auth->isLoggedIn());
        
        // Simulate login
        $_SESSION['user_id'] = 1;
        $this->assertTrue($this->auth->isLoggedIn());
        
        unset($_SESSION['user_id']);
        $this->assertFalse($this->auth->isLoggedIn());
    }
    
    public function testLogout()
    {
        // Simulate login
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'teacher';
        
        $result = $this->auth->logout();
        
        $this->assertTrue($result['success']);
        $this->assertFalse(isset($_SESSION['user_id']));
        $this->assertFalse(isset($_SESSION['role']));
    }
    
    public function testRequireLogin()
    {
        // Test with no login
        ob_start();
        $this->auth->requireLogin();
        $output = ob_get_clean();
        
        // Should redirect to login.php
        $this->assertEmpty($output); // Headers sent, no output
        
        // Test with login
        $_SESSION['user_id'] = 1;
        
        ob_start();
        $this->auth->requireLogin();
        $output = ob_get_clean();
        
        // Should not redirect
        $this->assertEmpty($output);
        
        unset($_SESSION['user_id']);
    }
    
    public function testRequireRole()
    {
        // Test with no login
        ob_start();
        $this->auth->requireRole('admin');
        $output = ob_get_clean();
        $this->assertEmpty($output);
        
        // Test with wrong role
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'teacher';
        
        ob_start();
        $this->auth->requireRole('admin');
        $output = ob_get_clean();
        $this->assertEmpty($output);
        
        // Test with correct role
        $_SESSION['role'] = 'admin';
        
        ob_start();
        $this->auth->requireRole('admin');
        $output = ob_get_clean();
        $this->assertEmpty($output);
        
        unset($_SESSION['user_id']);
        unset($_SESSION['role']);
    }
    
    public function testHasRole()
    {
        $_SESSION['role'] = 'teacher';
        
        $this->assertTrue($this->auth->hasRole('teacher'));
        $this->assertFalse($this->auth->hasRole('admin'));
        
        unset($_SESSION['role']);
        
        $this->assertFalse($this->auth->hasRole('teacher'));
    }
    
    public function testGetCurrentUser()
    {
        // Test with no login
        $user = $this->auth->getCurrentUser();
        $this->assertNull($user);
        
        // Test with login
        $userId = $this->db->insert('users', [
            'email' => 'test@example.com',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'User',
            'role' => 'teacher',
            'is_active' => 1
        ]);
        
        $_SESSION['user_id'] = $userId;
        
        $user = $this->auth->getCurrentUser();
        $this->assertNotNull($user);
        $this->assertEquals('test@example.com', $user['email']);
        $this->assertEquals('Test', $user['first_name']);
        
        // Clean up
        $this->db->delete('users', 'id = ?', [$userId]);
        unset($_SESSION['user_id']);
    }
}
```

## Phase 4: Security Enhancement

### 4.1 Security Headers
```php
<?php
// Create: src/Security/SecurityHeaders.php
<?php
namespace SAMS\Security;

class SecurityHeaders
{
    public static function send()
    {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Enable XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Force HTTPS (if available)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https:; connect-src 'self'; frame-ancestors 'none';");
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        
        // Server information
        header('Server: SAMS/2.0');
        
        // Remove PHP version
        header('X-Powered-By: PHP');
    }
    
    public static function sendAPI()
    {
        // API-specific headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 3600');
        
        // Rate limiting headers
        header('X-RateLimit-Limit: 1000');
        header('X-RateLimit-Remaining: 999');
        header('X-RateLimit-Reset: ' . (time() + 3600));
        
        // API version
        header('API-Version: 1.0');
        
        // Send standard security headers
        self::send();
    }
    
    public static function sendNoCache()
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    }
    
    public static function sendJSON($data, $statusCode = 200)
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
        
        echo json_encode($data);
        exit;
    }
    
    public static function validateCSRF($token)
    {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    public static function sanitizeInput($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validatePassword($password)
    {
        // At least 8 characters, at least one uppercase, one lowercase, one number
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
    }
}
```

### 4.2 Input Validation
```php
<?php
// Create: src/Validation/Validator.php
<?php
namespace SAMS\Validation;

class Validator
{
    private $errors = [];
    private $data;
    
    public function __construct($data = [])
    {
        $this->data = $data;
    }
    
    public function validate($rules)
    {
        foreach ($rules as $field => $fieldRules) {
            $value = $this->data[$field] ?? null;
            
            foreach ($fieldRules as $rule => $params) {
                if (!$this->validateRule($field, $value, $rule, $params)) {
                    break; // Stop on first error for this field
                }
            }
        }
        
        return empty($this->errors);
    }
    
    private function validateRule($field, $value, $rule, $params)
    {
        switch ($rule) {
            case 'required':
                if (empty($value)) {
                    $this->addError($field, 'This field is required');
                    return false;
                }
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'Please enter a valid email address');
                    return false;
                }
                break;
                
            case 'min':
                if (!empty($value) && strlen($value) < $params) {
                    $this->addError($field, "Minimum length is {$params} characters");
                    return false;
                }
                break;
                
            case 'max':
                if (!empty($value) && strlen($value) > $params) {
                    $this->addError($field, "Maximum length is {$params} characters");
                    return false;
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, 'This field must be numeric');
                    return false;
                }
                break;
                
            case 'alpha':
                if (!empty($value) && !ctype_alpha($value)) {
                    $this->addError($field, 'This field must contain only letters');
                    return false;
                }
                break;
                
            case 'alphanum':
                if (!empty($value) && !ctype_alnum($value)) {
                    $this->addError($field, 'This field must contain only letters and numbers');
                    return false;
                }
                break;
                
            case 'regex':
                if (!empty($value) && !preg_match($params, $value)) {
                    $this->addError($field, 'Invalid format');
                    return false;
                }
                break;
                
            case 'in':
                if (!empty($value) && !in_array($value, $params)) {
                    $this->addError($field, 'Invalid value');
                    return false;
                }
                break;
                
            case 'date':
                if (!empty($value) && !strtotime($value)) {
                    $this->addError($field, 'Invalid date format');
                    return false;
                }
                break;
                
            case 'password':
                if (!empty($value) && !$this->validatePassword($value)) {
                    $this->addError($field, 'Password must be at least 8 characters with uppercase, lowercase, and number');
                    return false;
                }
                break;
        }
        
        return true;
    }
    
    private function validatePassword($password)
    {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
    }
    
    private function addError($field, $message)
    {
        $this->errors[$field] = $message;
    }
    
    public function getErrors()
    {
        return $this->errors;
    }
    
    public function getFirstError()
    {
        return reset($this->errors);
    }
    
    public function hasErrors()
    {
        return !empty($this->errors);
    }
    
    public static function sanitize($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    public static function escape($value)
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    public static function clean($value)
    {
        return trim($value);
    }
    
    public static function validateCSRF($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
}
```

## Implementation Commands

### Phase 1 Commands
```bash
# Setup enhanced composer
composer install

# Run code quality checks
composer check

# Fix code style issues
composer fix

# Run static analysis
composer analyze

# Run tests
composer test
```

### Phase 2 Commands
```bash
# Run tests with coverage
composer test-coverage

# Run quality checks
composer quality

# Security check
composer security

# Clear cache
composer cache-clear
```

### Phase 3 Commands
```bash
# Run specific test suites
phpunit tests/Unit/
phpunit tests/Integration/
phpunit tests/API/

# Generate coverage report
phpunit --coverage-html reports/coverage

# Run performance tests
php tests/Performance/
```

### Phase 4 Commands
```bash
# Run security tests
phpunit tests/Security/

# Validate configuration
php scripts/validate-config.php

# Check security headers
php scripts/check-headers.php
```

## Monitoring Commands

```bash
# Monitor system health
composer monitor

# Check database status
php scripts/db-status.php

# Check cache status
php scripts/cache-status.php

# Generate system report
php scripts/system-report.php
```

This implementation guide provides a safe, incremental approach to upgrading the SAMS system while maintaining full backward compatibility and system stability.
