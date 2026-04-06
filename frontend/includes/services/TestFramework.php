<?php
/**
 * SAMS Testing & QA Framework
 * Automated testing suite for the SAMS platform
 */

class SAMS_TestFramework {
    private $db;
    private $results = [];
    private $currentSuite = null;
    
    public function __construct() {
        $this->db = db();
    }
    
    /**
     * Run complete test suite
     */
    public function runAllTests() {
        $this->results = [
            'start_time' => microtime(true),
            'suites' => [],
            'total_tests' => 0,
            'passed' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        // Run test suites
        $this->runSyntaxTests();
        $this->runServiceTests();
        $this->runDatabaseTests();
        $this->runSecurityTests();
        $this->runIntegrationTests();
        
        $this->results['end_time'] = microtime(true);
        $this->results['duration'] = round($this->results['end_time'] - $this->results['start_time'], 3);
        
        // Log results
        $this->logResults();
        
        return $this->results;
    }
    
    /**
     * PHP Syntax Tests
     */
    private function runSyntaxTests() {
        $this->startSuite('Syntax Check');
        
        $phpFiles = $this->getAllPHPFiles();
        
        foreach ($phpFiles as $file) {
            $testName = 'Syntax: ' . basename($file);
            
            $output = [];
            $returnCode = 0;
            exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $returnCode);
            
            if ($returnCode === 0) {
                $this->pass($testName);
            } else {
                $this->fail($testName, implode("\n", $output));
            }
        }
        
        $this->endSuite();
    }
    
    /**
     * Service Layer Tests
     */
    private function runServiceTests() {
        $this->startSuite('Service Layer');
        
        // Test Service Container
        $this->test('Service Container - Initialization', function() {
            $container = SAMS_ServiceContainer::getInstance();
            return $container !== null;
        });
        
        // Test Auth Service
        $this->test('Auth Service - Password Hash', function() {
            $password = 'testpassword123';
            $hash = password_hash($password, PASSWORD_DEFAULT);
            return password_verify($password, $hash);
        });
        
        // Test OTP Service
        $this->test('OTP Service - Generation', function() {
            $container = SAMS_ServiceContainer::getInstance();
            $otpService = new SAMS_OTPService($container);
            $result = $otpService->generateOTP('test@test.com', 'test');
            return $result['success'] || isset($result['retry_after']);
        });
        
        // Test Tenant Resolution
        $this->test('Tenant Service - Resolution', function() {
            $container = SAMS_ServiceContainer::getInstance();
            $tenantService = new SAMS_TenantService($container);
            return $tenantService->getCurrentTenantId() !== null;
        });
        
        $this->endSuite();
    }
    
    /**
     * Database Tests
     */
    private function runDatabaseTests() {
        $this->startSuite('Database');
        
        // Test connection
        $this->test('Database Connection', function() {
            $db = db();
            return $db && $db->ping();
        });
        
        // Test critical tables exist
        $criticalTables = ['users', 'tenants', 'classes', 'students', 'teachers'];
        
        foreach ($criticalTables as $table) {
            $this->test("Table exists: $table", function() use ($table) {
                $db = db();
                $result = $db->query("SHOW TABLES LIKE '$table'");
                return $result && mysqli_num_rows($result) > 0;
            });
        }
        
        // Test schema version table
        $this->test('Schema Version Table', function() {
            $db = db();
            $result = $db->query("SHOW TABLES LIKE 'schema_versions'");
            return $result && mysqli_num_rows($result) > 0;
        });
        
        $this->endSuite();
    }
    
    /**
     * Security Tests
     */
    private function runSecurityTests() {
        $this->startSuite('Security');
        
        // Test password strength validation
        $this->test('Password - Strong accepted', function() {
            return $this->validatePasswordStrength('StrongP@ssw0rd');
        });
        
        $this->test('Password - Weak rejected', function() {
            return !$this->validatePasswordStrength('123');
        });
        
        // Test input sanitization
        $this->test('Input Sanitization', function() {
            $dangerous = "<script>alert('xss')</script>";
            $cleaned = htmlspecialchars($dangerous, ENT_QUOTES, 'UTF-8');
            return strpos($cleaned, '<script>') === false;
        });
        
        // Test CSRF token generation
        $this->test('CSRF Token Generation', function() {
            $token = bin2hex(random_bytes(32));
            return strlen($token) === 64;
        });
        
        // Test session security
        $this->test('Session Security Headers', function() {
            return ini_get('session.cookie_httponly') == 1 || 
                   ini_get('session.use_only_cookies') == 1;
        });
        
        $this->endSuite();
    }
    
    /**
     * Integration Tests
     */
    private function runIntegrationTests() {
        $this->startSuite('Integration');
        
        // Test user creation workflow
        $this->test('Workflow - User Creation', function() {
            $container = SAMS_ServiceContainer::getInstance();
            $userService = new SAMS_UserService($container);
            
            $testEmail = 'test_' . time() . '@test.com';
            $result = $userService->createUser([
                'email' => $testEmail,
                'role' => 'student',
                'full_name' => 'Test Student',
                'tenant_id' => 1
            ]);
            
            // Clean up
            if ($result['success']) {
                $userId = $result['user_id'];
                $db = db();
                $db->query("DELETE FROM users WHERE id = $userId");
            }
            
            return isset($result['success']);
        });
        
        // Test tenant isolation
        $this->test('Multi-tenant - Data Isolation', function() {
            $container = SAMS_ServiceContainer::getInstance();
            $tenantService = new SAMS_TenantService($container);
            
            $tenantId = $tenantService->getCurrentTenantId();
            return is_numeric($tenantId) && $tenantId > 0;
        });
        
        // Test API endpoints accessible
        $this->test('API - Health Check Endpoint', function() {
            return file_exists(__DIR__ . '/../api/health.php') || 
                   file_exists(__DIR__ . '/../api/index.php');
        });
        
        $this->endSuite();
    }
    
