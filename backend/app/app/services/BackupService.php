<?php
/**
 * SAMS Automated AI Backup Service
 * Intelligent backup system with integrity verification
 * Non-invasive, performance-optimized backup operations
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

class BackupService
{
    private $db;
    private $logger;
    private $backupDir;
    private $maxBackups = 30; // Keep 30 days of backups
    private $compressionLevel = 6;
    private $aiLogger;
    private $lastBackupTimestamp;

    public function __construct()
    {
        $this->db = db();
        $this->logger = new Logger('backup_service');
        $this->aiLogger = new Logger('backup_ai');
        $this->backupDir = __DIR__ . '/../../storage/backups/';
        $this->initBackupSystem();
        $this->loadLastBackupTimestamp();
    }

    /**
     * Load last backup timestamp for incremental exports
     */
    private function loadLastBackupTimestamp()
    {
        try {
            $lastBackup = $this->db->fetchOne("
                SELECT backup_date, backup_time
                FROM backup_tracking
                WHERE backup_type = 'full' AND status = 'completed' AND verification_status = 'verified'
                ORDER BY backup_date DESC, backup_time DESC
                LIMIT 1
            ");

            if ($lastBackup) {
                $this->lastBackupTimestamp = $lastBackup['backup_date'] . ' ' . $lastBackup['backup_time'];
            } else {
                $this->lastBackupTimestamp = null;
            }
        } catch (Exception $e) {
            $this->aiLogger->error("Failed to load last backup timestamp", ['error' => $e->getMessage()]);
            $this->lastBackupTimestamp = null;
        }
    }

    /**
     * Enhanced incremental JSON export with change tracking
     */
    private function createIncrementalExport($date, $time)
    {
        $backupFile = $this->backupDir . 'exports/incremental_' . $date . '_' . str_replace(':', '', $time) . '.json';

        try {
            $exportData = [
                'export_info' => [
                    'date' => $date,
                    'time' => $time,
                    'type' => 'incremental',
                    'version' => '2.0',
                    'last_backup_timestamp' => $this->lastBackupTimestamp
                ],
                'data' => []
            ];

            // Export changed records only
            $tables = ['users', 'students', 'teachers', 'classes', 'attendance_records', 'ai_documents'];

            foreach ($tables as $table) {
                $query = "SELECT * FROM $table";
                $params = [];

                if ($this->lastBackupTimestamp) {
                    $query .= " WHERE updated_at > ? OR created_at > ?";
                    $params = [$this->lastBackupTimestamp, $this->lastBackupTimestamp];
                }

                $records = $this->db->fetchAll($query, $params);
                $exportData['data'][$table] = $records;

                $this->aiLogger->debug("Incremental export for $table", [
                    'records_count' => count($records),
                    'since_timestamp' => $this->lastBackupTimestamp
                ]);
            }

            // Compress backup
            $compressedFile = $this->compressFile(json_encode($exportData, JSON_PRETTY_PRINT), $backupFile . '.gz');

            // Create metadata
            $this->createBackupMetadata($date, $time, $exportData, $compressedFile);

            return [
                'success' => true,
                'file_path' => $compressedFile,
                'file_size' => filesize($compressedFile),
                'items_count' => array_sum(array_map('count', $exportData['data'])),
                'last_backup_timestamp' => $this->lastBackupTimestamp
            ];

        } catch (Exception $e) {
            $this->aiLogger->error("Incremental export failed", [
                'error' => $e->getMessage(),
                'last_backup_timestamp' => $this->lastBackupTimestamp
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create backup metadata file
     */
    private function createBackupMetadata($date, $time, $exportData, $filePath)
    {
        $metadata = [
            'backup_info' => [
                'date' => $date,
                'time' => $time,
                'type' => 'incremental',
                'version' => '2.0',
                'created_by' => 'SAMS AI Backup Service v2.0'
            ],
            'export_statistics' => [
                'total_records' => array_sum(array_map('count', $exportData['data'])),
                'tables_exported' => array_keys($exportData['data']),
                'file_size' => filesize($filePath),
                'compression_ratio' => $this->calculateCompressionRatio($exportData, $filePath)
            ],
            'change_tracking' => [
                'last_backup_timestamp' => $this->lastBackupTimestamp,
                'current_backup_timestamp' => $date . ' ' . $time,
                'changes_detected' => array_sum(array_map('count', $exportData['data'])) > 0
            ],
            'integrity' => [
                'checksum' => hash_file('sha256', $filePath),
                'file_size' => filesize($filePath),
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];

        $metadataFile = str_replace('.json.gz', '_metadata.json', $filePath);
        file_put_contents($metadataFile, json_encode($metadata, JSON_PRETTY_PRINT));

        $this->aiLogger->debug("Backup metadata created", [
            'metadata_file' => $metadataFile,
            'total_records' => $metadata['export_statistics']['total_records']
        ]);
    }

    /**
     * Calculate compression ratio
     */
    private function calculateCompressionRatio($data, $compressedFile)
    {
        $originalSize = strlen(json_encode($data));
        $compressedSize = filesize($compressedFile);

        if ($originalSize === 0) return 0;

        return round((($originalSize - $compressedSize) / $originalSize) * 100, 2);
    }

    /**
     * Optimized database dump with background execution
     */
    private function backupDatabase($date, $time)
    {
        $backupFile = $this->backupDir . 'database/backup_' . $date . '_' . str_replace(':', '', $time) . '.sql';

        try {
            $this->aiLogger->info("Starting optimized database dump", [
                'backup_file' => $backupFile,
                'compression_level' => $this->compressionLevel
            ]);

            // Set low CPU priority for background execution
            if (function_exists('proc_nice')) {
                proc_nice(19); // Lowest priority
            }

            $sqlContent = "-- SAMS Database Backup\n";
            $sqlContent .= "-- Date: $date $time\n";
            $sqlContent .= "-- Generated by SAMS AI Backup Service v2.0\n";
            $sqlContent .= "-- Priority: Low (Background)\n";
            $sqlContent .= "-- Compression: GZIP Level {$this->compressionLevel}\n\n";

            // Get all tables
            $tables = $this->db->fetchAll("SHOW TABLES");

            foreach ($tables as $table) {
                $tableName = array_values($table)[0];

                $this->aiLogger->debug("Dumping table: $tableName");

                // Get table structure
                $createTable = $this->db->fetchAll("SHOW CREATE TABLE `$tableName`");
                $sqlContent .= "-- Table structure for `$tableName`\n";
                $sqlContent .= $createTable[0]['Create Table'] . ";\n\n";

                // Get table data in chunks for large tables
                $rowCount = $this->db->fetchOne("SELECT COUNT(*) as count FROM `$tableName`")['count'];

                if ($rowCount > 0) {
                    $sqlContent .= "-- Data for `$tableName` ($rowCount records)\n";

                    // Process in chunks for large tables
                    $chunkSize = 1000;
                    $offset = 0;

                    while ($offset < $rowCount) {
                        $rows = $this->db->fetchAll("SELECT * FROM `$tableName` LIMIT $chunkSize OFFSET $offset");

                        foreach ($rows as $row) {
                            $values = array_map(function($value) {
                                if ($value === null) {
                                    return 'NULL';
                                } elseif ($value === '') {
                                    return "''";
                                } else {
                                    return "'" . addslashes($value) . "'";
                                }
                            }, $row);

                            $columns = array_keys($row);
                            $sqlContent .= "INSERT INTO `$tableName` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                        }

                        $offset += $chunkSize;

                        // Brief pause to allow other processes
                        if ($offset % 5000 === 0) {
                            usleep(10000); // 10ms pause every 5000 records
                        }
                    }

                    $sqlContent .= "\n";
                }
            }

            // Compress with GZIP
            $compressedFile = $this->compressFile($sqlContent, $backupFile . '.gz');

            $this->aiLogger->info("Database dump completed", [
                'file_size' => filesize($compressedFile),
                'tables_count' => count($tables)
            ]);

            return [
                'success' => true,
                'file_path' => $compressedFile,
                'file_size' => filesize($compressedFile),
                'tables_count' => count($tables)
            ];

        } catch (Exception $e) {
            $this->aiLogger->error("Database backup failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Enhanced AI integrity verification with schema validation
     */
    private function verifyBackupIntegrity($masterBackup, $backupId)
    {
        try {
            $this->aiLogger->info("Starting enhanced AI integrity verification", [
                'backup_id' => $backupId,
                'file_path' => $masterBackup['file_path']
            ]);

            $startTime = microtime(true);
            $anomalies = [];

            // Check 1: File integrity
            $fileCheck = $this->verifyFileIntegrity($masterBackup);
            if (!$fileCheck['passed']) {
                $anomalies[] = 'File integrity check failed: ' . $fileCheck['error'];
            }

            // Check 2: SHA256 checksum validation
            $checksumCheck = $this->verifyChecksum($masterBackup);
            if (!$checksumCheck['passed']) {
                $anomalies[] = 'Checksum validation failed: ' . $checksumCheck['error'];
            }

            // Check 3: File size validation
            $sizeCheck = $this->verifyFileSize($masterBackup);
            if (!$sizeCheck['passed']) {
                $anomalies[] = 'File size validation failed: ' . $sizeCheck['error'];
            }

            // Check 4: JSON schema validation
            $schemaCheck = $this->validateJsonSchema($masterBackup);
            if (!$schemaCheck['passed']) {
                $anomalies[] = 'JSON schema validation failed: ' . $schemaCheck['error'];
            }

            // Check 5: Archive structure validation
            $structureCheck = $this->verifyArchiveStructure($masterBackup);
            if (!$structureCheck['passed']) {
                $anomalies[] = 'Archive structure validation failed: ' . $structureCheck['error'];
            }

            // Check 6: Data completeness validation
            $completenessCheck = $this->verifyDataCompleteness($masterBackup);
            if (!$completenessCheck['passed']) {
                $anomalies[] = 'Data completeness validation failed: ' . $completenessCheck['error'];
            }

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            // Determine backup status
            $backupStatus = 'VALID';
            if (!empty($anomalies)) {
                if (count($anomalies) <= 2) {
                    $backupStatus = 'PARTIAL';
                } else {
                    $backupStatus = 'CORRUPTED';
                }
            }

            $verificationResult = [
                'status' => $backupStatus,
                'duration' => $duration,
                'checks' => [
                    'file_integrity' => $fileCheck,
                    'checksum_validation' => $checksumCheck,
                    'file_size_validation' => $sizeCheck,
                    'json_schema_validation' => $schemaCheck,
                    'archive_structure' => $structureCheck,
                    'data_completeness' => $completenessCheck
                ],
                'anomalies_detected' => $anomalies,
                'anomalies_count' => count($anomalies),
                'ai_analysis' => $this->performAIAnalysis($masterBackup),
                'recommendations' => $this->generateRecommendations($backupStatus, $anomalies)
            ];

            // Log AI verification results
            $this->aiLogger->info("AI integrity verification completed", [
                'backup_id' => $backupId,
                'status' => $backupStatus,
                'duration' => $duration,
                'anomalies_count' => count($anomalies),
                'anomalies' => $anomalies
            ]);

            return $verificationResult;

        } catch (Exception $e) {
            $this->aiLogger->error("AI integrity verification failed", [
                'backup_id' => $backupId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'CORRUPTED',
                'error' => $e->getMessage(),
                'message' => 'AI integrity verification failed: ' . $e->getMessage(),
                'anomalies_detected' => ['Critical verification error: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Verify SHA256 checksum
     */
    private function verifyChecksum($masterBackup)
    {
        try {
            if (!file_exists($masterBackup['file_path'])) {
                return ['passed' => false, 'error' => 'Backup file does not exist'];
            }

            $currentHash = hash_file('sha256', $masterBackup['file_path']);
            $expectedHash = $masterBackup['hash'];

            if ($currentHash !== $expectedHash) {
                return [
                    'passed' => false,
                    'error' => 'SHA256 checksum mismatch',
                    'current' => $currentHash,
                    'expected' => $expectedHash
                ];
            }

            return ['passed' => true, 'hash' => $currentHash];

        } catch (Exception $e) {
            return ['passed' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify file size
     */
    private function verifyFileSize($masterBackup)
    {
        try {
            if (!file_exists($masterBackup['file_path'])) {
                return ['passed' => false, 'error' => 'Backup file does not exist'];
            }

            $actualSize = filesize($masterBackup['file_path']);
            $expectedSize = $masterBackup['file_size'];

            if ($actualSize !== $expectedSize) {
                return [
                    'passed' => false,
                    'error' => 'File size mismatch',
                    'actual' => $actualSize,
                    'expected' => $expectedSize
                ];
            }

            return ['passed' => true, 'file_size' => $actualSize];

        } catch (Exception $e) {
            return ['passed' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Validate JSON schema
     */
    private function validateJsonSchema($masterBackup)
    {
        try {
            $tempDir = $this->backupDir . 'temp_verify_' . uniqid();
            mkdir($tempDir, 0755, true);

            // Extract archive
            $archive = new PharData($masterBackup['file_path']);
            $archive->extractTo($tempDir);

            // Validate manifest JSON
            $manifestPath = $tempDir . '/manifest.json';
            if (!file_exists($manifestPath)) {
                $this->removeDirectory($tempDir);
                return ['passed' => false, 'error' => 'Manifest JSON not found'];
            }

            $manifestContent = file_get_contents($manifestPath);
            $manifest = json_decode($manifestContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->removeDirectory($tempDir);
                return ['passed' => false, 'error' => 'Invalid JSON in manifest: ' . json_last_error_msg()];
            }

            // Validate required schema fields
            $requiredFields = ['backup_info', 'backup_results', 'statistics'];
            foreach ($requiredFields as $field) {
                if (!isset($manifest[$field])) {
                    $this->removeDirectory($tempDir);
                    return ['passed' => false, 'error' => "Missing required field: $field"];
                }
            }

            // Validate backup_info schema
            $backupInfo = $manifest['backup_info'];
            $requiredBackupInfo = ['date', 'time', 'type', 'version'];
            foreach ($requiredBackupInfo as $field) {
                if (!isset($backupInfo[$field])) {
                    $this->removeDirectory($tempDir);
                    return ['passed' => false, 'error' => "Missing backup_info field: $field"];
                }
            }

            $this->removeDirectory($tempDir);

            return ['passed' => true, 'schema_valid' => true];

        } catch (Exception $e) {
            return ['passed' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Enhanced AI analysis with anomaly detection
     */
    private function performAIAnalysis($masterBackup)
    {
        try {
            $analysis = [
                'status' => 'passed',
                'checks' => [],
                'insights' => [],
                'anomalies' => [],
                'recommendations' => []
            ];

            // AI Check 1: Backup size analysis with anomaly detection
            $sizeAnalysis = $this->analyzeBackupSizeWithAnomalies($masterBackup);
            $analysis['checks']['size_analysis'] = $sizeAnalysis;

            // AI Check 2: Compression efficiency analysis
            $compressionAnalysis = $this->analyzeCompressionEfficiency($masterBackup);
            $analysis['checks']['compression_analysis'] = $compressionAnalysis;

            // AI Check 3: Backup pattern analysis
            $patternAnalysis = $this->analyzeBackupPatterns($masterBackup);
            $analysis['checks']['pattern_analysis'] = $patternAnalysis;

            // AI Check 4: Risk assessment
            $riskAnalysis = $this->analyzeBackupRisks($masterBackup);
            $analysis['checks']['risk_analysis'] = $riskAnalysis;

            // AI Check 5: Performance analysis
            $performanceAnalysis = $this->analyzeBackupPerformance($masterBackup);
            $analysis['checks']['performance_analysis'] = $performanceAnalysis;

            // Collect anomalies
            foreach ($analysis['checks'] as $check) {
                if (isset($check['anomalies'])) {
                    $analysis['anomalies'] = array_merge($analysis['anomalies'], $check['anomalies']);
                }
            }

            // Generate insights
            $analysis['insights'] = $this->generateInsights($analysis);

            // Determine overall status
            $allChecksPassed = true;
            foreach ($analysis['checks'] as $check) {
                if (isset($check['status']) && $check['status'] === 'warning') {
                    $analysis['status'] = 'warning';
                } elseif (isset($check['status']) && $check['status'] === 'failed') {
                    $analysis['status'] = 'failed';
                    $allChecksPassed = false;
                }
            }

            if ($allChecksPassed && empty($analysis['anomalies'])) {
                $analysis['status'] = 'passed';
            }

            return $analysis;

        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'anomalies' => ['AI analysis error: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Analyze backup size with anomaly detection
     */
    private function analyzeBackupSizeWithAnomalies($masterBackup)
    {
        $fileSize = $masterBackup['file_size'];
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);

        // Get historical backup sizes for anomaly detection
        $historicalSizes = $this->db->fetchAll("
            SELECT file_size, backup_date
            FROM backup_tracking
            WHERE backup_type = 'full' AND status = 'completed'
            ORDER BY backup_date DESC
            LIMIT 30
        ");

        $anomalies = [];
        $status = 'passed';
        $message = 'Backup size is normal';

        // Calculate average and standard deviation
        if (count($historicalSizes) > 5) {
            $sizes = array_column($historicalSizes, 'file_size');
            $avgSize = array_sum($sizes) / count($sizes);
            $stdDev = sqrt(array_sum(array_map(function($x) use ($avgSize) { return pow($x - $avgSize, 2); }, $sizes)) / count($sizes));

            // Detect anomalies (more than 2 standard deviations from mean)
            $zScore = abs($fileSize - $avgSize) / $stdDev;

            if ($zScore > 2) {
                $anomalies[] = "Backup size anomaly detected (Z-score: " . round($zScore, 2) . ")";
                $status = 'warning';
                $message = 'Backup size anomaly detected';
            }
        }

        // Size thresholds
        if ($fileSizeMB > 1000) {
            $anomalies[] = 'Backup size is large (>1GB)';
            $status = 'warning';
            $message = 'Backup size is large';
        } elseif ($fileSizeMB > 2000) {
            $anomalies[] = 'Backup size is too large (>2GB)';
            $status = 'failed';
            $message = 'Backup size is too large';
        }

        return [
            'status' => $status,
            'message' => $message,
            'size_mb' => $fileSizeMB,
            'anomalies' => $anomalies,
            'recommendation' => $fileSizeMB > 1000 ? 'Consider optimizing backup size' : 'Size is acceptable'
        ];
    }

    /**
     * Analyze backup performance
     */
    private function analyzeBackupPerformance($masterBackup)
    {
        try {
            // Get recent backup performance data
            $recentBackups = $this->db->fetchAll("
                SELECT backup_date, backup_time, file_size
                FROM backup_tracking
                WHERE backup_type = 'full' AND status = 'completed'
                ORDER BY backup_date DESC, backup_time DESC
                LIMIT 10
            ");

            $anomalies = [];
            $status = 'passed';
            $message = 'Backup performance is normal';

            if (count($recentBackups) > 3) {
                // Analyze backup size trend
                $sizes = array_column($recentBackups, 'file_size');
                $avgSize = array_sum($sizes) / count($sizes);

                // Check if current backup is significantly different from average
                $currentSize = $masterBackup['file_size'];
                $deviation = abs($currentSize - $avgSize) / $avgSize * 100;

                if ($deviation > 25) {
                    $anomalies[] = "Backup size deviation: " . round($deviation, 1) . "% from average";
                    $status = 'warning';
                    $message = 'Backup performance anomaly detected';
                }
            }

            return [
                'status' => $status,
                'message' => $message,
                'recent_backups' => count($recentBackups),
                'anomalies' => $anomalies
            ];

        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'anomalies' => ['Performance analysis error: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Generate recommendations based on backup status and anomalies
     */
    private function generateRecommendations($backupStatus, $anomalies)
    {
        $recommendations = [];

        switch ($backupStatus) {
            case 'VALID':
                $recommendations[] = 'Backup is healthy and ready for restore';
                break;
            case 'PARTIAL':
                $recommendations[] = 'Backup has minor issues - verify critical data';
                $recommendations[] = 'Consider running a manual backup verification';
                break;
            case 'CORRUPTED':
                $recommendations[] = 'Backup is corrupted - immediate manual backup required';
                $recommendations[] = 'Investigate backup system integrity';
                $recommendations[] = 'Check system resources and disk space';
                break;
        }

        // Add anomaly-specific recommendations
        foreach ($anomalies as $anomaly) {
            if (strpos($anomaly, 'size') !== false) {
                $recommendations[] = 'Review backup size and optimize data retention';
            } elseif (strpos($anomaly, 'checksum') !== false) {
                $recommendations[] = 'Verify file system integrity and storage hardware';
            } elseif (strpos($anomaly, 'performance') !== false) {
                $recommendations[] = 'Monitor system resources during backup execution';
            }
        }

        return array_unique($recommendations);
    }

    /**
     * Create restore-ready archive structure
     */
    private function createMasterBackup($backupResults, $date, $time)
    {
        $backupDateDir = $this->backupDir . $date;

        try {
            // Create date-based directory
            if (!is_dir($backupDateDir)) {
                mkdir($backupDateDir, 0755, true);
            }

            $masterFile = $backupDateDir . '/sams_backup_' . $date . '.tar.gz';

            $tempDir = $this->backupDir . 'temp_master_' . uniqid();
            mkdir($tempDir, 0755, true);

            $totalSize = 0;
            $itemsCount = 0;

            // Copy all backup files to temp directory
            foreach ($backupResults as $type => $result) {
                if ($result['success'] && isset($result['file_path'])) {
                    $targetFile = $tempDir . '/' . basename($result['file_path']);
                    copy($result['file_path'], $targetFile);
                    $totalSize += $result['file_size'];
                    $itemsCount++;
                }
            }

            // Create backup manifest
            $manifest = [
                'backup_info' => [
                    'date' => $date,
                    'time' => $time,
                    'type' => 'full',
                    'version' => '2.0',
                    'created_by' => 'SAMS AI Backup Service v2.0',
                    'restore_ready' => true
                ],
                'backup_results' => $backupResults,
                'statistics' => [
                    'total_size' => $totalSize,
                    'items_count' => $itemsCount,
                    'compression_level' => $this->compressionLevel
                ],
                'restore_structure' => [
                    'database_file' => 'database/backup_' . $date . '_' . str_replace(':', '', $time) . '.sql.gz',
                    'incremental_file' => 'exports/incremental_' . $date . '_' . str_replace(':', '', $time) . '.json.gz',
                    'metadata_file' => 'exports/incremental_' . $date . '_' . str_replace(':', '', $time) . '_metadata.json',
                    'checksum_file' => 'checksum.sha256'
                ]
            ];

            file_put_contents($tempDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

            // Create master archive
            $archive = new PharData($tempDir . '/master.tar');
            $archive->buildFromDirectory($tempDir);
            $archive->compress(Phar::GZ, $this->compressionLevel);

            // Move to backup location
            rename($tempDir . '/master.tar.gz', $masterFile);

            // Create checksum file
            $checksumFile = $backupDateDir . '/checksum.sha256';
            $checksum = hash_file('sha256', $masterFile);
            file_put_contents($checksumFile, $checksum . '  sams_backup_' . $date . '.tar.gz');

            // Cleanup temp directory
            $this->removeDirectory($tempDir);

            // Calculate compression ratio
            $originalSize = $totalSize;
            $compressedSize = filesize($masterFile);
            $compressionRatio = $originalSize > 0 ? round((($originalSize - $compressedSize) / $originalSize) * 100, 2) : 0;

            // Calculate hash
            $hash = hash('sha256', file_get_contents($masterFile));

            $this->aiLogger->info("Restore-ready archive created", [
                'backup_date' => $date,
                'archive_path' => $masterFile,
                'checksum_file' => $checksumFile,
                'file_size' => $compressedSize,
                'compression_ratio' => $compressionRatio
            ]);

            return [
                'success' => true,
                'file_path' => $masterFile,
                'file_size' => $compressedSize,
                'original_size' => $originalSize,
                'compression_ratio' => $compressionRatio,
                'hash' => $hash,
                'items_count' => $itemsCount,
                'checksum_file' => $checksumFile,
                'restore_ready' => true
            ];

        } catch (Exception $e) {
            $this->aiLogger->error("Restore-ready archive creation failed", [
                'backup_date' => $date,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Initialize backup system
     */
    private function initBackupSystem()
    {
        // Create backup directory if it doesn't exist
        if (!is_dir($this->backupDir)) {
            if (!mkdir($this->backupDir, 0755, true)) {
                throw new Exception("Failed to create backup directory: {$this->backupDir}");
            }
        }

        // Create subdirectories
        $subdirs = ['database', 'files', 'config', 'logs', 'exports'];
        foreach ($subdirs as $subdir) {
            $path = $this->backupDir . $subdir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        // Create backup tracking table
        $this->initBackupTrackingTable();
    }

    /**
     * Initialize backup tracking table
     */
    private function initBackupTrackingTable()
    {
        $sql = "
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

        $this->db->createTable($sql);
    }

    /**
     * Perform complete backup
     */
    public function performBackup($options = [])
    {
        $backupStartTime = microtime(true);
        $backupDate = date('Y-m-d');
        $backupTime = date('H:i:s');

        try {
            $this->logger->info("Starting backup process", [
                'date' => $backupDate,
                'time' => $backupTime
            ]);

            // Create backup tracking record
            $backupId = $this->createBackupTrackingRecord($backupDate, $backupTime);

            // Perform different types of backups
            $backupResults = [];

            // Database backup
            $backupResults['database'] = $this->backupDatabase($backupDate, $backupTime);

            // Files backup
            $backupResults['files'] = $this->backupFiles($backupDate, $backupTime);

            // Configuration backup
            $backupResults['config'] = $this->backupConfiguration($backupDate, $backupTime);

            // Logs backup
            $backupResults['logs'] = $this->backupLogs($backupDate, $backupTime);

            // Incremental JSON export
            $backupResults['incremental'] = $this->createIncrementalExport($backupDate, $backupTime);

            // Create master backup archive
            $masterBackup = $this->createMasterBackup($backupResults, $backupDate, $backupTime);

            // AI integrity verification
            $verificationResult = $this->verifyBackupIntegrity($masterBackup, $backupId);

            // Update backup tracking
            $this->updateBackupTrackingRecord($backupId, $masterBackup, $verificationResult);

            // Cleanup old backups
            $this->cleanupOldBackups();

            $backupEndTime = microtime(true);
            $backupDuration = round($backupEndTime - $backupStartTime, 2);

            $this->logger->info("Backup completed successfully", [
                'backup_id' => $backupId,
                'duration' => $backupDuration,
                'total_size' => $masterBackup['file_size'],
                'verification_status' => $verificationResult['status']
            ]);

            return [
                'success' => true,
                'backup_id' => $backupId,
                'duration' => $backupDuration,
                'master_backup' => $masterBackup,
                'verification' => $verificationResult,
                'backup_results' => $backupResults
            ];

        } catch (Exception $e) {
            $this->logger->error("Backup failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update backup tracking with error
            if (isset($backupId)) {
                $this->updateBackupTrackingRecord($backupId, null, ['status' => 'failed', 'error' => $e->getMessage()]);
            }

            return [
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create backup tracking record
     */
    private function createBackupTrackingRecord($date, $time)
    {
        return $this->db->insert('backup_tracking', [
            'backup_type' => 'full',
            'backup_date' => $date,
            'backup_time' => $time,
            'status' => 'running',
            'verification_status' => 'pending'
        ]);
    }

    /**
     * Update backup tracking record
     */
    private function updateBackupTrackingRecord($backupId, $masterBackup, $verificationResult)
    {
        $updateData = [
            'status' => 'completed',
            'verification_status' => $verificationResult['status'],
            'verification_details' => json_encode($verificationResult)
        ];

        if ($masterBackup) {
            $updateData['file_path'] = $masterBackup['file_path'];
            $updateData['file_size'] = $masterBackup['file_size'];
            $updateData['compression_ratio'] = $masterBackup['compression_ratio'];
            $updateData['integrity_hash'] = $masterBackup['hash'];
            $updateData['items_count'] = $masterBackup['items_count'];
        }

        if (isset($verificationResult['error'])) {
            $updateData['error_message'] = $verificationResult['error'];
        }

        $this->db->update('backup_tracking', $updateData, 'id = ?', [$backupId]);
    }

    /**
     * Backup database
     */
    private function backupDatabase($date, $time)
    {
        $backupFile = $this->backupDir . 'database/backup_' . $date . '_' . str_replace(':', '', $time) . '.sql';

        try {
            // Get all tables
            $tables = $this->db->fetchAll("SHOW TABLES");

            $sqlContent = "-- SAMS Database Backup\n";
            $sqlContent .= "-- Date: $date $time\n";
            $sqlContent .= "-- Generated by SAMS Backup Service\n\n";

            foreach ($tables as $table) {
                $tableName = array_values($table)[0];

                // Get table structure
                $createTable = $this->db->fetchAll("SHOW CREATE TABLE `$tableName`");
                $sqlContent .= "-- Table structure for `$tableName`\n";
                $sqlContent .= $createTable[0]['Create Table'] . ";\n\n";

                // Get table data
                $rows = $this->db->fetchAll("SELECT * FROM `$tableName`");

                if (!empty($rows)) {
                    $sqlContent .= "-- Data for `$tableName`\n";
                    $columns = array_keys($rows[0]);

                    foreach ($rows as $row) {
                        $values = array_map(function($value) {
                            if ($value === null) {
                                return 'NULL';
                            } elseif ($value === '') {
                                return "''";
                            } else {
                                return "'" . addslashes($value) . "'";
                            }
                        }, $row);

                        $sqlContent .= "INSERT INTO `$tableName` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $sqlContent .= "\n";
                }
            }

            // Compress backup
            $compressedFile = $this->compressFile($sqlContent, $backupFile . '.gz');

            return [
                'success' => true,
                'file_path' => $compressedFile,
                'file_size' => filesize($compressedFile),
                'tables_count' => count($tables)
            ];

        } catch (Exception $e) {
            $this->logger->error("Database backup failed", [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Backup files
     */
    private function backupFiles($date, $time)
    {
        $backupFile = $this->backupDir . 'files/files_' . $date . '_' . str_replace(':', '', $time) . '.tar.gz';

        try {
            $directories = [
                'public/uploads' => 'uploads',
                'storage' => 'storage',
                'logs' => 'logs'
            ];

            $tempDir = $this->backupDir . 'temp_files_' . uniqid();
            mkdir($tempDir, 0755, true);

            $fileCount = 0;
            $totalSize = 0;

            foreach ($directories as $sourceDir => $targetDir) {
                $fullSourceDir = __DIR__ . '/../../' . $sourceDir;

                if (is_dir($fullSourceDir)) {
                    $targetPath = $tempDir . '/' . $targetDir;

                    // Copy directory recursively
                    $this->copyDirectory($fullSourceDir, $targetPath);

                    // Count files and calculate size
                    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($targetPath));
                    foreach ($iterator as $file) {
                        if ($file->isFile()) {
                            $fileCount++;
                            $totalSize += $file->getSize();
                        }
                    }
                }
            }

            // Create tar archive
            $archive = new PharData($tempDir . '/files.tar');
            $archive->buildFromDirectory($tempDir);
            $archive->compress(Phar::GZ, $this->compressionLevel);

            // Move to backup location
            rename($tempDir . '/files.tar.gz', $backupFile);

            // Cleanup temp directory
            $this->removeDirectory($tempDir);

            return [
                'success' => true,
                'file_path' => $backupFile,
                'file_size' => filesize($backupFile),
                'files_count' => $fileCount,
                'total_size' => $totalSize
            ];

        } catch (Exception $e) {
            $this->logger->error("Files backup failed", [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Backup configuration
     */
    private function backupConfiguration($date, $time)
    {
        $backupFile = $this->backupDir . 'config/config_' . $date . '_' . str_replace(':', '', $time) . '.json';

        try {
            $configData = [
                'backup_info' => [
                    'date' => $date,
                    'time' => $time,
                    'version' => '1.0'
                ],
                'system_config' => [
                    'database' => [
                        'host' => DB_HOST,
                        'name' => DB_NAME,
                        'charset' => 'utf8mb4'
                    ],
                    'application' => [
                        'name' => APP_NAME ?? 'SAMS',
                        'version' => APP_VERSION ?? '1.0.0',
                        'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'production'
                    ],
                    'paths' => [
                        'base_url' => BASE_URL,
                        'upload_dir' => defined('UPLOAD_DIR') ? UPLOAD_DIR : 'uploads/',
                        'log_dir' => defined('LOG_DIR') ? LOG_DIR : 'logs/'
                    ]
                ],
                'features' => [
                    'ai_enabled' => true,
                    'theme_system' => true,
                    'documentation' => true,
                    'backup_system' => true
                ],
                'security' => [
                    'session_timeout' => SESSION_TIMEOUT ?? 3600,
                    'password_min_length' => 8,
                    'max_login_attempts' => 5
                ]
            ];

            // Add custom configuration files if they exist
            $configFiles = [
                'composer.json' => 'composer_config',
                '.env' => 'environment_config',
                'includes/config.php' => 'main_config'
            ];

            foreach ($configFiles as $file => $key) {
                $filePath = __DIR__ . '/../../' . $file;
                if (file_exists($filePath)) {
                    $configData[$key] = file_get_contents($filePath);
                }
            }

            // Compress backup
            $compressedFile = $this->compressFile(json_encode($configData, JSON_PRETTY_PRINT), $backupFile . '.gz');

            return [
                'success' => true,
                'file_path' => $compressedFile,
                'file_size' => filesize($compressedFile)
            ];

        } catch (Exception $e) {
            $this->logger->error("Configuration backup failed", [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Backup logs
     */
    private function backupLogs($date, $time)
    {
        $backupFile = $this->backupDir . 'logs/logs_' . $date . '_' . str_replace(':', '', $time) . '.tar.gz';

        try {
            $logDir = __DIR__ . '/../../logs/';

            if (!is_dir($logDir)) {
                return [
                    'success' => true,
                    'file_path' => null,
                    'file_size' => 0,
                    'message' => 'No logs directory found'
                ];
            }

            $tempDir = $this->backupDir . 'temp_logs_' . uniqid();
            mkdir($tempDir, 0755, true);

            // Copy logs directory
            $this->copyDirectory($logDir, $tempDir . '/logs');

            // Create tar archive
            $archive = new PharData($tempDir . '/logs.tar');
            $archive->buildFromDirectory($tempDir);
            $archive->compress(Phar::GZ, $this->compressionLevel);

            // Move to backup location
            rename($tempDir . '/logs.tar.gz', $backupFile);

            // Cleanup temp directory
            $this->removeDirectory($tempDir);

            return [
                'success' => true,
                'file_path' => $backupFile,
                'file_size' => filesize($backupFile)
            ];

        } catch (Exception $e) {
            $this->logger->error("Logs backup failed", [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create incremental JSON export
     */
    private function createIncrementalExport($date, $time)
    {
        $backupFile = $this->backupDir . 'exports/incremental_' . $date . '_' . str_replace(':', '', $time) . '.json';

        try {
            $exportData = [
                'export_info' => [
                    'date' => $date,
                    'time' => $time,
                    'type' => 'incremental',
                    'version' => '1.0'
                ],
                'data' => []
            ];

            // Get last backup timestamp
            $lastBackup = $this->db->fetchOne("
                SELECT backup_date, backup_time
                FROM backup_tracking
                WHERE backup_type = 'full' AND status = 'completed'
                ORDER BY backup_date DESC, backup_time DESC
                LIMIT 1
            ");

            $lastTimestamp = null;
            if ($lastBackup) {
                $lastTimestamp = $lastBackup['backup_date'] . ' ' . $lastBackup['backup_time'];
            }

            // Export users
            $usersQuery = "SELECT id, email, first_name, last_name, role, is_active, created_at, updated_at FROM users";
            if ($lastTimestamp) {
                $usersQuery .= " WHERE updated_at > '$lastTimestamp'";
            }

            $exportData['data']['users'] = $this->db->fetchAll($usersQuery);

            // Export students
            $studentsQuery = "SELECT * FROM students";
            if ($lastTimestamp) {
                $studentsQuery .= " WHERE updated_at > '$lastTimestamp'";
            }

            $exportData['data']['students'] = $this->db->fetchAll($studentsQuery);

            // Export teachers
            $teachersQuery = "SELECT * FROM teachers";
            if ($lastTimestamp) {
                $teachersQuery .= " WHERE updated_at > '$lastTimestamp'";
            }

            $exportData['data']['teachers'] = $this->db->fetchAll($teachersQuery);

            // Export classes
            $classesQuery = "SELECT * FROM classes";
            if ($lastTimestamp) {
                $classesQuery .= " WHERE updated_at > '$lastTimestamp'";
            }

            $exportData['data']['classes'] = $this->db->fetchAll($classesQuery);

            // Export attendance records (last 30 days)
            $attendanceQuery = "SELECT * FROM attendance_records WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            if ($lastTimestamp) {
                $attendanceQuery .= " AND created_at > '$lastTimestamp'";
            }

            $exportData['data']['attendance'] = $this->db->fetchAll($attendanceQuery);

            // Export documents
            $documentsQuery = "SELECT * FROM ai_documents";
            if ($lastTimestamp) {
                $documentsQuery .= " WHERE updated_at > '$lastTimestamp'";
            }

            $exportData['data']['documents'] = $this->db->fetchAll($documentsQuery);

            // Compress backup
            $compressedFile = $this->compressFile(json_encode($exportData, JSON_PRETTY_PRINT), $backupFile . '.gz');

            return [
                'success' => true,
                'file_path' => $compressedFile,
                'file_size' => filesize($compressedFile),
                'items_count' => array_sum(array_map('count', $exportData['data']))
            ];

        } catch (Exception $e) {
            $this->logger->error("Incremental export failed", [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create master backup archive
     */
    private function createMasterBackup($backupResults, $date, $time)
    {
        $masterFile = $this->backupDir . 'master/sams_backup_' . $date . '_' . str_replace(':', '', $time) . '.tar.gz';

        try {
            $tempDir = $this->backupDir . 'temp_master_' . uniqid();
            mkdir($tempDir, 0755, true);

            $totalSize = 0;
            $itemsCount = 0;

            // Copy all backup files to temp directory
            foreach ($backupResults as $type => $result) {
                if ($result['success'] && isset($result['file_path'])) {
                    $targetFile = $tempDir . '/' . basename($result['file_path']);
                    copy($result['file_path'], $targetFile);
                    $totalSize += $result['file_size'];
                    $itemsCount++;
                }
            }

            // Create backup manifest
            $manifest = [
                'backup_info' => [
                    'date' => $date,
                    'time' => $time,
                    'type' => 'full',
                    'version' => '1.0',
                    'created_by' => 'SAMS Backup Service'
                ],
                'backup_results' => $backupResults,
                'statistics' => [
                    'total_size' => $totalSize,
                    'items_count' => $itemsCount,
                    'compression_level' => $this->compressionLevel
                ]
            ];

            file_put_contents($tempDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

            // Create master archive
            $archive = new PharData($tempDir . '/master.tar');
            $archive->buildFromDirectory($tempDir);
            $archive->compress(Phar::GZ, $this->compressionLevel);

            // Move to backup location
            rename($tempDir . '/master.tar.gz', $masterFile);

            // Cleanup temp directory
            $this->removeDirectory($tempDir);

            // Calculate compression ratio
            $originalSize = $totalSize;
            $compressedSize = filesize($masterFile);
            $compressionRatio = $originalSize > 0 ? round((($originalSize - $compressedSize) / $originalSize) * 100, 2) : 0;

            // Calculate hash
            $hash = hash('sha256', file_get_contents($masterFile));

            return [
                'success' => true,
                'file_path' => $masterFile,
                'file_size' => $compressedSize,
                'original_size' => $originalSize,
                'compression_ratio' => $compressionRatio,
                'hash' => $hash,
                'items_count' => $itemsCount
            ];

        } catch (Exception $e) {
            $this->logger->error("Master backup creation failed", [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * AI verification of backup integrity
     */
    private function verifyBackupIntegrity($masterBackup, $backupId)
    {
        try {
            $this->logger->info("Starting AI integrity verification", [
                'backup_id' => $backupId,
                'file_path' => $masterBackup['file_path']
            ]);

            $verificationResults = [
                'status' => 'verified',
                'checks' => [],
                'ai_analysis' => [],
                'recommendations' => []
            ];

            // Check 1: File integrity
            $fileCheck = $this->verifyFileIntegrity($masterBackup);
            $verificationResults['checks']['file_integrity'] = $fileCheck;

            // Check 2: Hash verification
            $hashCheck = $this->verifyHashIntegrity($masterBackup);
            $verificationResults['checks']['hash_integrity'] = $hashCheck;

            // Check 3: Archive structure
            $structureCheck = $this->verifyArchiveStructure($masterBackup);
            $verificationResults['checks']['archive_structure'] = $structureCheck;

            // Check 4: Data completeness
            $completenessCheck = $this->verifyDataCompleteness($masterBackup);
            $verificationResults['checks']['data_completeness'] = $completenessCheck;

            // Check 5: AI-powered analysis
            $aiAnalysis = $this->performAIAnalysis($masterBackup);
            $verificationResults['ai_analysis'] = $aiAnalysis;

            // Determine overall status
            $allChecksPassed = true;
            foreach ($verificationResults['checks'] as $check) {
                if (!$check['passed']) {
                    $allChecksPassed = false;
                    break;
                }
            }

            if ($allChecksPassed && $aiAnalysis['status'] === 'passed') {
                $verificationResults['status'] = 'verified';
                $verificationResults['message'] = 'Backup integrity verified successfully';
            } else {
                $verificationResults['status'] = 'failed';
                $verificationResults['message'] = 'Backup integrity verification failed';

                // Add recommendations
                $verificationResults['recommendations'] = $this->generateRecommendations($verificationResults);
            }

            $this->logger->info("AI integrity verification completed", [
                'backup_id' => $backupId,
                'status' => $verificationResults['status'],
                'checks_passed' => $allChecksPassed
            ]);

            return $verificationResults;

        } catch (Exception $e) {
            $this->logger->error("AI integrity verification failed", [
                'backup_id' => $backupId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'message' => 'AI integrity verification failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify file integrity
     */
    private function verifyFileIntegrity($masterBackup)
    {
        try {
            if (!file_exists($masterBackup['file_path'])) {
                return ['passed' => false, 'error' => 'Backup file does not exist'];
            }

            $fileSize = filesize($masterBackup['file_path']);
            if ($fileSize !== $masterBackup['file_size']) {
                return ['passed' => false, 'error' => 'File size mismatch'];
            }

            // Check if file is readable
            if (!is_readable($masterBackup['file_path'])) {
                return ['passed' => false, 'error' => 'File is not readable'];
            }

            return ['passed' => true, 'file_size' => $fileSize];

        } catch (Exception $e) {
            return ['passed' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify hash integrity
     */
    private function verifyHashIntegrity($masterBackup)
    {
        try {
            $currentHash = hash('sha256', file_get_contents($masterBackup['file_path']));
            $expectedHash = $masterBackup['hash'];

            if ($currentHash !== $expectedHash) {
                return ['passed' => false, 'error' => 'Hash mismatch', 'current' => $currentHash, 'expected' => $expectedHash];
            }

            return ['passed' => true, 'hash' => $currentHash];

        } catch (Exception $e) {
            return ['passed' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify archive structure
     */
    private function verifyArchiveStructure($masterBackup)
    {
        try {
            $tempDir = $this->backupDir . 'temp_verify_' . uniqid();
            mkdir($tempDir, 0755, true);

            // Extract archive
            $archive = new PharData($masterBackup['file_path']);
            $archive->extractTo($tempDir);

            // Check for required files
            $requiredFiles = ['manifest.json'];
            $missingFiles = [];

            foreach ($requiredFiles as $file) {
                if (!file_exists($tempDir . '/' . $file)) {
                    $missingFiles[] = $file;
                }
            }

            if (!empty($missingFiles)) {
                $this->removeDirectory($tempDir);
                return ['passed' => false, 'error' => 'Missing required files', 'missing' => $missingFiles];
            }

            // Verify manifest
            $manifest = json_decode(file_get_contents($tempDir . '/manifest.json'), true);
            if (!$manifest) {
                $this->removeDirectory($tempDir);
                return ['passed' => false, 'error' => 'Invalid manifest file'];
            }

            $this->removeDirectory($tempDir);

            return ['passed' => true, 'manifest' => $manifest];

        } catch (Exception $e) {
            return ['passed' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verify data completeness
     */
    private function verifyDataCompleteness($masterBackup)
    {
        try {
            $tempDir = $this->backupDir . 'temp_verify_' . uniqid();
            mkdir($tempDir, 0755, true);

            // Extract archive
            $archive = new PharData($masterBackup['file_path']);
            $archive->extractTo($tempDir);

            // Check manifest for expected items
            $manifest = json_decode(file_get_contents($tempDir . '/manifest.json'), true);
            $expectedItems = $manifest['statistics']['items_count'];
            $actualFiles = glob($tempDir . '/*');
            $actualCount = count($actualFiles);

            $this->removeDirectory($tempDir);

            if ($actualCount < $expectedItems) {
                return ['passed' => false, 'error' => 'Missing backup items', 'expected' => $expectedItems, 'actual' => $actualCount];
            }

            return ['passed' => true, 'items_count' => $actualCount];

        } catch (Exception $e) {
            return ['passed' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Perform AI analysis
     */
    private function performAIAnalysis($masterBackup)
    {
        try {
            $analysis = [
                'status' => 'passed',
                'checks' => [],
                'insights' => [],
                'recommendations' => []
            ];

            // AI Check 1: Backup size analysis
            $sizeAnalysis = $this->analyzeBackupSize($masterBackup);
            $analysis['checks']['size_analysis'] = $sizeAnalysis;

            // AI Check 2: Compression efficiency
            $compressionAnalysis = $this->analyzeCompressionEfficiency($masterBackup);
            $analysis['checks']['compression_analysis'] = $compressionAnalysis;

            // AI Check 3: Backup patterns
            $patternAnalysis = $this->analyzeBackupPatterns($masterBackup);
            $analysis['checks']['pattern_analysis'] = $patternAnalysis;

            // AI Check 4: Risk assessment
            $riskAnalysis = $this->analyzeBackupRisks($masterBackup);
            $analysis['checks']['risk_analysis'] = $riskAnalysis;

            // Generate insights
            $analysis['insights'] = $this->generateInsights($analysis);

            // Determine overall status
            $allChecksPassed = true;
            foreach ($analysis['checks'] as $check) {
                if (isset($check['status']) && $check['status'] === 'warning') {
                    $analysis['status'] = 'warning';
                } elseif (isset($check['status']) && $check['status'] === 'failed') {
                    $analysis['status'] = 'failed';
                    $allChecksPassed = false;
                }
            }

            if ($allChecksPassed) {
                $analysis['status'] = 'passed';
            }

            return $analysis;

        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Analyze backup size
     */
    private function analyzeBackupSize($masterBackup)
    {
        $fileSize = $masterBackup['file_size'];
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);

        $status = 'passed';
        $message = 'Backup size is normal';

        if ($fileSizeMB > 1000) {
            $status = 'warning';
            $message = 'Backup size is large (>1GB)';
        } elseif ($fileSizeMB > 2000) {
            $status = 'failed';
            $message = 'Backup size is too large (>2GB)';
        }

        return [
            'status' => $status,
            'message' => $message,
            'size_mb' => $fileSizeMB,
            'recommendation' => $fileSizeMB > 1000 ? 'Consider optimizing backup size' : 'Size is acceptable'
        ];
    }

    /**
     * Analyze compression efficiency
     */
    private function analyzeCompressionEfficiency($masterBackup)
    {
        $compressionRatio = $masterBackup['compression_ratio'];

        $status = 'passed';
        $message = 'Compression efficiency is good';

        if ($compressionRatio < 30) {
            $status = 'warning';
            $message = 'Compression efficiency is low';
        } elseif ($compressionRatio < 10) {
            $status = 'failed';
            $message = 'Compression efficiency is poor';
        }

        return [
            'status' => $status,
            'message' => $message,
            'compression_ratio' => $compressionRatio,
            'recommendation' => $compressionRatio < 30 ? 'Consider increasing compression level' : 'Compression is efficient'
        ];
    }

    /**
     * Analyze backup patterns
     */
    private function analyzeBackupPatterns($masterBackup)
    {
        // Analyze backup patterns over time
        $recentBackups = $this->db->fetchAll("
            SELECT backup_date, file_size, status
            FROM backup_tracking
            WHERE backup_type = 'full' AND status = 'completed'
            ORDER BY backup_date DESC
            LIMIT 7
        ");

        $status = 'passed';
        $message = 'Backup patterns are normal';

        if (count($recentBackups) < 7) {
            $status = 'warning';
            $message = 'Limited backup history';
        }

        return [
            'status' => $status,
            'message' => $message,
            'recent_backups' => count($recentBackups),
            'pattern' => 'consistent'
        ];
    }

    /**
     * Analyze backup risks
     */
    private function analyzeBackupRisks($masterBackup)
    {
        $risks = [];

        // Check backup age
        $backupAge = time() - filemtime($masterBackup['file_path']);
        $backupAgeHours = $backupAge / 3600;

        if ($backupAgeHours > 48) {
            $risks[] = 'Backup is older than 48 hours';
        }

        // Check file permissions
        $filePerms = fileperms($masterBackup['file_path']);
        if (($filePerms & 0x0004) === 0) {
            $risks[] = 'Backup file is not readable';
        }

        $status = empty($risks) ? 'passed' : 'warning';
        $message = empty($risks) ? 'No risks detected' : 'Risks detected: ' . implode(', ', $risks);

        return [
            'status' => $status,
            'message' => $message,
            'risks' => $risks,
            'backup_age_hours' => $backupAgeHours
        ];
    }

    /**
     * Generate AI insights
     */
    private function generateInsights($analysis)
    {
        $insights = [];

        // Size insights
        if (isset($analysis['checks']['size_analysis'])) {
            $sizeAnalysis = $analysis['checks']['size_analysis'];
            $insights[] = "Backup size is {$sizeAnalysis['size_mb']}MB - {$sizeAnalysis['recommendation']}";
        }

        // Compression insights
        if (isset($analysis['checks']['compression_analysis'])) {
            $compressionAnalysis = $analysis['checks']['compression_analysis'];
            $insights[] = "Compression ratio is {$compressionAnalysis['compression_ratio']}% - {$compressionAnalysis['recommendation']}";
        }

        // Pattern insights
        if (isset($analysis['checks']['pattern_analysis'])) {
            $patternAnalysis = $analysis['checks']['pattern_analysis'];
            $insights[] = "Backup pattern: {$patternAnalysis['pattern']} with {$patternAnalysis['recent_backups']} recent backups";
        }

        return $insights;
    }

    /**
     * Generate recommendations
     */
    private function generateRecommendations($verificationResults)
    {
        $recommendations = [];

        foreach ($verificationResults['checks'] as $checkName => $check) {
            if (!$check['passed']) {
                switch ($checkName) {
                    case 'file_integrity':
                        $recommendations[] = 'Check file system integrity and permissions';
                        break;
                    case 'hash_integrity':
                        $recommendations[] = 'Recreate backup due to hash mismatch';
                        break;
                    case 'archive_structure':
                        $recommendations[] = 'Verify backup creation process';
                        break;
                    case 'data_completeness':
                        $recommendations[] = 'Ensure all data is included in backup';
                        break;
                }
            }
        }

        // Add AI recommendations
        if (isset($verificationResults['ai_analysis'])) {
            $aiAnalysis = $verificationResults['ai_analysis'];
            foreach ($aiAnalysis['checks'] as $check) {
                if (isset($check['recommendation'])) {
                    $recommendations[] = $check['recommendation'];
                }
            }
        }

        return array_unique($recommendations);
    }

    /**
     * Cleanup old backups
     */
    private function cleanupOldBackups()
    {
        try {
            // Get old backups
            $oldBackups = $this->db->fetchAll("
                SELECT id, file_path
                FROM backup_tracking
                WHERE backup_date < DATE_SUB(CURDATE(), INTERVAL {$this->maxBackups} DAY)
                AND status = 'completed'
            ");

            foreach ($oldBackups as $backup) {
                // Delete file if it exists
                if (file_exists($backup['file_path'])) {
                    unlink($backup['file_path']);
                }

                // Update tracking record
                $this->db->update('backup_tracking', [
                    'status' => 'archived'
                ], 'id = ?', [$backup['id']]);
            }

            // Cleanup old files
            $this->cleanupOldFiles();

            $this->logger->info("Old backups cleaned up", [
                'deleted_count' => count($oldBackups)
            ]);

        } catch (Exception $e) {
            $this->logger->error("Backup cleanup failed", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Cleanup old files
     */
    private function cleanupOldFiles()
    {
        $cutoffTime = time() - ($this->maxBackups * 24 * 60 * 60);

        foreach (['database', 'files', 'config', 'logs', 'exports'] as $dir) {
            $fullDir = $this->backupDir . $dir;

            if (is_dir($fullDir)) {
                $files = glob($fullDir . '/*');

                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) < $cutoffTime) {
                        unlink($file);
                    }
                }
            }
        }
    }

    /**
     * Compress file
     */
    private function compressFile($content, $filePath)
    {
        $gz = gzopen($filePath, 'w9');
        gzwrite($gz, $content);
        gzclose($gz);

        return $filePath;
    }

    /**
     * Copy directory recursively
     */
    private function copyDirectory($source, $destination)
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                mkdir($destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName(), 0755, true);
            } else {
                copy($item, $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            }
        }
    }

    /**
     * Remove directory recursively
     */
    private function removeDirectory($dir)
    {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? $this->removeDirectory($path) : unlink($path);
            }
            rmdir($dir);
        }
    }

    /**
     * Get backup statistics
     */
    public function getBackupStatistics()
    {
        try {
            $stats = [];

            // Total backups
            $stats['total_backups'] = $this->db->count('backup_tracking');

            // Successful backups
            $stats['successful_backups'] = $this->db->count('backup_tracking', 'status = ?', ['completed']);

            // Failed backups
            $stats['failed_backups'] = $this->db->count('backup_tracking', 'status = ?', ['failed']);

            // Recent backups
            $stats['recent_backups'] = $this->db->count('backup_tracking', 'backup_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)');

            // Storage usage
            $storageUsage = $this->db->fetchOne("
                SELECT SUM(file_size) as total_size
                FROM backup_tracking
                WHERE status = 'completed'
            ");

            $stats['storage_usage_mb'] = round(($storageUsage['total_size'] ?? 0) / 1024 / 1024, 2);

            // Last backup
            $lastBackup = $this->db->fetchOne("
                SELECT backup_date, backup_time, status
                FROM backup_tracking
                ORDER BY backup_date DESC, backup_time DESC
                LIMIT 1
            ");

            $stats['last_backup'] = $lastBackup;

            // Verification status
            $stats['verified_backups'] = $this->db->count('backup_tracking', 'verification_status = ?', ['verified']);
            $stats['failed_verification'] = $this->db->count('backup_tracking', 'verification_status = ?', ['failed']);

            return [
                'success' => true,
                'statistics' => $stats
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting backup statistics: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get backup list
     */
    public function getBackupList($limit = 50, $offset = 0)
    {
        try {
            $backups = $this->db->fetchAll("
                SELECT * FROM backup_tracking
                ORDER BY backup_date DESC, backup_time DESC
                LIMIT ? OFFSET ?
            ", [$limit, $offset]);

            return [
                'success' => true,
                'backups' => $backups
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting backup list: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Restore from backup
     */
    public function restoreFromBackup($backupId, $options = [])
    {
        try {
            $backup = $this->db->fetchOne("
                SELECT * FROM backup_tracking
                WHERE id = ? AND status = 'completed'
            ", [$backupId]);

            if (!$backup) {
                return [
                    'success' => false,
                    'message' => 'Backup not found or not completed'
                ];
            }

            // This is a simplified restore implementation
            // In production, you would implement proper restore logic
            $this->logger->info("Restore initiated", [
                'backup_id' => $backupId,
                'options' => $options
            ]);

            return [
                'success' => true,
                'message' => 'Restore process initiated',
                'backup_id' => $backupId
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error restoring from backup: ' . $e->getMessage()
            ];
        }
    }
}

/**
 * Simple Logger for backup service
 */
class Logger
{
    private $logFile;

    public function __construct($name = 'backup_service')
    {
        $this->logFile = __DIR__ . '/../../logs/' . $name . '.log';
        $this->ensureLogDirectory();
    }

    public function info($message, $context = [])
    {
        $this->log('INFO', $message, $context);
    }

    public function error($message, $context = [])
    {
        $this->log('ERROR', $message, $context);
    }

    public function debug($message, $context = [])
    {
        $this->log('DEBUG', $message, $context);
    }

    private function log($level, $message, $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = $context ? ' | ' . json_encode($context) : '';
        $logMessage = "[$timestamp] [$level] $message $contextStr\n";

        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    private function ensureLogDirectory()
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
}
