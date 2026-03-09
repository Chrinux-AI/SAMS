<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/AccountCreationAutomation.php';

class BulkStudentImport
{
    private $tenantId;
    private $automation;
    
    public function __construct($tenantId = null)
    {
        $this->tenantId = $tenantId ?? 1;
        $this->automation = new AccountCreationAutomation($this->tenantId);
    }
    
    /**
     * Process CSV upload for bulk student import
     */
    public function processCSVUpload($fileData, $options = [])
    {
        try {
            // Validate file
            $validation = $this->validateCSVFile($fileData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Invalid file: ' . implode(', ', $validation['errors'])
                ];
            }
            
            // Parse CSV
            $students = $this->parseCSV($fileData['tmp_name']);
            
            if (empty($students)) {
                return [
                    'success' => false,
                    'error' => 'No valid student records found in CSV'
                ];
            }
            
            // Validate and process students
            $results = $this->processStudents($students, $options);
            
            // Generate report
            $report = $this->generateImportReport($results);
            
            return [
                'success' => true,
                'report' => $report,
                'results' => $results
            ];
            
        } catch (Exception $e) {
            error_log("BulkStudentImport::processCSVUpload error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate CSV file
     */
    private function validateCSVFile($file)
    {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'No file uploaded or invalid file';
        }
        
        // Check file size (max 5MB)
        if (isset($file['size']) && $file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'File size exceeds 5MB limit';
        }
        
        // Check file type
        if (isset($file['type']) && !in_array($file['type'], ['text/csv', 'application/csv'])) {
            $errors[] = 'File must be a CSV file';
        }
        
        // Check file extension
        if (isset($file['name'])) {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($extension !== 'csv') {
                $errors[] = 'File must have .csv extension';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Parse CSV file
     */
    private function parseCSV($filePath)
    {
        $students = [];
        $headerRow = null;
        $rowNumber = 0;
        
        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $rowNumber++;
                
                // Skip empty rows
                if (empty(array_filter($data))) {
                    continue;
                }
                
                // First row should be headers
                if ($headerRow === null) {
                    $headerRow = $this->normalizeHeaders($data);
                    continue;
                }
                
                // Map data to headers
                $studentData = array_combine($headerRow, $data);
                
                // Add row number for error reporting
                $studentData['csv_row'] = $rowNumber;
                
                $students[] = $studentData;
            }
            fclose($handle);
        }
        
        return $students;
    }
    
    /**
     * Normalize CSV headers
     */
    private function normalizeHeaders($headers)
    {
        $normalized = [];
        $headerMap = [
            'student_name' => ['student_name', 'name', 'full_name', 'student'],
            'student_email' => ['student_email', 'email', 'student_email_address'],
            'class' => ['class', 'class_name', 'grade', 'section'],
            'parent_email' => ['parent_email', 'parent_email_address', 'guardian_email']
        ];
        
        foreach ($headers as $header) {
            $normalizedHeader = null;
            $header = strtolower(trim(str_replace(' ', '_', $header)));
            
            foreach ($headerMap as $standard => $variants) {
                if (in_array($header, $variants)) {
                    $normalizedHeader = $standard;
                    break;
                }
            }
            
            if ($normalizedHeader) {
                $normalized[] = $normalizedHeader;
            }
        }
        
        return $normalized;
    }
    
    /**
     * Process student records
     */
    private function processStudents($students, $options)
    {
        $results = [
            'total' => count($students),
            'successful' => 0,
            'failed' => 0,
            'duplicates' => 0,
            'errors' => [],
            'imported_students' => []
        ];
        
        foreach ($students as $index => $studentData) {
            try {
                // Validate student data
                $validation = $this->validateStudentData($studentData);
                if (!$validation['valid']) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'row' => $studentData['csv_row'] ?? ($index + 2),
                        'email' => $studentData['student_email'] ?? 'unknown',
                        'error' => implode(', ', $validation['errors'])
                    ];
                    continue;
                }
                
                // Check for duplicates
                if ($this->checkDuplicateStudent($studentData['student_email'])) {
                    $results['duplicates']++;
                    $results['errors'][] = [
                        'row' => $studentData['csv_row'] ?? ($index + 2),
                        'email' => $studentData['student_email'],
                        'error' => 'Student with this email already exists'
                    ];
                    continue;
                }
                
                // Process student
                $studentResult = $this->processStudent($studentData, $options);
                
                if ($studentResult['success']) {
                    $results['successful']++;
                    $results['imported_students'][] = $studentResult['student'];
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'row' => $studentData['csv_row'] ?? ($index + 2),
                        'email' => $studentData['student_email'],
                        'error' => $studentResult['error']
                    ];
                }
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'row' => $studentData['csv_row'] ?? ($index + 2),
                    'email' => $studentData['student_email'] ?? 'unknown',
                    'error' => 'Processing error: ' . $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Validate student data
     */
    private function validateStudentData($data)
    {
        $errors = [];
        
        // Required fields
        if (empty($data['student_name'])) {
            $errors[] = 'Student name is required';
        }
        
        if (empty($data['student_email'])) {
            $errors[] = 'Student email is required';
        } elseif (!filter_var($data['student_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid student email format';
        }
        
        if (empty($data['class'])) {
            $errors[] = 'Class is required';
        }
        
        // Optional parent email validation
        if (!empty($data['parent_email']) && !filter_var($data['parent_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid parent email format';
        }
        
        // Validate class existence
        if (!empty($data['class'])) {
            $classExists = db()->fetchOne("
                SELECT id FROM classes 
                WHERE class_name = ? AND tenant_id = ? AND is_active = 1
            ", [$data['class'], $this->tenantId]);
            
            if (empty($classExists)) {
                $errors[] = 'Class does not exist: ' . $data['class'];
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Check for duplicate student
     */
    private function checkDuplicateStudent($email)
    {
        try {
            $existing = db()->fetchOne("
                SELECT u.id FROM users u
                JOIN students s ON u.id = s.user_id
                WHERE u.email = ? AND u.tenant_id = ? AND u.role = 'student'
            ", [$email, $this->tenantId]);
            
            return !empty($existing);
        } catch (Exception $e) {
            error_log("BulkStudentImport::checkDuplicateStudent error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process individual student
     */
    private function processStudent($studentData, $options)
    {
        try {
            // Parse student name
            $nameParts = $this->parseName($studentData['student_name']);
            
            // Prepare form data for automation
            $formData = [
                'first_name' => $nameParts['first_name'],
                'last_name' => $nameParts['last_name'],
                'email' => $studentData['student_email'],
                'phone' => $studentData['phone'] ?? '',
                'role' => 'student',
                'grade_level' => $this->extractGradeLevel($studentData['class']),
                'parent_email' => $studentData['parent_email'] ?? '',
                'class_name' => $studentData['class'],
                'submission_date' => date('Y-m-d H:i:s')
            ];
            
            // Create student account
            $userId = $this->createStudentAccount($formData);
            
            if ($userId) {
                // Enroll in class
                $this->enrollInClass($userId, $studentData['class']);
                
                // Send activation email (if enabled)
                if (!($options['skip_email'] ?? false)) {
                    $activationToken = $this->generateActivationToken($userId);
                    $this->sendActivationEmail($formData, $activationToken);
                }
                
                return [
                    'success' => true,
                    'student' => [
                        'user_id' => $userId,
                        'name' => $formData['first_name'] . ' ' . $formData['last_name'],
                        'email' => $formData['email'],
                        'class' => $studentData['class']
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to create student account'
                ];
            }
            
        } catch (Exception $e) {
            error_log("BulkStudentImport::processStudent error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Parse full name into first and last name
     */
    private function parseName($fullName)
    {
        $nameParts = explode(' ', trim($fullName), 2);
        
        return [
            'first_name' => $nameParts[0] ?? '',
            'last_name' => $nameParts[1] ?? ''
        ];
    }
    
    /**
     * Extract grade level from class name
     */
    private function extractGradeLevel($className)
    {
        // Try to extract grade from class name (e.g., "Grade 10 - Section A" -> "10")
        if (preg_match('/grade\s*(\d+)/i', $className, $matches)) {
            return $matches[1];
        }
        
        // Try other patterns
        if (preg_match('/(\d+)[a-zA-Z]/', $className, $matches)) {
            return $matches[1];
        }
        
        return '1'; // Default to grade 1
    }
    
    /**
     * Create student account
     */
    private function createStudentAccount($formData)
    {
        try {
            // Generate username
            $username = $this->automation->generateUsername($formData['first_name'], $formData['last_name']);
            
            // Generate temporary password
            $tempPassword = $this->automation->generateTempPassword();
            
            // Insert user record
            $userId = db()->insert('users', [
                'tenant_id' => $this->tenantId,
                'username' => $username,
                'password' => password_hash($tempPassword, PASSWORD_DEFAULT),
                'email' => $formData['email'],
                'first_name' => $formData['first_name'],
                'last_name' => $formData['last_name'],
                'role' => 'student',
                'status' => 'pending',
                'email_verified' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($userId) {
                // Create student record
                db()->insert('students', [
                    'user_id' => $userId,
                    'tenant_id' => $this->tenantId,
                    'admission_number' => $this->automation->generateAdmissionNumber(),
                    'grade_level' => $formData['grade_level'],
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Link parent if provided
                if (!empty($formData['parent_email'])) {
                    $this->automation->linkParentToStudent($userId, $formData['parent_email']);
                }
            }
            
            return $userId;
            
        } catch (Exception $e) {
            error_log("BulkStudentImport::createStudentAccount error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enroll student in class
     */
    private function enrollInClass($userId, $className)
    {
        try {
            $class = db()->fetchOne("
                SELECT id FROM classes 
                WHERE class_name = ? AND tenant_id = ? AND is_active = 1
            ", [$className, $this->tenantId]);
            
            if ($class) {
                // Check if already enrolled
                $existing = db()->fetchOne("
                    SELECT id FROM class_enrollments 
                    WHERE student_id = ? AND class_id = ?
                ", [$userId, $class['id']]);
                
                if (empty($existing)) {
                    db()->insert('class_enrollments', [
                        'student_id' => $userId,
                        'class_id' => $class['id'],
                        'enrollment_date' => date('Y-m-d'),
                        'status' => 'active',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("BulkStudentImport::enrollInClass error: " . $e->getMessage());
        }
    }
    
    /**
     * Generate activation token
     */
    private function generateActivationToken($userId)
    {
        $token = bin2hex(random_bytes(32));
        
        db()->insert('account_activations', [
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return $token;
    }
    
    /**
     * Send activation email
     */
    private function sendActivationEmail($formData, $activationToken)
    {
        try {
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
            $activationUrl = $baseUrl . "/attendance/activate-account.php?token=" . $activationToken;
            
            $subject = 'Activate Your ' . APP_NAME . ' Student Account';
            $message = $this->getStudentActivationEmailTemplate($formData, $activationUrl);
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: " . APP_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">" . "\r\n";
            
            return mail($formData['email'], $subject, $message, $headers);
            
        } catch (Exception $e) {
            error_log("BulkStudentImport::sendActivationEmail error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get student activation email template
     */
    private function getStudentActivationEmailTemplate($formData, $activationUrl)
    {
        return "
        <html>
        <head>
            <title>Student Account Activation</title>
        </head>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%); padding: 30px; text-align: center; color: white;'>
                <h1>Welcome to " . APP_NAME . "!</h1>
                <p>Your student account has been created</p>
            </div>
            
            <div style='padding: 30px; background: #f9f9f9;'>
                <h2>Dear Student/Parent,</h2>
                <p>A student account has been created for <strong>{$formData['first_name']} {$formData['last_name']}</strong> in the School Attendance Management System.</p>
                
                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3>Student Details:</h3>
                    <p><strong>Name:</strong> {$formData['first_name']} {$formData['last_name']}</p>
                    <p><strong>Email:</strong> {$formData['email']}</p>
                    <p><strong>Class:</strong> {$formData['class_name']}</p>
                    <p><strong>Grade Level:</strong> {$formData['grade_level']}</p>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$activationUrl}' style='background: #4F46E5; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Activate Student Account</a>
                </div>
                
                <p style='color: #666; font-size: 14px;'>This activation link will expire in 24 hours.</p>
                <p style='color: #666; font-size: 14px;'>If the button above doesn't work, copy and paste this link into your browser:<br>
                <small>{$activationUrl}</small></p>
            </div>
            
            <div style='background: #333; color: white; padding: 20px; text-align: center; font-size: 12px;'>
                <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Generate import report
     */
    private function generateImportReport($results)
    {
        $report = [
            'summary' => [
                'total_processed' => $results['total'],
                'successful_imports' => $results['successful'],
                'failed_imports' => $results['failed'],
                'duplicate_entries' => $results['duplicates'],
                'success_rate' => $results['total'] > 0 ? round(($results['successful'] / $results['total']) * 100, 2) : 0
            ],
            'details' => [
                'imported_students' => $results['imported_students'],
                'errors' => $results['errors']
            ],
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        // Save report to database
        db()->insert('bulk_import_reports', [
            'tenant_id' => $this->tenantId,
            'import_type' => 'student_csv',
            'total_records' => $results['total'],
            'successful_imports' => $results['successful'],
            'failed_imports' => $results['failed'],
            'duplicate_entries' => $results['duplicates'],
            'report_data' => json_encode($report),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        return $report;
    }
    
    /**
     * Get CSV template
     */
    public function getCSVTemplate()
    {
        $template = [
            ['student_name', 'student_email', 'class', 'parent_email'],
            ['John Doe', 'john.doe@school.com', 'Grade 10 - Section A', 'parent.doe@email.com'],
            ['Jane Smith', 'jane.smith@school.com', 'Grade 10 - Section B', 'parent.smith@email.com']
        ];
        
        return $template;
    }
    
    /**
     * Get import history
     */
    public function getImportHistory($limit = 10)
    {
        try {
            return db()->fetchAll("
                SELECT * FROM bulk_import_reports 
                WHERE tenant_id = ? AND import_type = 'student_csv'
                ORDER BY created_at DESC 
                LIMIT ?
            ", [$this->tenantId, $limit]);
        } catch (Exception $e) {
            error_log("BulkStudentImport::getImportHistory error: " . $e->getMessage());
            return [];
        }
    }
}