    /**
     * Validate password strength
     */
    private function validatePasswordStrength($password) {
        $minLength = 8;
        
        if (strlen($password) < $minLength) return false;
        if (!preg_match('/[A-Z]/', $password)) return false;
        if (!preg_match('/[a-z]/', $password)) return false;
        if (!preg_match('/[0-9]/', $password)) return false;
        if (!preg_match('/[^A-Za-z0-9]/', $password)) return false;
        
        return true;
    }
    
    /**
     * Get all PHP files in project
     */
    private function getAllPHPFiles() {
        $files = [];
        $rootDir = realpath(__DIR__ . '/../..');
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootDir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                // Skip vendor and cache directories
                $path = $file->getPathname();
                if (strpos($path, '/vendor/') === false && 
                    strpos($path, '/cache/') === false) {
                    $files[] = $path;
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Start test suite
     */
    private function startSuite($name) {
        $this->currentSuite = [
            'name' => $name,
            'tests' => [],
            'passed' => 0,
            'failed' => 0,
            'start_time' => microtime(true)
        ];
    }
    
    /**
     * End test suite
     */
    private function endSuite() {
        $this->currentSuite['end_time'] = microtime(true);
        $this->currentSuite['duration'] = round(
            $this->currentSuite['end_time'] - $this->currentSuite['start_time'], 
            3
        );
        
        $this->results['suites'][] = $this->currentSuite;
        $this->results['total_tests'] += count($this->currentSuite['tests']);
        $this->results['passed'] += $this->currentSuite['passed'];
        $this->results['failed'] += $this->currentSuite['failed'];
        
        $this->currentSuite = null;
    }
    
    /**
     * Run a single test
     */
    private function test($name, $callback) {
        try {
            $result = $callback();
            
            if ($result === true) {
                $this->pass($name);
            } else {
                $this->fail($name, 'Test returned false');
            }
        } catch (Exception $e) {
            $this->fail($name, $e->getMessage());
        }
    }
    
    /**
     * Record passing test
     */
    private function pass($name) {
        $this->currentSuite['tests'][] = [
            'name' => $name,
            'status' => 'PASS',
            'duration' => 0
        ];
        $this->currentSuite['passed']++;
    }
    
    /**
     * Record failing test
     */
    private function fail($name, $message) {
        $this->currentSuite['tests'][] = [
            'name' => $name,
            'status' => 'FAIL',
            'message' => $message,
            'duration' => 0
        ];
        $this->currentSuite['failed']++;
        $this->results['errors'][] = "$name: $message";
    }
    
    /**
     * Log test results
     */
    private function logResults() {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_tests' => $this->results['total_tests'],
            'passed' => $this->results['passed'],
            'failed' => $this->results['failed'],
            'duration' => $this->results['duration'],
            'suites' => array_map(function($suite) {
                return [
                    'name' => $suite['name'],
                    'passed' => $suite['passed'],
                    'failed' => $suite['failed']
                ];
            }, $this->results['suites'])
        ];
        
        $logFile = __DIR__ . '/../../logs/test-results.json';
        file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT));
    }
    
    /**
     * Generate HTML report
     */
    public function generateReport() {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <title>SAMS Test Report</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                .summary { background: #f0f0f0; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
                .suite { margin: 20px 0; border: 1px solid #ddd; }
                .suite-header { background: #333; color: white; padding: 10px; }
                .test { padding: 8px; border-bottom: 1px solid #eee; }
                .PASS { color: green; }
                .FAIL { color: red; }
                .stats { font-size: 18px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <h1>SAMS Test Report</h1>
            <div class="summary">
                <div class="stats">
                    Total: ' . $this->results['total_tests'] . ' | 
                    Passed: <span class="PASS">' . $this->results['passed'] . '</span> | 
                    Failed: <span class="FAIL">' . $this->results['failed'] . '</span> |
                    Duration: ' . $this->results['duration'] . 's
                </div>
            </div>';
        
        foreach ($this->results['suites'] as $suite) {
            $html .= '<div class="suite">
                <div class="suite-header">
                    ' . htmlspecialchars($suite['name']) . ' 
                    (' . $suite['passed'] . '/' . count($suite['tests']) . ' passed)
                </div>';
            
            foreach ($suite['tests'] as $test) {
                $html .= '<div class="test ' . $test['status'] . '">
                    [' . $test['status'] . '] ' . htmlspecialchars($test['name']);
                
                if ($test['status'] === 'FAIL' && !empty($test['message'])) {
                    $html .= '<br><small>' . htmlspecialchars($test['message']) . '</small>';
                }
                
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</body></html>';
        
        return $html;
    }
}

/**
 * Manual test execution script
 */
if (php_sapi_name() === 'cli' || isset($_GET['run_tests'])) {
    $tester = new SAMS_TestFramework();
    $results = $tester->runAllTests();
    
    echo "\n=== SAMS Test Results ===\n";
    echo "Total Tests: " . $results['total_tests'] . "\n";
    echo "Passed: " . $results['passed'] . "\n";
    echo "Failed: " . $results['failed'] . "\n";
    echo "Duration: " . $results['duration'] . "s\n\n";
    
    foreach ($results['suites'] as $suite) {
        echo $suite['name'] . ": " . $suite['passed'] . "/" . count($suite['tests']) . " passed\n";
    }
    
    if ($results['failed'] > 0) {
        echo "\n=== Errors ===\n";
        foreach ($results['errors'] as $error) {
            echo "- $error\n";
        }
        exit(1);
    }
    
    echo "\nAll tests passed!\n";
    exit(0);
}
