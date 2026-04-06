<?php
/**
 * Schema Governance System
 * Manages database schema versioning, migrations, and drift detection
 */

class SAMS_SchemaGovernance {
    private $db;
    private $migrationsPath;
    private $schemaVersionTable = 'schema_versions';
    
    public function __construct() {
        $this->db = db();
        $this->migrationsPath = __DIR__ . '/../../database/migrations/';
        $this->ensureVersionTable();
    }
    
    /**
     * Initialize schema version tracking table
     */
    private function ensureVersionTable() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->schemaVersionTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            version VARCHAR(20) NOT NULL UNIQUE,
            description VARCHAR(255),
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            applied_by INT,
            checksum VARCHAR(64),
            execution_time_ms INT
        )";
        
        $this->db->query($sql);
    }
    
    /**
     * Get current schema version
     */
    public function getCurrentVersion() {
        $result = $this->db->query("SELECT version FROM {$this->schemaVersionTable} ORDER BY id DESC LIMIT 1");
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            return $row['version'];
        }
        
        return '0000';
    }
    
    /**
     * Get all pending migrations
     */
    public function getPendingMigrations() {
        $currentVersion = $this->getCurrentVersion();
        $allMigrations = $this->getAllMigrationFiles();
        
        return array_filter($allMigrations, function($migration) use ($currentVersion) {
            return $migration['version'] > $currentVersion;
        });
    }
    
    /**
     * Run all pending migrations
     */
    public function migrate() {
        $pending = $this->getPendingMigrations();
        
        if (empty($pending)) {
            return ['success' => true, 'message' => 'No pending migrations'];
        }
        
        $results = [];
        
        foreach ($pending as $migration) {
            $result = $this->runMigration($migration);
            $results[] = $result;
            
            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => 'Migration failed: ' . $migration['version'],
                    'results' => $results
                ];
            }
        }
        
        return [
            'success' => true,
            'migrated' => count($pending),
            'results' => $results
        ];
    }
    
    /**
     * Run single migration
     */
    public function runMigration($migration) {
        $startTime = microtime(true);
        
        // Read migration SQL
        $sql = file_get_contents($migration['path']);
        
        if (!$sql) {
            return ['success' => false, 'error' => 'Cannot read migration file'];
        }
        
        // Split into individual statements
        $statements = $this->parseSqlStatements($sql);
        
        // Begin transaction
        $this->db->query("START TRANSACTION");
        
        try {
            foreach ($statements as $statement) {
                if (trim($statement)) {
                    $result = $this->db->query($statement);
                    
                    if (!$result) {
                        throw new Exception("Query failed: " . $this->db->error);
                    }
                }
            }
            
            // Record migration
            $checksum = hash('sha256', $sql);
            $executionTime = round((microtime(true) - $startTime) * 1000);
            
            $this->recordMigration($migration['version'], $migration['description'], $checksum, $executionTime);
            
            // Commit
            $this->db->query("COMMIT");
            
            return [
                'success' => true,
                'version' => $migration['version'],
                'execution_time_ms' => $executionTime
            ];
            
        } catch (Exception $e) {
            $this->db->query("ROLLBACK");
            
            return [
                'success' => false,
                'version' => $migration['version'],
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Detect schema drift between code expectations and database
     */
    public function detectSchemaDrift() {
        $issues = [];
        
        // Get expected schema from migrations
        $expectedSchema = $this->getExpectedSchema();
        
        // Get actual database schema
        $actualSchema = $this->getActualSchema();
        
        // Compare tables
        foreach ($expectedSchema as $tableName => $expectedTable) {
            if (!isset($actualSchema[$tableName])) {
                $issues[] = [
                    'type' => 'missing_table',
                    'table' => $tableName,
                    'severity' => 'critical'
                ];
                continue;
            }
            
            $actualTable = $actualSchema[$tableName];
            
            // Check columns
            foreach ($expectedTable['columns'] as $columnName => $expectedColumn) {
                if (!isset($actualTable['columns'][$columnName])) {
                    $issues[] = [
                        'type' => 'missing_column',
                        'table' => $tableName,
                        'column' => $columnName,
                        'severity' => 'high'
                    ];
                } elseif ($actualTable['columns'][$columnName] != $expectedColumn) {
                    $issues[] = [
                        'type' => 'column_mismatch',
                        'table' => $tableName,
                        'column' => $columnName,
                        'expected' => $expectedColumn,
                        'actual' => $actualTable['columns'][$columnName],
                        'severity' => 'medium'
                    ];
                }
            }
            
            // Check indexes
            foreach ($expectedTable['indexes'] as $indexName => $expectedIndex) {
                if (!isset($actualTable['indexes'][$indexName])) {
                    $issues[] = [
                        'type' => 'missing_index',
                        'table' => $tableName,
                        'index' => $indexName,
                        'severity' => 'medium'
                    ];
                }
            }
        }
        
        // Check for unexpected tables
        foreach ($actualSchema as $tableName => $actualTable) {
            if (!isset($expectedSchema[$tableName])) {
                $issues[] = [
                    'type' => 'unexpected_table',
                    'table' => $tableName,
                    'severity' => 'low'
                ];
            }
        }
        
        return [
            'issues' => $issues,
            'issue_count' => count($issues),
            'critical_count' => count(array_filter($issues, fn($i) => $i['severity'] === 'critical')),
            'high_count' => count(array_filter($issues, fn($i) => $i['severity'] === 'high'))
        ];
    }
    
    /**
     * Get all migration files
     */
    private function getAllMigrationFiles() {
        $migrations = [];
        
        if (!is_dir($this->migrationsPath)) {
            return $migrations;
        }
        
        $files = glob($this->migrationsPath . '*.sql');
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            // Parse version from filename (e.g., 001_init.sql)
            if (preg_match('/^(\d+)_(.+?)\.sql$/', $filename, $matches)) {
                $migrations[] = [
                    'version' => $matches[1],
                    'description' => str_replace('_', ' ', $matches[2]),
                    'path' => $file,
                    'filename' => $filename
                ];
            }
        }
        
        // Sort by version
        usort($migrations, fn($a, $b) => strcmp($a['version'], $b['version']));
        
        return $migrations;
    }
    
    /**
     * Parse SQL file into individual statements
     */
    private function parseSqlStatements($sql) {
        // Remove comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Split by semicolon, but not within procedures/triggers
        $statements = [];
        $current = '';
        $delimiter = ';';
        $length = strlen($sql);
        $i = 0;
        
        while ($i < $length) {
            $char = $sql[$i];
            
            // Check for DELIMITER command
            if (strtoupper(substr($sql, $i, 9)) === 'DELIMITER') {
                $end = strpos($sql, "\n", $i);
                if ($end === false) $end = $length;
                $delimiter = trim(substr($sql, $i + 9, $end - ($i + 9)));
                $i = $end;
                continue;
            }
            
            $current .= $char;
            
            // Check if we hit the delimiter
            if (substr($current, -strlen($delimiter)) === $delimiter) {
                $statements[] = trim(substr($current, 0, -strlen($delimiter)));
                $current = '';
            }
            
            $i++;
        }
        
        // Add any remaining statement
        if (trim($current)) {
            $statements[] = trim($current);
        }
        
        return $statements;
    }
    
    /**
     * Record migration in version table
     */
    private function recordMigration($version, $description, $checksum, $executionTime) {
        $version = mysqli_real_escape_string($this->db, $version);
        $description = mysqli_real_escape_string($this->db, $description);
        $checksum = mysqli_real_escape_string($this->db, $checksum);
        $appliedBy = (int)($_SESSION['user_id'] ?? 0);
        
        $sql = "INSERT INTO {$this->schemaVersionTable} 
                (version, description, checksum, execution_time_ms, applied_by) 
                VALUES ('$version', '$description', '$checksum', $executionTime, $appliedBy)";
        
        $this->db->query($sql);
    }
    
    /**
     * Get expected schema from all migrations
     */
    private function getExpectedSchema() {
        // This would parse CREATE TABLE statements from migrations
        // For now, return a simplified version
        $schema = [];
        
        $migrations = $this->getAllMigrationFiles();
        
        foreach ($migrations as $migration) {
            $sql = file_get_contents($migration['path']);
            $tables = $this->parseCreateTableStatements($sql);
            $schema = array_merge($schema, $tables);
        }
        
        return $schema;
    }
    
    /**
     * Get actual database schema
     */
    private function getActualSchema() {
        $schema = [];
        
        // Get all tables
        $result = $this->db->query("SHOW TABLES");
        
        if (!$result) {
            return $schema;
        }
        
        while ($row = mysqli_fetch_array($result)) {
            $tableName = $row[0];
            
            // Get columns
            $columnsResult = $this->db->query("SHOW COLUMNS FROM `$tableName`");
            $columns = [];
            
            if ($columnsResult) {
                while ($col = mysqli_fetch_assoc($columnsResult)) {
                    $columns[$col['Field']] = [
                        'type' => $col['Type'],
                        'null' => $col['Null'],
                        'key' => $col['Key'],
                        'default' => $col['Default'],
                        'extra' => $col['Extra']
                    ];
                }
            }
            
            // Get indexes
            $indexResult = $this->db->query("SHOW INDEX FROM `$tableName`");
            $indexes = [];
            
            if ($indexResult) {
                while ($idx = mysqli_fetch_assoc($indexResult)) {
                    $indexes[$idx['Key_name']] = [
                        'column' => $idx['Column_name'],
                        'unique' => !$idx['Non_unique']
                    ];
                }
            }
            
            $schema[$tableName] = [
                'columns' => $columns,
                'indexes' => $indexes
            ];
        }
        
        return $schema;
    }
    
    /**
     * Parse CREATE TABLE statements
     */
    private function parseCreateTableStatements($sql) {
        $tables = [];
        
        // Simple regex-based parsing
        preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\((.+?)\)\s*(?:ENGINE|DEFAULT|CHARSET|;)/is', $sql, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $tableName = $match[1];
            $columnsDef = $match[2];
            
            $columns = [];
            $indexes = [];
            
            // Parse columns
            preg_match_all('/`?(\w+)`?\s+(\w+(?:\([^)]+\))?)\s*(.*?),?\s*$/mi', $columnsDef, $colMatches, PREG_SET_ORDER);
            
            foreach ($colMatches as $colMatch) {
                $colName = $colMatch[1];
                $colType = $colMatch[2];
                $colExtras = $colMatch[3];
                
                $columns[$colName] = [
                    'type' => $colType,
                    'null' => strpos($colExtras, 'NOT NULL') === false ? 'YES' : 'NO',
                    'default' => null,
                    'extra' => ''
                ];
            }
            
            $tables[$tableName] = [
                'columns' => $columns,
                'indexes' => $indexes
            ];
        }
        
        return $tables;
    }
}

/**
 * Schema compatibility layer for handling schema drift
 */
class SAMS_SchemaCompatibility {
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Check if column exists
     */
    public function columnExists($table, $column) {
        $table = mysqli_real_escape_string($this->db, $table);
        $result = $this->db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $result && mysqli_num_rows($result) > 0;
    }
    
    /**
     * Check if table exists
     */
    public function tableExists($table) {
        $table = mysqli_real_escape_string($this->db, $table);
        $result = $this->db->query("SHOW TABLES LIKE '$table'");
        return $result && mysqli_num_rows($result) > 0;
    }
    
    /**
     * Safe column value retrieval with fallback
     */
    public function safeGet($row, $column, $default = null) {
        return isset($row[$column]) ? $row[$column] : $default;
    }
    
    /**
     * Add column if missing
     */
    public function ensureColumn($table, $column, $definition) {
        if (!$this->columnExists($table, $column)) {
            $table = mysqli_real_escape_string($this->db, $table);
            $column = mysqli_real_escape_string($this->db, $column);
            $definition = mysqli_real_escape_string($this->db, $definition);
            
            $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
            return $this->db->query($sql);
        }
        
        return true;
    }
}
