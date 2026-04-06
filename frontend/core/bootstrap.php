<?php

/**
 * SAMS Global Bootstrap System
 * Stabilizes all role panels with safe initialization
 * Prevents session warnings and database errors from breaking UI
 */

// Enable output buffering to catch all output before headers
ob_start();

// Set error reporting to catch all errors but not display them
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../storage/logs/system.log');

// Configure session settings before session_start
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');

    // Start session safely
    if (!headers_sent()) {
        session_start();
    }
}

// Set global error handler
set_error_handler(function ($severity, $message, $file, $line) {
    // Only log errors, don't display them
    $errorType = 'UNKNOWN';
    switch ($severity) {
        case E_ERROR:
            $errorType = 'ERROR';
            break;
        case E_WARNING:
            $errorType = 'WARNING';
            break;
        case E_NOTICE:
            $errorType = 'NOTICE';
            break;
        case E_USER_ERROR:
            $errorType = 'USER_ERROR';
            break;
        case E_USER_WARNING:
            $errorType = 'USER_WARNING';
            break;
        case E_USER_NOTICE:
            $errorType = 'USER_NOTICE';
            break;
    }

    $logMessage = sprintf(
        "[%s] %s: %s in %s on line %d",
        date('Y-m-d H:i:s'),
        $errorType,
        $message,
        $file,
        $line
    );

    error_log($logMessage);

    // Don't show errors to user
    return true;
});

// Set exception handler
set_exception_handler(function ($exception) {
    $logMessage = sprintf(
        "[%s] EXCEPTION: %s in %s on line %d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );

    error_log($logMessage);

    // Show clean error page
    showSystemErrorPage();
});

// Register shutdown function for fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $logMessage = sprintf(
            "[%s] FATAL ERROR: %s in %s on line %d",
            date('Y-m-d H:i:s'),
            $error['message'],
            $error['file'],
            $error['line']
        );

        error_log($logMessage);

        // Clean output buffer and show error page
        ob_clean();
        showSystemErrorPage();
    }
});

/**
 * Load the main includes system for database and functions
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Show clean system error page
 */
function showSystemErrorPage()
{
    // Clean any existing output
    if (ob_get_level()) {
        ob_clean();
    }

    // Set proper headers
    header('HTTP/1.1 503 Service Temporarily Unavailable');
    header('Content-Type: text/html; charset=UTF-8');

    // Show clean error page
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAMS - System Temporarily Unavailable</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
        }

        .error-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: #ff6b6b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: white;
        }

        .error-title {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .error-message {
            font-size: 16px;
            color: #7f8c8d;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .error-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
        }

        .btn-secondary {
            background: #ecf0f1;
            color: #2c3e50;
        }

        .btn-secondary:hover {
            background: #d5dbdd;
        }

        .error-details {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
            font-size: 12px;
            color: #95a5a6;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1 class="error-title">SAMS Temporarily Unavailable</h1>
        <p class="error-message">
            We are experiencing technical difficulties. Please contact the administrator or try again in a few minutes.
        </p>
        <div class="error-actions">
            <button class="btn btn-primary" onclick="window.location.reload()">Try Again</button>
            <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
        </div>
        <div class="error-details">
            Error ID: ' . uniqid('ERR_') . ' | Time: ' . date('Y-m-d H:i:s') . '
        </div>
    </div>
</body>
</html>';

    exit;
}

/**
 * Load shared layout components
 */
function loadLayout($component, $role = null)
{
    $layoutFile = __DIR__ . '/../views/layouts/' . $component . '.php';

    if (file_exists($layoutFile)) {
        include $layoutFile;
    }
}

/**
 * Load dynamic sidebar based on role
 */
function loadSidebar($role)
{
    $sidebarFile = __DIR__ . '/../views/layouts/sidebar_' . $role . '.php';

    if (file_exists($sidebarFile)) {
        include $sidebarFile;
    } else {
        // Fallback to default sidebar
        $defaultSidebar = __DIR__ . '/../views/layouts/sidebar.php';
        if (file_exists($defaultSidebar)) {
            include $defaultSidebar;
        }
    }
}

/**
 * Apply theme globally
 */
function applyTheme()
{
    $theme = 'default';

    // Get theme from session or settings
    if (isset($_SESSION['theme'])) {
        $theme = $_SESSION['theme'];
    } else {
        // Try to get from database
        try {
            $setting = db()->fetchOne("SELECT setting_value FROM settings WHERE setting_key = 'theme' AND is_active = 1");
            if ($setting) {
                $theme = $setting['setting_value'];
                $_SESSION['theme'] = $theme;
            }
        } catch (Exception $e) {
            // Use default theme
        }
    }

    // Set theme CSS variable
    echo '<style>';
    echo ':root { --theme-color: var(--theme-' . $theme . '-color, #667eea); }';
    echo '</style>';

    // Load theme CSS if exists
    $themeFile = __DIR__ . '/../public/themes/' . $theme . '/theme.css';
    if (file_exists($themeFile)) {
        echo '<link rel="stylesheet" href="' . str_replace(__DIR__, '', $themeFile) . '">';
    }
}

/**
 * Check if user is authenticated
 */
function isAuthenticated()
{
    return is_logged_in();
}

/**
 * Get current user role
 */
function getCurrentRole()
{
    return $_SESSION['role'] ?? 'guest';
}

/**
 * Redirect if not authenticated
 */
function requireAuth()
{
    if (!isAuthenticated()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Redirect if role doesn't match
 */
function requireRole($requiredRole)
{
    requireAuth();

    if (getCurrentRole() !== $requiredRole) {
        header('HTTP/1.1 403 Forbidden');
        showSystemErrorPage();
    }
}

/**
 * Safe file include with error handling
 */
function safeInclude($file)
{
    if (file_exists($file)) {
        include $file;
        return true;
    }

    error_log("Required file not found: $file");
    return false;
}

/**
 * Initialize system
 */
function initializeSystem()
{
    // Apply theme
    applyTheme();

    // Set common headers
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');

    // Set timezone
    date_default_timezone_set('UTC');
}

// Initialize the system
initializeSystem();

// Load database helper functions
require_once __DIR__ . '/../includes/database_helper.php';
