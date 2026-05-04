<?php

declare(strict_types=1);

require_once __DIR__ . '/advanced-sams.php';

function sams_merit_default_attendance_rules(): array
{
    return [
        'present' => ['delta' => 2, 'rule_code' => 'attendance_present', 'label' => 'Present attendance'],
        'late' => ['delta' => 1, 'rule_code' => 'attendance_late', 'label' => 'Late attendance'],
        'absent' => ['delta' => -1, 'rule_code' => 'attendance_absent', 'label' => 'Absent attendance'],
        'excused' => ['delta' => 0, 'rule_code' => 'attendance_excused', 'label' => 'Excused attendance'],
        'early' => ['delta' => 1, 'rule_code' => 'attendance_early', 'label' => 'Early attendance']
    ];
}

function sams_merit_default_behavior_rules(): array
{
    return [
        'positive' => ['minor' => 1, 'moderate' => 2, 'major' => 3, 'severe' => 5],
        'neutral' => ['minor' => 0, 'moderate' => 0, 'major' => 0, 'severe' => 0],
        'negative' => ['minor' => -1, 'moderate' => -2, 'major' => -3, 'severe' => -5]
    ];
}

function sams_merit_default_grade_rules(): array
{
    return [
        ['min' => 90, 'delta' => 5, 'rule_code' => 'academic_grade_excellent', 'label' => 'Excellent academic performance'],
        ['min' => 80, 'delta' => 3, 'rule_code' => 'academic_grade_strong', 'label' => 'Strong academic performance'],
        ['min' => 70, 'delta' => 1, 'rule_code' => 'academic_grade_good', 'label' => 'Good academic performance'],
        ['min' => 60, 'delta' => 0, 'rule_code' => 'academic_grade_pass', 'label' => 'Pass grade'],
        ['min' => 0, 'delta' => -2, 'rule_code' => 'academic_grade_risk', 'label' => 'Academic risk']
    ];
}

function sams_merit_resolve_tenant_id_for_class(int $classId): ?int
{
    if ($classId <= 0) {
        return AdvancedSAMS::currentTenantId();
    }

    $class = db()->fetchOne('SELECT tenant_id, school_id FROM classes WHERE id = ? LIMIT 1', [$classId]);
    if ($class) {
        if (!empty($class['tenant_id'])) {
            return (int) $class['tenant_id'];
        }
        if (!empty($class['school_id'])) {
            return (int) $class['school_id'];
        }
    }

    return AdvancedSAMS::currentTenantId();
}

function sams_merit_resolve_student_class_id(int $studentId): ?int
{
    if ($studentId <= 0) {
        return null;
    }

    if (AdvancedSAMS::tableExists('class_enrollments')) {
        $enrollment = db()->fetchOne(
            "SELECT class_id
             FROM class_enrollments
             WHERE student_id = ?
             ORDER BY id DESC
             LIMIT 1",
            [$studentId]
        );
        if ($enrollment && !empty($enrollment['class_id'])) {
            return (int) $enrollment['class_id'];
        }
    }

    if (AdvancedSAMS::tableExists('students')) {
        $student = db()->fetchOne(
            "SELECT class_id
             FROM students
             WHERE id = ?
             LIMIT 1",
            [$studentId]
        );
        if ($student && !empty($student['class_id'])) {
            return (int) $student['class_id'];
        }
    }

    return null;
}

function sams_merit_rule_delta(int $tenantId, string $ruleCode, int $fallbackDelta): int
{
    if ($tenantId <= 0 || $ruleCode === '' || !AdvancedSAMS::tableExists('merit_rules')) {
        return $fallbackDelta;
    }

    $scopeColumn = 'tenant_id';
    if (!db()->fetchOne("SHOW COLUMNS FROM merit_rules LIKE 'tenant_id'")) {
        $scopeColumn = 'school_id';
    }

    $rule = db()->fetchOne(
        "SELECT point_delta
         FROM merit_rules
         WHERE {$scopeColumn} = ? AND rule_code = ? AND rule_status = 'active'
         ORDER BY id DESC
         LIMIT 1",
        [$tenantId, $ruleCode]
    );

    if (!$rule || !isset($rule['point_delta'])) {
        return $fallbackDelta;
    }

    return (int) $rule['point_delta'];
}

function sams_merit_current_session_label(): string
{
    $year = (int) date('Y');
    $month = (int) date('n');
    if ($month >= 9) {
        return $year . '/' . ($year + 1);
    }
    return ($year - 1) . '/' . $year;
}

