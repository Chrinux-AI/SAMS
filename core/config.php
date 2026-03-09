<?php
/**
 * SAMS Core Configuration
 * School Attendance Management System
 * Version: 2.0.0
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'sams_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// System Configuration
define('SAMS_VERSION', '2.0.0');
define('BASE_URL', 'http://localhost/attendance');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('AI_PATH', __DIR__ . '/../ai/');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1); // Set to 0 in production

// Timezone
date_default_timezone_set('UTC');

// Security Settings
define('CSRF_TOKEN_LENGTH', 32);
define('OTP_LENGTH', 6);
define('OTP_EXPIRY', 300); // 5 minutes

// AI Configuration
define('AI_SCAN_INTERVAL', 3600); // 1 hour
define('AI_BATCH_SIZE', 100);

// Email Configuration (for OTP, notifications)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');

// System Limits
define('MAX_FILE_SIZE', 5242880); // 5MB
define('MAX_LOGIN_ATTEMPTS', 5);
define('SESSION_TIMEOUT', 7200); // 2 hours

// Multi-tenant Support
define('DEFAULT_TENANT_ID', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Global database connection
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
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Make $pdo available globally
$GLOBALS['db'] = $pdo;

// Helper function for database access
function db() {
    return $GLOBALS['db'];
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Generate OTP
function generateOTP() {
    return str_pad(random_int(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);
}

// Send OTP via email (placeholder - implement actual email sending)
function sendOTP($email, $otp) {
    // In production, implement actual email sending
    // For now, just log it
    error_log("OTP for $email: $otp");
    return true;
}

// Log system event
function logEvent($level, $message, $context = []) {
    $logEntry = date('Y-m-d H:i:s') . " [$level] $message";
    if (!empty($context)) {
        $logEntry .= " Context: " . json_encode($context);
    }
    error_log($logEntry);
}

// Clean input
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Get current tenant ID
function getTenantId() {
    return $_SESSION['tenant_id'] ?? DEFAULT_TENANT_ID;
}

// Check if system is in maintenance mode
function isMaintenanceMode() {
    return file_exists(__DIR__ . '/../maintenance.mode');
}

// Get system info
function getSystemInfo() {
    return [
        'version' => SAMS_VERSION,
        'php_version' => PHP_VERSION,
        'mysql_version' => db()->query("SELECT VERSION() as version")->fetchColumn(),
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'timestamp' => date('Y-m-d H:i:s')
    ];
}
?>
