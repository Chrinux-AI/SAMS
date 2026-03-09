<?php
/**
 * SAMS Services Autoloader
 * Central file to load all service layer components
 */

// Define services directory
if (!defined('SAMS_SERVICES_DIR')) {
    define('SAMS_SERVICES_DIR', __DIR__ . '/services/');
}

// Service registry
$samsServices = [
    'ServiceContainer',
    'AuthService',
    'UserService',
    'StudentService',
    'TeacherService',
    'ClassService',
    'ImportService',
    'OTPService',
    'WorkflowService',
    'TenantService',
    'ChatbotService',
    'SchemaGovernance',
    'ErrorService',
    'EmailService',
    'AuditService',
    'SecurityService',
    'ValidationService'
];

// Autoload services
foreach ($samsServices as $service) {
    $file = SAMS_SERVICES_DIR . $service . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
}

/**
 * Get service instance helper
 */
function sams_service($name) {
    return SAMS_Services::get($name);
}

/**
 * Get config value helper  
 */
function sams_config($key, $default = null) {
    return SAMS_Services::config($key, $default);
}

/**
 * Initialize SAMS service layer
 */
function sams_init_services() {
    // Initialize service container
    $container = SAMS_ServiceContainer::getInstance();
    
    // Register core services
    $container->register('auth', new SAMS_AuthService($container));
    $container->register('user', new SAMS_UserService($container));
    $container->register('otp', new SAMS_OTPService($container));
    $container->register('workflow', new SAMS_WorkflowService($container));
    $container->register('tenant', new SAMS_TenantService($container));
    $container->register('chatbot', new SAMS_ChatbotService($container));
    $container->register('error', new SAMS_ErrorService($container));
    
    // Initialize error handling
    $errorService = $container->get('error');
    
    // Set display errors based on environment
    $isDevelopment = defined('ENVIRONMENT') && ENVIRONMENT === 'development';
    $errorService->setDisplayErrors($isDevelopment);
    
    return $container;
}

// Initialize on load
$samsServiceContainer = sams_init_services();
