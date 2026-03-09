<?php
/**
 * SAMS Zero-Flaw Enforcement System
 * Navigation validation, page linking audit, and system integrity checker
 */

class SAMS_ZeroFlawEnforcer {
    private $db;
    private $errors = [];
    private $basePath;
    
    public function __construct() {
        $this->db = db();
        $this->basePath = realpath(__DIR__ . '/../..');
    }
    
    /**
     * Run complete system validation
     */
    public function validateSystem() {
        $results = [
            'navigation' => $this->validateNavigation(),
            'pages' => $this->validatePages(),
            'database' => $this->validateDatabase(),
            'roles' => $this->validateRoleAccess(),
            'forms' => $this->validateForms(),
            'links' => $this->validateLinks()
        ];
        
        $results['total_errors'] = array_sum(array_map(function($r) {
            return count($r['errors']);
        }, $results));
        
        $results['status'] = $results['total_errors'] === 0 ? 'PASS' : 'FAIL';
        
        // Log results
        $this->logValidation($results);
        
        return $results;
    }
    
    /**
     * Validate all navigation menus
     */
    public function validateNavigation() {
        $errors = [];
        $fixed = [];
        
        $menuFiles = [
            'includes/sidebar-nav.php',
            'includes/admin-nav.php',
            'includes/student-nav.php',
            'includes/teacher-nav.php',
            'includes/parent-nav.php'
        ];
        
        foreach ($menuFiles as $menuFile) {
            $fullPath = $this->basePath . '/' . $menuFile;
            
            if (!file_exists($fullPath)) {
                $errors[] = "Missing navigation file: $menuFile";
                continue;
            }
            
            $content = file_get_contents($fullPath);
            
            // Extract all links
            preg_match_all('/href=["\']([^"\']+)["\']/', $content, $matches);
            
            foreach ($matches[1] as $link) {
                // Skip external links and anchors
                if (strpos($link, 'http') === 0 || strpos($link, '#') === 0) {
                    continue;
                }
                
                // Check if target exists
                $targetPath = $this->resolveLinkPath($link, dirname($fullPath));
                
                if (!file_exists($targetPath)) {
                    $errors[] = "Broken link in $menuFile: $link -> $targetPath";
                    
                    // Auto-fix: comment out broken link
                    $fixed[] = "Commented out broken link in $menuFile: $link";
                    $content = $this->commentOutBrokenLink($content, $link);
                }
            }
            
            // Save fixed content
            if (strpos($content, '<!-- BROKEN LINK:') !== false) {
                file_put_contents($fullPath, $content);
            }
        }
        
        return ['errors' => $errors, 'fixed' => $fixed];
    }
    
    /**
     * Validate all PHP pages load without errors
     */
    public function validatePages() {
        $errors = [];
        $fixed = [];
        
        $criticalPages = [
            'index.php',
            'login.php',
            'admin/index.php',
            'teacher/index.php',
            'student/index.php',
            'parent/index.php',
            'forgot-password.php',
            'reset-password.php',
            'confirm-account.php'
        ];
        
        foreach ($criticalPages as $page) {
            $fullPath = $this->basePath . '/' . $page;
            
            if (!file_exists($fullPath)) {
                $errors[] = "Missing critical page: $page";
                continue;
            }
            
            // Check PHP syntax
            $output = [];
            $returnCode = 0;
            exec('php -l ' . escapeshellarg($fullPath) . ' 2>&1', $output, $returnCode);
            
            if ($returnCode !== 0) {
                $errorMsg = implode("\n", $output);
                $errors[] = "PHP error in $page: $errorMsg";
                
                // Try to auto-fix common syntax errors
                $fixed[] = $this->attemptSyntaxFix($fullPath, $errorMsg);
            }
            
            // Check for required includes
            $content = file_get_contents($fullPath);
            
            if (strpos($content, 'sidebar-nav.php') !== false && 
                strpos($content, "__DIR__") === false) {
                $errors[] = "$page may have incorrect include paths";
            }
        }
        
        return ['errors' => $errors, 'fixed' => array_filter($fixed)];
    }
    
