<?php
/**
 * AI-Assisted Account Creation System
 * Processes Google Form submissions to automatically create user accounts
 */

class SAMS_AIAccountCreator {
    private $db;
    private $workflowService;
    private $emailService;
    private $validationRules;
    
    public function __construct() {
        $this->db = db();
        $this->workflowService = sams_service('workflow');
        $this->emailService = sams_service('email');
        $this->initializeValidationRules();
    }
    
    /**
     * Initialize field validation rules
     */
    private function initializeValidationRules() {
        $this->validationRules = [
            'email' => [
                'required' => true,
                'type' => 'email',
                'unique' => true
            ],
            'full_name' => [
                'required' => true,
                'min_length' => 2,
                'max_length' => 100
            ],
            'role' => [
                'required' => true,
                'allowed' => ['teacher', 'student', 'parent', 'staff', 'admin']
            ],
            'phone' => [
                'required' => false,
                'type' => 'phone'
            ],
            'department' => [
                'required' => false,
                'max_length' => 100
            ],
            'grade_level' => [
                'required' => false,
                'type' => 'grade'
            ],
            'parent_email' => [
                'required' => false,
                'type' => 'email'
            ]
        ];
    }
    
    /**
     * Process Google Form webhook data
     */
    public function processFormSubmission($formData) {
        // Extract data from various form formats
        $extractedData = $this->extractFormData($formData);
        
        if (empty($extractedData)) {
            return [
                'success' => false,
                'error' => 'No valid data extracted from form'
            ];
        }
        
        $results = [
            'processed' => 0,
            'created' => 0,
            'failed' => 0,
            'errors' => [],
            'created_accounts' => []
        ];
        
        // Process each entry
        foreach ($extractedData as $entry) {
            $results['processed']++;
            
            // Normalize data
            $normalizedData = $this->normalizeData($entry);
            
            // Validate data
            $validation = $this->validateData($normalizedData);
            
            if (!$validation['valid']) {
                $results['failed']++;
                $results['errors'][] = [
                    'entry' => $entry,
                    'errors' => $validation['errors']
                ];
                continue;
            }
            
            // Check for duplicates
            if ($this->isDuplicate($normalizedData['email'])) {
                $results['failed']++;
                $results['errors'][] = [
                    'entry' => $entry,
                    'errors' => ['Email already exists: ' . $normalizedData['email']]
                ];
                continue;
            }
            
            // Create account
            $creationResult = $this->createAccount($normalizedData);
            
            if ($creationResult['success']) {
                $results['created']++;
                $results['created_accounts'][] = [
                    'user_id' => $creationResult['user_id'],
                    'email' => $normalizedData['email'],
                    'role' => $normalizedData['role']
                ];
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'entry' => $entry,
                    'errors' => [$creationResult['error']]
                ];
            }
        }
        
        // Log batch processing
        $this->logBatchProcessing($results);
        
