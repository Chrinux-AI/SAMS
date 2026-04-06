<?php

/**
 * SAMS Security Middleware
 * Role-based access control and security checks
 */

class SAMS_SecurityMiddleware
{

    private static $allowedRoles = [
        'admin' => ['admin/*', 'api/*', 'dashboard.php'],
        'teacher' => ['teacher/*', 'api/*', 'dashboard.php', 'attendance.php'],
        'student' => ['student/*', 'api/*', 'dashboard.php'],
        'parent' => ['parent/*', 'api/*', 'dashboard.php'],
        'accountant' => ['accountant/*', 'api/*', 'dashboard.php'],
        'bursar' => ['bursar/*', 'api/*', 'dashboard.php'],
        'librarian' => ['librarian/*', 'api/*', 'dashboard.php'],
        'transport' => ['transport/*', 'api/*', 'dashboard.php'],
        'forum_moderator' => ['forum/*', 'api/*', 'dashboard.php']
    ];

    /**
     * Check if user is authenticated
     */
    public static function requireAuth()
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
            header('Location: /login.php?error=unauthorized');
            exit;
        }

        // Check session timeout (30 minutes)
        if (isset($_SESSION['last_activity'])) {
            $inactive = time() - $_SESSION['last_activity'];
            if ($inactive > 1800) {
                session_destroy();
                header('Location: /login.php?error=session_expired');
                exit;
            }
        }

        $_SESSION['last_activity'] = time();
    }

    /**
     * Check if user has required role
     */
    public static function requireRole($allowedRoles)
    {
        self::requireAuth();

        if (!is_array($allowedRoles)) {
            $allowedRoles = [$allowedRoles];
        }

        if (!in_array($_SESSION['role'], $allowedRoles)) {
            header('Location: /unauthorized.php');
            exit;
        }
    }

    /**
     * Check if user can access current page
     */
    public static function checkAccess()
    {
        self::requireAuth();

        $currentPage = $_SERVER['PHP_SELF'];
        $userRole = $_SESSION['role'];

        // Super admin can access everything
        if ($userRole === 'super_admin') {
            return true;
        }

        // Get allowed patterns for role
        $patterns = self::$allowedRoles[$userRole] ?? [];

        foreach ($patterns as $pattern) {
            if (self::matchPattern($currentPage, $pattern)) {
                return true;
            }
        }

        // No match - unauthorized
        header('Location: /unauthorized.php');
        exit;
    }

    /**
     * Match URL against pattern
     */
    private static function matchPattern($url, $pattern)
    {
        $pattern = str_replace('*', '.*', $pattern);
        $pattern = '/^' . str_replace('/', '\/', $pattern) . '$/';
        return preg_match($pattern, $url);
    }

    /**
     * Generate and validate CSRF token
     */
    public static function generateCSRFToken()
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token)
    {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Sanitize input
     */
    public static function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Rate limiting check
     */
    public static function checkRateLimit($action, $maxAttempts = 5, $window = 3600)
    {
        $key = 'rate_limit_' . $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => 0, 'first_attempt' => time()];
        }

        $data = $_SESSION[$key];

        // Reset if window expired
        if (time() - $data['first_attempt'] > $window) {
            $_SESSION[$key] = ['attempts' => 1, 'first_attempt' => time()];
            return true;
        }

        // Check limit
        if ($data['attempts'] >= $maxAttempts) {
            return false;
        }

        // Increment
        $_SESSION[$key]['attempts']++;
        return true;
    }

    /**
     * Log security event
     */
    public static function logSecurityEvent($event, $details = [])
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];

        error_log('SECURITY: ' . json_encode($logData));

        // Log to database using parameterized query
        try {
            $db = db();
            if ($db) {
                $db->query(
                    "INSERT INTO security_logs (event, user_id, ip_address, details, created_at) VALUES (?, ?, ?, ?, NOW())",
                    [
                        (string)$event,
                        $_SESSION['user_id'] ?? null,
                        (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
                        json_encode($details)
                    ]
                );
            }
        } catch (Exception $e) {
            // Database logging failed, but error_log succeeded
        }
    }
}

/**
 * Helper function to require authentication
 */
function require_auth()
{
    SAMS_SecurityMiddleware::requireAuth();
}

/**
 * Helper function to require specific role
 */
function require_role($roles)
{
    SAMS_SecurityMiddleware::requireRole($roles);
}

/**
 * Helper function to check access
 */
function check_access()
{
    SAMS_SecurityMiddleware::checkAccess();
}

/**
 * Helper function to get CSRF token
 */
function csrf_token()
{
    return SAMS_SecurityMiddleware::generateCSRFToken();
}

/**
 * Helper function to validate CSRF
 */
function validate_csrf($token)
{
    return SAMS_SecurityMiddleware::validateCSRFToken($token);
}
