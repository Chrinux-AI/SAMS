<?php
/**
 * SAMS Backup Setup Script
 * Creates necessary directories and tables for automated backup system
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$db = db();

echo "SAMS Automated Backup System Setup\n";
echo "==================================\n\n";

// Create storage directory structure
$storageDir = __DIR__ . '/../storage/';
$backupDir = $storageDir . 'backups/';

echo "Creating storage directories...\n";

// Create main storage directory
if (!is_dir($storageDir)) {
    if (mkdir($storageDir, 0755, true)) {
        echo "✓ Storage directory created: $storageDir\n";
    } else {
        echo "✗ Failed to create storage directory: $storageDir\n";
    }
} else {
    echo "✓ Storage directory already exists: $storageDir\n";
}

// Create backup directory
if (!is_dir($backupDir)) {
    if (mkdir($backupDir, 0755, true)) {
        echo "✓ Backup directory created: $backupDir\n";
    } else {
        echo "✗ Failed to create backup directory: $backupDir\n";
    }
} else {
    echo "✓ Backup directory already exists: $backupDir\n";
}

// Create backup subdirectories
$subdirs = ['database', 'files', 'config', 'logs', 'exports', 'master', 'temp'];

foreach ($subdirs as $subdir) {
    $path = $backupDir . $subdir;
    if (!is_dir($path)) {
        if (mkdir($path, 0755, true)) {
            echo "✓ Created subdirectory: $path\n";
        } else {
            echo "✗ Failed to create subdirectory: $path\n";
        }
    } else {
        echo "✓ Subdirectory already exists: $path\n";
    }
}

// Create logs directory if it doesn't exist
$logsDir = __DIR__ . '/../logs/';
if (!is_dir($logsDir)) {
    if (mkdir($logsDir, 0755, true)) {
        echo "✓ Logs directory created: $logsDir\n";
    } else {
        echo "✗ Failed to create logs directory: $logsDir\n";
    }
} else {
    echo "✓ Logs directory already exists: $logsDir\n";
}

echo "\nCreating backup tracking table...\n";

// Create backup tracking table
$backupTrackingTable = "
CREATE TABLE IF NOT EXISTS backup_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_type VARCHAR(50) NOT NULL,
    backup_date DATE NOT NULL,
    backup_time TIME NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT DEFAULT 0,
    compression_ratio DECIMAL(5,2) DEFAULT 0,
    integrity_hash VARCHAR(64),
    status ENUM('running', 'completed', 'failed', 'verified') DEFAULT 'running',
    error_message TEXT,
    items_count INT DEFAULT 0,
    verification_status ENUM('pending', 'verified', 'failed') DEFAULT 'pending',
    verification_details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_backup_date (backup_date),
    INDEX idx_backup_type (backup_type),
    INDEX idx_status (status),
    INDEX idx_verification_status (verification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

try {
    $db->createTable($backupTrackingTable);
    echo "✓ Backup tracking table created successfully\n";
} catch (Exception $e) {
    echo "✗ Error creating backup tracking table: " . $e->getMessage() . "\n";
}

echo "\nChecking file permissions...\n";

// Check directory permissions
$dirs = [$storageDir, $backupDir, $logsDir];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        echo "Directory $dir permissions: $perms\n";

        if ($perms !== '0755') {
            echo "  → Fixing permissions...\n";
            chmod($dir, 0755);
        }
    }
}

echo "\nChecking system requirements...\n";

// Check PHP extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'zip', 'phar'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    } else {
        echo "✓ Extension $ext is loaded\n";
    }
}

if (!empty($missingExtensions)) {
    echo "✗ Missing extensions: " . implode(', ', $missingExtensions) . "\n";
    echo "  → Please install missing extensions to use backup system\n";
}

// Check available disk space
$freeSpace = disk_free_space($backupDir);
$totalSpace = disk_total_space($backupDir);
$usedSpace = $totalSpace - $freeSpace;
$usagePercent = round(($usedSpace / $totalSpace) * 100, 2);

echo "\nDisk Space Information:\n";
echo "Total Space: " . round($totalSpace / 1024 / 1024 / 1024, 2) . " GB\n";
echo "Used Space: " . round($usedSpace / 1024 / 1024 / 1024, 2) . " GB\n";
echo "Free Space: " . round($freeSpace / 1024 / 1024 / 1024, 2) . " GB\n";
echo "Usage: {$usagePercent}%\n";

if ($usagePercent > 80) {
    echo "⚠️  High disk usage detected. Consider cleaning up old files.\n";
}

// Check memory limit
$memoryLimit = ini_get('memory_limit');
$currentMemory = memory_get_usage(true);
$memoryUsagePercent = round(($currentMemory / return_bytes($memoryLimit)) * 100, 2);

echo "\nMemory Information:\n";
echo "Memory Limit: $memoryLimit\n";
echo "Current Usage: " . round($currentMemory / 1024 / 1024, 2) . " MB\n";
echo "Usage: {$memoryUsagePercent}%\n";

echo "\nChecking backup service...\n";

// Check if BackupService exists
if (file_exists(__DIR__ . '/../app/services/BackupService.php')) {
    echo "✓ BackupService exists\n";
} else {
    echo "✗ BackupService not found\n";
}

// Check if backup cron exists
if (file_exists(__DIR__ . '/backup.php')) {
    echo "✓ Backup cron script exists\n";
} else {
    echo "✗ Backup cron script not found\n";
}

echo "\nCron Job Setup Instructions:\n";
echo "==================================\n";
echo "To set up automated daily backups, add the following cron job:\n\n";
echo "# Daily backup at 2:00 AM\n";
echo "0 2 * * * /usr/bin/php " . __DIR__ . "/backup.php >> " . $logsDir . "backup_cron.log 2>&1\n\n";
echo "Or for testing (every minute):\n";
echo "* * * * * /usr/bin/php " . __DIR__ . "/backup.php >> " . $logsDir . "backup_cron.log 2>&1\n\n";

echo "Alternative: Use web-based cron (if available)\n";
echo "URL: " . BASE_URL . "/cron/backup.php\n\n";

echo "Backup Features:\n";
echo "==================================\n";
echo "✓ Daily automatic backup scheduling\n";
echo "✓ Incremental JSON export\n";
echo "✓ Complete database dump\n";
echo "✓ File system backup\n";
echo "✓ Configuration backup\n";
echo "✓ Log file backup\n";
echo "✓ AI-powered integrity verification\n";
echo "✓ Compressed archive creation\n";
echo "✓ Backup history tracking\n";
echo "✓ Automatic cleanup of old backups\n";
echo "✓ Performance-optimized operations\n";
echo "✓ Non-invasive system impact\n";
echo "✓ Restore-ready archives\n";
echo "✓ Comprehensive error handling\n";
echo "✓ Email notifications (optional)\n";
echo "✓ System resource monitoring\n";

echo "\nConfiguration Options:\n";
echo "==================================\n";
echo "To customize backup behavior, you can modify these constants in includes/config.php:\n\n";
echo "// Backup notification email (optional)\n";
echo "define('BACKUP_NOTIFICATION_EMAIL', 'admin@example.com');\n\n";
echo "// Backup retention period (days)\n";
echo "define('BACKUP_RETENTION_DAYS', 30);\n\n";
echo "// Backup compression level (1-9)\n";
echo "define('BACKUP_COMPRESSION_LEVEL', 6);\n\n";
echo "// Maximum backup file size (MB)\n";
echo "define('BACKUP_MAX_FILE_SIZE', 2048);\n";

echo "\nTesting Backup System:\n";
echo "==================================\n";
echo "To test the backup system manually:\n\n";
echo "1. Run the backup script:\n";
echo "   php " . __DIR__ . "/backup.php\n\n";
echo "2. Check the backup directory:\n";
echo "   ls -la " . $backupDir . "\n\n";
echo "3. Verify backup integrity:\n";
echo "   php -r \"" . __DIR__ . "/../app/services/BackupService.php\"\n";
echo "   \$backup = new BackupService();\n";
echo "   \$stats = \$backup->getBackupStatistics();\n";
echo "   print_r(\$stats);\n\n";

echo "\nBackup Directory Structure:\n";
echo "==================================\n";
$structure = [
    $backupDir => [
        'database/' => 'Database SQL dumps',
        'files/' => 'File system backups',
        'config/' => 'Configuration files',
        'logs/' => 'Log file backups',
        'exports/' => 'Incremental JSON exports',
        'master/' => 'Complete backup archives',
        'temp/' => 'Temporary files (cleaned automatically)'
    ]
];

foreach ($structure as $dir => $contents) {
    echo "$dir\n";
    foreach ($contents as $subdir => $description) {
        echo "  ├── $subdir - $description\n";
    }
}

echo "\nSecurity Considerations:\n";
echo "==================================\n";
echo "✓ Backup files are stored in secure directory\n";
echo "✓ File permissions are set to 0755\n";
echo "✓ Backup tracking is stored in database\n";
echo "✓ AI verification ensures data integrity\n";
echo "✓ Old backups are automatically cleaned up\n";
echo "✓ Backup process is non-invasive to runtime\n";
echo "✓ Concurrent execution is prevented\n";
echo "✓ System resources are monitored\n";

echo "\nTroubleshooting:\n";
echo "==================================\n";
echo "If backup fails, check:\n\n";
echo "1. Directory permissions: chmod -R 755 storage/\n";
echo "2. PHP extensions: php -m | grep -E '(pdo|zip|phar)'\n";
echo "3. Disk space: df -h\n";
echo "4. Memory usage: php -i | grep memory_limit\n";
echo "5. Error logs: tail " . $logsDir . "backup_cron.log\n";

echo "\nSetup completed!\n";
echo "==================================\n";
echo "The automated backup system is ready to use.\n";
echo "Run the backup script manually to test: php " . __DIR__ . "/backup.php\n";
echo "Set up cron job for automated daily backups.\n";
?>
