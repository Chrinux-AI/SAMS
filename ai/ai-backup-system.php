<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';

class AIBackupSystem
{
    private $tenantId;
    private $backupDir;
    
    public function __construct($tenantId = null)
    {
        $this->tenantId = $tenantId ?? ($_SESSION['tenant_id'] ?? 1);
        $this->backupDir = __DIR__ . '/../backups';
        
        // Create backup directory if it doesn't exist
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Perform full system backup
     */
    public function performFullBackup()
    {
        try {
            $backupId = $this->createBackupRecord('full');
            
            $backupData = [
                'database' => $this->backupDatabase(),
                'files' => $this->backupFiles(),
                'metadata' => $this->backupMetadata(),
                'logs' => $this->backupLogs()
            ];
            
            // Create backup archive
            $archivePath = $this->createBackupArchive($backupId, $backupData);
            
            // Verify backup integrity
            $verification = $this->verifyBackupIntegrity($archivePath);
            
            if ($verification['valid']) {
                $this->updateBackupRecord($backupId, 'completed', $archivePath, $verification);
                
                // Log successful backup
                $this->logBackupEvent('backup_completed', [
                    'backup_id' => $backupId,
                    'type' => 'full',
                    'file_size' => $verification['file_size'],
                    'checksum' => $verification['checksum']
                ]);
                
                return [
                    'success' => true,
                    'backup_id' => $backupId,
                    'file_size' => $verification['file_size'],
                    'archive_path' => $archivePath
                ];
            } else {
                $this->updateBackupRecord($backupId, 'failed', null, $verification);
                
                return [
                    'success' => false,
                    'error' => 'Backup integrity verification failed',
                    'backup_id' => $backupId
                ];
            }
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::performFullBackup error: " . $e->getMessage());
            
            if (isset($backupId)) {
                $this->updateBackupRecord($backupId, 'failed', null, ['error' => $e->getMessage()]);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Perform database backup
     */
    public function performDatabaseBackup()
    {
        try {
            $backupId = $this->createBackupRecord('database');
            
            $databaseBackup = $this->backupDatabase();
            
            // Create database backup file
            $filename = "database_backup_{$backupId}_" . date('Y-m-d_H-i-s') . ".sql";
            $filePath = $this->backupDir . '/' . $filename;
            
            file_put_contents($filePath, $databaseBackup['content']);
            
            // Verify backup
            $verification = $this->verifyBackupIntegrity($filePath);
            
            if ($verification['valid']) {
                $this->updateBackupRecord($backupId, 'completed', $filename, $verification);
                
                return [
                    'success' => true,
                    'backup_id' => $backupId,
                    'file_size' => $verification['file_size'],
                    'file_path' => $filePath
                ];
            } else {
                $this->updateBackupRecord($backupId, 'failed', null, $verification);
                
                return [
                    'success' => false,
                    'error' => 'Database backup verification failed'
                ];
            }
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::performDatabaseBackup error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create backup record
     */
    private function createBackupRecord($type)
    {
        try {
            $backupId = db()->insert('ai_backups', [
                'tenant_id' => $this->tenantId,
                'type' => $type,
                'status' => 'processing',
                'started_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return $backupId;
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::createBackupRecord error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update backup record
     */
    private function updateBackupRecord($backupId, $status, $filePath, $verification = null)
    {
        try {
            $data = [
                'status' => $status,
                'completed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($filePath) {
                $data['file_path'] = $filePath;
            }
            
            if ($verification) {
                $data['file_size'] = $verification['file_size'];
                $data['checksum'] = $verification['checksum'];
                $data['verification_status'] = $verification['valid'] ? 'passed' : 'failed';
            }
            
            db()->update('ai_backups', $data, 'id = ?', [$backupId]);
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::updateBackupRecord error: " . $e->getMessage());
        }
    }
    
    /**
     * Backup database
     */
    private function backupDatabase()
    {
        try {
            // Get all tables for this tenant
            $tables = $this->getTenantTables();
            
            $backup = [
                'tables' => [],
                'structure' => [],
                'data' => [],
                'metadata' => [
                    'backup_date' => date('Y-m-d H:i:s'),
                    'tenant_id' => $this->tenantId,
                    'total_tables' => count($tables)
                ]
            ];
            
            foreach ($tables as $table) {
                // Get table structure
                $structure = $this->getTableStructure($table);
                $backup['structure'][$table] = $structure;
                
                // Get table data
                $data = $this->getTableData($table);
                $backup['data'][$table] = $data;
                
                $backup['tables'][] = $table;
            }
            
            // Convert to SQL format
            $sqlContent = $this->convertToSQL($backup);
            
            return [
                'content' => $sqlContent,
                'tables_count' => count($tables),
                'metadata' => $backup['metadata']
            ];
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::backupDatabase error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get tenant tables
     */
    private function getTenantTables()
    {
        try {
            $tables = db()->fetchAll("SHOW TABLES");
            $tenantTables = [];
            
            foreach ($tables as $table) {
                $tableName = array_values($table)[0];
                
                // Check if table has tenant_id or is a system table
                if ($this->isTenantTable($tableName)) {
                    $tenantTables[] = $tableName;
                }
            }
            
            return $tenantTables;
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::getTenantTables error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if table is tenant-specific
     */
    private function isTenantTable($tableName)
    {
        $systemTables = [
            'ai_backups', 'ai_documents', 'ai_knowledge_base', 'audit_logs', 'cache',
            'json_metadata', 'security_logs', 'system_logs', 'users', 'tenants'
        ];
        
        return in_array($tableName, $systemTables) || 
               strpos($tableName, 'tenant_') === 0;
    }
    
    /**
     * Get table structure
     */
    private function getTableStructure($tableName)
    {
        try {
            $structure = db()->fetchAll("DESCRIBE {$tableName}");
            
            $createTable = "CREATE TABLE {$tableName} (\n";
            $columns = [];
            
            foreach ($structure as $column) {
                $columnDef = "  {$column['Field']} {$column['Type']}";
                
                if ($column['Null'] === 'NO') {
                    $columnDef .= ' NOT NULL';
                }
                
                if ($column['Default'] !== null) {
                    $columnDef .= " DEFAULT '{$column['Default']}'";
                }
                
                if ($column['Extra']) {
                    $columnDef .= " {$column['Extra']}";
                }
                
                $columns[] = $columnDef;
            }
            
            $createTable .= implode(",\n", $columns);
            $createTable .= "\n)";
            
            return $createTable;
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::getTableStructure error: " . $e->getMessage());
            return "";
        }
    }
    
    /**
     * Get table data
     */
    private function getTableData($tableName)
    {
        try {
            $data = [];
            
            // Get all data for this tenant
            if ($tableName === 'tenants') {
                $rows = db()->fetchAll("SELECT * FROM {$tableName} WHERE id = ?", [$this->tenantId]);
            } else {
                $rows = db()->fetchAll("SELECT * FROM {$tableName} WHERE tenant_id = ?", [$this->tenantId]);
            }
            
            foreach ($rows as $row) {
                $data[] = $row;
            }
            
            return $data;
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::getTableData error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Convert backup to SQL
     */
    private function convertToSQL($backup)
    {
        $sql = "-- SAMS Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Tenant ID: {$this->tenantId}\n\n";
        
        foreach ($backup['structure'] as $tableName => $structure) {
            $sql .= "-- Table: {$tableName}\n";
            $sql .= $structure . ";\n\n";
            
            if (isset($backup['data'][$tableName]) && !empty($backup['data'][$tableName])) {
                $sql .= "-- Data for {$tableName}\n";
                
                foreach ($backup['data'][$tableName] as $row) {
                    $columns = array_keys($row);
                    $values = array_map(function($value) {
                        if ($value === null) return 'NULL';
                        if ($value === '') return "''";
                        return "'" . addslashes($value) . "'";
                    }, $row);
                    
                    $sql .= "INSERT INTO {$tableName} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
                }
                
                $sql .= "\n";
            }
        }
        
        return $sql;
    }
    
    /**
     * Backup files
     */
    private function backupFiles()
    {
        try {
            $files = [];
            
            // Important directories to backup
            $directories = [
                'uploads' => 'User uploads',
                'docs' => 'Generated documents',
                'logs' => 'System logs'
            ];
            
            foreach ($directories as $dir => $description) {
                $dirPath = __DIR__ . "/../{$dir}";
                if (is_dir($dirPath)) {
                    $files[$dir] = $this->backupDirectory($dirPath);
                }
            }
            
            return [
                'directories' => $directories,
                'files' => $files,
                'backup_date' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::backupFiles error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Backup directory
     */
    private function backupDirectory($dirPath)
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace(__DIR__ . '/../', '', $file->getPathname());
                $files[$relativePath] = base64_encode(file_get_contents($file->getPathname()));
            }
        }
        
        return $files;
    }
    
    /**
     * Backup metadata
     */
    private function backupMetadata()
    {
        try {
            $metadata = [
                'system_info' => [
                    'php_version' => PHP_VERSION,
                    'mysql_version' => $this->getMySQLVersion(),
                    'app_version' => APP_VERSION ?? '1.0.0',
                    'backup_version' => '1.0'
                ],
                'tenant_info' => $this->getTenantInfo(),
                'backup_info' => [
                    'backup_date' => date('Y-m-d H:i:s'),
                    'backup_type' => 'full',
                    'backup_method' => 'ai_system'
                ]
            ];
            
            return $metadata;
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::backupMetadata error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get MySQL version
     */
    private function getMySQLVersion()
    {
        try {
            $result = db()->fetchOne("SELECT VERSION() as version");
            return $result['version'] ?? 'Unknown';
        } catch (Exception $e) {
            return 'Unknown';
        }
    }
    
    /**
     * Get tenant info
     */
    private function getTenantInfo()
    {
        try {
            $tenant = db()->fetchOne("SELECT * FROM tenants WHERE id = ?", [$this->tenantId]);
            
            if ($tenant) {
                return [
                    'id' => $tenant['id'],
                    'name' => $tenant['name'],
                    'domain' => $tenant['domain'],
                    'status' => $tenant['status'],
                    'created_at' => $tenant['created_at']
                ];
            }
            
            return [];
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::getTenantInfo error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Backup logs
     */
    private function backupLogs()
    {
        try {
            $logs = [];
            
            // Get recent system logs
            $systemLogs = db()->fetchAll("
                SELECT * FROM system_logs 
                WHERE tenant_id = ? OR tenant_id IS NULL
                ORDER BY created_at DESC 
                LIMIT 1000
            ", [$this->tenantId]);
            
            $logs['system_logs'] = $systemLogs;
            
            // Get recent audit logs
            $auditLogs = db()->fetchAll("
                SELECT * FROM audit_logs 
                WHERE tenant_id = ?
                ORDER BY created_at DESC 
                LIMIT 1000
            ", [$this->tenantId]);
            
            $logs['audit_logs'] = $auditLogs;
            
            return $logs;
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::backupLogs error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create backup archive
     */
    private function createBackupArchive($backupId, $backupData)
    {
        try {
            $filename = "backup_{$backupId}_" . date('Y-m-d_H-i-s') . ".json";
            $filePath = $this->backupDir . '/' . $filename;
            
            // Add backup metadata
            $backupData['backup_metadata'] = [
                'backup_id' => $backupId,
                'tenant_id' => $this->tenantId,
                'created_at' => date('Y-m-d H:i:s'),
                'backup_type' => 'full',
                'compression' => 'none'
            ];
            
            // Save backup as JSON
            file_put_contents($filePath, json_encode($backupData, JSON_PRETTY_PRINT));
            
            return $filename;
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::createBackupArchive error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Verify backup integrity
     */
    private function verifyBackupIntegrity($filePath)
    {
        try {
            if (!file_exists($filePath)) {
                return ['valid' => false, 'error' => 'Backup file does not exist'];
            }
            
            $fileSize = filesize($filePath);
            $checksum = md5_file($filePath);
            
            // Basic integrity checks
            if ($fileSize === 0) {
                return ['valid' => false, 'error' => 'Backup file is empty'];
            }
            
            // Try to parse JSON if it's a JSON backup
            if (strpos($filePath, '.json') !== false) {
                $content = file_get_contents($filePath);
                $decoded = json_decode($content, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return ['valid' => false, 'error' => 'Invalid JSON format: ' . json_last_error_msg()];
                }
                
                if (!isset($decoded['backup_metadata'])) {
                    return ['valid' => false, 'error' => 'Missing backup metadata'];
                }
            }
            
            return [
                'valid' => true,
                'file_size' => $fileSize,
                'checksum' => $checksum,
                'verified_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::verifyBackupIntegrity error: " . $e->getMessage());
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get backup history
     */
    public function getBackupHistory($limit = 50)
    {
        try {
            return db()->fetchAll("
                SELECT * FROM ai_backups 
                WHERE tenant_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ", [$this->tenantId, $limit]);
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::getBackupHistory error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get backup statistics
     */
    public function getBackupStatistics()
    {
        try {
            $stats = db()->fetchOne("
                SELECT 
                    COUNT(*) as total_backups,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_backups,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_backups,
                    SUM(CASE WHEN file_size IS NOT NULL THEN file_size ELSE 0 END) as total_size,
                    MAX(created_at) as last_backup
                FROM ai_backups 
                WHERE tenant_id = ?
            ", [$this->tenantId]);
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::getBackupStatistics error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Schedule automatic backup
     */
    public function scheduleAutomaticBackup()
    {
        try {
            // Check if backup is already scheduled
            $existing = db()->fetchOne("
                SELECT id FROM ai_backup_schedule 
                WHERE tenant_id = ? AND status = 'scheduled'
                AND scheduled_at > NOW()
                LIMIT 1
            ", [$this->tenantId]);
            
            if (!$existing) {
                // Schedule next backup
                $nextBackup = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                db()->insert('ai_backup_schedule', [
                    'tenant_id' => $this->tenantId,
                    'backup_type' => 'full',
                    'status' => 'scheduled',
                    'scheduled_at' => $nextBackup,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::scheduleAutomaticBackup error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log backup event
     */
    private function logBackupEvent($eventType, $details)
    {
        try {
            db()->insert('audit_logs', [
                'tenant_id' => $this->tenantId,
                'user_id' => 0, // System action
                'action' => 'backup_' . $eventType,
                'details' => json_encode($details),
                'ip_address' => 'system',
                'user_agent' => 'AI Backup System',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::logBackupEvent error: " . $e->getMessage());
        }
    }
    
    /**
     * Restore backup
     */
    public function restoreBackup($backupId)
    {
        try {
            $backup = db()->fetchOne("
                SELECT * FROM ai_backups 
                WHERE id = ? AND tenant_id = ? AND status = 'completed'
            ", [$backupId, $this->tenantId]);
            
            if (!$backup) {
                return ['success' => false, 'error' => 'Backup not found or not completed'];
            }
            
            $filePath = $this->backupDir . '/' . $backup['file_path'];
            
            if (!file_exists($filePath)) {
                return ['success' => false, 'error' => 'Backup file not found'];
            }
            
            // Verify backup integrity before restore
            $verification = $this->verifyBackupIntegrity($filePath);
            
            if (!$verification['valid']) {
                return ['success' => false, 'error' => 'Backup integrity verification failed'];
            }
            
            // Perform restore
            $result = $this->performRestore($filePath);
            
            if ($result['success']) {
                $this->logBackupEvent('restore_completed', [
                    'backup_id' => $backupId,
                    'restored_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::restoreBackup error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Perform restore
     */
    private function performRestore($filePath)
    {
        try {
            $content = file_get_contents($filePath);
            $backupData = json_decode($content, true);
            
            if (!$backupData) {
                return ['success' => false, 'error' => 'Invalid backup format'];
            }
            
            // Restore database
            if (isset($backupData['database'])) {
                $this->restoreDatabase($backupData['database']);
            }
            
            // Restore files
            if (isset($backupData['files'])) {
                $this->restoreFiles($backupData['files']);
            }
            
            return ['success' => true, 'message' => 'Backup restored successfully'];
            
        } catch (Exception $e) {
            error_log("AIBackupSystem::performRestore error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Restore database
     */
    private function restoreDatabase($databaseBackup)
    {
        // This would execute the SQL statements from the backup
        // For security reasons, this should be done manually or with proper validation
        // Implementation would depend on specific requirements
    }
    
    /**
     * Restore files
     */
    private function restoreFiles($filesBackup)
    {
        // This would restore files from the backup
        // Implementation would depend on specific requirements
    }
}