function sams_merit_current_term_label(): string
{
    $month = (int) date('n');
    if ($month >= 9 || $month <= 12) {
        return 'Term 1';
    }
    if ($month >= 1 && $month <= 4) {
        return 'Term 2';
    }
    return 'Term 3';
}

function sams_sync_attendance_merit(
    int $attendanceId,
    int $studentId,
    int $classId,
    string $status,
    int $actorId,
    ?string $attendanceDate = null,
    string $sourceType = 'attendance_record'
): void {
    if ($attendanceId <= 0 || $studentId <= 0 || $classId <= 0) {
        return;
    }

    if (!AdvancedSAMS::tableExists('merit_events') || !AdvancedSAMS::tableExists('class_point_ledger')) {
        return;
    }

    $status = strtolower(trim($status));
    $rules = sams_merit_default_attendance_rules();
    if (!isset($rules[$status])) {
        $status = 'excused';
    }

    $rule = $rules[$status];
    $tenantId = sams_merit_resolve_tenant_id_for_class($classId);
    if (!$tenantId) {
        return;
    }
    $rule['delta'] = sams_merit_rule_delta($tenantId, (string) $rule['rule_code'], (int) $rule['delta']);

    $attendanceDate = $attendanceDate ?: date('Y-m-d');
    $sessionLabel = sams_merit_current_session_label();
    $termLabel = sams_merit_current_term_label();

    $latestEvent = db()->fetchOne(
        "SELECT *
         FROM merit_events
         WHERE tenant_id = ? AND source_type = ? AND source_id = ?
         ORDER BY id DESC
         LIMIT 1",
        [$tenantId, $sourceType, $attendanceId]
    );

    $latestPayload = [];
    if ($latestEvent && !empty($latestEvent['event_payload'])) {
        $decoded = json_decode((string) $latestEvent['event_payload'], true);
        $latestPayload = is_array($decoded) ? $decoded : [];
    }

    $previousStatus = strtolower((string) ($latestPayload['status'] ?? ''));
    $previousDelta = (int) ($latestPayload['point_delta'] ?? 0);

    if ($previousStatus === $status && $latestEvent) {
        return;
    }

    if ($latestEvent && $previousStatus !== '' && $previousDelta !== 0) {
        AdvancedSAMS::postClassPointLedger([
            'tenant_id' => $tenantId,
            'class_id' => $classId,
            'academic_session' => $sessionLabel,
            'academic_term' => $termLabel,
            'source_type' => $sourceType . '_reversal',
            'rule_code' => 'attendance_reversal',
            'delta' => -$previousDelta,
            'actor_id' => $actorId,
            'reason' => 'Attendance merit reversal for updated status',
            'correlation_key' => "attendance:{$attendanceId}:reversal:event:{$latestEvent['id']}"
        ]);
    }

    if ((int) $rule['delta'] !== 0) {
        AdvancedSAMS::postClassPointLedger([
            'tenant_id' => $tenantId,
            'class_id' => $classId,
            'academic_session' => $sessionLabel,
            'academic_term' => $termLabel,
            'source_type' => $sourceType,
            'rule_code' => (string) $rule['rule_code'],
            'delta' => (int) $rule['delta'],
            'actor_id' => $actorId,
            'reason' => $rule['label'] . ' on ' . $attendanceDate,
            'correlation_key' => "attendance:{$attendanceId}:status:{$status}"
        ]);
    }

    AdvancedSAMS::createMeritEvent([
        'tenant_id' => $tenantId,
        'student_id' => $studentId,
        'class_id' => $classId,
        'event_category' => 'attendance',
        'source_type' => $sourceType,
        'source_id' => $attendanceId,
        'event_score' => (int) $rule['delta'],
        'event_payload' => [
            'attendance_id' => $attendanceId,
            'status' => $status,
            'point_delta' => (int) $rule['delta'],
            'attendance_date' => $attendanceDate
        ],
        'created_by' => $actorId
    ]);
}

