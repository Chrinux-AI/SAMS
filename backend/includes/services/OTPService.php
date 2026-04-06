<?php
/**
 * OTP Service
 * Handles OTP generation, verification, and lifecycle management
 */

class SAMS_OTPService extends SAMS_BaseService {
    
    private $otpLength;
    private $otpExpiry;
    private $cooldown;
    private $maxAttempts;
    private $maxRequests;
    
    public function __construct($container) {
        parent::__construct($container);
        $this->otpLength = $container->config('security.otp_length', 6);
        $this->otpExpiry = $container->config('security.otp_expiry', 900);
        $this->cooldown = $container->config('security.otp_cooldown', 60);
        $this->maxAttempts = $container->config('security.max_otp_attempts', 3);
        $this->maxRequests = $container->config('security.max_otp_requests', 5);
    }
    
    /**
     * Generate and send OTP
     */
    public function generateOTP($email, $purpose = 'verification') {
        $email = strtolower(trim($email));
        
        // Check rate limiting
        $rateCheck = $this->checkRateLimit($email);
        if (!$rateCheck['allowed']) {
            return [
                'success' => false,
                'error' => $rateCheck['message'],
                'retry_after' => $rateCheck['retry_after']
            ];
        }
        
        // Check cooldown
        $cooldownCheck = $this->checkCooldown($email);
        if (!$cooldownCheck['allowed']) {
            return [
                'success' => false,
                'error' => 'Please wait before requesting another code',
                'retry_after' => $cooldownCheck['remaining']
            ];
        }
        
        // Generate OTP
        $otp = $this->generateSecureOTP();
        $token = $this->generateToken();
        
        // Store OTP
        $stored = $this->storeOTP($email, $otp, $token, $purpose);
        
        if (!$stored) {
            return ['success' => false, 'error' => 'Failed to generate code'];
        }
        
        // Log generation
        $this->log('OTP_GENERATED', [
            'email' => $email,
            'purpose' => $purpose
        ]);
        
        return [
            'success' => true,
            'otp' => $otp, // Only for testing - remove in production
            'token' => $token,
            'expires_in' => $this->otpExpiry
        ];
    }
    
    /**
     * Verify OTP
     */
    public function verifyOTP($email, $otp, $token = null) {
        $email = strtolower(trim($email));
        
        // Get stored OTP
        $stored = $this->getStoredOTP($email, $token);
        
        if (!$stored) {
            $this->log('OTP_VERIFY_FAILED', [
                'email' => $email,
                'reason' => 'not_found'
            ]);
            return ['success' => false, 'error' => 'Invalid or expired code'];
        }
        
        // Check if expired
        if (strtotime($stored['expires_at']) < time()) {
            $this->invalidateOTP($stored['id']);
            $this->log('OTP_VERIFY_FAILED', [
                'email' => $email,
                'reason' => 'expired'
            ]);
            return ['success' => false, 'error' => 'Code has expired'];
        }
        
        // Check attempts
        if ($stored['attempts'] >= $this->maxAttempts) {
            $this->invalidateOTP($stored['id']);
            $this->log('OTP_VERIFY_FAILED', [
                'email' => $email,
                'reason' => 'max_attempts'
            ]);
            return ['success' => false, 'error' => 'Too many failed attempts'];
        }
        
        // Verify code
        if (!hash_equals($stored['otp_hash'], hash('sha256', $otp))) {
            $this->incrementAttempts($stored['id']);
            $remaining = $this->maxAttempts - ($stored['attempts'] + 1);
            
            $this->log('OTP_VERIFY_FAILED', [
                'email' => $email,
                'reason' => 'invalid_code',
                'attempts_remaining' => $remaining
            ]);
            
            return [
                'success' => false,
                'error' => 'Invalid code',
                'attempts_remaining' => max(0, $remaining)
            ];
        }
        
        // Success - invalidate OTP
        $this->invalidateOTP($stored['id']);
        
        $this->log('OTP_VERIFY_SUCCESS', [
            'email' => $email,
            'purpose' => $stored['purpose']
        ]);
        
        return [
            'success' => true,
            'email' => $email,
            'purpose' => $stored['purpose']
        ];
    }
    
    /**
     * Resend OTP
     */
    public function resendOTP($email, $purpose = 'verification') {
        $email = strtolower(trim($email));
        
        // Check if existing valid OTP
        $existing = $this->getStoredOTP($email);
        
        if ($existing && strtotime($existing['expires_at']) > time()) {
            // Check cooldown
            $cooldownCheck = $this->checkCooldown($email);
            if (!$cooldownCheck['allowed']) {
                return [
                    'success' => false,
                    'error' => 'Please wait before requesting another code',
                    'retry_after' => $cooldownCheck['remaining']
                ];
            }
            
            // Extend expiry and resend same OTP
            $this->extendOTPExpiry($existing['id']);
            
            $this->log('OTP_RESENT', [
                'email' => $email,
                'purpose' => $purpose
            ]);
            
            return [
                'success' => true,
                'message' => 'Code resent successfully',
                'expires_in' => $this->otpExpiry
            ];
        }
        
        // Generate new OTP
        return $this->generateOTP($email, $purpose);
    }
    
