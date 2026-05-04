<?php

/**
 * School Attendance System - Configuration File
 */

// Base Paths
defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
defined('INCLUDES_PATH') || define('INCLUDES_PATH', BASE_PATH . '/includes');
defined('PROJECT_ROOT') || define('PROJECT_ROOT', dirname(BASE_PATH));

if (!function_exists('detect_app_base_path')) {
    function detect_app_base_path(): string
    {
        $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName === '') {
            return '/attendance';
        }

        $patterns = [
            '#^(.*?)/frontend(?:/.*)?$#',
            '#^(.*?)/backend(?:/.*)?$#',
            '#^(.*?)/api(?:/.*)?$#',
            '#^(.*?)/[^/]+\.php$#'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $scriptName, $matches)) {
                $base = rtrim((string)($matches[1] ?? ''), '/');
                return $base === '' ? '' : $base;
            }
        }

        return '/attendance';
    }
}

if (!function_exists('detect_app_url')) {
    function detect_app_url(): string
    {
        $envUrl = getenv('APP_URL') ?: getenv('WEBSITE_HOSTNAME');
        if ($envUrl) {
            if (filter_var($envUrl, FILTER_VALIDATE_URL)) {
                return rtrim($envUrl, '/');
            }

            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
            $scheme = $https ? 'https' : 'http';
            $basePath = detect_app_base_path();
            return $scheme . '://' . trim((string)$envUrl, '/') . $basePath;
        }

        $host = (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
        $scheme = $https ? 'https' : 'http';
        $basePath = detect_app_base_path();
        return rtrim($scheme . '://' . $host . $basePath, '/');
    }
}

// Database Configuration
define('DB_HOST', 'localhost');  // Windows XAMPP
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'attendance_system');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'School Attendance System');
define('APP_VERSION', '1.0.0');
define('APP_URL', detect_app_url());
define('TIMEZONE', 'America/New_York');

// Security Settings
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('PASSWORD_MIN_LENGTH', 12);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

// File Upload Settings
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('MAX_FILE_SIZE', 10485760); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Attendance Settings
define('ATTENDANCE_STATUSES', ['present', 'absent', 'late', 'excused']);
define('CHRONIC_ABSENTEEISM_THRESHOLD', 10); // percentage

// Email Settings - Gmail SMTP Configuration
// IMPORTANT: You must set up Gmail App Password first! See EMAIL-SMTP-SETUP.md for instructions
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: '');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'School Attendance System');
define('SMTP_ENCRYPTION', 'tls');

// WhatsApp Configuration (Twilio)
// See WHATSAPP-API-SETUP.md for setup instructions
define('TWILIO_ACCOUNT_SID', getenv('TWILIO_ACCOUNT_SID') ?: '');
define('TWILIO_AUTH_TOKEN', getenv('TWILIO_AUTH_TOKEN') ?: '');
define('TWILIO_WHATSAPP_FROM', getenv('TWILIO_WHATSAPP_FROM') ?: '');
define('ADMIN_WHATSAPP_NUMBER', getenv('ADMIN_WHATSAPP_NUMBER') ?: '');

// Gemini API Configuration
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Database connection: load database.php which provides the db() function
// via the Database class (with fetchOne, fetchAll, insert, update, delete, count methods).
require_once __DIR__ . '/database.php';

// Error reporting: keep full reporting but never display to end users.
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
$frontendLogDir = BASE_PATH . '/storage/logs';
if (!is_dir($frontendLogDir)) {
    @mkdir($frontendLogDir, 0775, true);
}
ini_set('error_log', $frontendLogDir . '/system.log');

// Session configuration (must be set before session_start)
if (session_status() === PHP_SESSION_NONE) {
    // Set session settings before starting
    $isHttpsRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', $isHttpsRequest ? '1' : '0');
    ini_set('session.cookie_path', '/');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    $backendLogDir = BASE_PATH . '/storage/logs';
    if (!is_dir($backendLogDir)) {
        @mkdir($backendLogDir, 0775, true);
    }
    ini_set('error_log', $backendLogDir . '/system.log');
    ini_set('session.cookie_samesite', 'Lax');

    // Only start session if headers not sent
    if (!headers_sent()) {
        session_start();
    }
}
// If session already active, ini_set calls are simply skipped (harmless).

// Load security infrastructure
require_once __DIR__ . '/session-guard.php';
require_once __DIR__ . '/security-headers.php';
require_once __DIR__ . '/rate-limiter.php';

// Apply security headers on every request
apply_security_headers();

// Enforce role-based session timeout on every authenticated request
enforce_session_timeout();

// Load bootstrap from whichever path exists in the current split-folder setup
$bootstrapCandidates = [
    PROJECT_ROOT . '/backend/app/app/bootstrap.php',  // backend split path
    BASE_PATH . '/app/bootstrap.php',                 // legacy monolith path
    BASE_PATH . '/core/bootstrap.php',                // current frontend path
];

$bootstrapLoaded = false;
foreach ($bootstrapCandidates as $bootstrapFile) {
    if (file_exists($bootstrapFile)) {
        require_once $bootstrapFile;
        $bootstrapLoaded = true;
        break;
    }
}

if (!$bootstrapLoaded) {
    error_log('Bootstrap file not found in expected locations.');
}

// Activate SecurityGateway: XSS + SQLi detection on every request
// CSRF and rate limiting are handled per-endpoint
try {
    SecurityGateway::guard([
        'require_auth' => false,
        'rate_action'  => null,
        'csrf'         => false,
        'xss_check'    => true,
        'sqli_check'   => true,
    ]);
} catch (Throwable $e) {
    error_log("SecurityGateway init error: " . $e->getMessage());
}

// Initialize Failure Containment (admin alerting on critical errors)
try {
    FailureContainment::init();
} catch (Throwable $e) {
    error_log("FailureContainment init error: " . $e->getMessage());
}

// Run the unified request middleware pipeline
try {
    RequestPipeline::process();
} catch (Throwable $e) {
    error_log("RequestPipeline error: " . $e->getMessage());
}
