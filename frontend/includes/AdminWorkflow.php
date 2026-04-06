<?php
/**
 * Admin Zero-Stress Workflow System
 * Handles teacher creation, bulk student import, and class management
 */

class SAMS_AdminWorkflow {
    private $db;
    private $userService;
    private $emailService;
    private $errorLog = [];

    public function __construct() {
        $this->db = db();
        $this->userService = sams_service('user');
        $this->emailService = sams_service('email');
    }

    /**
     * Workflow 1: Add Teacher (Zero-Stress)
     * Admin dashboard → Add teacher form → Submit → Teacher account created → Email invite sent
     */
    public function addTeacher($data) {
        $this->clearErrors();

        try {
            // Step 1: Validate input
            $validation = $this->validateTeacherData($data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'validation_errors' => $validation['errors'],
                    'step' => 'validation'
                ];
            }

            // Step 2: Check for duplicates
            if ($this->emailExists($data['email'])) {
                return [
                    'success' => false,
                    'error' => 'A user with this email already exists',
                    'step' => 'duplicate_check'
                ];
            }

            // Step 3: Create teacher account
            $teacherData = [
                'email' => strtolower(trim($data['email'])),
                'role' => 'teacher',
                'status' => 'pending_activation',
                'full_name' => $this->normalizeName($data['full_name']),
                'employee_id' => $data['employee_id'] ?? null,
                'department' => $data['department'] ?? null,
                'qualifications' => $data['qualifications'] ?? null,
                'phone' => $data['phone'] ?? null,
                'tenant_id' => $_SESSION['tenant_id'] ?? 1,
                'created_by' => $_SESSION['user_id'] ?? null
            ];

            $result = $this->userService->createUser($teacherData);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to create teacher account',
                    'step' => 'account_creation'
                ];
            }

            $userId = $result['user_id'];

            // Step 4: Generate activation token
            $activationToken = $this->generateActivationToken($userId);

            // Step 5: Send invitation email
            $emailResult = $this->sendTeacherInvitation(
                $teacherData['email'],
                $teacherData['full_name'],
                $activationToken
            );

            if (!$emailResult) {
                $this->logError("Failed to send invitation email to: " . $teacherData['email']);
                // Don't fail - account was created, admin can resend
            }

            // Step 6: Log success
            $this->logWorkflow('TEACHER_ADDED', [
                'user_id' => $userId,
                'email' => $teacherData['email'],
                'invitation_sent' => $emailResult
            ]);

            return [
                'success' => true,
                'message' => 'Teacher added successfully. Invitation email sent.',
                'user_id' => $userId,
                'email_sent' => $emailResult,
                'step' => 'completed'
            ];

        } catch (Exception $e) {
            $this->logError("Exception in addTeacher: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'An unexpected error occurred. Please try again.',
                'technical_error' => $e->getMessage(),
                'step' => 'exception'
            ];
        }
    }

    /**
     * Workflow 1b: Add Student (Zero-Stress)
     * Admin dashboard → Add student form → Submit → Student account created → Email invite sent
     */
    public function addStudent($data) {
        $this->clearErrors();

        try {
            // Step 1: Validate input
            $validation = $this->validateStudentData($data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Validation failed: ' . implode(', ', $validation['errors']),
                    'step' => 'validation'
                ];
            }

            // Step 2: Check for duplicates
            if ($this->emailExists($data['email'])) {
                return [
                    'success' => false,
                    'error' => 'A user with this email already exists',
                    'step' => 'duplicate_check'
                ];
            }

            // Step 3: Create student account
            $studentData = [
                'email' => strtolower(trim($data['email'])),
                'role' => 'student',
                'status' => 'pending_activation',
                'full_name' => $this->normalizeName($data['full_name']),
                'admission_no' => $data['admission_no'] ?? null,
                'grade_level' => $data['grade_level'] ?? null,
                'parent_email' => $data['parent_email'] ?? null,
                'tenant_id' => $_SESSION['tenant_id'] ?? 1,
                'created_by' => $_SESSION['user_id'] ?? null
            ];

            $result = $this->userService->createUser($studentData);

            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to create student account',
                    'step' => 'account_creation'
                ];
            }

            $userId = $result['user_id'];

            // Step 4: Generate activation token
            $activationToken = $this->generateActivationToken($userId);

            // Step 5: Send invitation email
            $emailResult = $this->sendStudentInvitation(
                $studentData['email'],
                $studentData['full_name'],
                $activationToken,
                $studentData['parent_email']
            );

            // Step 6: Log success
            $this->logWorkflow('STUDENT_ADDED', [
                'user_id' => $userId,
                'email' => $studentData['email'],
                'invitation_sent' => $emailResult
            ]);

            return [
                'success' => true,
                'message' => 'Student added successfully. Activation email sent.',
                'user_id' => $userId,
                'email_sent' => $emailResult,
                'step' => 'completed'
            ];

        } catch (Exception $e) {
            $this->logError("Exception in addStudent: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'An unexpected error occurred. Please try again.',
                'technical_error' => $e->getMessage(),
                'step' => 'exception'
            ];
        }
    }

    /**
     * Workflow 2: Bulk Student Import (Zero-Stress)
     * Admin uploads CSV → System validates → Creates accounts → Assigns classes → Sends invites
     */
    public function bulkImportStudents($csvFile, $options = []) {
        $this->clearErrors();

        try {
            // Step 1: Parse CSV
            $students = $this->parseStudentCSV($csvFile);

            if (empty($students)) {
                return [
                    'success' => false,
                    'error' => 'No valid student data found in CSV file',
                    'step' => 'csv_parsing'
                ];
            }

            $results = [
                'total' => count($students),
                'created' => 0,
                'failed' => 0,
                'errors' => [],
                'created_students' => []
            ];

            // Step 2: Process each student
            foreach ($students as $index => $studentData) {
                $rowNum = $index + 2; // +2 for header row and 0-index

                // Validate
                $validation = $this->validateStudentData($studentData);
                if (!$validation['valid']) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'row' => $rowNum,
                        'name' => $studentData['full_name'] ?? 'Unknown',
                        'email' => $studentData['email'] ?? 'Unknown',
                        'errors' => $validation['errors']
                    ];
                    continue;
                }

                // Check duplicate
                if ($this->emailExists($studentData['email'])) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'row' => $rowNum,
                        'name' => $studentData['full_name'],
                        'email' => $studentData['email'],
                        'errors' => ['Email already exists']
                    ];
                    continue;
                }

                // Create student
                $studentRecord = [
                    'email' => strtolower(trim($studentData['email'])),
                    'role' => 'student',
                    'status' => 'pending_activation',
                    'full_name' => $this->normalizeName($studentData['full_name']),
                    'admission_no' => $studentData['admission_no'] ?? null,
                    'grade_level' => $studentData['grade_level'] ?? null,
                    'parent_email' => $studentData['parent_email'] ?? null,
                    'tenant_id' => $_SESSION['tenant_id'] ?? 1,
                    'created_by' => $_SESSION['user_id'] ?? null
                ];

                $result = $this->userService->createUser($studentRecord);

                if (!$result['success']) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'row' => $rowNum,
                        'name' => $studentData['full_name'],
                        'email' => $studentData['email'],
                        'errors' => [$result['error'] ?? 'Account creation failed']
                    ];
                    continue;
                }

                $userId = $result['user_id'];

                // Assign to class if specified
                if (!empty($studentData['class_name'])) {
                    $classAssignment = $this->assignStudentToClass(
                        $userId,
                        $studentData['class_name'],
                        $studentData['grade_level'] ?? null
                    );

                    if (!$classAssignment['success']) {
                        $this->logError("Failed to assign student $userId to class: " . $studentData['class_name']);
                    }
                }

                // Send invitation
                $activationToken = $this->generateActivationToken($userId);
                $this->sendStudentInvitation(
                    $studentRecord['email'],
                    $studentRecord['full_name'],
                    $activationToken,
                    $studentRecord['parent_email']
                );

                $results['created']++;
                $results['created_students'][] = [
                    'user_id' => $userId,
                    'name' => $studentRecord['full_name'],
                    'email' => $studentRecord['email']
                ];
            }

            // Step 3: Generate error report if needed
            $errorReportPath = null;
            if ($results['failed'] > 0) {
                $errorReportPath = $this->generateErrorReport($results['errors']);
            }

            // Step 4: Log bulk import
            $this->logWorkflow('BULK_STUDENT_IMPORT', [
                'total' => $results['total'],
                'created' => $results['created'],
                'failed' => $results['failed'],
                'error_report' => $errorReportPath
            ]);

            return [
                'success' => true,
                'message' => "Import completed: {$results['created']} created, {$results['failed']} failed",
                'results' => $results,
                'error_report' => $errorReportPath,
                'step' => 'completed'
            ];

        } catch (Exception $e) {
            $this->logError("Exception in bulkImportStudents: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'An unexpected error occurred during import.',
                'technical_error' => $e->getMessage(),
                'step' => 'exception'
            ];
        }
    }

    /**
     * Workflow 3: Create Class (Zero-Stress)
     * Admin creates class → Assigns teacher → Assigns students
     */
    public function createClass($data) {
        $this->clearErrors();

        try {
            // Step 1: Validate
            $required = ['name', 'grade_level'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'error' => "{$field} is required",
                        'step' => 'validation'
                    ];
                }
            }

            // Step 2: Check for duplicate
            if ($this->classExists($data['name'], $data['grade_level'])) {
                return [
                    'success' => false,
                    'error' => 'A class with this name and grade level already exists',
                    'step' => 'duplicate_check'
                ];
            }

            // Step 3: Create class
            $classData = [
                'name' => mysqli_real_escape_string($this->db, trim($data['name'])),
                'grade_level' => mysqli_real_escape_string($this->db, $data['grade_level']),
                'academic_year' => $data['academic_year'] ?? date('Y'),
                'description' => mysqli_real_escape_string($this->db, $data['description'] ?? ''),
                'tenant_id' => $_SESSION['tenant_id'] ?? 1,
                'created_by' => $_SESSION['user_id'] ?? null
            ];

            // Add teacher if specified
            if (!empty($data['teacher_id'])) {
                $teacherId = (int)$data['teacher_id'];
                if (!$this->teacherExists($teacherId)) {
                    return [
                        'success' => false,
                        'error' => 'Selected teacher does not exist',
                        'step' => 'teacher_validation'
                    ];
                }
                $classData['teacher_id'] = $teacherId;
            }

            $sql = "INSERT INTO classes (" . implode(', ', array_keys($classData)) . ")
                    VALUES ('" . implode("', '", array_values($classData)) . "')";

            if (!$this->db->query($sql)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create class: ' . $this->db->error,
                    'step' => 'class_creation'
                ];
            }

            $classId = $this->db->insert_id;

            // Step 4: Assign students if specified
            $assignedStudents = 0;
            if (!empty($data['student_ids']) && is_array($data['student_ids'])) {
                foreach ($data['student_ids'] as $studentId) {
                    $enrollResult = $this->enrollStudent($classId, (int)$studentId);
                    if ($enrollResult) {
                        $assignedStudents++;
                    }
                }
            }

            // Step 5: Log success
            $this->logWorkflow('CLASS_CREATED', [
                'class_id' => $classId,
                'name' => $data['name'],
                'teacher_id' => $data['teacher_id'] ?? null,
                'students_assigned' => $assignedStudents
            ]);

            return [
                'success' => true,
                'message' => 'Class created successfully' .
                    ($assignedStudents > 0 ? " with {$assignedStudents} students enrolled" : ''),
                'class_id' => $classId,
                'students_assigned' => $assignedStudents,
                'step' => 'completed'
            ];

        } catch (Exception $e) {
            $this->logError("Exception in createClass: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'An unexpected error occurred.',
                'technical_error' => $e->getMessage(),
                'step' => 'exception'
            ];
        }
    }

    /**
     * Helper: Validate teacher data
     */
    private function validateTeacherData($data) {
        $errors = [];

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }

        if (empty($data['full_name']) || strlen(trim($data['full_name'])) < 2) {
            $errors[] = 'Full name is required (minimum 2 characters)';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Helper: Validate student data
     */
    private function validateStudentData($data) {
        $errors = [];

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }

        if (empty($data['full_name']) || strlen(trim($data['full_name'])) < 2) {
            $errors[] = 'Full name is required';
        }

        // Optional parent email validation
        if (!empty($data['parent_email']) && !filter_var($data['parent_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Parent email must be valid';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Helper: Parse student CSV
     */
    private function parseStudentCSV($file) {
        $students = [];

        if (!file_exists($file)) {
            return $students;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            return $students;
        }

        // Read header
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return $students;
        }

        // Normalize headers
        $headerMap = [];
        foreach ($headers as $index => $header) {
            $normalized = strtolower(str_replace([' ', '-'], '_', trim($header)));
            $headerMap[$index] = $normalized;
        }

        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue; // Skip malformed rows
            }

            $student = [];
            foreach ($row as $index => $value) {
                if (isset($headerMap[$index])) {
                    $student[$headerMap[$index]] = trim($value);
                }
            }

            // Map common variations
            $mappings = [
                'student_name' => 'full_name',
                'name' => 'full_name',
                'student_email' => 'email',
                'class' => 'class_name',
                'grade' => 'grade_level'
            ];

            foreach ($mappings as $from => $to) {
                if (isset($student[$from]) && !isset($student[$to])) {
                    $student[$to] = $student[$from];
                }
            }

            $students[] = $student;
        }

        fclose($handle);

        return $students;
    }

    /**
     * Helper: Generate error report
     */
    private function generateErrorReport($errors) {
        $filename = 'import_errors_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = __DIR__ . '/../../uploads/' . $filename;

        $handle = fopen($filepath, 'w');

        // Header
        fputcsv($handle, ['Row', 'Name', 'Email', 'Error(s)']);

        // Data
        foreach ($errors as $error) {
            fputcsv($handle, [
                $error['row'],
                $error['name'],
                $error['email'],
                implode('; ', $error['errors'])
            ]);
        }

        fclose($handle);

        return $filepath;
    }

    /**
     * Helper: Send teacher invitation
     */
    private function sendTeacherInvitation($email, $name, $token) {
        $subject = "Welcome to SAMS - Activate Your Teacher Account";

        $activationUrl = $this->getBaseUrl() . "/activate-account.php?token=" . urlencode($token);

        $body = "
        <h2>Welcome to SAMS, $name!</h2>
        <p>Your teacher account has been created. Please activate it by clicking the link below:</p>
        <p><a href='$activationUrl' style='padding: 10px 20px; background: #4F46E5; color: white; text-decoration: none; border-radius: 5px;'>Activate Account</a></p>
        <p>Or copy this link: $activationUrl</p>
        <p>This link expires in 24 hours.</p>
        ";

        return $this->emailService->send($email, $subject, $body);
    }

    /**
     * Helper: Send student invitation
     */
    private function sendStudentInvitation($email, $name, $token, $parentEmail = null) {
        $subject = "Welcome to SAMS - Activate Your Student Account";

        $activationUrl = $this->getBaseUrl() . "/activate-account.php?token=" . urlencode($token);

        $body = "
        <h2>Welcome to SAMS, $name!</h2>
        <p>Your student account has been created. Please activate it:</p>
        <p><a href='$activationUrl' style='padding: 10px 20px; background: #4F46E5; color: white; text-decoration: none; border-radius: 5px;'>Activate Account</a></p>
        <p>This link expires in 24 hours.</p>
        ";

        $this->emailService->send($email, $subject, $body);

        // Also notify parent if email provided
        if ($parentEmail) {
            $parentSubject = "Your Child's SAMS Account Created";
            $parentBody = "
            <h2>SAMS Account Created</h2>
            <p>An account has been created for your child: $name</p>
            <p>They will receive an activation email at: $email</p>
            ";
            $this->emailService->send($parentEmail, $parentSubject, $parentBody);
        }
    }

    /**
     * Additional helper methods
     */
    private function clearErrors() {
        $this->errorLog = [];
    }

    private function logError($message) {
        $this->errorLog[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message
        ];
        error_log("AdminWorkflow Error: $message");
    }

    private function logWorkflow($action, $data) {
        $json = json_encode($data);
        $userId = $_SESSION['user_id'] ?? 0;
        $this->db->query("INSERT INTO workflow_logs (action, data, user_id, created_at)
            VALUES ('$action', '$json', $userId, NOW())");
    }

    private function emailExists($email) {
        $email = mysqli_real_escape_string($this->db, strtolower(trim($email)));
        $result = $this->db->query("SELECT id FROM users WHERE email = '$email' AND status != 'deleted' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    }

    private function classExists($name, $grade) {
        $name = mysqli_real_escape_string($this->db, $name);
        $grade = mysqli_real_escape_string($this->db, $grade);
        $tenantId = $_SESSION['tenant_id'] ?? 1;

        $result = $this->db->query("SELECT id FROM classes
            WHERE name = '$name' AND grade_level = '$grade' AND tenant_id = $tenantId LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    }

    private function teacherExists($teacherId) {
        $teacherId = (int)$teacherId;
        $result = $this->db->query("SELECT id FROM users
            WHERE id = $teacherId AND role = 'teacher' AND status = 'active' LIMIT 1");
        return $result && mysqli_num_rows($result) > 0;
    }

    private function assignStudentToClass($studentId, $className, $gradeLevel = null) {
        $studentId = (int)$studentId;
        $className = mysqli_real_escape_string($this->db, $className);
        $tenantId = $_SESSION['tenant_id'] ?? 1;

        // Find class
        $sql = "SELECT id FROM classes WHERE name = '$className' AND tenant_id = $tenantId";
        if ($gradeLevel) {
            $gradeLevel = mysqli_real_escape_string($this->db, $gradeLevel);
            $sql .= " AND grade_level = '$gradeLevel'";
        }
        $sql .= " LIMIT 1";

        $result = $this->db->query($sql);

        if ($result && mysqli_num_rows($result) > 0) {
            $classId = (int)mysqli_fetch_assoc($result)['id'];
            return $this->enrollStudent($classId, $studentId);
        }

        return ['success' => false, 'error' => 'Class not found'];
    }

    private function enrollStudent($classId, $studentId) {
        $classId = (int)$classId;
        $studentId = (int)$studentId;

        $sql = "INSERT INTO enrollments (class_id, student_id, enrolled_at)
                VALUES ($classId, $studentId, NOW())
                ON DUPLICATE KEY UPDATE enrolled_at = NOW()";

        return $this->db->query($sql);
    }

    private function normalizeName($name) {
        return ucwords(strtolower(trim($name)));
    }

    private function generateActivationToken($userId) {
        $token = bin2hex(random_bytes(32));
        $userId = (int)$userId;

        $this->db->query("UPDATE users SET activation_token = '$token' WHERE id = $userId");

        return $token;
    }

    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "$protocol://$host";
    }
}
