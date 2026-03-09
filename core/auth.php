<?php
/**
 * SAMS Core Authentication
 * Handles user authentication, sessions, and security
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

class Auth {
    private $db;
    private $maxLoginAttempts;
    
    public function __construct() {
        $this->db = db();
        $this->maxLoginAttempts = MAX_LOGIN_ATTEMPTS;
    }
    
    /**
     * Login user
     */
    public function login($username, $password, $remember = false) {
        // Check if user is locked out
        if ($this->isLockedOut($username)) {
            return ['success' => false, 'message' => 'Account temporarily locked due to too many failed attempts'];
        }
        
        // Get user by username
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE username = ? AND is_active = 1",
            [$username]
        );
        
        if (!$user) {
            $this->recordFailedLogin($username);
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $this->recordFailedLogin($username);
            return ['success' => false, 'message' => 'Invalid username or password'];
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
    
    /**
     * Logout user
     */
    public function logout() {
        // Record logout
        if (isset($_SESSION['user_id'])) {
            $this->db->query(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$_SESSION['user_id']]
            );
        }
        
        // Destroy session
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
        
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE id = ? AND is_active = 1",
            [$_SESSION['user_id']]
        );
    }
    
    /**
     * Get user role
     */
    public function getUserRole() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $_SESSION['role'] ?? null;
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($role) {
        return $this->getUserRole() === $role;
    }
    
    /**
     * Require login
     */
    public function requireLogin($redirect = 'login.php') {
        if (!$this->isLoggedIn()) {
            header("Location: $redirect");
            exit;
        }
    }
    
    /**
     * Require specific role
     */
    public function requireRole($role, $redirect = 'unauthorized.php') {
        $this->requireLogin();
        
        if (!$this->hasRole($role)) {
            header("Location: $redirect");
            exit;
        }
    }
    
    /**
     * Create user session
     */
    private function createSession($user, $remember = false) {
        // Regenerate session ID
        session_regenerate_id(true);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['tenant_id'] = $user['tenant_id'];
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        
        // Set remember me cookie
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + (30 * 24 * 60 * 60); // 30 days
            
            // Store token in database
            $this->db->insert('user_tokens', [
                'user_id' => $user['id'],
                'token' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', $expiry),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Set cookie
            setcookie('remember_token', $token, $expiry, '/', '', false, true);
        }
    }
    
    /**
     * Check if user is locked out
     */
    private function isLockedOut($username) {
        $recentAttempts = $this->db->count(
            'login_logs',
            "username = ? AND success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
            [$username]
        );
        
        return $recentAttempts >= $this->maxLoginAttempts;
    }
    
    /**
     * Record failed login
     */
    private function recordFailedLogin($username) {
        $this->db->insert('login_logs', [
            'username' => $username,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'success' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Record successful login
     */
    private function recordSuccessfulLogin($user) {
        $this->db->insert('login_logs', [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'success' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Update last login
        $this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
        
        // Clear failed login attempts
        $this->db->query(
            "DELETE FROM login_logs WHERE username = ? AND success = 0",
            [$user['username']]
        );
    }
    
    /**
     * Check if session is expired
     */
    private function isSessionExpired($user) {
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
    
    /**
     * Hash password
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    
    /**
     * Verify password
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate password reset token
     */
    public function generatePasswordResetToken($email) {
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE email = ? AND is_active = 1",
            [$email]
        );
        
        if (!$user) {
            return ['success' => false, 'message' => 'Email not found'];
        }
        
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store reset token
        $this->db->insert('password_resets', [
            'user_id' => $user['id'],
            'token' => hash('sha256', $token),
            'expires_at' => $expiry,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Send email (implement actual email sending)
        // sendPasswordResetEmail($email, $token);
        
        return ['success' => true, 'message' => 'Password reset token sent', 'token' => $token];
    }
    
    /**
     * Reset password
     */
    public function resetPassword($token, $newPassword) {
        $hashedToken = hash('sha256', $token);
        
        $reset = $this->db->fetchOne(
            "SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()",
            [$hashedToken]
        );
        
        if (!$reset) {
            return ['success' => false, 'message' => 'Invalid or expired token'];
        }
        
        // Update password
        $this->db->update('users', [
            'password_hash' => $this->hashPassword($newPassword)
        ], 'id = ?', [$reset['user_id']]);
        
        // Delete reset token
        $this->db->delete('password_resets', 'token = ?', [$hashedToken]);
        
        return ['success' => true, 'message' => 'Password reset successful'];
    }
    
    /**
     * Check remember me token
     */
    public function checkRememberToken() {
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $hashedToken = hash('sha256', $token);
            
            $userToken = $this->db->fetchOne(
                "SELECT ut.*, u.* FROM user_tokens ut 
                 JOIN users u ON ut.user_id = u.id 
                 WHERE ut.token = ? AND ut.expires_at > NOW() AND u.is_active = 1",
                [$hashedToken]
            );
            
            if ($userToken) {
                $this->createSession($userToken, true);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Update user activity
     */
    public function updateActivity() {
        if ($this->isLoggedIn()) {
            $_SESSION['last_activity'] = time();
        }
    }
    
    /**
     * Check session timeout
     */
    public function checkSessionTimeout() {
        if ($this->isLoggedIn()) {
            $lastActivity = $_SESSION['last_activity'] ?? $_SESSION['login_time'] ?? time();
            
            if ((time() - $lastActivity) > SESSION_TIMEOUT) {
                $this->logout();
                return true;
            }
        }
        
        return false;
    }
}

// Global auth instance
function auth() {
    static $instance = null;
    if ($instance === null) {
        $instance = new Auth();
    }
    return $instance;
}

// Convenience functions
function is_logged_in() {
    return auth()->isLoggedIn();
}

function require_login($redirect = 'login.php') {
    return auth()->requireLogin($redirect);
}

function require_role($role, $redirect = 'unauthorized.php') {
    return auth()->requireRole($role, $redirect);
}

function get_current_user() {
    return auth()->getCurrentUser();
}

function get_user_role() {
    return auth()->getUserRole();
}

function has_role($role) {
    return auth()->hasRole($role);
}

// Initialize auth system
auth()->checkRememberToken();
auth()->updateActivity();
auth()->checkSessionTimeout();

// Create missing tables if needed
function initializeAuthTables() {
    $db = db();
    
    $tables = [
        // Login Logs table
        "CREATE TABLE IF NOT EXISTS login_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL DEFAULT 1,
            user_id INT,
            username VARCHAR(50),
            ip_address VARCHAR(45),
            user_agent TEXT,
            success BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_tenant (tenant_id),
            INDEX idx_user (user_id),
            INDEX idx_username (username),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // User Tokens table
        "CREATE TABLE IF NOT EXISTS user_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL DEFAULT 1,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_tenant (tenant_id),
            INDEX idx_user (user_id),
            INDEX idx_token (token),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // Password Resets table
        "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL DEFAULT 1,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_tenant (tenant_id),
            INDEX idx_user (user_id),
            INDEX idx_token (token),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    foreach ($tables as $sql) {
        $db->createTable($sql);
    }
}

// Initialize auth tables
if (!isset($_SESSION['auth_tables_initialized'])) {
    initializeAuthTables();
    $_SESSION['auth_tables_initialized'] = true;
}
?>
