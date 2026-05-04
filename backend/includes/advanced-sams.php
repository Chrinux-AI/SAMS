<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

class AdvancedSAMS
{
    public static function tableExists(string $tableName): bool
    {
        $row = db()->fetchOne(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
            [$tableName]
        );
        return (bool) $row;
    }

    public static function columnExists(string $tableName, string $columnName): bool
    {
        $row = db()->fetchOne(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$tableName, $columnName]
        );
        return (bool) $row;
    }

    public static function currentTenantId(): ?int
    {
        if (!empty($_SESSION['tenant_id'])) {
            return (int) $_SESSION['tenant_id'];
        }

        if (!empty($_SESSION['school_id'])) {
            return (int) $_SESSION['school_id'];
        }

        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $tenantId = self::resolveUserTenantId((int) $_SESSION['user_id']);
        if ($tenantId) {
            $_SESSION['tenant_id'] = $tenantId;
            $_SESSION['school_id'] = $tenantId;
        }

        return $tenantId;
    }

    public static function resolveUserTenantId(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        if (self::tableExists('tenant_users')) {
            $row = db()->fetchOne(
                'SELECT tenant_id FROM tenant_users WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1',
                [$userId]
            );
            if ($row && !empty($row['tenant_id'])) {
                return (int) $row['tenant_id'];
            }
        }

        if (self::columnExists('users', 'tenant_id')) {
            $row = db()->fetchOne('SELECT tenant_id, school_id FROM users WHERE id = ? LIMIT 1', [$userId]);
            if ($row) {
                if (!empty($row['tenant_id'])) {
                    return (int) $row['tenant_id'];
                }
                if (!empty($row['school_id'])) {
                    return (int) $row['school_id'];
                }
            }
        }

        if (self::columnExists('students', 'tenant_id')) {
            $row = db()->fetchOne('SELECT tenant_id, school_id FROM students WHERE user_id = ? LIMIT 1', [$userId]);
            if ($row) {
                if (!empty($row['tenant_id'])) {
                    return (int) $row['tenant_id'];
                }
                if (!empty($row['school_id'])) {
                    return (int) $row['school_id'];
                }
            }
        }

        return null;
    }

    public static function userCanAccess(): array
    {
        if (empty($_SESSION['user_id'])) {
            return [false, 'Not authenticated'];
        }

        $user = db()->fetchOne(
            'SELECT id, role, tenant_id, school_id, status, is_active, email_verified, approved FROM users WHERE id = ? LIMIT 1',
            [(int) $_SESSION['user_id']]
        );

        if (!$user) {
            return [false, 'User account not found'];
        }

        if (isset($user['is_active']) && (int) $user['is_active'] !== 1) {
            return [false, 'This account is inactive'];
        }

        if (!empty($user['status']) && in_array((string) $user['status'], ['inactive', 'suspended', 'expelled'], true)) {
            return [false, 'This account is restricted'];
        }

        $tenantId = self::currentTenantId();
        if ($tenantId && self::tableExists('school_tenants')) {
            $tenant = db()->fetchOne(
                'SELECT status, onboarding_status, subscription_status FROM school_tenants WHERE id = ? LIMIT 1',
                [$tenantId]
            );
            if ($tenant) {
                $tenantStatus = (string) ($tenant['status'] ?? 'active');
                $onboardingStatus = (string) ($tenant['onboarding_status'] ?? 'active');
                $subscriptionStatus = (string) ($tenant['subscription_status'] ?? 'active');

                if (in_array($tenantStatus, ['inactive', 'suspended'], true)
                    || in_array($onboardingStatus, ['payment_pending', 'suspended'], true)
                    || in_array($subscriptionStatus, ['suspended', 'expired'], true)) {
                    return [false, 'School access is currently restricted'];
                }
            }
        }

        if (self::tableExists('enforcement_actions')) {
            $restriction = db()->fetchOne(
                "SELECT id FROM enforcement_actions
                 WHERE user_id = ? AND action_status = 'active'
                 AND action_type IN ('soft_deactivation', 'restriction')
                 ORDER BY effective_at DESC, id DESC
                 LIMIT 1",
                [(int) $_SESSION['user_id']]
            );

            if ($restriction) {
                return [false, 'Your account has been restricted'];
            }
        }

        return [true, null];
    }

    public static function createSchoolRegistration(array $payload): array
    {
        if (!self::tableExists('school_tenants') || !self::tableExists('tenant_users')) {
            throw new RuntimeException('Tenant tables are not available in this installation.');
        }

        $tenantName = trim((string) ($payload['school_name'] ?? ''));
        $tenantSlug = strtolower(trim((string) ($payload['school_slug'] ?? $tenantName)));
        $tenantSlug = preg_replace('/[^a-z0-9-]+/', '-', $tenantSlug);
        $tenantSlug = trim((string) $tenantSlug, '-');
        if ($tenantSlug === '') {
            $tenantSlug = 'school-' . substr(bin2hex(random_bytes(3)), 0, 6);
        }

        db()->beginTransaction();

        try {
            $tenantId = db()->insert('school_tenants', array_filter([
                'name' => $tenantName,
                'slug' => $tenantSlug,
                'contact_email' => trim((string) ($payload['admin_email'] ?? '')),
                'status' => 'active',
                'onboarding_status' => 'pending',
                'subscription_plan' => 'trial',
                'subscription_status' => 'trial'
            ], static fn($value) => $value !== null));

            $passwordHash = password_hash((string) ($payload['password'] ?? ''), PASSWORD_DEFAULT);
            $userPayload = [
                'email' => trim((string) ($payload['admin_email'] ?? '')),
                'password' => $passwordHash,
                'full_name' => trim((string) (($payload['admin_first_name'] ?? '') . ' ' . ($payload['admin_last_name'] ?? ''))),
                'role' => 'admin',
                'is_active' => 1,
                'email_verified' => 0,
                'approved' => 0,
                'status' => 'pending',
                'first_name' => trim((string) ($payload['admin_first_name'] ?? '')),
                'last_name' => trim((string) ($payload['admin_last_name'] ?? '')),
                'email_verification_token' => bin2hex(random_bytes(32)),
                'assigned_id' => 'SCHADMIN' . str_pad((string) $tenantId, 5, '0', STR_PAD_LEFT),
                'tenant_id' => (int) $tenantId,
                'school_id' => (int) $tenantId,
                'token_expires_at' => date('Y-m-d H:i:s', strtotime('+10 minutes'))
            ];

            if (self::columnExists('users', 'username')) {
                $userPayload['username'] = trim((string) ($payload['admin_email'] ?? ''));
            }

            $userId = db()->insert('users', $userPayload);

            db()->insert('tenant_users', [
                'tenant_id' => (int) $tenantId,
                'user_id' => (int) $userId,
                'is_active' => 1,
                'role_override' => 'admin',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            if (self::columnExists('school_tenants', 'admin_user_id')) {
                db()->update('school_tenants', ['admin_user_id' => (int) $userId], 'id = ?', [(int) $tenantId]);
            }

            if (self::tableExists('school_onboarding_steps')) {
                db()->insert('school_onboarding_steps', [
                    'tenant_id' => (int) $tenantId,
                    'step_key' => 'school_registration',
                    'step_status' => 'completed',
                    'completed_by' => (int) $userId,
                    'completed_at' => date('Y-m-d H:i:s')
                ]);

                db()->insert('school_onboarding_steps', [
                    'tenant_id' => (int) $tenantId,
                    'step_key' => 'admin_verification',
                    'step_status' => 'pending'
                ]);
            }

            db()->commit();

            return [
                'tenant_id' => (int) $tenantId,
                'user_id' => (int) $userId
            ];
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $e;
        }
    }

    public static function createInvite(int $tenantId, int $actorId, array $payload): array
    {
        if (!self::tableExists('school_invites')) {
            throw new RuntimeException('Advanced SAMS migration has not been run yet.');
        }

        $token = bin2hex(random_bytes(24));
        $inviteId = db()->insert('school_invites', [
            'tenant_id' => $tenantId,
            'email' => trim((string) ($payload['email'] ?? '')),
            'role' => trim((string) ($payload['role'] ?? 'teacher')),
            'invite_token' => $token,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'created_by' => $actorId
        ]);

        return [
            'id' => (int) $inviteId,
            'token' => $token
        ];
    }

    public static function redeemInvite(array $payload): int
    {
        if (!self::tableExists('school_invites')) {
            throw new RuntimeException('Advanced SAMS migration has not been run yet.');
        }

        $invite = db()->fetchOne(
            "SELECT * FROM school_invites
             WHERE invite_token = ? AND status = 'pending' AND expires_at > NOW()
             LIMIT 1",
            [trim((string) ($payload['invite_token'] ?? ''))]
        );

        if (!$invite) {
            throw new RuntimeException('Invalid or expired invite');
        }

        if (strcasecmp((string) $invite['email'], trim((string) ($payload['email'] ?? ''))) !== 0) {
            throw new RuntimeException('Invite email does not match');
        }

        db()->beginTransaction();

        try {
            $passwordHash = password_hash((string) ($payload['password'] ?? ''), PASSWORD_DEFAULT);
            $userPayload = [
                'email' => trim((string) ($payload['email'] ?? '')),
                'password' => $passwordHash,
                'full_name' => trim((string) (($payload['first_name'] ?? '') . ' ' . ($payload['last_name'] ?? ''))),
                'role' => (string) $invite['role'],
                'is_active' => 1,
                'email_verified' => 1,
                'approved' => 0,
                'status' => 'pending',
                'first_name' => trim((string) ($payload['first_name'] ?? '')),
                'last_name' => trim((string) ($payload['last_name'] ?? '')),
                'tenant_id' => (int) $invite['tenant_id'],
                'school_id' => (int) $invite['tenant_id']
            ];

            if (self::columnExists('users', 'username')) {
                $userPayload['username'] = trim((string) ($payload['email'] ?? ''));
            }

            $userId = db()->insert('users', $userPayload);

            if (self::tableExists('tenant_users')) {
                db()->insert('tenant_users', [
                    'tenant_id' => (int) $invite['tenant_id'],
                    'user_id' => (int) $userId,
                    'is_active' => 1,
                    'role_override' => (string) $invite['role'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }

            db()->update(
                'school_invites',
                [
                    'status' => 'accepted',
                    'accepted_by' => (int) $userId,
                    'accepted_at' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [(int) $invite['id']]
            );

            db()->commit();
            return (int) $userId;
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $e;
        }
    }

    public static function getOrCreateClassPointAccount(int $tenantId, int $classId, string $sessionName, string $termName): array
    {
        $account = db()->fetchOne(
            'SELECT * FROM class_point_accounts WHERE tenant_id = ? AND class_id = ? AND academic_session = ? AND academic_term = ? LIMIT 1',
            [$tenantId, $classId, $sessionName, $termName]
        );

        if ($account) {
            return $account;
        }

        $id = db()->insert('class_point_accounts', [
            'tenant_id' => $tenantId,
            'class_id' => $classId,
            'academic_session' => $sessionName,
            'academic_term' => $termName,
            'current_balance' => 0,
            'account_status' => 'active'
        ]);

        return db()->fetchOne('SELECT * FROM class_point_accounts WHERE id = ? LIMIT 1', [$id]) ?: [];
    }

    public static function postClassPointLedger(array $payload): int
    {
        $tenantId = (int) $payload['tenant_id'];
        $classId = (int) $payload['class_id'];
        $account = self::getOrCreateClassPointAccount(
            $tenantId,
            $classId,
            (string) $payload['academic_session'],
            (string) $payload['academic_term']
        );

        $correlationKey = (string) $payload['correlation_key'];
        $existing = db()->fetchOne('SELECT id FROM class_point_ledger WHERE correlation_key = ? LIMIT 1', [$correlationKey]);
        if ($existing) {
            return (int) $existing['id'];
        }

        $before = (int) ($account['current_balance'] ?? 0);
        $delta = (int) $payload['delta'];
        $after = $before + $delta;

        db()->beginTransaction();

        try {
            $ledgerId = db()->insert('class_point_ledger', [
                'tenant_id' => $tenantId,
                'class_point_account_id' => (int) $account['id'],
                'class_id' => $classId,
                'source_type' => (string) $payload['source_type'],
                'rule_code' => (string) $payload['rule_code'],
                'delta_points' => $delta,
                'before_balance' => $before,
                'after_balance' => $after,
                'actor_id' => (int) $payload['actor_id'],
                'reason' => (string) $payload['reason'],
                'correlation_key' => $correlationKey,
                'approved_at' => date('Y-m-d H:i:s')
            ]);

            db()->update(
                'class_point_accounts',
                ['current_balance' => $after, 'last_snapshot_at' => date('Y-m-d H:i:s')],
                'id = ?',
                [(int) $account['id']]
            );

            db()->commit();
            return (int) $ledgerId;
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $e;
        }
    }

    public static function getOrCreatePrivatePointAccount(int $tenantId, int $studentId): array
    {
        $account = db()->fetchOne(
            'SELECT * FROM private_point_accounts WHERE tenant_id = ? AND student_id = ? LIMIT 1',
            [$tenantId, $studentId]
        );

        if ($account) {
            return $account;
        }

        $id = db()->insert('private_point_accounts', [
            'tenant_id' => $tenantId,
            'student_id' => $studentId,
            'current_balance' => 0,
            'account_status' => 'active'
        ]);

        return db()->fetchOne('SELECT * FROM private_point_accounts WHERE id = ? LIMIT 1', [$id]) ?: [];
    }

    public static function postPrivatePointLedger(array $payload): int
    {
        $tenantId = (int) $payload['tenant_id'];
        $studentId = (int) $payload['student_id'];
        $account = self::getOrCreatePrivatePointAccount($tenantId, $studentId);
        $correlationKey = (string) $payload['correlation_key'];

        $existing = db()->fetchOne('SELECT id FROM private_point_ledger WHERE correlation_key = ? LIMIT 1', [$correlationKey]);
        if ($existing) {
            return (int) $existing['id'];
        }

        $before = (float) ($account['current_balance'] ?? 0);
        $delta = (float) $payload['amount'];
        $after = $before + $delta;

        db()->beginTransaction();

        try {
            $ledgerId = db()->insert('private_point_ledger', [
                'tenant_id' => $tenantId,
                'private_point_account_id' => (int) $account['id'],
                'student_id' => $studentId,
                'entry_type' => (string) $payload['entry_type'],
                'amount' => $delta,
                'currency_code' => 'NGN',
                'before_balance' => $before,
                'after_balance' => $after,
                'actor_id' => (int) $payload['actor_id'],
                'reason' => (string) $payload['reason'],
                'correlation_key' => $correlationKey
            ]);

            db()->update(
                'private_point_accounts',
                ['current_balance' => $after, 'last_allowance_run_at' => date('Y-m-d H:i:s')],
                'id = ?',
                [(int) $account['id']]
            );

            db()->commit();
            return (int) $ledgerId;
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $e;
        }
    }

    public static function runMonthlyAllowance(
        int $tenantId,
        int $classId,
        string $sessionName,
        string $termName,
        string $runMonth,
        int $actorId
    ): array {
        $account = self::getOrCreateClassPointAccount($tenantId, $classId, $sessionName, $termName);
        $classPoints = (int) ($account['current_balance'] ?? 0);
        $creditAmount = $classPoints * 100;
        $checksum = hash('sha256', implode('|', [$tenantId, $classId, $sessionName, $termName, $runMonth, $creditAmount]));

        $existingRun = db()->fetchOne(
            'SELECT id FROM monthly_allowance_runs WHERE tenant_id = ? AND class_id = ? AND run_month = ? AND run_checksum = ? LIMIT 1',
            [$tenantId, $classId, $runMonth, $checksum]
        );
        if ($existingRun) {
            return [
                'run_id' => (int) $existingRun['id'],
                'credits_issued' => 0,
                'allowance' => $creditAmount,
                'message' => 'Allowance already processed'
            ];
        }

        $students = db()->fetchAll(
            "SELECT DISTINCT s.id
             FROM students s
             LEFT JOIN class_enrollments ce
                ON ce.student_id = s.id
               AND ce.class_id = ?
               AND ce.status IN ('active', 'enrolled')
             WHERE (s.class_id = ? OR ce.class_id = ?)
               AND COALESCE(s.tenant_id, s.school_id, ?) = ?",
            [$classId, $classId, $classId, $tenantId, $tenantId]
        );

        db()->beginTransaction();

        try {
            $runId = db()->insert('monthly_allowance_runs', [
                'tenant_id' => $tenantId,
                'class_point_account_id' => (int) $account['id'],
                'class_id' => $classId,
                'run_month' => $runMonth,
                'class_points_snapshot' => $classPoints,
                'allowance_per_student' => $creditAmount,
                'student_count' => count($students),
                'run_checksum' => $checksum,
                'run_status' => 'completed',
                'processed_by' => $actorId,
                'processed_at' => date('Y-m-d H:i:s')
            ]);

            foreach ($students as $student) {
                self::postPrivatePointLedger([
                    'tenant_id' => $tenantId,
                    'student_id' => (int) $student['id'],
                    'entry_type' => 'monthly_credit',
                    'amount' => $creditAmount,
                    'actor_id' => $actorId,
                    'reason' => 'Monthly allowance generated from class points snapshot',
                    'correlation_key' => "allowance:{$runId}:student:{$student['id']}"
                ]);
            }

            db()->commit();

            return [
                'run_id' => (int) $runId,
                'credits_issued' => count($students),
                'allowance' => $creditAmount
            ];
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            throw $e;
        }
    }

    public static function createMeritEvent(array $payload): int
    {
        return (int) db()->insert('merit_events', [
            'tenant_id' => (int) $payload['tenant_id'],
            'student_id' => !empty($payload['student_id']) ? (int) $payload['student_id'] : null,
            'class_id' => !empty($payload['class_id']) ? (int) $payload['class_id'] : null,
            'event_category' => (string) $payload['event_category'],
            'source_type' => (string) $payload['source_type'],
            'source_id' => !empty($payload['source_id']) ? (int) $payload['source_id'] : null,
            'event_score' => (float) ($payload['event_score'] ?? 0),
            'event_payload' => json_encode($payload['event_payload'] ?? []),
            'created_by' => (int) $payload['created_by']
        ]);
    }

    public static function createSpecialExam(array $payload): int
    {
        return (int) db()->insert('special_exams', [
            'tenant_id' => (int) $payload['tenant_id'],
            'exam_name' => trim((string) $payload['exam_name']),
            'scope_type' => trim((string) $payload['scope_type']),
            'eligibility_scope' => json_encode($payload['eligibility_scope'] ?? []),
            'rule_version' => trim((string) ($payload['rule_version'] ?? 'v1')),
            'stakes_summary' => trim((string) ($payload['stakes_summary'] ?? '')),
            'starts_at' => trim((string) $payload['starts_at']),
            'ends_at' => trim((string) $payload['ends_at']),
            'created_by' => (int) $payload['created_by']
        ]);
    }

    public static function recordEnforcementAction(array $payload): int
    {
        $actionId = db()->insert('enforcement_actions', [
            'tenant_id' => (int) $payload['tenant_id'],
            'student_id' => !empty($payload['student_id']) ? (int) $payload['student_id'] : null,
            'user_id' => !empty($payload['user_id']) ? (int) $payload['user_id'] : null,
            'source_type' => trim((string) $payload['source_type']),
            'source_id' => !empty($payload['source_id']) ? (int) $payload['source_id'] : null,
            'action_type' => trim((string) $payload['action_type']),
            'action_status' => 'active',
            'reason' => trim((string) $payload['reason']),
            'review_notes' => trim((string) ($payload['review_notes'] ?? '')),
            'reviewed_by' => (int) $payload['reviewed_by'],
            'effective_at' => date('Y-m-d H:i:s')
        ]);

        if (!empty($payload['user_id']) && in_array((string) $payload['action_type'], ['soft_deactivation', 'restriction'], true)) {
            $updates = ['status' => 'inactive'];
            if (self::columnExists('users', 'is_active')) {
                $updates['is_active'] = 0;
            }
            db()->update('users', $updates, 'id = ?', [(int) $payload['user_id']]);
        }

        return (int) $actionId;
    }

    public static function restoreEnforcementAction(int $actionId, int $reviewerId): bool
    {
        $action = db()->fetchOne('SELECT * FROM enforcement_actions WHERE id = ? LIMIT 1', [$actionId]);
        if (!$action) {
            throw new RuntimeException('Enforcement action not found');
        }

        db()->update(
            'enforcement_actions',
            [
                'action_status' => 'restored',
                'reviewed_by' => $reviewerId,
                'restored_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$actionId]
        );

        if (!empty($action['user_id'])) {
            $updates = ['status' => 'active'];
            if (self::columnExists('users', 'is_active')) {
                $updates['is_active'] = 1;
            }
            db()->update('users', $updates, 'id = ?', [(int) $action['user_id']]);
        }

        return true;
    }
}
