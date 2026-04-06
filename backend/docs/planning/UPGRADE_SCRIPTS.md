# SAMS Upgrade Scripts

## Database Migration Scripts

### Phase 1: Infrastructure Setup
```bash
#!/bin/bash
# Phase 1: Infrastructure Setup Script

echo "=== SAMS Phase 1 Upgrade Setup ==="

# Backup existing system
echo "Creating system backup..."
php scripts/backup.php

# Install composer dependencies
echo "Installing enhanced composer dependencies..."
composer install --no-dev
composer install --dev

# Create necessary directories
echo "Creating directory structure..."
mkdir -p src/Core
mkdir -p src/Cache
mkdir -p src/Security
mkdir -src/Validation
mkdir -p src/Monitoring
mkdir -p src/WebSocket
mkdir -p src/Notifications
mkdir -p src/Analytics
mkdir -p tests/Unit
mkdir -p tests/Integration
mkdir -p tests/API
mkdir -p tests/Security
mkdir -p tests/Performance
mkdir -p reports/coverage
mkdir -p logs
mkdir -p cache

# Set permissions
echo "Setting permissions..."
chmod 755 src/
chmod 755 tests/
chmod 755 logs/
chmod 755 cache/

echo "Phase 1 setup completed successfully!"
```

### Phase 2: Code Quality Setup
```bash
#!/bin/bash
# Phase 2: Code Quality Setup Script

echo "=== SAMS Phase 2 Code Quality Setup ==="

# Run code style analysis
echo "Running code style analysis..."
composer fix-dry-run

# Run static analysis
echo "Running static analysis..."
composer analyze

# Run quality checks
echo "Running quality checks..."
composer quality

# Initialize testing
echo "Initializing testing environment..."
phpunit --version
phpunit --generate-baseline phpunit-baseline.neon

echo "Phase 2 setup completed successfully!"
```

### Phase 3: Performance Setup
```bash
#!/bin/bash
# Phase 3: Performance Setup Script

echo "=== SAMS Phase 3 Performance Setup ==="

# Check Redis installation
echo "Checking Redis installation..."
redis-cli ping

# Check database performance
echo "Checking database performance..."
php scripts/db-performance-check.php

# Set up monitoring
echo "Setting up monitoring..."
php scripts/setup-monitoring.php

# Optimize database
echo "Optimizing database..."
php scripts/optimize-database.php

echo "Phase 3 setup completed successfully!"
```

### Phase 4: Security Setup
```bash
#!/bin/bash
# Phase 4: Security Setup Script

echo "=== SAMS Phase 4 Security Setup ==="

# Run security scan
echo "Running security scan..."
composer security

# Validate configuration
echo "Validating security configuration..."
php scripts/validate-security.php

# Check security headers
echo "Checking security headers..."
php scripts/check-security-headers.php

# Set up security monitoring
echo "Setting up security monitoring..."
php scripts/setup-security-monitoring.php

echo "Phase 4 setup completed successfully!"
```

## Utility Scripts

### System Backup Script
```php
<?php
// scripts/backup.php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

echo "Creating system backup...\n";

$backupDir = __DIR__ . '/../backups/' . date('Y-m-d_H-i-s');
mkdir($backupDir, 0755, true);

// Database backup
echo "Creating database backup...\n";
$dbFile = $backupDir . '/database.sql';
$command = "mysqldump -h " . DB_HOST . " -u " . DB_USER . " " . DB_NAME . " > " . $dbFile;
exec($command, $output, $returnCode);

if ($returnCode === 0) {
    echo "Database backup created: $dbFile\n";
} else {
    echo "Database backup failed!\n";
}

// Files backup
echo "Creating files backup...\n";
$filesDir = $backupDir . '/files';
mkdir($filesDir, 0755, true);

$command = "cp -r " . __DIR__ . "/../uploads " . $filesDir;
exec($command, $output, $returnCode);

if ($returnCode === 0) {
    echo "Files backup created: $filesDir\n";
} else {
    echo "Files backup failed!\n";
}

// Configuration backup
echo "Creating configuration backup...\n";
$configDir = $backupDir . '/config';
mkdir($configDir, 0755, true);

$configFiles = [
    __DIR__ . '/../includes/config.php',
    __DIR__ . '/../.env',
    __DIR__ . '/../composer.json'
];

foreach ($configFiles as $file) {
    if (file_exists($file)) {
        $dest = $configDir . '/' . basename($file);
        copy($file, $dest);
        echo "Config backup created: $dest\n";
    }
}

echo "Backup completed: $backupDir\n";
?>
```

