<?php
/**
 * SAMS Automated Backup Cron Job v2.0
 * Enhanced scheduling with 24-hour automation and AI self-monitoring
 * Performance-optimized with async execution only
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../app/services/BackupService.php';

// Enhanced lock file system with 24-hour protection
$lockFile = __DIR__ . '/../../storage/backup.lock';
$lockTimeout = 3600; // 1 hour timeout

// Check for existing lock
if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    if (time() - $lockTime < $lockTimeout) {
        echo "Backup already running. Skipping execution.\n";
        exit(0);
    } else {
        // Remove stale lock
        unlink($lockFile);
    }
}

// Create lock file with process ID
touch($lockFile);
file_put_contents($lockFile, getmypid());

// AI Self-Monitoring Logger
$aiLog = function($event, $details = []) {
    $logFile = __DIR__ . '/../../storage/logs/backup_ai.log';
    $timestamp = date('Y-m-d H:i:s');
    $detailsStr = $details ? ' | ' . json_encode($details) : '';
    $logMessage = "[$timestamp] $event $detailsStr\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
};

try {
    $startTime = microtime(true);
    $aiLog('backup_cron_started', [
        'date' => date('Y-m-d'),
        'time' => date('H:i:s'),
        'process_id' => getmypid(),
        'php_version' => PHP_VERSION,
        'memory_limit' => ini_get('memory_limit')
    ]);

    echo "SAMS Automated Backup v2.0\n";
    echo "==========================================\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n";
    echo "Process ID: " . getmypid() . "\n";
    echo "==========================================\n\n";

    // 24-Hour Automation: Check if backup already completed today
    $today = date('Y-m-d');
    $db = db();

    $todayBackup = $db->fetchOne("
        SELECT * FROM backup_tracking
        WHERE backup_date = ? AND status = 'completed'
        ORDER BY backup_time DESC
        LIMIT 1
    ", [$today]);

    if ($todayBackup) {
        echo "Backup already completed today at {$todayBackup['backup_time']}\n";
        echo "Status: {$todayBackup['verification_status']}\n";
        echo "Skipping execution.\n";

        $aiLog('backup_already_completed', [
            'backup_date' => $today,
            'backup_time' => $todayBackup['backup_time'],
            'verification_status' => $todayBackup['verification_status']
        ]);

        unlink($lockFile);
        exit(0);
    }

    // Performance Rule: Check system load before backup
    $systemLoad = sys_getloadavg();
    if ($systemLoad[0] > 2.0) {
        echo "System load is high ({$systemLoad[0]}). Delaying backup...\n";
        $aiLog('high_system_load_detected', ['load' => $systemLoad[0]]);
        sleep(300); // Wait 5 minutes
    }

    // Check available memory
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = return_bytes(ini_get('memory_limit'));
    $memoryUsagePercent = round(($memoryUsage / $memoryLimit) * 100, 2);

    if ($memoryUsagePercent > 80) {
        echo "High memory usage detected ({$memoryUsagePercent}%). Delaying backup...\n";
        $aiLog('high_memory_usage_detected', ['usage_percent' => $memoryUsagePercent]);
        sleep(300); // Wait 5 minutes
    }

    echo "System checks passed. Starting backup...\n\n";

    // Initialize enhanced backup service
    $backupService = new BackupService();

    // Perform backup with enhanced options
    echo "Performing enhanced backup...\n";
    $result = $backupService->performBackup([
        'compression_level' => 6,
        'verify_integrity' => true,
        'create_incremental' => true,
        'ai_monitoring' => true
    ]);

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    // AI Self-Monitoring: Log results
    $aiLog('backup_process_completed', [
        'success' => $result['success'],
        'duration' => $duration,
        'backup_id' => $result['backup_id'] ?? null,
        'integrity_result' => $result['verification']['status'] ?? 'unknown',
        'anomalies_detected' => count($result['verification']['anomalies_detected'] ?? []),
        'file_size' => $result['master_backup']['file_size'] ?? 0
    ]);

    if ($result['success']) {
        echo "✓ Enhanced backup completed successfully!\n";
        echo "  - Duration: {$duration} seconds\n";
        echo "  - Backup ID: {$result['backup_id']}\n";
        echo "  - File Size: " . round($result['master_backup']['file_size'] / 1024 / 1024, 2) . " MB\n";
        echo "  - Compression Ratio: {$result['master_backup']['compression_ratio']}%\n";
        echo "  - Items Count: {$result['master_backup']['items_count']}\n";

        // Enhanced verification status display
        $verificationStatus = $result['verification']['status'];
        $statusIcon = $verificationStatus === 'VALID' ? '✓' : ($verificationStatus === 'PARTIAL' ? '⚠' : '✗');
        echo "  - AI Verification: {$statusIcon} {$verificationStatus}\n";

        if (!empty($result['verification']['anomalies_detected'])) {
            echo "  - Anomalies Detected: " . count($result['verification']['anomalies_detected']) . "\n";
            foreach ($result['verification']['anomalies_detected'] as $anomaly) {
                echo "    • {$anomaly}\n";
            }
        }

        // Log backup details
        echo "\nBackup Details:\n";
        echo "  - Database: " . ($result['backup_results']['database']['success'] ? '✓' : '✗') . "\n";
        echo "  - Files: " . ($result['backup_results']['files']['success'] ? '✓' : '✗') . "\n";
        echo "  - Configuration: " . ($result['backup_results']['config']['success'] ? '✓' : '✗') . "\n";
        echo "  - Logs: " . ($result['backup_results']['logs']['success'] ? '✓' : '✗') . "\n";
        echo "  - Incremental Export: " . ($result['backup_results']['incremental']['success'] ? '✓' : '✗') . "\n";

        // Show restore-ready structure
        if ($result['master_backup']['restore_ready']) {
            echo "\nRestore-Ready Archive Structure:\n";
            echo "  storage/backups/" . date('Y-m-d') . "/\n";
            echo "    ├── database.sql.gz\n";
            echo "    ├── incremental.json.gz\n";
            echo "    ├── metadata.json\n";
            echo "    ├── checksum.sha256\n";
            echo "    └── sams_backup_" . date('Y-m-d') . ".tar.gz\n";
        }

        // Send notification (optional)
        if (defined('BACKUP_NOTIFICATION_EMAIL') && BACKUP_NOTIFICATION_EMAIL) {
            sendEnhancedBackupNotification($result, $duration);
        }

        $aiLog('backup_success', [
            'backup_id' => $result['backup_id'],
            'file_size' => $result['master_backup']['file_size'],
            'verification_status' => $verificationStatus,
            'anomalies_count' => count($result['verification']['anomalies_detected'] ?? [])
        ]);

    } else {
        echo "✗ Enhanced backup failed!\n";
        echo "  - Error: {$result['message']}\n";

        $aiLog('backup_failed', [
            'error' => $result['message'],
            'error_details' => $result['error'] ?? null
        ]);

        // Send error notification
        if (defined('BACKUP_NOTIFICATION_EMAIL') && BACKUP_NOTIFICATION_EMAIL) {
            sendEnhancedBackupErrorNotification($result, $duration);
        }
    }

    echo "\n==========================================\n";
    echo "Enhanced backup process completed at " . date('Y-m-d H:i:s') . "\n";
    echo "Total Duration: {$duration} seconds\n";
    echo "Process ID: " . getmypid() . "\n";
    echo "==========================================\n";

    // Final AI monitoring log
    $aiLog('backup_cron_finished', [
        'success' => $result['success'],
        'duration' => $duration,
        'final_status' => $result['verification']['status'] ?? 'unknown'
    ]);

} catch (Exception $e) {
    echo "✗ Critical error during enhanced backup process!\n";
    echo "  - Error: " . $e->getMessage() . "\n";
    echo "  - File: " . $e->getFile() . "\n";
    echo "  - Line: " . $e->getLine() . "\n";

    // Enhanced error logging
    $aiLog('backup_critical_error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    // Log to system log
    error_log("Enhanced backup cron error: " . $e->getMessage());

} finally {
    // Remove lock file
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}

/**
 * Enhanced backup success notification
 */