        return $results;
    }
    
    /**
     * Extract data from form submission (supports JSON, CSV, key-value)
     */
    private function extractFormData($formData) {
        $data = [];
        
        // Try JSON first
        $json = json_decode($formData, true);
        if ($json && is_array($json)) {
            // Handle Google Forms format
            if (isset($json['responses'])) {
                foreach ($json['responses'] as $response) {
                    $data[] = $this->parseGoogleFormResponse($response);
                }
            } else {
                $data[] = $json;
            }
            return $data;
        }
        
        // Try CSV
        if (strpos($formData, ',') !== false && strpos($formData, "\n") !== false) {
            $lines = explode("\n", trim($formData));
            $headers = str_getcsv(array_shift($lines));
            
            foreach ($lines as $line) {
                if (trim($line)) {
                    $values = str_getcsv($line);
                    $data[] = array_combine($headers, $values);
                }
            }
            return $data;
        }
        
        // Try key-value format
        $entries = explode("\n\n", trim($formData));
        foreach ($entries as $entry) {
            $lines = explode("\n", $entry);
            $parsed = [];
            
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $parsed[trim($key)] = trim($value);
                }
            }
            
            if (!empty($parsed)) {
                $data[] = $parsed;
            }
        }
        
        return $data;
    }
    
    /**
     * Parse Google Forms response format
     */
    private function parseGoogleFormResponse($response) {
        $parsed = [];
        
        // Map Google Form field names to system fields
        $fieldMappings = [
            'Name' => 'full_name',
            'Full Name' => 'full_name',
            'Email' => 'email',
            'Email Address' => 'email',
            'E-mail' => 'email',
            'Role' => 'role',
            'User Role' => 'role',
            'Phone' => 'phone',
            'Phone Number' => 'phone',
            'Department' => 'department',
            'Grade' => 'grade_level',
            'Grade Level' => 'grade_level',
            'Class' => 'grade_level',
            'Parent Email' => 'parent_email',
            'Parent\'s Email' => 'parent_email'
        ];
        
        foreach ($response as $key => $value) {
            $normalizedKey = $fieldMappings[$key] ?? $this->normalizeFieldName($key);
            $parsed[$normalizedKey] = $value;
        }
        
        return $parsed;
    }
    
    /**
     * Normalize field name
     */
    private function normalizeFieldName($name) {
        $name = strtolower(trim($name));
        $name = str_replace([' ', '-', '_'], '_', $name);
        return $name;
    }
    
    /**
     * Normalize extracted data
     */
    private function normalizeData($data) {
        $normalized = [];
        
        // Email normalization
        if (isset($data['email'])) {
            $normalized['email'] = strtolower(trim($data['email']));
        }
        
        // Name normalization
        if (isset($data['full_name'])) {
            $normalized['full_name'] = $this->normalizeName($data['full_name']);
        }
        
        // Role normalization
        if (isset($data['role'])) {
            $normalized['role'] = $this->normalizeRole($data['role']);
        }
        
        // Copy other fields
        $fields = ['phone', 'department', 'grade_level', 'parent_email', 'employee_id', 'admission_no'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $normalized[$field] = trim($data[$field]);
            }
        }
        
        // Set defaults
        $normalized['status'] = 'pending_activation';
        $normalized['tenant_id'] = $_SESSION['tenant_id'] ?? 1;
        
        return $normalized;
    }
    
    /**
     * Normalize name (Title Case)
     */
    private function normalizeName($name) {
        return ucwords(strtolower(trim($name)));
    }
    
    /**
     * Normalize role
     */
    private function normalizeRole($role) {
        $role = strtolower(trim($role));
        
        $mappings = [
            'teacher' => 'teacher',
            'instructor' => 'teacher',
            'professor' => 'teacher',
            'student' => 'student',
            'pupil' => 'student',
            'learner' => 'student',
            'parent' => 'parent',
            'guardian' => 'parent',
            'staff' => 'staff',
            'admin' => 'admin',
            'administrator' => 'admin'
        ];
        
        return $mappings[$role] ?? $role;
    }
    
    /**
     * Validate normalized data
     */
    private function validateData($data) {
        $errors = [];
        
        foreach ($this->validationRules as $field => $rules) {
            $value = $data[$field] ?? null;
            
            // Required check
            if ($rules['required'] && empty($value)) {
                $errors[] = "$field is required";
                continue;
            }
            
            // Skip further validation if empty and not required
            if (empty($value) && !$rules['required']) {
                continue;
            }
            
            // Type validation
            if (isset($rules['type'])) {
                switch ($rules['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[] = "$field must be a valid email address";
                        }
                        break;
                        
                    case 'phone':
                        if (!preg_match('/^[\d\s\-\+\(\)]+$/', $value)) {
                            $errors[] = "$field must be a valid phone number";
                        }
                        break;
                        
                    case 'grade':
                        if (!preg_match('/^[\d]+$/', $value) || $value < 1 || $value > 12) {
                            $errors[] = "$field must be a valid grade level (1-12)";
                        }
                        break;
                }
            }
            
            // Length validation
            if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
                $errors[] = "$field must be at least {$rules['min_length']} characters";
            }
            
            if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
                $errors[] = "$field must be no more than {$rules['max_length']} characters";
            }
            
            // Allowed values validation
            if (isset($rules['allowed']) && !in_array($value, $rules['allowed'])) {
                $errors[] = "$field must be one of: " . implode(', ', $rules['allowed']);
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Check if email already exists
     */
    private function isDuplicate($email) {
        $email = mysqli_real_escape_string($this->db, $email);
        
        $result = $this->db->query("SELECT id FROM users WHERE email = '$email' AND status != 'deleted' LIMIT 1");
        
        return $result && mysqli_num_rows($result) > 0;
    }
    
    /**
     * Create user account
     */
    private function createAccount($data) {
        // Generate activation token
        $activationToken = $this->generateActivationToken();
        
        // Prepare user data
        $userData = [
            'email' => $data['email'],
            'role' => $data['role'],
            'status' => 'pending_activation',
            'password_hash' => null, // No password - user will set via activation
            'tenant_id' => $data['tenant_id'],
            'activation_token' => $activationToken,
            'created_by' => $_SESSION['user_id'] ?? null
        ];
        
        // Add role-specific data
        if ($data['role'] === 'teacher') {
            $userData['full_name'] = $data['full_name'];
            $userData['department'] = $data['department'] ?? null;
            $userData['employee_id'] = $data['employee_id'] ?? null;
        } elseif ($data['role'] === 'student') {
            $userData['full_name'] = $data['full_name'];
            $userData['grade_level'] = $data['grade_level'] ?? null;
            $userData['admission_no'] = $data['admission_no'] ?? null;
            $userData['parent_email'] = $data['parent_email'] ?? null;
        }
        
        // Create user via workflow service
        $result = $this->workflowService->createUser($userData);
        
        if ($result['success']) {
            // Send activation email
            $this->sendActivationEmail($data['email'], $activationToken, $data['full_name']);
            
            return [
                'success' => true,
                'user_id' => $result['user_id'],
                'activation_token' => $activationToken
            ];
        }
        
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to create account'
        ];
    }
    
    /**
     * Generate secure activation token
     */
    private function generateActivationToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Send activation email
     */
    private function sendActivationEmail($email, $token, $name) {
        $activationUrl = $this->getBaseUrl() . "/activate-account.php?token=" . urlencode($token);
        
        $subject = "Activate Your SAMS Account";
        
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #4F46E5;'>Welcome to SAMS!</h2>
                
                <p>Hello $name,</p>
                
                <p>An account has been created for you in the School Attendance Management System.</p>
                
                <p>To activate your account and set your password, please click the button below:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$activationUrl' 
                       style='background: #4F46E5; color: white; padding: 12px 30px; 
                              text-decoration: none; border-radius: 5px; display: inline-block;'>
                        Activate My Account
                    </a>
                </div>
                
                <p>Or copy and paste this link into your browser:</p>
                <p style='background: #f5f5f5; padding: 10px; word-break: break-all;'>
                    $activationUrl
                </p>
                
                <p><strong>This link will expire in 24 hours.</strong></p>
                
                <p>If you did not request this account, please ignore this email.</p>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                <p style='font-size: 12px; color: #666;'>
                    This is an automated message from SAMS. Please do not reply to this email.
                </p>
            </div>
        </body>
        </html>
        ";
        
        return $this->emailService->send($email, $subject, $body);
    }
    
    /**
     * Get base URL
     */
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "$protocol://$host";
    }
    
    /**
     * Log batch processing
     */
    private function logBatchProcessing($results) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'processed' => $results['processed'],
            'created' => $results['created'],
            'failed' => $results['failed'],
            'created_by' => $_SESSION['user_id'] ?? 'system'
        ];
        
        // Insert into ai_processing_logs table
        $json = mysqli_real_escape_string($this->db, json_encode($logData));
        $this->db->query("INSERT INTO ai_processing_logs (data, created_at) VALUES ('$json', NOW())");
    }
    
    /**
     * Get processing statistics
     */
    public function getStatistics($days = 30) {
        $days = (int)$days;
        
        $stats = [
            'total_processed' => 0,
            'total_created' => 0,
            'total_failed' => 0,
            'by_role' => []
        ];
        
        $result = $this->db->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as created,
            role
            FROM users 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL $days DAY)
            AND activation_token IS NOT NULL
            GROUP BY role");
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $stats['by_role'][$row['role']] = [
                    'total' => (int)$row['total'],
                    'created' => (int)$row['created']
                ];
                $stats['total_processed'] += $row['total'];
                $stats['total_created'] += $row['created'];
            }
        }
        
        return $stats;
    }
}

/**
 * API endpoint for Google Form webhooks
 */
if (basename($_SERVER['PHP_SELF']) === 'ai-webhook.php') {
    header('Content-Type: application/json');
    
    // Verify webhook secret
    $webhookSecret = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
    $expectedSecret = defined('AI_WEBHOOK_SECRET') ? AI_WEBHOOK_SECRET : '';
    
    if ($webhookSecret !== $expectedSecret) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit;
    }
    
    // Process form submission
    $creator = new SAMS_AIAccountCreator();
    $result = $creator->processFormSubmission($data);
    
    echo json_encode($result);
}
