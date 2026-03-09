<?php
/**
 * Error Handling & Elimination Service
 * Centralized error handling, logging, and graceful degradation
 */

class SAMS_ErrorService extends SAMS_BaseService {
    
    private $errorLogFile;
    private $displayErrors = false;
    
    public function __construct($container) {
        parent::__construct($container);
        $this->errorLogFile = __DIR__ . '/../../logs/error.log';
        $this->ensureLogDirectory();
        $this->registerErrorHandlers();
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory() {
        $logDir = dirname($this->errorLogFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Register PHP error handlers
     */
    private function registerErrorHandlers() {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }
    
    /**
     * Handle PHP errors
     */
    public function handleError($errno, $errstr, $errfile, $errline) {
        $errorTypes = [
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Notice',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];
        
        $type = $errorTypes[$errno] ?? 'Unknown Error';
        
        $errorData = [
            'type' => $type,
            'code' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        $this->logError($errorData);
        
        // Display user-friendly message for fatal errors
        if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->displayErrorPage($errorData);
            exit(1);
        }
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    /**
     * Handle exceptions
     */
    public function handleException($exception) {
        $errorData = [
            'type' => 'Exception',
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_id' => $_SESSION['user_id'] ?? null
        ];
        
        $this->logError($errorData);
        $this->displayErrorPage($errorData);
    }
    
    /**
     * Handle shutdown (for fatal errors)
     */
    public function handleShutdown() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->handleError(
                $error['type'],
                $error['message'],
                $error['file'],
                $error['line']
            );
        }
    }
    
    /**
     * Log error to file and database
     */
    private function logError($errorData) {
        // Log to file
        $logLine = sprintf(
            "[%s] %s: %s in %s:%d (User: %s, URI: %s)\n",
            $errorData['timestamp'],
            $errorData['type'],
            $errorData['message'],
            $errorData['file'],
            $errorData['line'],
            $errorData['user_id'] ?? 'guest',
            $errorData['request_uri']
        );
        
        error_log($logLine, 3, $this->errorLogFile);
        
        // Log to database for critical errors
        if (in_array($errorData['type'], ['Fatal Error', 'Exception', 'Parse Error'])) {
            $this->logToDatabase($errorData);
        }
    }
    
    /**
     * Log critical error to database
     */
    private function logToDatabase($errorData) {
        $type = mysqli_real_escape_string($this->db, $errorData['type']);
        $message = mysqli_real_escape_string($this->db, substr($errorData['message'], 0, 500));
        $file = mysqli_real_escape_string($this->db, $errorData['file']);
        $line = (int)$errorData['line'];
        $userId = (int)($errorData['user_id'] ?? 0);
        $uri = mysqli_real_escape_string($this->db, substr($errorData['request_uri'], 0, 255));
        
        $sql = "INSERT INTO error_logs 
                (error_type, message, file, line, user_id, request_uri, created_at) 
                VALUES ('$type', '$message', '$file', $line, $userId, '$uri', NOW())";
        
        $this->db->query($sql);
    }
    
    /**
     * Display user-friendly error page
     */
    private function displayErrorPage($errorData) {
        // Don't display in production unless explicitly enabled
        if (!$this->displayErrors) {
            http_response_code(500);
            include __DIR__ . '/../../error-500.php';
            return;
        }
        
        // Development display
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Error - SAMS</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 50px; background: #f5f5f5; }
                .error-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { color: #e74c3c; }
                .error-type { color: #e74c3c; font-weight: bold; }
                pre { background: #f8f8f8; padding: 15px; overflow-x: auto; }
            </style>
        </head>
        <body>
            <div class="error-box">
                <h1>System Error</h1>
                <p class="error-type">' . htmlspecialchars($errorData['type']) . '</p>
                <p><strong>Message:</strong> ' . htmlspecialchars($errorData['message']) . '</p>
                <p><strong>File:</strong> ' . htmlspecialchars($errorData['file']) . '</p>
                <p><strong>Line:</strong> ' . (int)$errorData['line'] . '</p>
                <p><strong>Time:</strong> ' . htmlspecialchars($errorData['timestamp']) . '</p>';
        
        if (!empty($errorData['trace'])) {
            echo '<h3>Stack Trace:</h3><pre>' . htmlspecialchars($errorData['trace']) . '</pre>';
        }
        
        echo '</div></body></html>';
    }
    
    /**
     * Set display errors mode
     */
    public function setDisplayErrors($display) {
        $this->displayErrors = (bool)$display;
    }
    
    /**
     * Get error statistics
     */
    public function getErrorStats($hours = 24) {
        $hours = (int)$hours;
        
        $stats = [
            'total' => 0,
            'by_type' => [],
            'by_file' => [],
            'recent' => []
        ];
        
        // Database stats
        $result = $this->db->query("SELECT 
            COUNT(*) as total,
            error_type,
            COUNT(DISTINCT file) as unique_files
            FROM error_logs 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL $hours HOUR)
            GROUP BY error_type");
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $stats['total'] += $row['total'];
                $stats['by_type'][$row['error_type']] = (int)$row['total'];
            }
        }
        
        // Recent errors
        $result = $this->db->query("SELECT * FROM error_logs 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL $hours HOUR)
            ORDER BY created_at DESC LIMIT 50");
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $stats['recent'][] = $row;
            }
        }
        
        return $stats;
    }
    
    /**
     * Run PHP syntax check on all files
     */
    public function runSyntaxCheck($directory = __DIR__ . '/../..') {
        $results = [
            'checked' => 0,
            'errors' => [],
            'clean' => 0
        ];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $results['checked']++;
                
                $output = [];
                $returnCode = 0;
                exec('php -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $returnCode);
                
                if ($returnCode !== 0) {
                    $results['errors'][] = [
                        'file' => $file->getPathname(),
                        'error' => implode("\n", $output)
                    ];
                } else {
                    $results['clean']++;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Clear error logs
     */
    public function clearLogs($olderThanDays = 30) {
        $days = (int)$olderThanDays;
        
        // Clear database logs
        $this->db->query("DELETE FROM error_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL $days DAY)");
        
        // Clear file logs (archive old logs)
        $logDir = dirname($this->errorLogFile);
        $archiveDir = $logDir . '/archive';
        
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }
        
        // Archive current log if it's large
        if (file_exists($this->errorLogFile) && filesize($this->errorLogFile) > 10 * 1024 * 1024) { // 10MB
            $archiveName = $archiveDir . '/error-' . date('Y-m-d') . '.log.gz';
            $logContent = file_get_contents($this->errorLogFile);
            file_put_contents($archiveName, gzencode($logContent));
            file_put_contents($this->errorLogFile, ''); // Clear current log
        }
        
        return ['success' => true, 'message' => 'Logs cleared/archived'];
    }
}

/**
 * Quick syntax check utility function
 */
function sams_check_syntax($file) {
    $output = [];
    $returnCode = 0;
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $returnCode);
    
    return [
        'valid' => $returnCode === 0,
        'errors' => $returnCode !== 0 ? implode("\n", $output) : null
    ];
}

/**
 * Safe execution wrapper
 */
function sams_safe_execute($callback, $fallback = null) {
    try {
        return $callback();
    } catch (Exception $e) {
        // Log error
        error_log("Execution error: " . $e->getMessage());
        
        // Return fallback
        return is_callable($fallback) ? $fallback() : $fallback;
    }
}
