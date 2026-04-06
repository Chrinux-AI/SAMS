<?php
/**
 * Workflow Service
 * Handles core business workflows: teacher creation, student import, class management
 */

class SAMS_WorkflowService extends SAMS_BaseService {
    
    private $userService;
    private $otpService;
    private $emailService;
    
    public function __construct($container) {
        parent::__construct($container);
        $this->userService = $container->get('user');
        $this->otpService = $container->get('otp');
        $this->emailService = $container->get('email');
    }
    
    /**
     * Workflow 1: Admin creates teacher
     */
    public function createTeacher($data, $sendInvitation = true) {
        return $this->transactional(function() use ($data, $sendInvitation) {
            // Prepare teacher data
            $teacherData = [
                'email' => $data['email'],
                'role' => 'teacher',
                'status' => 'inactive', // Pending OTP setup
                'full_name' => $data['full_name'],
                'employee_id' => $data['employee_id'] ?? null,
                'department' => $data['department'] ?? null,
                'qualifications' => $data['qualifications'] ?? null,
                'tenant_id' => $data['tenant_id'] ?? 1
            ];
            
            // Create user
            $result = $this->userService->createUser($teacherData);
            
            if (!$result['success']) {
                return $result;
            }
            
            $userId = $result['user_id'];
            
            // Generate OTP and send invitation
            if ($sendInvitation) {
                $otpResult = $this->otpService->generateOTP($data['email'], 'teacher_setup');
                
                if ($otpResult['success']) {
                    $this->emailService->sendTeacherInvitation($data['email'], [
                        'name' => $data['full_name'],
                        'otp' => $otpResult['otp'],
                        'setup_url' => $this->getSetupUrl($otpResult['token'])
                    ]);
                }
            }
            
            // Log workflow
            $this->log('TEACHER_CREATED', [
                'user_id' => $userId,
                'email' => $data['email'],
                'invitation_sent' => $sendInvitation
            ]);
            
            return [
                'success' => true,
                'user_id' => $userId,
                'message' => 'Teacher created successfully',
                'setup_pending' => $sendInvitation
            ];
        });
    }
    
    /**
     * Workflow 2: Bulk import students from CSV
     */
    public function bulkImportStudents($csvData, $defaultGrade = null) {
        $results = [
            'created' => 0,
            'failed' => 0,
            'errors' => [],
            'users' => []
        ];
        
        foreach ($csvData as $index => $row) {
            // Validate required fields
            if (empty($row['email']) || empty($row['full_name'])) {
                $results['failed']++;
                $results['errors'][] = "Row $index: Missing required fields";
                continue;
            }
            
            // Prepare student data
            $studentData = [
                'email' => $row['email'],
                'role' => 'student',
                'status' => 'inactive',
                'full_name' => $row['full_name'],
                'admission_no' => $row['admission_no'] ?? null,
                'grade_level' => $row['grade_level'] ?? $defaultGrade,
                'parent_id' => $row['parent_id'] ?? null,
                'tenant_id' => $row['tenant_id'] ?? 1
            ];
            
            // Create student
            $result = $this->userService->createUser($studentData);
            
            if ($result['success']) {
                $results['created']++;
                $results['users'][] = [
                    'user_id' => $result['user_id'],
                    'email' => $row['email']
                ];
                
                // Send invitation
                $otpResult = $this->otpService->generateOTP($row['email'], 'student_setup');
                if ($otpResult['success']) {
                    $this->emailService->sendStudentInvitation($row['email'], [
                        'name' => $row['full_name'],
                        'otp' => $otpResult['otp'],
                        'setup_url' => $this->getSetupUrl($otpResult['token'])
                    ]);
                }
            } else {
                $results['failed']++;
                $results['errors'][] = "Row $index ({$row['email']}): " . ($result['error'] ?? 'Unknown error');
            }
        }
        
        // Log bulk import
        $this->log('STUDENTS_IMPORTED', [
            'created' => $results['created'],
            'failed' => $results['failed']
        ]);
        
        return $results;
    }
    
