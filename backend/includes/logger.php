<?php

/**
 * Logger Class
 * Centralized logging for Attendance AI
 */

class Logger
{
    private static $logDir = __DIR__ . '/../logs/';

    public static function error($message, $context = [])
    {
        self::write('error/php_errors.log', 'ERROR', $message, $context);
    }

    public static function info($message, $context = [])
    {
        self::write('access/http_access.log', 'INFO', $message, $context);
    }

    public static function audit($action, $userId, $details = [])
    {
        $message = "User $userId performed: $action";
        self::write('audit/user_actions.log', 'AUDIT', $message, $details);
    }

    private static function write($file, $level, $message, $context)
    {
        $path = self::$logDir . $file;
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextJson = !empty($context) ? ' ' . json_encode($context) : '';
        $logLine = "[$timestamp] [$level] $message$contextJson\n";

        // Rotate if file > 10MB
        if (file_exists($path) && filesize($path) > 10 * 1024 * 1024) {
            rename($path, $path . '.' . date('Ymd'));
        }

        file_put_contents($path, $logLine, FILE_APPEND | LOCK_EX);
    }
}