function sendEnhancedBackupNotification($result, $duration)
{
    $subject = "SAMS Enhanced Backup Completed Successfully";
    $message = "SAMS enhanced automated backup completed successfully on " . date('Y-m-d H:i:s') . "\n\n";
    $message .= "Enhanced Backup Details:\n";
    $message .= "- Duration: {$duration} seconds\n";
    $message .= "- Backup ID: {$result['backup_id']}\n";
    $message .= "- File Size: " . round($result['master_backup']['file_size'] / 1024 / 1024, 2) . " MB\n";
    $message .= "- Compression Ratio: {$result['master_backup']['compression_ratio']}%\n";
    $message .= "- Items Count: {$result['master_backup']['items_count']}\n";
    $message .= "- AI Verification Status: {$result['verification']['status']}\n";
    $message .= "- Restore Ready: " . ($result['master_backup']['restore_ready'] ? 'Yes' : 'No') . "\n";

    if (!empty($result['verification']['anomalies_detected'])) {
        $message .= "- Anomalies Detected: " . count($result['verification']['anomalies_detected']) . "\n";
        foreach ($result['verification']['anomalies_detected'] as $anomaly) {
            $message .= "  • {$anomaly}\n";
        }
    }

    if (!empty($result['verification']['recommendations'])) {
        $message .= "\nAI Recommendations:\n";
        foreach ($result['verification']['recommendations'] as $recommendation) {
            $message .= "- {$recommendation}\n";
        }
    }

    $headers = [
        'From: ' . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@sams.edu'),
        'Content-Type: text/plain; charset=UTF-8'
    ];

    mail(BACKUP_NOTIFICATION_EMAIL, $subject, $message, $headers);
}

/**
 * Enhanced backup error notification
 */