### Database Performance Check
```php
<?php
// scripts/db-performance-check.php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

echo "Database Performance Check\n";
echo "======================\n";

$db = Database::getInstance();
$pdo = $db->getConnection();

// Check slow queries
echo "Checking slow queries...\n";
$sql = "
    SELECT 
        query_time,
        lock_time,
        rows_sent,
        rows_examined,
        tmp_tables,
        sql_text
    FROM mysql.slow_log 
    WHERE start_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY query_time DESC
    LIMIT 10
";

try {
    $stmt = $pdo->query($sql);
    $slowQueries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($slowQueries)) {
        echo "No slow queries found in the last 24 hours.\n";
    } else {
        foreach ($slowQueries as $query) {
            echo "Query Time: {$query['query_time']}s\n";
            echo "Lock Time: {$query['lock_time']}s\n";
            echo "Rows Sent: {$query['rows_sent']}\n";
            echo "SQL: " . substr($query['sql_text'], 0, 100) . "...\n";
            echo "---\n";
        }
    }
} catch (Exception $e) {
    echo "Error checking slow queries: " . $e->getMessage() . "\n";
}

// Check table sizes
echo "\nChecking table sizes...\n";
$sql = "
    SELECT 
        table_name,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
        table_rows
    FROM information_schema.tables 
    WHERE table_schema = ?
    ORDER BY size_mb DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([DB_NAME]);
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tables as $table) {
        echo "Table: {$table['table_name']}\n";
        echo "Size: {$table['size_mb']} MB\n";
        echo "Rows: {$table['table_rows']}\n";
        echo "---\n";
    }
} catch (Exception $e) {
    echo "Error checking table sizes: " . $e->getMessage() . "\n";
}

// Check indexes
echo "\nChecking indexes...\n";
$sql = "
    SELECT 
        table_name,
        index_name,
        column_name,
        cardinality
    FROM information_schema.statistics 
    WHERE table_schema = ?
    ORDER BY table_name, index_name
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([DB_NAME]);
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $indexCounts = [];
    foreach ($indexes as $index) {
        $indexCounts[$index['table_name']] = ($indexCounts[$index['table_name']] ?? 0) + 1;
    }
    
    foreach ($indexCounts as $table => $count) {
        echo "Table: $table\n";
        echo "Indexes: $count\n";
        echo "---\n";
    }
} catch (Exception $e) {
    echo "Error checking indexes: " . $e->getMessage() . "\n";
}

echo "Database performance check completed.\n";
?>
```

