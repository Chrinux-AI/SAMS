<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/email-helper.php';

class AccountCreationAutomation
{
    private $tenantId;

    public function __construct($tenantId = null)
    {
        $this->tenantId = $tenantId ?? 1;
    }

    /**
     * Process Google Form webhook data
     */
    public function processGoogleFormWebhook($data)
    {
        try {
            // Validate webhook secret
            if (!$this->validateWebhookSecret($data['secret'] ?? '')) {
                throw new Exception('Invalid webhook secret');
            }

            // Extract form data
            $formData = $this->extractFormData($data);

            // Validate extracted data
            $validation = $this->validateFormData($formData);
            if (!$validation['valid']) {
                throw new Exception('Validation failed: ' . implode(', ', $validation['errors']));
            }

            // Check for duplicates
            if ($this->checkDuplicateAccount($formData['email'])) {
                throw new Exception('Account already exists for this email');
            }

            // Create pending user
            $userId = $this->createPendingUser($formData);

            if ($userId) {
                // Generate activation token
                $activationToken = $this->generateActivationToken($userId);

                // Send activation email
                $emailSent = $this->sendActivationEmail($formData, $activationToken);

                if ($emailSent) {
                    // Log successful creation
                    $this->logAccountCreation($userId, 'pending', 'google_form');

                    return [
                        'success' => true,
                        'user_id' => $userId,
                        'message' => 'Account created successfully. Activation email sent.'
                    ];
                } else {
                    throw new Exception('Failed to send activation email');
                }
            } else {
                throw new Exception('Failed to create user account');
            }

        } catch (Exception $e) {
            error_log("AccountCreationAutomation::processGoogleFormWebhook error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate webhook secret
     */
    private function validateWebhookSecret($secret)
    {
        $expectedSecret = defined('GOOGLE_FORM_WEBHOOK_SECRET') ? GOOGLE_FORM_WEBHOOK_SECRET : 'sams_webhook_2024';
        return hash_equals($expectedSecret, $secret);
    }

    /**
     * Extract and clean form data
     */
    private function extractFormData($data)
    {
        $rawData = $data['form_data'] ?? [];

        return [
            'first_name' => $this->cleanInput($rawData['first_name'] ?? ''),
            'last_name' => $this->cleanInput($rawData['last_name'] ?? ''),
            'email' => strtolower($this->cleanInput($rawData['email'] ?? '')),
            'phone' => $this->cleanInput($rawData['phone'] ?? ''),
            'role' => $this->cleanInput($rawData['role'] ?? 'student'),
            'grade_level' => $this->cleanInput($rawData['grade_level'] ?? ''),
            'parent_email' => strtolower($this->cleanInput($rawData['parent_email'] ?? '')),
            'class_name' => $this->cleanInput($rawData['class_name'] ?? ''),
            'submission_date' => $data['submission_date'] ?? date('Y-m-d H:i:s')
        ];
    }

    /**
     * Clean input data
     */
    private function cleanInput($input)
    {
        return trim(strip_tags($input));
    }

    /**
     * Validate form data
     */
    private function validateFormData($data)
    {
        $errors = [];

        // Required fields
        if (empty($data['first_name'])) {
            $errors[] = 'First name is required';
        }

        if (empty($data['last_name'])) {
            $errors[] = 'Last name is required';
        }

        if (empty($data['email'])) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        if (empty($data['role'])) {
            $errors[] = 'Role is required';
        } elseif (!in_array($data['role'], ['student', 'teacher', 'parent'])) {
            $errors[] = 'Invalid role specified';
        }

        // Role-specific validations
        switch ($data['role']) {
            case 'student':
                if (empty($data['grade_level'])) {
                    $errors[] = 'Grade level is required for students';
                }
                if (empty($data['parent_email'])) {
                    $errors[] = 'Parent email is required for students';
                } elseif (!filter_var($data['parent_email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Invalid parent email format';
                }
                break;

            case 'teacher':
                if (empty($data['phone'])) {
                    $errors[] = 'Phone number is required for teachers';
                }
                break;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Check for duplicate accounts
     */
    private function checkDuplicateAccount($email)
    {
        try {
            $existing = db()->fetchOne("
                SELECT id FROM users
                WHERE email = ? AND tenant_id = ?
            ", [$email, $this->tenantId]);

            return !empty($existing);
        } catch (Exception $e) {
            error_log("AccountCreationAutomation::checkDuplicateAccount error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create pending user account
     */
    private function createPendingUser($formData)
    {
        try {
            // Generate username
            $username = $this->generateUsername($formData['first_name'], $formData['last_name']);

            // Generate temporary password
            $tempPassword = $this->generateTempPassword();

            // Insert user record
            $userId = db()->insert('users', [
                'tenant_id' => $this->tenantId,
                'username' => $username,
                'password' => password_hash($tempPassword, PASSWORD_DEFAULT),
                'email' => $formData['email'],
                'first_name' => $formData['first_name'],
                'last_name' => $formData['last_name'],
                'phone' => $formData['phone'] ?? '',
                'role' => $formData['role'],
                'status' => 'pending',
                'email_verified' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            if ($userId) {
                // Create role-specific records
                $this->createRoleSpecificRecord($userId, $formData);

                // Store form submission record
                db()->insert('google_form_submissions', [
                    'tenant_id' => $this->tenantId,
                    'user_id' => $userId,
                    'form_data' => json_encode($formData),
                    'submission_date' => $formData['submission_date'],
                    'processed_date' => date('Y-m-d H:i:s'),
                    'status' => 'pending_activation'
                ]);
            }

            return $userId;

        } catch (Exception $e) {
            error_log("AccountCreationAutomation::createPendingUser error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate unique username
     */
    private function generateUsername($firstName, $lastName)
    {
        $baseUsername = strtolower(substr($firstName, 0, 1) . $lastName);
        $username = $baseUsername;
        $counter = 1;

        // Ensure username is unique
        while (true) {
            $existing = db()->fetchOne("
                SELECT id FROM users
                WHERE username = ? AND tenant_id = ?
            ", [$username, $this->tenantId]);

            if (empty($existing)) {
                break;
            }

            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Generate temporary password
     */
    private function generateTempPassword()
    {
        return substr(md5(uniqid(mt_rand(), true)), 0, 8);
    }

    /**
     * Create role-specific records
     */
    private function createRoleSpecificRecord($userId, $formData)
    {
        try {
            switch ($formData['role']) {
                case 'student':
                    db()->insert('students', [
                        'user_id' => $userId,
                        'tenant_id' => $this->tenantId,
                        'admission_number' => $this->generateAdmissionNumber(),
                        'grade_level' => $formData['grade_level'],
                        'status' => 'pending',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);

                    // Link parent if provided
                    if (!empty($formData['parent_email'])) {
                        $this->linkParentToStudent($userId, $formData['parent_email']);
                    }
                    break;

                case 'teacher':
                    db()->insert('teachers', [
                        'user_id' => $userId,
                        'tenant_id' => $this->tenantId,
                        'employee_id' => $this->generateEmployeeId(),
                        'department' => 'General',
                        'status' => 'pending',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    break;

                case 'parent':
                    db()->insert('parents', [
                        'user_id' => $userId,
                        'tenant_id' => $this->tenantId,
                        'occupation' => 'Not specified',
                        'status' => 'pending',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    break;
            }
        } catch (Exception $e) {
            error_log("AccountCreationAutomation::createRoleSpecificRecord error: " . $e->getMessage());
        }
    }

    /**
     * Generate admission number
     */
    private function generateAdmissionNumber()
    {
        $year = date('Y');
        $sequence = db()->fetchOne("
            SELECT COUNT(*) as count FROM students
            WHERE YEAR(created_at) = ? AND tenant_id = ?
        ", [$year, $this->tenantId]);

        return 'ADM' . $year . str_pad(($sequence['count'] + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate employee ID
     */
    private function generateEmployeeId()
    {
        $year = date('Y');
        $sequence = db()->fetchOne("
            SELECT COUNT(*) as count FROM teachers
            WHERE YEAR(created_at) = ? AND tenant_id = ?
        ", [$year, $this->tenantId]);

        return 'EMP' . $year . str_pad(($sequence['count'] + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Link parent to student
     */
    private function linkParentToStudent($studentId, $parentEmail)
    {
        try {
            $parent = db()->fetchOne("
                SELECT u.id FROM users u
                JOIN parents p ON u.id = p.user_id
                WHERE u.email = ? AND u.tenant_id = ?
            ", [$parentEmail, $this->tenantId]);

            if ($parent) {
                db()->insert('parent_student_links', [
                    'parent_id' => $parent['id'],
                    'student_id' => $studentId,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        } catch (Exception $e) {
            error_log("AccountCreationAutomation::linkParentToStudent error: " . $e->getMessage());
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

            $subject = 'Activate Your ' . APP_NAME . ' Account';
            $message = $this->getActivationEmailTemplate($formData, $activationUrl);

            // Use PHP mail function as fallback
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: " . APP_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">" . "\r\n";

            return mail($formData['email'], $subject, $message, $headers);

        } catch (Exception $e) {
            error_log("AccountCreationAutomation::sendActivationEmail error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get activation email template
     */
    private function getActivationEmailTemplate($formData, $activationUrl)
    {
        $template = "
        <html>
        <head>
            <title>Activate Your Account</title>
        </head>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white;'>
                <h1>Welcome to " . APP_NAME . "!</h1>
                <p>Your account has been created successfully</p>
            </div>

            <div style='padding: 30px; background: #f9f9f9;'>
                <h2>Hi {$formData['first_name']} {$formData['last_name']},</h2>
                <p>Thank you for registering with the School Attendance Management System. Your account has been created and is ready for activation.</p>

                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3>Account Details:</h3>
                    <p><strong>Name:</strong> {$formData['first_name']} {$formData['last_name']}</p>
                    <p><strong>Email:</strong> {$formData['email']}</p>
                    <p><strong>Role:</strong> " . ucfirst($formData['role']) . "</p>";

                    if ($formData['role'] === 'student') {
                        $template .= "<p><strong>Grade Level:</strong> {$formData['grade_level']}</p>";
                    }

                    $template .= "
                </div>

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$activationUrl}' style='background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Activate Your Account</a>
                </div>

                <p style='color: #666; font-size: 14px;'>This activation link will expire in 24 hours. If you didn't request this account, please ignore this email.</p>
                <p style='color: #666; font-size: 14px;'>If the button above doesn't work, copy and paste this link into your browser:<br>
                <small>{$activationUrl}</small></p>
            </div>

            <div style='background: #333; color: white; padding: 20px; text-align: center; font-size: 12px;'>
                <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
            </div>
        </body>
        </html>";

        return $template;
    }

    /**
     * Log account creation
     */
    private function logAccountCreation($userId, $status, $source)
    {
        try {
            db()->insert('audit_logs', [
                'tenant_id' => $this->tenantId,
                'user_id' => $userId,
                'action' => 'account_created',
                'details' => json_encode([
                    'status' => $status,
                    'source' => $source,
                    'timestamp' => date('Y-m-d H:i:s')
                ]),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("AccountCreationAutomation::logAccountCreation error: " . $e->getMessage());
        }
    }

    /**
     * Get automation statistics
     */
    public function getStatistics($dateRange = '7 days')
    {
        try {
            $stats = db()->fetchOne("
                SELECT
                    COUNT(*) as total_submissions,
                    SUM(CASE WHEN status = 'pending_activation' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'activated' THEN 1 ELSE 0 END) as activated,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    DATE(processed_date) as date
                FROM google_form_submissions
                WHERE tenant_id = ? AND processed_date >= DATE_SUB(NOW(), INTERVAL {$dateRange})
                GROUP BY DATE(processed_date)
                ORDER BY date DESC
                LIMIT 1
            ", [$this->tenantId]);

            return $stats;
        } catch (Exception $e) {
            error_log("AccountCreationAutomation::getStatistics error: " . $e->getMessage());
            return null;
        }
    }
}