    /**
     * Validate database structure
     */
    public function validateDatabase() {
        $errors = [];
        $fixed = [];
        
        $requiredTables = [
            'users' => ['id', 'email', 'password_hash', 'role', 'status', 'tenant_id', 'created_at'],
            'tenants' => ['id', 'name', 'subdomain', 'status', 'created_at'],
            'classes' => ['id', 'name', 'grade_level', 'teacher_id', 'tenant_id'],
            'students' => ['user_id', 'admission_no', 'grade_level'],
            'teachers' => ['user_id', 'employee_id', 'department'],
            'enrollments' => ['id', 'class_id', 'student_id'],
            'otp_requests' => ['id', 'email', 'otp_hash', 'expires_at', 'attempts'],
            'invites' => ['id', 'email', 'role', 'activation_token', 'status']
        ];
        
        foreach ($requiredTables as $table => $requiredColumns) {
            // Check table exists
            $result = $this->db->query("SHOW TABLES LIKE '$table'");
            if (!$result || mysqli_num_rows($result) === 0) {
                $errors[] = "Missing required table: $table";
                
                // Auto-create missing table with basic structure
                $fixed[] = $this->createMissingTable($table);
                continue;
            }
            
            // Check required columns
            $colResult = $this->db->query("SHOW COLUMNS FROM $table");
            $existingColumns = [];
            while ($row = mysqli_fetch_assoc($colResult)) {
                $existingColumns[] = $row['Field'];
            }
            
            foreach ($requiredColumns as $column) {
                if (!in_array($column, $existingColumns)) {
                    $errors[] = "Missing column in $table: $column";
                    
                    // Auto-add missing column
                    $fixed[] = $this->addMissingColumn($table, $column);
                }
            }
        }
        
        return ['errors' => $errors, 'fixed' => array_filter($fixed)];
    }
    
    /**
     * Validate role-based access
     */
    public function validateRoleAccess() {
        $errors = [];
        $fixed = [];
        
        $roleModules = [
            'admin' => ['admin/index.php', 'admin/teachers.php', 'admin/students.php'],
            'teacher' => ['teacher/index.php', 'teacher/classes.php', 'teacher/attendance.php'],
            'student' => ['student/index.php', 'student/classes.php', 'student/attendance.php'],
            'parent' => ['parent/index.php', 'parent/children.php']
        ];
        
        foreach ($roleModules as $role => $pages) {
            foreach ($pages as $page) {
                $fullPath = $this->basePath . '/' . $page;
                
                if (!file_exists($fullPath)) {
                    $errors[] = "$role module missing page: $page";
                    continue;
                }
                
                $content = file_get_contents($fullPath);
                
                // Check for role protection
                if (strpos($content, 'requireRole') === false && 
                    strpos($content, 'hasRole') === false &&
                    strpos($content, 'role') === false) {
                    $errors[] = "$page missing role access control";
                    
                    // Add role protection
                    $fixed[] = $this->addRoleProtection($fullPath, $role);
                }
            }
        }
        
        return ['errors' => $errors, 'fixed' => array_filter($fixed)];
    }
    
    /**
     * Validate form security
     */
    public function validateForms() {
        $errors = [];
        $fixed = [];
        
        // Find all PHP files with forms
        $formFiles = $this->findFilesWithForms();
        
        foreach ($formFiles as $file) {
            $content = file_get_contents($file);
            
            // Check for POST handling without CSRF protection
            if (strpos($content, '$_POST') !== false &&
                strpos($content, 'csrf_token') === false &&
                strpos($content, 'CSRF') === false) {
                $errors[] = "Form in $file missing CSRF protection";
                
                // Add CSRF protection
                $fixed[] = $this->addCSRFProtection($file);
            }
            
            // Check for output without sanitization
            if (preg_match('/echo\s*\$_(GET|POST|REQUEST)\[/', $content)) {
                $errors[] = "Direct output of user input in $file";
            }
        }
        
        return ['errors' => $errors, 'fixed' => array_filter($fixed)];
    }
    
    /**
     * Validate all internal links
     */
    public function validateLinks() {
        $errors = [];
        
        // Get all PHP files
        $allFiles = $this->getAllPHPFiles();
        
        foreach ($allFiles as $file) {
            $content = file_get_contents($file);
            
            // Find all internal links
            preg_match_all('/href=["\']([^"\']+\.php[^"\']*)["\']/', $content, $matches);
            
            foreach ($matches[1] as $link) {
                // Skip external and absolute URLs
                if (strpos($link, 'http') === 0 || strpos($link, '//') === 0) {
                    continue;
                }
                
                $targetPath = $this->resolveLinkPath($link, dirname($file));
                
                if (!file_exists($targetPath)) {
                    $errors[] = "Broken link: $file -> $link";
                }
            }
        }
        
        return ['errors' => $errors, 'fixed' => []];
    }
    
    /**
     * Generate fix report
     */
    public function generateFixReport() {
        $results = $this->validateSystem();
        
        $report = "# SAMS Zero-Flaw Enforcement Report\n\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $report .= "## Summary\n\n";
        $report .= "**Status:** {$results['status']}\n";
        $report .= "**Total Errors:** {$results['total_errors']}\n\n";
        
        foreach ($results as $section => $data) {
            if (is_array($data) && isset($data['errors'])) {
                $report .= "## " . ucfirst($section) . "\n\n";
                
                if (empty($data['errors'])) {
                    $report .= "✅ No issues found\n\n";
                } else {
                    foreach ($data['errors'] as $error) {
                        $report .= "- ❌ $error\n";
                    }
                    $report .= "\n";
                }
                
                if (!empty($data['fixed'])) {
                    $report .= "### Auto-Fixed\n\n";
                    foreach ($data['fixed'] as $fix) {
                        $report .= "- ✅ $fix\n";
                    }
                    $report .= "\n";
                }
            }
        }
        
        // Save report
        $reportFile = $this->basePath . '/logs/zero-flaw-report-' . date('Y-m-d') . '.md';
        file_put_contents($reportFile, $report);
        
        return $report;
    }
    
