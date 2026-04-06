<?php
/**
 * Authentication Service
 * Handles user authentication, session management, and security
 */

class SAMS_AuthService extends SAMS_BaseService {

    /**
     * Authenticate user with credentials
     */
    public function authenticate($email, $password, $remember = false) {
        // Validate inputs
        if (empty($email) || empty($password)) {
            return ['success' => false, 'error' => 'Email and password required'];
        }

        // Get user by email
        $user = $this->getUserByEmail($email);

        if (!$user) {
            $this->log('LOGIN_FAILED', ['email' => $email, 'reason' => 'user_not_found']);
            return ['success' => false, 'error' => 'Invalid credentials'];
        }

        // Check if account is active
        if ($user['status'] !== 'active') {
            $this->log('LOGIN_FAILED', ['email' => $email, 'reason' => 'account_inactive']);
            return ['success' => false, 'error' => 'Account not activated'];
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $this->log('LOGIN_FAILED', ['email' => $email, 'reason' => 'invalid_password']);
            $this->incrementFailedAttempts($user['id']);
            return ['success' => false, 'error' => 'Invalid credentials'];
        }

        // Check for account lockout
        if ($this->isAccountLocked($user['id'])) {
            return ['success' => false, 'error' => 'Account temporarily locked'];
        }

        // Create session
        $this->createSession($user, $remember);

        // Update last login
        $this->updateLastLogin($user['id']);

        // Clear failed attempts
        $this->clearFailedAttempts($user['id']);

        // Log success
        $this->log('LOGIN_SUCCESS', ['user_id' => $user['id'], 'role' => $user['role']]);

        return [
            'success' => true,
            'user' => $this->sanitizeUserData($user),
            'redirect' => $this->getRoleDashboard($user['role'])
        ];
    }

    /**
     * Create user session
     */
    private function createSession($user, $remember = false) {
        // Regenerate session ID for security
        session_regenerate_id(true);

        // Set session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['tenant_id'] = $user['tenant_id'] ?? 1;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        // Set remember me cookie if requested
        if ($remember) {
            $token = $this->generateRememberToken($user['id']);
            setcookie('remember_me', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
        }
    }

    /**
     * Verify session validity
     */
    public function verifySession() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        // Check session timeout
        $timeout = $this->container->config('security.session_timeout', 1800);
        if (time() - $_SESSION['last_activity'] > $timeout) {
            $this->logout();
            return false;
        }

        // Update last activity
        $_SESSION['last_activity'] = time();

        return true;
    }

    /**
     * Logout user
     */
    public function logout() {
        $userId = $_SESSION['user_id'] ?? null;

        // Log the logout
        if ($userId) {
            $this->log('LOGOUT', ['user_id' => $userId]);
        }

        // Clear remember me cookie
        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', time() - 3600, '/');
            $this->clearRememberToken($_COOKIE['remember_me']);
        }

        // Destroy session
        session_destroy();
        $_SESSION = [];

        return ['success' => true];
    }

    /**
     * Check if user has required role
     */
    public function hasRole($requiredRoles) {
        if (!is_array($requiredRoles)) {
            $requiredRoles = [$requiredRoles];
        }

        $userRole = $_SESSION['role'] ?? '';
        return in_array($userRole, $requiredRoles);
    }

    /**
     * Enforce role access
     */
    public function requireRole($roles, $redirect = 'login.php') {
        if (!$this->hasRole($roles)) {
            header("Location: $redirect?error=unauthorized");
            exit;
        }
    }

    /**
     * Get current user
     */
    public function getCurrentUser() {
        if (!$this->verifySession()) {
            return null;
        }

        return $this->getUserById($_SESSION['user_id']);
    }

    /**
     * Get user by email
     */
    private function getUserByEmail($email) {
        $email = strtolower(trim($email));
        $escapedEmail = mysqli_real_escape_string($this->db, $email);

        $result = $this->db->query("SELECT * FROM users WHERE email = '$escapedEmail' LIMIT 1");

        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }

        return null;
    }

    /**
     * Get user by ID
     */
    private function getUserById($id) {
        $id = (int)$id;

        $result = $this->db->query("SELECT id, email, role, status, tenant_id, created_at FROM users WHERE id = $id LIMIT 1");

        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }

        return null;
    }

    /**
     * Update last login timestamp
     */
    private function updateLastLogin($userId) {
        $userId = (int)$userId;
        $this->db->query("UPDATE users SET last_login = NOW() WHERE id = $userId");
    }

    /**
     * Increment failed login attempts
     */
    private function incrementFailedAttempts($userId) {
        $userId = (int)$userId;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $this->db->query("INSERT INTO login_attempts (user_id, ip_address, attempted_at) VALUES ($userId, '$ip', NOW())");
    }

    /**
     * Clear failed attempts on successful login
     */
    private function clearFailedAttempts($userId) {
        $userId = (int)$userId;
        $this->db->query("DELETE FROM login_attempts WHERE user_id = $userId AND attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    }

    /**
     * Check if account is locked
     */
    private function isAccountLocked($userId) {
        $userId = (int)$userId;

        $result = $this->db->query("SELECT COUNT(*) as count FROM login_attempts WHERE user_id = $userId AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");

        if ($result) {
            $row = mysqli_fetch_assoc($result);
            return $row['count'] >= 5; // Lock after 5 failed attempts
        }

        return false;
    }

    /**
     * Generate remember me token
     */
    private function generateRememberToken($userId) {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        $userId = (int)$userId;
        $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));

        $this->db->query("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES ($userId, '$hash', '$expires')");

        return $token;
    }

    /**
     * Clear remember token
     */
    private function clearRememberToken($token) {
        $hash = hash('sha256', $token);
        $this->db->query("DELETE FROM remember_tokens WHERE token_hash = '$hash'");
    }

    /**
     * Sanitize user data for output
     */
    private function sanitizeUserData($user) {
        unset($user['password_hash']);
        unset($user['remember_token']);
        return $user;
    }

    /**
     * Get dashboard URL for role
     */
    private function getRoleDashboard($role) {
        $dashboards = [
            'admin' => 'admin/index.php',
            'super_admin' => 'admin/index.php',
            'teacher' => 'teacher/index.php',
            'student' => 'student/index.php',
            'parent' => 'parent/index.php',
            'accountant' => 'accountant/index.php',
            'bursar' => 'bursar/index.php',
            'librarian' => 'librarian/index.php'
        ];

        return $dashboards[$role] ?? 'index.php';
    }
}
