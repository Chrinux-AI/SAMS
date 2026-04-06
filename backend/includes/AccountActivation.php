<?php
/**
 * Secure Account Activation System
 * Handles token verification, OTP delivery, and password creation
 */

class SAMS_AccountActivation {
    private $db;
    private $otpService;
    private $emailService;
    private $config;
    
    public function __construct() {
        $this->db = db();
        $this->otpService = sams_service('otp');
        $this->emailService = sams_service('email');
        $this->config = [
            'otp_length' => 6,
            'otp_expiry' => 600, // 10 minutes
            'token_expiry' => 86400, // 24 hours
            'max_attempts' => 5,
            'cooldown' => 60, // 60 seconds
            'password_min_length' => 8,
            'password_requirements' => [
                'uppercase' => true,
                'lowercase' => true,
                'number' => true,
                'special' => true
            ]
        ];
    }
    
    /**
     * Step 1: Verify activation token and initiate OTP
     */
    public function initiateActivation($token) {
        // Validate token
        $user = $this->validateToken($token);
        
        if (!$user) {
            return [
                'success' => false,
                'error' => 'Invalid or expired activation link',
                'step' => 'token_validation'
            ];
        }
        
        // Check if already activated
        if ($user['status'] === 'active') {
            return [
                'success' => false,
                'error' => 'Account is already activated. Please login.',
                'step' => 'already_activated',
                'redirect' => 'login.php'
            ];
        }
        
        // Generate and send OTP
        $otpResult = $this->otpService->generateOTP($user['email'], 'account_activation');
        
        if (!$otpResult['success']) {
            return [
                'success' => false,
                'error' => $otpResult['error'] ?? 'Failed to generate verification code',
                'retry_after' => $otpResult['retry_after'] ?? null,
                'step' => 'otp_generation'
            ];
        }
        
        // Send OTP email
        $this->sendOTPEmail($user['email'], $otpResult['otp'], $user['full_name'] ?? 'User');
        
        // Log activation step
        $this->logActivationStep($user['id'], 'otp_sent');
        
        return [
            'success' => true,
            'message' => 'Verification code sent to your email',
            'email_masked' => $this->maskEmail($user['email']),
            'step' => 'otp_sent',
            'token' => $token // Return token for next step
        ];
    }
    
    /**
     * Step 2: Verify OTP
     */
    public function verifyOTP($token, $otp) {
        // Validate token again
        $user = $this->validateToken($token);
        
        if (!$user) {
            return [
                'success' => false,
                'error' => 'Invalid or expired session',
                'step' => 'token_validation'
            ];
        }
        
        // Verify OTP
        $verifyResult = $this->otpService->verifyOTP($user['email'], $otp);
        
        if (!$verifyResult['success']) {
            $remaining = $verifyResult['attempts_remaining'] ?? 0;
            
            return [
                'success' => false,
                'error' => $verifyResult['error'],
                'attempts_remaining' => $remaining,
                'step' => 'otp_verification'
            ];
        }
        
        // OTP verified - mark for password creation
        $this->markOTPVerified($user['id']);
        
        // Log success
        $this->logActivationStep($user['id'], 'otp_verified');
        
        return [
            'success' => true,
            'message' => 'Identity verified. Please create your password.',
            'step' => 'otp_verified',
            'token' => $token
        ];
    }
    
    /**
     * Step 3: Create password and activate account
     */
    public function createPassword($token, $password, $passwordConfirm) {
        // Validate token
        $user = $this->validateToken($token);
        
        if (!$user) {
            return [
                'success' => false,
                'error' => 'Invalid or expired session',
                'step' => 'token_validation'
            ];
        }
        
        // Check if OTP was verified
        if (!$this->wasOTPVerified($user['id'])) {
            return [
                'success' => false,
                'error' => 'Please verify your identity with the OTP first',
                'step' => 'otp_required'
            ];
        }
        
        // Validate password match
        if ($password !== $passwordConfirm) {
            return [
                'success' => false,
                'error' => 'Passwords do not match',
                'step' => 'password_match'
            ];
        }
        
        // Validate password strength
        $strengthCheck = $this->validatePasswordStrength($password);
        
        if (!$strengthCheck['valid']) {
            return [
                'success' => false,
                'error' => 'Password does not meet requirements: ' . implode(', ', $strengthCheck['errors']),
                'requirements' => $strengthCheck['requirements'],
                'step' => 'password_strength'
            ];
        }
        
        // Hash password and activate account
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $userId = (int)$user['id'];
        $sql = "UPDATE users SET 
                password_hash = '$passwordHash',
                status = 'active',
                activation_token = NULL,
                activated_at = NOW(),
                updated_at = NOW()
                WHERE id = $userId";
        
        if (!$this->db->query($sql)) {
            return [
                'success' => false,
                'error' => 'Failed to activate account. Please try again.',
                'step' => 'activation_failed'
            ];
        }
        
        // Clear OTP verification record
        $this->clearOTPVerification($user['id']);
        
        // Send welcome email
        $this->sendWelcomeEmail($user['email'], $user['full_name'] ?? 'User', $user['role']);
        
        // Log activation completion
        $this->logActivationStep($user['id'], 'account_activated');
        
        return [
            'success' => true,
            'message' => 'Account activated successfully!',
            'step' => 'activated',
            'redirect' => 'login.php?activated=1',
            'role' => $user['role']
        ];
    }
    
