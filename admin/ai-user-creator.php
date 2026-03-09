<?php
/**
 * AI-Powered User Creator
 * Extracts user details from Google Form responses and creates accounts
 * with OTP-based passwordless onboarding.
 *
 * Flow:
 *  1. Admin pastes Google Form responses (JSON / CSV / raw text)
 *  2. System extracts: full_name, email, phone, role, class/department
 *  3. Creates user account (no password) with CONFIRM OTP token
 *  4. Sends OTP confirmation email to each user
 *  5. User clicks link, enters OTP, creates their own password
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/email-helper.php';

class AI_User_Creator
{
    /** Maps common Google Form header variants to canonical field names */
    private const FIELD_MAP = [
        'full_name'   => ['full name', 'full_name', 'fullname', 'name', 'student name', 'teacher name', 'staff name'],
        'first_name'  => ['first name', 'first_name', 'firstname', 'given name'],
        'last_name'   => ['last name', 'last_name', 'lastname', 'surname', 'family name'],
        'email'       => ['email', 'email address', 'e-mail', 'mail', 'email_address'],
        'phone'       => ['phone', 'phone number', 'phone_number', 'mobile', 'mobile number', 'tel', 'telephone', 'contact'],
        'role'        => ['role', 'user role', 'position', 'account type', 'type', 'user_role'],
        'class'       => ['class', 'class name', 'grade', 'grade level', 'section', 'department', 'class/department'],
        'gender'      => ['gender', 'sex'],
        'dob'         => ['date of birth', 'dob', 'date_of_birth', 'birthday', 'birth date'],
        'address'     => ['address', 'home address', 'residential address'],
    ];

    private const ROLE_ALIASES = [
        'student' => ['student', 'learner', 'pupil'],
        'teacher' => ['teacher', 'tutor', 'instructor', 'lecturer', 'educator'],
        'parent'  => ['parent', 'guardian', 'mother', 'father', 'caretaker'],
        'admin'   => ['admin', 'administrator', 'admin_officer'],
        'staff'   => ['staff', 'non-teaching', 'support staff'],
    ];

    /**
     * Parse raw input (JSON, CSV, or key-value text) into validated user rows
     * @return array{users: array, errors: array}
     */
    public function parseInput(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['users' => [], 'errors' => ['Input is empty']];
        }

        // Try JSON first
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $rows = isset($json[0]) ? $json : [$json];
            return $this->normalizeRows($rows);
        }

        // Try CSV
        $lines = preg_split('/\r?\n/', $raw);
        if (count($lines) >= 2 && strpos($lines[0], ',') !== false) {
            return $this->parseCSV($lines);
        }

        // Treat as single record key-value pairs
        return $this->parseSingleRecord($raw);
    }

    /**
     * Create accounts for validated user data
     * @return array{created: array, failed: array}
     */
    public function createAccounts(array $users, int $admin_id): array
    {
        $created = [];
        $failed = [];
        foreach ($users as $u) {
            $result = $this->createSingleAccount($u, $admin_id);
            if ($result['success']) {
                $created[] = $result;
            } else {
                $failed[] = $result;
            }
        }
        return ['created' => $created, 'failed' => $failed];
    }

    /**
     * Parse raw source and create accounts in one call.
     * @return array{created: array, failed: array, parse_errors: array}
     */
    public function processRaw(string $raw, int $admin_id): array
    {
        $parsed = $this->parseInput($raw);
        if (empty($parsed['users'])) {
            return ['created' => [], 'failed' => [], 'parse_errors' => $parsed['errors']];
        }

        $created = $this->createAccounts($parsed['users'], $admin_id);
        return [
            'created' => $created['created'],
            'failed' => $created['failed'],
            'parse_errors' => $parsed['errors'],
        ];
    }

    // ── PARSING ───────────────────────────────────────────────────

    private function parseCSV(array $lines): array
    {
        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn($h) => strtolower(trim($h)), $header);
        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $vals = str_getcsv($line);
            $row = [];
            foreach ($header as $i => $h) {
                $row[$h] = trim($vals[$i] ?? '');
            }
            $rows[] = $row;
        }
        return $this->normalizeRows($rows);
    }

    private function parseSingleRecord(string $raw): array
    {
        $data = [];
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (preg_match('/^(.+?)\s*[:=]\s*(.+)$/', $line, $m)) {
                $data[strtolower(trim($m[1]))] = trim($m[2]);
            }
        }
        if (empty($data)) {
            return ['users' => [], 'errors' => ['Could not parse input. Use CSV, JSON, or "Field: Value" format.']];
        }
        return $this->normalizeRows([$data]);
    }

    private function normalizeRows(array $rows): array
    {
        $users = [];
        $errors = [];

        foreach ($rows as $idx => $row) {
            $mapped = [];
            $row = array_change_key_case($row, CASE_LOWER);

            foreach (self::FIELD_MAP as $canonical => $variants) {
                foreach ($variants as $v) {
                    if (isset($row[$v]) && $row[$v] !== '') {
                        $mapped[$canonical] = $row[$v];
                        break;
                    }
                }
            }

            // Build full name
            if (empty($mapped['full_name']) && !empty($mapped['first_name'])) {
                $mapped['full_name'] = trim($mapped['first_name'] . ' ' . ($mapped['last_name'] ?? ''));
            }
            if (!empty($mapped['full_name']) && empty($mapped['first_name'])) {
                $parts = preg_split('/\s+/', $mapped['full_name'], 2);
                $mapped['first_name'] = $parts[0];
                $mapped['last_name'] = $parts[1] ?? '';
            }

            // Normalize role
            $mapped['role'] = $this->resolveRole($mapped['role'] ?? 'student');

            // Validate
            $rowNum = $idx + 1;
            if (empty($mapped['full_name']) && empty($mapped['first_name'])) {
                $errors[] = "Row $rowNum: Name is required";
                continue;
            }
            if (empty($mapped['email']) || !filter_var($mapped['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row $rowNum: Valid email required" . (!empty($mapped['email']) ? " (got: {$mapped['email']})" : '');
                continue;
            }

            // Check existing
            if (db()->fetchOne('SELECT id FROM users WHERE email = ?', [$mapped['email']])) {
                $errors[] = "Row $rowNum: {$mapped['email']} already exists";
                continue;
            }

            $users[] = $mapped;
        }

        return ['users' => $users, 'errors' => $errors];
    }

    private function resolveRole(string $input): string
    {
        $input = strtolower(trim($input));
        foreach (self::ROLE_ALIASES as $canonical => $aliases) {
            if (in_array($input, $aliases, true)) return $canonical;
        }
        return 'student';
    }

    // ── ACCOUNT CREATION ──────────────────────────────────────────

    private function createSingleAccount(array $data, int $admin_id): array
    {
        $email = $data['email'];
        $first_name = $data['first_name'] ?? '';
        $last_name = $data['last_name'] ?? '';
        $full_name = $data['full_name'] ?? trim("$first_name $last_name");
        $role = $data['role'] ?? 'student';
        $phone = $data['phone'] ?? '';

        try {
            db()->query('START TRANSACTION');

            $otp = sprintf('%06d', random_int(100000, 999999));
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $otp_token = 'CONFIRM:' . $otp . ':' . strtotime($otp_expiry);
            $assigned_id = $this->generateAssignedId($role);

            $payload = build_user_payload([
                'email' => $email,
                'password' => bin2hex(random_bytes(32)),
                'role' => $role,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'full_name' => $full_name,
                'status' => 'active',
                'approved' => 1,
                'email_verified' => 0,
                'assigned_id' => $assigned_id,
            ]);
            // Override: account must stay inactive until OTP confirmed
            $payload['is_active'] = 0;
            $payload['email_verified'] = 0;
            $payload['verification_token'] = $otp_token;
            if (table_has_column('users', 'token_expiry')) {
                $payload['token_expiry'] = $otp_expiry;
            }
            if ($phone !== '' && table_has_column('users', 'phone')) {
                $payload['phone'] = $phone;
            }

            $user_id = insert_flexible('users', $payload);
            if (!$user_id) throw new \Exception('User insert failed');

            $this->createRoleProfile($user_id, $role, $data, $assigned_id);
            attach_user_to_tenant((int)$user_id, current_tenant_id());

            // Log in form submissions table
            try {
                insert_flexible('google_form_submissions', [
                    'raw_data' => json_encode($data),
                    'extracted_data' => json_encode(['full_name' => $full_name, 'email' => $email, 'role' => $role, 'assigned_id' => $assigned_id]),
                    'processing_status' => 'completed',
                    'created_user_id' => $user_id,
                    'processed_at' => date('Y-m-d H:i:s'),
                    'processed_by' => $admin_id,
                ]);
            } catch (\Throwable $e) {
                error_log('form_submissions log: ' . $e->getMessage());
            }

            db()->query('COMMIT');
            log_activity($admin_id, 'ai_create_user', 'users', $user_id, "Created $role: $email");

            $email_sent = send_account_otp_email($email, $full_name, $otp, $assigned_id, $role);

            return [
                'success' => true,
                'user_id' => $user_id,
                'email' => $email,
                'name' => $full_name,
                'role' => $role,
                'assigned_id' => $assigned_id,
                'email_sent' => $email_sent,
            ];
        } catch (\Throwable $e) {
            db()->query('ROLLBACK');
            error_log("AI User Creator: $email - " . $e->getMessage());
            return [
                'success' => false,
                'email' => $email,
                'name' => $full_name ?? '',
                'role' => $role ?? '',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function generateAssignedId(string $role): string
    {
        $prefix = match ($role) {
            'teacher' => 'TCH',
            'parent'  => 'PAR',
            'admin'   => 'ADM',
            default   => 'STD',
        };
        $count = db()->count('users', 'role = ?', [$role]) + 1;
        return $prefix . date('Y') . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    private function createRoleProfile(int $user_id, string $role, array $data, string $assigned_id): void
    {
        if ($role === 'student') {
            $class_id = null;
            if (!empty($data['class'])) {
                $cl = db()->fetchOne(
                    "SELECT id FROM classes WHERE class_name LIKE ? OR grade_level LIKE ? LIMIT 1",
                    ['%' . $data['class'] . '%', '%' . $data['class'] . '%']
                );
                $class_id = $cl ? (int)$cl['id'] : null;
            }

            $sid = insert_flexible('students', [
                'user_id' => $user_id,
                'admission_number' => $assigned_id,
                'assigned_student_id' => $assigned_id,
                'class_id' => $class_id,
                'gender' => $this->normalizeGender($data['gender'] ?? ''),
                'date_of_birth' => $this->normalizeDate($data['dob'] ?? ''),
                'is_active' => 1,
            ]);

            if ($class_id && $sid) {
                try {
                    insert_flexible('class_enrollments', [
                        'class_id' => $class_id,
                        'student_id' => $sid,
                        'status' => 'active',
                    ]);
                } catch (\Throwable $e) { /* ignore dupes */ }
            }
        } elseif ($role === 'teacher') {
            insert_flexible('teachers', [
                'user_id' => $user_id,
                'employee_id' => $assigned_id,
                'date_joined' => date('Y-m-d'),
                'is_class_teacher' => 0,
            ]);
        }
    }

    private function normalizeGender(string $input): ?string
    {
        $input = strtolower(trim($input));
        if (in_array($input, ['male', 'm', 'boy'])) return 'male';
        if (in_array($input, ['female', 'f', 'girl'])) return 'female';
        return null;
    }

    private function normalizeDate(string $input): ?string
    {
        if ($input === '') return null;
        $ts = strtotime($input);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}

// ── API HANDLER ───────────────────────────────────────────────────
$is_direct_script = realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($is_direct_script && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $_user_role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';
    if (!in_array($_user_role, ['admin', 'super_admin', 'superadmin', 'owner'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $raw    = $_POST['raw_data'] ?? '';

    $creator = new AI_User_Creator();

    if ($action === 'parse') {
        $result = $creator->parseInput($raw);
        echo json_encode(['success' => true, 'users' => $result['users'], 'errors' => $result['errors']]);
        exit;
    }

    if ($action === 'create') {
        $users = json_decode($_POST['users_json'] ?? '[]', true);
        if (empty($users)) {
            echo json_encode(['success' => false, 'error' => 'No user data provided']);
            exit;
        }
        $admin_id = (int)($_SESSION['user_id'] ?? 0);
        $result = $creator->createAccounts($users, $admin_id);
        echo json_encode(['success' => true, 'created' => $result['created'], 'failed' => $result['failed']]);
        exit;
    }

    // Legacy: combined parse + create
    if ($raw !== '') {
        $parsed = $creator->parseInput($raw);
        if (!empty($parsed['users'])) {
            $admin_id = (int)($_SESSION['user_id'] ?? 0);
            $result = $creator->createAccounts($parsed['users'], $admin_id);
            echo json_encode(['success' => true, 'created' => $result['created'], 'failed' => $result['failed'], 'parse_errors' => $parsed['errors']]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No valid users found', 'errors' => $parsed['errors']]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}
