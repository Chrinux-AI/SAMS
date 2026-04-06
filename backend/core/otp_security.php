<?php
/**
 * SAMS Core OTP Security
 * One-Time Password verification system
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';

class OTPSecurity {
    private $db;
    private $otpLength;
    private $otpExpiry;
    
    public function __construct() {
        $this->db = db();
        $this->otpLength = OTP_LENGTH;
        $this->otpExpiry = OTP_EXPIRY;
    }
    
    /**
     * Generate and send OTP
     */
    public function generateOTP($userId, $purpose = 'general') {
        // Clean old OTPs
        $this->cleanOldOTPs($userId);
        
        // Generate new OTP
        $otp = $this->generateOTPCode();
        $hashedOTP = hash('sha256', $otp);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->otpExpiry);
        
        // Store OTP
        $this->db->insert('otp_codes', [
            'user_id' => $userId,
            'otp_code' => $hashedOTP,
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Get user email
        $user = $this->db->fetchOne("SELECT email FROM users WHERE id = ?", [$userId]);
        
        if ($user) {
            // Send OTP via email
            $this->sendOTPEmail($user['email'], $otp, $purpose);
            
            // Log OTP generation
            log_system_action('otp_generated', [
                'user_id' => $userId,
                'purpose' => $purpose,
                'expires_at' => $expiresAt
            ]);
            
            return [
                'success' => true,
                'message' => 'OTP sent to your email',
                'expires_at' => $expiresAt
            ];
        }
        
        return ['success' => false, 'message' => 'User not found'];
    }
    
    /**
     * Verify OTP
     */
    public function verifyOTP($userId, $otp, $purpose = 'general') {
        $hashedOTP = hash('sha256', $otp);
        
        $validOTP = $this->db->fetchOne(
            "SELECT * FROM otp_codes 
             WHERE user_id = ? AND otp_code = ? AND purpose = ? AND expires_at > NOW() AND used = 0",
            [$userId, $hashedOTP, $purpose]
        );
        
        if (!$validOTP) {
            // Log failed verification
            log_system_action('otp_verification_failed', [
                'user_id' => $userId,
                'purpose' => $purpose
            ]);
            
            return ['success' => false, 'message' => 'Invalid or expired OTP'];
        }
        
        // Mark OTP as used
        $this->db->update('otp_codes', [
            'used' => 1,
            'used_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$validOTP['id']]);
        
        // Log successful verification
        log_system_action('otp_verified', [
            'user_id' => $userId,
            'purpose' => $purpose
        ]);
        
        return ['success' => true, 'message' => 'OTP verified successfully'];
    }
    
    /**
     * Require OTP verification for sensitive actions
     */
    public function requireOTP($purpose, $redirect = 'verify-otp.php') {
        if (!isset($_SESSION['otp_verified'][$purpose]) || !$_SESSION['otp_verified'][$purpose]) {
            $_SESSION['otp_purpose'] = $purpose;
            $_SESSION['otp_redirect'] = $_SERVER['REQUEST_URI'];
            header("Location: $redirect");
            exit;
        }
    }
    
    /**
     * Mark OTP as verified for session
     */
    public function markOTPVerified($purpose) {
        $_SESSION['otp_verified'][$purpose] = true;
        $_SESSION['otp_verified_time'][$purpose] = time();
    }
    
    /**
     * Check if OTP is verified for session
     */
    public function isOTPVerified($purpose, $timeout = 300) {
        if (!isset($_SESSION['otp_verified'][$purpose]) || !$_SESSION['otp_verified'][$purpose]) {
            return false;
        }
        
        // Check timeout
        if (isset($_SESSION['otp_verified_time'][$purpose])) {
            $elapsed = time() - $_SESSION['otp_verified_time'][$purpose];
            if ($elapsed > $timeout) {
                unset($_SESSION['otp_verified'][$purpose]);
                unset($_SESSION['otp_verified_time'][$purpose]);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Clear OTP verification for session
     */
    public function clearOTPVerification($purpose = null) {
        if ($purpose) {
            unset($_SESSION['otp_verified'][$purpose]);
            unset($_SESSION['otp_verified_time'][$purpose]);
        } else {
            unset($_SESSION['otp_verified']);
            unset($_SESSION['otp_verified_time']);
        }
    }
    
    /**
     * Generate OTP code
     */
    private function generateOTPCode() {
        return str_pad(random_int(0, pow(10, $this->otpLength) - 1), $this->otpLength, '0', STR_PAD_LEFT);
    }
    
    /**
     * Send OTP via email
     */
    private function sendOTPEmail($email, $otp, $purpose) {
        $subject = 'SAMS - OTP Verification';
        
        $message = "Your OTP for $purpose is: $otp\n";
        $message .= "This OTP will expire in " . ($this->otpExpiry / 60) . " minutes.\n";
        $message .= "If you didn't request this OTP, please contact support.\n";
        
        // In production, use actual email sending
        // mail($email, $subject, $message);
        
        // For now, just log it
        error_log("OTP Email: To: $email, OTP: $otp, Purpose: $purpose");
        
        return true;
    }
    
    /**
     * Clean old OTPs
     */
    private function cleanOldOTPs($userId = null) {
        $where = "expires_at < NOW() OR used = 1";
        $params = [];
        
        if ($userId) {
            $where .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        return $this->db->delete('otp_codes', $where, $params);
    }
    
    /**
     * Get OTP statistics
     */
    public function getOTPStatistics($hours = 24) {
        $sql = "
            SELECT 
                COUNT(*) as total_generated,
                COUNT(CASE WHEN used = 1 THEN 1 END) as total_used,
                COUNT(CASE WHEN used = 0 AND expires_at > NOW() THEN 1 END) as pending,
                COUNT(CASE WHEN used = 0 AND expires_at <= NOW() THEN 1 END) as expired,
                COUNT(DISTINCT user_id) as unique_users
            FROM otp_codes
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ";
        
        return $this->db->fetchOne($sql, [$hours]);
    }
    
    /**
     * Get user OTP history
     */
    public function getUserOTPHistory($userId, $limit = 50) {
        $sql = "
            SELECT purpose, created_at, expires_at, used, used_at
            FROM otp_codes
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ";
        
        return $this->db->fetchAll($sql, [$userId, $limit]);
    }
    
    /**
     * Check for OTP abuse
     */
    public function checkOTPAbuse($userId, $timeframe = 3600, $maxAttempts = 5) {
        $recentOTPs = $this->db->count(
            'otp_codes',
            "user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$userId, $timeframe]
        );
        
        return $recentOTPs >= $maxAttempts;
    }
    
    /**
     * Block user from OTP generation temporarily
     */
    public function blockOTPGeneration($userId, $minutes = 15) {
        $this->db->insert('otp_blocks', [
            'user_id' => $userId,
            'blocked_until' => date('Y-m-d H:i:s', time() + ($minutes * 60)),
            'reason' => 'Too many OTP requests',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        log_system_action('otp_blocked', [
            'user_id' => $userId,
            'blocked_until' => date('Y-m-d H:i:s', time() + ($minutes * 60))
        ]);
    }
    
    /**
     * Check if user is blocked from OTP generation
     */
    public function isOTPBlocked($userId) {
        $block = $this->db->fetchOne(
            "SELECT * FROM otp_blocks WHERE user_id = ? AND blocked_until > NOW()",
            [$userId]
        );
        
        return !empty($block);
    }
    
    /**
     * Resend OTP
     */
    public function resendOTP($userId, $purpose = 'general') {
        // Check if user is blocked
        if ($this->isOTPBlocked($userId)) {
            return ['success' => false, 'message' => 'OTP generation temporarily blocked due to too many requests'];
        }
        
        // Check for abuse
        if ($this->checkOTPAbuse($userId)) {
            $this->blockOTPGeneration($userId);
            return ['success' => false, 'message' => 'Too many OTP requests. Please try again later.'];
        }
        
        // Generate new OTP
        return $this->generateOTP($userId, $purpose);
    }
}

// Global OTP security instance
function otp_security() {
    static $instance = null;
    if ($instance === null) {
        $instance = new OTPSecurity();
    }
    return $instance;
}

// Convenience functions
function generate_otp($userId, $purpose = 'general') {
    return otp_security()->generateOTP($userId, $purpose);
}

function verify_otp($userId, $otp, $purpose = 'general') {
    return otp_security()->verifyOTP($userId, $otp, $purpose);
}

function require_otp($purpose, $redirect = 'verify-otp.php') {
    return otp_security()->requireOTP($purpose, $redirect);
}

function mark_otp_verified($purpose) {
    return otp_security()->markOTPVerified($purpose);
}

function is_otp_verified($purpose, $timeout = 300) {
    return otp_security()->isOTPVerified($purpose, $timeout);
}

// Auto-load OTP security
$GLOBALS['otp_security'] = otp_security();

// Create OTP tables if needed
function initializeOTPTables() {
    $db = db();
    
    $tables = [
        // OTP Codes table
        "CREATE TABLE IF NOT EXISTS otp_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL DEFAULT 1,
            user_id INT NOT NULL,
            otp_code VARCHAR(64) NOT NULL,
            purpose VARCHAR(50) DEFAULT 'general',
            expires_at DATETIME NOT NULL,
            used BOOLEAN DEFAULT 0,
            used_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_tenant (tenant_id),
            INDEX idx_user (user_id),
            INDEX idx_code (otp_code),
            INDEX idx_expires (expires_at),
            INDEX idx_purpose (purpose)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        // OTP Blocks table
        "CREATE TABLE IF NOT EXISTS otp_blocks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL DEFAULT 1,
            user_id INT NOT NULL,
            blocked_until DATETIME NOT NULL,
            reason TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_tenant (tenant_id),
            INDEX idx_user (user_id),
            INDEX idx_blocked_until (blocked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    
    foreach ($tables as $sql) {
        $db->createTable($sql);
    }
}

// Initialize OTP tables
if (!isset($_SESSION['otp_tables_initialized'])) {
    initializeOTPTables();
    $_SESSION['otp_tables_initialized'] = true;
}
?>