function sendEnhancedBackupErrorNotification($result, $duration)
{
    $subject = "SAMS Enhanced Backup Failed";
    $message = "SAMS enhanced automated backup failed on " . date('Y-m-d H:i:s') . "\n\n";
    $message .= "Error Details:\n";
    $message .= "- Duration: {$duration} seconds\n";
    $message .= "- Error: {$result['message']}\n";

    if (isset($result['error'])) {
        $message .= "- Technical Error: {$result['error']}\n";
    }

    $message .= "- Process ID: " . getmypid() . "\n";
    $message .= "- Timestamp: " . date('Y-m-d H:i:s') . "\n";

    $headers = [
        'From: ' . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@sams.edu'),
        'Content-Type: text/plain; charset=UTF-8'
    ];

    mail(BACKUP_NOTIFICATION_EMAIL, $subject, $message, $headers);
}

/**
 * Enhanced system resources check
 */
function checkEnhancedSystemResources()
{
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = ini_get('memory_limit');
    $memoryUsagePercent = round(($memoryUsage / return_bytes($memoryLimit)) * 100, 2);

    $diskUsage = disk_free_space(__DIR__);
    $diskTotal = disk_total_space(__DIR__);
    $diskUsagePercent = round((($diskTotal - $diskUsage) / $diskTotal) * 100, 2);

    $systemLoad = sys_getloadavg();

    echo "Enhanced System Resources:\n";
    echo "- Memory Usage: {$memoryUsagePercent}% (" . round($memoryUsage / 1024 / 1024, 2) . " MB)\n";
    echo "- Disk Usage: {$diskUsagePercent}% (" . round(($diskTotal - $diskUsage) / 1024 / 1024 / 1024, 2) . " GB free)\n";
    echo "- System Load: " . round($systemLoad[0], 2) . " (1 min), " . round($systemLoad[1], 2) . " (5 min), " . round($systemLoad[2], 2) . " (15 min)\n";

    $warnings = [];
    if ($memoryUsagePercent > 80) {
        $warnings[] = "High memory usage detected";
    }
    if ($diskUsagePercent > 80) {
        $warnings[] = "High disk usage detected";
    }
    if ($systemLoad[0] > 2.0) {
        $warnings[] = "High system load detected";
    }

    if (!empty($warnings)) {
        echo "⚠️  Warnings: " . implode(', ', $warnings) . "\n";
    }

    return [
        'memory_usage_percent' => $memoryUsagePercent,
        'disk_usage_percent' => $diskUsagePercent,
        'system_load' => $systemLoad,
        'warnings' => $warnings
    ];
}

/**
 * Enhanced backup integrity verification
 */
function verifyEnhancedBackupIntegrity($backupPath)
{
    if (!file_exists($backupPath)) {
        return false;
    }

    $fileSize = filesize($backupPath);
    if ($fileSize === 0) {
        return false;
    }

    if (!is_readable($backupPath)) {
        return false;
    }

    $hash = hash_file('sha256', $backupPath);
    if (strlen($hash) !== 64) {
        return false;
    }

    return [
        'valid' => true,
        'file_size' => $fileSize,
        'checksum' => $hash
    ];
}

/**
 * Enhanced cleanup of old backup files
 */
function cleanupEnhancedOldBackupFiles()
{
    $backupDir = __DIR__ . '/../../storage/backups/';
    $maxAge = 30 * 24 * 60 * 60; // 30 days

    if (is_dir($backupDir)) {
        // Clean up old date-based directories
        $dateDirs = glob($backupDir . '*', GLOB_ONLYDIR);
        $deletedCount = 0;

        foreach ($dateDirs as $dir) {
            $dirName = basename($dir);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dirName)) {
                $dirDate = new DateTime($dirName);
                $now = new DateTime();
                $interval = $now->diff($dirDate);

                if ($interval->days > 30) {
                    // Remove entire directory
                    $files = glob($dir . '/*');
                    foreach ($files as $file) {
                        unlink($file);
                    }
                    rmdir($dir);
                    $deletedCount++;
                }
            }
        }

        // Clean up old master files
        $masterFiles = glob($backupDir . 'master/*.tar.gz');
        foreach ($masterFiles as $file) {
            if (filemtime($file) < (time() - $maxAge)) {
                unlink($file);
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            echo "Cleaned up {$deletedCount} old backup files and directories\n";
        }
    }
}

/**
 * Enhanced backup event logging
 */
function logEnhancedBackupEvent($event, $details = [])
{
    $logFile = __DIR__ . '/../../logs/backup_cron.log';
    $timestamp = date('Y-m-d H:i:s');
    $detailsStr = $details ? ' | ' . json_encode($details) : '';
    $logMessage = "[$timestamp] $event $detailsStr\n";

    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Enhanced system checks
echo "Performing enhanced system checks...\n";
$systemResources = checkEnhancedSystemResources();
cleanupEnhancedOldBackupFiles();
echo "\n";

logEnhancedBackupEvent('enhanced_backup_cron_started', [
    'date' => date('Y-m-d'),
    'time' => date('H:i:s'),
    'process_id' => getmypid(),
    'system_resources' => $systemResources
]);

?>
