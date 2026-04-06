<?php

/**
 * SAMS Core Configuration
 * School Attendance Management System
 * Version: 2.0.0
 */

// Database Configuration (guard against redefinition when includes/config.php is also loaded)
if (!defined('DB_HOST'))    define('DB_HOST', 'localhost');
if (!defined('DB_NAME'))    define('DB_NAME', 'attendance_system');
if (!defined('DB_USER'))    define('DB_USER', 'root');
if (!defined('DB_PASS'))    define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// System Configuration
if (!defined('SAMS_VERSION')) define('SAMS_VERSION', '2.0.0');
if (!defined('BASE_URL'))     define('BASE_URL', 'http://localhost/attendance');
if (!defined('UPLOAD_PATH'))  define('UPLOAD_PATH', __DIR__ . '/../uploads/');
if (!defined('AI_PATH'))      define('AI_PATH', __DIR__ . '/../ai/');

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
}

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Never display to end users

// Timezone
date_default_timezone_set('UTC');

// Security Settings
if (!defined('CSRF_TOKEN_LENGTH')) define('CSRF_TOKEN_LENGTH', 32);
if (!defined('OTP_LENGTH'))        define('OTP_LENGTH', 6);
if (!defined('OTP_EXPIRY'))        define('OTP_EXPIRY', 300);

// AI Configuration
if (!defined('AI_SCAN_INTERVAL')) define('AI_SCAN_INTERVAL', 3600);
if (!defined('AI_BATCH_SIZE'))    define('AI_BATCH_SIZE', 100);

// Email Configuration
if (!defined('SMTP_HOST')) define('SMTP_HOST', 'localhost');
if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
if (!defined('SMTP_USER')) define('SMTP_USER', '');
if (!defined('SMTP_PASS')) define('SMTP_PASS', '');

// System Limits
if (!defined('MAX_FILE_SIZE'))      define('MAX_FILE_SIZE', 5242880);
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('SESSION_TIMEOUT'))    define('SESSION_TIMEOUT', 7200);

// Multi-tenant Support
if (!defined('DEFAULT_TENANT_ID')) define('DEFAULT_TENANT_ID', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Global database connection
if (!isset($GLOBALS['db'])) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        $GLOBALS['db'] = $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection failed. Please contact administrator.");
    }
}

// Helper function: raw PDO via $GLOBALS['db'] — will be overridden by core/database.php
// with a full Database wrapper that adds fetchOne(), count(), insert(), etc.

// Generate CSRF token
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        }
        return $_SESSION['csrf_token'];
    }
}

// Verify CSRF token
if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Generate OTP
if (!function_exists('generateOTP')) {
    function generateOTP()
    {
        return str_pad(random_int(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);
    }
}

// Send OTP via email
if (!function_exists('sendOTP')) {
    function sendOTP($email, $otp)
    {
        error_log("OTP for $email: $otp");
        return true;
    }
}

// Log system event
if (!function_exists('logEvent')) {
    function logEvent($level, $message, $context = [])
    {
        $logEntry = date('Y-m-d H:i:s') . " [$level] $message";
        if (!empty($context)) {
            $logEntry .= " Context: " . json_encode($context);
        }
        error_log($logEntry);
    }
}

// Clean input
if (!function_exists('cleanInput')) {
    function cleanInput($data)
    {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

// Validate email
if (!function_exists('validateEmail')) {
    function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

// Get current tenant ID
if (!function_exists('getTenantId')) {
    function getTenantId()
    {
        return $_SESSION['tenant_id'] ?? DEFAULT_TENANT_ID;
    }
}

// Check if system is in maintenance mode
if (!function_exists('isMaintenanceMode')) {
    function isMaintenanceMode()
    {
        return file_exists(__DIR__ . '/../maintenance.mode');
    }
}

// Get system info
function getSystemInfo()
{
    return [
        'version' => SAMS_VERSION,
        'php_version' => PHP_VERSION,
        'mysql_version' => db()->query("SELECT VERSION() as version")->fetchColumn(),
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'timestamp' => date('Y-m-d H:i:s')
    ];
}
