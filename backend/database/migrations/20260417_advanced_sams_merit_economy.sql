ALTER TABLE school_tenants
    ADD COLUMN IF NOT EXISTS admin_user_id INT NULL,
    ADD COLUMN IF NOT EXISTS onboarding_status ENUM('pending', 'trial', 'payment_pending', 'active', 'suspended') NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS subscription_plan ENUM('trial', 'basic', 'standard', 'premium') NOT NULL DEFAULT 'trial',
    ADD COLUMN IF NOT EXISTS subscription_status ENUM('trial', 'active', 'suspended', 'expired') NOT NULL DEFAULT 'trial';

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS tenant_id INT NULL,
    ADD COLUMN IF NOT EXISTS school_id INT NULL,
    ADD COLUMN IF NOT EXISTS status ENUM('pending', 'active', 'inactive', 'suspended', 'expelled') NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS approved TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS approved_by INT NULL,
    ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(128) NULL,
    ADD COLUMN IF NOT EXISTS assigned_id VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS token_expires_at DATETIME NULL;

ALTER TABLE students
    ADD COLUMN IF NOT EXISTS tenant_id INT NULL,
    ADD COLUMN IF NOT EXISTS school_id INT NULL;

ALTER TABLE classes
    ADD COLUMN IF NOT EXISTS tenant_id INT NULL,
    ADD COLUMN IF NOT EXISTS school_id INT NULL;

ALTER TABLE class_enrollments
    ADD COLUMN IF NOT EXISTS tenant_id INT NULL;

UPDATE users u
LEFT JOIN tenant_users tu ON tu.user_id = u.id AND tu.is_active = 1
SET u.tenant_id = COALESCE(u.tenant_id, tu.tenant_id, u.school_id, 1),
    u.school_id = COALESCE(u.school_id, u.tenant_id, tu.tenant_id, 1)
WHERE u.tenant_id IS NULL OR u.school_id IS NULL;

UPDATE students
SET tenant_id = COALESCE(tenant_id, school_id, 1),
    school_id = COALESCE(school_id, tenant_id, 1)
WHERE tenant_id IS NULL OR school_id IS NULL;

UPDATE classes
SET tenant_id = COALESCE(tenant_id, school_id, 1),
    school_id = COALESCE(school_id, tenant_id, 1)
WHERE tenant_id IS NULL OR school_id IS NULL;

UPDATE class_enrollments ce
LEFT JOIN classes c ON c.id = ce.class_id
SET ce.tenant_id = COALESCE(ce.tenant_id, c.tenant_id, c.school_id, 1)
WHERE ce.tenant_id IS NULL;