    /**
     * Resend OTP
     */
    public function resendOTP($token) {
        $user = $this->validateToken($token);
        
        if (!$user) {
            return [
                'success' => false,
                'error' => 'Invalid session'
            ];
        }
        
        $result = $this->otpService->resendOTP($user['email'], 'account_activation');
        
        if ($result['success']) {
            $this->sendOTPEmail($user['email'], $result['otp'], $user['full_name'] ?? 'User');
        }
        
        return $result;
    }
    
    /**
     * Validate activation token
     */
    private function validateToken($token) {
        $token = mysqli_real_escape_string($this->db, $token);
        
        $sql = "SELECT u.*, 
                COALESCE(s.full_name, t.full_name, p.full_name, u.email) as full_name
                FROM users u
                LEFT JOIN students s ON u.id = s.user_id
                LEFT JOIN teachers t ON u.id = t.user_id
                LEFT JOIN parents p ON u.id = p.user_id
                WHERE u.activation_token = '$token'
                AND u.status = 'pending_activation'
                AND u.created_at > DATE_SUB(NOW(), INTERVAL {$this->config['token_expiry']} SECOND)
                LIMIT 1";
        
        $result = $this->db->query($sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }
        
        return null;
    }
    
    /**
     * Validate password strength
     */
    private function validatePasswordStrength($password) {
        $errors = [];
        $requirements = [];
        
        // Minimum length
        if (strlen($password) < $this->config['password_min_length']) {
            $errors[] = "At least {$this->config['password_min_length']} characters";
        }
        $requirements[] = "{$this->config['password_min_length']}+ characters";
        
        // Uppercase
        if ($this->config['password_requirements']['uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "At least one uppercase letter";
        }
        if ($this->config['password_requirements']['uppercase']) {
            $requirements[] = "One uppercase letter";
        }
        
        // Lowercase
        if ($this->config['password_requirements']['lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = "At least one lowercase letter";
        }
        if ($this->config['password_requirements']['lowercase']) {
            $requirements[] = "One lowercase letter";
        }
        
        // Number
        if ($this->config['password_requirements']['number'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = "At least one number";
        }
        if ($this->config['password_requirements']['number']) {
            $requirements[] = "One number";
        }
        
        // Special character
        if ($this->config['password_requirements']['special'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "At least one special character";
        }
        if ($this->config['password_requirements']['special']) {
            $requirements[] = "One special character (!@#$%^&*)";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'requirements' => $requirements
        ];
    }
    
    /**
     * Mark OTP as verified for user
     */
    private function markOTPVerified($userId) {
        $userId = (int)$userId;
        
        // Store in session or temporary table
        $_SESSION['otp_verified_user_' . $userId] = true;
        $_SESSION['otp_verified_time_' . $userId] = time();
        
        // Also store in database for persistence
        $this->db->query("UPDATE users SET 
            otp_verified = 1, 
            otp_verified_at = NOW() 
            WHERE id = $userId");
    }
    
    /**
     * Check if OTP was verified
     */
    private function wasOTPVerified($userId) {
        $userId = (int)$userId;
        
        // Check session first
        if (isset($_SESSION['otp_verified_user_' . $userId]) && 
            $_SESSION['otp_verified_user_' . $userId] === true) {
            // Check if not expired (10 minutes)
            $verifiedTime = $_SESSION['otp_verified_time_' . $userId] ?? 0;
            if (time() - $verifiedTime < 600) {
                return true;
            }
        }
        
        // Check database
        $result = $this->db->query("SELECT otp_verified, otp_verified_at 
            FROM users 
            WHERE id = $userId 
            AND otp_verified = 1
            AND otp_verified_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            LIMIT 1");
        
        return $result && mysqli_num_rows($result) > 0;
    }
    
    /**
     * Clear OTP verification
     */
    private function clearOTPVerification($userId) {
        $userId = (int)$userId;
        
        unset($_SESSION['otp_verified_user_' . $userId]);
        unset($_SESSION['otp_verified_time_' . $userId]);
        
        $this->db->query("UPDATE users SET otp_verified = 0, otp_verified_at = NULL WHERE id = $userId");
    }
    
    /**
     * Send OTP email
     */
    private function sendOTPEmail($email, $otp, $name) {
        $subject = "Your SAMS Account Verification Code";
        
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #4F46E5;'>Verify Your Identity</h2>
                
                <p>Hello $name,</p>
                
                <p>You requested to activate your SAMS account. Please use the following verification code:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <div style='background: #f5f5f5; padding: 20px; font-size: 32px; 
                                letter-spacing: 8px; font-weight: bold; color: #4F46E5;'>
                        $otp
                    </div>
                </div>
                
                <p><strong>This code will expire in 10 minutes.</strong></p>
                
                <p>If you did not request this, please ignore this email or contact support.</p>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                <p style='font-size: 12px; color: #666;'>
                    This is an automated message from SAMS. Please do not reply to this email.
                </p>
            </div>
        </body>
        </html>
        ";
        
        return $this->emailService->send($email, $subject, $body);
    }
    
    /**
     * Send welcome email
     */
    private function sendWelcomeEmail($email, $name, $role) {
        $roleDisplay = ucfirst($role);
        
        $subject = "Welcome to SAMS - Account Activated";
        
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #4F46E5;'>Welcome to SAMS!</h2>
                
                <p>Hello $name,</p>
                
                <p>Your SAMS account has been successfully activated!</p>
                
                <p><strong>Account Details:</strong></p>
                <ul>
                    <li>Email: $email</li>
                    <li>Role: $roleDisplay</li>
                </ul>
                
                <p>You can now log in to your account using your email and the password you just created.</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$this->getBaseUrl()}/login.php' 
                       style='background: #4F46E5; color: white; padding: 12px 30px; 
                              text-decoration: none; border-radius: 5px; display: inline-block;'>
                        Log In Now
                    </a>
                </div>
                
                <p>If you have any questions, please contact your system administrator.</p>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                <p style='font-size: 12px; color: #666;'>
                    This is an automated message from SAMS. Please do not reply to this email.
                </p>
            </div>
        </body>
        </html>
        ";
        
        return $this->emailService->send($email, $subject, $body);
    }
    
    /**
     * Log activation step
     */
    private function logActivationStep($userId, $step) {
        $userId = (int)$userId;
        $step = mysqli_real_escape_string($this->db, $step);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $this->db->query("INSERT INTO activation_logs 
            (user_id, step, ip_address, created_at) 
            VALUES ($userId, '$step', '$ip', NOW())");
    }
    
    /**
     * Mask email for display
     */
    private function maskEmail($email) {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;
        
        $local = $parts[0];
        $domain = $parts[1];
        
        $maskedLocal = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 4)) . substr($local, -2);
        $maskedDomain = substr($domain, 0, 1) . str_repeat('*', max(0, strpos($domain, '.') - 1)) . substr($domain, strpos($domain, '.'));
        
        return $maskedLocal . '@' . $maskedDomain;
    }
    
    /**
     * Get base URL
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "$protocol://$host";
    }
    
    /**
     * Get activation statistics
     */
    public function getStatistics($days = 30) {
        $days = (int)$days;
        
        $stats = [
            'initiated' => 0,
            'otp_verified' => 0,
            'completed' => 0,
            'expired' => 0
        ];
        
        $result = $this->db->query("SELECT 
            COUNT(*) as total,
            status
            FROM users 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL $days DAY)
            AND activation_token IS NOT NULL
            GROUP BY status");
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                switch ($row['status']) {
                    case 'pending_activation':
                        $stats['initiated'] += $row['total'];
                        break;
                    case 'active':
                        $stats['completed'] += $row['total'];
                        break;
                }
            }
        }
        
        // Count expired
        $expiredResult = $this->db->query("SELECT COUNT(*) as total FROM users 
            WHERE status = 'pending_activation'
            AND created_at < DATE_SUB(NOW(), INTERVAL {$this->config['token_expiry']} SECOND)");
        
        if ($expiredResult) {
            $stats['expired'] = (int)mysqli_fetch_assoc($expiredResult)['total'];
        }
        
        return $stats;
    }
}
