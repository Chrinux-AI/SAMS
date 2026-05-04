<?php

class AdminUserManager
{
    private $db;
    private $tenantId;
    private $actorId;

    public function __construct(?int $tenantId = null, ?int $actorId = null)
    {
        $this->db = db();
        $this->tenantId = $tenantId ?? current_tenant_id();
        $this->actorId = $actorId ?? (int)($_SESSION['user_id'] ?? 0);
    }

    public function getApprovalScreenData(): array
    {
        return [
            'pending_users' => $this->getPendingUsers(),
            'unverified_users' => $this->getUnverifiedUsers(),
        ];
    }

    public function getUsersPageData(array $filters = []): array
    {
        return [
            'users' => $this->getUsers($filters),
            'stats' => [
                'total_users' => $this->countUsers(),
                'active_users' => $this->countUsers(['status' => 'active']),
                'pending_users' => $this->countUsers(['status' => 'pending']),
                'admins' => $this->countUsers(['role' => 'admin']),
                'teachers' => $this->countUsers(['role' => 'teacher']),
                'students' => $this->countUsers(['role' => 'student']),
            ],
        ];
    }

    public function approveUser(int $userId, string $assignedId = '', bool $bypassEmailVerification = false): array
    {
        $user = $this->findTenantUser($userId);
        if (!$user) {
            return $this->failure('User not found in the active tenant.');
        }

        $assignedId = trim($assignedId);
        if ($assignedId === '') {
            $assignedId = $this->generateAssignedId((string)$user['role']);
        }

        try {
            $this->db->beginTransaction();

            $userUpdate = [
                'approved' => 1,
                'approved_by' => $this->actorId > 0 ? $this->actorId : null,
                'approved_at' => date('Y-m-d H:i:s'),
                'assigned_id' => $assignedId,
                'status' => 'active',
                'is_active' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($bypassEmailVerification) {
                $userUpdate['email_verified'] = 1;
                $userUpdate['email_verification_token'] = null;
                $userUpdate['token_expiry'] = null;
                $userUpdate['token_expires_at'] = null;
                $userUpdate['verification_token'] = null;
            }

            if (!update_flexible('users', $userUpdate, 'id = ?', [$userId])) {
                throw new RuntimeException('Failed to update the user approval record.');
            }

            $this->syncRoleProfile((string)$user['role'], $userId, $assignedId);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->failure($e->getMessage());
        }

        $emailSent = false;
        try {
            $emailSent = (bool)send_approval_notification(
                $userId,
                (string)$user['email'],
                trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? '')),
                (string)$user['role'],
                $assignedId,
                (string)($user['username'] ?? $user['email'] ?? $assignedId)
            );
        } catch (Throwable $e) {
            error_log('Admin approval notification failed: ' . $e->getMessage());
        }

        $action = $bypassEmailVerification ? 'approve_unverified_user' : 'approve_user';
        $message = $bypassEmailVerification
            ? "Approved user without email verification. Assigned ID: {$assignedId}"
            : "Approved user successfully. Assigned ID: {$assignedId}";

        $this->logAction($action, $userId, "{$message} ({$user['email']})");