function sams_sync_behavior_merit(
    int $behaviorLogId,
    int $studentId,
    ?int $classId,
    string $type,
    string $severity,
    int $actorId,
    ?string $incidentDate = null,
    string $description = '',
    string $sourceType = 'behavior_log'
): void {
    if ($behaviorLogId <= 0 || $studentId <= 0 || !AdvancedSAMS::tableExists('merit_events')) {
        return;
    }

    $type = strtolower(trim($type));
    $severity = strtolower(trim($severity));
    $behaviorRules = sams_merit_default_behavior_rules();
    $classId = $classId && $classId > 0 ? $classId : sams_merit_resolve_student_class_id($studentId);

    if (!isset($behaviorRules[$type][$severity])) {
        $type = 'neutral';
        $severity = 'minor';
    }

    $tenantId = $classId ? sams_merit_resolve_tenant_id_for_class((int) $classId) : AdvancedSAMS::currentTenantId();
    if (!$tenantId) {
        return;
    }

    $ruleCode = 'behavior_' . $type . '_' . $severity;
    $delta = sams_merit_rule_delta($tenantId, $ruleCode, (int) $behaviorRules[$type][$severity]);
    $incidentDate = $incidentDate ?: date('Y-m-d');

    if ($classId && $delta !== 0 && AdvancedSAMS::tableExists('class_point_ledger')) {
        AdvancedSAMS::postClassPointLedger([
            'tenant_id' => $tenantId,
            'class_id' => (int) $classId,
            'academic_session' => sams_merit_current_session_label(),
            'academic_term' => sams_merit_current_term_label(),
            'source_type' => $sourceType,
            'rule_code' => $ruleCode,
            'delta' => $delta,
            'actor_id' => $actorId,
            'reason' => 'Behavior merit update on ' . $incidentDate,
            'correlation_key' => "behavior:{$behaviorLogId}:{$ruleCode}"
        ]);
    }

    AdvancedSAMS::createMeritEvent([
        'tenant_id' => $tenantId,
        'student_id' => $studentId,
        'class_id' => $classId ? (int) $classId : null,
        'event_category' => 'behavior',
        'source_type' => $sourceType,
        'source_id' => $behaviorLogId,
        'event_score' => $delta,
        'event_payload' => [
            'behavior_log_id' => $behaviorLogId,
            'type' => $type,
            'severity' => $severity,
            'description' => $description,
            'incident_date' => $incidentDate,
            'point_delta' => $delta
        ],
        'created_by' => $actorId
    ]);
}

function sams_sync_grade_merit(
    int $gradeId,
    int $studentId,
    int $classId,
    float $percentage,
    int $actorId,
    ?string $gradeDate = null,
    string $sourceType = 'grade_record'
): void {
    if ($gradeId <= 0 || $studentId <= 0 || $classId <= 0 || !AdvancedSAMS::tableExists('merit_events')) {
        return;
    }

    $tenantId = sams_merit_resolve_tenant_id_for_class($classId);
    if (!$tenantId) {
        return;
    }

    $gradeRule = sams_merit_default_grade_rules()[count(sams_merit_default_grade_rules()) - 1];
    foreach (sams_merit_default_grade_rules() as $candidate) {
        if ($percentage >= (float) $candidate['min']) {
            $gradeRule = $candidate;
            break;
        }
    }

    $delta = sams_merit_rule_delta($tenantId, (string) $gradeRule['rule_code'], (int) $gradeRule['delta']);
    $gradeDate = $gradeDate ?: date('Y-m-d');

    if ($delta !== 0 && AdvancedSAMS::tableExists('class_point_ledger')) {
        AdvancedSAMS::postClassPointLedger([
            'tenant_id' => $tenantId,
            'class_id' => $classId,
            'academic_session' => sams_merit_current_session_label(),
            'academic_term' => sams_merit_current_term_label(),
            'source_type' => $sourceType,
            'rule_code' => (string) $gradeRule['rule_code'],
            'delta' => $delta,
            'actor_id' => $actorId,
            'reason' => $gradeRule['label'] . ' on ' . $gradeDate,
            'correlation_key' => "grade:{$gradeId}:rule:" . $gradeRule['rule_code']
        ]);
    }

    AdvancedSAMS::createMeritEvent([
        'tenant_id' => $tenantId,
        'student_id' => $studentId,
        'class_id' => $classId,
        'event_category' => 'academic',
        'source_type' => $sourceType,
        'source_id' => $gradeId,
        'event_score' => $delta,
        'event_payload' => [
            'grade_id' => $gradeId,
            'percentage' => round($percentage, 2),
            'point_delta' => $delta,
            'grade_date' => $gradeDate,
            'rule_code' => $gradeRule['rule_code']
        ],
        'created_by' => $actorId
    ]);
}
