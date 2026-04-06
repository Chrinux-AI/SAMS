<?php
/**
 * Global Error & Exception Handler
 * Prevents blank pages and raw PHP errors from reaching users.
 * Bootstrapped from config.php — loaded on every request.
 */

// Convert PHP errors to ErrorException so they flow through the exception handler.
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    // Respect the current error_reporting level (allows @ suppression).
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Catch all uncaught exceptions.
set_exception_handler(function (Throwable $e): void {
    // Always log the full error.
    $logMsg = sprintf(
        "[%s] %s in %s:%d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($logMsg);

    // If headers already sent we can only append text.
    if (headers_sent()) {
        // Minimal safe output — never expose internals.
        echo '<!-- An error occurred. Check logs for details. -->';
        return;
    }

    // Determine if this is an API/AJAX request.
    $isApi = (
        str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/') ||
        str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
        !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    );

    if ($isApi) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error'   => 'Internal server error',
        ]);
        return;
    }

    // HTML error page for browser requests.
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Something went wrong</title>
    <style>
        body{font-family:'Inter',system-ui,sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;color:#334155}
        .error-box{background:#fff;border-radius:16px;padding:48px;max-width:480px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.08)}
        .error-box .icon{font-size:3rem;margin-bottom:16px}
        .error-box h1{font-size:1.5rem;margin:0 0 12px;font-weight:700}
        .error-box p{color:#64748b;line-height:1.6;margin:0 0 24px}
        .error-box a{display:inline-block;padding:12px 24px;background:#4F46E5;color:#fff;border-radius:10px;text-decoration:none;font-weight:600;transition:background .2s}
        .error-box a:hover{background:#4338CA}
    </style>
</head>
<body>
<div class="error-box">
    <div class="icon">⚠️</div>
    <h1>Something went wrong</h1>
    <p>We encountered an unexpected error. The issue has been logged and our team will look into it.</p>
    <a href="/attendance/">Return to Home</a>
</div>
</body>
</html>
<?php
});

// Catch fatal errors that bypass the exception handler
// (e.g. E_COMPILE_ERROR, out-of-memory).
register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }
    // Only handle fatal-level errors.
    $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;
    if (!($error['type'] & $fatal)) {
        return;
    }

    $logMsg = sprintf(
        "[%s] FATAL: %s in %s:%d",
        date('Y-m-d H:i:s'),
        $error['message'],
        $error['file'],
        $error['line']
    );
    error_log($logMsg);

    if (headers_sent()) {
        return;
    }

    $isApi = (
        str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/') ||
        str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
        !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    );

    if ($isApi) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    } else {
        http_response_code(500);
        echo '<h1>Something went wrong</h1><p>A fatal error occurred. Please try again later.</p>';
        echo '<p><a href="/attendance/">Return to Home</a></p>';
    }
});
