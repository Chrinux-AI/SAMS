<?php

/**
 * Database Connection and Query Functions
 */

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        try {
            // Check if DB_HOST contains a socket path
            if (strpos(DB_HOST, '/') !== false) {
                // Unix socket connection
                $dsn = "mysql:unix_socket=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            } else {
                // TCP connection
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            }

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed. Please contact administrator.');
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . print_r($params, true));
            return false;
        }
    }

    public function fetch($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : false;
    }

    public function fetchOne($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetch() : false;
    }

    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Validate that a table name contains only safe characters.
     */
    private function validateTableName(string $table): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $table)) {
            throw new InvalidArgumentException("Invalid table name: {$table}");
        }
        return "`{$table}`";
    }

    public function insert($table, $data)
    {
        $safeTable = $this->validateTableName($table);
        $keys = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$safeTable} ({$keys}) VALUES ({$placeholders})";
        $stmt = $this->query($sql, $data);

        return $stmt ? $this->connection->lastInsertId() : false;
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        $setString = implode(', ', $set);

        // Convert positional WHERE parameters to named parameters
        $whereNamed = $where;
        $namedWhereParams = [];

        if (!empty($whereParams)) {
            // Check if we have positional parameters (numeric keys)
            if (isset($whereParams[0])) {
                // Convert ? to named parameters
                $paramIndex = 0;
                $whereNamed = preg_replace_callback('/\?/', function () use (&$paramIndex) {
                    return ':where_param_' . $paramIndex++;
                }, $where);

                // Create named array from positional array
                foreach ($whereParams as $index => $value) {
                    $namedWhereParams['where_param_' . $index] = $value;
                }
            } else {
                // Already named parameters
                $namedWhereParams = $whereParams;
            }
        }

        $safeTable = $this->validateTableName($table);
        $sql = "UPDATE {$safeTable} SET {$setString} WHERE {$whereNamed}";
        $params = array_merge($data, $namedWhereParams);

        return $this->query($sql, $params) !== false;
    }

    public function delete($table, $where, $params = [])
    {
        $safeTable = $this->validateTableName($table);
        $sql = "DELETE FROM {$safeTable} WHERE {$where}";
        return $this->query($sql, $params) !== false;
    }

    public function count($table, $where = '1=1', $params = [])
    {
        $safeTable = $this->validateTableName($table);
        $sql = "SELECT COUNT(*) as count FROM {$safeTable} WHERE {$where}";
        $result = $this->fetch($sql, $params);
        return $result ? (int)$result['count'] : 0;
    }
}

// Helper function to get database instance
if (!function_exists('db')) {
    function db()
    {
        return Database::getInstance();
    }
}