    /**
     * Workflow 3: Create and manage class
     */
    public function createClass($data) {
        return $this->transactional(function() use ($data) {
            // Validate
            $required = ['name', 'grade_level', 'academic_year'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'error' => "$field is required"];
                }
            }
            
            // Check if class exists
            if ($this->classExists($data['name'], $data['grade_level'], $data['academic_year'])) {
                return ['success' => false, 'error' => 'Class already exists'];
            }
            
            // Create class
            $classId = $this->insertClass($data);
            
            if (!$classId) {
                return ['success' => false, 'error' => 'Failed to create class'];
            }
            
            // Assign teacher if specified
            if (!empty($data['teacher_id'])) {
                $this->assignTeacherToClass($classId, $data['teacher_id']);
            }
            
            // Enroll students if specified
            if (!empty($data['student_ids']) && is_array($data['student_ids'])) {
                $this->enrollStudents($classId, $data['student_ids']);
            }
            
            // Log
            $this->log('CLASS_CREATED', [
                'class_id' => $classId,
                'name' => $data['name']
            ]);
            
            return [
                'success' => true,
                'class_id' => $classId,
                'message' => 'Class created successfully'
            ];
        });
    }
    
    /**
     * Workflow 4: AI/Form onboarding to account creation
     */
    public function processAIUserCreation($formData, $role = 'student') {
        $results = [
            'processed' => 0,
            'created' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        // Parse form data (supports JSON, CSV, key-value)
        $users = $this->parseFormData($formData);
        
        foreach ($users as $index => $userData) {
            $results['processed']++;
            
            // Validate email
            if (empty($userData['email']) || !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                $results['failed']++;
                $results['errors'][] = "Entry $index: Invalid email";
                continue;
            }
            
            // Set role
            $userData['role'] = $userData['role'] ?? $role;
            $userData['status'] = 'inactive'; // Requires OTP setup
            
            // Create user
            $result = $this->userService->createUser($userData);
            
            if ($result['success']) {
                $results['created']++;
                
                // Generate OTP and send invitation
                $otpResult = $this->otpService->generateOTP($userData['email'], 'ai_onboarding');
                if ($otpResult['success']) {
                    $this->emailService->sendAICreatedAccountInvitation($userData['email'], [
                        'name' => $userData['full_name'] ?? 'User',
                        'role' => $userData['role'],
                        'otp' => $otpResult['otp'],
                        'setup_url' => $this->getSetupUrl($otpResult['token'])
                    ]);
                }
            } else {
                $results['failed']++;
                $results['errors'][] = "Entry $index ({$userData['email']}): " . ($result['error'] ?? 'Unknown error');
            }
        }
        
        // Log AI creation batch
        $this->log('AI_USERS_CREATED', [
            'processed' => $results['processed'],
            'created' => $results['created'],
            'failed' => $results['failed']
        ]);
        
        return $results;
    }
    
    /**
     * Workflow 5: Complete account setup with OTP
     */
    public function completeAccountSetup($email, $otp, $password) {
        // Verify OTP
        $otpResult = $this->otpService->verifyOTP($email, $otp);
        
        if (!$otpResult['success']) {
            return $otpResult;
        }
        
        // Get user
        $user = $this->getUserByEmail($email);
        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }
        
        // Update password and activate
        $updateResult = $this->userService->updateUser($user['id'], [
            'password' => $password,
            'status' => 'active'
        ]);
        
        if (!$updateResult['success']) {
            return $updateResult;
        }
        
        // Log
        $this->log('ACCOUNT_SETUP_COMPLETE', [
            'user_id' => $user['id'],
            'email' => $email
        ]);
        
        return [
            'success' => true,
            'message' => 'Account activated successfully',
            'user_id' => $user['id'],
            'role' => $user['role']
        ];
    }
    
    /**
     * Parse form data from various formats
     */
    private function parseFormData($formData) {
        $users = [];
        
        // Try JSON first
        $json = json_decode($formData, true);
        if ($json && is_array($json)) {
            return $this->normalizeUserData($json);
        }
        
        // Try CSV
        if (strpos($formData, ',') !== false) {
            $lines = explode("\n", $formData);
            $headers = str_getcsv(array_shift($lines));
            
            foreach ($lines as $line) {
                if (trim($line)) {
                    $data = str_getcsv($line);
                    $users[] = array_combine($headers, $data);
                }
            }
            
            return $this->normalizeUserData($users);
        }
        
        // Try key-value format
        $entries = explode("\n\n", $formData);
        foreach ($entries as $entry) {
            $lines = explode("\n", trim($entry));
            $user = [];
            
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $user[trim($key)] = trim($value);
                }
            }
            
            if (!empty($user)) {
                $users[] = $user;
            }
        }
        
        return $this->normalizeUserData($users);
    }
    
    /**
     * Normalize user data field names
     */
    private function normalizeUserData($users) {
        $fieldMappings = [
            'email' => ['email', 'e-mail', 'mail', 'email_address'],
            'full_name' => ['name', 'full_name', 'fullname', 'student_name', 'teacher_name'],
            'admission_no' => ['admission_no', 'admission', 'student_id', 'id'],
            'employee_id' => ['employee_id', 'employeeid', 'staff_id'],
            'grade_level' => ['grade', 'grade_level', 'class', 'year'],
            'department' => ['department', 'dept', 'subject']
        ];
        
        $normalized = [];
        
        foreach ($users as $user) {
            $normUser = [];
            
            foreach ($fieldMappings as $standard => $aliases) {
                foreach ($aliases as $alias) {
                    if (isset($user[$alias])) {
                        $normUser[$standard] = $user[$alias];
                        break;
                    }
                }
            }
            
            // Keep any additional fields
            foreach ($user as $key => $value) {
                if (!isset($normUser[$key])) {
                    $normUser[$key] = $value;
                }
            }
            
            $normalized[] = $normUser;
        }
        
        return $normalized;
    }
    
    /**
     * Helper: Check if class exists
     */
    private function classExists($name, $grade, $year) {
        $name = mysqli_real_escape_string($this->db, $name);
        $grade = mysqli_real_escape_string($this->db, $grade);
        $year = mysqli_real_escape_string($this->db, $year);
        
        $result = $this->db->query("SELECT id FROM classes WHERE name = '$name' AND grade_level = '$grade' AND academic_year = '$year' LIMIT 1");
        
        return $result && mysqli_num_rows($result) > 0;
    }
    
    /**
     * Helper: Insert class
     */
    private function insertClass($data) {
        $fields = [
            'name' => mysqli_real_escape_string($this->db, $data['name']),
            'grade_level' => mysqli_real_escape_string($this->db, $data['grade_level']),
            'academic_year' => mysqli_real_escape_string($this->db, $data['academic_year']),
            'description' => mysqli_real_escape_string($this->db, $data['description'] ?? ''),
            'tenant_id' => (int)($data['tenant_id'] ?? 1),
            'created_by' => (int)($_SESSION['user_id'] ?? 0),
            'created_at' => 'NOW()'
        ];
        
        if (!empty($data['teacher_id'])) {
            $fields['teacher_id'] = (int)$data['teacher_id'];
        }
        
        $columns = implode(', ', array_keys($fields));
        $values = implode(', ', array_map(function($v) {
            return is_int($v) ? $v : "'$v'";
        }, $fields));
        
        $sql = "INSERT INTO classes ($columns) VALUES ($values)";
        
        if ($this->db->query($sql)) {
            return $this->db->insert_id;
        }
        
        return false;
    }
    
    /**
     * Helper: Assign teacher to class
     */
    private function assignTeacherToClass($classId, $teacherId) {
        $classId = (int)$classId;
        $teacherId = (int)$teacherId;
        
        $this->db->query("UPDATE classes SET teacher_id = $teacherId WHERE id = $classId");
    }
    
    /**
     * Helper: Enroll students in class
     */
    private function enrollStudents($classId, $studentIds) {
        $classId = (int)$classId;
        
        foreach ($studentIds as $studentId) {
            $studentId = (int)$studentId;
            
            $this->db->query("INSERT INTO class_enrollments (class_id, student_id, enrolled_at) 
                             VALUES ($classId, $studentId, NOW()) 
                             ON DUPLICATE KEY UPDATE enrolled_at = NOW()");
        }
    }
    
    /**
     * Helper: Get user by email
     */
    private function getUserByEmail($email) {
        $email = strtolower(trim($email));
        $escapedEmail = mysqli_real_escape_string($this->db, $email);
        
        $result = $this->db->query("SELECT * FROM users WHERE email = '$escapedEmail' LIMIT 1");
        
        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }
        
        return null;
    }
    
    /**
     * Helper: Get account setup URL
     */
    private function getSetupUrl($token) {
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        return $baseUrl . "/confirm-account.php?token=" . urlencode($token);
    }
}
