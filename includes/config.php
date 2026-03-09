<?php

/**
 * School Attendance System - Configuration File
 */

// Base Paths
define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');

// Database Configuration
define('DB_HOST', 'localhost');  // Windows XAMPP
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'attendance_system');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'School Attendance System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/attendance');
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

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error reporting: keep full reporting but never display to end users.
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', BASE_PATH . '/logs/php-errors.log');

// Global error/exception handler – prevents blank pages & hides internals.
require_once INCLUDES_PATH . '/error-handler.php';

// Session configuration (must be set before session_start)
if (session_status() === PHP_SESSION_NONE) {
    // Avoid warnings when any output has already started upstream.
    if (!headers_sent()) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
        ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
        session_start();
    }
}