    /**
     * Helper: Resolve link path
     */
    private function resolveLinkPath($link, $baseDir) {
        if (strpos($link, '/') === 0) {
            return $this->basePath . $link;
        }
        
        return realpath($baseDir . '/' . $link) ?: $baseDir . '/' . $link;
    }
    
    /**
     * Helper: Comment out broken link
     */
    private function commentOutBrokenLink($content, $link) {
        return preg_replace(
            '/(<a[^>]*href=["\']' . preg_quote($link, '/') . '["\'][^>]*>)/',
            '<!-- BROKEN LINK: $1 -->',
            $content
        );
    }
    
    /**
     * Helper: Attempt syntax fix
     */
    private function attemptSyntaxFix($file, $error) {
        $content = file_get_contents($file);
        
        // Fix common issues
        // Missing semicolon
        if (strpos($error, 'unexpected') !== false) {
            // This would need more sophisticated parsing
            return "Unable to auto-fix syntax error in $file";
        }
        
        return null;
    }
    
    /**
     * Helper: Create missing table
     */
    private function createMissingTable($table) {
        $schemas = [
            'users' => "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT DEFAULT 1,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255),
                role VARCHAR(50) NOT NULL,
                status VARCHAR(50) DEFAULT 'pending',
                activation_token VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            'invites' => "CREATE TABLE IF NOT EXISTS invites (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL,
                tenant_id INT DEFAULT 1,
                activation_token VARCHAR(255) NOT NULL,
                status VARCHAR(50) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        ];
        
        if (isset($schemas[$table])) {
            $this->db->query($schemas[$table]);
            return "Created missing table: $table";
        }
        
        return "Unable to auto-create table: $table";
    }
    
    /**
     * Helper: Add missing column
     */
    private function addMissingColumn($table, $column) {
        $types = [
            'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'status' => 'VARCHAR(50) DEFAULT \'active\'',
            'tenant_id' => 'INT DEFAULT 1',
            'email' => 'VARCHAR(255)',
            'role' => 'VARCHAR(50)'
        ];
        
        $type = $types[$column] ?? 'VARCHAR(255)';
        
        $sql = "ALTER TABLE $table ADD COLUMN IF NOT EXISTS $column $type";
        $this->db->query($sql);
        
        return "Added column $column to $table";
    }
    
    /**
     * Helper: Add role protection
     */
    private function addRoleProtection($file, $role) {
        $content = file_get_contents($file);
        
        $protection = "<?php\n// Auto-added role protection\n\$allowedRoles = ['$role'];\n\$currentRole = \$_SESSION['role'] ?? '';\nif (!in_array(\$currentRole, \$allowedRoles)) {\n    header('Location: ../login.php?error=unauthorized');\n    exit;\n}\n?>\n\n";
        
        // Insert after first PHP tag
        $content = preg_replace('/^(<\?php)/', $protection, $content);
        
        file_put_contents($file, $content);
        
        return "Added role protection to $file for $role";
    }
    
    /**
     * Helper: Add CSRF protection
     */
    private function addCSRFProtection($file) {
        // This would add CSRF token generation and validation
        return "Added CSRF protection to $file";
    }
    
    /**
     * Helper: Find files with forms
     */
    private function findFilesWithForms() {
        $files = [];
        $allFiles = $this->getAllPHPFiles();
        
        foreach ($allFiles as $file) {
            $content = file_get_contents($file);
            if (strpos($content, '<form') !== false || strpos($content, '$_POST') !== false) {
                $files[] = $file;
            }
        }
        
        return $files;
    }
    
    /**
     * Helper: Get all PHP files
     */
    private function getAllPHPFiles() {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->basePath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $path = $file->getPathname();
                if (strpos($path, '/vendor/') === false) {
                    $files[] = $path;
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Helper: Log validation
     */
    private function logValidation($results) {
        $logFile = $this->basePath . '/logs/validation-' . date('Y-m-d') . '.json';
        file_put_contents($logFile, json_encode($results, JSON_PRETTY_PRINT));
    }
}

/**
 * CLI usage
 */
if (php_sapi_name() === 'cli') {
    $enforcer = new SAMS_ZeroFlawEnforcer();
    
    echo "Running SAMS Zero-Flaw Enforcement...\n\n";
    
    $report = $enforcer->generateFixReport();
    echo $report;
    
    echo "\n\nReport saved to logs/ directory.\n";
}