CREATE TABLE IF NOT EXISTS school_invites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    invite_token VARCHAR(64) NOT NULL UNIQUE,
    status ENUM('pending', 'accepted', 'expired', 'cancelled') NOT NULL DEFAULT 'pending',
    expires_at DATETIME NOT NULL,
    created_by INT NOT NULL,
    accepted_by INT NULL,
    accepted_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_school_invites_tenant (tenant_id),
    INDEX idx_school_invites_email (email),
    CONSTRAINT fk_school_invites_tenant FOREIGN KEY (tenant_id) REFERENCES school_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_onboarding_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    step_key VARCHAR(100) NOT NULL,
    step_status ENUM('pending', 'completed', 'blocked') NOT NULL DEFAULT 'pending',
    completed_by INT NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tenant_step (tenant_id, step_key),
    CONSTRAINT fk_school_onboarding_tenant FOREIGN KEY (tenant_id) REFERENCES school_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_point_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    class_id INT NOT NULL,
    academic_session VARCHAR(20) NOT NULL,
    academic_term VARCHAR(50) NOT NULL,
    current_balance INT NOT NULL DEFAULT 0,
    account_status ENUM('active', 'archived', 'locked') NOT NULL DEFAULT 'active',
    last_snapshot_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_class_point_account (tenant_id, class_id, academic_session, academic_term),
    INDEX idx_class_point_tenant (tenant_id),
    CONSTRAINT fk_class_point_tenant FOREIGN KEY (tenant_id) REFERENCES school_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_point_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    class_point_account_id INT NOT NULL,
    class_id INT NOT NULL,
    source_type VARCHAR(50) NOT NULL,
    rule_code VARCHAR(100) NOT NULL,
    delta_points INT NOT NULL,
    before_balance INT NOT NULL,
    after_balance INT NOT NULL,
    actor_id INT NOT NULL,
    reason TEXT NULL,
    correlation_key VARCHAR(120) NOT NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_class_point_correlation (correlation_key),
    INDEX idx_class_point_ledger_tenant (tenant_id, class_id),
    CONSTRAINT fk_class_point_ledger_account FOREIGN KEY (class_point_account_id) REFERENCES class_point_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS private_point_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    student_id INT NOT NULL,
    current_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    account_status ENUM('active', 'frozen', 'closed') NOT NULL DEFAULT 'active',
    last_allowance_run_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_private_point_account (tenant_id, student_id),
    INDEX idx_private_point_tenant (tenant_id),
    CONSTRAINT fk_private_point_tenant FOREIGN KEY (tenant_id) REFERENCES school_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS private_point_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    private_point_account_id INT NOT NULL,
    student_id INT NOT NULL,
    entry_type ENUM('monthly_credit', 'transfer_in', 'transfer_out', 'special_exam_reward', 'fine', 'manual_adjustment', 'reversal') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'NGN',
    before_balance DECIMAL(12,2) NOT NULL,
    after_balance DECIMAL(12,2) NOT NULL,
    actor_id INT NOT NULL,
    reason TEXT NULL,
    correlation_key VARCHAR(120) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_private_point_correlation (correlation_key),
    INDEX idx_private_point_ledger_tenant (tenant_id, student_id),
    CONSTRAINT fk_private_point_ledger_account FOREIGN KEY (private_point_account_id) REFERENCES private_point_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS monthly_allowance_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    class_point_account_id INT NOT NULL,
    class_id INT NOT NULL,
    run_month CHAR(7) NOT NULL,
    class_points_snapshot INT NOT NULL,
    allowance_per_student DECIMAL(12,2) NOT NULL,
    student_count INT NOT NULL DEFAULT 0,
    run_checksum VARCHAR(64) NOT NULL,
    run_status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    processed_by INT NOT NULL,
    processed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_monthly_allowance (tenant_id, class_id, run_month, run_checksum),
    CONSTRAINT fk_monthly_allowance_account FOREIGN KEY (class_point_account_id) REFERENCES class_point_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merit_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    rule_code VARCHAR(100) NOT NULL,
    rule_name VARCHAR(150) NOT NULL,
    rule_category ENUM('attendance', 'behavior', 'academic', 'punctuality', 'special_exam', 'manual') NOT NULL,
    target_scope ENUM('student', 'class') NOT NULL DEFAULT 'student',
    point_delta INT NOT NULL,
    needs_approval TINYINT(1) NOT NULL DEFAULT 0,
    rule_status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    metadata JSON NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_merit_rule (tenant_id, rule_code),
    CONSTRAINT fk_merit_rules_tenant FOREIGN KEY (tenant_id) REFERENCES school_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merit_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    student_id INT NULL,
    class_id INT NULL,
    event_category ENUM('attendance', 'behavior', 'academic', 'punctuality', 'special_exam', 'manual', 'sanction', 'reward') NOT NULL,
    source_type VARCHAR(50) NOT NULL,
    source_id INT NULL,
    event_score DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    event_payload JSON NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_merit_events_tenant (tenant_id, event_category),
    CONSTRAINT fk_merit_events_tenant FOREIGN KEY (tenant_id) REFERENCES school_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS special_exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    exam_name VARCHAR(150) NOT NULL,
    scope_type ENUM('class', 'grade', 'school') NOT NULL DEFAULT 'class',
    eligibility_scope JSON NULL,
    rule_version VARCHAR(30) NOT NULL DEFAULT 'v1',
    stakes_summary TEXT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    exam_status ENUM('draft', 'active', 'closed', 'archived') NOT NULL DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_special_exams_tenant FOREIGN KEY (tenant_id) REFERENCES school_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS special_exam_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    special_exam_id INT NOT NULL,
    rule_key VARCHAR(100) NOT NULL,
    rule_type ENUM('score', 'reward', 'penalty', 'expulsion', 'tiebreaker') NOT NULL,
    rule_config JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_special_exam_rule (special_exam_id, rule_key),
    CONSTRAINT fk_special_exam_rules_exam FOREIGN KEY (special_exam_id) REFERENCES special_exams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS special_exam_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    special_exam_id INT NOT NULL,
    tenant_id INT NOT NULL,
    participant_type ENUM('student', 'class') NOT NULL,
    participant_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_special_exam_participant (special_exam_id, participant_type, participant_id),
    CONSTRAINT fk_special_exam_participants_exam FOREIGN KEY (special_exam_id) REFERENCES special_exams(id) ON DELETE CASCADE,
    CONSTRAINT fk_special_exam_participants_tenant FOREIGN KEY (tenant_id) REFERENCES school_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS special_exam_outcomes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    special_exam_id INT NOT NULL,
    tenant_id INT NOT NULL,
    participant_type ENUM('student', 'class') NOT NULL,
    participant_id INT NOT NULL,
    score_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    reward_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    penalty_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    outcome_status ENUM('pending', 'rewarded', 'penalized', 'expelled', 'restored') NOT NULL DEFAULT 'pending',
    audit_payload JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_special_exam_outcomes_exam FOREIGN KEY (special_exam_id) REFERENCES special_exams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enforcement_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    student_id INT NULL,
    user_id INT NULL,
    source_type VARCHAR(50) NOT NULL,
    source_id INT NULL,
    action_type ENUM('soft_deactivation', 'probation', 'restriction', 'restoration', 'appeal') NOT NULL,
    action_status ENUM('active', 'restored', 'appealed', 'cancelled') NOT NULL DEFAULT 'active',
    reason TEXT NOT NULL,
    review_notes TEXT NULL,
    reviewed_by INT NOT NULL,
    effective_at DATETIME NOT NULL,
    restored_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_enforcement_tenant (tenant_id, action_status),
    CONSTRAINT fk_enforcement_tenant FOREIGN KEY (tenant_id) REFERENCES school_tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