    /**
     * Generate cryptographically secure OTP
     */
    private function generateSecureOTP() {
        $min = pow(10, $this->otpLength - 1);
        $max = pow(10, $this->otpLength) - 1;
        
        return str_pad(random_int($min, $max), $this->otpLength, '0', STR_PAD_LEFT);
    }
    
    /**
     * Generate verification token
     */
    private function generateToken() {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Store OTP in database
     */
    private function storeOTP($email, $otp, $token, $purpose) {
        $email = mysqli_real_escape_string($this->db, $email);
        $otpHash = hash('sha256', $otp);
        $tokenHash = hash('sha256', $token);
        $purpose = mysqli_real_escape_string($this->db, $purpose);
        $expires = date('Y-m-d H:i:s', time() + $this->otpExpiry);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $sql = "INSERT INTO otp_requests 
                (email, otp_hash, token_hash, purpose, expires_at, ip_address, created_at) 
                VALUES ('$email', '$otpHash', '$tokenHash', '$purpose', '$expires', '$ip', NOW())";
        
        return $this->db->query($sql);
    }
    
    /**
     * Get stored OTP
     */
    private function getStoredOTP($email, $token = null) {
        $email = mysqli_real_escape_string($this->db, $email);
        
        $sql = "SELECT * FROM otp_requests 
                WHERE email = '$email' 
                AND used = 0 
                AND expires_at > NOW() 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $result = $this->db->query($sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }
        
        return null;
    }
    
    /**
     * Check rate limiting
     */
    private function checkRateLimit($email) {
        $email = mysqli_real_escape_string($this->db, $email);
        
        $sql = "SELECT COUNT(*) as count FROM otp_requests 
                WHERE email = '$email' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        
        $result = $this->db->query($sql);
        $count = 0;
        
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $count = (int)$row['count'];
        }
        
        if ($count >= $this->maxRequests) {
            return [
                'allowed' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => 3600 // 1 hour
            ];
        }
        
        return ['allowed' => true];
    }
    
    /**
     * Check cooldown period
     */
    private function checkCooldown($email) {
        $email = mysqli_real_escape_string($this->db, $email);
        
        $sql = "SELECT created_at FROM otp_requests 
                WHERE email = '$email' 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $result = $this->db->query($sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $lastRequest = strtotime($row['created_at']);
            $elapsed = time() - $lastRequest;
            
            if ($elapsed < $this->cooldown) {
                return [
                    'allowed' => false,
                    'remaining' => $this->cooldown - $elapsed
                ];
            }
        }
        
        return ['allowed' => true, 'remaining' => 0];
    }
    
    /**
     * Increment attempt count
     */
    private function incrementAttempts($otpId) {
        $otpId = (int)$otpId;
        $this->db->query("UPDATE otp_requests SET attempts = attempts + 1 WHERE id = $otpId");
    }
    
    /**
     * Invalidate OTP
     */
    private function invalidateOTP($otpId) {
        $otpId = (int)$otpId;
        $this->db->query("UPDATE otp_requests SET used = 1 WHERE id = $otpId");
    }
    
    /**
     * Extend OTP expiry
     */
    private function extendOTPExpiry($otpId) {
        $otpId = (int)$otpId;
        $newExpiry = date('Y-m-d H:i:s', time() + $this->otpExpiry);
        $this->db->query("UPDATE otp_requests SET expires_at = '$newExpiry' WHERE id = $otpId");
    }
    
    /**
     * Clean up expired OTPs
     */
    public function cleanupExpired() {
        $this->db->query("DELETE FROM otp_requests WHERE expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        return $this->db->affected_rows;
    }
    
    /**
     * Get OTP statistics
     */
    public function getStats($email = null) {
        $where = '';
        if ($email) {
            $email = mysqli_real_escape_string($this->db, strtolower(trim($email)));
            $where = "WHERE email = '$email'";
        }
        
        $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN used = 1 THEN 1 ELSE 0 END) as used,
                SUM(CASE WHEN used = 0 AND expires_at > NOW() THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN used = 0 AND expires_at <= NOW() THEN 1 ELSE 0 END) as expired
                FROM otp_requests $where";
        
        $result = $this->db->query($sql);
        
        if ($result) {
            return mysqli_fetch_assoc($result);
        }
        
        return ['total' => 0, 'used' => 0, 'active' => 0, 'expired' => 0];
    }
}