        return [
            'success' => true,
            'message' => $message,
            'assigned_id' => $assignedId,
            'email_sent' => $emailSent,
        ];
    }

    public function bulkApproveUsers(array $userIds, array $assignedIds = []): array
    {
        $processed = 0;
        $errors = [];

        foreach ($this->normalizeIdList($userIds) as $userId) {
            $result = $this->approveUser($userId, (string)($assignedIds[$userId] ?? ''));
            if (!empty($result['success'])) {
                $processed++;
                continue;
            }
            $errors[] = "User {$userId}: " . ($result['message'] ?? $result['error'] ?? 'Approval failed');
        }

        return [
            'success' => empty($errors),
            'processed' => $processed,
            'errors' => $errors,
            'message' => "Bulk approval completed: {$processed} user(s) approved.",
        ];
    }

    public function disapproveUser(int $userId): array
    {
        $user = $this->findTenantUser($userId);
        if (!$user) {
            return $this->failure('User not found in the active tenant.');
        }

        $payload = [
            'approved' => 0,
            'approved_by' => null,
            'approved_at' => null,
            'status' => 'pending',
            'is_active' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!update_flexible('users', $payload, 'id = ?', [$userId])) {
            return $this->failure('Failed to update user status.');
        }

        if ((string)$user['role'] === 'student') {
            update_flexible('students', ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')], 'user_id = ?', [$userId]);
        } elseif ((string)$user['role'] === 'teacher') {
            update_flexible('teachers', ['updated_at' => date('Y-m-d H:i:s')], 'user_id = ?', [$userId]);
        }

        $this->logAction('disapprove_user', $userId, 'Disapproved user: ' . (string)$user['email']);

        return [
            'success' => true,
            'message' => 'User moved back to pending status.',
        ];
    }

    public function rejectUser(int $userId): array
    {
        return $this->deleteUser($userId, true);
    }

    public function bulkRejectUsers(array $userIds): array
    {
        return $this->bulkDeleteUsers($userIds, true);
    }

    public function resendVerification(int $userId): array
    {
        $user = $this->findTenantUser($userId);
        if (!$user || (int)($user['email_verified'] ?? 0) === 1) {
            return $this->failure('User not found or already verified.');
        }

        $verificationToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $updated = update_flexible('users', [
            'email_verification_token' => $verificationToken,
            'token_expiry' => $expiresAt,
            'token_expires_at' => $expiresAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$userId]);

        if (!$updated) {
            return $this->failure('Failed to refresh the verification token.');
        }

        if (!send_verification_email(
            (string)$user['email'],
            trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? '')),
            $verificationToken
        )) {
            return $this->failure('Failed to send verification email. Please check email configuration.');
        }

        $this->logAction('resend_verification', $userId, 'Resent verification email to ' . (string)$user['email']);

        return [
            'success' => true,
            'message' => 'Verification email sent successfully.',
            'email' => $user['email'],
        ];
    }

    public function bulkResendVerification(array $userIds): array
    {
        $sent = 0;
        $errors = [];

        foreach ($this->normalizeIdList($userIds) as $userId) {
            $result = $this->resendVerification($userId);
            if (!empty($result['success'])) {
                $sent++;
                continue;
            }
            $errors[] = "User {$userId}: " . ($result['message'] ?? $result['error'] ?? 'Failed');
        }

        return [
            'success' => empty($errors),
            'sent' => $sent,
            'failed' => count($errors),
            'errors' => $errors,
            'message' => "Verification emails sent: {$sent}",
        ];
    }

    public function resendVerificationToAll(): array
    {
        $userIds = array_column($this->getUnverifiedUsers(), 'id');
        if (empty($userIds)) {
            return $this->failure('No unverified users found.');
        }

        return $this->bulkResendVerification($userIds);
    }

    public function deleteUser(int $userId, bool $asRejection = false): array
    {
        if ($userId === $this->actorId) {
            return $this->failure('You cannot delete your own account.');
        }

        $user = $this->findTenantUser($userId);
        if (!$user) {
            return $this->failure('User not found in the active tenant.');
        }

        try {
            $this->db->beginTransaction();

            $this->deleteRoleRelations($user);
            $this->deleteCommonRelations($userId);

            if (!db()->delete('users', 'id = ?', [$userId])) {
                throw new RuntimeException('Failed to delete the user record.');
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->failure($e->getMessage());
        }

        $this->deleteUserFiles($userId, $user);

        $action = $asRejection ? 'reject_user' : 'delete_user';
        $prefix = $asRejection ? 'Rejected and deleted user' : 'Deleted user';
        $this->logAction($action, $userId, "{$prefix}: {$user['email']}");

        return [
            'success' => true,
            'message' => $prefix . ' successfully.',
            'deleted_user' => trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? '')),
        ];
    }

    public function bulkDeleteUsers(array $userIds, bool $asRejection = false): array
    {
        $deleted = 0;
        $errors = [];

        foreach ($this->normalizeIdList($userIds) as $userId) {
            $result = $this->deleteUser($userId, $asRejection);
            if (!empty($result['success'])) {
                $deleted++;
                continue;
            }
            $errors[] = "User {$userId}: " . ($result['message'] ?? $result['error'] ?? 'Delete failed');
        }

        return [
            'success' => empty($errors),
            'deleted' => $deleted,
            'failed' => count($errors),
            'errors' => $errors,
            'message' => "Deleted {$deleted} user(s).",
        ];
    }

    public function deletePendingUsers(): array
    {
        $pendingIds = array_map(
            static fn(array $row): int => (int)$row['id'],
            $this->db->fetchAll(
                "SELECT u.id FROM users u WHERE " . $this->buildPendingStatusSql('u') . $this->buildTenantScopeWhere('u')[0],
                $this->buildTenantScopeWhere('u')[1]
            ) ?: []
        );

        if (empty($pendingIds)) {
            return $this->failure('No pending users found.');
        }

        return $this->bulkDeleteUsers($pendingIds);
    }

    public function getUserDetails(int $userId): ?array
    {
        $user = $this->findTenantUser($userId, true);
        if (!$user) {
            return null;
        }

        if (table_exists('students')) {
            $user['student_profile'] = $this->db->fetchOne(
                "SELECT s.*, c.class_name, c.grade_level
                 FROM students s
                 LEFT JOIN classes c ON c.id = s.class_id
                 WHERE s.user_id = ?
                 LIMIT 1",
                [$userId]
            ) ?: null;
        }

        if (table_exists('teachers')) {
            $user['teacher_profile'] = $this->db->fetchOne(
                "SELECT t.*
                 FROM teachers t
                 WHERE t.user_id = ?
                 LIMIT 1",
                [$userId]
            ) ?: null;
        }

        if (table_exists('tenant_users')) {
            $user['tenant_memberships'] = $this->db->fetchAll(
                "SELECT tenant_id, role_override, is_active, created_at, updated_at
                 FROM tenant_users
                 WHERE user_id = ?
                 ORDER BY updated_at DESC, created_at DESC",
                [$userId]
            ) ?: [];
        } else {
            $user['tenant_memberships'] = [];
        }

        return $user;
    }

    private function getPendingUsers(): array
    {
        $joins = '';
        if (table_exists('students')) {
            $joins .= ' LEFT JOIN students s ON s.user_id = u.id';
        }
        if (table_exists('teachers')) {
            $joins .= ' LEFT JOIN teachers t ON t.user_id = u.id';
        }

        [$tenantWhere, $tenantParams] = $this->buildTenantScopeWhere('u');
        $sql = "
            SELECT
                u.*,
                " . (table_exists('students') ? 's.id AS student_profile_id,' : 'NULL AS student_profile_id,') . "
                " . (table_exists('teachers') ? 't.id AS teacher_profile_id,' : 'NULL AS teacher_profile_id,') . "
                " . (table_exists('students') ? 's.admission_number,' : 'NULL AS admission_number,') . "
                " . (table_exists('students') ? 's.assigned_student_id,' : 'NULL AS assigned_student_id,') . "
                " . (table_exists('teachers') ? 't.employee_id' : 'NULL AS employee_id') . "
            FROM users u
            {$joins}
            WHERE u.email_verified = 1
              AND COALESCE(u.approved, 0) = 0
              {$tenantWhere}
            ORDER BY u.created_at DESC
        ";

        $rows = $this->db->fetchAll($sql, $tenantParams) ?: [];

        foreach ($rows as &$row) {
            $row['generated_id'] = $this->resolveExistingOrGeneratedId($row);
        }

        return $rows;
    }

    private function getUnverifiedUsers(): array
    {
        [$tenantWhere, $tenantParams] = $this->buildTenantScopeWhere('u');
        $sql = "
            SELECT u.*
            FROM users u
            WHERE COALESCE(u.email_verified, 0) = 0
              {$tenantWhere}
            ORDER BY u.created_at DESC
        ";

        return $this->db->fetchAll($sql, $tenantParams) ?: [];
    }

    private function getUsers(array $filters = []): array
    {
        [$whereSql, $params] = $this->buildUsersFilterWhere($filters, 'u');

        $joins = '';
        if (table_exists('students')) {
            $joins .= ' LEFT JOIN students s ON s.user_id = u.id';
        }
        if (table_exists('teachers')) {
            $joins .= ' LEFT JOIN teachers t ON t.user_id = u.id';
        }

        $sql = "
            SELECT
                u.*,
                " . (table_exists('students') ? 's.id AS student_profile_id,' : 'NULL AS student_profile_id,') . "
                " . (table_exists('teachers') ? 't.id AS teacher_profile_id,' : 'NULL AS teacher_profile_id,') . "
                " . (table_exists('students') ? 's.admission_number,' : 'NULL AS admission_number,') . "
                " . (table_exists('students') ? 's.assigned_student_id,' : 'NULL AS assigned_student_id,') . "
                " . (table_exists('teachers') ? 't.employee_id' : 'NULL AS employee_id') . "
            FROM users u
            {$joins}
            {$whereSql}
            ORDER BY u.created_at DESC
        ";

        return $this->db->fetchAll($sql, $params) ?: [];
    }

    private function countUsers(array $filters = []): int
    {
        [$whereSql, $params] = $this->buildUsersFilterWhere($filters, 'u');
        $row = $this->db->fetchOne("SELECT COUNT(*) AS aggregate_count FROM users u {$whereSql}", $params) ?: [];
        return (int)($row['aggregate_count'] ?? 0);
    }

    private function buildUsersFilterWhere(array $filters, string $alias): array
    {
        $parts = ['1=1'];
        $params = [];

        [$tenantWhere, $tenantParams] = $this->buildTenantScopeWhere($alias);
        if ($tenantWhere !== '') {
            $parts[] = ltrim($tenantWhere, ' AND');
            $params = array_merge($params, $tenantParams);
        }

        $role = trim((string)($filters['role'] ?? 'all'));
        if ($role !== '' && strtolower($role) !== 'all') {
            $parts[] = "{$alias}.role = ?";
            $params[] = $role;
        }

        $status = trim((string)($filters['status'] ?? 'all'));
        if ($status !== '' && strtolower($status) !== 'all') {
            if (strtolower($status) === 'pending') {
                $parts[] = $this->buildPendingStatusSql($alias);
            } elseif (strtolower($status) === 'active') {
                $parts[] = "(
                    LOWER(COALESCE({$alias}.status, '')) = 'active'
                    OR COALESCE({$alias}.is_active, 0) = 1
                )";
            } elseif (strtolower($status) === 'inactive') {
                $parts[] = "(
                    LOWER(COALESCE({$alias}.status, '')) IN ('inactive', 'suspended')
                    OR COALESCE({$alias}.is_active, 0) = 0
                )";
            } else {
                $parts[] = "{$alias}.status = ?";
                $params[] = $status;
            }
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $parts[] = "(
                {$alias}.first_name LIKE ?
                OR {$alias}.last_name LIKE ?
                OR {$alias}.email LIKE ?
                OR COALESCE({$alias}.assigned_id, '') LIKE ?
            )";
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }

        return ['WHERE ' . implode(' AND ', $parts), $params];
    }

    private function buildTenantScopeWhere(string $alias): array
    {
        if (table_exists('tenant_users')) {
            return [
                " AND EXISTS (
                    SELECT 1
                    FROM tenant_users tu
                    WHERE tu.user_id = {$alias}.id
                      AND tu.tenant_id = ?
                      AND tu.is_active = 1
                )",
                [$this->tenantId],
            ];
        }

        if (table_has_column('users', 'tenant_id')) {
            return [" AND {$alias}.tenant_id = ?", [$this->tenantId]];
        }

        if (table_has_column('users', 'school_id')) {
            return [" AND {$alias}.school_id = ?", [$this->tenantId]];
        }

        return ['', []];
    }

    private function buildPendingStatusSql(string $alias): string
    {
        return "(
            COALESCE({$alias}.approved, 0) = 0
            OR COALESCE({$alias}.email_verified, 0) = 0
            OR LOWER(COALESCE({$alias}.status, '')) = 'pending'
        )";
    }

    private function findTenantUser(int $userId, bool $includeTenantDetails = false): ?array
    {
        [$tenantWhere, $tenantParams] = $this->buildTenantScopeWhere('u');
        $select = 'u.*';

        if ($includeTenantDetails) {
            $select .= ",
                " . (table_exists('students') ? 's.id AS student_profile_id,' : 'NULL AS student_profile_id,') . "
                " . (table_exists('teachers') ? 't.id AS teacher_profile_id' : 'NULL AS teacher_profile_id') . "
            ";
        }

        $joins = '';
        if ($includeTenantDetails && table_exists('students')) {
            $joins .= ' LEFT JOIN students s ON s.user_id = u.id';
        }
        if ($includeTenantDetails && table_exists('teachers')) {
            $joins .= ' LEFT JOIN teachers t ON t.user_id = u.id';
        }

        $row = $this->db->fetchOne(
            "SELECT {$select}
             FROM users u
             {$joins}
             WHERE u.id = ?{$tenantWhere}
             LIMIT 1",
            array_merge([$userId], $tenantParams)
        );

        return $row ?: null;
    }

    private function syncRoleProfile(string $role, int $userId, string $assignedId): void
    {
        if ($role === 'student' && table_exists('students')) {
            $payload = [
                'user_id' => $userId,
                'admission_number' => $assignedId,
                'assigned_student_id' => $assignedId,
                'is_active' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $existing = $this->db->fetchOne("SELECT id FROM students WHERE user_id = ? LIMIT 1", [$userId]);
            if ($existing) {
                if (!update_flexible('students', $payload, 'user_id = ?', [$userId])) {
                    throw new RuntimeException('Failed to update student profile.');
                }
                return;
            }

            $payload['created_at'] = date('Y-m-d H:i:s');
            if (!insert_flexible('students', $payload)) {
                throw new RuntimeException('Failed to create student profile.');
            }
            return;
        }

        if ($role === 'teacher' && table_exists('teachers')) {
            $payload = [
                'user_id' => $userId,
                'employee_id' => $assignedId,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $existing = $this->db->fetchOne("SELECT id FROM teachers WHERE user_id = ? LIMIT 1", [$userId]);
            if ($existing) {
                if (!update_flexible('teachers', $payload, 'user_id = ?', [$userId])) {
                    throw new RuntimeException('Failed to update teacher profile.');
                }
                return;
            }

            $payload['created_at'] = date('Y-m-d H:i:s');
            $payload['date_joined'] = date('Y-m-d');
            if (!insert_flexible('teachers', $payload)) {
                throw new RuntimeException('Failed to create teacher profile.');
            }
        }
    }

    private function deleteRoleRelations(array $user): void
    {
        $userId = (int)$user['id'];
        $role = (string)($user['role'] ?? '');

        if ($role === 'student' && table_exists('students')) {
            $student = $this->db->fetchOne("SELECT id FROM students WHERE user_id = ? LIMIT 1", [$userId]);
            $studentProfileId = (int)($student['id'] ?? 0);

            if ($studentProfileId > 0) {
                $this->deleteByColumnIfPresent('attendance_records', 'student_id', $studentProfileId);
                $this->deleteByColumnIfPresent('attendance', 'student_id', $studentProfileId);
                $this->deleteByColumnIfPresent('class_enrollments', 'student_id', $studentProfileId);
                $this->deleteByColumnIfPresent('transport_allocations', 'student_id', $studentProfileId);
                $this->deleteByColumnIfPresent('parent_student', 'student_id', $studentProfileId);
                $this->deleteByColumnIfPresent('parent_student_links', 'student_id', $studentProfileId);
            }

            $this->deleteByColumnIfPresent('students', 'user_id', $userId);
            return;
        }

        if ($role === 'teacher' && table_exists('teachers')) {
            $teacher = $this->db->fetchOne("SELECT id FROM teachers WHERE user_id = ? LIMIT 1", [$userId]);
            $teacherProfileId = (int)($teacher['id'] ?? 0);

            if ($teacherProfileId > 0) {
                $this->deleteByColumnIfPresent('class_teachers', 'teacher_id', $teacherProfileId);
                if (table_exists('classes')) {
                    if (table_has_column('classes', 'class_teacher_id')) {
                        update_flexible('classes', ['class_teacher_id' => null], 'class_teacher_id = ?', [$teacherProfileId]);
                    }
                    if (table_has_column('classes', 'teacher_id')) {
                        update_flexible('classes', ['teacher_id' => null], 'teacher_id = ?', [$teacherProfileId]);
                    }
                }
            }

            $this->deleteByColumnIfPresent('teachers', 'user_id', $userId);
            return;
        }

        if ($role === 'parent') {
            $this->deleteByColumnIfPresent('parent_student', 'parent_id', $userId);
            $this->deleteByColumnIfPresent('parent_student_links', 'parent_id', $userId);
            $this->deleteByColumnIfPresent('parent_student_links', 'parent_user_id', $userId);
        }
    }

    private function deleteCommonRelations(int $userId): void
    {
        $this->deleteByColumnIfPresent('tenant_users', 'user_id', $userId);
        $this->deleteByColumnIfPresent('notifications', 'user_id', $userId);
        $this->deleteByColumnIfPresent('notifications', 'recipient_id', $userId);
        $this->deleteByColumnIfPresent('audit_logs', 'user_id', $userId);
        $this->deleteByColumnIfPresent('biometric_credentials', 'user_id', $userId);
        $this->deleteByColumnIfPresent('biometric_auth_logs', 'user_id', $userId);
        $this->deleteByColumnIfPresent('attendance_biometric', 'user_id', $userId);
        $this->deleteByColumnIfPresent('user_sessions', 'user_id', $userId);
        $this->deleteByColumnIfPresent('remember_tokens', 'user_id', $userId);

        if (table_exists('messages')) {
            $clauses = [];
            $params = [];
            if (table_has_column('messages', 'sender_id')) {
                $clauses[] = 'sender_id = ?';
                $params[] = $userId;
            }
            if (table_has_column('messages', 'receiver_id')) {
                $clauses[] = 'receiver_id = ?';
                $params[] = $userId;
            }
            if (!empty($clauses)) {
                db()->delete('messages', implode(' OR ', $clauses), $params);
            }
        }
    }

    private function deleteByColumnIfPresent(string $table, string $column, int $value): void
    {
        if (!table_exists($table) || !table_has_column($table, $column)) {
            return;
        }

        db()->delete($table, "{$column} = ?", [$value]);
    }

    private function deleteUserFiles(int $userId, array $user): void
    {
        $uploadPath = PROJECT_ROOT . DIRECTORY_SEPARATOR . 'uploads';
        $directories = [
            $uploadPath . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR . $userId,
            $uploadPath . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $userId,
            $uploadPath . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR . $userId,
            $uploadPath . DIRECTORY_SEPARATOR . 'students' . DIRECTORY_SEPARATOR . $userId,
            $uploadPath . DIRECTORY_SEPARATOR . 'teachers' . DIRECTORY_SEPARATOR . $userId,
        ];

        foreach ($directories as $directory) {
            $this->deleteDirectory($directory);
        }

        $profilePhoto = $uploadPath . DIRECTORY_SEPARATOR . 'profiles' . DIRECTORY_SEPARATOR . (string)($user['email'] ?? '') . '.jpg';
        if (is_file($profilePhoto)) {
            @unlink($profilePhoto);
        }
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (array_diff(scandir($directory), ['.', '..']) as $entry) {
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    private function resolveExistingOrGeneratedId(array $user): string
    {
        $existing = trim((string)($user['assigned_student_id'] ?? $user['admission_number'] ?? $user['employee_id'] ?? $user['assigned_id'] ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        return $this->generateAssignedId((string)($user['role'] ?? 'user'));
    }

    private function generateAssignedId(string $role): string
    {
        $year = date('Y');
        $suffix = str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $role = strtolower($role);

        if ($role === 'student') {
            return 'STU' . $year . $suffix;
        }

        if ($role === 'teacher') {
            return 'TCH' . $year . $suffix;
        }

        return strtoupper(substr($role !== '' ? $role : 'usr', 0, 3)) . $year . $suffix;
    }

    private function normalizeIdList(array $userIds): array
    {
        $ids = [];
        foreach ($userIds as $userId) {
            $userId = (int)$userId;
            if ($userId > 0) {
                $ids[] = $userId;
            }
        }

        return array_values(array_unique($ids));
    }

    private function logAction(string $action, int $targetId, string $message): void
    {
        if ($this->actorId > 0 && function_exists('log_activity')) {
            log_activity($this->actorId, $action, 'users', $targetId, $message);
        }

        if (class_exists('AuditLogger')) {
            try {
                AuditLogger::log($action, 'users', $message, $this->actorId > 0 ? $this->actorId : null);
            } catch (Throwable $e) {
                error_log('Audit log write failed: ' . $e->getMessage());
            }
        }
    }

    private function failure(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'error' => $message,
        ];
    }
}