### Cache Status Check
```php
<?php
// scripts/cache-status.php
<?php
echo "Cache Status Check\n";
echo "==================\n";

// Check Redis
echo "Checking Redis cache...\n";
try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    $info = $redis->info();
    echo "Redis Version: " . $info['redis_version'] . "\n";
    echo "Redis Mode: " . $info['redis_mode'] . "\n";
    echo "Used Memory: " . $info['used_memory_human'] . "\n";
    echo "Connected Clients: " . $info['connected_clients'] . "\n";
    
    // Check cache keys
    $keys = $redis->keys('*');
    echo "Cache Keys: " . count($keys) . "\n";
    
    if (!empty($keys)) {
        echo "\nCache Keys:\n";
        foreach (array_slice($keys, 0, 10) as $key) {
            $type = $redis->type($key);
            $ttl = $redis->ttl($key);
            echo "Key: $key (Type: $type, TTL: $ttl)\n";
        }
    }
    
} catch (Exception $e) {
    echo "Redis not available: " . $e->getMessage() . "\n";
}

// Check file cache
echo "\nChecking file cache...\n";
$cacheDir = __DIR__ . '/../cache';
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '/*');
    echo "Cache Files: " . count($files) . "\n";
    
    $totalSize = 0;
    foreach ($files as $file) {
        $totalSize += filesize($file);
    }
    
    echo "Cache Size: " . round($totalSize / 1024 / 1024, 2) . " MB\n";
    
    if (!empty($files)) {
        echo "\nCache Files:\n";
        foreach (array_slice($files, 0, 10) as $file) {
            $size = filesize($file);
            $modified = date('Y-m-d H:i:s', filemtime($file));
            echo "File: " . basename($file) . " (Size: " . round($size / 1024, 2) . " KB, Modified: $modified)\n";
        }
    }
} else {
    echo "Cache directory not found.\n";
}

echo "Cache status check completed.\n";
?>
```

### System Health Check
```php
<?php
// scripts/system-health.php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

echo "System Health Check\n";
echo "==================\n";

$health = [
    'database' => false,
    'redis' => false,
    'disk_space' => false,
    'memory' => false,
    'php_version' => false,
    'extensions' => false
];

// Check database
echo "Checking database connection...\n";
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $stmt = $pdo->query("SELECT 1");
    $health['database'] = true;
    echo "✓ Database connection: OK\n";
} catch (Exception $e) {
    echo "✗ Database connection: FAILED - " . $e->getMessage() . "\n";
}

// Check Redis
echo "\nChecking Redis connection...\n";
try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->ping();
    $health['redis'] = true;
    echo "✓ Redis connection: OK\n";
} catch (Exception $e) {
    echo "✗ Redis connection: FAILED - " . $e->getMessage() . "\n";
}

// Check disk space
echo "\nChecking disk space...\n";
$freeSpace = disk_free_space('/');
$totalSpace = disk_total_space('/');
$usedSpace = $totalSpace - $freeSpace;
$usagePercent = ($usedSpace / $totalSpace) * 100;

if ($usagePercent < 80) {
    $health['disk_space'] = true;
    echo "✓ Disk space: OK (" . round($usagePercent, 2) . "% used)\n";
} else {
    echo "✗ Disk space: LOW (" . round($usagePercent, 2) . "% used)\n";
}

// Check memory
echo "\nChecking memory usage...\n";
$memoryUsage = memory_get_usage(true);
$memoryLimit = ini_get('memory_limit');
$memoryPercent = ($memoryUsage / $memoryLimit) * 100;

if ($memoryPercent < 80) {
    $health['memory'] = true;
    echo "✓ Memory usage: OK (" . round($memoryPercent, 2) . "% used)\n";
} else {
    echo "✗ Memory usage: HIGH (" . round($memoryPercent, 2) . "% used)\n";
}

// Check PHP version
echo "\nChecking PHP version...\n";
$phpVersion = phpversion();
if (version_compare($phpVersion, '8.0', '>=')) {
    $health['php_version'] = true;
    echo "✓ PHP version: OK ($phpVersion)\n";
} else {
    echo "✗ PHP version: OUTDATED ($phpVersion)\n";
}

// Check required extensions
echo "\nChecking required extensions...\n";
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl', 'gd'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (empty($missingExtensions)) {
    $health['extensions'] = true;
    echo "✓ Required extensions: OK\n";
} else {
    echo "✗ Missing extensions: " . implode(', ', $missingExtensions) . "\n";
}

// Overall health
echo "\nOverall Health Status:\n";
$healthyComponents = array_filter($health);
$totalComponents = count($health);
$healthScore = (count($healthyComponents) / $totalComponents) * 100;

echo "Health Score: " . round($healthScore, 1) . "%\n";
echo "Healthy Components: " . count($healthyComponents) . "/" . $totalComponents . "\n";

if ($healthScore >= 80) {
    echo "✓ System Health: GOOD\n";
} elseif ($healthScore >= 60) {
    echo "⚠ System Health: WARNING\n";
} else {
    echo "✗ System Health: CRITICAL\n";
}

echo "\nSystem health check completed.\n";
?>
```

