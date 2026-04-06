<?php
/**
 * System log helper
 */

if (!function_exists('system_log')) {
    function system_log(string $level, string $message, array $context = []): void
    {
        $logFile = BASE_PATH . '/logs/system.log';
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $safeLevel = strtoupper(trim($level));
        if ($safeLevel === '') {
            $safeLevel = 'INFO';
        }

        $ctx = '';
        if (!empty($context)) {
            $ctxJson = json_encode($context, JSON_UNESCAPED_SLASHES);
            $ctx = $ctxJson !== false ? ' ' . $ctxJson : '';
        }

        $line = '[' . date('Y-m-d H:i:s') . "] [{$safeLevel}] {$message}{$ctx}" . PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}

