<?php
/**
 * SAMS System Validation Script
 * Automated validation of the entire system
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

class SAMS_SystemValidator {
    private $results = [];
    private $errors = [];
    private $warnings = [];
    private $db;
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Run all validations
     */
    public function validateAll() {
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║           SAMS System Validation Script                      ║\n";
        echo "║           Automated System Health Check                      ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n\n";
        
        $this->validateDatabase();
        $this->validateCoreFiles();
        $this->validateAdminWorkflows();
        $this->validateAPIEndpoints();
        $this->validateNavigation();
        $this->validateSecurity();
        $this->validateConfiguration();
        
        $this->printSummary();
        
        return [
            'success' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'results' => $this->results
        ];
    }
    
    /**
     * Validate database connectivity and tables
     */
    private function validateDatabase() {
        echo "📊 Validating Database...\n";
        
        // Check connection
        try {
            $result = $this->db->query("SELECT 1");
            if ($result) {
                $this->results[] = "✅ Database connection successful";
            } else {
                $this->errors[] = "❌ Database connection failed";
                return;
            }
        } catch (Exception $e) {
            $this->errors[] = "❌ Database error: " . $e->getMessage();
            return;
        }
        
        // Check required tables
        $requiredTables = [
            'users', 'students', 'teachers', 'classes', 'enrollments',
            'attendance', 'otp_codes', 'tenants', 'invites'
        ];
        
        foreach ($requiredTables as $table) {
            $check = $this->db->query("SHOW TABLES LIKE '$table'");
            if ($check && mysqli_num_rows($check) > 0) {
                $this->results[] = "✅ Table '$table' exists";
            } else {
                $this->errors[] = "❌ Table '$table' missing";
            }
        }
        
        echo "   Done\n\n";
    }
    
    /**
     * Validate core system files
     */
    private function validateCoreFiles() {
        echo "📁 Validating Core Files...\n";
        
        $coreFiles = [
            'includes/config.php',
            'includes/database.php',
            'includes/functions.php',
            'includes/AdminWorkflow.php',
            'includes/AccountActivation.php',
            'includes/AIAccountCreator.php',
            'includes/ZeroFlawEnforcer.php',
            'includes/SecurityMiddleware.php',
            'includes/services/ServiceContainer.php',
            'includes/services/AuthService.php',
            'includes/services/UserService.php',
            'includes/services/OTPService.php',
            'includes/services/ChatbotService.php'
        ];
        
        foreach ($coreFiles as $file) {
            $path = __DIR__ . '/../' . $file;
            if (file_exists($path)) {
                $this->results[] = "✅ File '$file' exists";
            } else {
                $this->errors[] = "❌ File '$file' missing";
            }
        }
        
        echo "   Done\n\n";
    }
    
    /**
     * Validate admin workflow pages
     */
    private function validateAdminWorkflows() {
        echo "👨‍💼 Validating Admin Workflows...\n";
        
        $workflowPages = [
            'admin/dashboard.php' => 'Admin Dashboard',
            'admin/management.php' => 'Management Dashboard',
            'admin/users.php' => 'User Management',
            'admin/teachers.php' => 'Teacher Management',
            'admin/students.php' => 'Student Management',
            'admin/classes.php' => 'Class Management',
            'admin/bulk-import.php' => 'Bulk Import',
            'admin/settings.php' => 'Settings',
            'admin/attendance.php' => 'Attendance'
        ];
        
        foreach ($workflowPages as $file => $name) {
            $path = __DIR__ . '/../' . $file;
            if (file_exists($path)) {
                $this->results[] = "✅ $name available";
            } else {
                $this->warnings[] = "⚠️ $name missing ($file)";
            }
        }
        
        echo "   Done\n\n";
    }
    
    /**
     * Validate API endpoints
     */
    private function validateAPIEndpoints() {
        echo "🔌 Validating API Endpoints...\n";
        
        $endpoints = [
            'api/ai-process-form.php' => 'AI Form Processing',
            'api/chatbot.php' => 'Chatbot API',
            'api/health.php' => 'Health Check'
        ];
        
        foreach ($endpoints as $file => $name) {
            $path = __DIR__ . '/../' . $file;
            if (file_exists($path)) {
                $this->results[] = "✅ API '$name' available";
            } else {
                $this->warnings[] = "⚠️ API '$name' missing";
            }
        }
        
        echo "   Done\n\n";
    }
    
    /**
     * Validate navigation links
     */
    private function validateNavigation() {
        echo "🧭 Validating Navigation...\n";
        
        $sidebarPath = __DIR__ . '/../includes/sidebar-nav.php';
        if (file_exists($sidebarPath)) {
            $this->results[] = "✅ Sidebar navigation exists";
            
            // Check if it includes role-based menus
            $content = file_get_contents($sidebarPath);
            if (strpos($content, 'admin') !== false && 
                strpos($content, 'teacher') !== false &&
                strpos($content, 'student') !== false) {
                $this->results[] = "✅ Role-based navigation configured";
            }
        } else {
            $this->errors[] = "❌ Sidebar navigation missing";
        }
        
        echo "   Done\n\n";
    }
    
    /**
     * Validate security components
     */
    private function validateSecurity() {
        echo "🔐 Validating Security...\n";
        
        $securityFiles = [
            'includes/SecurityMiddleware.php',
            'includes/services/AuthService.php',
            'includes/services/OTPService.php'
        ];
        
        foreach ($securityFiles as $file) {
            $path = __DIR__ . '/../' . $file;
            if (file_exists($path)) {
                $this->results[] = "✅ Security component '$file' exists";
            } else {
                $this->errors[] = "❌ Security component '$file' missing";
            }
        }
        
        echo "   Done\n\n";
    }
    
    /**
     * Validate configuration
     */
    private function validateConfiguration() {
        echo "⚙️  Validating Configuration...\n";
        
        // Check if config constants are defined
        if (defined('DB_HOST') && defined('DB_NAME')) {
            $this->results[] = "✅ Database configuration present";
        } else {
            $this->warnings[] = "⚠️ Database configuration may be incomplete";
        }
        
        // Check upload directory
        $uploadDir = __DIR__ . '/../uploads';
        if (is_dir($uploadDir) && is_writable($uploadDir)) {
            $this->results[] = "✅ Uploads directory writable";
        } else {
            $this->warnings[] = "⚠️ Uploads directory not writable";
        }
        
        // Check logs directory
        $logsDir = __DIR__ . '/../logs';
        if (is_dir($logsDir) && is_writable($logsDir)) {
            $this->results[] = "✅ Logs directory writable";
        } else {
            $this->warnings[] = "⚠️ Logs directory not writable";
        }
        
        echo "   Done\n\n";
    }
    
    /**
     * Print validation summary
     */
    private function printSummary() {
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║                    VALIDATION SUMMARY                        ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n\n";
        
        echo "✅ Passed: " . count($this->results) . "\n";
        echo "⚠️  Warnings: " . count($this->warnings) . "\n";
        echo "❌ Errors: " . count($this->errors) . "\n\n";
        
        if (!empty($this->errors)) {
            echo "ERRORS:\n";
            echo str_repeat("-", 50) . "\n";
            foreach ($this->errors as $error) {
                echo "  $error\n";
            }
            echo "\n";
        }
        
        if (!empty($this->warnings)) {
            echo "WARNINGS:\n";
            echo str_repeat("-", 50) . "\n";
            foreach ($this->warnings as $warning) {
                echo "  $warning\n";
            }
            echo "\n";
        }
        
        echo str_repeat("=", 50) . "\n";
        
        if (empty($this->errors)) {
            echo "🎉 SYSTEM VALIDATION PASSED!\n";
            echo "SAMS is ready for deployment.\n";
        } else {
            echo "⚠️  SYSTEM VALIDATION FAILED\n";
            echo "Please fix the errors above before deployment.\n";
        }
        
        echo str_repeat("=", 50) . "\n";
    }
}

// Run validation if called directly
if (php_sapi_name() === 'cli' || basename($_SERVER['PHP_SELF']) === 'validate-system.php') {
    $validator = new SAMS_SystemValidator();
    $result = $validator->validateAll();
    exit($result['success'] ? 0 : 1);
}