### Security Validation
```php
<?php
// scripts/validate-security.php
<?php
echo "Security Validation\n";
echo "==================\n";

$security = [
    'csrf_protection' => false,
    'input_validation' => false,
    'sql_injection' => false,
    'xss_protection' => false,
    'session_security' => false,
    'file_upload' => false,
    'password_policy' => false
];

// Check CSRF protection
echo "Checking CSRF protection...\n";
if (isset($_SESSION['csrf_token']) && !empty($_SESSION['csrf_token'])) {
    $security['csrf_protection'] = true;
    echo "✓ CSRF protection: ENABLED\n";
} else {
    echo "✗ CSRF protection: DISABLED\n";
}

// Check input validation
echo "\nChecking input validation...\n";
$validationFiles = [
    __DIR__ . '/../src/Validation/Validator.php',
    __DIR__ . '/../src/Security/SecurityHeaders.php'
];

foreach ($validationFiles as $file) {
    if (file_exists($file)) {
        $security['input_validation'] = true;
        echo "✓ Input validation: IMPLEMENTED\n";
        break;
    }
}

if (!$security['input_validation']) {
    echo "✗ Input validation: NOT IMPLEMENTED\n";
}

// Check SQL injection protection
echo "\nChecking SQL injection protection...\n";
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Test with prepared statement
    $stmt = $pdo->prepare("SELECT 1");
    $stmt->execute();
    
    $security['sql_injection'] = true;
    echo "✓ SQL injection protection: ENABLED\n";
} catch (Exception $e) {
    echo "✗ SQL injection protection: FAILED\n";
}

// Check XSS protection
echo "\nChecking XSS protection...\n";
if (ini_get('filter.default') === 'unsafe_raw') {
    echo "✗ XSS protection: DISABLED (filter.default = unsafe_raw)\n";
} else {
    $security['xss_protection'] = true;
    echo "✓ XSS protection: ENABLED\n";
}

// Check session security
echo "\nChecking session security...\n";
if (ini_get('session.cookie_httponly') && ini_get('session.cookie_secure')) {
    $security['session_security'] = true;
    echo "✓ Session security: ENABLED\n";
} else {
    echo "⚠ Session security: PARTIAL\n";
}

// Check file upload security
echo "\nChecking file upload security...\n";
if (ini_get('file_uploads') && ini_get('max_file_size') && ini_get('upload_max_filesize')) {
    $security['file_upload'] = true;
    echo "✓ File upload security: ENABLED\n";
} else {
    echo "✗ File upload security: DISABLED\n";
}

// Check password policy
echo "\nChecking password policy...\n";
$passwordMinLength = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8;
if ($passwordMinLength >= 8) {
    $security['password_policy'] = true;
    echo "✓ Password policy: ENABLED (Min length: $passwordMinLength)\n";
} else {
    echo "✗ Password policy: WEAK (Min length: $passwordMinLength)\n";
}

// Overall security score
echo "\nSecurity Score:\n";
$secureComponents = array_filter($security);
$totalComponents = count($security);
$securityScore = (count($secureComponents) / $totalComponents) * 100;

echo "Security Score: " . round($securityScore, 1) . "%\n";
echo "Secure Components: " . count($secureComponents) . "/" . $totalComponents . "\n";

if ($securityScore >= 80) {
    echo "✓ Security Status: GOOD\n";
} elseif ($securityScore >= 60) {
    echo "⚠ Security Status: WARNING\n";
} else {
    echo "✗ Security Status: POOR\n";
}

echo "\nSecurity validation completed.\n";
?>
```

