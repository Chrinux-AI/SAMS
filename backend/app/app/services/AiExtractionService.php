<?php
/**
 * SAMS AI Extraction Service
 * Automated data extraction from Google Form submissions
 * Handles teacher and student account creation with AI validation
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

class AiExtractionService
{
    private $db;
    private $logger;
    private $queue;

    public function __construct()
    {
        $this->db = db();
        $this->logger = new Logger('ai_extraction');
        $this->queue = new QueueService();
    }

    /**
     * Process Google Form submission data
     */
    public function processGoogleFormSubmission($formData)
    {
        try {
            $this->logger->info('Processing Google Form submission', ['data_size' => strlen($formData)]);

            // Parse JSON data
            $parsedData = $this->parseFormData($formData);

            // Validate and extract entities
            $entities = $this->extractEntities($parsedData);

            // Queue for background processing
            $jobId = $this->queue->enqueue('ai_extraction', [
                'entities' => $entities,
                'timestamp' => time(),
                'processed' => false
            ]);

            $this->logger->info('Google Form submission queued', [
                'job_id' => $jobId,
                'entities_count' => count($entities)
            ]);

            return [
                'success' => true,
                'job_id' => $jobId,
                'message' => 'Form submission queued for processing',
                'entities_found' => count($entities)
            ];

        } catch (Exception $e) {
            $this->logger->error('Error processing Google Form submission', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error processing submission: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Parse Google Form JSON data
     */
    private function parseFormData($formData)
    {
        // Handle different JSON formats
        $data = json_decode($formData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON data: ' . json_last_error_msg());
        }

        // Handle Google Forms nested structure
        return $this->flattenGoogleFormData($data);
    }

    /**
     * Flatten nested Google Form data
     */
    private function flattenGoogleFormData($data, $prefix = '')
    {
        $flattened = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Handle nested arrays
                $flattened = array_merge($flattened, $this->flattenGoogleFormData($value, $prefix . $key . '.'));
            } else {
                // Handle Google Forms field naming conventions
                $cleanKey = $this->cleanGoogleFormKey($key);
                $flattened[$prefix . $cleanKey] = $value;
            }
        }

        return $flattened;
    }

    /**
     * Clean Google Form field names
     */
    private function cleanGoogleFormKey($key)
    {
        // Remove common Google Forms prefixes
        $key = preg_replace('/^entry\./', '', $key);
        $key = preg_replace('/^item\./', '', $key);

        // Convert to lowercase and replace spaces with underscores
        $key = strtolower($key);
        $key = str_replace(' ', '_', $key);

        // Remove special characters except underscores and hyphens
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);

        return $key;
    }

    /**
     * Extract entities from form data
     */
    private function extractEntities($data)
    {
        $entities = [];

        // Try to identify entity type based on form structure
        $entityType = $this->detectEntityType($data);

        switch ($entityType) {
            case 'teacher':
                $entities = $this->extractTeacherEntities($data);
                break;
            case 'student':
                $entities = $this->extractStudentEntities($data);
                break;
            case 'mixed':
                $entities = $this->extractMixedEntities($data);
                break;
            default:
                $entities = $this->extractGenericEntities($data);
                break;
        }

        return $entities;
    }

    /**
     * Detect entity type from form data
     */
    private function detectEntityType($data)
    {
        // Check for teacher-specific fields
        $teacherIndicators = ['teacher', 'faculty', 'staff', 'department', 'qualification', 'experience'];
        $studentIndicators = ['student', 'pupil', 'grade', 'class', 'parent', 'guardian'];

        $dataString = json_encode($data);

        $teacherScore = 0;
        $studentScore = 0;

        foreach ($teacherIndicators as $indicator) {
            if (stripos($dataString, $indicator) !== false) {
                $teacherScore++;
            }
        }

        foreach ($studentIndicators as $indicator) {
            if (stripos($dataString, $indicator) !== false) {
                $studentScore++;
            }
        }

        if ($teacherScore > $studentScore && $teacherScore > 0) {
            return 'teacher';
        } elseif ($studentScore > $teacherScore && $studentScore > 0) {
            return 'student';
        } elseif ($teacherScore > 0 && $studentScore > 0) {
            return 'mixed';
        }

        return 'generic';
    }

    /**
     * Extract teacher entities
     */
    private function extractTeacherEntities($data)
    {
        $entities = [];

        // Try to find multiple teacher records
        $teacherRecords = $this->findMultipleRecords($data, 'teacher');

        foreach ($teacherRecords as $record) {
            $entity = [
                'type' => 'teacher',
                'data' => $this->validateTeacherData($record),
                'validation_errors' => []
            ];

            if (!empty($entity['validation_errors'])) {
                $entity['status'] = 'validation_failed';
            } else {
                $entity['status'] = 'pending_creation';
            }

            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * Extract student entities
     */
    private function extractStudentEntities($data)
    {
        $entities = [];

        // Try to find multiple student records
        $studentRecords = $this->findMultipleRecords($data, 'student');

        foreach ($studentRecords as $record) {
            $entity = [
                'type' => 'student',
                'data' => $this->validateStudentData($record),
                'validation_errors' => []
            ];

            if (!empty($entity['validation_errors'])) {
                $entity['status'] = 'validation_failed';
            } else {
                $entity['status'] = 'pending_creation';
            }

            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * Extract mixed entities (both teachers and students)
     */
    private function extractMixedEntities($data)
    {
        $entities = [];

        // First extract teachers
        $teacherEntities = $this->extractTeacherEntities($data);
        $entities = array_merge($entities, $teacherEntities);

        // Then extract students
        $studentEntities = $this->extractStudentEntities($data);
        $entities = array_merge($entities, $studentEntities);

        return $entities;
    }

    /**
     * Extract generic entities
     */
    private function extractGenericEntities($data)
    {
        $entities = [];

        // Try to identify entities by common field patterns
        $records = $this->findMultipleRecords($data, 'generic');

        foreach ($records as $record) {
            $entity = [
                'type' => $this->determineGenericEntityType($record),
                'data' => $record,
                'validation_errors' => []
            ];

            // Basic validation
            if (empty($record['email'])) {
                $entity['validation_errors'][] = 'Email is required';
            }

            if (!empty($entity['validation_errors'])) {
                $entity['status'] = 'validation_failed';
            } else {
                $entity['status'] = 'pending_creation';
            }

            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * Find multiple records in form data
     */
    private function findMultipleRecords($data, $type)
    {
        $records = [];

        // Look for patterns like 'name1', 'name2', 'email1', 'email2'
        $fieldPatterns = [
            'teacher' => ['name', 'email', 'phone', 'department', 'qualification'],
            'student' => ['name', 'email', 'grade', 'class', 'parent_name', 'parent_email']
        ];

        $patterns = $fieldPatterns[$type] ?? ['name', 'email'];

        // Find the maximum index for each pattern
        $maxIndexes = [];
        foreach ($patterns as $pattern) {
            $maxIndex = $this->findMaxFieldIndex($data, $pattern);
            if ($maxIndex > 0) {
                $maxIndexes[$pattern] = $maxIndex;
            }
        }

        if (empty($maxIndexes)) {
            // Try single record
            $records[] = $data;
        } else {
            // Build multiple records
            $recordCount = max($maxIndexes);

            for ($i = 1; $i <= $recordCount; $i++) {
                $record = [];

                foreach ($patterns as $pattern) {
                    $fieldKey = $pattern . $i;
                    if (isset($data[$fieldKey])) {
                        $record[$pattern] = $data[$fieldKey];
                    }
                }

                // Add any non-indexed fields
                foreach ($data as $key => $value) {
                    if (!preg_match('/\d+$/', $key) && !in_array($key, $patterns)) {
                        $record[$key] = $value;
                    }
                }

                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * Find maximum field index for a pattern
     */
    private function findMaxFieldIndex($data, $pattern)
    {
        $maxIndex = 0;

        foreach ($data as $key => $value) {
            if (preg_match('/^' . preg_quote($pattern) . '(\d+)$/', $key, $matches)) {
                $maxIndex = max($maxIndex, (int)$matches[1]);
            }
        }

        return $maxIndex;
    }

    /**
     * Determine generic entity type
     */
    private function determineGenericEntityType($record)
    {
        // Try to infer from available fields
        if (isset($record['department']) || isset($record['qualification'])) {
            return 'teacher';
        } elseif (isset($record['grade']) || isset($record['class'])) {
            return 'student';
        } elseif (isset($record['role'])) {
            return strtolower($record['role']);
        }

        return 'generic';
    }

    /**
     * Validate teacher data
     */
    private function validateTeacherData($data)
    {
        $validated = [];
        $errors = [];

        // Required fields
        $requiredFields = ['name', 'email'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst($field) . ' is required';
            }
        }

        // Validate email
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        // Validate phone (if provided)
        if (!empty($data['phone']) && !$this->validatePhone($data['phone'])) {
            $errors[] = 'Invalid phone format';
        }

        // Clean and validate data
        $validated['first_name'] = $this->extractFirstName($data['name'] ?? '');
        $validated['last_name'] = $this->extractLastName($data['name'] ?? '');
        $validated['email'] = strtolower(trim($data['email'] ?? ''));
        $validated['phone'] = $this->formatPhone($data['phone'] ?? '');
        $validated['department'] = trim($data['department'] ?? '');
        $validated['qualification'] = trim($data['qualification'] ?? '');
        $validated['experience'] = (int)($data['experience'] ?? 0);

        return $validated;
    }

    /**
     * Validate student data
     */
    private function validateStudentData($data)
    {
        $validated = [];
        $errors = [];

        // Required fields
        $requiredFields = ['name', 'email', 'grade'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst($field) . ' is required';
            }
        }

        // Validate email
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        // Validate grade
        if (!empty($data['grade'])) {
            if (!preg_match('/^(K|1|2|3|4|5|6|7|8|9|10|11|12|13)$/', $data['grade'])) {
                $errors[] = 'Invalid grade format';
            }
        }

        // Clean and validate data
        $validated['first_name'] = $this->extractFirstName($data['name'] ?? '');
        $validated['last_name'] = $this->extractLastName($data['name'] ?? '');
        $validated['email'] = strtolower(trim($data['email'] ?? ''));
        $validated['grade'] = strtoupper(trim($data['grade'] ?? ''));
        $validated['class'] = trim($data['class'] ?? '');
        $validated['parent_name'] = trim($data['parent_name'] ?? '');
        $validated['parent_email'] = strtolower(trim($data['parent_email'] ?? ''));

        // Validate parent email if provided
        if (!empty($validated['parent_email']) && !filter_var($validated['parent_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid parent email format';
        }

        return $validated;
    }

    /**
     * Extract first name from full name
     */
    private function extractFirstName($fullName)
    {
        $nameParts = explode(' ', trim($fullName));
        return $nameParts[0] ?? '';
    }

    /**
     * Extract last name from full name
     */
    private function extractLastName($fullName)
    {
        $nameParts = explode(' ', trim($fullName));
        return count($nameParts) > 1 ? end($nameParts) : '';
    }

    /**
     * Validate phone number
     */
    private function validatePhone($phone)
    {
        // Remove all non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Check if it's a valid phone number (10 or 11 digits)
        return strlen($phone) >= 10 && strlen($phone) <= 11;
    }

    /**
     * Format phone number
     */
    private function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 10) {
            return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6, 4);
        } elseif (strlen($phone) === 11) {
            return '+' . substr($phone, 0, 1) . ' (' . substr($phone, 1, 3) . ') ' . substr($phone, 4, 3) . '-' . substr($phone, 7, 4);
        }

        return $phone;
    }

    /**
     * Process queued extraction job
     */
    public function processExtractionJob($jobId)
    {
        try {
            $this->logger->info('Processing extraction job', ['job_id' => $jobId]);

            // Get job from queue
            $job = $this->queue->getJob('ai_extraction', $jobId);

            if (!$job) {
                throw new Exception('Job not found: ' . $jobId);
            }

            $entities = $job['entities'];
            $results = [];

            foreach ($entities as $entity) {
                $result = $this->processEntity($entity);
                $results[] = $result;

                // Update job progress
                $this->queue->updateProgress($jobId, count($results), count($entities));
            }

            // Mark job as completed
            $this->queue->markCompleted($jobId, [
                'results' => $results,
                'completed_at' => time()
            ]);

            $this->logger->info('Extraction job completed', [
                'job_id' => $jobId,
                'total_entities' => count($entities),
                'successful' => count(array_filter($results, fn($r) => $r['success']))
            ]);

            return $results;

        } catch (Exception $e) {
            $this->logger->error('Error processing extraction job', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark job as failed
            $this->queue->markFailed($jobId, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Process individual entity
     */
    public function processEntity($entity)
    {
        $result = [
            'entity_type' => $entity['type'],
            'success' => false,
            'message' => '',
            'user_id' => null,
            'otp_token' => null,
            'verification_sent' => false
        ];

        try {
            switch ($entity['type']) {
                case 'teacher':
                    $result = $this->processTeacherEntity($entity);
                    break;
                case 'student':
                    $result = $this->processStudentEntity($entity);
                    break;
                default:
                    $result = $this->processGenericEntity($entity);
                    break;
            }

            return $result;

        } catch (Exception $e) {
            $this->logger->error('Error processing entity', [
                'entity_type' => $entity['type'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $result['success'] = false;
            $result['message'] = 'Error processing entity: ' . $e->getMessage();

            return $result;
        }
    }

    /**
     * Process teacher entity
     */
    private function processTeacherEntity($entity)
    {
        $result = [
            'entity_type' => 'teacher',
            'success' => false,
            'message' => ''
        ];

        $data = $entity['data'];

        // Check if teacher already exists
        $existingTeacher = $this->db->fetchOne(
            "SELECT id FROM users WHERE email = ? AND role = 'teacher'",
            [$data['email']]
        );

        if ($existingTeacher) {
            $result['success'] = false;
            $result['message'] = 'Teacher with this email already exists';
            $result['user_id'] = $existingTeacher['id'];
            return $result;
        }

        // Create user account
        $userId = $this->createUserAccount($data['email'], 'teacher', $data['first_name'], $data['last_name']);

        if (!$userId) {
            $result['success'] = false;
            $result['message'] = 'Failed to create user account';
            return $result;
        }

        // Create teacher record
        $teacherId = $this->createTeacherRecord($userId, $data);

        if (!$teacherId) {
            $result['success'] = false;
            $result['message'] = 'Failed to create teacher record';
            return $result;
        }

        // Generate OTP token
        $otpToken = $this->generateOTP($userId);

        // Send verification email
        $verificationSent = $this->sendVerificationEmail($data['email'], $otpToken, 'teacher');

        $result['success'] = true;
        $result['message'] = 'Teacher account created successfully. Verification email sent.';
        $result['user_id'] = $userId;
        $result['teacher_id'] = $teacherId;
        $result['otp_token'] = $otpToken;
        $result['verification_sent'] = $verificationSent;

        return $result;
    }

    /**
     * Process student entity
     */
    private function processStudentEntity($entity)
    {
        $result = [
            'entity_type' => 'student',
            'success' => false,
            'message' => ''
        ];

        $data = $entity['data'];

        // Check if student already exists
        $existingStudent = $this->db->fetchOne(
            "SELECT u.id FROM users u JOIN students s ON u.id = s.user_id WHERE u.email = ? AND u.role = 'student'",
            [$data['email']]
        );

        if ($existingStudent) {
            $result['success'] = false;
            $result['message'] = 'Student with this email already exists';
            $result['user_id'] = $existingStudent['id'];
            return $result;
        }

        // Create user account
        $userId = $this->createUserAccount($data['email'], 'student', $data['first_name'], $data['last_name']);

        if (!$userId) {
            $result['success'] = false;
            $result['message'] = 'Failed to create user account';
            return $result;
        }

        // Create student record
        $studentId = $this->createStudentRecord($userId, $data);

        if (!$studentId) {
            $result['success'] = false;
            $result['message'] = 'Failed to create student record';
            return $result;
        }

        // Generate OTP token
        $otpToken = $this->generateOTP($userId);

        // Send verification email
        $verificationSent = $this->sendVerificationEmail($data['email'], $otpToken, 'student');

        $result['success'] = true;
        $result['message'] = 'Student account created successfully. Verification email sent.';
        $result['user_id'] = $userId;
        $result['student_id'] = $studentId;
        $result['otp_token'] = $otpToken;
        $result['verification_sent'] = $verificationSent;

        return $result;
    }

    /**
     * Process generic entity
     */
    private function processGenericEntity($entity)
    {
        $result = [
            'entity_type' => $entity['type'],
            'success' => false,
            'message' => ''
        ];

        $data = $entity['data'];

        // Check if user already exists
        $existingUser = $this->db->fetchOne(
            "SELECT id FROM users WHERE email = ?",
            [$data['email']]
        );

        if ($existingUser) {
            $result['success'] = false;
            $result['message'] = 'User with this email already exists';
            $result['user_id'] = $existingUser['id'];
            return $result;
        }

        // Determine role from data or default to 'user'
        $role = $data['role'] ?? 'user';

        // Create user account
        $userId = $this->createUserAccount($data['email'], $role, $data['first_name'] ?? '', $data['last_name'] ?? '');

        if (!$userId) {
            $result['success'] = false;
            $result['message'] = 'Failed to create user account';
            return $result;
        }

        // Generate OTP token
        $otpToken = $this->generateOTP($userId);

        // Send verification email
        $verificationSent = $this->sendVerificationEmail($data['email'], $otpToken, $role);

        $result['success'] = true;
        $result['message'] = 'Account created successfully. Verification email sent.';
        $result['user_id'] = $userId;
        $result['otp_token'] = $otpToken;
        $result['verification_sent'] = $verificationSent;

        return $result;
    }

    /**
     * Create user account
     */
    private function createUserAccount($email, $role, $firstName, $lastName)
    {
        $password = bin2hex(random_bytes(16));

        $userId = $this->db->insert('users', [
            'email' => strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => $role,
            'is_active' => 0, // Inactive until verified
            'email_verified' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return $userId;
    }

    /**
     * Create teacher record
     */
    private function createTeacherRecord($userId, $data)
    {
        return $this->db->insert('teachers', [
            'user_id' => $userId,
            'employee_number' => $this->generateEmployeeNumber(),
            'department' => $data['department'],
            'designation' => $data['designation'] ?? 'Teacher',
            'qualification' => $data['qualification'],
            'experience_years' => $data['experience'],
            'teacher_status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Create student record
     */
    private function createStudentRecord($userId, $data)
    {
        return $this->db->insert('students', [
            'user_id' => $userId,
            'admission_number' => $this->generateAdmissionNumber(),
            'student_status' => 'active',
            'grade_level' => $data['grade'],
            'section' => $data['class'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Generate employee number
     */
    private function generateEmployeeNumber()
    {
        $prefix = 'TCH';
        $number = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return $prefix . $number;
    }

    /**
     * Generate admission number
     */
    private function generateAdmissionNumber()
    {
        $prefix = 'ADM';
        $year = date('Y');
        $number = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return $prefix . $year . $number;
    }

    /**
     * Generate OTP token
     */
    private function generateOTP($userId)
    {
        $token = bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

        $this->db->insert('otp_tokens', [
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $token;
    }

    /**
     * Send verification email
     */
    private function sendVerificationEmail($email, $token, $entityType)
    {
        $verificationLink = BASE_URL . "/admin/ai/verify-otp?token=" . $token . "&email=" . urlencode($email);

        $subject = "SAMS - " . ucfirst($entityType) . " Account Verification";

        $message = "Hello,\n\n";
        $message .= "Your " . ucfirst($entityType) . " account has been created in SAMS.\n\n";
        $message .= "To complete your account setup and set your password, please click the verification link below:\n\n";
        $message .= $verificationLink . "\n\n";
        $message .= "This link will expire in 10 minutes.\n\n";
        $message .= "If you did not request this account, please contact the administrator.\n\n";
        $message .= "Thank you!\n";
        $message .= "SAMS Team";

        // In production, use actual email sending
        $headers = [
            'From: ' . SMTP_FROM_EMAIL,
            'Content-Type: text/plain'
        ];

        return mail($email, $subject, $message, $headers);
    }

    /**
     * Verify OTP token
     */
    public function verifyOTP($token, $email)
    {
        try {
            $otpRecord = $this->db->fetchOne(
                "SELECT * FROM otp_tokens WHERE token = ? AND email = ? AND expires_at > NOW() AND used = 0",
                [$token, $email]
            );

            if (!$otpRecord) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired verification token'
                ];
            }

            // Mark token as used
            $this->db->update('otp_tokens', [
                'used' => 1,
                'used_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$otpRecord['id']]);

            // Get user
            $user = $this->db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }

            // Activate user account
            $this->db->update('users', [
                'is_active' => 1,
                'email_verified' => 1,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$user['id']]);

            return [
                'success' => true,
                'message' => 'Account verified successfully. You can now set your password.',
                'user_id' => $user['id'],
                'entity_type' => $user['role']
            ];

        } catch (Exception $e) {
            $this->logger->error('Error verifying OTP', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error verifying token: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get job status
     */
    public function getJobStatus($jobId)
    {
        try {
            $job = $this->queue->getJob('ai_extraction', $jobId);

            if (!$job) {
                return [
                    'success' => false,
                    'message' => 'Job not found'
                ];
            }

            return [
                'success' => true,
                'job' => $job,
                'progress_percentage' => $this->calculateProgress($job)
            ];

        } catch (Exception $e) {
            $this->logger->error('Error getting job status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error getting job status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Calculate job progress
     */
    private function calculateProgress($job)
    {
        $total = count($job['entities'] ?? []);
        $processed = count($job['results'] ?? []);

        return $total > 0 ? round(($processed / $total) * 100, 1) : 0;
    }

    /**
     * Get queue statistics
     */
    public function getQueueStatistics()
    {
        try {
            $stats = [
                'pending' => $this->queue->countJobs('ai_extraction', 'pending'),
                'processing' => $this->queue->countJobs('ai_extraction', 'processing'),
                'completed' => $this->queue->countJobs('ai_extraction', 'completed'),
                'failed' => $this->queue->countJobs('ai_extraction', 'failed'),
                'total' => $this->queue->countJobs('ai_extraction')
            ];

            return [
                'success' => true,
                'statistics' => $stats
            ];

        } catch (Exception $e) {
            $this->logger->error('Error getting queue statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error getting queue statistics: ' . $e->getMessage()
            ];
        }
    }
}

/**
 * Simple Queue Service for background processing
 */
class QueueService
{
    private $db;

    public function __construct()
    {
        $this->db = db();
        $this->initQueueTable();
    }

    public function enqueue($queueName, $data)
    {
        $jobId = uniqid();

        $this->db->insert('jobs', [
            'id' => $jobId,
            'queue_name' => $queueName,
            'data' => json_encode($data),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return $jobId;
    }

    public function getJob($queueName, $jobId)
    {
        return $this->db->fetchOne(
            "SELECT * FROM jobs WHERE id = ? AND queue_name = ?",
            [$jobId, $queueName]
        );
    }

    public function updateProgress($jobId, $progress, $total)
    {
        $this->db->update('jobs', [
            'progress' => $progress,
            'total' => $total,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$jobId]);
    }

    public function markCompleted($jobId, $results = [])
    {
        $this->db->update('jobs', [
            'status' => 'completed',
            'results' => json_encode($results),
            'completed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$jobId]);
    }

    public function markFailed($jobId, $error)
    {
        $this->db->update('jobs', [
            'status' => 'failed',
            'error' => $error,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$jobId]);
    }

    public function countJobs($queueName, $status = null)
    {
        $where = "queue_name = ?";
        $params = [$queueName];

        if ($status) {
            $where .= " AND status = ?";
            $params[] = $status;
        }

        return $this->db->count('jobs', $where, $params);
    }

    private function initQueueTable()
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS jobs (
                id VARCHAR(36) PRIMARY KEY,
                queue_name VARCHAR(50) NOT NULL,
                data JSON NOT NULL,
                status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
                progress INT DEFAULT 0,
                total INT DEFAULT 0,
                error TEXT,
                results JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_queue_status (queue_name, status),
                INDEX idx_created_at (created_at),
                INDEX idx_updated_at (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $this->db->createTable($sql);
    }
}

/**
 * Simple Logger for debugging
 */
class Logger
{
    private $logFile;

    public function __construct($name = 'ai_extraction')
    {
        $this->logFile = __DIR__ . '/../../logs/' . $name . '.log';
        $this->ensureLogDirectory();
    }

    public function info($message, $context = [])
    {
        $this->log('INFO', $message, $context);
    }

    public function error($message, $context = [])
    {
        $this->log('ERROR', $message, $context);
    }

    public function debug($message, $context = [])
    {
        $this->log('DEBUG', $message, $context);
    }

    private function log($level, $message, $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = $context ? ' | ' . json_encode($context) : '';
        $logMessage = "[$timestamp] [$level] $message $contextStr\n";

        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    private function ensureLogDirectory()
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
}
