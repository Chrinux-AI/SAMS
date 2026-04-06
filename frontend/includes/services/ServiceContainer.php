<?php
/**
 * SAMS Service Layer Architecture
 * Central service registry and dependency injection container
 *
 * Implements the service layer pattern for clean separation of concerns
 * across the multi-tenant school management platform
 */

class SAMS_ServiceContainer {
    private static $instance = null;
    private $services = [];
    private $config = [];

    private function __construct() {
        $this->loadConfiguration();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load system configuration
     */
    private function loadConfiguration() {
        $this->config = [
            'database' => [
                'host' => defined('DB_HOST') ? DB_HOST : 'localhost',
                'name' => defined('DB_NAME') ? DB_NAME : 'attendance_system',
                'user' => defined('DB_USER') ? DB_USER : 'root',
                'pass' => defined('DB_PASS') ? DB_PASS : ''
            ],
            'security' => [
                'otp_length' => 6,
                'otp_expiry' => 900, // 15 minutes
                'otp_cooldown' => 60, // 1 minute
                'max_otp_attempts' => 3,
                'max_otp_requests' => 5,
                'lockout_duration' => 3600, // 1 hour
                'session_timeout' => 1800, // 30 minutes
                'password_min_length' => 8
            ],
            'multi_tenant' => [
                'enabled' => true,
                'default_tenant' => 1,
                'isolation_level' => 'shared_db' // shared_db or isolated_db
            ],
            'features' => [
                'ai_user_creation' => true,
                'chatbot' => true,
                'pwa' => true,
                'biometric' => false
            ]
        ];
    }

    /**
     * Register a service
     */
    public function register($name, $service) {
        $this->services[$name] = $service;
        return $this;
    }

    /**
     * Get a registered service
     */
    public function get($name) {
        if (!isset($this->services[$name])) {
            $this->services[$name] = $this->createService($name);
        }
        return $this->services[$name];
    }

    /**
     * Create service by name
     */
    private function createService($name) {
        $services = [
            'auth' => 'SAMS_AuthService',
            'user' => 'SAMS_UserService',
            'student' => 'SAMS_StudentService',
            'teacher' => 'SAMS_TeacherService',
            'class' => 'SAMS_ClassService',
            'import' => 'SAMS_ImportService',
            'chatbot' => 'SAMS_ChatbotService',
            'otp' => 'SAMS_OTPService',
            'security' => 'SAMS_SecurityService',
            'audit' => 'SAMS_AuditService',
            'tenant' => 'SAMS_TenantService',
            'email' => 'SAMS_EmailService',
            'validation' => 'SAMS_ValidationService',
            'error' => 'SAMS_ErrorService',
            'workflow' => 'SAMS_WorkflowService'
        ];

        if (!isset($services[$name])) {
            throw new Exception("Service not found: " . $name);
        }

        $className = $services[$name];
        return new $className($this);
    }

    /**
     * Get configuration value
     */
    public function config($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Check if service exists
     */
    public function has($name) {
        return isset($this->services[$name]);
    }
}

/**
 * Base service class with common functionality
 */
abstract class SAMS_BaseService {
    protected $container;
    protected $db;
    protected $logger;

    public function __construct($container) {
        $this->container = $container;
        $this->db = $this->getDatabase();
        $this->logger = $this->container->get('audit');
    }

    /**
     * Get database connection
     */
    protected function getDatabase() {
        return db(); // Use existing db() function
    }

    /**
     * Log service action
     */
    protected function log($action, $details = []) {
        if ($this->logger) {
            $this->logger->log([
                'service' => get_class($this),
                'action' => $action,
                'details' => $details,
                'timestamp' => date('Y-m-d H:i:s'),
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
        }
    }

    /**
     * Begin database transaction
     */
    protected function beginTransaction() {
        $this->db->query("START TRANSACTION");
    }

    /**
     * Commit database transaction
     */
    protected function commit() {
        $this->db->query("COMMIT");
    }

    /**
     * Rollback database transaction
     */
    protected function rollback() {
        $this->db->query("ROLLBACK");
    }

    /**
     * Execute with transaction safety
     */
    protected function transactional($callback) {
        try {
            $this->beginTransaction();
            $result = $callback();
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
}

/**
 * Service registry for static access
 */
class SAMS_Services {
    public static function get($name) {
        return SAMS_ServiceContainer::getInstance()->get($name);
    }

    public static function config($key, $default = null) {
        return SAMS_ServiceContainer::getInstance()->config($key, $default);
    }
}