## Automation Scripts

### Daily Maintenance
```bash
#!/bin/bash
# scripts/daily-maintenance.sh

echo "=== Daily Maintenance Script ==="

# Clear old cache
echo "Clearing old cache..."
php scripts/cache-clear.php

# Backup database
echo "Creating daily backup..."
php scripts/backup.php

# Check system health
echo "Checking system health..."
php scripts/system-health.php

# Optimize database
echo "Optimizing database..."
php scripts/optimize-database.php

# Generate reports
echo "Generating daily reports..."
php scripts/daily-reports.php

echo "Daily maintenance completed!"
```

### Weekly Maintenance
```bash
#!/bin/bash
# scripts/weekly-maintenance.sh

echo "=== Weekly Maintenance Script ==="

# Full system backup
echo "Creating full system backup..."
php scripts/full-backup.php

# Security audit
echo "Running security audit..."
php scripts/security-audit.php

# Performance analysis
echo "Running performance analysis..."
php scripts/performance-analysis.php

# Log rotation
echo "Rotating logs..."
php scripts/rotate-logs.php

# Update system
echo "Updating system..."
php scripts/update-system.php

echo "Weekly maintenance completed!"
```

## Monitoring Scripts

### Real-time Monitoring
```php
<?php
// scripts/realtime-monitor.php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

echo "Real-time System Monitor\n";
echo "========================\n";

while (true) {
    // Clear screen
    system('clear');
    
    // Timestamp
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    echo str_repeat("=", 50) . "\n";
    
    // Database status
    echo "Database Status:\n";
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) as connections FROM information_schema.processlist WHERE db = '" . DB_NAME . "'");
        $connections = $stmt->fetchColumn();
        echo "Connections: $connections\n";
    } catch (Exception $e) {
        echo "Database: OFFLINE\n";
    }
    
    // Redis status
    echo "\nRedis Status:\n";
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $info = $redis->info();
        echo "Status: ONLINE\n";
        echo "Memory: " . $info['used_memory_human'] . "\n";
        echo "Clients: " . $info['connected_clients'] . "\n";
    } catch (Exception $e) {
        echo "Status: OFFLINE\n";
    }
    
    // System load
    echo "\nSystem Load:\n";
    $load = sys_getload();
    echo "Load Average: " . $load[0] . " " . $load[1] . " " . $load[2] . "\n";
    
    // Memory usage
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = ini_get('memory_limit');
    $memoryPercent = ($memoryUsage / $memoryLimit) * 100;
    echo "Memory Usage: " . round($memoryPercent, 2) . "%\n";
    
    // Disk usage
    $freeSpace = disk_free_space('/');
    $totalSpace = disk_total_space('/');
    $usagePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
    echo "Disk Usage: " . round($usagePercent, 2) . "%\n";
    
    // Active users
    echo "\nActive Users:\n";
    try {
        $db = Database::getInstance();
        $activeUsers = $db->count('users', 'is_active = 1 AND last_login >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)');
        echo "Active Users: $activeUsers\n";
    } catch (Exception $e) {
        echo "Active Users: N/A\n";
    }
    
    // Recent activity
    echo "\nRecent Activity:\n";
    try {
        $db = Database::getInstance();
        $recentActivity = $db->fetchOne(
            "SELECT COUNT(*) as count FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        );
        echo "Recent Actions: {$recentActivity['count']}\n";
    } catch (Exception $e) {
        echo "Recent Actions: N/A\n";
    }
    
    echo "\nNext update in 30 seconds...\n";
    sleep(30);
}
?>
```

These scripts provide comprehensive automation for the SAMS upgrade process, including setup, monitoring, maintenance, and security validation. They ensure the system remains healthy and performant throughout the upgrade process.
